<?php
/**
 * controllers/C_Taypi.php
 * Crea un pago QR (Yape/Plin) en TAYPI para un pedido ya creado y en estado
 * "pendiente de pago", devolviendo el checkout_token que checkout.js necesita para
 * abrir el modal. Se llama DESPUÉS de crear_pedido (mismo patrón que C_Izipay.php).
 *
 * Autorización: el `token_publico` del pedido, igual que get_pedido. No basta con
 * la sesión de cliente — así un cliente autenticado tampoco puede crear el pago de
 * un pedido ajeno enumerando ids.
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__) . '/config/sesion_segura.php';
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/models/M_Ecommerce.php';
require_once dirname(__DIR__) . '/models/M_Taypi.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'crear_pago':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $id_pedido = intval($data['id_pedido'] ?? 0);
        $token     = trim((string) ($data['token'] ?? ''));

        if ($id_pedido <= 0 || $token === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Solicitud inválida.']);
            exit;
        }

        $pedido = M_Ecommerce::singleton()->getPedidoPublico($id_pedido, $token);
        if (!$pedido) {
            echo json_encode(['success' => false, 'mensaje' => 'Pedido no encontrado.']);
            exit;
        }

        switch (M_Ecommerce::singleton()->estadoCobrabilidad($id_pedido)) {
            case 'ok':
                break;
            case 'expirado':
                echo json_encode(['success' => false, 'mensaje' => 'La reserva de tu pedido expiró. Vuelve a armar tu carrito.']);
                exit;
            default:
                echo json_encode(['success' => false, 'mensaje' => 'Este pedido ya no está pendiente de pago.']);
                exit;
        }

        $taypi = M_Taypi::singleton();
        if (!$taypi->estaConfigurado()) {
            echo json_encode(['success' => false, 'mensaje' => 'La pasarela de pago QR no está configurada.']);
            exit;
        }

        // El monto SIEMPRE sale de la BD, nunca del navegador.
        $r = $taypi->crearPago(
            (float) $pedido['total'],
            M_Ecommerce::referenciaPago($id_pedido),
            'Pedido NISSI #' . $id_pedido,
            ['id_pedido' => (string) $id_pedido]
        );

        if (!$r['ok']) {
            error_log('TAYPI crear_pago pedido ' . $id_pedido . ': ' . $r['error']);
            echo json_encode(['success' => false, 'mensaje' => 'No pudimos iniciar el pago. Intenta de nuevo en unos minutos.']);
            exit;
        }

        // Guardar el payment_id de TAYPI: sirve para verificar_pago (confirmación
        // inmediata sin esperar el webhook, que puede tardar decenas de segundos).
        if ($r['payment_id'] !== '') {
            M_Ecommerce::singleton()->guardarPaymentIdTaypi($id_pedido, $r['payment_id']);
        }

        echo json_encode([
            'success'         => true,
            'checkout_token'  => $r['checkout_token'],
            'public_key'      => TAYPI_PUBLIC_KEY,
            'checkout_js_url' => TAYPI_CHECKOUT_JS_URL,
            'total'           => (float) $pedido['total'],
        ]);
        break;

    // Verifica contra TAYPI el estado de un pago pendiente (estado 3/4) y, si ya
    // está pagado, confirma el pedido al instante SIN esperar el webhook (que puede
    // tardar ~50 s). El webhook sigue siendo el respaldo/autoridad final.
    case 'verificar_pago':
        $id_pedido = intval($_GET['id'] ?? 0);
        $token     = trim((string) ($_GET['t'] ?? ''));

        if ($id_pedido <= 0 || $token === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Solicitud inválida.']);
            exit;
        }

        $modelo  = M_Ecommerce::singleton();
        $pedido  = $modelo->getPedidoPublico($id_pedido, $token);
        if (!$pedido) {
            echo json_encode(['success' => false, 'mensaje' => 'Pedido no encontrado.']);
            exit;
        }

        $estado = (int) $pedido['estado'];
        // Ya confirmado (1/2/5) o rechazado (0): no hay nada que verificar.
        if ($estado !== 3 && $estado !== 4) {
            echo json_encode(['success' => true, 'estado' => $estado, 'ya_pagado' => $estado !== 0, 'pedido' => $pedido]);
            exit;
        }

        $paymentId = (string) ($pedido['payment_id_taypi'] ?? '');
        if ($paymentId === '') {
            echo json_encode(['success' => true, 'estado' => $estado, 'ya_pagado' => false, 'pedido' => $pedido]);
            exit;
        }

        $taypi = M_Taypi::singleton();
        $v = $taypi->verificarPago($paymentId);
        if (!$v['ok']) {
            // Error de red/API de TAYPI: no podemos afirmar nada, se deja pendiente
            // para que el webhook (o el siguiente reintento) resuelva.
            error_log('TAYPI verificar_pago pedido ' . $id_pedido . ': ' . $v['error']);
            echo json_encode(['success' => true, 'estado' => $estado, 'ya_pagado' => false, 'pedido' => $pedido]);
            exit;
        }

        $montoTaypi = (float) ($v['data']['amount'] ?? 0);
        $conf = $modelo->confirmarPagoTaypi($id_pedido, $paymentId, (string) $montoTaypi);

        // Recargar el pedido con el estado fresco tras confirmar.
        $pedido = $modelo->getPedidoPublico($id_pedido, $token);
        $nuevoEstado = (int) ($pedido['estado'] ?? $estado);

        echo json_encode([
            'success'  => true,
            'estado'   => $nuevoEstado,
            'ya_pagado' => $nuevoEstado !== 0 && $nuevoEstado !== 3 && $nuevoEstado !== 4,
            'confirmado' => ($conf['ok'] ?? false) && !($conf['ya_confirmado'] ?? false),
            'pedido'   => $pedido,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida.']);
        break;
}