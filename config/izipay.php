<?php
/**
 * config/izipay.php
 * Credenciales de la pasarela Izipay (API REST V4 / formulario embebido Krypton).
 *
 * Todas salen del Back Office Vendedor (https://secure.micuentaweb.pe/vads-merchant/)
 * en Configuración → Tiendas → **Claves de API REST**. Ojo: la pestaña "Claves" de al
 * lado corresponde al formulario legacy V1/V2 + SOAP y sus valores NO sirven aquí.
 *
 * Reparto de responsabilidades (importante, es fácil equivocarse):
 *   - IZIPAY_PUBLIC_KEY   → única que puede viajar al navegador (inicializa Krypton).
 *   - IZIPAY_PASSWORD     → solo backend. Basic Auth del API REST **y** clave con la
 *                           que se valida la firma de la IPN (servidor a servidor).
 *   - IZIPAY_HMAC_SHA256  → solo backend. Valida la firma del retorno al navegador
 *                           (kr-answer / kr-hash) tras el pago.
 *
 * Nunca hardcodear valores reales aquí: todo se lee de .env, que está en .gitignore.
 */

$_envFile = dirname(__DIR__) . '/.env';
$_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

require_once dirname(__DIR__) . '/config/settings.php';

// TEST mientras se integra en sandbox. En Izipay el modo lo determina la clave
// utilizada, no un host distinto; esta constante es solo para que la UI y los logs
// puedan advertir que no se está cobrando de verdad.
define('IZIPAY_MODO', strtoupper(configuracion('IZIPAY_MODO', $_env['IZIPAY_MODO'] ?? 'TEST')));

// ¿Pasarela habilitada? La administra el panel (Configuración → Pagos). Default SI.
define('IZIPAY_HABILITADO', strtoupper(configuracion('IZIPAY_HABILITADO', $_env['IZIPAY_HABILITADO'] ?? 'SI')));

define('IZIPAY_SHOP_ID',     configuracion('IZIPAY_SHOP_ID',     $_env['IZIPAY_SHOP_ID']     ?? ''));
define('IZIPAY_PASSWORD',    configuracion('IZIPAY_PASSWORD',    $_env['IZIPAY_PASSWORD']    ?? ''));
define('IZIPAY_PUBLIC_KEY',  configuracion('IZIPAY_PUBLIC_KEY',  $_env['IZIPAY_PUBLIC_KEY']  ?? ''));
define('IZIPAY_HMAC_SHA256', configuracion('IZIPAY_HMAC_SHA256', $_env['IZIPAY_HMAC_SHA256'] ?? ''));

// "Dirección del servidor de API REST" que indica el propio Back Office.
define('IZIPAY_API_HOST', rtrim(configuracion('IZIPAY_API_HOST', $_env['IZIPAY_API_HOST'] ?? 'https://api.micuentaweb.pe'), '/'));

unset($_envFile, $_env);
