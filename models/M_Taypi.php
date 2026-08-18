<?php
require_once dirname(__DIR__) . '/config/taypi.php';

/**
 * Wrapper Singleton sobre la API REST de TAYPI (pagos QR Yape/Plin/BIM) vía cURL puro.
 * Sin SDK: hosting compartido sin vendor/autoload (mismo criterio que M_Izipay).
 *
 * Flujo checkout.js:
 *   1. El backend crea un pago con crearPago()  ← este modelo.
 *   2. El navegador abre el modal QR con checkout_token + TAYPI_PUBLIC_KEY.
 *   3. El cliente escanea con Yape/Plin; TAYPI notifica vía webhook
 *      `payment.completed` → verificarWebhook() (la única fuente de verdad del cobro).
 *      El callback onSuccess() del navegador NO basta para confirmar un pedido.
 *
 * Seguridad (ver https://docs.taypi.pe/seguridad.html):
 *   - Cada request a la API lleva firma HMAC-SHA256 sobre
 *     "{timestamp}\n{METHOD}\n{path}\n{body}" con la Secret Key.
 *   - El webhook se firma con el Webhook Secret sobre el body crudo.
 *   - Los POST requieren Idempotency-Key (UUID único por operación).
 */
class M_Taypi {
    private static $instancia = null;

    private function __construct() {}

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /** ¿Están las credenciales cargadas Y la pasarela habilitada en el panel? */
    public function estaConfigurado(): bool {
        return TAYPI_HABILITADO !== 'NO'
            && TAYPI_PUBLIC_KEY !== ''
            && TAYPI_SECRET_KEY !== '';
    }

    /**
     * Crea un pago y devuelve el checkout_token que el navegador necesita para abrir
     * el modal QR de checkout.js. El monto se envía como cadena con 2 decimales
     * ("50.00"), tal como lo espera la API de TAYPI (no céntimos como Izipay).
     *
     * @param float  $monto   Monto en soles, tal como se muestra al cliente.
     * @param string $referencia Referencia del comercio (p.ej. PN-12). Viaja en el
     *                        webhook para identificar el pedido del lado nuestro.
     * @param string $descripcion
     * @param array  $metadata  Datos opcionales que se devuelven en el webhook.
     * @return array{ok:bool, checkout_token:string, payment_id:string, error:string}
     */
    public function crearPago(
        float $monto,
        string $referencia,
        string $descripcion = '',
        array $metadata = []
    ): array {
        $payload = [
            'amount'      => number_format($monto, 2, '.', ''),
            'currency'    => 'PEN',
            'reference'   => $referencia,
        ];
        if ($descripcion !== '') {
            $payload['description'] = $descripcion;
        }
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $r = $this->llamar('POST', '/api/v1/payments', $payload);
        if (!$r['ok']) {
            return ['ok' => false, 'checkout_token' => '', 'payment_id' => '', 'error' => $r['error'], 'data' => $r['data']];
        }

        $respuesta = $r['data']['data'] ?? $r['data'];
        $checkoutToken = $respuesta['checkout_token'] ?? '';
        $paymentId     = $respuesta['payment_id'] ?? '';
        if ($checkoutToken === '') {
            return ['ok' => false, 'checkout_token' => '', 'payment_id' => $paymentId, 'error' => 'TAYPI no devolvió un checkout_token.', 'data' => $r['data']];
        }

        return ['ok' => true, 'checkout_token' => $checkoutToken, 'payment_id' => $paymentId, 'error' => '', 'data' => $r['data']];
    }

    /**
     * Consulta el estado actual de un pago. Útil como red de seguridad cuando el
     * webhook no llega (mismo papel que consultarOrden() en Izipay).
     *
     * @return array{ok:bool, status:string, pagado:bool, error:string}
     */
    public function verificarPago(string $paymentId): array {
        $r = $this->llamar('GET', '/api/v1/payments/' . rawurlencode($paymentId), null);
        if (!$r['ok']) {
            return ['ok' => false, 'status' => '', 'pagado' => false, 'error' => $r['error'], 'data' => $r['data']];
        }
        $respuesta = $r['data']['data'] ?? $r['data'];
        $status = (string) ($respuesta['status'] ?? '');
        return ['ok' => true, 'status' => $status, 'pagado' => $status === 'completed', 'error' => '', 'data' => $r['data']];
    }

    /**
     * Valida la firma HMAC-SHA256 de un webhook entrante.
     * TAYPI envía el header `Taypi-Signature` como "sha256=<hex>" y firma el body
     * CRUDO (sin parsear) con el Webhook Secret. Comparación timing-safe.
     */
    public function verificarWebhook(string $rawBody, string $signatureHeader): bool {
        if ($rawBody === '' || $signatureHeader === '' || TAYPI_WEBHOOK_SECRET === '') {
            return false;
        }
        $received = str_replace('sha256=', '', $signatureHeader);
        $expected = hash_hmac('sha256', $rawBody, TAYPI_WEBHOOK_SECRET);
        return hash_equals($expected, $received);
    }

    /**
     * Núcleo de las llamadas a la API. Construye la firma HMAC-SHA256 sobre el
     * string "{timestamp}\n{METHOD}\n{path}\n{body}" (doc oficial) y envía los
     * headers requeridos (Authorization Bearer con la PUBLIC key, Taypi-Signature,
     * Taypi-Timestamp e Idempotency-Key en los POST).
     */
    private function llamar(string $method, string $path, ?array $payload): array {
        $body = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        $timestamp = (string) time();
        $signatureString = implode("\n", [$timestamp, strtoupper($method), $path, $body]);
        $signature = hash_hmac('sha256', $signatureString, TAYPI_SECRET_KEY);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . TAYPI_PUBLIC_KEY,
            'Taypi-Signature: ' . $signature,
            'Taypi-Timestamp: ' . $timestamp,
        ];
        if (strtoupper($method) === 'POST') {
            $headers[] = 'Idempotency-Key: ' . bin2hex(random_bytes(16));
        }

        $ch = curl_init(TAYPI_API_HOST . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST   => strtoupper($method),
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_TIMEOUT         => 20,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => $curlError];
        }

        $data = json_decode($response, true) ?: [];

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $detalle = $data['message'] ?? $data['error'] ?? 'Error de TAYPI.';
            $codigo  = $data['code'] ?? '';
            return [
                'ok'     => false,
                'status' => $httpStatus,
                'data'   => $data,
                'error'  => trim($detalle . ($codigo !== '' ? " ($codigo)" : '')),
            ];
        }

        return ['ok' => true, 'status' => $httpStatus, 'data' => $data, 'error' => ''];
    }
}