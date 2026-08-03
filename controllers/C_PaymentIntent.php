<?php
/**
 * create_payment_intent.php
 * Crea un PaymentIntent en Stripe usando cURL puro.
 * Sin dependencia de Stripe PHP SDK ni vendor/autoload.
 * Compatible con InfinityFree y cualquier hosting con PHP + cURL habilitado.
 */

header('Content-Type: application/json');

// ── Cargar configuración de Stripe ────────────────────────────────────────────
require_once dirname(__DIR__) . '/config/stripe.php';
$stripeSecretKey = STRIPE_SK;

if (empty($stripeSecretKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'Clave de Stripe no configurada en el servidor.']);
    exit;
}

// ── Leer body JSON ────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$amountSoles = floatval($body['amount'] ?? 0);
if ($amountSoles <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'El monto del pedido no es válido.']);
    exit;
}

// Stripe requiere importe en centavos (entero)
$amountCentavos = (int) round($amountSoles * 100);

$clienteDni     = htmlspecialchars(trim($body['cliente_dni']     ?? ''), ENT_QUOTES);
$clienteNombres = htmlspecialchars(trim($body['cliente_nombres'] ?? ''), ENT_QUOTES);

// ── Llamada a Stripe API con cURL ─────────────────────────────────────────────
$postFields = http_build_query([
    'amount'                             => $amountCentavos,
    'currency'                           => 'pen',           // Soles peruanos (S/)
    'automatic_payment_methods[enabled]' => 'true',
    'description'                        => 'Pedido PuntoNet - Click & Collect',
    'metadata[cliente_dni]'              => $clienteDni,
    'metadata[cliente_nombres]'          => $clienteNombres,
]);

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_USERPWD        => $stripeSecretKey . ':',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

// ── Error de red / cURL ───────────────────────────────────────────────────────
if ($response === false) {
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo conectar con el servidor de pagos. (' . $curlError . ')']);
    exit;
}

// ── Procesar respuesta de Stripe ──────────────────────────────────────────────
$stripeData = json_decode($response, true);

if ($httpStatus !== 200) {
    $errorMsg = $stripeData['error']['message'] ?? 'Error al crear la sesión de pago.';
    http_response_code($httpStatus);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

// ── Éxito: devolver client_secret ─────────────────────────────────────────────
echo json_encode(['client_secret' => $stripeData['client_secret']]);
