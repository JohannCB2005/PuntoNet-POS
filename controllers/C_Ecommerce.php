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

    // Requiere el token público del pedido: evita enumerar pedidos ajenos (IDOR).
    case 'get_pedido':
        $id = intval($_GET['id'] ?? 0);
        $token = trim($_GET['t'] ?? '');
        if ($id <= 0 || $token === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Solicitud inválida.']);
            exit;
        }
        $pedido = M_Ecommerce::singleton()->getPedidoPublico($id, $token);
        echo json_encode(['success' => (bool) $pedido, 'data' => $pedido]);
        break;

    // Crea el pedido en estado "pendiente de pago" y reserva stock. El precio y el total
    // se recalculan siempre en el servidor; el cliente solo envía {id_producto, cantidad}.
    case 'crear_pedido':
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);
        if (!$data) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos.']);
            exit;
        }

        $cliente = $data['cliente'] ?? [];
        $carrito = $data['carrito'] ?? [];
        $payment_intent_id = trim($data['payment_intent_id'] ?? '');

        if (empty($cliente['dni']) || empty($cliente['nombres']) || empty($carrito) || $payment_intent_id === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Faltan datos obligatorios o el carrito está vacío.']);
            exit;
        }

        // El PaymentIntent debe existir en nuestra cuenta y seguir en curso (nadie puede fabricar un id de Stripe).
        require_once dirname(__DIR__) . '/models/M_Stripe.php';
        $pi = M_Stripe::singleton()->obtenerPaymentIntent($payment_intent_id);
        $estadosValidos = ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'];
        if (!$pi['ok'] || !in_array($pi['data']['status'] ?? '', $estadosValidos, true)) {
            echo json_encode(['success' => false, 'mensaje' => 'El pago no es válido o ya fue procesado.']);
            exit;
        }

        $res = M_Ecommerce::singleton()->crearPedidoPendiente($cliente, $carrito, $payment_intent_id);
        echo json_encode([
            'success'   => $res['ok'],
            'mensaje'   => $res['mensaje'] ?? null,
            'id_pedido' => $res['id_pedido'] ?? null,
            'token'     => $res['token'] ?? null,
        ]);
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
        $id_pedido = intval($data['id_pedido'] ?? 0);
        $accion = $data['accion'] ?? ''; // 'aprobar' o 'rechazar'

        if ($id_pedido <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos.']);
            exit;
        }

        if ($accion === 'aprobar') {
            $modelCaja = M_Caja::singleton();
            if (!$modelCaja->obtenerCajaAbierta($_SESSION['id_usuario'])) {
                echo json_encode(["success" => false, "mensaje" => "Debes tener una caja abierta para aprobar pedidos y generar ventas."]);
                exit;
            }
        }

        $res = M_Ecommerce::singleton()->gestionarPedido($id_pedido, $accion, $_SESSION['id_usuario']);
        echo json_encode(['success' => $res['ok'], 'mensaje' => $res['mensaje']]);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
