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

// Cargar las dependencias necesarias: Entidad y Modelo de Categoría
require_once dirname(__DIR__) . '/entities/Categoria.php';
require_once dirname(__DIR__) . '/models/M_Categoria.php';

// Obtener la acción solicitada por URL (GET)
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Extraer los datos enviados en formato JSON (fetch) o por POST tradicional
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el modelo usando el patrón Singleton
$model = M_Categoria::singleton();

// Enrutar según la petición
switch ($action) {
    
    // Obtiene una lista de todas las categorías activas
    case 'listar':
        echo json_encode($model->listar());
        break;

    // Crea un nuevo registro de categoría (Acción restringida a Administradores)
    case 'crear':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        
        $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
        $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
        
        // Validación obligatoria del nombre
        if (empty($nombre)) {
            echo json_encode(["success" => false, "mensaje" => "El nombre es obligatorio."]);
            exit;
        }
        
        // Crear entidad Categoria y proceder al registro
        $cat = new Categoria($nombre, $descripcion);
        if ($model->registrar($cat)) {
            echo json_encode(["success" => true, "mensaje" => "Categoría registrada con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al registrar la categoría."]);
        }
        break;

    // Actualiza los datos de una categoría existente (Acción restringida a Administradores)
    case 'actualizar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        
        $id = isset($input['id_categoria']) ? intval($input['id_categoria']) : 0;
        $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
        $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
        
        // Validación de datos requeridos
        if ($id <= 0 || empty($nombre)) {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos."]);
            exit;
        }
        
        // Cargar los nuevos datos en la entidad Categoria
        $cat = new Categoria($nombre, $descripcion);
        $cat->id_categoria = $id;
        
        // Ejecutar actualización
        if ($model->actualizar($cat)) {
            echo json_encode(["success" => true, "mensaje" => "Categoría actualizada con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al actualizar la categoría."]);
        }
        break;

    // Elimina una categoría por su ID de forma física o lógica (Acción restringida a Administradores)
    case 'eliminar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        
        $id = isset($input['id_categoria']) ? intval($input['id_categoria']) : 0;
        
        // Validación del ID de categoría
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de categoría inválido."]);
            exit;
        }
        
        // Ejecutar eliminación
        if ($model->eliminar($id)) {
            echo json_encode(["success" => true, "mensaje" => "Categoría eliminada con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al eliminar la categoría."]);
        }
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
