<?php
require_once dirname(__DIR__) . '/config/stripe.php';

/**
 * Wrapper Singleton sobre la API REST de Stripe vía cURL puro.
 * Sin SDK, para mantener la compatibilidad con hosting compartido sin vendor/autoload.
 */
class M_Stripe {
    private static $instancia = null;

    private function __construct() {}

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    public function crearPaymentIntent(array $params, string $claveIdempotencia = ''): array {
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($claveIdempotencia !== '') {
            $headers[] = 'Idempotency-Key: ' . $claveIdempotencia;
        }
        return $this->llamar('POST', 'payment_intents', $params, $headers);
    }

    public function obtenerPaymentIntent(string $id): array {
        return $this->llamar('GET', 'payment_intents/' . rawurlencode($id));
    }

    public function actualizarPaymentIntent(string $id, array $params): array {
        return $this->llamar('POST', 'payment_intents/' . rawurlencode($id), $params);
    }

    /** Verifica la firma `Stripe-Signature` de un webhook (HMAC-SHA256, con tolerancia anti-replay). */
    public function verificarFirmaWebhook(string $payload, string $cabecera, string $secreto, int $tolerancia = 300): bool {
        $t = null;
        $firmas = [];
        foreach (explode(',', $cabecera) as $parte) {
            $kv = explode('=', trim($parte), 2);
            if (count($kv) !== 2) continue;
            if ($kv[0] === 't') $t = (int) $kv[1];
            if ($kv[0] === 'v1') $firmas[] = $kv[1];
        }
        if ($t === null || empty($firmas) || $secreto === '') return false;
        if (abs(time() - $t) > $tolerancia) return false;

        $esperada = hash_hmac('sha256', $t . '.' . $payload, $secreto);
        foreach ($firmas as $f) {
            if (hash_equals($esperada, $f)) return true;
        }
        return false;
    }

    private function llamar(string $metodo, string $ruta, array $params = [], array $headersExtra = []): array {
        $url = 'https://api.stripe.com/v1/' . $ruta;

        $opciones = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => STRIPE_SK . ':',
            CURLOPT_HTTPHEADER     => $headersExtra ?: ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($metodo === 'POST') {
            $opciones[CURLOPT_POST] = true;
            $opciones[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $opciones);
        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => $curlError];
        }

        $data = json_decode($response, true) ?: [];
        if ($httpStatus < 200 || $httpStatus >= 300) {
            return ['ok' => false, 'status' => $httpStatus, 'data' => $data, 'error' => $data['error']['message'] ?? 'Error de Stripe.'];
        }

        return ['ok' => true, 'status' => $httpStatus, 'data' => $data, 'error' => ''];
    }
}
