<?php
/**
 * config/taypi.php
 * Credenciales de la pasarela de pagos QR interoperables TAYPI (Yape/Plin/BIM).
 *
 * Los valores salen del panel de TAYPI:
 *   - Sandbox:  https://sandbox.taypi.pe/login  →  API Keys
 *   - Producción: panel de producción (las claves cambian de prefijo test_ a live_)
 *
 * Reparto de responsabilidades:
 *   - TAYPI_PUBLIC_KEY  → única que viaja al navegador (inicializa checkout.js).
 *   - TAYPI_SECRET_KEY  → solo backend. Firma HMAC-SHA256 de las llamadas a la API
 *                         y de los webhooks entrantes. Nunca exponerla.
 *   - TAYPI_WEBHOOK_SECRET → solo backend. Clave con la que TAYPI firma el body
 *                         de los webhooks (se guarda al configurar el webhook en
 *                         Panel → Configuración → Webhooks).
 *
 * Nunca hardcodear valores reales aquí: todo se lee de .env, que está en .gitignore.
 */

$_envFile = dirname(__DIR__) . '/.env';
$_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

require_once dirname(__DIR__) . '/config/settings.php';

// El modo (TEST/PRODUCCION) determina el host de la API: en TAYPI el prefijo de la
// clave (test_/live_) y el host van de la mano, pero se sigue el mismo patrón de
// Izipay: una constante MODO para que la UI y los logs adviertan si se está cobrando.
define('TAYPI_MODO', strtoupper(configuracion('TAYPI_MODO', $_env['TAYPI_MODO'] ?? 'TEST')));

// ¿Pasarela habilitada? El administrador la enciende/apaga desde el panel
// (Configuración → Pagos). Default SI para no romper instalaciones previas.
define('TAYPI_HABILITADO', strtoupper(configuracion('TAYPI_HABILITADO', $_env['TAYPI_HABILITADO'] ?? 'SI')));

define('TAYPI_PUBLIC_KEY',      configuracion('TAYPI_PUBLIC_KEY',      $_env['TAYPI_PUBLIC_KEY']      ?? ''));
define('TAYPI_SECRET_KEY',      configuracion('TAYPI_SECRET_KEY',      $_env['TAYPI_SECRET_KEY']      ?? ''));
define('TAYPI_WEBHOOK_SECRET',  configuracion('TAYPI_WEBHOOK_SECRET',  $_env['TAYPI_WEBHOOK_SECRET']  ?? ''));

// API base según el modo (doc oficial: sandbox.taypi.pe / app.taypi.pe).
$taypiApiHost = TAYPI_MODO === 'PRODUCCION' ? 'https://app.taypi.pe' : 'https://sandbox.taypi.pe';
define('TAYPI_API_HOST', rtrim(configuracion('TAYPI_API_HOST', $_env['TAYPI_API_HOST'] ?? $taypiApiHost), '/'));

// checkout.js también vive en el host del ambiente: con claves test_ debe cargarse
// desde sandbox.taypi.pe, si no valida la public key contra producción y falla
// con "API key inválida o revocada" (AUTH_KEY_INVALID).
define('TAYPI_CHECKOUT_JS_URL', rtrim(configuracion('TAYPI_CHECKOUT_JS_URL', $_env['TAYPI_CHECKOUT_JS_URL'] ?? $taypiApiHost), '/') . '/v1/checkout.js');

unset($_envFile, $_env, $taypiApiHost);