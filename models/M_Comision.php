<?php
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Comisiones por venta: monto fijo por unidad, congelado en
 * detalle_ventas.comision_unitaria al momento de la venta (nunca se recalcula
 * hacia atrás si se cambia productos.comision después).
 *
 * Reglas de negocio que hacen que la comisión refleje lo REALMENTE entregado y
 * cobrado, no lo tecleado (ver CODEMAP.md):
 *   - Una venta anulada (estado=0) no paga comisión.
 *   - Una separación con anticipo solo paga cuando se DESPACHA (estado=2), y
 *     devenga en su fecha_despacho, no en la del anticipo. Si se anula, no paga
 *     nada aunque sus ventas de abono ya cobradas sigan activas.
 *   - Un cambio de talla paga el ajuste de comisión (comision_delta) al vendedor
 *     ORIGINAL, vía cambios_talla.id_venta_original — no a quien tramitó el
 *     cambio. La línea de la venta-diferencia (origen=3) siempre lleva
 *     comision_unitaria=0 para no contar la prenda dos veces.
 */
class M_Comision {
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
     * SQL compartido: una fila por evento que devenga comisión (línea vendida o
     * ajuste de cambio de talla), con id_usuario, fecha_devengo y comision.
     */
    private function sqlDevengado(): string {
        return "
            SELECT v.id_usuario, v.id_venta,
                   CASE WHEN v.id_separacion IS NOT NULL THEN s.fecha_despacho ELSE v.fecha END AS fecha_devengo,
                   dv.cantidad * dv.comision_unitaria AS comision
            FROM detalle_ventas dv
            INNER JOIN ventas v ON dv.id_venta = v.id_venta
            LEFT JOIN separaciones s ON s.id_separacion = v.id_separacion
            WHERE v.estado = 1
              AND (v.id_separacion IS NULL OR s.estado = 2)
              AND dv.comision_unitaria <> 0

            UNION ALL

            SELECT vo.id_usuario, ct.id_venta_original AS id_venta, ct.fecha AS fecha_devengo, ct.comision_delta AS comision
            FROM cambios_talla ct
            INNER JOIN ventas vo ON ct.id_venta_original = vo.id_venta
            WHERE vo.estado = 1 AND ct.comision_delta <> 0
        ";
    }

    /**
     * Resumen por vendedor: nº de ventas distintas, unidades vendidas y comisión
     * devengada en el rango de fechas (por fecha_devengo, no por fecha de venta).
     */
    public function resumenPorUsuario(string $desde, string $hasta): array {
        try {
            $sql = "SELECT u.id_usuario, u.username,
                           IF(p.apellidos IS NOT NULL AND p.apellidos != '',
                              CONCAT(p.apellidos, ', ', p.nombres_razon_social),
                              p.nombres_razon_social) AS vendedor,
                           COUNT(DISTINCT d.id_venta) AS num_ventas,
                           SUM(d.comision) AS comision_total
                    FROM ({$this->sqlDevengado()}) d
                    INNER JOIN usuarios u ON d.id_usuario = u.id_usuario
                    INNER JOIN personas p ON u.id_persona = p.id_persona
                    WHERE DATE(d.fecha_devengo) BETWEEN ? AND ?
                    GROUP BY u.id_usuario
                    ORDER BY comision_total DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$desde, $hasta]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$f) {
                $f['comision_total'] = round((float) $f['comision_total'], 2);
            }
            unset($f);
            return $filas;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Detalle línea a línea de un vendedor en el rango: producto, cantidad,
     * comisión unitaria y una etiqueta de concepto para que se entienda de dónde
     * viene cada monto en la planilla.
     */
    public function detallePorUsuario(int $id_usuario, string $desde, string $hasta): array {
        try {
            $sql = "SELECT dv.id_venta, v.fecha, v.origen, v.id_separacion,
                           s.codigo AS codigo_separacion, s.fecha_despacho,
                           i.nombre AS producto, dv.cantidad, dv.comision_unitaria,
                           (dv.cantidad * dv.comision_unitaria) AS comision,
                           'venta' AS tipo
                    FROM detalle_ventas dv
                    INNER JOIN ventas v ON dv.id_venta = v.id_venta
                    INNER JOIN productos i ON dv.id_producto = i.id_producto
                    LEFT JOIN separaciones s ON s.id_separacion = v.id_separacion
                    WHERE v.estado = 1 AND v.id_usuario = ?
                      AND (v.id_separacion IS NULL OR s.estado = 2)
                      AND dv.comision_unitaria <> 0
                      AND DATE(CASE WHEN v.id_separacion IS NOT NULL THEN s.fecha_despacho ELSE v.fecha END) BETWEEN ? AND ?

                    UNION ALL

                    SELECT ct.id_venta_original AS id_venta, ct.fecha, 3 AS origen, NULL AS id_separacion,
                           NULL AS codigo_separacion, NULL AS fecha_despacho,
                           ie.nombre AS producto, ct.cantidad, NULL AS comision_unitaria,
                           ct.comision_delta AS comision,
                           'cambio_talla' AS tipo
                    FROM cambios_talla ct
                    INNER JOIN ventas vo ON ct.id_venta_original = vo.id_venta
                    INNER JOIN productos ie ON ct.id_producto_entrante = ie.id_producto
                    WHERE vo.estado = 1 AND vo.id_usuario = ? AND ct.comision_delta <> 0
                      AND DATE(ct.fecha) BETWEEN ? AND ?

                    ORDER BY fecha DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_usuario, $desde, $hasta, $id_usuario, $desde, $hasta]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$f) {
                $f['comision'] = round((float) $f['comision'], 2);
                $f['concepto'] = $this->etiquetaConcepto($f);
            }
            unset($f);
            return $filas;
        } catch (PDOException $e) {
            return [];
        }
    }

    private function etiquetaConcepto(array $fila): string {
        if ($fila['tipo'] === 'cambio_talla') {
            return 'Ajuste por cambio de talla';
        }
        if (!empty($fila['id_separacion'])) {
            return 'Separación despachada (' . $fila['codigo_separacion'] . ')';
        }
        if ((int) $fila['origen'] === 2) {
            return 'Pedido online entregado';
        }
        return 'Venta';
    }

    /**
     * Comisión retenida en separaciones aún pendientes de despachar: informativa,
     * no se suma al devengado hasta que la separación pase a estado=2.
     */
    public function pendientesPorUsuario(): array {
        try {
            $sql = "SELECT v.id_usuario, u.username,
                           IF(p.apellidos IS NOT NULL AND p.apellidos != '',
                              CONCAT(p.apellidos, ', ', p.nombres_razon_social),
                              p.nombres_razon_social) AS vendedor,
                           s.codigo, s.fecha_vencimiento,
                           SUM(dv.cantidad * dv.comision_unitaria) AS comision_pendiente
                    FROM detalle_ventas dv
                    INNER JOIN ventas v ON dv.id_venta = v.id_venta
                    INNER JOIN separaciones s ON s.id_separacion = v.id_separacion
                    INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                    INNER JOIN personas p ON u.id_persona = p.id_persona
                    WHERE v.estado = 1 AND s.estado = 1 AND dv.comision_unitaria <> 0
                    GROUP BY s.id_separacion
                    ORDER BY s.fecha_vencimiento ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$f) {
                $f['comision_pendiente'] = round((float) $f['comision_pendiente'], 2);
            }
            unset($f);
            return $filas;
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>
