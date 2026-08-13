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

// Cargar las entidades y modelos necesarios para producto
require_once dirname(__DIR__) . '/entities/Producto.php';
require_once dirname(__DIR__) . '/models/M_Producto.php';

// Obtener la acción a realizar
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Decodificar el cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el modelo de productos bajo Singleton
$model = M_Producto::singleton();

/**
 * Valida y guarda la imagen subida en assets/productos/, devolviendo el nombre de
 * archivo generado (o null si no vino imagen o no pasó la validación).
 *
 * Tres controles, los tres necesarios:
 *   - Extensión en whitelist Y nombre de archivo regenerado. Es lo que impide que
 *     se escriba un .php en un directorio servido por el navegador; el nombre que
 *     manda el cliente nunca se reutiliza.
 *   - getimagesize(): confirma que el contenido es realmente una imagen. Sin esto
 *     se podía subir cualquier cosa con tal de llamarla .jpg.
 *   - Límite de tamaño: sin él, subidas grandes repetidas llenan el disco del
 *     hosting compartido, que es poco y no avisa.
 *
 * Estaba duplicado en las 4 acciones que guardan producto (crear, crear con
 * variantes, actualizar con variantes, actualizar), cada copia ligeramente distinta.
 */
function guardarImagenProducto(string $campo = 'imagen'): ?string {
    $MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    $EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $TIPOS_IMAGEN = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = $_FILES[$campo]['tmp_name'];
    if (!is_uploaded_file($tmp) || $_FILES[$campo]['size'] > $MAX_BYTES) {
        return null;
    }

    $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $EXTENSIONES, true)) {
        return null;
    }

    // El contenido tiene que ser una imagen de verdad, no solo llamarse .jpg.
    $info = @getimagesize($tmp);
    if ($info === false || !in_array($info[2], $TIPOS_IMAGEN, true)) {
        return null;
    }

    $uploadDir = dirname(__DIR__) . '/assets/productos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $nombreArchivo = 'producto_' . time() . '_' . uniqid() . '.' . $extension;
    return move_uploaded_file($tmp, $uploadDir . $nombreArchivo) ? $nombreArchivo : null;
}

// Enrutar según la acción
switch ($action) {
    
    // Retorna la lista de todos los productos activos en el inventario
    case 'listar':
        echo json_encode($model->listar());
        break;

    // Registra un nuevo producto en el inventario
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
        $comision           = isset($input['comision'])           ? floatval($input['comision'])         : 0.0;
        $stock_piezas       = isset($input['stock'])              ? floatval($input['stock'])            : 0.0;
        $stock_ilimitado    = isset($input['stock_ilimitado'])     ? intval($input['stock_ilimitado'])   : 0;
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
        $imagen_db = guardarImagenProducto();

        // Crear entidad producto y guardar en DB
        $producto = new Producto($id_categoria, $id_unidad, $nombre, $precio_unitario, $costo_produccion, $stock_piezas, null, $imagen_db, $id_talla, $id_tipo_corbata, $id_nivel, $id_grado, $id_area, $id_bimestre, 0, null, $comision, $stock_ilimitado);
        if ($model->registrar($producto)) {
            echo json_encode(["success" => true, "mensaje" => "Producto registrado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al registrar el producto."]);
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
        $imagen_db = guardarImagenProducto();

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

        $id_padre     = isset($input['id_producto'])    ? intval($input['id_producto'])    : 0;
        $id_categoria = isset($input['id_categoria']) ? intval($input['id_categoria']) : 0;
        $id_unidad    = isset($input['id_unidad'])    ? intval($input['id_unidad'])    : 0;
        $nombre       = isset($input['nombre'])       ? trim($input['nombre'])         : '';
        $variantes    = isset($input['variantes'])    ? $input['variantes']            : [];

        if ($id_padre <= 0 || $id_categoria <= 0 || $id_unidad <= 0 || empty($nombre) || empty($variantes)) {
            echo json_encode(["success" => false, "mensaje" => "Datos incompletos para actualizar producto con variantes."]);
            exit;
        }

        // Procesar subida de imagen compartida por todas las variantes
        $imagen_db = guardarImagenProducto();

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

    // Actualiza los datos de un producto existente
    case 'actualizar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        $id_producto          = isset($input['id_producto'])          ? intval($input['id_producto'])          : 0;
        $id_categoria       = isset($input['id_categoria'])       ? intval($input['id_categoria'])       : 0;
        $id_unidad          = isset($input['id_unidad'])          ? intval($input['id_unidad'])          : 0;
        $nombre             = isset($input['nombre'])             ? trim($input['nombre'])               : '';
        $precio_unitario    = isset($input['precio_unitario'])    ? floatval($input['precio_unitario'])  : 0.0;
        $costo_produccion   = isset($input['costo_produccion'])   ? floatval($input['costo_produccion']) : 0.0;
        $comision           = isset($input['comision'])           ? floatval($input['comision'])         : 0.0;
        $stock_piezas       = isset($input['stock'])              ? floatval($input['stock'])            : 0.0;
        $stock_ilimitado    = isset($input['stock_ilimitado'])     ? intval($input['stock_ilimitado'])   : 0;
        $id_talla           = isset($input['id_talla']) && $input['id_talla'] !== '' ? intval($input['id_talla']) : null;
        $id_tipo_corbata    = isset($input['id_tipo_corbata']) && $input['id_tipo_corbata'] !== '' ? intval($input['id_tipo_corbata']) : null;
        $id_nivel           = isset($input['id_nivel']) && $input['id_nivel'] !== '' ? intval($input['id_nivel']) : null;
        $id_grado           = isset($input['id_grado']) && $input['id_grado'] !== '' ? intval($input['id_grado']) : null;
        $id_area            = isset($input['id_area']) && $input['id_area'] !== '' ? intval($input['id_area']) : null;
        $id_bimestre        = isset($input['id_bimestre']) && $input['id_bimestre'] !== '' ? intval($input['id_bimestre']) : null;

        // Validaciones de integridad
        if ($id_producto <= 0 || $id_categoria <= 0 || $id_unidad <= 0 || empty($nombre) || $precio_unitario < 0 || $stock_piezas < 0) {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos o faltantes."]);
            exit;
        }

        // Procesar subida de imagen si existe
        $imagen_db = guardarImagenProducto();

        // La imagen anterior se borra SOLO si la nueva se guardó bien. Antes se
        // borraba primero y, si la subida fallaba después, el producto se quedaba
        // sin ninguna imagen. basename() por si el nombre guardado trajera rutas.
        if ($imagen_db !== null) {
            $productoViejo = $model->obtenerPorId($id_producto);
            if ($productoViejo && !empty($productoViejo['imagen'])) {
                $rutaVieja = dirname(__DIR__) . '/assets/productos/' . basename($productoViejo['imagen']);
                if (is_file($rutaVieja)) {
                    unlink($rutaVieja);
                }
            }
        }

        // Actualizar datos del producto
        $producto = new Producto($id_categoria, $id_unidad, $nombre, $precio_unitario, $costo_produccion, $stock_piezas, $id_producto, $imagen_db, $id_talla, $id_tipo_corbata, $id_nivel, $id_grado, $id_area, $id_bimestre, 0, null, $comision, $stock_ilimitado);

        if ($model->actualizar($producto)) {
            echo json_encode(["success" => true, "mensaje" => "Producto actualizado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al actualizar el producto."]);
        }
        break;

    // Actualiza el stock de un producto
    case 'actualizar_stock':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id_producto'], $data['variacion'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos.']);
            exit;
        }

        $id = $data['id_producto'];
        $variacion = floatval($data['variacion']);

        // Un producto de stock ilimitado (servicio) no administra stock.
        $ins = $model->obtenerPorId($id);
        if ($ins && (int) ($ins['stock_ilimitado'] ?? 0) === 1) {
            echo json_encode(['success' => false, 'mensaje' => 'Este producto tiene stock ilimitado y no requiere gestión de stock.']);
            exit;
        }

        if (M_Producto::singleton()->actualizarStockRapido($id, $variacion)) {
            echo json_encode(['success' => true, 'mensaje' => 'Stock actualizado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'No se pudo actualizar el stock.']);
        }
        break;

    // Elimina de forma lógica un producto del catálogo
    case 'eliminar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos para esta acción."]);
            exit;
        }
        $id_producto = isset($input['id_producto']) ? intval($input['id_producto']) : 0;

        if ($id_producto <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de producto inválido."]);
            exit;
        }

        // Ejecutar borrado
        if ($model->eliminar($id_producto)) {
            echo json_encode(["success" => true, "mensaje" => "Producto eliminado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al eliminar el producto."]);
        }
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
