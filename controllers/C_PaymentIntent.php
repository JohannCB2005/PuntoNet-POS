<?php
/**
 * controllers/C_PaymentIntent.php
 * Crea y confirma PaymentIntents de Stripe. El monto SIEMPRE se calcula en el
 * servidor a partir de M_Ecommerce::calcularCarrito(): el cliente nunca envía
 * precios ni el total, solo {id_producto, cantidad}.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/stripe.php';
require_once dirname(__DIR__) . '/models/M_Stripe.php';
require_once dirname(__DIR__) . '/models/M_Ecommerce.php';

if (empty(STRIPE_SK) || STRIPE_SK === 'YOUR_STRIPE_SECRET_KEY') {
    http_response_code(500);
    echo json_encode(['error' => 'Clave de Stripe no configurada en el servidor.']);
    exit;
}

$action = $_GET['action'] ?? 'crear';
$body = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {

    // Calcula el total en servidor y crea el PaymentIntent. No toca stock ni crea pedido.
    case 'crear':
        $carrito = $body['carrito'] ?? [];
        $calc = M_Ecommerce::singleton()->calcularCarrito($carrito, false);

        if (!$calc['ok']) {
            http_response_code(400);
            echo json_encode(['error' => $calc['mensaje']]);
            exit;
        }

        $amountCentavos = (int) round($calc['total'] * 100);
        if ($amountCentavos <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'El monto del pedido no es válido.']);
            exit;
        }

        $claveIdempotencia = 'pi-' . session_id() . '-' . $calc['hash'];
        $res = M_Stripe::singleton()->crearPaymentIntent([
            'amount'                             => $amountCentavos,
            'currency'                           => 'pen',
            'automatic_payment_methods[enabled]' => 'true',
            'description'                        => 'Pedido PuntoNet - Click & Collect',
            'metadata[cart_hash]'                => $calc['hash'],
        ], $claveIdempotencia);

        if (!$res['ok']) {
            http_response_code($res['status'] ?: 502);
            echo json_encode(['error' => $res['error']]);
            exit;
        }

        echo json_encode([
            'client_secret'     => $res['data']['client_secret'],
            'payment_intent_id' => $res['data']['id'],
            'total'             => $calc['total'],
            'items'             => $calc['items'],
        ]);
        break;

    // Verifica contra Stripe que el pago realmente se completó y confirma el pedido.
    case 'confirmar':
        $id_pedido         = intval($body['id_pedido'] ?? 0);
        $payment_intent_id = trim($body['payment_intent_id'] ?? '');

        if ($id_pedido <= 0 || $payment_intent_id === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos.']);
            exit;
        }

        $pi = M_Stripe::singleton()->obtenerPaymentIntent($payment_intent_id);
        if (!$pi['ok'] || ($pi['data']['status'] ?? '') !== 'succeeded') {
            echo json_encode(['success' => false, 'mensaje' => 'El pago aún no se ha confirmado.']);
            exit;
        }

        $res = M_Ecommerce::singleton()->confirmarPagoPedido($id_pedido, $payment_intent_id);
        echo json_encode(['success' => $res['ok'], 'mensaje' => $res['mensaje'] ?? '']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida.']);
}
