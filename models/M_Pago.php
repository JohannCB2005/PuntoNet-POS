<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Pago.php';
require_once dirname(__DIR__) . '/models/M_Cruce.php';

class M_Pago {
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
     * Upsert idempotente por (tipo_comprobante, serie, numero, concepto_clave).
     * Igual que M_Alumno::upsertDesdeImportacion(), clasifica comparando en PHP
     * antes de escribir para no depender de rowCount() del ON DUPLICATE KEY
     * UPDATE, que se confundiría por id_importacion cambiando en cada fila.
     *
     * Preserva una conciliación manual previa: si el pago ya tenía id_alumno
     * asignado a mano (metodo_cruce=2) y el nuevo cruce automático no encontró
     * a nadie, NO se pisa la asignación manual con NULL.
     */
    public function upsertDesdeImportacion(array $datos, int $idImportacion): string {
        $tipo = 'BOLETA';
        $serie = '';

        $stmt = $this->conexion->prepare(
            "SELECT * FROM pagos WHERE tipo_comprobante = ? AND serie = ? AND numero = ? AND concepto_clave = ?"
        );
        $stmt->execute([$tipo, $serie, $datos['numero'], $datos['concepto_clave']]);
        $existente = $stmt->fetch();

        $metodoCruce = $datos['id_alumno'] !== null ? 1 : 0;

        if ($existente === false) {
            $this->conexion->prepare(
                "INSERT INTO pagos (id_alumno, tipo_comprobante, serie, numero, nombre_comprobante, nombre_normalizado,
                    concepto, concepto_clave, mes_concepto, anio_concepto, fecha_pago, observacion,
                    monto, mora, descuento, total, metodo_cruce, id_importacion, origen, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
            )->execute([
                $datos['id_alumno'], $tipo, $serie, $datos['numero'], $datos['nombre_comprobante'], $datos['nombre_normalizado'],
                $datos['concepto'], $datos['concepto_clave'], $datos['mes_concepto'], $datos['anio_concepto'],
                $datos['fecha_pago'], $datos['observacion'], $datos['monto'], $datos['mora'], $datos['descuento'],
                $datos['total'], $metodoCruce, $idImportacion,
            ]);
            return 'nuevo';
        }

        // Conservar una conciliación manual (metodo_cruce=2) salvo que el nuevo
        // cruce automático SÍ encuentre a alguien (entonces manda el automático).
        $idAlumnoFinal = $datos['id_alumno'];
        $metodoCruceFinal = $metodoCruce;
        if ($idAlumnoFinal === null && (int) $existente['metodo_cruce'] === 2) {
            $idAlumnoFinal = $existente['id_alumno'];
            $metodoCruceFinal = 2;
        }

        $cambio =
            (int) $existente['id_alumno'] !== (int) $idAlumnoFinal ||
            $existente['nombre_comprobante'] !== $datos['nombre_comprobante'] ||
            (float) $existente['monto'] !== (float) $datos['monto'] ||
            (float) $existente['mora'] !== (float) $datos['mora'] ||
            (float) $existente['descuento'] !== (float) $datos['descuento'] ||
            (float) $existente['total'] !== (float) $datos['total'] ||
            (string) $existente['fecha_pago'] !== (string) $datos['fecha_pago'] ||
            (string) $existente['observacion'] !== (string) $datos['observacion'];

        if ($cambio) {
            $this->conexion->prepare(
                "UPDATE pagos SET id_alumno=?, nombre_comprobante=?, nombre_normalizado=?, fecha_pago=?, observacion=?,
                    monto=?, mora=?, descuento=?, total=?, metodo_cruce=?, id_importacion=?, fecha_actualizacion=NOW()
                 WHERE id_pago = ?"
            )->execute([
                $idAlumnoFinal, $datos['nombre_comprobante'], $datos['nombre_normalizado'], $datos['fecha_pago'], $datos['observacion'],
                $datos['monto'], $datos['mora'], $datos['descuento'], $datos['total'], $metodoCruceFinal,
                $idImportacion, $existente['id_pago'],
            ]);
            return 'actualizado';
        }

        $this->conexion->prepare("UPDATE pagos SET id_importacion = ? WHERE id_pago = ?")
            ->execute([$idImportacion, $existente['id_pago']]);
        return 'sin_cambios';
    }

    public function listarPorPeriodo(int $mes, int $anio): array {
        $stmt = $this->conexion->prepare(
            "SELECT p.*, a.codigo, a.nombre_completo AS alumno_nombre
             FROM pagos p
             LEFT JOIN alumnos a ON p.id_alumno = a.id_alumno
             WHERE p.estado = 1 AND p.mes_concepto = ? AND p.anio_concepto = ?
             ORDER BY p.fecha_pago DESC"
        );
        $stmt->execute([$mes, $anio]);
        return $stmt->fetchAll();
    }

    /**
     * Bandeja de conciliación: pagos sin alumno asignado. Se limita al año
     * escolar vigente por defecto — el histórico de años anteriores casi
     * siempre son alumnos que ya no están en el colegio (no un error real por
     * resolver) y solo generan ruido en la bandeja. No se borran de la BD,
     * solo se dejan de mostrar aquí; `$anio = null` trae todo el histórico.
     */
    public function pendientesConciliacion(?int $anio = null): array {
        $anio = $anio ?? (int) date('Y');
        if ($anio === 0) {
            $stmt = $this->conexion->query(
                "SELECT * FROM pagos WHERE estado = 1 AND id_alumno IS NULL ORDER BY fecha_registro DESC"
            );
            return $stmt->fetchAll();
        }
        $stmt = $this->conexion->prepare(
            "SELECT * FROM pagos WHERE estado = 1 AND id_alumno IS NULL AND anio_concepto = ? ORDER BY fecha_registro DESC"
        );
        $stmt->execute([$anio]);
        return $stmt->fetchAll();
    }

    /**
     * Candidatos sugeridos para conciliar un pago a mano: coincidencia parcial
     * por apellidos (primeras dos palabras del nombre normalizado del pago).
     */
    public function candidatosParaConciliar(string $nombreNormalizado): array {
        $palabras = explode(' ', trim($nombreNormalizado));
        $apellidos = implode(' ', array_slice($palabras, 0, 2));
        if ($apellidos === '') {
            return [];
        }
        $stmt = $this->conexion->prepare(
            "SELECT id_alumno, codigo, nombre_completo, nombre_normalizado FROM alumnos
             WHERE estado = 1 AND nombre_normalizado LIKE ? LIMIT 15"
        );
        $stmt->execute(['%' . $apellidos . '%']);
        return $stmt->fetchAll();
    }

    public function conciliarManualmente(int $idPago, ?int $idAlumno, int $idUsuario, ?string $motivo = null): bool {
        try {
            $this->conexion->beginTransaction();
            $this->conexion->prepare(
                "UPDATE pagos SET id_alumno = ?, metodo_cruce = ? WHERE id_pago = ?"
            )->execute([$idAlumno, $idAlumno !== null ? 2 : 0, $idPago]);

            $accion = $idAlumno !== null ? 1 : 2; // 1=Asignado, 2=Descartado
            $this->conexion->prepare(
                "INSERT INTO conciliaciones_pago (id_pago, id_alumno, accion, motivo, id_usuario) VALUES (?, ?, ?, ?, ?)"
            )->execute([$idPago, $idAlumno, $accion, $motivo, $idUsuario]);

            $this->conexion->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return false;
        }
    }

    public function altaManual(Pago $p): array {
        try {
            $this->conexion->prepare(
                "INSERT INTO pagos (id_alumno, tipo_comprobante, serie, numero, nombre_comprobante, nombre_normalizado,
                    concepto, concepto_clave, mes_concepto, anio_concepto, fecha_pago, monto, total, metodo_cruce, origen, estado)
                 VALUES (?, 'BOLETA', '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 2, 1)"
            )->execute([
                $p->id_alumno, $p->numero, $p->nombre_comprobante, $p->nombre_normalizado,
                $p->concepto, M_Cruce::conceptoClave($p->mes_concepto, $p->anio_concepto),
                $p->mes_concepto, $p->anio_concepto, $p->fecha_pago, $p->total, $p->total,
                $p->id_alumno !== null ? 3 : 0,
            ]);
            return ['ok' => true, 'mensaje' => 'Pago registrado correctamente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al registrar el pago: ' . $e->getMessage()];
        }
    }

    public function eliminar(int $id): bool {
        try {
            $this->conexion->prepare("UPDATE pagos SET estado = 0 WHERE id_pago = ?")->execute([$id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>