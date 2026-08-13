<?php
// Cabeceras de seguridad HTTP
header('X-Frame-Options: DENY');           // Anti-clickjacking
header('X-Content-Type-Options: nosniff'); // Anti-MIME sniffing
header('Referrer-Policy: strict-origin');  // Control de referrer

session_start();

// 1. Auth check
if (!isset($_SESSION['id_usuario'])) {
    require_once 'views/V_login.php';
    exit;
}

$rol = $_SESSION['rol'];

// 2. Default route based on role
$defaultModule = ($rol === 'Administrador') ? 'dashboard' : 'nueva-venta';
$modulo = isset($_GET['modulo']) ? $_GET['modulo'] : $defaultModule;

// 3. Define route permissions
$routes = [
    'dashboard'      => ['Administrador'],
    'categorias'     => ['Administrador'],
    'productos'        => ['Administrador'],
    'kardex'         => ['Administrador'],
    'usuarios'       => ['Administrador'],
    'clientes'       => ['Administrador', 'Vendedor'],
    'nueva-venta'    => ['Administrador', 'Vendedor'],
    'historial'      => ['Administrador', 'Vendedor'],
    'reportes'       => ['Administrador'],
    'pedidos-online' => ['Administrador', 'Vendedor'],
    'cotizaciones'   => ['Administrador', 'Vendedor'],
    'nueva-cotizacion' => ['Administrador', 'Vendedor'],
    'separaciones'   => ['Administrador', 'Vendedor'],
    'caja'           => ['Administrador', 'Vendedor'],
    'control-cajas'  => ['Administrador'],
    'comisiones'     => ['Administrador'],
    'sunat-series'   => ['Administrador']
];

// 4. Validate route exists and is allowed for the user's role
if (!array_key_exists($modulo, $routes)) {
    $modulo = $defaultModule;
}

if (!in_array($rol, $routes[$modulo])) {
    // If not allowed, redirect to default
    header("Location: index.php?modulo=" . $defaultModule);
    exit;
}

// 5. Title for the header
$titles = [
    'dashboard'      => 'Dashboard - NISSI POS',
    'categorias'     => 'Categorías - NISSI POS',
    'productos'        => 'Productos - NISSI POS',
    'kardex'         => 'Kardex - NISSI POS',
    'usuarios'       => 'Usuarios - NISSI POS',
    'clientes'       => 'Clientes - NISSI POS',
    'nueva-venta'    => 'Nueva Venta - NISSI POS',
    'historial'      => 'Historial de Ventas - NISSI POS',
    'reportes'       => 'Reportes - NISSI POS',
    'pedidos-online' => 'Pedidos Online - NISSI POS',
    'cotizaciones'   => 'Cotizaciones - NISSI POS',
    'nueva-cotizacion' => 'Nueva Cotización - NISSI POS',
    'separaciones'   => 'Separaciones - NISSI POS',
    'caja'           => 'Mi Caja - NISSI POS',
    'control-cajas'  => 'Control de Cajas - NISSI POS',
    'comisiones'     => 'Comisiones - NISSI POS',
    'sunat-series'   => 'Series SUNAT - NISSI POS'
];
$title = isset($titles[$modulo]) ? $titles[$modulo] : 'NISSI POS';

// 6. Include layout and render module view
require_once 'views/layouts/header.php';
require_once 'views/layouts/sidebar.php';
require_once 'views/layouts/navbar.php';

echo '<main class="main-content">';
switch ($modulo) {
    case 'dashboard':
        require_once 'views/V_dashboard.php';
        break;
    case 'categorias':
        require_once 'views/V_categorias.php';
        break;
    case 'productos':
        require_once 'views/V_productos.php';
        break;
    case 'kardex':
        require_once 'views/V_kardex.php';
        break;
    case 'usuarios':
        require_once 'views/V_usuarios.php';
        break;
    case 'clientes':
        require_once 'views/V_clientes.php';
        break;
    case 'nueva-venta':
        require_once 'views/V_nueva_venta.php';
        break;
    case 'historial':
        require_once 'views/V_historial.php';
        break;
    case 'reportes':
        require_once 'views/V_reportes.php';
        break;
    case 'pedidos-online':
        require_once 'views/V_pedidos_online.php';
        break;
    case 'cotizaciones':
        require_once 'views/V_cotizaciones.php';
        break;
    case 'nueva-cotizacion':
        require_once 'views/V_nueva_cotizacion.php';
        break;
    case 'separaciones':
        require_once 'views/V_separaciones.php';
        break;
    case 'caja':
        require_once 'views/V_caja.php';
        break;
    case 'control-cajas':
        require_once 'views/V_control_cajas.php';
        break;
    case 'comisiones':
        require_once 'views/V_comisiones.php';
        break;
    case 'sunat-series':
        require_once 'views/V_sunat_series.php';
        break;
}
echo '</main>';

require_once 'views/layouts/footer.php';
?>