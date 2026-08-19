<?php
/**
 * index.php (router local)
 * =========================
 * El front controller real es pos.php (ver .htaccess en producción,
 * porque el hosting bloquea el nombre "index.php").
 *
 * Este archivo se usa con el servidor built-in de PHP (php -S), que
 * toma index.php como página de inicio y NO procesa .htaccess. Por eso
 * replicamos aquí las reglas de mod_rewrite: las URLs limpias como
 * /dashboard, /nueva-venta, /login, /logout, etc. se traducen a
 * pos.php?modulo=... (o al controller correspondiente).
 *
 * No debe desplegarse en producción: allí el .htaccess fija pos.php
 * como DirectoryIndex y estas reglas ya existen en mod_rewrite.
 */

// Módulos validados (misma lista que $routes en pos.php)
$modulos = [
    'dashboard', 'categorias', 'productos', 'tipos-variante', 'kardex', 'usuarios', 'clientes',
    'nueva-venta', 'historial', 'reportes', 'pedidos-online', 'cotizaciones',
    'nueva-cotizacion', 'separaciones', 'caja', 'control-cajas', 'comisiones',
    'sunat-series', 'configuracion', 'carga', 'alumnos', 'pagos', 'conciliacion',
    'promociones', 'entregas',
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');
$path = explode('/', $path)[0]; // solo primer segmento

if ($path === '') {
    // Raíz → pos.php (muestra login si no hay sesión)
    require __DIR__ . '/pos.php';
    return;
}

if ($path === 'login') {
    require __DIR__ . '/pos.php';
    return;
}

if ($path === 'logout') {
    require __DIR__ . '/logout.php';
    return;
}

// Tienda en línea pública: /Tienda (y /tienda) sirve store.php como ruta limpia.
// No requiere sesión ni módulo del POS. La barra final se normaliza porque
// rompería las rutas relativas (assets/, controllers/, views/public/).
if (strtolower($path) === 'tienda') {
    if (substr($_SERVER['REQUEST_URI'], -1) === '/') {
        header('Location: /Tienda', true, 301);
        exit;
    }
    require __DIR__ . '/store.php';
    return;
}

// Tienda: /compra (checkout) y /confirmacion (éxito) como rutas limpias.
if (strtolower($path) === 'compra') {
    require __DIR__ . '/views/public/V_checkout.php';
    return;
}
if (strtolower($path) === 'confirmacion') {
    require __DIR__ . '/views/public/V_checkout_success.php';
    return;
}
if (strtolower($path) === 'cuenta') {
    require __DIR__ . '/views/public/V_cuenta.php';
    return;
}
if (strtolower($path) === 'mi-cuenta') {
    require __DIR__ . '/views/public/V_mi_cuenta.php';
    return;
}
if (strtolower($path) === 'mis-pedidos') {
    require __DIR__ . '/views/public/V_mis_pedidos.php';
    return;
}

// Comprobante público por enlace: /comprobante?id=<venta>&t=<token>.
if (strtolower($path) === 'comprobante') {
    require __DIR__ . '/controllers/C_ComprobantePublico.php';
    return;
}

if (in_array($path, $modulos)) {
    $_GET['modulo'] = $path;
    require __DIR__ . '/pos.php';
    return;
}

// Rutas estáticas (tienda, etc.) o cualquier otro archivo existente
$archivo = __DIR__ . '/' . ($_SERVER['REQUEST_URI'] ? ltrim($_SERVER['REQUEST_URI'], '/') : '');
if (substr($archivo, -4) === '.php' && file_exists($archivo)) {
    return false; // deja que php -S sirva el archivo directamente
}

// Cualquier otra cosa → 404
http_response_code(404);
echo 'Not Found: /' . htmlspecialchars($path);