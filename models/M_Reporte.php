<?php
require_once dirname(__DIR__) . '/config/conexion.php';

class M_Reporte {
    private $pdo;

    public function __construct() {
        $this->pdo = Conexion::singleton()->getConexion();
    }

    public function getKPIs($desde, $hasta) {
        $sql = "SELECT 
                    COALESCE(SUM(total), 0) as total_ventas,
                    COUNT(*) as num_ventas,
                    COALESCE(AVG(total), 0) as ticket_promedio,
                    COUNT(DISTINCT id_cliente) as clientes_unicos
                FROM ventas 
                WHERE estado = 1 AND DATE(fecha) BETWEEN :desde AND :hasta";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getVentasPorPeriodo($desde, $hasta, $agrupacion = 'dia') {
        $groupFormat = '%Y-%m-%d';
        if ($agrupacion === 'semana') {
            $groupFormat = '%Y-%u'; // Año-Semana
        } elseif ($agrupacion === 'mes') {
            $groupFormat = '%Y-%m'; // Año-Mes
        }

        $sql = "SELECT 
                    DATE_FORMAT(fecha, :format) as periodo,
                    COUNT(*) as num_ventas,
                    SUM(total) as ingresos
                FROM ventas
                WHERE estado = 1 AND DATE(fecha) BETWEEN :desde AND :hasta
                GROUP BY periodo
                ORDER BY MIN(fecha) ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':format' => $groupFormat,
            ':desde' => $desde,
            ':hasta' => $hasta
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasPorComprobante($desde, $hasta) {
        $sql = "SELECT 
                    tipo_comprobante,
                    COUNT(*) as cantidad, 
                    SUM(total) as total
                FROM ventas 
                WHERE estado = 1 AND DATE(fecha) BETWEEN :desde AND :hasta
                GROUP BY tipo_comprobante";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopProductos($desde, $hasta, $limit = 5) {
        $sql = "SELECT 
                    i.nombre, 
                    c.nombre as categoria,
                    SUM(dv.cantidad) as piezas_vendidas,
                    NULL as kg_vendidos,
                    SUM(dv.subtotal) as ingresos,
                    um.abreviatura as unidad
                FROM detalle_ventas dv
                INNER JOIN ventas v ON dv.id_venta = v.id_venta
                INNER JOIN productos i ON dv.id_producto = i.id_producto
                INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                WHERE v.estado = 1 AND DATE(v.fecha) BETWEEN :desde AND :hasta
                GROUP BY dv.id_producto
                ORDER BY ingresos DESC 
                LIMIT " . intval($limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasPorVendedor($desde, $hasta) {
        $sql = "SELECT 
                    u.username,
                    p.nombres_razon_social,
                    p.apellidos,
                    COUNT(v.id_venta) as num_ventas,
                    SUM(v.total) as total_vendido
                FROM ventas v
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                INNER JOIN personas p ON u.id_persona = p.id_persona
                WHERE v.estado = 1 AND DATE(v.fecha) BETWEEN :desde AND :hasta
                GROUP BY v.id_usuario
                ORDER BY total_vendido DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopClientes($desde, $hasta, $limit = 10) {
        $sql = "SELECT 
                    p.numero_documento,
                    p.nombres_razon_social,
                    p.apellidos,
                    COUNT(v.id_venta) as compras,
                    SUM(v.total) as total_gastado,
                    MAX(v.fecha) as ultima_compra
                FROM ventas v
                INNER JOIN clientes c ON v.id_cliente = c.id_cliente
                INNER JOIN personas p ON c.id_persona = p.id_persona
                WHERE v.estado = 1 AND DATE(v.fecha) BETWEEN :desde AND :hasta
                GROUP BY v.id_cliente
                ORDER BY total_gastado DESC 
                LIMIT " . intval($limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEstadoStock() {
        $sql = "SELECT 
                    i.nombre, 
                    c.nombre as categoria,
                    um.abreviatura as unidad,
                    i.stock_piezas, 
                    i.precio_unitario,
                    (i.stock_piezas * i.precio_unitario) as valor_stock,
                    CASE 
                        WHEN i.stock_ilimitado = 1 THEN 'Ilimitado'
                        WHEN i.stock_piezas <= 0 THEN 'Agotado'
                        WHEN i.stock_piezas <= 20 THEN 'Bajo'
                        ELSE 'Normal' 
                    END as estado_stock
                FROM productos i
                INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                WHERE i.estado = 1
                ORDER BY i.stock_piezas ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasDetalladas($desde, $hasta) {
        $sql = "SELECT 
                    v.id_venta,
                    v.fecha,
                    v.tipo_comprobante,
                    v.total,
                    p_cliente.numero_documento as cliente_doc,
                    p_cliente.nombres_razon_social as cliente_nombres,
                    p_cliente.apellidos as cliente_apellidos,
                    u.username as vendedor
                FROM ventas v
                INNER JOIN clientes c ON v.id_cliente = c.id_cliente
                INNER JOIN personas p_cliente ON c.id_persona = p_cliente.id_persona
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                WHERE v.estado = 1 AND DATE(v.fecha) BETWEEN :desde AND :hasta
                ORDER BY v.fecha DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reporteBeneficio($desde, $hasta) {
        $sql = "SELECT 
                    i.nombre as producto,
                    c.nombre as categoria,
                    SUM(dv.cantidad) as cantidad_vendida,
                    NULL as peso_vendido,
                    SUM(dv.subtotal) as ingresos,
                    SUM(dv.cantidad * dv.costo_unitario) as costo_total,
                    SUM(dv.subtotal) - SUM(dv.cantidad * dv.costo_unitario) as beneficio
                FROM detalle_ventas dv
                INNER JOIN ventas v ON dv.id_venta = v.id_venta
                INNER JOIN productos i ON dv.id_producto = i.id_producto
                INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                WHERE v.estado = 1 AND DATE(v.fecha) BETWEEN :desde AND :hasta
                GROUP BY dv.id_producto
                ORDER BY beneficio DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
