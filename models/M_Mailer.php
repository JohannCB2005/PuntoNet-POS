<?php
require_once dirname(__DIR__) . '/config/brevo.php';
require_once dirname(__DIR__) . '/config/soporte.php';
require_once dirname(__DIR__) . '/config/marca.php';

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
        $html = $this->plantilla(
            'Verifica tu cuenta',
            '<p style="margin:0 0 18px;">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p style="margin:0 0 14px;">Usa este código para confirmar tu correo y empezar a comprar en la tienda:</p>
            ' . $this->codigoBox($codigo) . '
            <p style="margin:14px 0 0;color:#838aa3;font-size:13px;">Este código vence en 30 minutos. Si no creaste una cuenta en NISSI, ignora este correo.</p>'
        );
        return $this->enviar($email, $nombre, 'Verifica tu cuenta de NISSI', $html);
    }

    public function enviarCodigoReset(string $email, string $nombre, string $codigo): array {
        $html = $this->plantilla(
            'Recupera tu contraseña',
            '<p style="margin:0 0 18px;">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p style="margin:0 0 14px;">Recibimos una solicitud para restablecer tu contraseña. Tu código es:</p>
            ' . $this->codigoBox($codigo) . '
            <p style="margin:14px 0 0;color:#838aa3;font-size:13px;">Este código vence en 15 minutos. Si no lo solicitaste, ignora este correo: tu contraseña seguirá siendo la misma.</p>'
        );
        return $this->enviar($email, $nombre, 'Recupera tu contraseña de NISSI', $html);
    }

    public function enviarConfirmacionCompra(string $email, string $nombre, array $pedido): array {
        $codigoPedido = str_pad((string) $pedido['id_pedido'], 6, '0', STR_PAD_LEFT);

        $items = '';
        foreach ($pedido['detalles'] ?? [] as $d) {
            $items .= '<tr>
                <td style="padding:10px 12px;border-bottom:1px solid #ececf0;font-size:14px;color:#1d2136;">' . htmlspecialchars($d['nombre']) . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #ececf0;text-align:center;font-size:14px;color:#434a67;">' . htmlspecialchars((string) $d['cantidad']) . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #ececf0;text-align:right;font-size:14px;font-weight:700;color:#1d2136;">S/ ' . number_format((float) $d['subtotal'], 2) . '</td>
            </tr>';
        }

        $entregaTexto = ((int) ($pedido['tipo_entrega'] ?? 1)) === 2
            ? 'Se entregará al estudiante <strong>' . htmlspecialchars($pedido['estudiante_nombre'] ?? '') . '</strong> en el colegio.'
            : 'Podrás recogerlo en tienda presentando tu número de pedido'
                . ($pedido['recoge_nombre'] ?? '' ? ' (recoge <strong>' . htmlspecialchars($pedido['recoge_nombre']) . '</strong>' . ($pedido['recoge_dni'] ?? '' ? ', DNI ' . htmlspecialchars($pedido['recoge_dni']) : '') . ').' : '.');

        $html = $this->plantilla(
            'Pedido confirmado',
            '<p style="margin:0 0 18px;">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p style="margin:0 0 4px;">¡Gracias por tu compra! Confirmamos el pago de tu pedido</p>
            <p style="margin:0 0 22px;font-size:20px;font-weight:700;color:#23284E;">Nº ' . $codigoPedido . '</p>
            <table style="width:100%;border-collapse:collapse;border:1px solid #ececf0;border-radius:12px;overflow:hidden;margin:0 0 22px;">
                <thead>
                    <tr style="background:#23284E;">
                        <th style="text-align:left;padding:10px 12px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#fff;">Producto</th>
                        <th style="text-align:center;padding:10px 12px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#fff;">Cant.</th>
                        <th style="text-align:right;padding:10px 12px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#fff;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>' . $items . '</tbody>
            </table>
            <table style="width:100%;border-collapse:collapse;margin:0 0 22px;">
                <tr>
                    <td style="font-size:15px;color:#838aa3;">Total pagado</td>
                    <td style="text-align:right;font-size:20px;font-weight:700;color:#BD1721;">S/ ' . number_format((float) ($pedido['total'] ?? 0), 2) . '</td>
                </tr>
            </table>
            <div style="background:#f6f6f8;border-left:4px solid #BD1721;border-radius:10px;padding:14px 16px;font-size:14px;color:#434a67;">' . $entregaTexto . '</div>'
        );
        return $this->enviar($email, $nombre, 'Confirmación de tu pedido #' . $codigoPedido, $html);
    }

    public function enviarPedidoEntregado(string $email, string $nombre, array $pedido): array {
        $codigoPedido = str_pad((string) $pedido['id_pedido'], 6, '0', STR_PAD_LEFT);

        $entregaTexto = ((int) ($pedido['tipo_entrega'] ?? 1)) === 2
            ? 'fue entregado al estudiante <strong>' . htmlspecialchars($pedido['estudiante_nombre'] ?? '') . '</strong> en el colegio'
            : 'fue entregado en tienda';

        $html = $this->plantilla(
            'Pedido entregado',
            '<p style="margin:0 0 18px;">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p style="margin:0;">Te confirmamos que tu pedido <strong>#' . $codigoPedido . '</strong> ' . $entregaTexto . '.</p>
            <p style="margin:18px 0 0;">¡Gracias por comprar en <strong>NISSI</strong>!</p>'
        );
        return $this->enviar($email, $nombre, 'Tu pedido #' . $codigoPedido . ' fue entregado', $html);
    }

    public function enviarPedidoRechazado(string $email, string $nombre, array $pedido, string $motivo): array {
        $codigoPedido = str_pad((string) $pedido['id_pedido'], 6, '0', STR_PAD_LEFT);

        $contactoTexto = !empty(WHATSAPP_ATENCION)
            ? 'Para coordinar la devolución de tu dinero, escríbenos por WhatsApp al <strong>' . htmlspecialchars(WHATSAPP_ATENCION) . '</strong>.'
            : 'Para coordinar la devolución de tu dinero, comunícate con Atención al Cliente.';

        $html = $this->plantilla(
            'Pedido rechazado',
            '<p style="margin:0 0 18px;">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p style="margin:0 0 14px;">Lamentamos informarte que tu pedido <strong>#' . $codigoPedido . '</strong> no pudo procesarse.</p>
            <div style="background:#fbe9e7;border-left:4px solid #BD1721;border-radius:10px;padding:14px 16px;margin:0 0 16px;font-size:14px;color:#9e121b;">
                <strong>Motivo:</strong> ' . htmlspecialchars($motivo) . '
            </div>
            <p style="margin:0;">' . $contactoTexto . '</p>'
        );
        return $this->enviar($email, $nombre, 'Tu pedido #' . $codigoPedido . ' fue rechazado', $html);
    }

    /**
     * Cáscara HTML común a todos los correos (diseño NISSI).
     * Estilos inline: los clientes de correo ignoran <style>.
     */
    private function plantilla(string $titulo, string $contenido): string {
        $anio = date('Y');
        $nombre  = marcaVar('nombre');
        $slogan  = marcaVar('slogan');
        $footer  = marcaVar('emailFooter');
        $primario = marcaVar('primario');
        $acento   = marcaVar('acento');
        $logo     = marcaLogo('LOGO_CLARO', '');
        $logoImg  = $logo !== ''
            ? '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($nombre) . '" style="max-height:48px;max-width:200px;object-fit:contain;display:block;">'
            : '<span style="font-family:Georgia,\'Times New Roman\',serif;font-size:26px;font-weight:bold;letter-spacing:.02em;color:#ffffff;">' . htmlspecialchars($nombre) . '<span style="color:' . htmlspecialchars($acento) . ';">.</span></span>';
        return '
        <div style="background:#ffffff;padding:28px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;">
                <tr>
                    <td>
                        <!-- Cabecera -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . htmlspecialchars($primario) . ';border-radius:16px 16px 0 0;">
                            <tr>
                                <td style="padding:24px 28px;">
                                    ' . $logoImg . '
                                    <span style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:10px;letter-spacing:.3em;text-transform:uppercase;color:#838aa3;margin-top:3px;">' . htmlspecialchars($slogan) . '</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="height:5px;background:' . htmlspecialchars($acento) . ';"></td>
                            </tr>
                        </table>
                        <!-- Cuerpo -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #ececf0;border-top:none;border-radius:0 0 16px 16px;">
                            <tr>
                                <td style="padding:30px 28px;">
                                    <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:22px;font-weight:bold;color:#1d2136;line-height:1.25;margin-bottom:20px;">' . htmlspecialchars($titulo) . '</div>
                                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#434a67;">' . $contenido . '</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 28px 26px;">
                                    <div style="border-top:1px solid #ececf0;padding-top:18px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#838aa3;text-align:center;">
                                        ' . htmlspecialchars($footer) . ' © ' . $anio . '<br>
                                        Este correo fue enviado por ' . htmlspecialchars($nombre) . '. Si no esperabas este mensaje, puedes ignorarlo.
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>';
    }

    /** Caja destacada para códigos de verificación. */
    private function codigoBox(string $codigo): string {
        return '<div style="display:inline-block;background:#f6f6f8;border:1px solid #ececf0;border-radius:12px;padding:14px 22px;font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:bold;letter-spacing:8px;color:' . htmlspecialchars(marcaVar('primario')) . ';">' . htmlspecialchars($codigo) . '</div>';
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
