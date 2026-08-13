<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/models/M_LectorHoja.php';
require_once dirname(__DIR__) . '/models/M_Cruce.php';
require_once dirname(__DIR__) . '/models/M_Nivel.php';
require_once dirname(__DIR__) . '/models/M_Alumno.php';
require_once dirname(__DIR__) . '/models/M_Pago.php';

/**
 * Orquesta las dos importaciones (padrón / comprobantes) dentro de una única
 * transacción PDO cada una, y deja el resumen persistido en `importaciones`.
 */
class M_Importacion {
    private static $instancia = null;
    private $conexion;

    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    // ============================== PADRÓN ====================================

    /**
     * Autómata de estados sobre las filas del padrón: una fila "INSTRUCCIÓN :"
     * fija Nivel/Grado/Sección para las filas de alumno que le siguen, hasta la
     * próxima fila "INSTRUCCIÓN :" (o "Total de Alumnos:").
     */
    private function parsearFilasPadron(array $filas): array {
        $alumnos = [];
        $nivel = $grado = $seccion = null;
        $bloques = 0;

        foreach ($filas as $fila) {
            $c0 = trim((string) ($fila[0] ?? ''));

            if ($c0 === 'INSTRUCCIÓN :') {
                $nivel = trim((string) ($fila[3] ?? ''));
                $grado = trim((string) ($fila[7] ?? ''));
                $seccion = trim((string) ($fila[14] ?? ''));
                $bloques++;
                continue;
            }

            if ($c0 !== '' && is_numeric($c0)) {
                $codigo = trim((string) ($fila[1] ?? ''));
                if ($codigo === '' || $codigo[0] !== 'A' || $nivel === null) {
                    continue; // fila de cabecera "N° | Código | ..." u otra cosa
                }
                $nombre = trim((string) ($fila[4] ?? ''));
                $estadoMatricula = trim((string) ($fila[16] ?? ''));
                $alumnos[] = [
                    'codigo' => $codigo,
                    'nombre_completo' => $nombre,
                    'nombre_normalizado' => M_Cruce::normalizarNombre($nombre),
                    'nivel_texto' => $nivel,
                    'grado_texto' => $grado,
                    'seccion' => $seccion,
                    'matriculado' => (stripos($estadoMatricula, 'no matriculado') === 0) ? 0 : 1,
                ];
            }
        }

        if ($bloques === 0) {
            throw new Exception('Este archivo no parece el Padrón de Alumnos: no se encontró ningún bloque "INSTRUCCIÓN :". ¿Subiste el archivo correcto?');
        }

        return $alumnos;
    }

    public function importarPadron(string $rutaArchivo, ?string $hoja, string $nombreArchivo, int $idUsuario): array {
        $filas = M_LectorHoja::leer($rutaArchivo, $hoja, null);
        $alumnosFila = $this->parsearFilasPadron($filas);

        $modeloNivel = M_Nivel::singleton();
        $modeloAlumno = M_Alumno::singleton();

        $resumen = ['filas_leidas' => count($alumnosFila), 'nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'omitidos' => 0];

        $this->conexion->beginTransaction();
        try {
            // La importación necesita su propio id antes de poder referenciarlo
            // desde cada alumno; se corrige el resumen con un UPDATE al final.
            $idImportacion = $this->crearRegistroImportacion(1, $nombreArchivo, $hoja, $rutaArchivo, $idUsuario);

            foreach ($alumnosFila as $a) {
                if ($a['codigo'] === '' || $a['nombre_completo'] === '') {
                    $resumen['omitidos']++;
                    continue;
                }
                $idNivel = $modeloNivel->resolverNivel($a['nivel_texto']);
                $idGrado = $idNivel !== null && $a['grado_texto'] !== '' ? $modeloNivel->resolverGrado($idNivel, $a['grado_texto']) : null;

                $resultado = $modeloAlumno->upsertDesdeImportacion([
                    'codigo' => $a['codigo'],
                    'nombre_completo' => $a['nombre_completo'],
                    'nombre_normalizado' => $a['nombre_normalizado'],
                    'id_nivel' => $idNivel,
                    'id_grado' => $idGrado,
                    'seccion' => $a['seccion'],
                    'matriculado' => $a['matriculado'],
                ], $idImportacion);

                $resumen[$resultado === 'nuevo' ? 'nuevos' : ($resultado === 'actualizado' ? 'actualizados' : 'sin_cambios')]++;
            }

            $this->cerrarRegistroImportacion($idImportacion, $resumen);
            $this->conexion->commit();

            $resumen['id_importacion'] = $idImportacion;
            return $resumen;
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    // =========================== COMPROBANTES ==================================

    /**
     * Comprobantes: tabla plana con cabecera en la fila 0. Se mapea por NOMBRE
     * de columna (no por posición). Por defecto importa TODO el histórico de
     * pensiones del archivo de una sola vez — cada fila se clasifica por su
     * propio mes/año (extraído del CONCEPTO), y el filtrado por periodo
     * ("¿quién pagó agosto?") se hace después, al consultar Pagos/Promociones/
     * Entregas, no al importar. $mes/$anio quedan como filtro OPCIONAL para el
     * caso raro de querer restringir una carga puntual a un solo periodo.
     */
    public function importarComprobantes(string $rutaArchivo, ?string $hoja, string $nombreArchivo, ?int $mes, ?int $anio, int $idUsuario): array {
        $filas = M_LectorHoja::leer($rutaArchivo, $hoja, null);
        if (empty($filas)) {
            throw new Exception('El archivo de comprobantes está vacío.');
        }

        $cabecera = array_map(fn($h) => M_Cruce::normalizarNombre((string) $h), $filas[0]);
        $col = function (string $nombreEsperado) use ($cabecera): ?int {
            $idx = array_search(M_Cruce::normalizarNombre($nombreEsperado), $cabecera, true);
            return $idx === false ? null : $idx;
        };

        $iNumero = $col('NUMERO');
        $iNombre = $col('NOMBRE');
        $iFechaPago = $col('FECHA PAGO');
        $iConcepto = $col('CONCEPTO');
        $iObservacion = $col('OBSERVACION');
        $iMonto = $col('MONTO');
        $iMora = $col('MORA');
        $iDesc = $col('DESC');
        $iTotal = $col('TOTAL');

        if ($iNumero === null || $iNombre === null || $iConcepto === null || $iMonto === null) {
            throw new Exception('Este archivo no parece el Reporte de Comprobantes: faltan columnas obligatorias (NÚMERO, NOMBRE, CONCEPTO, MONTO). ¿Subiste el archivo correcto?');
        }

        $modeloAlumno = M_Alumno::singleton();
        $modeloPago = M_Pago::singleton();
        $mapaNombres = $modeloAlumno->mapaNormalizados();

        $resumen = ['filas_leidas' => 0, 'nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'omitidos' => 0, 'sin_cruce' => 0];

        $this->conexion->beginTransaction();
        try {
            $idImportacion = $this->crearRegistroImportacion(2, $nombreArchivo, $hoja, $rutaArchivo, $idUsuario, $mes, $anio);

            for ($i = 1; $i < count($filas); $i++) {
                $fila = $filas[$i];
                $concepto = trim((string) ($fila[$iConcepto] ?? ''));
                if ($concepto === '') {
                    continue;
                }

                [$mesConcepto, $anioConcepto] = M_Cruce::parsearConcepto($concepto);
                if ($mesConcepto === null || $anioConcepto === null) {
                    continue; // no es un concepto de pensión reconocible (matrícula, materiales, etc.) — no cuenta como "leída"
                }
                // Filtro opcional: solo si el usuario pidió restringir a un periodo puntual.
                if ($mes !== null && $anio !== null && ($mesConcepto !== $mes || $anioConcepto !== $anio)) {
                    continue;
                }

                $resumen['filas_leidas']++;

                $numero = trim((string) ($fila[$iNumero] ?? ''));
                $nombreComprobante = trim((string) ($fila[$iNombre] ?? ''));
                if ($numero === '' || $nombreComprobante === '') {
                    $resumen['omitidos']++;
                    continue;
                }
                // Los N° de boleta deben ser texto (0008425). Si el .xlsx los entregó
                // numéricos (se perdieron los ceros en la conversión desde .xls), se
                // re-rellenan a la longitud típica de los que SÍ llegaron como texto
                // en este mismo archivo, para no deformar series de otra longitud.
                $numero = $this->normalizarNumeroBoleta($numero, $filas, $iNumero);

                $nombreNormalizado = M_Cruce::normalizarNombre($nombreComprobante);
                $candidatos = $mapaNombres[$nombreNormalizado] ?? [];
                // Más de un candidato (homónimos) = nunca auto-asignar; va a conciliación.
                $idAlumno = count($candidatos) === 1 ? $candidatos[0] : null;

                $monto = (float) str_replace(',', '', (string) ($fila[$iMonto] ?? 0));
                $mora = $iMora !== null ? (float) str_replace(',', '', (string) ($fila[$iMora] ?? 0)) : 0.0;
                $desc = $iDesc !== null ? (float) str_replace(',', '', (string) ($fila[$iDesc] ?? 0)) : 0.0;
                $total = $iTotal !== null ? (float) str_replace(',', '', (string) ($fila[$iTotal] ?? 0)) : $monto;
                $fechaPago = $iFechaPago !== null ? (trim((string) ($fila[$iFechaPago] ?? '')) ?: null) : null;
                $observacion = $iObservacion !== null ? (trim((string) ($fila[$iObservacion] ?? '')) ?: null) : null;

                $datos = [
                    'numero' => $numero,
                    'nombre_comprobante' => $nombreComprobante,
                    'nombre_normalizado' => $nombreNormalizado,
                    'concepto' => $concepto,
                    'concepto_clave' => M_Cruce::conceptoClave($mesConcepto, $anioConcepto),
                    'mes_concepto' => $mesConcepto,
                    'anio_concepto' => $anioConcepto,
                    'fecha_pago' => $fechaPago,
                    'observacion' => $observacion,
                    'monto' => $monto,
                    'mora' => $mora,
                    'descuento' => $desc,
                    'total' => $total,
                    'id_alumno' => $idAlumno,
                ];

                $resultado = $modeloPago->upsertDesdeImportacion($datos, $idImportacion);
                $resumen[$resultado === 'nuevo' ? 'nuevos' : ($resultado === 'actualizado' ? 'actualizados' : 'sin_cambios')]++;
                if ($idAlumno === null) {
                    $resumen['sin_cruce']++;
                }
            }

            $this->cerrarRegistroImportacion($idImportacion, $resumen);
            $this->conexion->commit();

            // Con los pagos ya persistidos se recalcula cuánto paga habitualmente
            // cada alumno. Va DESPUÉS del commit y no dentro de la transacción:
            // es un dato derivado, y que falle no debe invalidar una importación
            // de miles de pagos que sí entró bien. Se recalcula sobre TODOS los
            // años presentes en los datos importados, no solo el filtro opcional
            // — el import por defecto trae todo el histórico (ver plan original).
            $aniosTocados = $this->conexion->query("SELECT DISTINCT anio_concepto FROM pagos WHERE estado = 1")->fetchAll(PDO::FETCH_COLUMN);
            $resumenPensiones = ['detectadas' => 0, 'ambiguas' => 0, 'respetadas_manual' => 0];
            foreach ($aniosTocados as $anioTocado) {
                $r = M_Alumno::singleton()->recalcularPensionesPactadas((int) $anioTocado);
                foreach ($r as $k => $v) {
                    $resumenPensiones[$k] += $v;
                }
            }
            $resumen['pensiones'] = $resumenPensiones;

            $resumen['id_importacion'] = $idImportacion;
            return $resumen;
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Deduce la longitud "correcta" del N° de boleta como la moda de las
     * longitudes de los valores que llegaron como texto puro (con ceros a la
     * izquierda) en la misma columna del mismo archivo, y paddea con esa
     * longitud los que llegaron numéricos. Sin esto, "8410" se quedaría con 4
     * dígitos en vez de los 7 reales (0008410).
     */
    private function normalizarNumeroBoleta(string $numero, array $filas, int $iNumero): string {
        if (!ctype_digit($numero)) {
            return $numero; // ya no es puramente numérico (serie con letras, etc.)
        }
        static $longitudModa = null;
        if ($longitudModa === null) {
            $conteo = [];
            foreach ($filas as $f) {
                $v = trim((string) ($f[$iNumero] ?? ''));
                if ($v !== '' && ctype_digit($v) && strlen($v) > 1 && $v[0] === '0') {
                    $conteo[strlen($v)] = ($conteo[strlen($v)] ?? 0) + 1;
                }
            }
            $longitudModa = empty($conteo) ? strlen($numero) : array_search(max($conteo), $conteo);
        }
        return str_pad($numero, $longitudModa, '0', STR_PAD_LEFT);
    }

    // ============================ REGISTRO / HISTORIAL ==========================

    private function crearRegistroImportacion(int $tipo, string $nombreArchivo, ?string $hoja, string $rutaArchivo, int $idUsuario, ?int $mes = null, ?int $anio = null): int {
        $hash = @hash_file('sha256', $rutaArchivo) ?: null;
        $this->conexion->prepare(
            "INSERT INTO importaciones (tipo, nombre_archivo, hash_archivo, hoja, mes_filtro, anio_filtro, id_usuario)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$tipo, $nombreArchivo, $hash, $hoja, $mes, $anio, $idUsuario]);
        return (int) $this->conexion->lastInsertId();
    }

    private function cerrarRegistroImportacion(int $idImportacion, array $resumen): void {
        $this->conexion->prepare(
            "UPDATE importaciones SET filas_leidas=?, nuevos=?, actualizados=?, sin_cambios=?, omitidos=?, sin_cruce=? WHERE id_importacion=?"
        )->execute([
            $resumen['filas_leidas'], $resumen['nuevos'], $resumen['actualizados'], $resumen['sin_cambios'],
            $resumen['omitidos'], $resumen['sin_cruce'] ?? 0, $idImportacion,
        ]);
    }

    public function historial(int $limite = 20): array {
        $stmt = $this->conexion->prepare(
            "SELECT i.*, u.username FROM importaciones i
             LEFT JOIN usuarios u ON i.id_usuario = u.id_usuario
             ORDER BY i.fecha_registro DESC LIMIT " . max(1, min($limite, 100))
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>