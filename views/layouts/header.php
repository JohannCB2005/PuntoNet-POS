<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once dirname(__DIR__, 2) . '/config/settings.php';
    require_once dirname(__DIR__, 2) . '/config/csrf.php';
    require_once dirname(__DIR__, 2) . '/config/marca.php';
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <!-- Título de página dinámico -->
    <title><?php echo isset($title) ? $title : marcaVar('nombre') . ' POS'; ?></title>
    <?php
    $faviconCfg = configuracion('LOGO_FAVICON', '');
    $faviconUrl = $faviconCfg !== '' ? $faviconCfg : 'assets/favicons/favicon-64.png?v=3';
    ?>
    <!-- Icono oficial de la aplicación -->
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(marcaLogo('LOGO_FAVICON', 'assets/favicon-nissi.svg?v=3')); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    <link rel="icon" type="image/png" sizes="128x128" href="assets/favicons/favicon-128.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/apple-touch-icon.png?v=3">
    <!-- Tipografía Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,800&display=swap" rel="stylesheet">
    <!-- Framework de Estilo Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Biblioteca de Iconos de Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2: Alertas Modales Personalizadas -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js: Generación Interactiva de Gráficos Financieros -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Diseño Estético Premium e Identidad de Marca (Aesthetics) -->
    <style>
        :root {
            --gp-primary: #23284E;          /* Navy NISSI (Marca principal) */
            --gp-primary-hover: #31386b;
            --gp-primary-light: #eef1f7;
            --gp-accent: #BD1721;           /* Rojo NISSI (acento) */
            --gp-background: #f2f4f9;       /* Fondo gris-navy suave */
            --gp-card: #ffffff;
            --gp-sidebar: #ffffff;          /* Sidebar claro (tiende a blanco) */
            --gp-sidebar-hover: #f1f3f8;
            --gp-sidebar-active: #23284E;   /* Navy de la selección (botones) */
            --gp-ink: #1d2136;              /* Tinta marina para títulos e ítems */
            --gp-item-muted: #64748b;       /* Iconos en reposo */
            --gp-scrollbar: #dbe0ea;
            --gp-text: #1f2937;
            --gp-text-muted: #6b7280;
            --gp-border: #e4e7ef;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }
        <?php echo marcaCssVars(); ?>

        body {
            font-family: var(--font-sans);
            background-color: var(--gp-background);
            color: var(--gp-text);
            overflow-x: hidden;
        }

        /* Estilos de la barra lateral (Sidebar) */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background:
                radial-gradient(120% 55% at 50% -10%, rgba(35, 40, 78, 0.10), transparent 62%),
                linear-gradient(180deg, #ffffff 0%, #f6f9ff 100%);
            color: var(--gp-text);
            z-index: 1000;
            transition: all 0.3s ease;
            border-right: 1px solid var(--gp-border);
            box-shadow: 1px 0 0 rgba(15, 23, 42, 0.03), 0 1px 0 rgba(15, 23, 42, 0.02);
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--gp-border);
        }

        .sb-brand-name {
            font-family: 'Fraunces', Georgia, serif;
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: .02em;
            color: var(--gp-primary);
            line-height: 1;
            display: block;
        }

        .sb-brand-name em {
            font-style: normal;
            color: var(--gp-accent);
        }

        .sb-brand-sub {
            display: block;
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .3em;
            text-transform: uppercase;
            color: var(--gp-text-muted);
            margin-top: 4px;
        }

        .sb-brand-img {
            max-height: 40px;
            max-width: 180px;
            object-fit: contain;
        }

        .sidebar-brand-icon {
            background-color: var(--gp-primary);
            color: #ffffff;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 6px 18px -6px rgba(35, 40, 78, 0.55);
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 20px 12px;
            scrollbar-width: thin;
            scrollbar-color: var(--gp-scrollbar) transparent;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: var(--gp-scrollbar);
            border-radius: 999px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb:hover {
            background: #c3cfe3;
        }

        /* Grupos desplegables del menú: encabezado clicable + subíndices */
        .sidebar-group {
            margin-top: 20px;
        }

        /* El primer elemento de la barra (Dashboard o el primer grupo según rol)
           no necesita separación superior. */
        .sidebar-menu > * + * {
            margin-top: 20px;
        }

        .menu-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
            margin-bottom: 6px;
            padding: 9px 12px;
            background: none;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--gp-ink);
            text-align: left;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .menu-header:hover {
            background-color: rgba(15, 23, 42, 0.04);
            color: var(--gp-ink);
        }

        .menu-header-arrow {
            font-size: 13px;
            opacity: 0.7;
            transition: transform 0.25s ease, color 0.25s ease;
        }

        /* El grupo cuyo módulo está activo también se sombrea en azul vivo con
           letras blancas, igual que el ítem seleccionado. */
        .sidebar-group.has-active > .menu-header {
            background-color: var(--gp-sidebar-active);
            color: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(35, 40, 78, 0.35);
        }

        .sidebar-group.has-active > .menu-header .menu-header-arrow {
            color: #ffffff;
            opacity: 1;
        }

        .sidebar-group.has-active > .menu-header:hover {
            background-color: var(--gp-primary-hover);
            color: #ffffff;
        }

        /* Cuerpo del grupo: se pliega/despliega con transición de altura. */
        .menu-group-items {
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.25s ease;
        }

        .sidebar-group.open .menu-group-items {
            max-height: 420px;
        }

        .sidebar-group.open .menu-header-arrow {
            transform: rotate(180deg);
        }

        /* Entrada en cascada del menú al cargar: un solo momento orquestado en
           vez de micro-efectos dispersos. Se desactiva con motion reducido. */
        @media (prefers-reduced-motion: no-preference) {
            .sidebar-menu > * {
                animation: nissi-fade 0.4s ease backwards;
            }
            .sidebar-menu > *:nth-child(1) { animation-delay: 0ms; }
            .sidebar-menu > *:nth-child(2) { animation-delay: 45ms; }
            .sidebar-menu > *:nth-child(3) { animation-delay: 90ms; }
            .sidebar-menu > *:nth-child(4) { animation-delay: 135ms; }
            .sidebar-menu > *:nth-child(5) { animation-delay: 180ms; }
            .sidebar-menu > *:nth-child(6) { animation-delay: 225ms; }
        }

        @keyframes nissi-fade {
            from {
                opacity: 0;
                transform: translateY(6px);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            border-radius: 8px;
            color: var(--gp-ink);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 2px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            position: relative;
        }

        .menu-item:hover {
            background-color: var(--gp-sidebar-hover);
            color: var(--gp-ink);
            transform: translateX(2px);
        }

        .menu-item i {
            font-size: 16px;
            color: var(--gp-item-muted);
            transition: color 0.2s ease;
        }

        .menu-item:hover i,
        .menu-item.active i {
            color: inherit;
        }

        /* El elemento seleccionado es el único que queda sombreado: azul vivo con
           letras blancas, y una "pista" vertical animada que marca la activación. */
        .menu-item.active {
            background-color: var(--gp-sidebar-active);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 8px 20px -8px rgba(35, 40, 78, 0.6);
        }

        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 58%;
            border-radius: 999px;
            background: #c8cde4;
            animation: nissi-rail 0.35s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }

        @keyframes nissi-rail {
            from {
                transform: translateY(-50%) scaleY(0.2);
                opacity: 0;
            }
            to {
                transform: translateY(-50%) scaleY(1);
                opacity: 1;
            }
        }

        .menu-item.active:hover {
            background-color: var(--gp-primary-hover);
            color: #ffffff;
        }

        /* Barra de navegación superior (Navbar) */
        .top-navbar {
            height: 64px;
            background-color: var(--gp-card);
            border-bottom: 1px solid var(--gp-border);
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            z-index: 999;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        /* Contenedor principal de contenidos */
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            flex: 1;
            min-width: 0;
            padding: 80px 16px 16px 16px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* Estados del sidebar colapsado */
        .sidebar.collapsed {
            width: 72px;
        }

        .sidebar.collapsed .brand-text, 
        .sidebar.collapsed .menu-item span,
        .sidebar.collapsed .menu-header {
            display: none;
        }

        .sidebar.collapsed .menu-item {
            justify-content: center;
            padding: 10px 0;
        }

        .sidebar.collapsed + .top-navbar {
            left: 72px;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 72px;
            width: calc(100% - 72px);
        }

        /* En modo iconos (colapsado en escritorio) no queda espacio para los
           encabezados, así que los subíndices se muestran siempre
           independientemente de si el grupo está plegado o desplegado. */
        @media (min-width: 992px) {
            .sidebar.collapsed .menu-group-items {
                max-height: none !important;
                overflow: visible;
            }
        }

        /* Tarjetas de diseño premium: borde azulado, acento superior de marca
           (inset) y sombra en dos capas para dar profundidad real. */
        .gp-card {
            background-color: var(--gp-card);
            border: 1px solid #e6ebf5;
            border-radius: 14px;
            padding: 20px;
            box-shadow: inset 0 2px 0 rgba(35, 40, 78, 0.12), 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -12px rgba(15, 23, 42, 0.10);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .gp-card:hover {
            transform: translateY(-2px);
            box-shadow: inset 0 2px 0 rgba(35, 40, 78, 0.12), 0 2px 4px rgba(15, 23, 42, 0.05), 0 12px 32px -14px rgba(15, 23, 42, 0.16);
        }

        .gp-btn-primary {
            background-color: var(--gp-primary);
            border: 1px solid var(--gp-primary);
            color: #ffffff;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 9px;
            background-image: linear-gradient(180deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0));
            box-shadow: 0 1px 2px rgba(35, 40, 78, 0.35);
            transition: transform 0.15s ease, box-shadow 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        }

        .gp-btn-primary:hover, .gp-btn-primary:focus {
            background-color: var(--gp-primary-hover);
            border-color: var(--gp-primary-hover);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px -6px rgba(35, 40, 78, 0.55);
        }

        .gp-btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px -2px rgba(35, 40, 78, 0.5);
        }

        .gp-badge-success {
            background-color: var(--gp-primary-light);
            color: var(--gp-primary);
            border: 1px solid rgba(35, 40, 78, 0.2);
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .gp-bg-primary {
            background-color: var(--gp-primary) !important;
            color: #ffffff;
        }

        .gp-badge-danger {
            background-color: #fee2e2;
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* ── Sistema compartido de módulos ──────────────────────────────────────
           Una sola piel para tablas, modales, campos y botones Bootstrap, con la
           misma dirección estética del sidebar (blanco + azul NISSI + tinta). */

        /* Tablas: cabecera con microtipografía y banda azulada; hover con tinte
           de marca para que la fila leída salte a la vista. */
        .table thead th {
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.06em;
            font-weight: 700;
            color: var(--gp-ink);
            background: linear-gradient(180deg, #fafbff, #f1f5f9);
            border-bottom: 1px solid var(--gp-border);
            white-space: nowrap;
        }

        .table > :not(caption) > * > * {
            padding: 10px 12px;
        }

        .table tbody tr {
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover td {
            background-color: #f5f8ff;
        }

        /* Modales: misma piel que las tarjetas, sin borde duro. */
        .modal-content {
            border: none;
            border-radius: 14px;
            box-shadow: 0 24px 60px -20px rgba(15, 23, 42, 0.35);
        }

        .modal-header {
            background: linear-gradient(180deg, #fafbff, #f1f5f9);
            border-bottom: 1px solid var(--gp-border);
            border-radius: 14px 14px 0 0;
        }

        .modal-header.gp-bg-primary {
            background: var(--gp-primary);
            border-bottom: none;
        }

        .modal-footer {
            border-top: 1px solid var(--gp-border);
        }

        /* Enfoque de campos con anillo de marca (no el azul genérico de Bootstrap). */
        .form-control:focus, .form-select:focus {
            border-color: var(--gp-primary);
            box-shadow: 0 0 0 3px rgba(35, 40, 78, 0.12);
        }

        /* Los botones primary de Bootstrap (21 vistas los usan) toman el azul NISSI. */
        .btn-primary {
            --bs-btn-bg: var(--gp-primary);
            --bs-btn-border-color: var(--gp-primary);
            --bs-btn-hover-bg: var(--gp-primary-hover);
            --bs-btn-hover-border-color: var(--gp-primary-hover);
            --bs-btn-active-bg: var(--gp-primary-hover);
            --bs-btn-active-border-color: var(--gp-primary-hover);
            --bs-btn-disabled-bg: var(--gp-primary);
            --bs-btn-disabled-border-color: var(--gp-primary);
        }

        /* Títulos de módulo en tinta marina, coherentes con el menú. */
        .main-content h4, .main-content h5 {
            color: var(--gp-ink);
        }

        /* Alertas con tinte de marca: pastel suave, borde fino, sin esquinas duras. */
        .alert {
            border-radius: 10px;
        }

        .alert-info {
            color: #31386b;
            background-color: #eef1f7;
            border-color: #c8cde4;
        }

        .alert-success {
            color: #166534;
            background-color: #f0fdf4;
            border-color: #bbf7d0;
        }

        .alert-warning {
            color: #92400e;
            background-color: #fffbeb;
            border-color: #fde68a;
        }

        .alert-danger {
            color: #b91c1c;
            background-color: #fef2f2;
            border-color: #fecaca;
        }

        /* Botones outline: mismo azul NISSI que los primarios. */
        .btn-outline-primary {
            --bs-btn-color: var(--gp-primary);
            --bs-btn-border-color: var(--gp-primary);
            --bs-btn-hover-bg: var(--gp-primary);
            --bs-btn-hover-border-color: var(--gp-primary);
            --bs-btn-active-bg: var(--gp-primary);
            --bs-btn-active-border-color: var(--gp-primary);
            --bs-btn-disabled-color: var(--gp-primary);
            --bs-btn-disabled-border-color: var(--gp-primary);
        }

        /* Paginación coherente con la marca. */
        .pagination .page-link {
            color: var(--gp-primary);
            border-color: var(--gp-border);
        }

        .pagination .page-link:focus {
            box-shadow: 0 0 0 3px rgba(35, 40, 78, 0.12);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--gp-primary);
            border-color: var(--gp-primary);
            color: #ffffff;
        }

        /* Campos: borde consistente y checkbox/radio con acento de marca. */
        .form-control, .form-select {
            border-color: #cbd5e1;
        }

        .form-check-input:checked {
            background-color: var(--gp-primary);
            border-color: var(--gp-primary);
        }

        /* Estados vacíos: fila diseñada, no un dato más. */
        .table td.text-muted.text-center {
            font-size: 12.5px;
            letter-spacing: 0.02em;
        }

        /* Barras de desplazamiento personalizadas */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* ── Fondo oscuro del menú lateral en móvil ──────────────────────────────
           Sin esto el sidebar se abría "flotando" sobre el contenido sin separarlo
           visualmente, y no había ninguna zona evidente donde tocar para cerrarlo. */
        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .sidebar-backdrop.show {
            opacity: 1;
            visibility: visible;
        }
        @media (min-width: 992px) {
            /* En escritorio el sidebar es fijo: el fondo nunca debe aparecer. */
            .sidebar-backdrop { display: none; }
        }

        /* ── Tablet (992px–1199px) ───────────────────────────────────────────────
           Cabe el sidebar, pero a 260px se come el espacio que necesitan las tablas.
           Se deja en modo iconos por defecto; el botón de contraer sigue funcionando
           igual y el usuario puede expandirlo cuando quiera. */
        @media (min-width: 992px) and (max-width: 1199.98px) {
            .sidebar:not(.expanded) { width: 72px; }
            .sidebar:not(.expanded) .brand-text,
            .sidebar:not(.expanded) .menu-item span,
            .sidebar:not(.expanded) .menu-header { display: none; }
            .sidebar:not(.expanded) .menu-item { justify-content: center; padding: 10px 0; }
            .sidebar:not(.expanded) .menu-group-items {
                max-height: none !important;
                overflow: visible;
            }
            .sidebar:not(.expanded) + .top-navbar { left: 72px; }
            .sidebar:not(.expanded) ~ .main-content {
                margin-left: 72px;
                width: calc(100% - 72px);
            }
        }

        /* ── Táctil (hasta 991.98px) ─────────────────────────────────────────────
           Los controles de acción de las tablas medían 24–31px de alto. La guía de
           accesibilidad pide 44px como mínimo para el dedo; se aplica solo en pantallas
           táctiles para no agrandar la interfaz de escritorio, que se usa con ratón. */
        @media (max-width: 991.98px) {
            /* Cubre también los botones sueltos de cabecera (Filtrar, Exportar…), que
               llevan `height: 31px` en un style inline; min-height gana a height. */
            .btn,
            .table .btn-link,
            .btn-link.p-1 {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            /* Los de solo icono además necesitan ancho: si no, quedan altos y finos. */
            .table .btn-link,
            .btn-link.p-1 {
                min-width: 44px;
            }
            .cat-filter-btn,
            .hist-format-btn {
                min-height: 40px;
                padding-top: 8px;
                padding-bottom: 8px;
            }
            /* Los campos por debajo de 16px hacen que iOS haga zoom automático al
               enfocarlos, descuadrando toda la pantalla. Lleva !important porque
               muchas vistas fijan el tamaño con `style` inline (font-size: 13.5px),
               y eso gana a cualquier regla de hoja de estilos por específica que sea;
               la alternativa era editar estilos inline en las 17 vistas. */
            .form-control,
            .form-select,
            .form-control-sm,
            .form-select-sm {
                font-size: 16px !important;
                min-height: 44px;
            }

            /* El botón hamburguesa quedaba en 24x34px: es el control más usado en
               móvil y hay que poder acertarle con el pulgar. */
            #mobile-toggle-sidebar {
                min-width: 44px;
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            /* Las tablas siguen con scroll horizontal (decisión tomada), pero se
               acompaña: inercia en iOS y la primera columna fija para no perder de
               vista de qué fila se está leyendo al desplazarse. */
            .table-responsive {
                -webkit-overflow-scrolling: touch;
            }
            .table-responsive > .table > thead > tr > th:first-child,
            .table-responsive > .table > tbody > tr > td:first-child {
                position: sticky;
                left: 0;
                background-color: var(--gp-card);
                z-index: 2;
                box-shadow: 1px 0 0 var(--gp-border);
            }
            .table-responsive > .table > thead > tr > th:first-child {
                z-index: 3;
            }
        }

        /* ── Móvil estrecho (hasta 575.98px) ─────────────────────────────────── */
        @media (max-width: 575.98px) {
            .main-content {
                padding-left: 10px;
                padding-right: 10px;
            }
            .gp-card { padding: 16px; }
            /* Cabeceras de módulo: título y acciones se apilan en vez de competir
               por el ancho y truncarse. */
            .gp-card h4, h4.fw-bold { font-size: 1.15rem; }
            /* Los diálogos de SweetAlert se salían del alto útil con teclado abierto. */
            .swal2-popup { width: 92vw !important; font-size: 14px; }
        }

        /* Reglas responsivas para pantallas móviles */
        @media (max-width: 991.98px) {
            .sidebar {
                left: -260px;
            }
            .sidebar.show {
                left: 0;
            }
            .top-navbar {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding-left: 12px;
                padding-right: 12px;
            }
            .sidebar.collapsed {
                left: -260px;
            }
            .sidebar.collapsed.show {
                left: 0;
                width: 260px;
            }
            .sidebar.collapsed.show .brand-text, 
            .sidebar.collapsed.show .menu-item span {
                display: inline;
            }
            .sidebar.collapsed.show .menu-header {
                display: flex;
            }
            .sidebar.collapsed.show .menu-item {
                justify-content: start;
                padding: 10px 14px;
            }
        }

        /* ── Modo "sin sidebar" (Configuración de la tienda) ────────────────────
           Oculta la barra lateral y expande navbar/contenido a todo el ancho.
           La X de cierre se pinta en la esquina superior derecha (V_configuracion). */
        body.sin-sidebar .sidebar,
        body.sin-sidebar .sidebar-backdrop {
            display: none;
        }
        body.sin-sidebar .top-navbar {
            left: 0;
        }
        body.sin-sidebar .main-content {
            margin-left: 0;
            width: 100%;
        }
        body.sin-sidebar #toggle-sidebar,
        body.sin-sidebar #mobile-toggle-sidebar {
            display: none !important;
        }
    </style>
</head>
<body class="<?php echo !empty($ocultarSidebar) ? 'sin-sidebar' : ''; ?>">
    <!-- Fondo oscuro del menú lateral en móvil (ver .sidebar-backdrop arriba). -->
    <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
    <div class="wrapper d-flex">
