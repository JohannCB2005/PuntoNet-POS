<?php
/**
 * config/sunat.php
 * Credenciales y datos del emisor para la emisión/anulación de comprobantes
 * electrónicos ante SUNAT (SEE del Contribuyente).
 *
 * Todo sale de SUNAT Operaciones en Línea (Clave SOL) → Empresa:
 *   - SUNAT_SOL_USUARIO / SUNAT_SOL_CLAVE → Usuarios Secundarios (crear uno
 *     restringido solo a comprobantes electrónicos, NUNCA usar la clave SOL
 *     principal aquí — ese usuario puede emitir comprobantes a nombre del RUC).
 *   - SUNAT_CERT_PATH → Comprobantes de Pago / Certificado Digital Tributario.
 *     El .pem debe vivir FUERA del docroot; si queda accesible por web,
 *     cualquiera puede firmar comprobantes a nombre de la empresa.
 *
 * SUNAT_MODO=BETA apunta a e-beta.sunat.gob.pe con el RUC/usuario de pruebas
 * de SUNAT (20000000001 / MODDATOS / moddatos) — así todo el desarrollo corre
 * sin arriesgar correlativos reales. El paso a producción es cambiar esta
 * variable y las credenciales, sin tocar código.
 *
 * Nunca hardcodear valores reales aquí: todo se lee de .env, que está en .gitignore.
 */

$_envFile = dirname(__DIR__) . '/.env';
$_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

define('SUNAT_MODO', strtoupper($_env['SUNAT_MODO'] ?? 'BETA'));

// Datos del emisor (empresa). En BETA se ignoran y se usan los de prueba de SUNAT.
if (SUNAT_MODO === 'PRODUCCION') {
    define('SUNAT_RUC', $_env['SUNAT_RUC'] ?? '');
    define('SUNAT_SOL_USUARIO', $_env['SUNAT_SOL_USUARIO'] ?? '');
    define('SUNAT_SOL_CLAVE', $_env['SUNAT_SOL_CLAVE'] ?? '');
    define('SUNAT_CERT_PATH', $_env['SUNAT_CERT_PATH'] ?? '');
} else {
    // Credenciales públicas de homologación/pruebas de SUNAT — no son secretas.
    define('SUNAT_RUC', '20000000001');
    define('SUNAT_SOL_USUARIO', 'MODDATOS');
    define('SUNAT_SOL_CLAVE', 'moddatos');
    // El certificado de prueba lo trae greenter/xmldsig en su repo de tests;
    // hasta tener uno propio de pruebas, M_Sunat debe fallar de forma explícita
    // si SUNAT_CERT_PATH no existe, no firmar con una ruta inválida en silencio.
    define('SUNAT_CERT_PATH', $_env['SUNAT_CERT_PATH_BETA'] ?? '');
}

define('SUNAT_RAZON_SOCIAL', $_env['SUNAT_RAZON_SOCIAL'] ?? '');
define('SUNAT_NOMBRE_COMERCIAL', $_env['SUNAT_NOMBRE_COMERCIAL'] ?? '');
define('SUNAT_UBIGEO', $_env['SUNAT_UBIGEO'] ?? '');
define('SUNAT_DEPARTAMENTO', $_env['SUNAT_DEPARTAMENTO'] ?? '');
define('SUNAT_PROVINCIA', $_env['SUNAT_PROVINCIA'] ?? '');
define('SUNAT_DISTRITO', $_env['SUNAT_DISTRITO'] ?? '');
define('SUNAT_DIRECCION', $_env['SUNAT_DIRECCION'] ?? '');

// Endpoints del servicio web de facturación (billService). El de guías de
// remisión y percepción/retención tienen su propia URL — fuera de alcance hoy.
define('SUNAT_WS_ENDPOINT', SUNAT_MODO === 'PRODUCCION'
    ? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService'
    : 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService'
);

// Boleta sobre este monto exige identificar al comprador con documento (POS ya
// no puede vender a "Público General"). Configurable en un solo sitio.
define('SUNAT_BOLETA_UMBRAL_DNI', floatval($_env['SUNAT_BOLETA_UMBRAL_DNI'] ?? 700));

unset($_envFile, $_env);
