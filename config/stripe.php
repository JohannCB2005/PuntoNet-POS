<?php
/**
 * config/stripe.php
 * Configuración de claves de Stripe.
 *
 * IMPORTANTE: No versionar este archivo con claves reales en repositorios públicos.
 * La clave pública (PK) puede estar en el frontend.
 * La clave secreta (SK) solo se usa en el backend (create_payment_intent.php).
 */

// ── Intentar leer desde .env primero ─────────────────────────────────────────
$_envFile = dirname(__DIR__) . '/.env';
$_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

// ── Claves de Stripe ──────────────────────────────────────────────────────────
// Si .env no existe o no tiene las claves, usar los valores hardcoded aquí.
// Actualiza estos valores en producción con tus claves reales de Stripe.

define('STRIPE_PK', $_env['STRIPE_PK']
    ?? 'YOUR_STRIPE_PUBLIC_KEY'
);

define('STRIPE_SK', $_env['STRIPE_SK']
    ?? 'YOUR_STRIPE_SECRET_KEY'
);

// ── Secreto del webhook (Stripe Dashboard → Developers → Webhooks) ───────────
define('STRIPE_WH_SECRET', $_env['STRIPE_WH_SECRET']
    ?? ''
);

unset($_envFile, $_env);
