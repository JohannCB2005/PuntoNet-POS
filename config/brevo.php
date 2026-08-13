<?php
/**
 * config/brevo.php
 * Configuración de Brevo (correo transaccional vía API HTTP).
 *
 * IMPORTANTE: No versionar este archivo con claves reales en repositorios públicos.
 * La API key y el remitente se leen de .env; aquí solo quedan fallbacks vacíos.
 */

// ── Intentar leer desde .env primero ─────────────────────────────────────────
$_envFile = dirname(__DIR__) . '/.env';
$_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

define('BREVO_API_KEY', $_env['BREVO_API_KEY'] ?? '');
define('BREVO_SENDER_EMAIL', $_env['BREVO_SENDER_EMAIL'] ?? '');
define('BREVO_SENDER_NAME', $_env['BREVO_SENDER_NAME'] ?? 'PuntoNet');

unset($_envFile, $_env);
