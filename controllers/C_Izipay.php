<?php
/**
 * controllers/C_Izipay.php
 * Genera el FormToken que el navegador necesita para pintar el formulario de pago
 * de Izipay (Krypton) sobre un pedido ya creado y en estado "pendiente de pago".
 *
 * Se llama DESPUÉS de crear_pedido: Krypton exige el token antes de renderizar, y
 * el orderId va dentro de ese token, así que el pedido tiene que existir primero.
 *
 * Autorización: el `token_publico` del pedido, igual que get_pedido. No basta con
 * la sesión de cliente — así un cliente autenticado tampoco puede pedir el token
 * de un pedido ajeno enumerando ids.
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__) . '/config/sesion_segura.php';
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/models/M_Ecommerce.php';
require_once dirname(__DIR__) . '/models/M_Izipay.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'form_token':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $id_pedido = intval($data['id_pedido'] ?? 0);
        $token     = trim((string) ($data['token'] ?? ''));

        if ($id_pedido <= 0 || $token === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Solicitud inválida.']);
            exit;
        }

        // Valida el token_publico con hash_equals y devuelve null si no coincide.
        $pedido = M_Ecommerce::singleton()->getPedidoPublico($id_pedido, $token);
        if (!$pedido) {
            echo json_encode(['success' => false, 'mensaje' => 'Pedido no encontrado.']);
            exit;
        }

        // Solo se cobra lo que está pendiente de pago y dentro de su ventana de reserva.
        // La comprobación vive en el modelo y se resuelve en SQL: comparar fechas de BD
        // contra el reloj de PHP falla si ambos están en zonas horarias distintas.
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

        $izipay = M_Izipay::singleton();
        if (!$izipay->estaConfigurado()) {
            echo json_encode(['success' => false, 'mensaje' => 'La pasarela de pago no está configurada.']);
            exit;
        }

        // El monto SIEMPRE sale de la BD, nunca del navegador.
        $r = $izipay->crearFormToken(
            (float) $pedido['total'],
            M_Ecommerce::referenciaPago($id_pedido),
            [
                'email'     => $_SESSION['cliente_email'] ?? '',
                'nombres'   => $pedido['nombres_razon_social'] ?? '',
                'apellidos' => $pedido['apellidos'] ?? '',
            ]
        );

        if (!$r['ok']) {
            // El detalle del error de la pasarela no se expone al cliente.
            error_log('Izipay form_token pedido ' . $id_pedido . ': ' . $r['error']);
            echo json_encode(['success' => false, 'mensaje' => 'No pudimos iniciar el pago. Intenta de nuevo en unos minutos.']);
            exit;
        }

        echo json_encode([
            'success'    => true,
            'formToken'  => $r['formToken'],
            'public_key' => IZIPAY_PUBLIC_KEY,
            'total'      => (float) $pedido['total'],
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
