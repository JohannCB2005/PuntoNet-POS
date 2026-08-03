<?php
// Iniciar sesión PHP para el control de autenticación
session_start();

// Definir cabecera de respuesta JSON
header('Content-Type: application/json');

// Restringir el acceso de este controlador únicamente a usuarios con rol de Administrador
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

// Cargar las entidades y modelos necesarios para insumo
require_once dirname(__DIR__) . '/entities/Insumo.php';
require_once dirname(__DIR__) . '/models/M_Insumo.php';

// Obtener la acción a realizar
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Decodificar el cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el modelo de insumos bajo Singleton
$model = M_Insumo::singleton();

// Enrutar según la acción
switch ($action) {
    
    // Retorna la lista de todos los insumos activos en el inventario
    case 'listar':
        echo json_encode($model->listar());
        break;

    // Registra un nuevo insumo en el inventario
    case 'crear':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        $id_categoria       = isset($input['id_categoria'])       ? intval($input['id_categoria'])      : 0;
        $id_unidad          = isset($input['id_unidad'])          ? intval($input['id_unidad'])          : 0;
        $nombre             = isset($input['nombre'])             ? trim($input['nombre'])               : '';
        $precio_unitario    = isset($input['precio_unitario'])    ? floatval($input['precio_unitario'])  : 0.0;
        $costo_produccion   = isset($input['costo_produccion'])   ? floatval($input['costo_produccion']) : 0.0;
        $stock_piezas       = isset($input['stock'])              ? floatval($input['stock'])            : 0.0;
        $id_talla           = isset($input['id_talla']) && $input['id_talla'] !== '' ? intval($input['id_talla']) : null;
        $id_tipo_corbata    = isset($input['id_tipo_corbata']) && $input['id_tipo_corbata'] !== '' ? intval($input['id_tipo_corbata']) : null;
        $id_nivel           = isset($input['id_nivel']) && $input['id_nivel'] !== '' ? intval($input['id_nivel']) : null;
        $id_grado           = isset($input['id_grado']) && $input['id_grado'] !== '' ? intval($input['id_grado']) : null;
        $id_area            = isset($input['id_area']) && $input['id_area'] !== '' ? intval($input['id_area']) : null;
        $id_bimestre        = isset($input['id_bimestre']) && $input['id_bimestre'] !== '' ? intval($input['id_bimestre']) : null;

        // Validaciones básicas de integridad de datos
        if ($id_categoria <= 0 || $id_unidad <= 0 || empty($nombre) || $precio_unitario < 0 || $stock_piezas < 0) {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos o faltantes."]);
            exit;
        }

        // Procesar subida de imagen si existe
        $imagen_db = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen']['tmp_name'];
            $fileName = $_FILES['imagen']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = dirname(__DIR__) . '/assets/productos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'insumo_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $imagen_db = $newFileName;
                }
            }
        }

        // Crear entidad insumo y guardar en DB
        $insumo = new Insumo($id_categoria, $id_unidad, $nombre, $precio_unitario, $costo_produccion, $stock_piezas, null, $imagen_db, $id_talla, $id_tipo_corbata, $id_nivel, $id_grado, $id_area, $id_bimestre);
        if ($model->registrar($insumo)) {
            echo json_encode(["success" => true, "mensaje" => "Insumo registrado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al registrar el insumo."]);
        }
        break;

    // Registra un producto padre con sus variantes de talla en una sola petición
    case 'crear_con_variantes':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $id_categoria = isset($input['id_categoria']) ? intval($input['id_categoria']) : 0;
        $id_unidad    = isset($input['id_unidad'])    ? intval($input['id_unidad'])    : 0;
        $nombre       = isset($input['nombre'])       ? trim($input['nombre'])         : '';
        $variantes    = isset($input['variantes'])    ? $input['variantes']            : [];

        if ($id_categoria <= 0 || $id_unidad <= 0 || empty($nombre) || empty($variantes)) {
            echo json_encode(["success" => false, "mensaje" => "Datos incompletos para crear producto con variantes."]);
            exit;
        }

        // Procesar subida de imagen compartida por todas las variantes
        $imagen_db = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen']['tmp_name'];
            $fileName = $_FILES['imagen']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = dirname(__DIR__) . '/assets/productos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newFileName = 'insumo_' . time() . '_' . uniqid() . '.' . $fileExtension;
                if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                    $imagen_db = $newFileName;
                }
            }
        }

        $datosPadre = [
            'id_categoria' => $id_categoria,
            'id_unidad'    => $id_unidad,
            'nombre'       => $nombre,
            'imagen'       => $imagen_db,
        ];

        $resultado = $model->registrarConVariantes($datosPadre, $variantes);
        echo json_encode([
            'success' => $resultado['ok'],
            'mensaje' => $resultado['mensaje'],
            'id_padre' => $resultado['id_padre'] ?? null,
        ]);
        break;

    // Actualiza un producto padre con sus variantes de talla en una sola petición
    case 'actualizar_con_variantes':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }

        $id_padre     = isset($input['id_insumo'])    ? intval($input['id_insumo'])    : 0;
        $id_categoria = isset($input['id_categoria']) ? intval($input['id_categoria']) : 0;
        $id_unidad    = isset($input['id_unidad'])    ? intval($input['id_unidad'])    : 0;
        $nombre       = isset($input['nombre'])       ? trim($input['nombre'])         : '';
        $variantes    = isset($input['variantes'])    ? $input['variantes']            : [];

        if ($id_padre <= 0 || $id_categoria <= 0 || $id_unidad <= 0 || empty($nombre) || empty($variantes)) {
            echo json_encode(["success" => false, "mensaje" => "Datos incompletos para actualizar producto con variantes."]);
            exit;
        }

        // Procesar subida de imagen compartida por todas las variantes
        $imagen_db = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen']['tmp_name'];
            $fileName = $_FILES['imagen']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = dirname(__DIR__) . '/assets/productos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newFileName = 'insumo_' . time() . '_' . uniqid() . '.' . $fileExtension;
                if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                    $imagen_db = $newFileName;
                }
            }
        }

        $datosPadre = [
            'id_categoria' => $id_categoria,
            'id_unidad'    => $id_unidad,
            'nombre'       => $nombre,
            'imagen'       => $imagen_db,
        ];

        $resultado = $model->actualizarConVariantes($id_padre, $datosPadre, $variantes);
        echo json_encode([
            'success' => $resultado['ok'],
            'mensaje' => $resultado['mensaje']
        ]);
        break;

    // Actualiza los datos de un insumo existente
    case 'actualizar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        $id_insumo          = isset($input['id_insumo'])          ? intval($input['id_insumo'])          : 0;
        $id_categoria       = isset($input['id_categoria'])       ? intval($input['id_categoria'])       : 0;
        $id_unidad          = isset($input['id_unidad'])          ? intval($input['id_unidad'])          : 0;
        $nombre             = isset($input['nombre'])             ? trim($input['nombre'])               : '';
        $precio_unitario    = isset($input['precio_unitario'])    ? floatval($input['precio_unitario'])  : 0.0;
        $costo_produccion   = isset($input['costo_produccion'])   ? floatval($input['costo_produccion']) : 0.0;
        $stock_piezas       = isset($input['stock'])              ? floatval($input['stock'])            : 0.0;
        $id_talla           = isset($input['id_talla']) && $input['id_talla'] !== '' ? intval($input['id_talla']) : null;
        $id_tipo_corbata    = isset($input['id_tipo_corbata']) && $input['id_tipo_corbata'] !== '' ? intval($input['id_tipo_corbata']) : null;
        $id_nivel           = isset($input['id_nivel']) && $input['id_nivel'] !== '' ? intval($input['id_nivel']) : null;
        $id_grado           = isset($input['id_grado']) && $input['id_grado'] !== '' ? intval($input['id_grado']) : null;
        $id_area            = isset($input['id_area']) && $input['id_area'] !== '' ? intval($input['id_area']) : null;
        $id_bimestre        = isset($input['id_bimestre']) && $input['id_bimestre'] !== '' ? intval($input['id_bimestre']) : null;

        // Validaciones de integridad
        if ($id_insumo <= 0 || $id_categoria <= 0 || $id_unidad <= 0 || empty($nombre) || $precio_unitario < 0 || $stock_piezas < 0) {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos o faltantes."]);
            exit;
        }

        // Procesar subida de imagen si existe
        $imagen_db = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen']['tmp_name'];
            $fileName = $_FILES['imagen']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = dirname(__DIR__) . '/assets/productos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Borrar imagen vieja si existe
                $insumoViejo = $model->obtenerPorId($id_insumo);
                if ($insumoViejo && !empty($insumoViejo['imagen'])) {
                    $oldFilePath = $uploadDir . $insumoViejo['imagen'];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $newFileName = 'insumo_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $imagen_db = $newFileName;
                }
            }
        }

        // Actualizar datos del insumo
        $insumo = new Insumo($id_categoria, $id_unidad, $nombre, $precio_unitario, $costo_produccion, $stock_piezas, $id_insumo, $imagen_db, $id_talla, $id_tipo_corbata, $id_nivel, $id_grado, $id_area, $id_bimestre);

        if ($model->actualizar($insumo)) {
            echo json_encode(["success" => true, "mensaje" => "Insumo actualizado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al actualizar el insumo."]);
        }
        break;

    // Actualiza el stock de un producto
    case 'actualizar_stock':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id_insumo'], $data['variacion'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos.']);
            exit;
        }

        $id = $data['id_insumo'];
        $variacion = floatval($data['variacion']);

        if (M_Insumo::singleton()->actualizarStockRapido($id, $variacion)) {
            echo json_encode(['success' => true, 'mensaje' => 'Stock actualizado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'No se pudo actualizar el stock.']);
        }
        break;

    // Elimina de forma lógica un insumo del catálogo
    case 'eliminar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        $id_insumo = isset($input['id_insumo']) ? intval($input['id_insumo']) : 0;

        if ($id_insumo <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de insumo inválido."]);
            exit;
        }

        // Ejecutar borrado
        if ($model->eliminar($id_insumo)) {
            echo json_encode(["success" => true, "mensaje" => "Insumo eliminado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al eliminar el insumo."]);
        }
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
