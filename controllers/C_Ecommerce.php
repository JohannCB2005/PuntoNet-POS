<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(__DIR__) . '/models/M_Ecommerce.php';
require_once dirname(__DIR__) . '/models/M_Caja.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'catalogo':
        $items = M_Ecommerce::singleton()->getCatalogo();
        echo json_encode(['success' => true, 'data' => $items]);
        break;

    case 'catalogo_agrupado':
        $grupos = M_Ecommerce::singleton()->getCatalogoAgrupado();
        echo json_encode(['success' => true, 'data' => $grupos]);
        break;

    case 'get_pedido':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID inválido']);
            exit;
        }
        $pedido = M_Ecommerce::singleton()->getPedidoPublico($id);
        echo json_encode(['success' => (bool)$pedido, 'data' => $pedido]);
        break;

    case 'crear_pedido':
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);
        if (!$data) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            exit;
        }

        $cliente = $data['cliente'] ?? [];
        $nro_operacion = trim($data['nro_operacion'] ?? '');
        $total = floatval($data['total'] ?? 0);
        $carrito = $data['carrito'] ?? [];

        if (empty($cliente['dni']) || empty($cliente['nombres']) || empty($nro_operacion) || empty($carrito)) {
            echo json_encode(['success' => false, 'mensaje' => 'Faltan datos obligatorios o el carrito está vacío.']);
            exit;
        }

        $res = M_Ecommerce::singleton()->crearPedido($cliente, $nro_operacion, $total, $carrito);
        echo json_encode(['success' => $res['ok'], 'mensaje' => $res['mensaje'], 'id_pedido' => $res['id_pedido'] ?? null]);
        break;

    case 'listar_pedidos':
        if (!isset($_SESSION['id_usuario'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
            exit;
        }
        $pedidos = M_Ecommerce::singleton()->listarPedidos();
        echo json_encode(['success' => true, 'data' => $pedidos]);
        break;

    case 'detalles_pedido':
        if (!isset($_SESSION['id_usuario'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
            exit;
        }
        $id = intval($_GET['id']);
        $detalles = M_Ecommerce::singleton()->getDetallesPedido($id);
        echo json_encode(['success' => true, 'data' => $detalles]);
        break;

    case 'gestionar_pedido':
        if (!isset($_SESSION['id_usuario'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
            exit;
        }

        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);
        $id_pedido = intval($data['id_pedido']);
        $accion = $data['accion']; // 'aprobar' o 'rechazar'

        if ($accion === 'aprobar') {
            // Verificar caja abierta
            $modelCaja = M_Caja::singleton();
            if (!$modelCaja->obtenerCajaAbierta($_SESSION['id_usuario'])) {
                echo json_encode(["success" => false, "mensaje" => "Debes tener una caja abierta para aprobar pedidos y generar ventas."]);
                exit;
            }
        }

        $res = M_Ecommerce::singleton()->gestionarPedido($id_pedido, $accion, $_SESSION['id_usuario']);
        echo json_encode(['success' => $res['ok'], 'mensaje' => $res['mensaje']]);
        break;

    case 'get_pedido':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID inválido']);
            exit;
        }
        $pedido = M_Ecommerce::singleton()->getPedidoPublico($id);
        if ($pedido) {
            echo json_encode(['success' => true, 'data' => $pedido]);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Pedido no encontrado']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>
