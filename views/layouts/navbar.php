<?php
// Generar iniciales del usuario logueado para mostrar en el avatar del navbar
$iniciales = '';
if (isset($_SESSION['nombres'])) {
    $parts = explode(' ', $_SESSION['nombres']);
    $iniciales .= substr($parts[0], 0, 1);
    if (count($parts) > 1 && !empty($parts[1])) {
        $iniciales .= substr($parts[1], 0, 1);
    }
}
$iniciales = strtoupper($iniciales);
$nombreUsuario = isset($_SESSION['nombres']) ? $_SESSION['nombres'] : 'Usuario';
$rolUsuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'Rol';
require_once dirname(__DIR__, 2) . '/config/settings.php';
$navbarMarcaNombre = configuracion('SUNAT_NOMBRE_COMERCIAL', configuracion('SUNAT_RAZON_SOCIAL', 'NISSI'));
?>
<header class="top-navbar">
    <div class="d-flex align-items-center gap-3">
        <!-- Botón para contraer barra lateral (escritorio) -->
        <button class="btn btn-link text-dark p-0 d-none d-lg-inline-flex" id="toggle-sidebar" aria-label="Contraer barra lateral">
            <i class="bi bi-list fs-4"></i>
        </button>
        <!-- Botón para expandir barra lateral (móvil) -->
        <button class="btn btn-link text-dark p-0 d-lg-none" id="mobile-toggle-sidebar" aria-label="Abrir menú">
            <i class="bi bi-list fs-4"></i>
        </button>
        
        <div class="d-none d-sm-block">
            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($navbarMarcaNombre); ?> POS</h6>
            <small class="text-muted" style="font-size: 11px;">Tienda de Uniformes Escolares</small>
        </div>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <!-- Indicador visual del Rol de Usuario -->
        <div class="d-none d-md-flex align-items-center gap-2">
            <span class="text-muted" style="font-size: 12px; font-weight: 500;">Rol Activo:</span>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 px-2.5 py-1.5 fw-semibold" style="font-size: 11px;">
                <?php echo htmlspecialchars($rolUsuario); ?>
            </span>
        </div>

        <!-- Menú Desplegable de Perfil de Usuario -->
        <div class="dropdown">
            <button class="btn btn-link text-dark text-decoration-none dropdown-toggle d-flex align-items-center gap-2 p-1 border rounded-3 bg-light hover-bg-secondary" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="box-shadow: none;">
                <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 12px;">
                    <?php echo $iniciales; ?>
                </div>
                <div class="text-start d-none d-lg-block leading-tight" style="line-height: 1.2;">
                    <p class="mb-0 fw-semibold text-dark" style="font-size: 13px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($nombreUsuario); ?></p>
                    <small class="text-muted d-block" style="font-size: 10px;"><?php echo htmlspecialchars($rolUsuario); ?></small>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-light" aria-labelledby="userDropdown" style="border-radius: 10px; padding: 8px;">
                <li class="px-3 py-2 border-bottom mb-2">
                    <div class="fw-bold" style="font-size: 14px;"><?php echo htmlspecialchars($nombreUsuario); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($rolUsuario); ?></small>
                </li>
                <?php if ($_SESSION['rol'] === 'Administrador'): ?>
                <li class="mb-1">
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded-2" href="/configuracion">
                        <i class="bi bi-gear-fill text-primary"></i>
                        Configuración de la tienda
                    </a>
                </li>
                <li class="mb-1">
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded-2" href="/usuarios">
                        <i class="bi bi-people-fill text-primary"></i>
                        Gestión de Usuarios
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item text-danger d-flex align-items-center gap-2 py-2 rounded-2" href="/logout">
                        <i class="bi bi-box-arrow-right"></i>
                        Cerrar sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
