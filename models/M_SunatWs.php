<?php
require_once dirname(__DIR__) . '/config/sunat.php';

/**
 * Transporte hacia el webservice billService de SUNAT (SEE del Contribuyente).
 *
 * Sin ext-soap (no disponible en este entorno ni garantizada en el hosting
 * final): el sobre SOAP + cabecera WSSE se arma a mano y se envía con cURL,
 * mismo patrón que M_Izipay::llamar(). El WSDL de SUNAT expone 3 operaciones:
 *   - sendBill: envía un comprobante (factura/boleta), síncrono, CDR inmediato.
 *   - sendSummary: envía resumen diario o comunicación de baja, asíncrono,
 *     devuelve un ticket que hay que consultar después.
 *   - getStatus: consulta el resultado de un ticket de sendSummary.
 */
class M_SunatWs {
    private static $instancia = null;

    private function __construct() {}

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Envía un comprobante (factura/boleta/NC/ND) y devuelve el CDR de inmediato.
     *
     * @param string $fileName Sin extensión, formato SUNAT: {RUC}-{tipoDoc}-{serie}-{correlativo}
     * @param string $zipBase64 Contenido del .zip (que contiene el XML firmado) en base64
     * @return array ['ok'=>bool, 'cdr_base64'=>?string, 'codigo'=>?string, 'mensaje'=>?string]
     */
    public function sendBill(string $fileName, string $zipBase64): array {
        $body = "<ser:sendBill>"
              . "<fileName>{$fileName}.zip</fileName>"
              . "<contentFile>{$zipBase64}</contentFile>"
              . "</ser:sendBill>";

        $respuesta = $this->llamar($body, 'sendBill');
        if (!$respuesta['ok']) {
            return $respuesta;
        }

        $cdr = $this->extraerNodo($respuesta['xml'], 'applicationResponse');
        return ['ok' => true, 'cdr_base64' => $cdr, 'codigo' => null, 'mensaje' => null];
    }

    /**
     * Envía un resumen diario (RC, boletas) o comunicación de baja (RA, facturas).
     * Asíncrono: devuelve un ticket, el resultado se consulta con getStatus().
     *
     * @return array ['ok'=>bool, 'ticket'=>?string, 'codigo'=>?string, 'mensaje'=>?string]
     */
    public function sendSummary(string $fileName, string $zipBase64): array {
        $body = "<ser:sendSummary>"
              . "<fileName>{$fileName}.zip</fileName>"
              . "<contentFile>{$zipBase64}</contentFile>"
              . "</ser:sendSummary>";

        $respuesta = $this->llamar($body, 'sendSummary');
        if (!$respuesta['ok']) {
            return $respuesta;
        }

        $ticket = $this->extraerNodo($respuesta['xml'], 'ticket');
        return ['ok' => true, 'ticket' => $ticket, 'codigo' => null, 'mensaje' => null];
    }

    /**
     * Consulta el resultado de un ticket de sendSummary.
     *
     * @return array ['ok'=>bool, 'status_code'=>?string, 'cdr_base64'=>?string, 'codigo'=>?string, 'mensaje'=>?string]
     *   status_code: '0'=procesado (revisar cdr_base64), '98'=en proceso (reintentar
     *   más tarde), '99'=con errores.
     */
    public function getStatus(string $ticket): array {
        $body = "<ser:getStatus><ticket>{$ticket}</ticket></ser:getStatus>";

        $respuesta = $this->llamar($body, 'getStatus');
        if (!$respuesta['ok']) {
            return $respuesta;
        }

        $statusCode = $this->extraerNodo($respuesta['xml'], 'statusCode');
        $cdr = $this->extraerNodo($respuesta['xml'], 'content');
        return ['ok' => true, 'status_code' => $statusCode, 'cdr_base64' => $cdr, 'codigo' => null, 'mensaje' => null];
    }

    /**
     * Arma el sobre SOAP con cabecera WSSE UsernameToken y lo envía por cURL.
     * Usuario = {RUC}{usuarioSOL}, exactamente el formato que exige SUNAT.
     */
    private function llamar(string $bodyXml, string $operacion): array {
        $usuario = SUNAT_RUC . SUNAT_SOL_USUARIO;
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
            . 'xmlns:ser="http://service.sunat.gob.pe" '
            . 'xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">'
            . '<soapenv:Header>'
            . '<wsse:Security>'
            . '<wsse:UsernameToken>'
            . '<wsse:Username>' . htmlspecialchars($usuario, ENT_XML1) . '</wsse:Username>'
            . '<wsse:Password>' . htmlspecialchars(SUNAT_SOL_CLAVE, ENT_XML1) . '</wsse:Password>'
            . '</wsse:UsernameToken>'
            . '</wsse:Security>'
            . '</soapenv:Header>'
            . '<soapenv:Body>' . $bodyXml . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $ch = curl_init(SUNAT_WS_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $envelope,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml;charset=UTF-8',
                'SOAPAction: ""',
            ],
            CURLOPT_TIMEOUT        => 45, // SUNAT puede tardar, especialmente con archivos grandes
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'codigo' => '', 'mensaje' => "Error de conexión con SUNAT ({$operacion}): {$curlError}"];
        }

        // SUNAT puede devolver HTTP 500 con un <soapenv:Fault> — es una respuesta
        // de negocio válida (rechazo, credenciales inválidas, etc.), no un fallo
        // de red, así que se sigue parseando en vez de cortar aquí.
        $doc = new DOMDocument();
        $anteriorLibxml = libxml_use_internal_errors(true);
        $cargado = $doc->loadXML($response);
        libxml_use_internal_errors($anteriorLibxml);

        if (!$cargado) {
            return ['ok' => false, 'codigo' => (string) $httpStatus, 'mensaje' => "SUNAT devolvió una respuesta no XML ({$operacion}), HTTP {$httpStatus}."];
        }

        $faultCode = $this->extraerNodo($doc, 'faultcode');
        $faultString = $this->extraerNodo($doc, 'faultstring');
        if ($faultCode !== null) {
            // El código de negocio real suele venir en <detail><...><faultCode>SUNAT-nnnn</faultCode>
            $codigoDetalle = $this->extraerNodo($doc, 'faultCode') ?? $faultCode;
            $mensajeDetalle = $this->extraerNodo($doc, 'faultMessage') ?? $faultString;
            return ['ok' => false, 'codigo' => $codigoDetalle, 'mensaje' => $mensajeDetalle ?: 'SUNAT rechazó la solicitud.'];
        }

        return ['ok' => true, 'xml' => $doc];
    }

    /**
     * Busca un elemento por su nombre local, sin importar el prefijo de
     * namespace que use la respuesta (SUNAT no siempre es consistente).
     */
    private function extraerNodo(DOMDocument $doc, string $nombreLocal): ?string {
        $nodos = $doc->getElementsByTagName($nombreLocal);
        if ($nodos->length === 0) {
            // getElementsByTagName no resuelve namespaces con prefijo; se
            // intenta también con comodín de namespace.
            $nodos = $doc->getElementsByTagNameNS('*', $nombreLocal);
        }
        return $nodos->length > 0 ? trim($nodos->item(0)->textContent) : null;
    }
}
?>
