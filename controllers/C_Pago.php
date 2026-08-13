<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/entities/Pago.php';
require_once dirname(__DIR__) . '/models/M_Pago.php';
require_once dirname(__DIR__) . '/models/M_Cruce.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$model = M_Pago::singleton();

switch ($action) {

    case 'listar':
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
        $anio = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
        echo json_encode($model->listarPorPeriodo($mes, $anio));
        break;

    case 'pendientes':
        echo json_encode($model->pendientesConciliacion());
        break;

    case 'candidatos':
        $nombreNormalizado = isset($_GET['nombre_normalizado']) ? $_GET['nombre_normalizado'] : '';
        echo json_encode($model->candidatosParaConciliar($nombreNormalizado));
        break;

    case 'conciliar':
        $idPago = isset($input['id_pago']) ? (int) $input['id_pago'] : 0;
        $idAlumno = isset($input['id_alumno']) && $input['id_alumno'] !== '' ? (int) $input['id_alumno'] : null;
        $motivo = isset($input['motivo']) ? trim($input['motivo']) : null;
        if ($idPago <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Pago inválido."]);
            exit;
        }
        if ($model->conciliarManualmente($idPago, $idAlumno, (int) $_SESSION['id_usuario'], $motivo)) {
            echo json_encode(["success" => true, "mensaje" => $idAlumno ? "Pago asignado al alumno." : "Pago desasignado."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se pudo conciliar el pago."]);
        }
        break;

    case 'descartar':
        $idPago = isset($input['id_pago']) ? (int) $input['id_pago'] : 0;
        $motivo = isset($input['motivo']) ? trim($input['motivo']) : 'Descartado manualmente';
        if ($idPago <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Pago inválido."]);
            exit;
        }
        if ($model->conciliarManualmente($idPago, null, (int) $_SESSION['id_usuario'], $motivo)) {
            echo json_encode(["success" => true, "mensaje" => "Pago descartado de la bandeja."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se pudo descartar el pago."]);
        }
        break;

    case 'crear':
        $idAlumno = isset($input['id_alumno']) && $input['id_alumno'] !== '' ? (int) $input['id_alumno'] : null;
        $numero = isset($input['numero']) ? trim($input['numero']) : '';
        $nombre = isset($input['nombre_comprobante']) ? trim($input['nombre_comprobante']) : '';
        $mes = isset($input['mes_concepto']) ? (int) $input['mes_concepto'] : 0;
        $anio = isset($input['anio_concepto']) ? (int) $input['anio_concepto'] : 0;
        $total = isset($input['total']) ? (float) $input['total'] : 0.0;
        $fechaPago = isset($input['fecha_pago']) ? trim((string) $input['fecha_pago']) : '';
        $fechaPago = ($fechaPago !== '' && DateTime::createFromFormat('Y-m-d', $fechaPago) !== false) ? $fechaPago : date('Y-m-d');

        if ($numero === '' || $nombre === '' || $mes < 1 || $mes > 12 || $anio < 2000 || $total <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Completa número, nombre, mes/año y monto."]);
            exit;
        }

        $p = new Pago($numero, $nombre, M_Cruce::normalizarNombre($nombre), 'Pensión - Manual - ' . $anio, $mes, $anio, $total, $idAlumno, 2);
        $p->fecha_pago = $fechaPago;
        echo json_encode($model->altaManual($p));
        break;

    case 'eliminar':
        $id = isset($input['id_pago']) ? (int) $input['id_pago'] : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID inválido."]);
            exit;
        }
        echo json_encode(["success" => $model->eliminar($id), "mensaje" => "Pago dado de baja."]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>