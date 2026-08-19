<?php
// Iniciar la sesión PHP para validar accesos
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();

// Definir el tipo de respuesta a retornar (JSON)
header('Content-Type: application/json');

// Validar que exista una sesión activa
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/config/csrf.php';
csrfRequerir();

// Cargar las dependencias necesarias: Modelo de Tipo de Variante
require_once dirname(__DIR__) . '/models/M_TipoVariante.php';

// Obtener la acción solicitada por URL (GET)
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Extraer los datos enviados en formato JSON (fetch) o por POST tradicional
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el modelo usando el patrón Singleton
$model = M_TipoVariante::singleton();

// Enrutar según la petición
switch ($action) {

    // Obtiene el listado de tipos de variante
    case 'listar':
        echo json_encode($model->listar());
        break;

    // Crea un nuevo tipo de variante (Acción restringida a Administradores)
    case 'crear':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $nombre   = isset($input['nombre']) ? trim($input['nombre']) : '';
        $modo     = isset($input['modo']) ? $input['modo'] : 'lista';
        $opciones = isset($input['opciones']) && is_array($input['opciones']) ? $input['opciones'] : [];

        if ($nombre === '') {
            echo json_encode(["success" => false, "mensaje" => "El nombre es obligatorio."]);
            exit;
        }
        if ($modo === 'lista' && count(array_filter(array_map('trim', array_column($opciones, 'nombre')))) === 0) {
            echo json_encode(["success" => false, "mensaje" => "Agrega al menos una opción para un tipo de lista."]);
            exit;
        }

        $resultado = $model->registrar($nombre, $modo, $opciones);
        echo json_encode(["success" => $resultado['ok'], "mensaje" => $resultado['mensaje']]);
        break;

    // Actualiza un tipo de variante existente (Acción restringida a Administradores)
    case 'actualizar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $id       = isset($input['id_tipo_variante']) ? intval($input['id_tipo_variante']) : 0;
        $nombre   = isset($input['nombre']) ? trim($input['nombre']) : '';
        $modo     = isset($input['modo']) ? $input['modo'] : 'lista';
        $opciones = isset($input['opciones']) && is_array($input['opciones']) ? $input['opciones'] : [];

        if ($id <= 0 || $nombre === '') {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos."]);
            exit;
        }
        if ($modo === 'lista' && count(array_filter(array_map('trim', array_column($opciones, 'nombre')))) === 0) {
            echo json_encode(["success" => false, "mensaje" => "Agrega al menos una opción para un tipo de lista."]);
            exit;
        }

        $resultado = $model->actualizar($id, $nombre, $modo, $opciones);
        echo json_encode(["success" => $resultado['ok'], "mensaje" => $resultado['mensaje']]);
        break;

    // Elimina un tipo de variante personalizado (Acción restringida a Administradores)
    case 'eliminar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $id = isset($input['id_tipo_variante']) ? intval($input['id_tipo_variante']) : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de tipo de variante inválido."]);
            exit;
        }

        $resultado = $model->eliminar($id);
        echo json_encode(["success" => $resultado['ok'], "mensaje" => $resultado['mensaje']]);
        break;

    // Renombra una opción individual (Acción restringida a Administradores)
    case 'editar_opcion':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $id          = isset($input['id_opcion']) ? intval($input['id_opcion']) : 0;
        $nombre      = isset($input['nombre']) ? trim($input['nombre']) : '';
        $tipoSistema = isset($input['tipo_sistema']) ? trim($input['tipo_sistema']) : '';
        if ($id <= 0 || $nombre === '') {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos."]);
            exit;
        }

        if ($tipoSistema !== '') {
            $resultado = $model->actualizarOpcionSistema($tipoSistema, $id, $nombre);
        } else {
            $resultado = $model->actualizarOpcion($id, $nombre);
        }
        echo json_encode(["success" => $resultado['ok'], "mensaje" => $resultado['mensaje']]);
        break;

    // Elimina una opción individual (Acción restringida a Administradores)
    case 'eliminar_opcion':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $id          = isset($input['id_opcion']) ? intval($input['id_opcion']) : 0;
        $tipoSistema = isset($input['tipo_sistema']) ? trim($input['tipo_sistema']) : '';
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de opción inválido."]);
            exit;
        }

        if ($tipoSistema !== '') {
            $resultado = $model->eliminarOpcionSistema($tipoSistema, $id);
        } else {
            $resultado = $model->eliminarOpcion($id);
        }
        echo json_encode(["success" => $resultado['ok'], "mensaje" => $resultado['mensaje']]);
        break;

    // Cambia el estado activo/inactivo de un tipo de variante
    case 'toggle':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $id = isset($input['id_tipo_variante']) ? intval($input['id_tipo_variante']) : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de tipo de variante inválido."]);
            exit;
        }

        $resultado = $model->toggleEstado($id);
        echo json_encode(["success" => $resultado['ok'], "mensaje" => $resultado['mensaje']]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}