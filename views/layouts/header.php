<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Título de página dinámico -->
    <title><?php echo isset($title) ? $title : 'NISSI POS'; ?></title>
    <!-- Icono oficial de la aplicación -->
    <link rel="icon" type="image/png" sizes="64x64" href="assets/favicons/favicon-64.png">
    <link rel="icon" type="image/png" sizes="128x128" href="assets/favicons/favicon-128.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/apple-touch-icon.png">
    <!-- Tipografía Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            --gp-primary: #1d4ed8;        /* Azul NISSI (Marca principal) */
            --gp-primary-hover: #1e40af;
            --gp-primary-light: #eff6ff;
            --gp-background: #f0f4ff;     /* Fondo azulado suave */
            --gp-card: #ffffff;
            --gp-sidebar: #0f172a;        /* Navy oscuro */
            --gp-sidebar-hover: #1e293b;
            --gp-sidebar-active: #1d4ed8;
            --gp-text: #1f2937;
            --gp-text-muted: #6b7280;
            --gp-border: #e2e8f0;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }

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
            background-color: var(--gp-sidebar);
            color: #ffffff;
            z-index: 1000;
            transition: all 0.3s ease;
            border-right: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
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
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 20px 12px;
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
            letter-spacing: 0.5px;
            color: rgba(255,255,255,0.35);
            text-align: left;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .menu-header:hover {
            background-color: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.8);
        }

        .menu-header-arrow {
            font-size: 13px;
            opacity: 0.7;
            transition: transform 0.25s ease;
        }

        /* El grupo cuyo módulo está activo se sombrea para indicar la selección,
           igual que resalta un elemento del menú. */
        .sidebar-group.has-active > .menu-header {
            background-color: rgba(29, 78, 216, 0.3);
            color: #ffffff;
        }

        .sidebar-group.has-active > .menu-header:hover {
            background-color: rgba(29, 78, 216, 0.38);
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

        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 2px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .menu-item:hover {
            background-color: var(--gp-sidebar-hover);
            color: #ffffff;
        }

        /* El elemento seleccionado solo resalta las letras (el sombreado de la
           selección lo lleva la categoría a la que pertenece). */
        .menu-item.active {
            background-color: transparent;
            color: #60a5fa;
            font-weight: 700;
        }

        .menu-item.active:hover {
            background-color: var(--gp-sidebar-hover);
            color: #60a5fa;
        }

        .menu-item i {
            font-size: 16px;
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

        /* Tarjetas de diseño premium */
        .gp-card {
            background-color: var(--gp-card);
            border: 1px solid var(--gp-border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 1px 2px rgba(0,0,0,0.01);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .gp-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
        }

        .gp-btn-primary {
            background-color: var(--gp-primary);
            border-color: var(--gp-primary);
            color: #ffffff;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .gp-btn-primary:hover, .gp-btn-primary:focus {
            background-color: var(--gp-primary-hover);
            border-color: var(--gp-primary-hover);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(29, 78, 216, 0.25);
        }

        .gp-badge-success {
            background-color: var(--gp-primary-light);
            color: var(--gp-primary);
            border: 1px solid rgba(29, 78, 216, 0.2);
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
    </style>
</head>
<body>
    <!-- Fondo oscuro del menú lateral en móvil (ver .sidebar-backdrop arriba). -->
    <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
    <div class="wrapper d-flex">
