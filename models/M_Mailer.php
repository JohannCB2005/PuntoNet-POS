<?php
require_once dirname(__DIR__) . '/config/brevo.php';
require_once dirname(__DIR__) . '/config/soporte.php';

/**
 * Wrapper Singleton sobre la API HTTP de Brevo (antes Sendinblue) vía cURL puro.
 * Sin SDK, mismo estilo que M_Izipay, para no depender de vendor/autoload.
 * Si no hay API key configurada, enviar() falla de forma controlada (no lanza
 * excepción) para que el registro/checkout nunca se rompa por falta de correo.
 */
class M_Mailer {
    private static $instancia = null;

    private function __construct() {}

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    public function enviarCodigoVerificacion(string $email, string $nombre, string $codigo): array {
        $html = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;">
                <h2 style="color:#1d4ed8;">PuntoNet</h2>
                <p>Hola ' . htmlspecialchars($nombre) . ',</p>
                <p>Tu código de verificación es:</p>
                <p style="font-size:28px;font-weight:bold;letter-spacing:4px;background:#f0f4ff;padding:12px 20px;border-radius:8px;display:inline-block;">' . htmlspecialchars($codigo) . '</p>
                <p>Este código vence en 30 minutos. Si no creaste una cuenta en PuntoNet, ignora este correo.</p>
            </div>
        ';
        return $this->enviar($email, $nombre, 'Verifica tu cuenta de PuntoNet', $html);
    }

    public function enviarCodigoReset(string $email, string $nombre, string $codigo): array {
        $html = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;">
                <h2 style="color:#1d4ed8;">PuntoNet</h2>
                <p>Hola ' . htmlspecialchars($nombre) . ',</p>
                <p>Recibimos una solicitud para restablecer tu contraseña. Tu código es:</p>
                <p style="font-size:28px;font-weight:bold;letter-spacing:4px;background:#f0f4ff;padding:12px 20px;border-radius:8px;display:inline-block;">' . htmlspecialchars($codigo) . '</p>
                <p>Este código vence en 15 minutos. Si no solicitaste esto, ignora este correo — tu contraseña seguirá siendo la misma.</p>
            </div>
        ';
        return $this->enviar($email, $nombre, 'Recupera tu contraseña de PuntoNet', $html);
    }

    public function enviarConfirmacionCompra(string $email, string $nombre, array $pedido): array {
        $codigoPedido = str_pad((string) $pedido['id_pedido'], 6, '0', STR_PAD_LEFT);

        $items = '';
        foreach ($pedido['detalles'] ?? [] as $d) {
            $items .= '<tr>
                <td style="padding:6px 0;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($d['nombre']) . '</td>
                <td style="padding:6px 0;border-bottom:1px solid #e5e7eb;text-align:center;">' . htmlspecialchars((string) $d['cantidad']) . '</td>
                <td style="padding:6px 0;border-bottom:1px solid #e5e7eb;text-align:right;">S/ ' . number_format((float) $d['subtotal'], 2) . '</td>
            </tr>';
        }

        $entregaTexto = ((int) ($pedido['tipo_entrega'] ?? 1)) === 2
            ? 'Se entregará al estudiante <strong>' . htmlspecialchars($pedido['estudiante_nombre'] ?? '') . '</strong> en el colegio.'
            : 'Podrás recogerlo en tienda presentando tu número de pedido.';

        $html = '
            <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">
                <h2 style="color:#1d4ed8;">¡Gracias por tu compra en PuntoNet!</h2>
                <p>Hola ' . htmlspecialchars($nombre) . ', confirmamos el pago de tu pedido <strong>#' . $codigoPedido . '</strong>.</p>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                    <thead>
                        <tr>
                            <th style="text-align:left;border-bottom:2px solid #1d4ed8;padding:6px 0;">Producto</th>
                            <th style="text-align:center;border-bottom:2px solid #1d4ed8;padding:6px 0;">Cant.</th>
                            <th style="text-align:right;border-bottom:2px solid #1d4ed8;padding:6px 0;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>' . $items . '</tbody>
                </table>
                <p style="font-size:18px;font-weight:bold;">Total: S/ ' . number_format((float) ($pedido['total'] ?? 0), 2) . '</p>
                <p>' . $entregaTexto . '</p>
            </div>
        ';
        return $this->enviar($email, $nombre, 'Confirmación de tu pedido #' . $codigoPedido, $html);
    }

    public function enviarPedidoEntregado(string $email, string $nombre, array $pedido): array {
        $codigoPedido = str_pad((string) $pedido['id_pedido'], 6, '0', STR_PAD_LEFT);

        $entregaTexto = ((int) ($pedido['tipo_entrega'] ?? 1)) === 2
            ? 'fue entregado al estudiante <strong>' . htmlspecialchars($pedido['estudiante_nombre'] ?? '') . '</strong> en el colegio'
            : 'fue entregado en tienda';

        $html = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;">
                <h2 style="color:#1d4ed8;">¡Tu pedido fue entregado!</h2>
                <p>Hola ' . htmlspecialchars($nombre) . ',</p>
                <p>Te confirmamos que tu pedido <strong>#' . $codigoPedido . '</strong> ' . $entregaTexto . '.</p>
                <p>Gracias por comprar en PuntoNet.</p>
            </div>
        ';
        return $this->enviar($email, $nombre, 'Tu pedido #' . $codigoPedido . ' fue entregado', $html);
    }

    public function enviarPedidoRechazado(string $email, string $nombre, array $pedido, string $motivo): array {
        $codigoPedido = str_pad((string) $pedido['id_pedido'], 6, '0', STR_PAD_LEFT);

        $contactoTexto = !empty(WHATSAPP_ATENCION)
            ? 'Para coordinar la devolución de tu dinero, escríbenos por WhatsApp al <strong>' . htmlspecialchars(WHATSAPP_ATENCION) . '</strong>.'
            : 'Para coordinar la devolución de tu dinero, comunícate con Atención al Cliente.';

        $html = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;">
                <h2 style="color:#dc2626;">Tu pedido fue rechazado</h2>
                <p>Hola ' . htmlspecialchars($nombre) . ',</p>
                <p>Lamentamos informarte que tu pedido <strong>#' . $codigoPedido . '</strong> no pudo procesarse.</p>
                <p style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;"><strong>Motivo:</strong> ' . htmlspecialchars($motivo) . '</p>
                <p>' . $contactoTexto . '</p>
            </div>
        ';
        return $this->enviar($email, $nombre, 'Tu pedido #' . $codigoPedido . ' fue rechazado', $html);
    }

    private function enviar(string $destinatarioEmail, string $destinatarioNombre, string $asunto, string $htmlContent): array {
        if (empty(BREVO_API_KEY) || empty(BREVO_SENDER_EMAIL)) {
            return ['ok' => false, 'error' => 'Brevo no está configurado (falta API key o remitente).'];
        }

        $payload = json_encode([
            'sender'      => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
            'to'          => [['email' => $destinatarioEmail, 'name' => $destinatarioNombre]],
            'subject'     => $asunto,
            'htmlContent' => $htmlContent,
        ]);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . BREVO_API_KEY,
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => $curlError];
        }

        $data = json_decode($response, true) ?: [];
        if ($httpStatus < 200 || $httpStatus >= 300) {
            return ['ok' => false, 'error' => $data['message'] ?? 'Error al enviar el correo.'];
        }

        return ['ok' => true, 'error' => ''];
    }
}
