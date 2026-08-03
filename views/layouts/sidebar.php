<?php
// Obtener el módulo actual para pintar con clase "active" el botón seleccionado
$moduloActual = isset($_GET['modulo']) ? $_GET['modulo'] : ($_SESSION['rol'] === 'Administrador' ? 'dashboard' : 'nueva-venta');
$rol = $_SESSION['rol'];
?>
<aside class="sidebar" id="sidebar">
    <!-- Logotipo y Nombre de Marca -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon" style="background: transparent; overflow: hidden; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px;">
            <img src="assets/Logo Login PuntoNet.png" alt="PuntoNet" style="max-width: 100%; max-height: 100%; object-fit: contain; filter: brightness(0) invert(1);">
        </div>
        <div class="brand-text">
            <h6 class="mb-0 fw-bold">NISSI</h6>
            <small class="text-white-50" style="font-size: 10px;">Tienda de Uniformes</small>
        </div>
    </div>
    
    <!-- Menú de Navegación Lateral -->
    <div class="sidebar-menu">
        <!-- Grupo de Análisis General (Solo Administrador) -->
        <?php if ($rol === 'Administrador'): ?>
            <a href="index.php?modulo=dashboard" class="menu-item <?php echo $moduloActual === 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
        <?php endif; ?>

        <!-- Grupo de Mantenimiento de Inventario (Solo Administrador) -->
        <?php if ($rol === 'Administrador'): ?>
            <div class="menu-header">Inventario</div>
            <a href="index.php?modulo=categorias" class="menu-item <?php echo $moduloActual === 'categorias' ? 'active' : ''; ?>">
                <i class="bi bi-tags-fill"></i>
                <span>Categorías</span>
            </a>
            <a href="index.php?modulo=productos" class="menu-item <?php echo $moduloActual === 'productos' ? 'active' : ''; ?>">
                <i class="bi bi-box-seam-fill"></i>
                <span>Productos</span>
            </a>
            <a href="index.php?modulo=kardex" class="menu-item <?php echo $moduloActual === 'kardex' ? 'active' : ''; ?>">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Kardex</span>
            </a>
        <?php endif; ?>

        <!-- Grupo de Administración y Clientes -->
        <div class="menu-header">Administración</div>
        <?php if ($rol === 'Administrador'): ?>
            <a href="index.php?modulo=usuarios" class="menu-item <?php echo $moduloActual === 'usuarios' ? 'active' : ''; ?>">
                <i class="bi bi-people-fill"></i>
                <span>Usuarios</span>
            </a>
        <?php endif; ?>
        <a href="index.php?modulo=clientes" class="menu-item <?php echo $moduloActual === 'clientes' ? 'active' : ''; ?>">
            <i class="bi bi-person-vcard-fill"></i>
            <span>Clientes</span>
        </a>

        <!-- Grupo Operativo de Ventas y Cajas -->
        <div class="menu-header">Ventas & Caja</div>
        <a href="index.php?modulo=caja" class="menu-item <?php echo $moduloActual === 'caja' ? 'active' : ''; ?>">
            <i class="bi bi-cash-coin"></i>
            <span>Mi Caja</span>
        </a>
        <a href="index.php?modulo=nueva-venta" class="menu-item <?php echo $moduloActual === 'nueva-venta' ? 'active' : ''; ?>">
            <i class="bi bi-cart-fill"></i>
            <span>Nueva Venta</span>
        </a>
        <a href="index.php?modulo=cotizaciones" class="menu-item <?php echo $moduloActual === 'cotizaciones' ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-text-fill"></i>
            <span>Cotizaciones</span>
        </a>
        <a href="index.php?modulo=pedidos-online" class="menu-item <?php echo $moduloActual === 'pedidos-online' ? 'active' : ''; ?>">
            <i class="bi bi-cloud-arrow-down-fill"></i>
            <span>Pedidos Online</span>
        </a>
        <a href="index.php?modulo=historial" class="menu-item <?php echo $moduloActual === 'historial' ? 'active' : ''; ?>">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Historial de Ventas</span>
        </a>

        <!-- Grupo de Análisis y Control Administrativo (Solo Administrador) -->
        <?php if ($rol === 'Administrador'): ?>
            <div class="menu-header">Análisis &amp; Control</div>
            <a href="index.php?modulo=control-cajas" class="menu-item <?php echo $moduloActual === 'control-cajas' ? 'active' : ''; ?>">
                <i class="bi bi-safe-fill"></i>
                <span>Control de Cajas</span>
            </a>
            <a href="index.php?modulo=reportes" class="menu-item <?php echo $moduloActual === 'reportes' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>Reportes</span>
            </a>
        <?php endif; ?>
    </div>
</aside>
