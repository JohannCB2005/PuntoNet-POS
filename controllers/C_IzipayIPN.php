<?php
/**
 * controllers/C_IzipayIPN.php
 * Notificación instantánea de pago (IPN) de Izipay: llamada servidor a servidor que
 * comunica el resultado de la transacción.
 *
 * Es la FUENTE DE VERDAD del cobro, no el retorno del navegador: si el cliente cierra
 * la pestaña justo después de pagar, este endpoint es lo único que llega. Por eso debe
 * ser idempotente y no depender de sesión.
 *
 * Configuración: Back Office → Configuración → Reglas de notificaciones →
 * "URL de notificación al final del pago", apuntando a este archivo.
 *
 * OJO con la clave de firma: aquí se valida con IZIPAY_PASSWORD, mientras que el retorno
 * al navegador se valida con IZIPAY_HMAC_SHA256. Son claves distintas para el mismo
 * algoritmo; es una asimetría de Izipay, no un error.
 */

require_once dirname(__DIR__) . '/models/M_Ecommerce.php';
require_once dirname(__DIR__) . '/models/M_Izipay.php';

header('Content-Type: text/plain; charset=utf-8');

// Izipay envía los campos como cuerpo urlencoded.
$raw = file_get_contents('php://input');
$datos = [];
parse_str((string) $raw, $datos);

// Fallback por si el servidor ya pobló $_POST.
$krAnswer = $datos['kr-answer'] ?? ($_POST['kr-answer'] ?? '');
$krHash   = $datos['kr-hash']   ?? ($_POST['kr-hash']   ?? '');

if ($krAnswer === '' || $krHash === '') {
    http_response_code(400);
    echo 'KO - Missing payload';
    exit;
}

$izipay = M_Izipay::singleton();

if (!$izipay->verificarFirmaIPN($krAnswer, $krHash)) {
    // Firma inválida: la petición no viene de Izipay. No se toca nada.
    http_response_code(400);
    error_log('Izipay IPN: firma inválida.');
    echo 'KO - Invalid signature';
    exit;
}

$resultado = $izipay->parsearRespuesta($krAnswer);
$orderId   = $resultado['orderId'];

if ($orderId === '') {
    http_response_code(400);
    echo 'KO - Missing orderId';
    exit;
}

$modelo = M_Ecommerce::singleton();
$pedido = $modelo->buscarPorReferencia($orderId);

if (!$pedido) {
    // Puede ser una transacción de prueba hecha desde el Back Office, ajena a la tienda.
    // Se responde OK para que Izipay no reintente indefinidamente algo que nunca existirá.
    error_log('Izipay IPN: no hay pedido para la referencia ' . $orderId);
    echo 'OK - Unknown order';
    exit;
}

// confirmarPagoPedido reconsulta a Izipay por su cuenta y es idempotente, así que da
// igual si el navegador ya confirmó este mismo pedido hace un segundo.
if ($resultado['orderStatus'] === 'PAID') {
    $modelo->confirmarPagoPedido((int) $pedido['id_pedido'], $orderId);
}

// Izipay espera una confirmación en texto plano para dar por entregada la notificación.
echo 'OK! OrderStatus is ' . $resultado['orderStatus'];
