<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/config/conexion.php';

try {
    $dbh = Conexion::singleton()->getConexion();

    // 1. Ventas Totales
    $stmt = $dbh->query("SELECT COALESCE(SUM(total), 0) as total FROM ventas WHERE estado = 1");
    $totalSales = floatval($stmt->fetch()['total']);

    // 2. Comprobantes Emitidos (Ventas completadas)
    $stmt = $dbh->query("SELECT COUNT(*) as count FROM ventas WHERE estado = 1");
    $completedCount = intval($stmt->fetch()['count']);

    // 3. Usuarios Activos
    $stmt = $dbh->query("SELECT COUNT(*) as count FROM usuarios u INNER JOIN personas p ON u.id_persona = p.id_persona WHERE p.estado = 1");
    $activeUsers = intval($stmt->fetch()['count']);

    // 4. Insumos con Stock Bajo (<= 20)
    $stmt = $dbh->query("SELECT COUNT(*) as count FROM insumos WHERE stock_piezas <= 20 AND estado = 1");
    $lowStockCount = intval($stmt->fetch()['count']);

    // 5. Historial Reciente (Últimas 5 ventas)
    $stmt = $dbh->query("SELECT v.id_venta, v.tipo_comprobante, v.fecha, v.total, v.estado,
                                p.nombres_razon_social AS cliente_nombre 
                         FROM ventas v
                         INNER JOIN clientes c ON v.id_cliente = c.id_cliente
                         INNER JOIN personas p ON c.id_persona = p.id_persona
                         ORDER BY v.fecha DESC LIMIT 5");
    $recentSales = $stmt->fetchAll();

    // 6. Analítica de Ventas (Últimos 7 días)
    // Para asegurarnos de que la gráfica siempre muestre los últimos 7 días con datos consistentes,
    // creamos un array con los últimos 7 días y llenamos los valores con las ventas reales.
    $ventasPorDia = [];
    $diasSemanaMap = [
        'Sunday' => 'Dom',
        'Monday' => 'Lun',
        'Tuesday' => 'Mar',
        'Wednesday' => 'Mié',
        'Thursday' => 'Jue',
        'Friday' => 'Vie',
        'Saturday' => 'Sáb'
    ];

    for ($i = 6; $i >= 0; $i--) {
        $timestamp = strtotime("-$i days");
        $fechaKey = date('Y-m-d', $timestamp);
        $diaIngles = date('l', $timestamp);
        $diaSemana = isset($diasSemanaMap[$diaIngles]) ? $diasSemanaMap[$diaIngles] : substr($diaIngles, 0, 3);
        
        $ventasPorDia[$fechaKey] = [
            "dia" => $diaSemana,
            "fecha" => $fechaKey,
            "ventas" => 0.0
        ];
    }

    $stmt = $dbh->query("SELECT DATE(fecha) as fecha_dia, SUM(total) as total_dia 
                         FROM ventas 
                         WHERE estado = 1 AND fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                         GROUP BY DATE(fecha)");
    $dbVentas = $stmt->fetchAll();
    
    foreach ($dbVentas as $row) {
        $f = $row['fecha_dia'];
        if (isset($ventasPorDia[$f])) {
            $ventasPorDia[$f]['ventas'] = floatval($row['total_dia']);
        }
    }

    $chartData = array_values($ventasPorDia);

    // 7. Productos más vendidos (Top 5)
    $stmt = $dbh->query("SELECT i.nombre, SUM(dv.cantidad) as vendidos, i.stock_piezas, um.abreviatura as unidad
                         FROM detalle_ventas dv
                         INNER JOIN insumos i ON dv.id_insumo = i.id_insumo
                         INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                         INNER JOIN ventas v ON dv.id_venta = v.id_venta
                         WHERE v.estado = 1
                         GROUP BY dv.id_insumo
                         ORDER BY vendidos DESC
                         LIMIT 5");
    $topProducts = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "metrics" => [
            "totalSales" => $totalSales,
            "completedCount" => $completedCount,
            "activeUsers" => $activeUsers,
            "lowStockCount" => $lowStockCount
        ],
        "recentSales" => $recentSales,
        "chartData" => $chartData,
        "topProducts" => $topProducts
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "mensaje" => "Error de base de datos: " . $e->getMessage()
    ]);
}
?>
