<?php
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "Acceso denegado."]);
    exit;
}

require_once dirname(__DIR__) . '/config/csrf.php';
csrfRequerir();

require_once '../models/M_Reporte.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$model = new M_Reporte();

// Obtener parámetros comunes. "Hoy" por defecto se toma del reloj de MySQL, no del
// de PHP: estos valores se comparan contra columnas DATETIME de la base y ambos
// relojes difieren 5 horas (ver fechaHoyBD() en config/conexion.php).
require_once dirname(__DIR__) . '/config/conexion.php';
$hoy = fechaHoyBD();
$desde = isset($_GET['desde']) && !empty($_GET['desde']) ? $_GET['desde'] : $hoy;
$hasta = isset($_GET['hasta']) && !empty($_GET['hasta']) ? $_GET['hasta'] : $hoy;
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'dia';

switch ($action) {
    case 'dashboard_data':
        $kpis = $model->getKPIs($desde, $hasta);
        $ventas_periodo = $model->getVentasPorPeriodo($desde, $hasta, $agrupacion);
        $ventas_comprobante = $model->getVentasPorComprobante($desde, $hasta);
        $top_productos = $model->getTopProductos($desde, $hasta, 5);
        
        echo json_encode([
            "success" => true,
            "data" => [
                "kpis" => $kpis,
                "tendencia" => $ventas_periodo,
                "comprobantes" => $ventas_comprobante,
                "top_productos" => $top_productos
            ]
        ]);
        break;

    case 'ventas_list':
        $ventas = $model->getVentasDetalladas($desde, $hasta);
        echo json_encode(["success" => true, "data" => $ventas]);
        break;

    case 'inventario_list':
        $stock = $model->getEstadoStock();
        echo json_encode(["success" => true, "data" => $stock]);
        break;

    case 'clientes_list':
        $clientes = $model->getTopClientes($desde, $hasta, 100); // 100 clientes
        echo json_encode(["success" => true, "data" => $clientes]);
        break;
        
    case 'vendedores_list':
        $vendedores = $model->getVentasPorVendedor($desde, $hasta);
        echo json_encode(["success" => true, "data" => $vendedores]);
        break;

    case 'beneficio_list':
        $beneficios = $model->reporteBeneficio($desde, $hasta);
        echo json_encode(["success" => true, "data" => $beneficios]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
