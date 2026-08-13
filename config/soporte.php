<?php
/**
 * config/soporte.php
 * Contacto de Atención al Cliente (WhatsApp) para cancelaciones y devoluciones.
 * Las cancelaciones/reembolsos se coordinan manualmente por este canal — no hay
 * reembolso automático en el sistema.
 */

$_envFile = dirname(__DIR__) . '/.env';
$_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

define('WHATSAPP_ATENCION', $_env['WHATSAPP_ATENCION'] ?? '');

unset($_envFile, $_env);
