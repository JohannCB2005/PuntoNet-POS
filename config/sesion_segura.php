<?php
/**
 * config/sesion_segura.php
 * Parámetros de cookie de sesión endurecidos, aplicados ANTES de session_start().
 *
 * - HttpOnly: la cookie no es legible desde JS (bloquea robo de sesión por XSS).
 * - Secure: solo se envía por HTTPS (el túnel Cloudflare sirve todo por HTTPS).
 * - SameSite=Lax: la cookie no viaja en peticiones cross-site (mitiga CSRF básico).
 * - use_strict_mode=1: rechaza IDs de sesión sin inicializar (anti fixation).
 *
 * Usar así, justo antes de session_start():
 *     require_once __DIR__ . '/config/sesion_segura.php';
 */

if (PHP_SESSION_ACTIVE !== session_status()) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}