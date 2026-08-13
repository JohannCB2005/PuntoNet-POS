<?php
session_start();
header('Content-Type: application/json');

// Módulo de planilla: solo Administrador ve las comisiones de todos los vendedores.
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/models/M_Comision.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$model = M_Comision::singleton();

// Periodo por defecto = mes en curso según el reloj de MySQL. Con date() de PHP,
// el último día del mes a partir de las 19:00 (hora de Perú) el "desde" saltaba al
// mes siguiente y la planilla del mes aparecía vacía (ver fechaHoyBD()).
require_once dirname(__DIR__) . '/config/conexion.php';
$hoyBD = fechaHoyBD();
$desde = isset($_GET['desde']) && !empty($_GET['desde']) ? $_GET['desde'] : substr($hoyBD, 0, 7) . '-01';
$hasta = isset($_GET['hasta']) && !empty($_GET['hasta']) ? $_GET['hasta'] : $hoyBD;

switch ($action) {

    case 'resumen':
        echo json_encode(["success" => true, "data" => $model->resumenPorUsuario($desde, $hasta)]);
        break;

    case 'detalle':
        $id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : 0;
        if ($id_usuario <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Falta id_usuario."]);
            exit;
        }
        echo json_encode(["success" => true, "data" => $model->detallePorUsuario($id_usuario, $desde, $hasta)]);
        break;

    case 'pendientes':
        echo json_encode(["success" => true, "data" => $model->pendientesPorUsuario()]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
