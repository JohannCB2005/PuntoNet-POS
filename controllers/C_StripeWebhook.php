<?php
/**
 * controllers/C_StripeWebhook.php
 * Recibe payment_intent.succeeded de Stripe. Verificación de firma obligatoria.
 * Es solo una red de seguridad secundaria: nunca crea pedidos ni toca stock
 * directamente, únicamente promueve (de forma idempotente, vía confirmarPagoPedido)
 * un pedido que ya existe en estado "pendiente de pago".
 *
 * Registrar esta URL en Stripe Dashboard → Developers → Webhooks,
 * suscrita a payment_intent.succeeded y payment_intent.payment_failed.
 */
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/stripe.php';
require_once dirname(__DIR__) . '/models/M_Stripe.php';
require_once dirname(__DIR__) . '/models/M_Ecommerce.php';

if (empty(STRIPE_WH_SECRET)) {
    http_response_code(503);
    echo json_encode(['error' => 'Webhook no configurado.']);
    exit;
}

$payload  = file_get_contents('php://input');
$cabecera = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!M_Stripe::singleton()->verificarFirmaWebhook($payload, $cabecera, STRIPE_WH_SECRET)) {
    http_response_code(400);
    echo json_encode(['error' => 'Firma inválida.']);
    exit;
}

$evento = json_decode($payload, true) ?: [];
$tipo = $evento['type'] ?? '';
$paymentIntentId = $evento['data']['object']['id'] ?? '';

if ($tipo === 'payment_intent.succeeded' && $paymentIntentId !== '') {
    // No confiamos ciegamente en el payload del evento: se re-consulta el estado real.
    $pi = M_Stripe::singleton()->obtenerPaymentIntent($paymentIntentId);
    if ($pi['ok'] && ($pi['data']['status'] ?? '') === 'succeeded') {
        $model = M_Ecommerce::singleton();
        $pedido = $model->buscarPorPaymentIntent($paymentIntentId);
        if ($pedido) {
            $model->confirmarPagoPedido((int) $pedido['id_pedido'], $paymentIntentId);
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
