<?php
/**
 * config/marca.php
 * Marca y apariencia del sistema, 100% editables desde el panel de configuración.
 *
 * Centraliza nombre, slogan, colores y textos de marca; genera un bloque CSS
 * dinámico que sobrescribe los tokens de color (--navy/--accent en la tienda y
 * --gp-primary/--gp-accent en el panel) con las variantes derivadas calculadas.
 *
 * Uso:
 *   require_once dirname(__DIR__) . '/config/marca.php';
 *   echo marcaCss();                      // <style> con las variables de color
 *   echo marcaVar('nombre');              // "NISSI"
 *   echo marcaLogo('LOGO_OSCURO', '...'); // ruta del logo o fallback
 */

require_once dirname(__DIR__) . '/config/settings.php';

/**
 * Lee los valores de marca (BD primero, fallback a las constantes actuales).
 * Resultado cacheado por request.
 */
function marcaDatos(): array {
    static $marca = null;
    if ($marca !== null) return $marca;

    $nombre = configuracion('MARCA_NOMBRE', '');
    if ($nombre === '') {
        $nombre = configuracion('SUNAT_NOMBRE_COMERCIAL', '');
        if ($nombre === '') $nombre = configuracion('SUNAT_RAZON_SOCIAL', 'NISSI');
    }

    $marca = [
        'nombre'   => $nombre,
        'slogan'   => configuracion('MARCA_SLOGAN', 'Uniforme escolar'),
        'emailFooter' => configuracion('MARCA_EMAIL_FOOTER', 'Tienda de uniformes escolares · Todos los derechos reservados'),
        'primario' => configuracion('COLOR_PRIMARIO', '#23284E'),
        'acento'   => configuracion('COLOR_ACENTO', '#BD1721'),
    ];
    return $marca;
}

/** Valor puntual de marca (nombre, slogan, primario, acento, emailFooter). */
function marcaVar(string $clave): string {
    $m = marcaDatos();
    return $m[$clave] ?? '';
}

/** Logo de marca: usa el archivo configurado y cae al fallback si no hay. */
function marcaLogo(string $clave, string $fallback): string {
    $logo = configuracion($clave, '');
    return $logo !== '' ? $logo : $fallback;
}

/* ────────────────────────────────────────────────────────────────────────────
 * Helpers de color (hex → hsl → mezcla con negro/blanco)
 * ──────────────────────────────────────────────────────────────────────────── */

/** Convierte #RRGGBB a [r,g,b] enteros. */
function _marcaHexRgb(string $hex): array {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) return [35, 40, 78];
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

/**
 * Mezcla un color con negro (0..1) o blanco (negativo) → "#rrggbb".
 * $t = 0 devuelve el color original; 1 → negro puro; -1 → blanco puro.
 */
function _marcaMezclar(string $hex, float $t): string {
    [$r, $g, $b] = _marcaHexRgb($hex);
    if ($t >= 0) {
        $r = (int) round($r * (1 - $t));
        $g = (int) round($g * (1 - $t));
        $b = (int) round($b * (1 - $t));
    } else {
        $w = -$t;
        $r = (int) round($r + (255 - $r) * $w);
        $g = (int) round($g + (255 - $g) * $w);
        $b = (int) round($b + (255 - $b) * $w);
    }
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/** Devuelve un color como "r,g,b" (para rgba() en CSS). */
function _marcaRgb(string $hex): string {
    return implode(',', _marcaHexRgb($hex));
}

/**
 * Genera el bloque <style> con los tokens de marca. Se usa tanto en la tienda
 * (store.php) como en el panel (header.php) y en el login.
 */
/**
 * Declaraciones :root con los tokens de marca (SIN envoltorio <style>).
 * Se inyecta DENTRO de un bloque <style> existente (login, panel).
 */
function marcaCssVars(): string {
    $m = marcaDatos();
    $navy      = $m['primario'];
    $accent    = $m['acento'];

    $navy700 = _marcaMezclar($navy, 0.30);
    $navy900 = _marcaMezclar($navy, 0.55);
    $accent600 = _marcaMezclar($accent, 0.18);
    $accent100 = _marcaMezclar($accent, -0.86);
    $gpHover   = _marcaMezclar($navy, 0.10);
    $gpLight   = _marcaMezclar($navy, -0.90);

    $navyRgb   = _marcaRgb($navy);
    $accentRgb = _marcaRgb($accent);

    return <<<CSS
:root{
  --navy:{$navy};--navy-700:{$navy700};--navy-900:{$navy900};--navy-rgb:{$navyRgb};
  --accent:{$accent};--accent-600:{$accent600};--accent-100:{$accent100};--accent-rgb:{$accentRgb};
  --gp-primary:{$navy};--gp-primary-hover:{$gpHover};--gp-primary-light:{$gpLight};
  --gp-accent:{$accent};--gp-sidebar-active:{$navy};
}
CSS
    ;
}

/**
 * Bloque <style> completo con los tokens de marca. Se usa en páginas que NO
 * tienen un bloque <style> propio donde inyectar las variables (store.php y las
 * vistas públicas que cargan tienda.css). En páginas con <style> propio (login,
 * panel) usar marcaCssVars() dentro de ese <style>, NO este envoltorio.
 */
function marcaCss(): string {
    return '<style id="marca-css">' . marcaCssVars() . '</style>';
}