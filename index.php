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
    'dashboard', 'categorias', 'productos', 'kardex', 'usuarios', 'clientes',
    'nueva-venta', 'historial', 'reportes', 'pedidos-online', 'cotizaciones',
    'nueva-cotizacion', 'separaciones', 'caja', 'control-cajas', 'comisiones',
    'sunat-series', 'carga', 'alumnos', 'pagos', 'conciliacion', 'promociones',
    'entregas',
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