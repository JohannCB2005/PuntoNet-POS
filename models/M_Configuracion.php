<?php
/**
 * models/M_Configuracion.php
 * Configuración de la tienda: acceso a la tabla `configuracion` (clave→valor).
 *
 * El panel "Configuración de la tienda" edita estos valores; los consumidores
 * (config/*.php vía config/settings.php) leen BD-primero con fallback al .env.
 *
 * IMPORTANTE: las claves de datos de empresa/SUNAT usan los MISMOS nombres que
 * leen config/*.php (SUNAT_RAZON_SOCIAL, SUNAT_RUC_EMISOR, ...) para que al
 * guardar desde el panel el cambio se refleje de inmediato en tickets/emisión.
 */
class M_Configuracion {
    private static $instancia = null;
    private $conexion;

    /**
     * Catálogo de claves administrables. tipo: texto|password|fecha|si_no|archivo.
     */
    public const CAMPOS = [
        // ── Datos de la Empresa ──
        'SUNAT_RUC_EMISOR'        => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'RUC'],
        'SUNAT_RAZON_SOCIAL'      => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Nombre (razón social)'],
        'SUNAT_NOMBRE_COMERCIAL'  => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Nombre comercial'],
        'TITULO_WEB'              => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Título (nombre web)'],
        'CERT_VENCIMIENTO'        => ['grupo' => 'empresa', 'tipo' => 'fecha', 'etiqueta' => 'Vencimiento de Certificado'],
        'CUENTA_DETRACCION'       => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'N° Cuenta de detracción'],
        'EMPRESA_MTC'             => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'MTC'],
        'LOGO_CLARO'              => ['grupo' => 'empresa', 'tipo' => 'archivo', 'etiqueta' => 'Logo (modo claro)'],
        'LOGO_OSCURO'             => ['grupo' => 'empresa', 'tipo' => 'archivo', 'etiqueta' => 'Logo (modo oscuro)'],
        'LOGO_FAVICON'            => ['grupo' => 'empresa', 'tipo' => 'archivo', 'etiqueta' => 'Favicon (ícono web)'],
        'LOGO_APP'                => ['grupo' => 'empresa', 'tipo' => 'archivo', 'etiqueta' => 'Logo APP'],
        'RUBRICA'                 => ['grupo' => 'empresa', 'tipo' => 'archivo', 'etiqueta' => 'Rúbrica (firma digital)'],
        'SUNAT_UBIGEO'            => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Ubigeo'],
        'SUNAT_DEPARTAMENTO'      => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Departamento'],
        'SUNAT_PROVINCIA'         => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Provincia'],
        'SUNAT_DISTRITO'          => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Distrito'],
        'SUNAT_DIRECCION'         => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Dirección'],
        'SUNAT_EMAIL'             => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Email'],
        'SUNAT_TELEFONO'          => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'Teléfono'],
        'SUNAT_CONSULTA_URL'      => ['grupo' => 'empresa', 'tipo' => 'texto', 'etiqueta' => 'URL consulta de comprobante'],

        // ── Entorno del sistema (SUNAT SOAP) ──
        'SUNAT_MODO'              => ['grupo' => 'sunat', 'tipo' => 'texto', 'etiqueta' => 'SOAP Tipo (BETA/PRODUCCION)'],
        'SOAP_ENVIO'              => ['grupo' => 'sunat', 'tipo' => 'texto', 'etiqueta' => 'SOAP Envío (Sunat/OSE)'],
        'SUNAT_SOL_USUARIO'       => ['grupo' => 'sunat', 'tipo' => 'texto', 'etiqueta' => 'Usuario Secundario Sunat/OSE'],
        'SUNAT_SOL_CLAVE'         => ['grupo' => 'sunat', 'tipo' => 'password', 'etiqueta' => 'SOAP Password'],
        'SUNAT_CERT_PATH'         => ['grupo' => 'sunat', 'tipo' => 'archivo', 'etiqueta' => 'Certificado (.pem)'],

        // ── Consulta integrada de CPE ──
        'CPE_CLIENT_ID'           => ['grupo' => 'cpe', 'tipo' => 'texto', 'etiqueta' => 'Client ID'],
        'CPE_CLIENT_SECRET'       => ['grupo' => 'cpe', 'tipo' => 'password', 'etiqueta' => 'Client Secret (Clave)'],

        // ── Guías electrónicas ──
        'GUIA_SOAP_USUARIO'       => ['grupo' => 'guias', 'tipo' => 'texto', 'etiqueta' => 'SOAP Usuario'],
        'GUIA_SOAP_PASSWORD'      => ['grupo' => 'guias', 'tipo' => 'password', 'etiqueta' => 'SOAP Password'],
        'GUIA_CLIENT_ID'          => ['grupo' => 'guias', 'tipo' => 'texto', 'etiqueta' => 'Client ID'],
        'GUIA_CLIENT_SECRET'      => ['grupo' => 'guias', 'tipo' => 'password', 'etiqueta' => 'Client Secret (Clave)'],

        // ── SIRE ──
        'SIRE_CLIENT_ID'          => ['grupo' => 'sire', 'tipo' => 'texto', 'etiqueta' => 'Client ID'],
        'SIRE_CLIENT_SECRET'      => ['grupo' => 'sire', 'tipo' => 'password', 'etiqueta' => 'Client Secret (Clave)'],
        'SIRE_USUARIO'            => ['grupo' => 'sire', 'tipo' => 'texto', 'etiqueta' => 'Usuario'],
        'SIRE_CONTRASENA'         => ['grupo' => 'sire', 'tipo' => 'password', 'etiqueta' => 'Contraseña'],

        // ── Envío de mensajes a través de QR Api ──
        'QRAPI_HABILITADO'        => ['grupo' => 'qrapi', 'tipo' => 'si_no', 'etiqueta' => 'Envío de mensajes por QR Api'],

        // ── Certificado Qz Tray ──
        'QZTRAY_DIGITAL_CERT'     => ['grupo' => 'qztray', 'tipo' => 'archivo', 'etiqueta' => 'Digital Certificate'],
        'QZTRAY_PRIVATE_KEY'      => ['grupo' => 'qztray', 'tipo' => 'archivo', 'etiqueta' => 'Private Key'],

        // ── Servicio PSE ──
        'PSE_HABILITADO'          => ['grupo' => 'pse', 'tipo' => 'si_no', 'etiqueta' => 'Servicio PSE'],

        // ── Cobro por verificación manual (sin pasarela, sin comisiones) ──
        'PAGO_MANUAL_HABILITADO'              => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'Cobro por verificación manual'],
        'PAGO_MANUAL_MINUTOS'                 => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Minutos de reserva para pago manual'],
        'RESERVA_PASARELA_MINUTOS'            => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Minutos de reserva para pasarela (tarjeta/QR)'],
        'PAGO_MANUAL_INSTRUCCIONES'           => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Instrucciones mostradas al cliente'],
        'PAGO_MANUAL_BILLETERA_HABILITADO'    => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'Billetera QR (Yape/Plin/Izipay QR)'],
        'PAGO_MANUAL_BILLETERA_YAPE_HABILITADO' => ['grupo' => 'pagos', 'tipo' => 'si_no', 'etiqueta' => 'Yape QR'],
        'PAGO_MANUAL_BILLETERA_PLIN_HABILITADO' => ['grupo' => 'pagos', 'tipo' => 'si_no', 'etiqueta' => 'Plin QR'],
        'PAGO_MANUAL_BILLETERA_IZIPAY_HABILITADO' => ['grupo' => 'pagos', 'tipo' => 'si_no', 'etiqueta' => 'Izipay QR'],
        'PAGO_MANUAL_BILLETERA_QR'            => ['grupo' => 'pagos', 'tipo' => 'archivo', 'etiqueta' => 'Imagen del QR de billetera'],
        'PAGO_MANUAL_BILLETERA_TITULAR'       => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Titular del QR de billetera'],
        'PAGO_MANUAL_QR_YAPE_CONTENIDO'       => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Contenido QR Yape (EMVCo)'],
        'PAGO_MANUAL_QR_PLIN_CONTENIDO'       => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Contenido QR Plin (EMVCo)'],
        'PAGO_MANUAL_TRANSFERENCIA_HABILITADO'=> ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'Transferencia bancaria'],
        'PAGO_MANUAL_BCP_HABILITADO'          => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'BCP habilitado'],
        'PAGO_MANUAL_BCP_TITULAR'             => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'BCP — Titular'],
        'PAGO_MANUAL_BCP_CUENTA'              => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'BCP — N° cuenta'],
        'PAGO_MANUAL_BCP_CCI'                 => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'BCP — CCI'],
        'PAGO_MANUAL_BBVA_HABILITADO'         => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'BBVA habilitado'],
        'PAGO_MANUAL_BBVA_TITULAR'            => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'BBVA — Titular'],
        'PAGO_MANUAL_BBVA_CUENTA'             => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'BBVA — N° cuenta'],
        'PAGO_MANUAL_BBVA_CCI'                => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'BBVA — CCI'],
        'PAGO_MANUAL_INTERBANK_HABILITADO'    => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'Interbank habilitado'],
        'PAGO_MANUAL_INTERBANK_TITULAR'       => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Interbank — Titular'],
        'PAGO_MANUAL_INTERBANK_CUENTA'        => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Interbank — N° cuenta'],
        'PAGO_MANUAL_INTERBANK_CCI'           => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Interbank — CCI'],
        'PAGO_MANUAL_SCOTIABANK_HABILITADO'   => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'Scotiabank habilitado'],
        'PAGO_MANUAL_SCOTIABANK_CUENTA'       => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Scotiabank — N° cuenta'],
        'PAGO_MANUAL_SCOTIABANK_CCI'          => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Scotiabank — CCI'],

        // ── Pasarelas de pago de la tienda online (TAYPI QR / Izipay tarjeta) ──
        'TAYPI_HABILITADO'        => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'TAYPI (Yape/Plin) habilitada'],
        'TAYPI_MODO'              => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'TAYPI modo (TEST/PRODUCCION)'],
        'TAYPI_PUBLIC_KEY'        => ['grupo' => 'pagos', 'tipo' => 'password','etiqueta' => 'TAYPI Public Key', 'secreto' => true],
        'TAYPI_SECRET_KEY'        => ['grupo' => 'pagos', 'tipo' => 'password','etiqueta' => 'TAYPI Secret Key', 'secreto' => true],
        'TAYPI_WEBHOOK_SECRET'    => ['grupo' => 'pagos', 'tipo' => 'password','etiqueta' => 'TAYPI Webhook Secret', 'secreto' => true],
        'IZIPAY_HABILITADO'       => ['grupo' => 'pagos', 'tipo' => 'si_no',  'etiqueta' => 'Izipay (Tarjeta) habilitada'],
        'IZIPAY_MODO'             => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Izipay modo (TEST/PRODUCCION)'],
        'IZIPAY_SHOP_ID'          => ['grupo' => 'pagos', 'tipo' => 'texto',  'etiqueta' => 'Izipay Shop ID'],
        'IZIPAY_PUBLIC_KEY'       => ['grupo' => 'pagos', 'tipo' => 'password','etiqueta' => 'Izipay Public Key', 'secreto' => true],
        'IZIPAY_PASSWORD'         => ['grupo' => 'pagos', 'tipo' => 'password','etiqueta' => 'Izipay Password (Backend)', 'secreto' => true],
        'IZIPAY_HMAC_SHA256'      => ['grupo' => 'pagos', 'tipo' => 'password','etiqueta' => 'Izipay HMAC SHA256', 'secreto' => true],

        // ── Confirmación de pedidos (tienda online) ──
        'CODIGO_CONFIRMACION_HABILITADO' => ['grupo' => 'pagos', 'tipo' => 'si_no', 'etiqueta' => 'Código de confirmación de pedido'],

        // ── Marca y apariencia (usa la tienda, el panel, correos y documentos) ──
        'MARCA_NOMBRE'            => ['grupo' => 'marca', 'tipo' => 'texto', 'etiqueta' => 'Nombre de la marca'],
        'MARCA_SLOGAN'            => ['grupo' => 'marca', 'tipo' => 'texto', 'etiqueta' => 'Slogan'],
        'COLOR_PRIMARIO'          => ['grupo' => 'marca', 'tipo' => 'texto', 'etiqueta' => 'Color primario'],
        'COLOR_ACENTO'            => ['grupo' => 'marca', 'tipo' => 'texto', 'etiqueta' => 'Color de acento'],
        'MARCA_EMAIL_FOOTER'      => ['grupo' => 'marca', 'tipo' => 'texto', 'etiqueta' => 'Frase pie de correo'],

        // ── Integraciones y servicios externos ──
        'BREVO_API_KEY'           => ['grupo' => 'integraciones', 'tipo' => 'password', 'etiqueta' => 'Brevo API Key', 'secreto' => true],
        'BREVO_SENDER_EMAIL'      => ['grupo' => 'integraciones', 'tipo' => 'texto', 'etiqueta' => 'Brevo remitente (email)'],
        'BREVO_SENDER_NAME'       => ['grupo' => 'integraciones', 'tipo' => 'texto', 'etiqueta' => 'Brevo remitente (nombre)'],
        'APIPERU_TOKEN'           => ['grupo' => 'integraciones', 'tipo' => 'password', 'etiqueta' => 'APIPerú Token (DNI/RUC)', 'secreto' => true],
        'WHATSAPP_ATENCION'       => ['grupo' => 'integraciones', 'tipo' => 'texto', 'etiqueta' => 'WhatsApp de atención'],
    ];

    private function __construct() {
        require_once dirname(__DIR__) . '/config/conexion.php';
        $this->conexion = Conexion::singleton()->getConexion();
    }

    public static function singleton(): self {
        if (!isset(self::$instancia)) {
            $miclase = __CLASS__;
            self::$instancia = new $miclase;
        }
        return self::$instancia;
    }

    /**
     * Devuelve todos los valores de la tabla configuracion como [clave => valor].
     */
    public function obtenerDesdeBD(): array {
        $stmt = $this->conexion->query('SELECT clave, valor FROM configuracion');
        $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $resultado[$fila['clave']] = $fila['valor'];
        }
        return $resultado;
    }

    /**
     * Devuelve el estado completo para el panel: cada clave del catálogo con su
     * valor efectivo (BD → .env → vacío), su grupo/tipo/etiqueta y si viene de BD.
     */
    public function obtenerTodas(): array {
        $enBD = $this->obtenerDesdeBD();
        $_env = file_exists(dirname(__DIR__) . '/.env')
            ? parse_ini_file(dirname(__DIR__) . '/.env') : [];

        $resultado = [];
        foreach (self::CAMPOS as $clave => $meta) {
            $valor = $enBD[$clave] ?? null;
            $desdeBD = $valor !== null;

            if ($valor === null && isset($_env[$clave])) {
                $valor = $_env[$clave];
            }

            $esSecreto = !empty($meta['secreto']);

            $resultado[$clave] = [
                'valor'      => $esSecreto ? '' : (string) $valor,   // secretos nunca viajan al navegador
                'tiene_valor'=> $esSecreto && $valor !== null && (string) $valor !== '',
                'grupo'      => $meta['grupo'],
                'tipo'       => $meta['tipo'],
                'etiqueta'   => $meta['etiqueta'],
                'desdeBD'    => $desdeBD,
            ];
        }
        return $resultado;
    }

    /**
     * Guarda un conjunto de claves→valor (upsert). Solo se guardan claves que
     * existan en el catálogo, para no permitir inyectar claves arbitrarias.
     *
     * @param array $campos [clave => valor] (texto; archivos van por subirArchivo)
     */
    public function guardarCampos(array $campos): array {
        $guardadas = 0;
        $sql = "INSERT INTO configuracion (clave, valor, grupo, tipo, etiqueta)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), grupo = VALUES(grupo),
                        tipo = VALUES(tipo), etiqueta = VALUES(etiqueta)";

        foreach ($campos as $clave => $valor) {
            if (!isset(self::CAMPOS[$clave])) {
                continue; // claves desconocidas: ignorar (nunca inyectar)
            }
            $meta = self::CAMPOS[$clave];
            $valor = is_scalar($valor) ? (string) $valor : '';

            // Los secretos nunca se exponen (obtenerTodas devuelve ''). Por eso, si
            // el formulario llega con el campo en blanco, se conserva el valor actual:
            // solo se sobrescribe cuando el administrador escribe una clave nueva.
            if (!empty($meta['secreto']) && $valor === '') {
                continue;
            }

            // Secretos: se cifran antes de persistir (nunca texto plano en BD).
            if (!empty($meta['secreto'])) {
                require_once dirname(__DIR__) . '/config/cripto.php';
                $valor = cifrarSecreto($valor);
            }

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$clave, $valor, $meta['grupo'], $meta['tipo'], $meta['etiqueta']]);
            $guardadas++;
        }
        return ['ok' => true, 'guardadas' => $guardadas];
    }

    /**
     * Guarda un archivo de configuración (logo/favicon/rúbrica/certificado).
     *
     * - Imágenes (logos, favicon, rúbrica) → assets/tienda/ (accesibles por web).
     * - Certificados y claves (.pem/.crt/.key) → config/certs/ (fuera de lo servido).
     *
     * @param string $clave Clave del catálogo (define el prefijo del archivo).
     * @param array  $file  Entrada de $_FILES[$clave].
     * @return array ['ok'=>bool, 'mensaje'=>string, 'ruta'=>?string]
     */
    public function subirArchivo(string $clave, array $file): array {
        if (!isset(self::CAMPOS[$clave])) {
            return ['ok' => false, 'mensaje' => 'Clave de archivo inválida.'];
        }
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'mensaje' => 'No se recibió el archivo.'];
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['ok' => false, 'mensaje' => 'El archivo supera el tamaño máximo (10 MB).'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $esImagen = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
        $esCertificado = in_array($extension, ['pem', 'crt', 'cer', 'key'], true);

        if ($esImagen) {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false) {
                return ['ok' => false, 'mensaje' => 'El archivo no es una imagen válida.'];
            }
            $dir = dirname(__DIR__) . '/assets/tienda/';
        } elseif ($esCertificado) {
            $dir = dirname(__DIR__) . '/config/certs/';
        } else {
            return ['ok' => false, 'mensaje' => 'Formato no permitido. Usa imagen (jpg/png/webp/svg) o certificado (pem/crt/key).'];
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $nombre = strtolower($clave) . '_' . time() . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
            return ['ok' => false, 'mensaje' => 'No se pudo guardar el archivo en el servidor.'];
        }

        // Ruta a persistir: absoluta para certificados (los consumidores como
        // M_Sunat necesitan la ruta real en disco), relativa para imágenes (se
        // sirven por web con src="assets/tienda/...").
        $ruta = $esCertificado ? ($dir . $nombre) : str_replace(dirname(__DIR__) . '/', '', $dir . $nombre);

        $meta = self::CAMPOS[$clave];
        $sql = "INSERT INTO configuracion (clave, valor, grupo, tipo, etiqueta)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo)";
        $this->conexion->prepare($sql)->execute([$clave, $ruta, $meta['grupo'], 'archivo', $meta['etiqueta']]);

        return ['ok' => true, 'mensaje' => 'Archivo guardado.', 'ruta' => $ruta];
    }
}