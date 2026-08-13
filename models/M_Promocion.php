<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Promocion.php';

/**
 * Promociones "Módulo del Bimestre".
 *
 * Además de los criterios de elegibilidad (heredados del sistema hermano), en
 * NISSI cada promoción lleva un DETALLE DE PRODUCTOS por (nivel, grado): qué
 * productos —y cuánto— recibe cada alumno. Ese detalle se usa de dos formas:
 *   - como plantilla para el siguiente bimestre (botón "Copiar del bimestre
 *     anterior"),
 *   - como insumo para descontar stock al confirmar una entrega (M_Entrega).
 */
class M_Promocion {
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

    public function listar(): array {
        try {
            return $this->conexion->query(
                "SELECT * FROM promociones WHERE estado = 1 ORDER BY anio_requerido DESC, bimestre DESC"
            )->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function obtenerPorId(int $id): ?array {
        $stmt = $this->conexion->prepare("SELECT * FROM promociones WHERE id_promocion = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * La "última" promoción es la de mayor (año, bimestre) — es la definición que
     * usa el listado de Entregas para su filtro por defecto.
     */
    public function obtenerUltima(): ?array {
        $r = $this->conexion->query(
            "SELECT * FROM promociones WHERE estado = 1 ORDER BY anio_requerido DESC, bimestre DESC LIMIT 1"
        )->fetch();
        return $r === false ? null : $r;
    }

    public function crear(Promocion $p): array {
        try {
            $stmt = $this->conexion->prepare("SELECT 1 FROM promociones WHERE anio_requerido = ? AND bimestre = ?");
            $stmt->execute([$p->anio_requerido, $p->bimestre]);
            if ($stmt->fetchColumn()) {
                return ['ok' => false, 'mensaje' => "Ya existe una promoción para el bimestre {$p->bimestre} del {$p->anio_requerido}."];
            }
            $this->conexion->prepare(
                "INSERT INTO promociones (nombre, bimestre, mes_requerido, anio_requerido, monto_minimo, fecha_limite_pago, exige_pension_completa, exige_matriculado, exige_neto_positivo, descripcion, id_usuario)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $p->nombre, $p->bimestre, $p->mes_requerido, $p->anio_requerido, $p->monto_minimo, $p->fecha_limite_pago,
                $p->exige_pension_completa, $p->exige_matriculado, $p->exige_neto_positivo, $p->descripcion, $p->id_usuario,
            ]);
            return ['ok' => true, 'mensaje' => 'Promoción creada correctamente.', 'id_promocion' => (int) $this->conexion->lastInsertId()];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al crear la promoción: ' . $e->getMessage()];
        }
    }

    public function actualizar(Promocion $p): array {
        try {
            $stmt = $this->conexion->prepare("SELECT 1 FROM promociones WHERE anio_requerido = ? AND bimestre = ? AND id_promocion <> ?");
            $stmt->execute([$p->anio_requerido, $p->bimestre, $p->id_promocion]);
            if ($stmt->fetchColumn()) {
                return ['ok' => false, 'mensaje' => "Ya existe otra promoción para el bimestre {$p->bimestre} del {$p->anio_requerido}."];
            }
            $this->conexion->prepare(
                "UPDATE promociones SET nombre=?, bimestre=?, mes_requerido=?, anio_requerido=?, monto_minimo=?, fecha_limite_pago=?, exige_pension_completa=?, exige_matriculado=?, exige_neto_positivo=?, descripcion=?
                 WHERE id_promocion = ?"
            )->execute([
                $p->nombre, $p->bimestre, $p->mes_requerido, $p->anio_requerido, $p->monto_minimo, $p->fecha_limite_pago,
                $p->exige_pension_completa, $p->exige_matriculado, $p->exige_neto_positivo, $p->descripcion, $p->id_promocion,
            ]);
            return ['ok' => true, 'mensaje' => 'Promoción actualizada correctamente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al actualizar la promoción: ' . $e->getMessage()];
        }
    }

    public function eliminar(int $id): bool {
        try {
            $this->conexion->prepare("UPDATE promociones SET estado = 0 WHERE id_promocion = ?")->execute([$id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Cuenta beneficiarios SIN materializar la lista: alumno con SUM(pagos.total)
     * del mes/año de la promoción por encima del umbral, opcionalmente exigiendo
     * matrícula y/o que el pago se haya hecho hasta cierta fecha. Es la misma
     * regla que usa M_Entrega::listarBeneficiarios(), aquí solo se necesita el
     * número para el contador en vivo del formulario.
     *
     * Con fecha límite activa, un pago sin fecha_pago registrada (dato faltante)
     * NO cuenta — no se puede demostrar que fue a tiempo, así que se prefiere
     * excluirlo antes que asumirlo válido.
     *
     * Con exigePensionCompleta activo, el umbral deja de ser único para todos:
     * cada alumno se compara contra SU propia pensión pactada
     * (alumnos.pension_pactada, detectada por M_Alumno::recalcularPensionesPactadas()
     * a partir de su propio historial de pagos) en vez de un monto fijo tipo
     * 290/300 — así un alumno con descuento exclusivo que sí pagó lo que le
     * corresponde cuenta como al día, no como moroso. Si un alumno todavía no
     * tiene pensión pactada detectada (sin historial), cae al umbral genérico.
     */
    public function contarBeneficiarios(int $mes, int $anio, bool $exigeMatriculado, bool $exigeNetoPositivo, ?float $montoMinimo, ?string $fechaLimite = null, bool $exigePensionCompleta = false): int {
        $umbral = $montoMinimo !== null ? $montoMinimo : ($exigeNetoPositivo ? 0 : -PHP_FLOAT_MAX);

        $condicionFecha = $fechaLimite !== null ? " AND p.fecha_pago IS NOT NULL AND p.fecha_pago <= ?" : "";
        // >= cuando se compara contra la pensión pactada (pagar EXACTO lo acordado
        // cuenta como estar al día); > en el caso genérico, igual que siempre.
        $comparacion = $exigePensionCompleta ? ">= COALESCE(a.pension_pactada, ?)" : "> ?";

        // a.pension_pactada tiene que ir en el SELECT de esta subconsulta aunque
        // no se necesite afuera: MariaDB exige que cualquier columna no agregada
        // referenciada en HAVING esté en el SELECT de ese mismo nivel, incluso
        // siendo funcionalmente dependiente del GROUP BY (a.id_alumno). Sin esto
        // tira "Unknown column 'a.pension_pactada' in HAVING".
        $sql = "SELECT COUNT(*) FROM (
                    SELECT a.id_alumno, a.pension_pactada, SUM(p.total) AS neto
                    FROM alumnos a
                    INNER JOIN pagos p ON p.id_alumno = a.id_alumno AND p.estado = 1 AND p.mes_concepto = ? AND p.anio_concepto = ?" . $condicionFecha . "
                    WHERE a.estado = 1" . ($exigeMatriculado ? " AND a.matriculado = 1" : "") . "
                    GROUP BY a.id_alumno
                    HAVING neto " . $comparacion . "
                ) t";
        $params = [$mes, $anio];
        if ($fechaLimite !== null) {
            $params[] = $fechaLimite;
        }
        $params[] = $umbral;
        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ============================ PRODUCTOS (NISSI) ============================

    /**
     * Catálogo de productos disponibles para asignar a una promoción: productos
     * reales (no agrupadores) activos, ordenados por categoría y nombre.
     */
    public function listarProductosCatalogo(): array {
        try {
            return $this->conexion->query(
                "SELECT p.id_producto, p.nombre, c.nombre AS categoria, p.stock_piezas, p.precio_unitario
                 FROM productos p
                 INNER JOIN categorias c ON p.id_categoria = c.id_categoria
                 WHERE p.estado = 1 AND p.es_agrupador = 0
                 ORDER BY c.nombre, p.nombre"
            )->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /** Detalle de productos ya asignados a una promoción, con nombres legibles. */
    public function productosDePromocion(int $idPromocion): array {
        try {
            $stmt = $this->conexion->prepare(
                "SELECT d.id_detalle, d.id_nivel, d.id_grado, d.id_producto, d.cantidad,
                        ne.nombre AS nivel_nombre, g.nombre AS grado_nombre, p.nombre AS producto_nombre
                 FROM detalle_promocion_productos d
                 INNER JOIN niveles_educativos ne ON d.id_nivel = ne.id_nivel
                 INNER JOIN grados g ON d.id_grado = g.id_grado
                 INNER JOIN productos p ON d.id_producto = p.id_producto
                 WHERE d.id_promocion = ?
                 ORDER BY ne.id_nivel, g.id_grado, p.nombre"
            );
            $stmt->execute([$idPromocion]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Reemplaza TODO el detalle de productos de una promoción: borra lo anterior
     * y vuelve a insertar (asignación desde cero en el modal). Cada fila debe
     * traer id_nivel, id_grado, id_producto y cantidad.
     */
    public function guardarProductos(int $idPromocion, array $filas): array {
        try {
            $this->conexion->beginTransaction();
            $this->conexion->prepare("DELETE FROM detalle_promocion_productos WHERE id_promocion = ?")->execute([$idPromocion]);

            $insert = $this->conexion->prepare(
                "INSERT INTO detalle_promocion_productos (id_promocion, id_nivel, id_grado, id_producto, cantidad)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $cuantas = 0;
            $vistas = [];
            foreach ($filas as $f) {
                $idNivel = (int) $f['id_nivel'];
                $idGrado = (int) $f['id_grado'];
                $idProducto = (int) $f['id_producto'];
                $cantidad = (float) $f['cantidad'];
                if ($idNivel <= 0 || $idGrado <= 0 || $idProducto <= 0 || $cantidad <= 0) {
                    continue;
                }
                $clave = $idNivel . '-' . $idGrado . '-' . $idProducto;
                if (isset($vistas[$clave])) {
                    continue; // duplicado dentro de la misma asignación
                }
                $vistas[$clave] = true;
                $insert->execute([$idPromocion, $idNivel, $idGrado, $idProducto, $cantidad]);
                $cuantas++;
            }

            $this->conexion->commit();
            return ['ok' => true, 'asignados' => $cuantas, 'mensaje' => "Se asignaron {$cuantas} productos a la promoción."];
        } catch (PDOException $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => 'Error al guardar los productos: ' . $e->getMessage()];
        }
    }

    /**
     * La promoción inmediatamente anterior en el tiempo se usa como plantilla
     * para copiar el detalle de productos al siguiente bimestre.
     */
    public function promocionAnterior(int $idPromocion): ?array {
        $actual = $this->obtenerPorId($idPromocion);
        if ($actual === null) {
            return null;
        }
        $stmt = $this->conexion->prepare(
            "SELECT * FROM promociones WHERE estado = 1 AND (anio_requerido < ? OR (anio_requerido = ? AND bimestre < ?))
             ORDER BY anio_requerido DESC, bimestre DESC LIMIT 1"
        );
        $stmt->execute([$actual['anio_requerido'], $actual['anio_requerido'], $actual['bimestre']]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Copia el detalle de productos de otra promoción (la anterior) hacia esta,
     * reemplazando lo que hubiera. Devuelve el número de filas copiadas.
     */
    public function copiarProductos(int $idDestino, int $idOrigen): array {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM detalle_promocion_productos WHERE id_promocion = ?");
            $stmt->execute([$idOrigen]);
            $disponibles = (int) $stmt->fetchColumn();
            if ($disponibles === 0) {
                return ['ok' => false, 'mensaje' => 'La promoción de origen no tiene productos asignados para copiar.'];
            }

            $this->conexion->beginTransaction();
            $this->conexion->prepare("DELETE FROM detalle_promocion_productos WHERE id_promocion = ?")->execute([$idDestino]);
            $this->conexion->prepare(
                "INSERT INTO detalle_promocion_productos (id_promocion, id_nivel, id_grado, id_producto, cantidad)
                 SELECT ?, id_nivel, id_grado, id_producto, cantidad
                 FROM detalle_promocion_productos WHERE id_promocion = ?"
            )->execute([$idDestino, $idOrigen]);
            $this->conexion->commit();

            return ['ok' => true, 'asignados' => $disponibles, 'mensaje' => "Se copiaron {$disponibles} productos del bimestre anterior."];
        } catch (PDOException $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => 'Error al copiar los productos: ' . $e->getMessage()];
        }
    }
}
?>