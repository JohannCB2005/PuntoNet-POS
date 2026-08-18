<?php
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/config/csrf.php';
csrfRequerir();

require_once dirname(__DIR__) . '/entities/Alumno.php';
require_once dirname(__DIR__) . '/models/M_Alumno.php';
require_once dirname(__DIR__) . '/models/M_Cruce.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$model = M_Alumno::singleton();

switch ($action) {

    case 'listar':
        $filtros = [
            'id_nivel' => isset($_GET['id_nivel']) ? (int) $_GET['id_nivel'] : null,
            'matriculado' => isset($_GET['matriculado']) ? (int) $_GET['matriculado'] : null,
        ];
        echo json_encode($model->listar($filtros));
        break;

    case 'crear':
        $codigo = isset($input['codigo']) ? trim($input['codigo']) : '';
        $nombre = isset($input['nombre_completo']) ? trim($input['nombre_completo']) : '';
        $idNivel = isset($input['id_nivel']) ? (int) $input['id_nivel'] : null;
        $idGrado = isset($input['id_grado']) ? (int) $input['id_grado'] : null;
        $seccion = isset($input['seccion']) ? trim($input['seccion']) : '';
        $matriculado = isset($input['matriculado']) ? (int) $input['matriculado'] : 1;

        if (empty($codigo) || empty($nombre) || empty($idNivel) || empty($idGrado)) {
            echo json_encode(["success" => false, "mensaje" => "Código, nombre, nivel y grado son obligatorios."]);
            exit;
        }

        $a = new Alumno($codigo, $nombre, M_Cruce::normalizarNombre($nombre), $idNivel, $idGrado, $seccion, $matriculado, 2);
        $resultado = $model->altaManual($a);
        echo json_encode($resultado);
        break;

    case 'actualizar':
        $id = isset($input['id_alumno']) ? (int) $input['id_alumno'] : 0;
        $nombre = isset($input['nombre_completo']) ? trim($input['nombre_completo']) : '';
        $idNivel = isset($input['id_nivel']) ? (int) $input['id_nivel'] : null;
        $idGrado = isset($input['id_grado']) ? (int) $input['id_grado'] : null;
        $seccion = isset($input['seccion']) ? trim($input['seccion']) : '';
        $matriculado = isset($input['matriculado']) ? (int) $input['matriculado'] : 1;

        if ($id <= 0 || empty($nombre) || empty($idNivel) || empty($idGrado)) {
            echo json_encode(["success" => false, "mensaje" => "Datos incompletos."]);
            exit;
        }

        $a = new Alumno('', $nombre, M_Cruce::normalizarNombre($nombre), $idNivel, $idGrado, $seccion, $matriculado, 2, $id);
        if ($model->actualizar($a)) {
            echo json_encode(["success" => true, "mensaje" => "Alumno actualizado."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se pudo actualizar el alumno."]);
        }
        break;

    case 'eliminar':
        $id = isset($input['id_alumno']) ? (int) $input['id_alumno'] : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID inválido."]);
            exit;
        }
        if ($model->eliminar($id)) {
            echo json_encode(["success" => true, "mensaje" => "Alumno dado de baja."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se pudo dar de baja al alumno."]);
        }
        break;

    // Fija a mano cuánto paga este alumno habitualmente (o, con el campo
    // vacío, devuelve el control a la detección automática por importación).
    case 'fijar_pension':
        $id = isset($input['id_alumno']) ? (int) $input['id_alumno'] : 0;
        $montoRaw = isset($input['pension_pactada']) ? trim((string) $input['pension_pactada']) : '';
        $monto = $montoRaw !== '' ? (float) $montoRaw : null;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID inválido."]);
            exit;
        }
        if ($monto !== null && $monto <= 0) {
            echo json_encode(["success" => false, "mensaje" => "La pensión pactada debe ser mayor a 0."]);
            exit;
        }
        if ($model->fijarPensionPactada($id, $monto)) {
            echo json_encode(["success" => true, "mensaje" => "Pensión pactada actualizada."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se pudo actualizar la pensión pactada."]);
        }
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>