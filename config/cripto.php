<?php
/**
 * config/cripto.php
 * Cifrado simétrico de secretos (API keys, passwords de servicios) para que la
 * tabla `configuracion` no los almacene en texto plano.
 *
 * Diseño:
 *   - AES-256-GCM (autenticado: el tag detecta manipulación del ciphertext).
 *   - Clave maestra en .env → APP_ENCRYPTION_KEY (base64 de 32 bytes).
 *   - Formato persistido:  "encv1:<iv_b64>:<tag_b64>:<ciphertext_b64>"
 *     El prefijo "encv1:" permite distinguir un valor cifrado de uno que aún no
 *     lo está (p. ej. migración parcial o fallback de .env).
 *   - Una clave maestra por instalación: se genera con `openssl rand -base64 32`.
 */

/** Lee la clave maestra desde .env (una sola vez por request). */
function criptoClaveMaestra(): string {
    static $clave = null;
    if ($clave === null) {
        $_envFile = dirname(__DIR__) . '/.env';
        $env = file_exists($_envFile) ? parse_ini_file($_envFile) : [];
        $clave = (string) ($env['APP_ENCRYPTION_KEY'] ?? '');
    }
    return $clave;
}

/**
 * Cifra un texto. Devuelve la cadena "encv1:..." o '' si falta la clave maestra
 * o el texto está vacío (vacío se guarda vacío, sin envoltorio).
 */
function cifrarSecreto(string $texto): string {
    if ($texto === '') return '';
    $clave = criptoClaveMaestra();
    if ($clave === '') return ''; // sin clave maestra no se puede cifrar

    $key  = base64_decode($clave);
    $iv   = random_bytes(12);                       // 96 bits para GCM
    $cifrado = openssl_encrypt($texto, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($cifrado === false) return '';

    return 'encv1:'
        . base64_encode($iv)
        . ':' . base64_encode($tag)
        . ':' . base64_encode($cifrado);
}

/**
 * Descifra una cadena producida por cifrarSecreto(). Si el valor NO tiene el
 * prefijo "encv1:" (texto plano antiguo o fallback de .env) se devuelve tal cual.
 */
function descifrarSecreto(string $token): string {
    if ($token === '') return '';
    if (!str_starts_with($token, 'encv1:')) {
        return $token; // ya es texto plano (fallback de .env o dato sin migrar)
    }

    $clave = criptoClaveMaestra();
    if ($clave === '') return '';

    $partes = explode(':', substr($token, 6), 3);
    if (count($partes) !== 3) return '';

    [$ivB64, $tagB64, $cifB64] = $partes;

    $claro = openssl_decrypt(
        base64_decode($cifB64),
        'aes-256-gcm',
        base64_decode($clave),
        OPENSSL_RAW_DATA,
        base64_decode($ivB64),
        base64_decode($tagB64)
    );

    return $claro === false ? '' : $claro;
}