<?php
// Iniciar sesión PHP para el control de identidad y roles de usuario
session_start();

// Configurar cabecera para responder en formato JSON
header('Content-Type: application/json');

// Validar que el usuario esté autenticado
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

// Cargar entidades y modelos requeridos para procesar ventas
require_once dirname(__DIR__) . '/entities/Venta.php';
require_once dirname(__DIR__) . '/entities/DetalleVenta.php';
require_once dirname(__DIR__) . '/models/M_Venta.php';
require_once dirname(__DIR__) . '/models/M_Caja.php';

// Obtener la acción a realizar
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Capturar los parámetros de entrada en formato JSON o POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el modelo centralizado de Ventas usando Singleton
$model = M_Venta::singleton();

// Enrutar según la acción
switch ($action) {
    
    // Lista el historial de ventas
    case 'listar':
        // Restricción: Si el rol no es Administrador, solo listar las ventas hechas por el usuario actual
        $id_vendedor = ($_SESSION['rol'] !== 'Administrador') ? $_SESSION['id_usuario'] : null;
        echo json_encode($model->listar($id_vendedor));
        break;

    // Registra una nueva venta con sus respectivas líneas de detalle
    case 'crear':
        $id_usuario = $_SESSION['id_usuario'];
        $id_cliente = isset($input['id_cliente']) && $input['id_cliente'] ? intval($input['id_cliente']) : 1;

        $tipo_comprobante = isset($input['tipo_comprobante']) ? intval($input['tipo_comprobante']) : 1; // 1 = Boleta, 2 = Factura, 3 = Nota de venta
        $total = isset($input['total']) ? floatval($input['total']) : 0.0;
        $metodo_pago = isset($input['metodo_pago']) ? intval($input['metodo_pago']) : 1;
        $cart = isset($input['cart']) ? $input['cart'] : []; // Elementos del carrito: {id_producto, cantidad, precio, subtotal}

        // Validación inicial
        if (empty($cart) || $total <= 0) {
            echo json_encode(["success" => false, "mensaje" => "El carrito está vacío o el total es cero."]);
            exit;
        }

        // CONTROL DE CAJA: Validar obligatoriamente que el usuario tenga una sesión de caja abierta
        $modelCaja = M_Caja::singleton();
        if (!$modelCaja->obtenerCajaAbierta($id_usuario)) {
            echo json_encode(["success" => false, "mensaje" => "Debes abrir caja antes de registrar ventas."]);
            exit;
        }

        // Crear la entidad principal de Venta
        $venta = new Venta(null, $id_usuario, $id_cliente, '', $tipo_comprobante, $total, $metodo_pago);
        
        // Cargar los items del carrito dentro de la entidad de venta
        foreach ($cart as $item) {
            $id_producto  = intval($item['id_producto']);
            // piezas: unidades físicas vendidas (descuenta stock)
            $piezas     = floatval($item['piezas'] ?? $item['cantidad'] ?? 0);
            $precio     = floatval($item['precio']);
            $subtotal   = floatval($item['subtotal']);

            $detalle = new DetalleVenta(null, $id_producto, $piezas, $precio, 0.0, $subtotal);
            $venta->agregarDetalle($detalle);
        }

        // Intentar registrar la venta de manera transaccional en la DB (afectará stock e inventario)
        $resultado = $model->registrar($venta);
        if ($resultado['ok']) {
            echo json_encode(["success" => true, "mensaje" => "Venta registrada con éxito.", "id_venta" => $resultado['id_venta']]);
        } else {
            echo json_encode(["success" => false, "mensaje" => $resultado['mensaje']]);
        }
        break;

    // Anula una venta previamente registrada y devuelve la mercadería al inventario
    case 'anular':
        $id_venta = isset($input['id_venta']) ? intval($input['id_venta']) : 0;

        if ($id_venta <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de venta inválido."]);
            exit;
        }

        // Restricción de seguridad: Si no es Admin, verificar que la venta pertenezca al vendedor logueado
        if ($_SESSION['rol'] !== 'Administrador') {
            $ventas_vendedor = $model->listar($_SESSION['id_usuario']);
            $pertence_a_usuario = false;
            foreach ($ventas_vendedor as $v) {
                if ($v['id_venta'] == $id_venta) {
                    $pertence_a_usuario = true;
                    break;
                }
            }
            if (!$pertence_a_usuario) {
                echo json_encode(["success" => false, "mensaje" => "No autorizado para anular esta venta."]);
                exit;
            }
        }

        // Ejecutar proceso de anulación en base de datos
        $resultadoAnular = $model->anular($id_venta);
        if ($resultadoAnular['ok']) {
            echo json_encode(["success" => true, "mensaje" => "Venta anulada con éxito. El stock ha sido retornado."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => $resultadoAnular['mensaje']]);
        }
        break;

    // Devuelve los detalles (líneas de venta) de una venta específica
    case 'detalles':
        $id_venta = isset($_GET['id_venta']) ? intval($_GET['id_venta']) : (isset($input['id_venta']) ? intval($input['id_venta']) : 0);

        if ($id_venta <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de venta inválido."]);
            exit;
        }

        // Restricción: Si no es Admin, solo puede consultar detalles de sus propias ventas
        if ($_SESSION['rol'] !== 'Administrador') {
            $ventas_vendedor = $model->listar($_SESSION['id_usuario']);
            $pertence_a_usuario = false;
            foreach ($ventas_vendedor as $v) {
                if ($v['id_venta'] == $id_venta) {
                    $pertence_a_usuario = true;
                    break;
                }
            }
            if (!$pertence_a_usuario) {
                echo json_encode([]);
                exit;
            }
        }

        // Obtener detalles desde el modelo de base de datos
        echo json_encode($model->obtenerDetallesPorVenta($id_venta));
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
