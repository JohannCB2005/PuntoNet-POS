<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Alumno.php';

class M_Alumno {
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

    /**
     * Upsert idempotente por `codigo` (clave natural cubicol). Clasifica la fila
     * en 'nuevo' / 'actualizado' / 'sin_cambios' COMPARANDO EN PHP antes de
     * escribir, no confiando en PDOStatement::rowCount() del ON DUPLICATE KEY
     * UPDATE — ese contador se confunde en cuanto se toca id_importacion en cada
     * fila (cosa que sí hace falta, para saber qué alumnos no vinieron en la
     * última subida), porque entonces SIEMPRE reporta "cambiado".
     *
     * @param array $datos codigo, nombre_completo, nombre_normalizado, id_nivel,
     *                      id_grado, seccion, matriculado
     */
    public function upsertDesdeImportacion(array $datos, int $idImportacion): string {
        $stmt = $this->conexion->prepare("SELECT * FROM alumnos WHERE codigo = ?");
        $stmt->execute([$datos['codigo']]);
        $existente = $stmt->fetch();

        if ($existente === false) {
            $this->conexion->prepare(
                "INSERT INTO alumnos (codigo, nombre_completo, nombre_normalizado, id_nivel, id_grado, seccion, matriculado, id_importacion, origen, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
            )->execute([
                $datos['codigo'], $datos['nombre_completo'], $datos['nombre_normalizado'],
                $datos['id_nivel'], $datos['id_grado'], $datos['seccion'], $datos['matriculado'],
                $idImportacion,
            ]);
            return 'nuevo';
        }

        $cambio =
            $existente['nombre_completo'] !== $datos['nombre_completo'] ||
            $existente['nombre_normalizado'] !== $datos['nombre_normalizado'] ||
            (int) $existente['id_nivel'] !== (int) $datos['id_nivel'] ||
            (int) $existente['id_grado'] !== (int) $datos['id_grado'] ||
            (string) $existente['seccion'] !== (string) $datos['seccion'] ||
            (int) $existente['matriculado'] !== (int) $datos['matriculado'];

        if ($cambio) {
            $this->conexion->prepare(
                "UPDATE alumnos SET nombre_completo=?, nombre_normalizado=?, id_nivel=?, id_grado=?, seccion=?, matriculado=?,
                    id_importacion=?, fecha_actualizacion=NOW()
                 WHERE id_alumno = ?"
            )->execute([
                $datos['nombre_completo'], $datos['nombre_normalizado'], $datos['id_nivel'],
                $datos['id_grado'], $datos['seccion'], $datos['matriculado'],
                $idImportacion, $existente['id_alumno'],
            ]);
            return 'actualizado';
        }

        // Sin cambios visibles, pero se actualiza id_importacion igual: es lo que
        // permite luego avisar "estos alumnos no vinieron en el último archivo".
        $this->conexion->prepare("UPDATE alumnos SET id_importacion = ? WHERE id_alumno = ?")
            ->execute([$idImportacion, $existente['id_alumno']]);
        return 'sin_cambios';
    }

    public function listar(array $filtros = []): array {
        $sql = "SELECT a.*, n.nombre AS nivel_nombre, g.nombre AS grado_nombre
                FROM alumnos a
                LEFT JOIN niveles_educativos n ON a.id_nivel = n.id_nivel
                LEFT JOIN grados g ON a.id_grado = g.id_grado
                WHERE a.estado = 1";
        $params = [];
        if (!empty($filtros['id_nivel'])) {
            $sql .= " AND a.id_nivel = ?";
            $params[] = $filtros['id_nivel'];
        }
        if (!empty($filtros['matriculado'])) {
            $sql .= " AND a.matriculado = ?";
            $params[] = $filtros['matriculado'];
        }
        $sql .= " ORDER BY n.id_nivel, g.id_grado, a.seccion, a.nombre_completo";
        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function obtenerPorId(int $id): ?array {
        $stmt = $this->conexion->prepare("SELECT * FROM alumnos WHERE id_alumno = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Deduce cuánto paga habitualmente cada alumno ("pensión pactada") a partir
     * de su propio historial de pagos del año.
     *
     * El colegio NO tiene una pensión única: 290 en Primaria y 300 en Secundaria
     * son solo lo más común, pero hay descuentos exclusivos por alumno (hermanos,
     * becas, convenios) que van de 200 a 280. Como ningún reporte de cubicol trae
     * el monto pactado, la única fuente posible es la conducta de pago: si un
     * alumno paga exactamente lo mismo mes a mes, ESE es su monto acordado, y
     * pagarlo significa estar al día aunque sea menor que la pensión estándar.
     *
     * Regla: la moda de sus netos mensuales. Ante empate gana el monto MÁS
     * RECIENTE — un descuento nuevo (p. ej. por una desgracia familiar a mitad
     * de año) debe reflejarse de inmediato. Esos casos quedan marcados con
     * pension_origen = 3 para que un humano los revise en vez de confiar a
     * ciegas en la heurística.
     *
     * Nunca pisa las pensiones fijadas a mano (pension_origen = 2).
     *
     * @return array ['detectadas'=>int, 'ambiguas'=>int, 'respetadas_manual'=>int]
     */
    public function recalcularPensionesPactadas(?int $anio = null): array {
        $anio = $anio ?? (int) date('Y');
        $resumen = ['detectadas' => 0, 'ambiguas' => 0, 'respetadas_manual' => 0];

        try {
            // Netos mensuales positivos del año, por alumno y en orden cronológico.
            // Los meses con neto <= 0 (anulaciones netas) no dicen nada del monto
            // pactado, así que se descartan del cálculo.
            $stmt = $this->conexion->prepare(
                "SELECT id_alumno, mes_concepto, SUM(total) AS neto
                 FROM pagos
                 WHERE anio_concepto = ? AND estado = 1 AND id_alumno IS NOT NULL
                 GROUP BY id_alumno, mes_concepto
                 HAVING neto > 0
                 ORDER BY id_alumno, mes_concepto"
            );
            $stmt->execute([$anio]);

            $porAlumno = [];
            foreach ($stmt->fetchAll() as $fila) {
                $porAlumno[(int) $fila['id_alumno']][] = (float) $fila['neto'];
            }
            if (empty($porAlumno)) {
                return $resumen;
            }

            $manuales = $this->conexion->query("SELECT id_alumno FROM alumnos WHERE pension_origen = 2")->fetchAll(PDO::FETCH_COLUMN);
            $manuales = array_flip(array_map('intval', $manuales));

            $update = $this->conexion->prepare("UPDATE alumnos SET pension_pactada = ?, pension_origen = ? WHERE id_alumno = ?");

            foreach ($porAlumno as $idAlumno => $netos) {
                if (isset($manuales[$idAlumno])) {
                    $resumen['respetadas_manual']++;
                    continue;
                }

                $frecuencia = [];
                foreach ($netos as $n) {
                    $clave = number_format($n, 2, '.', '');
                    $frecuencia[$clave] = ($frecuencia[$clave] ?? 0) + 1;
                }

                $maxFrec = max($frecuencia);
                // Ante empate gana el más reciente: se recorre el historial al
                // revés y se toma el primero que alcance la frecuencia máxima.
                $pactada = null;
                foreach (array_reverse($netos) as $n) {
                    $clave = number_format($n, 2, '.', '');
                    if ($frecuencia[$clave] === $maxFrec) {
                        $pactada = (float) $clave;
                        break;
                    }
                }

                $ambigua = count($frecuencia) > 1;
                $origen = $ambigua ? 3 : 1;
                $update->execute([$pactada, $origen, $idAlumno]);
                $resumen[$ambigua ? 'ambiguas' : 'detectadas']++;
            }

            return $resumen;
        } catch (PDOException $e) {
            return $resumen;
        }
    }

    /** Fija la pensión pactada a mano; el import ya no la vuelve a tocar. */
    public function fijarPensionPactada(int $idAlumno, ?float $monto): bool {
        try {
            // Volver a null = devolver el control a la detección automática.
            $origen = $monto === null ? 1 : 2;
            $this->conexion->prepare("UPDATE alumnos SET pension_pactada = ?, pension_origen = ? WHERE id_alumno = ?")
                ->execute([$monto, $origen, $idAlumno]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Mapa nombre_normalizado -> [id_alumno, ...] de todos los alumnos activos.
     * Se usa en memoria durante el cruce de comprobantes: cargar todo el padrón
     * una sola vez es muchísimo más barato que una consulta por cada pago.
     */
    public function mapaNormalizados(): array {
        $mapa = [];
        $stmt = $this->conexion->query("SELECT id_alumno, nombre_normalizado FROM alumnos WHERE estado = 1");
        foreach ($stmt->fetchAll() as $fila) {
            $mapa[$fila['nombre_normalizado']][] = (int) $fila['id_alumno'];
        }
        return $mapa;
    }

    public function altaManual(Alumno $a): array {
        try {
            $stmt = $this->conexion->prepare("SELECT 1 FROM alumnos WHERE codigo = ?");
            $stmt->execute([$a->codigo]);
            if ($stmt->fetchColumn()) {
                return ['ok' => false, 'mensaje' => 'Ya existe un alumno con ese código.'];
            }
            $this->conexion->prepare(
                "INSERT INTO alumnos (codigo, nombre_completo, nombre_normalizado, id_nivel, id_grado, seccion, matriculado, origen, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 2, 1)"
            )->execute([
                $a->codigo, $a->nombre_completo, $a->nombre_normalizado,
                $a->id_nivel, $a->id_grado, $a->seccion, $a->matriculado,
            ]);
            return ['ok' => true, 'mensaje' => 'Alumno registrado correctamente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al registrar el alumno: ' . $e->getMessage()];
        }
    }

    public function actualizar(Alumno $a): bool {
        try {
            $this->conexion->prepare(
                "UPDATE alumnos SET nombre_completo=?, nombre_normalizado=?, id_nivel=?, id_grado=?, seccion=?, matriculado=?, fecha_actualizacion=NOW()
                 WHERE id_alumno = ?"
            )->execute([
                $a->nombre_completo, $a->nombre_normalizado, $a->id_nivel,
                $a->id_grado, $a->seccion, $a->matriculado, $a->id_alumno,
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function eliminar(int $id): bool {
        try {
            $this->conexion->prepare("UPDATE alumnos SET estado = 0 WHERE id_alumno = ?")->execute([$id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>