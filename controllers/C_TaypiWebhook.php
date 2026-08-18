<?php
/**
 * controllers/C_TaypiWebhook.php
 * Webhook de TAYPI: notificación servidor a servidor que confirma el pago QR.
 *
 * Es la FUENTE DE VERDAD del cobro, no el onSuccess() del navegador: si el cliente
 * cierra la pestaña justo después de pagar, este endpoint es lo único que llega.
 * Por eso debe ser idempotente y no depender de sesión.
 *
 * Configuración: Panel TAYPI → Configuración → Webhooks → URL del endpoint.
 *  - Sandbox: https://nissi.likadev.com/controllers/C_TaypiWebhook.php
 *  - En producción debe ser HTTPS (TAYPI rechaza HTTP).
 *
 * Seguridad: el body CRUDO se valida con HMAC-SHA256(TAYPI_WEBHOOK_SECRET) contra el
 * header `Taypi-Signature`. Sin verificación, cualquiera podría simular pagos.
 *
 * Eventos: `payment.completed` (confirmar) y `payment.expired` (no hacer nada: la
 * reserva local ya caduca sola con fecha_expira).
 */
require_once dirname(__DIR__) . '/models/M_Ecommerce.php';
require_once dirname(__DIR__) . '/models/M_Taypi.php';

// Responder rápido: TAYPI reintenta si no recibe 2xx en pocos segundos.
header('Content-Type: text/plain; charset=utf-8');

$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_TAYPI_SIGNATURE'] ?? '';

$taypi = M_Taypi::singleton();

if (!$taypi->verificarWebhook($raw, $signature)) {
    // Firma inválida: la petición no viene de TAYPI. No se toca nada.
    http_response_code(401);
    error_log('TAYPI webhook: firma inválida.');
    echo 'Firma invalida';
    exit;
}

$evento = json_decode($raw, true);
if (!is_array($evento)) {
    http_response_code(400);
    echo 'Payload invalido';
    exit;
}

$tipo  = $evento['event'] ?? '';
$referencia = trim((string) ($evento['reference'] ?? ''));

if ($tipo === 'payment.completed') {
    if ($referencia === '') {
        http_response_code(400);
        echo 'KO - Missing reference';
        exit;
    }

    $modelo = M_Ecommerce::singleton();
    $pedido = $modelo->buscarPorReferencia($referencia);

    if (!$pedido) {
        // Pago de un pedido que no existe (ej. prueba hecha desde el panel).
        // Se responde 2xx para que TAYPI no reintente algo que nunca existirá.
        error_log('TAYPI webhook: no hay pedido para la referencia ' . $referencia);
        echo 'OK - Unknown order';
        exit;
    }

    // Idempotente y re-validando el monto contra la BD: el webhook llega con el
    // monto que nosotros creamos en crear_pago, así que confirmarPagoTaypi lo
    // compara para no confirmar un pago de importe distinto (posible manipulación).
    $modelo->confirmarPagoTaypi((int) $pedido['id_pedido'], (string) ($evento['payment_id'] ?? ''), (string) ($evento['amount'] ?? ''));
}

echo 'OK';
exit;