<?php
require_once dirname(__DIR__) . '/config/izipay.php';

/**
 * Wrapper Singleton sobre la API REST V4 de Izipay vía cURL puro.
 * Sin SDK: hosting compartido sin vendor/autoload.
 *
 * Flujo del formulario embebido (Krypton):
 *   1. El backend pide un FormToken con crearFormToken()  ← este modelo.
 *   2. El navegador monta el formulario con ese token + IZIPAY_PUBLIC_KEY.
 *   3. Al pagar, el navegador hace POST a nuestra URL de retorno con kr-answer +
 *      kr-hash → validar con verificarFirmaNavegador().
 *   4. En paralelo Izipay llama a nuestra IPN → validar con verificarFirmaIPN().
 *      La IPN es la fuente de verdad: el paso 3 puede no ocurrir nunca si el
 *      cliente cierra el navegador.
 */
class M_Izipay {
    private static $instancia = null;

    private function __construct() {}

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /** ¿Están las credenciales cargadas? Permite degradar sin romper el checkout. */
    public function estaConfigurado(): bool {
        return IZIPAY_SHOP_ID !== '' && IZIPAY_PASSWORD !== '' && IZIPAY_PUBLIC_KEY !== '';
    }

    /**
     * Crea el FormToken que el navegador necesita para renderizar el formulario.
     * El monto se envía SIEMPRE en céntimos: se convierte aquí para que ningún
     * llamador tenga que acordarse (fuente clásica de cobros x100).
     *
     * @param float  $monto   Monto en soles, tal como se muestra al cliente.
     * @param string $orderId Identificador del pedido en nuestra BD.
     * @param array  $cliente ['email' => ..., 'nombres' => ..., 'apellidos' => ...]
     * @param string $moneda  Código ISO de la moneda.
     * @param string $autenticacionFuerte Preferencia de autenticación 3DS2, p.ej.
     *        'CHALLENGE_REQUESTED'. Vacío = se omite el campo y decide la config de
     *        la tienda. Ojo: es solo una *preferencia*; el banco emisor tiene la
     *        última palabra, por eso la respuesta trae `effectiveStrongAuthentication`
     *        con lo que realmente ocurrió.
     */
    public function crearFormToken(
        float $monto,
        string $orderId,
        array $cliente,
        string $moneda = 'PEN',
        string $autenticacionFuerte = ''
    ): array {
        $payload = [
            'amount'   => (int) round($monto * 100),
            'currency' => $moneda,
            'orderId'  => $orderId,
            'customer' => [
                'email'          => $cliente['email'] ?? '',
                'billingDetails' => [
                    'firstName' => $cliente['nombres']   ?? '',
                    'lastName'  => $cliente['apellidos'] ?? '',
                ],
            ],
        ];

        if ($autenticacionFuerte !== '') {
            $payload['strongAuthentication'] = $autenticacionFuerte;
        }

        $r = $this->llamar('Charge/CreatePayment', $payload);
        if (!$r['ok']) return $r;

        $formToken = $r['data']['answer']['formToken'] ?? '';
        if ($formToken === '') {
            return ['ok' => false, 'formToken' => '', 'data' => $r['data'], 'error' => 'Izipay no devolvió un formToken.'];
        }

        return ['ok' => true, 'formToken' => $formToken, 'data' => $r['data'], 'error' => ''];
    }

    /**
     * Consulta una orden ya creada. La usan el retorno del navegador, el IPN y el
     * barrido de pedidos vencidos.
     *
     * Devuelve TODAS las transacciones del pedido: Izipay acumula los reintentos
     * bajo el mismo orderId, así que un pedido pagado al tercer intento trae tres.
     */
    public function consultarOrden(string $orderId): array {
        return $this->llamar('Order/Get', ['orderId' => $orderId]);
    }

    /**
     * Única fuente de verdad de "¿este pedido está pagado?".
     *
     * No basta con mirar la primera transacción: la #1 puede estar rechazada y la
     * #3 pagada. Devuelve la transacción PAID si existe, o null.
     *
     * @return array{ok:bool, pagada:bool, transaccion:?array, error:string}
     */
    public function ordenEstaPagada(string $orderId): array {
        $r = $this->consultarOrden($orderId);

        if (!$r['ok']) {
            // PSP_010 = "transaction not found": la orden existe para nosotros pero
            // el cliente nunca llegó a intentar el pago, así que Izipay no tiene
            // ninguna transacción. Es una respuesta DEFINITIVA de "no pagada", no un
            // fallo. Tratarla como error dejaría el pedido prorrogándose para
            // siempre y el stock retenido sin que nadie lo libere.
            if (($r['codigo'] ?? '') === 'PSP_010') {
                return ['ok' => true, 'pagada' => false, 'transaccion' => null, 'error' => ''];
            }
            // Cualquier otro fallo (red, credenciales, caída de Izipay) sí es
            // indeterminado: quien llama debe reintentar, nunca dar el pago por perdido.
            return ['ok' => false, 'pagada' => false, 'transaccion' => null, 'error' => $r['error']];
        }

        foreach ($r['data']['answer']['transactions'] ?? [] as $t) {
            if (($t['status'] ?? '') === 'PAID') {
                return ['ok' => true, 'pagada' => true, 'transaccion' => $t, 'error' => ''];
            }
        }

        return ['ok' => true, 'pagada' => false, 'transaccion' => null, 'error' => ''];
    }

    /**
     * Valida la firma del retorno al NAVEGADOR (kr-answer + kr-hash).
     * Clave: IZIPAY_HMAC_SHA256.
     */
    public function verificarFirmaNavegador(string $krAnswer, string $krHash): bool {
        return $this->verificarFirma($krAnswer, $krHash, IZIPAY_HMAC_SHA256);
    }

    /**
     * Valida la firma de la IPN (servidor a servidor).
     * Clave: IZIPAY_PASSWORD — NO la HMAC. Esta asimetría es de Izipay, no un error.
     */
    public function verificarFirmaIPN(string $krAnswer, string $krHash): bool {
        return $this->verificarFirma($krAnswer, $krHash, IZIPAY_PASSWORD);
    }

    /**
     * Núcleo de la validación de firma. Izipay firma el JSON con las barras SIN
     * escapar, mientras que lo transmite escapado — hay que deshacer el escape
     * antes de calcular el HMAC o la firma nunca coincide.
     */
    private function verificarFirma(string $krAnswer, string $krHash, string $clave): bool {
        if ($krAnswer === '' || $krHash === '' || $clave === '') return false;
        $normalizado = str_replace('\\/', '/', $krAnswer);
        $calculado   = hash_hmac('sha256', $normalizado, $clave);
        return hash_equals($calculado, $krHash);
    }

    /** Extrae el resultado de un kr-answer ya validado. */
    public function parsearRespuesta(string $krAnswer): array {
        $answer = json_decode(str_replace('\\/', '/', $krAnswer), true) ?: [];
        $tx     = $answer['transactions'][0] ?? [];

        return [
            'orderStatus' => $answer['orderStatus'] ?? '',              // PAID | UNPAID | ...
            'orderId'     => $answer['orderDetails']['orderId'] ?? '',
            'uuid'        => $tx['uuid'] ?? '',
            'monto'       => isset($tx['amount']) ? $tx['amount'] / 100 : null,
            'errorCode'   => $tx['errorCode'] ?? '',
            'errorMsg'    => $tx['errorMessage'] ?? '',
            // Qué autenticación se aplicó realmente y si el riesgo de fraude se
            // trasladó al banco emisor. Con 3DS deshabilitado, liabilityShift = NO
            // y los contracargos por fraude los asume el comercio.
            'autenticacion'  => $tx['effectiveStrongAuthentication'] ?? '',
            'liabilityShift' => $tx['transactionDetails']['liabilityShift'] ?? '',
            'raw'         => $answer,
        ];
    }

    private function llamar(string $ruta, array $payload): array {
        $url = IZIPAY_API_HOST . '/api-payment/V4/' . $ruta;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode(IZIPAY_SHOP_ID . ':' . IZIPAY_PASSWORD),
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
            // Los ejemplos oficiales desactivan la verificación TLS; aquí se mantiene
            // activa a propósito.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => $curlError, 'codigo' => ''];
        }

        $data = json_decode($response, true) ?: [];

        // Izipay devuelve HTTP 200 incluso en errores de negocio: el veredicto real
        // está en el campo "status" del cuerpo.
        if ($httpStatus < 200 || $httpStatus >= 300 || ($data['status'] ?? '') === 'ERROR') {
            $detalle = $data['answer']['errorMessage']
                ?? $data['answer']['detailedErrorMessage']
                ?? 'Error de Izipay.';
            $codigo  = $data['answer']['errorCode'] ?? '';
            return [
                'ok'     => false,
                'status' => $httpStatus,
                'data'   => $data,
                'error'  => trim($detalle . ($codigo !== '' ? " ($codigo)" : '')),
                'codigo' => $codigo,
            ];
        }

        return ['ok' => true, 'status' => $httpStatus, 'data' => $data, 'error' => '', 'codigo' => ''];
    }
}
