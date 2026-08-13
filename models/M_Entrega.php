<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Entrega.php';

/**
 * Entregas de módulos escolares.
 *
 * Además del alta del sistema hermano, en NISSI una entrega mueve inventario:
 *   - al ENTREGAR  → salida de kardex por cada producto del detalle de la
 *     promoción para el nivel/grado del alumno, decremento de stock_piezas y
 *     foto en detalle_entregas,
 *   - al ANULAR    → entrada de kardex (restaura stock) y reaparición del
 *     alumno como "Pendiente".
 * Todo dentro de la misma transacción, con SELECT ... FOR UPDATE sobre el stock
 * para que dos cajeros no descuenten el mismo producto a la vez. La entrega NO
 * crea venta contable: solo kardex (decisión del proyecto).
 */
class M_Entrega {
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
     * Lista de beneficiarios de una promoción: DERIVADA, no materializada (ver
     * M_Promocion). Un alumno es beneficiario si su neto de pensión del mes/año
     * de la promoción supera el umbral configurado, opcionalmente exigiendo
     * matrícula y/o que el pago se haya hecho hasta la fecha_limite_pago (si la
     * promoción la tiene configurada). Se acompaña del estado de entrega
     * (LEFT JOIN: NULL = pendiente).
     *
     * Con fecha límite activa, la boleta/id_pago mostrados también se restringen
     * a pagos hechos a tiempo — así el botón Entregar nunca queda apuntando a
     * una boleta que llegó después de la fecha límite.
     */
    public function listarBeneficiarios(array $promocion): array {
        $mes = (int) $promocion['mes_requerido'];
        $anio = (int) $promocion['anio_requerido'];
        $exigeMatriculado = (int) $promocion['exige_matriculado'] === 1;
        $exigeNeto = (int) $promocion['exige_neto_positivo'] === 1;
        $exigePensionCompleta = (int) ($promocion['exige_pension_completa'] ?? 0) === 1;
        $montoMinimo = $promocion['monto_minimo'] !== null ? (float) $promocion['monto_minimo'] : null;
        $fechaLimite = $promocion['fecha_limite_pago'] ?? null;
        $umbral = $montoMinimo !== null ? $montoMinimo : ($exigeNeto ? 0 : -PHP_FLOAT_MAX);

        $condicionFecha = $fechaLimite !== null ? " AND %ALIAS%.fecha_pago IS NOT NULL AND %ALIAS%.fecha_pago <= ?" : "";
        // Igual criterio que M_Promocion::contarBeneficiarios(): con pensión
        // completa exigida, cada alumno se mide contra SU pensión pactada
        // (descuentos individuales respetados), no un monto fijo para todos.
        $comparacion = $exigePensionCompleta ? ">= COALESCE(a.pension_pactada, ?)" : "> ?";

        $sql = "SELECT
                    a.id_alumno, a.codigo, a.nombre_completo, a.pension_pactada,
                    n.nombre AS nivel, g.nombre AS grado, a.seccion,
                    SUM(p.total) AS neto,
                    (SELECT p2.numero FROM pagos p2 WHERE p2.id_alumno = a.id_alumno AND p2.mes_concepto = ? AND p2.anio_concepto = ? AND p2.estado = 1" . str_replace('%ALIAS%', 'p2', $condicionFecha) . " ORDER BY p2.id_pago DESC LIMIT 1) AS numero_boleta,
                    (SELECT p2.id_pago FROM pagos p2 WHERE p2.id_alumno = a.id_alumno AND p2.mes_concepto = ? AND p2.anio_concepto = ? AND p2.estado = 1" . str_replace('%ALIAS%', 'p2', $condicionFecha) . " ORDER BY p2.id_pago DESC LIMIT 1) AS id_pago,
                    (SELECT COUNT(*) FROM pagos p3 WHERE p3.id_alumno = a.id_alumno AND p3.mes_concepto = ? AND p3.anio_concepto = ? AND p3.estado = 1" . str_replace('%ALIAS%', 'p3', $condicionFecha) . ") AS num_boletas,
                    e.id_entrega, e.dni_receptor, e.nombre_receptor, e.fecha_entrega
                FROM alumnos a
                INNER JOIN pagos p ON p.id_alumno = a.id_alumno AND p.mes_concepto = ? AND p.anio_concepto = ? AND p.estado = 1" . str_replace('%ALIAS%', 'p', $condicionFecha) . "
                LEFT JOIN niveles_educativos n ON a.id_nivel = n.id_nivel
                LEFT JOIN grados g ON a.id_grado = g.id_grado
                LEFT JOIN entregas e ON e.id_alumno = a.id_alumno AND e.id_promocion = ? AND e.estado = 1
                WHERE a.estado = 1" . ($exigeMatriculado ? " AND a.matriculado = 1" : "") . "
                GROUP BY a.id_alumno
                HAVING neto " . $comparacion . "
                ORDER BY (e.id_entrega IS NULL) DESC, n.id_nivel, g.id_grado, a.seccion, a.nombre_completo";

        $params = [$mes, $anio];
        if ($fechaLimite !== null) $params[] = $fechaLimite;
        $params = array_merge($params, [$mes, $anio]);
        if ($fechaLimite !== null) $params[] = $fechaLimite;
        $params = array_merge($params, [$mes, $anio]);
        if ($fechaLimite !== null) $params[] = $fechaLimite;
        $params = array_merge($params, [$mes, $anio]);
        if ($fechaLimite !== null) $params[] = $fechaLimite;
        $params[] = (int) $promocion['id_promocion'];
        $params[] = $umbral;

        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Registra una entrega. La protección real contra la doble entrega es la
     * UNIQUE KEY (id_promocion, id_alumno): si dos cajeros confirman a la vez,
     * el segundo INSERT choca con el error 1062 y se responde con un mensaje
     * claro en vez de una excepción genérica.
     *
     * Si el alumno ya tenía una entrega ANULADA para esta misma promoción, esa
     * fila se reactiva (UPDATE) en vez de intentar un INSERT nuevo — la UNIQUE
     * KEY es sobre (id_promocion, id_alumno) sin importar el estado, así que
     * insertar de nuevo chocaría con el registro anulado que sigue ahí como
     * historial.
     *
     * MUCHO OJO con la mercadería: toda la operación va en una transacción que,
     * además del alta, descuenta stock y escribe los kardex de salida. Si faltan
     * productos asignados para el nivel/grado del alumno, o stock insuficiente,
     * la transacción se revierte y la entrega NO queda registrada.
     */
    public function registrar(Entrega $e): array {
        try {
            $this->conexion->beginTransaction();

            $stmt = $this->conexion->prepare("SELECT id_entrega, estado FROM entregas WHERE id_promocion = ? AND id_alumno = ?");
            $stmt->execute([$e->id_promocion, $e->id_alumno]);
            $existente = $stmt->fetch();

            if ($existente && (int) $existente['estado'] === 1) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'Este alumno ya recibió el módulo de esta promoción.'];
            }

            if ($existente) {
                $this->conexion->prepare(
                    "UPDATE entregas SET id_pago=?, dni_receptor=?, nombre_receptor=?, parentesco=?, origen_datos=?, observacion=NULL, id_usuario=?, fecha_entrega=NOW(), estado=1
                     WHERE id_entrega=?"
                )->execute([
                    $e->id_pago, $e->dni_receptor, $e->nombre_receptor, $e->parentesco, $e->origen_datos,
                    $e->id_usuario, $existente['id_entrega'],
                ]);
                $idEntrega = (int) $existente['id_entrega'];
            } else {
                $this->conexion->prepare(
                    "INSERT INTO entregas (id_promocion, id_alumno, id_pago, dni_receptor, nombre_receptor, parentesco, origen_datos, observacion, id_usuario, estado)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
                )->execute([
                    $e->id_promocion, $e->id_alumno, $e->id_pago, $e->dni_receptor, $e->nombre_receptor,
                    $e->parentesco, $e->origen_datos, $e->observacion, $e->id_usuario,
                ]);
                $idEntrega = (int) $this->conexion->lastInsertId();
            }

            // ---- Stock: qué lleva este alumno (productos por su nivel/grado) ----
            $alumno = $this->conexion->prepare("SELECT id_nivel, id_grado FROM alumnos WHERE id_alumno = ?");
            $alumno->execute([$e->id_alumno]);
            $a = $alumno->fetch();
            $idNivel = $a ? (int) $a['id_nivel'] : 0;
            $idGrado = $a ? (int) $a['id_grado'] : 0;

            $stmtDetalle = $this->conexion->prepare(
                "SELECT d.id_producto, d.cantidad, p.nombre AS producto_nombre, p.precio_unitario, p.stock_piezas
                 FROM detalle_promocion_productos d
                 INNER JOIN productos p ON d.id_producto = p.id_producto
                 WHERE d.id_promocion = ? AND d.id_nivel = ? AND d.id_grado = ?"
            );
            $stmtDetalle->execute([$e->id_promocion, $idNivel, $idGrado]);
            $productos = $stmtDetalle->fetchAll();

            if (empty($productos)) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'Esta promoción no tiene productos asignados para el nivel/grado del alumno. Asigna los productos en el módulo de Promociones primero.'];
            }

            $insertDetalle = $this->conexion->prepare(
                "INSERT INTO detalle_entregas (id_entrega, id_producto, cantidad) VALUES (?, ?, ?)"
            );
            $insertKardex = $this->conexion->prepare(
                "INSERT INTO kardex_movimientos (id_producto, tipo, cantidad, precio_unitario, referencia, concepto, id_usuario, fecha)
                 VALUES (?, 'salida', ?, ?, ?, ?, ?, NOW())"
            );
            $lock = $this->conexion->prepare("SELECT stock_piezas FROM productos WHERE id_producto = ? FOR UPDATE");
            $updateStock = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas - ? WHERE id_producto = ?");

            foreach ($productos as $prod) {
                $cantidad = (float) $prod['cantidad'];
                $lock->execute([$prod['id_producto']]);
                $stockActual = (float) $lock->fetchColumn();

                if ($stockActual < $cantidad) {
                    $this->conexion->rollBack();
                    return ['ok' => false, 'mensaje' => 'Stock insuficiente para "' . $prod['producto_nombre'] . '" (disponible: ' . $stockActual . '). Repón el stock o revisa los módulos entregados.'];
                }

                $updateStock->execute([$cantidad, $prod['id_producto']]);
                $insertDetalle->execute([$idEntrega, $prod['id_producto'], $cantidad]);
                $insertKardex->execute([
                    $prod['id_producto'], $cantidad, (float) $prod['precio_unitario'],
                    'ENT-' . str_pad((string) $idEntrega, 5, '0', STR_PAD_LEFT),
                    'Entrega módulo escolar (promoción #' . $e->id_promocion . ')',
                    $e->id_usuario,
                ]);
            }

            $this->conexion->commit();
            return ['ok' => true, 'mensaje' => 'Entrega registrada y stock descontado correctamente.', 'id_entrega' => $idEntrega];
        } catch (PDOException $ex) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            if ((int) $ex->errorInfo[1] === 1062) {
                return ['ok' => false, 'mensaje' => 'Este alumno ya recibió el módulo de esta promoción.'];
            }
            return ['ok' => false, 'mensaje' => 'Error al registrar la entrega: ' . $ex->getMessage()];
        }
    }

    /**
     * Detalle de una entrega: datos + los productos realmente entregados
     * (detalle_entregas), para la auditoría del modal "Ver".
     */
    public function obtener(int $idEntrega): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT en.*, a.codigo, a.nombre_completo AS alumno_nombre, p.nombre AS nombre_promocion, u.username
             FROM entregas en
             INNER JOIN alumnos a ON en.id_alumno = a.id_alumno
             INNER JOIN promociones p ON en.id_promocion = p.id_promocion
             LEFT JOIN usuarios u ON en.id_usuario = u.id_usuario
             WHERE en.id_entrega = ?"
        );
        $stmt->execute([$idEntrega]);
        $r = $stmt->fetch();
        if ($r === false) {
            return null;
        }

        $stmtDet = $this->conexion->prepare(
            "SELECT de.cantidad, de.id_producto, p.nombre AS producto_nombre
             FROM detalle_entregas de INNER JOIN productos p ON de.id_producto = p.id_producto
             WHERE de.id_entrega = ?"
        );
        $stmtDet->execute([$idEntrega]);
        $r['detalle'] = $stmtDet->fetchAll();

        return $r;
    }

    /**
     * Anula una entrega y guarda el motivo en `observacion` (queda como
     * historial permanente; se limpia si el alumno vuelve a recibir el módulo
     * más adelante, ver registrar()). El alumno vuelve a aparecer como
     * "Pendiente" en el listado de beneficiarios inmediatamente.
     *
     * La anulación además REINTEGRA el stock: entrada de kardex por cada
     * producto que llevaba la entrega (detalle_entregas), todo en una
     * transacción para que nunca quede "anulada sin reponer".
     */
    public function anular(int $idEntrega, string $motivo, int $idUsuario): bool {
        try {
            $this->conexion->beginTransaction();

            $stmtDet = $this->conexion->prepare(
                "SELECT de.id_producto, de.cantidad, p.precio_unitario
                 FROM detalle_entregas de INNER JOIN productos p ON de.id_producto = p.id_producto
                 WHERE de.id_entrega = ?"
            );
            $stmtDet->execute([$idEntrega]);
            $detalles = $stmtDet->fetchAll();

            $updateStock = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ?");
            $insertKardex = $this->conexion->prepare(
                "INSERT INTO kardex_movimientos (id_producto, tipo, cantidad, precio_unitario, referencia, concepto, id_usuario, fecha)
                 VALUES (?, 'entrada', ?, ?, ?, ?, ?, NOW())"
            );

            $usuario = $idUsuario > 0 ? $idUsuario : null;
            foreach ($detalles as $d) {
                $updateStock->execute([(float) $d['cantidad'], $d['id_producto']]);
                $insertKardex->execute([
                    $d['id_producto'], (float) $d['cantidad'], (float) $d['precio_unitario'],
                    'ANUL-ENT-' . str_pad((string) $idEntrega, 5, '0', STR_PAD_LEFT),
                    'Anulación de entrega de módulo escolar: ' . $motivo,
                    $usuario,
                ]);
            }

            $this->conexion->prepare("UPDATE entregas SET estado = 0, observacion = ? WHERE id_entrega = ?")
                ->execute([$motivo, $idEntrega]);

            $this->conexion->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return false;
        }
    }
}
?>