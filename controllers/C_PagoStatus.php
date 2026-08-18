<?php
/**
 * controllers/C_PagoStatus.php
 * Endpoint Server-Sent Events (SSE): notifica al navegador en tiempo real cuando
 * el pago de un pedido pasa de "verificando" a confirmado.
 *
 * Cómo funciona:
 *   - El cliente (V_checkout_success.php) abre un EventSource con id_pedido + token.
 *   - Este endpoint valida el token y mantiene la conexión abierta consultando el
 *     estado del pedido en la BD cada ~3 s.
 *   - En cuanto el estado deja de ser pendiente/verificando (3/4) — porque el
 *     webhook de TAYPI o la IPN de Izipay confirmaron el cobro — envía el pedido
 *     completo como evento `pago_confirmado` y cierra la conexión.
 *   - Si el webhook tarda más que el tiempo máximo, el frontend cae en polling
 *     como respaldo (ver V_checkout_success.php).
 *
 * Seguridad: requiere el token_publico del pedido (getPedidoPublico), igual que
 * get_pedido. Sin él, 403.
 */
if (session_status() === PHP_SESSION_NONE) { require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start(); }

$id_pedido = intval($_GET['id'] ?? 0);
$token     = trim((string) ($_GET['t'] ?? ''));

require_once dirname(__DIR__) . '/models/M_Ecommerce.php';

$modelo = M_Ecommerce::singleton();

// Valida token_publico (hash_equals) y además descarta pedidos que ya no existen.
$pedido = $modelo->getPedidoPublico($id_pedido, $token);
if (!$pedido) {
    http_response_code(403);
    exit('Solicitud inválida.');
}

// Sin buffers intermedios: cada flush() debe llegar al cliente de inmediato.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no'); // evita que nginx acumule el streaming
header('Connection: keep-alive');
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('implicit_flush', 'On');

if (ob_get_level()) { ob_end_clean(); }

set_time_limit(0);
ignore_user_abort(false);

// Máximo de espera: la reserva del pedido dura 10 min, pero no conviene tener un
// worker de PHP ocupado 10 min por cliente. Con 90 s el webhook (que llega en
// segundos) casi siempre gana; si no, el frontend continúa con polling.
$tiempoMaximo = 90;
$inicio = time();

// El estado de salida: comparar contra NOW() en SQL (nunca contra el reloj PHP).
$estadoAnterior = (int) $pedido['estado'];
$eventoEnviado  = false;

echo "retry: 4000\n\n";
flush();

while (time() - $inicio < $tiempoMaximo) {
    // Reconexión del cliente (EventSource vuelve solo tras cerrar el stream).
    if (connection_aborted()) { break; }

    $actual = $modelo->getPedidoPublico($id_pedido, $token);
    if (!$actual) {
        echo "event: pago_confirmado\ndata: {\"estado\":0}\n\n";
        flush();
        break;
    }

    $estadoActual = (int) $actual['estado'];

    // Cuando deja de estar pendiente/verificando (3/4) se notifica con el pedido
    // completo y se cierra. Se envía también al arrancar si ya estaba confirmado.
    if ($estadoActual !== 3 && $estadoActual !== 4) {
        echo "event: pago_confirmado\n";
        echo 'data: ' . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        $eventoEnviado = true;
        break;
    }

    // Mantiene la conexión viva aunque no haya cambios (heartbeat cada 15 s).
    if (($estadoActual === $estadoAnterior) && ((time() - $inicio) % 15 === 0)) {
        echo ": keepalive " . time() . "\n\n";
        flush();
    }

    $estadoAnterior = $estadoActual;
    sleep(3);
}

// Si el límite de tiempo se agotó sin confirmación, se cierra el stream: el
// frontend verá onerror y caerá al polling como respaldo.
if (!$eventoEnviado) {
    echo "event: timeout\ndata: {}\n\n";
    flush();
}
exit;