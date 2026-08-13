<?php
// Obtener el módulo actual para pintar con clase "active" el botón seleccionado
$moduloActual = isset($_GET['modulo']) ? $_GET['modulo'] : ($_SESSION['rol'] === 'Administrador' ? 'dashboard' : 'nueva-venta');
$rol = $_SESSION['rol'];
?>
<aside class="sidebar" id="sidebar">
    <!-- Logotipo y Nombre de Marca -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon" style="background: transparent; overflow: hidden; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px;">
            <img src="assets/logo.svg" alt="PuntoNet" style="max-width: 100%; max-height: 100%; object-fit: contain;">
        </div>
        <div class="brand-text">
            <h6 class="mb-0 fw-bold">NISSI</h6>
            <small class="text-white-50" style="font-size: 10px;">Tienda de Uniformes</small>
        </div>
    </div>
    
    <!-- Menú de Navegación Lateral -->
    <div class="sidebar-menu">
        <!-- Dashboard (Solo Administrador) -->
        <?php if ($rol === 'Administrador'): ?>
            <a href="/dashboard" class="menu-item <?php echo $moduloActual === 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
        <?php endif; ?>

        <!-- Grupo de Mantenimiento de Inventario (Solo Administrador) -->
        <?php if ($rol === 'Administrador'): ?>
            <div class="sidebar-group<?php echo in_array($moduloActual, ['categorias', 'productos', 'kardex']) ? ' open has-active' : ''; ?>" data-group="inventario">
                <button class="menu-header" type="button" aria-expanded="true" aria-controls="grupo-inventario">
                    <span>Inventario</span>
                    <i class="bi bi-chevron-down menu-header-arrow"></i>
                </button>
                <div class="menu-group-items" id="grupo-inventario">
                    <a href="/categorias" class="menu-item <?php echo $moduloActual === 'categorias' ? 'active' : ''; ?>">
                        <i class="bi bi-tags-fill"></i>
                        <span>Categorías</span>
                    </a>
                    <a href="/productos" class="menu-item <?php echo $moduloActual === 'productos' ? 'active' : ''; ?>">
                        <i class="bi bi-box-seam-fill"></i>
                        <span>Productos</span>
                    </a>
                    <a href="/kardex" class="menu-item <?php echo $moduloActual === 'kardex' ? 'active' : ''; ?>">
                        <i class="bi bi-journal-bookmark-fill"></i>
                        <span>Kardex</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grupo de Administración y Clientes -->
        <?php $grupoAdminActivo = ($rol === 'Administrador' && $moduloActual === 'usuarios') || $moduloActual === 'clientes'; ?>
        <div class="sidebar-group<?php echo $grupoAdminActivo ? ' open has-active' : ''; ?>" data-group="administracion">
            <button class="menu-header" type="button" aria-expanded="true" aria-controls="grupo-administracion">
                <span>Administración</span>
                <i class="bi bi-chevron-down menu-header-arrow"></i>
            </button>
            <div class="menu-group-items" id="grupo-administracion">
                <?php if ($rol === 'Administrador'): ?>
                    <a href="/usuarios" class="menu-item <?php echo $moduloActual === 'usuarios' ? 'active' : ''; ?>">
                        <i class="bi bi-people-fill"></i>
                        <span>Usuarios</span>
                    </a>
                <?php endif; ?>
                <a href="/clientes" class="menu-item <?php echo $moduloActual === 'clientes' ? 'active' : ''; ?>">
                    <i class="bi bi-person-vcard-fill"></i>
                    <span>Clientes</span>
                </a>
            </div>
        </div>

        <!-- Grupo Operativo de Ventas y Cajas -->
        <?php $grupoVentasActivo = in_array($moduloActual, ['caja', 'nueva-venta', 'cotizaciones', 'separaciones', 'pedidos-online', 'historial']); ?>
        <div class="sidebar-group<?php echo $grupoVentasActivo ? ' open has-active' : ''; ?>" data-group="ventas">
            <button class="menu-header" type="button" aria-expanded="true" aria-controls="grupo-ventas">
                <span>Ventas &amp; Caja</span>
                <i class="bi bi-chevron-down menu-header-arrow"></i>
            </button>
            <div class="menu-group-items" id="grupo-ventas">
                <a href="/caja" class="menu-item <?php echo $moduloActual === 'caja' ? 'active' : ''; ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>Mi Caja</span>
                </a>
                <a href="/nueva-venta" class="menu-item <?php echo $moduloActual === 'nueva-venta' ? 'active' : ''; ?>">
                    <i class="bi bi-cart-fill"></i>
                    <span>Nueva Venta</span>
                </a>
                <a href="/cotizaciones" class="menu-item <?php echo $moduloActual === 'cotizaciones' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Cotizaciones</span>
                </a>
                <a href="/separaciones" class="menu-item <?php echo $moduloActual === 'separaciones' ? 'active' : ''; ?>">
                    <i class="bi bi-bookmark-star-fill"></i>
                    <span>Separaciones</span>
                </a>
                <a href="/pedidos-online" class="menu-item <?php echo $moduloActual === 'pedidos-online' ? 'active' : ''; ?>">
                    <i class="bi bi-cloud-arrow-down-fill"></i>
                    <span>Pedidos Online</span>
                </a>
                <a href="/historial" class="menu-item <?php echo $moduloActual === 'historial' ? 'active' : ''; ?>">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>Historial de Ventas</span>
                </a>
            </div>
        </div>

        <!-- Grupo de Colegio (Módulos) — Admin ve todo; Vendedor solo Entregas -->
        <?php $grupoColegioActivo = in_array($moduloActual, ['carga', 'alumnos', 'pagos', 'conciliacion', 'promociones', 'entregas']); ?>
        <div class="sidebar-group<?php echo $grupoColegioActivo ? ' open has-active' : ''; ?>" data-group="colegio">
            <button class="menu-header" type="button" aria-expanded="true" aria-controls="grupo-colegio">
                <span>Módulos Escolares</span>
                <i class="bi bi-chevron-down menu-header-arrow"></i>
            </button>
            <div class="menu-group-items" id="grupo-colegio">
                <?php if ($rol === 'Administrador'): ?>
                    <a href="/carga" class="menu-item <?php echo $moduloActual === 'carga' ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-arrow-up-fill"></i>
                        <span>Carga de Datos</span>
                    </a>
                    <a href="/alumnos" class="menu-item <?php echo $moduloActual === 'alumnos' ? 'active' : ''; ?>">
                        <i class="bi bi-mortarboard-fill"></i>
                        <span>Padrón de Alumnos</span>
                    </a>
                    <a href="/pagos" class="menu-item <?php echo $moduloActual === 'pagos' ? 'active' : ''; ?>">
                        <i class="bi bi-cash-stack"></i>
                        <span>Pagos y Pensiones</span>
                    </a>
                    <a href="/conciliacion" class="menu-item <?php echo $moduloActual === 'conciliacion' ? 'active' : ''; ?>">
                        <i class="bi bi-diagram-3-fill"></i>
                        <span>Conciliación</span>
                    </a>
                    <a href="/promociones" class="menu-item <?php echo $moduloActual === 'promociones' ? 'active' : ''; ?>">
                        <i class="bi bi-gift-fill"></i>
                        <span>Promociones</span>
                    </a>
                    <a href="/entregas" class="menu-item <?php echo $moduloActual === 'entregas' ? 'active' : ''; ?>">
                        <i class="bi bi-box2-fill"></i>
                        <span>Entregas de Módulos</span>
                    </a>
                <?php else: ?>
                    <a href="/entregas" class="menu-item <?php echo $moduloActual === 'entregas' ? 'active' : ''; ?>">
                        <i class="bi bi-box2-fill"></i>
                        <span>Entregas de Módulos</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grupo de Análisis y Control Administrativo (Solo Administrador) -->
        <?php if ($rol === 'Administrador'): ?>
            <?php $grupoControlActivo = in_array($moduloActual, ['control-cajas', 'reportes', 'comisiones', 'sunat-series']); ?>
            <div class="sidebar-group<?php echo $grupoControlActivo ? ' open has-active' : ''; ?>" data-group="control">
                <button class="menu-header" type="button" aria-expanded="true" aria-controls="grupo-control">
                    <span>Análisis &amp; Control</span>
                    <i class="bi bi-chevron-down menu-header-arrow"></i>
                </button>
                <div class="menu-group-items" id="grupo-control">
                    <a href="/control-cajas" class="menu-item <?php echo $moduloActual === 'control-cajas' ? 'active' : ''; ?>">
                        <i class="bi bi-safe-fill"></i>
                        <span>Control de Cajas</span>
                    </a>
                    <a href="/reportes" class="menu-item <?php echo $moduloActual === 'reportes' ? 'active' : ''; ?>">
                        <i class="bi bi-bar-chart-line-fill"></i>
                        <span>Reportes</span>
                    </a>
                    <a href="/comisiones" class="menu-item <?php echo $moduloActual === 'comisiones' ? 'active' : ''; ?>">
                        <i class="bi bi-percent"></i>
                        <span>Comisiones</span>
                    </a>
                    <a href="/sunat-series" class="menu-item <?php echo $moduloActual === 'sunat-series' ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-ruled"></i>
                        <span>Series SUNAT</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</aside>
