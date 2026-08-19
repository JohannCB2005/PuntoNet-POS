<?php
// Tienda en Línea Pública
require_once __DIR__ . '/config/sesion_segura.php';
session_start();
require_once __DIR__ . '/config/sunat.php';
$clienteLogueado = isset($_SESSION['id_cliente']);
$clienteNombre = $_SESSION['cliente_nombre'] ?? '';

require_once __DIR__ . '/config/marca.php';
$marcaNombre   = marcaVar('nombre');
$marcaTitulo   = configuracion('TITULO_WEB', marcaVar('nombre') . ' STORE | Tienda de Uniformes');
$marcaLogo     = marcaLogo('LOGO_CLARO', 'assets/logo.svg');
$marcaFavicon  = configuracion('LOGO_FAVICON', 'assets/favicon-nissi.svg?v=3');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($marcaTitulo); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($marcaFavicon); ?>">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Tema NISSI -->
    <link rel="stylesheet" href="assets/css/tienda.css?v=4">
    <?php require_once __DIR__ . '/config/marca.php'; echo marcaCss(); ?>

    <style>
        body { padding-top: 76px; }

        /* ── Navbar ─────────────────────────────────────────── */
        .navbar { background: rgba(255,255,255,.88); backdrop-filter: blur(14px); }
        @media (max-width: 576px) {
            body { padding-top: 62px; }
            .navbar .btn { padding-left: .7rem; padding-right: .7rem; }
            .n-brand img { height: 30px; }
            .n-brand-name { font-size: 1.15rem; }
            .n-brand-sub { display: none; }
            .nav-label { display: none; }
            .navbar .dropdown-toggle::after { display: none; }
        }

        /* ── Hero editorial ─────────────────────────────────── */
        .hero {
            position: relative;
            background:
                radial-gradient(1100px 420px at 85% -10%, rgba(var(--accent-rgb),.16), transparent 60%),
                radial-gradient(800px 460px at -10% 110%, rgba(var(--navy-rgb),.18), transparent 55%),
                linear-gradient(140deg, var(--navy-900) 0%, var(--navy) 68%, #31386b 100%);
            color: #fff;
            border-radius: 24px;
            margin: 28px 0 36px;
            padding: 64px 48px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(18,21,43,.3);
            position: relative;
        }
        .hero::after {
            content: '';
            position: absolute;
            right: -60px; top: -60px;
            width: 280px; height: 280px;
            border: 1.5px solid rgba(255,255,255,.14);
            border-radius: 50%;
            background-image: radial-gradient(rgba(255,255,255,.09) 1px, transparent 1px);
            background-size: 10px 10px;
        }
        .hero-kicker {
            display: inline-flex; align-items: center; gap: 10px;
            font-size: .74rem; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
            color: #f0a3a8; margin-bottom: 18px;
        }
        .hero-kicker::before { content: ''; width: 34px; height: 2px; background: var(--accent); }
        .hero h1 {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: clamp(2rem, 4.4vw, 3.2rem);
            line-height: 1.08;
            letter-spacing: -.02em;
            max-width: 560px;
            margin: 0 0 14px;
        }
        .hero h1 em { font-style: italic; color: #f0a3a8; }
        .hero p {
            font-size: 1.02rem; line-height: 1.6; opacity: .88;
            max-width: 470px; margin: 0 0 26px; font-weight: 400;
        }
        .hero-badges { display: flex; flex-wrap: wrap; gap: 10px; position: relative; z-index: 1; }
        .hero-badges .h-badge {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
            border-radius: 999px; padding: 8px 16px;
            font-size: .82rem; font-weight: 600; color: #fff;
        }
        @media (max-width: 575.98px) {
            .hero { padding: 28px 20px; margin: 16px 0 24px; border-radius: 18px; }
            .hero h1 { font-size: clamp(1.55rem, 7vw, 2.1rem); letter-spacing: -.01em; }
            .hero p { font-size: .9rem; line-height: 1.55; margin-bottom: 18px; }
            .hero-kicker { font-size: .66rem; letter-spacing: .16em; margin-bottom: 12px; gap: 8px; }
            .hero-kicker::before { width: 24px; }
            .hero-badges { gap: 7px; }
            .hero-badges .h-badge { padding: 6px 11px; font-size: .72rem; gap: 5px; }
        }

        /* ── Barra del catálogo ────────────────────────────── */
        .catalog-head {
            display: flex; align-items: flex-end; justify-content: space-between;
            gap: 16px; margin-bottom: 24px;
        }
        .catalog-head h2 {
            font-family: var(--font-display); font-weight: 700; font-size: 1.7rem;
            margin: 0; color: var(--navy);
        }
        .catalog-head p { margin: 4px 0 0; color: var(--muted); font-size: .9rem; }

        /* ── Product Cards ─────────────────────────────────── */
        .product-card {
            background: linear-gradient(180deg, #ffffff 0%, var(--paper-2) 100%);
            border-radius: var(--radius);
            border: 1px solid var(--line);
            box-shadow: 0 1px 0 rgba(18,21,43,.02), 0 8px 24px rgba(18,21,43,.05);
            overflow: hidden;
            transition: transform .3s cubic-bezier(.16,1,.3,1), box-shadow .3s ease, border-color .3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 2px 0 rgba(18,21,43,.02), 0 18px 38px rgba(18,21,43,.09);
            border-color: var(--line-strong);
        }
        .product-img-wrap {
            height: 172px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.2rem;
            color: var(--line-strong);
            overflow: hidden;
            border-bottom: 1px solid var(--line);
            position: relative;
            padding: 16px;
        }
        .product-img-wrap img {
            max-width: 100%;
            max-height: 100%;
            width: auto; height: auto;
            object-fit: contain;
            transition: transform .6s cubic-bezier(.16,1,.3,1);
        }
        .product-card:hover .product-img-wrap img { transform: scale(1.07); }
        .product-info { padding: 20px 20px 22px; flex-grow: 1; display: flex; flex-direction: column; }
        .product-title {
            font-family: var(--font-display);
            font-weight: 650;
            font-size: 1.08rem;
            line-height: 1.25;
            margin-bottom: 2px;
            color: var(--ink);
        }
        .product-category {
            font-size: .7rem; color: var(--accent); text-transform: uppercase;
            letter-spacing: .14em; font-weight: 700; margin-bottom: 8px;
        }
        .product-price { font-size: 1.28rem; font-weight: 800; color: var(--navy); font-family: var(--font-body); }
        .product-price .from { font-size: .78rem; color: var(--muted); font-weight: 600; }
        .product-stock {
            font-size: .72rem; color: var(--navy); background: rgba(var(--navy-rgb),.07);
            padding: 3px 10px; border-radius: 999px; font-weight: 700;
        }
        .btn-add {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            background: var(--accent); color: #fff; border: none; border-radius: 999px;
            padding: 11px 16px; font-weight: 600; font-size: .9rem; width: 100%; margin-top: auto;
            transition: background .2s ease, transform .15s ease, box-shadow .2s ease;
        }
        .btn-add:hover { background: var(--accent-600); transform: translateY(-1px); box-shadow: 0 8px 18px rgba(var(--accent-rgb),.26); }
        .btn-add:active { transform: translateY(0) scale(.99); }
        .btn-add--size { background: var(--card); color: var(--navy); border: 1.5px solid var(--navy); }
        .btn-add--size:hover { background: var(--navy); color: #fff; }

        /* ── Tarjetas en móvil (2 columnas) ────────────────── */
        @media (max-width: 575.98px) {
            .product-img-wrap { height: 118px; font-size: 2.4rem; padding: 10px; }
            .product-info { padding: 12px 12px 14px; }
            .product-title { font-size: .92rem; line-height: 1.2; }
            .product-category { font-size: .62rem; letter-spacing: .1em; margin-bottom: 4px; }
            .product-price { font-size: 1.05rem; }
            .product-price .from { font-size: .68rem; }
            .product-stock { font-size: .62rem; padding: 2px 7px; }
            .btn-add { padding: 9px 10px; font-size: .78rem; gap: 5px; }
            .product-card:hover { transform: none; }
        }

        /* ── Sidebar de filtros ────────────────────────────── */
        .filtros-sidebar-inline {
            width: 288px; min-width: 288px;
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            padding: 24px 20px;
            position: sticky; top: 92px;
            max-height: calc(100vh - 112px);
            overflow-y: auto;
            transition: all .35s cubic-bezier(.16,1,.3,1);
            transform-origin: left;
        }
        .filtros-sidebar-inline.sidebar-hidden { width: 0; min-width: 0; padding: 0; border: none; overflow: hidden; margin-right: -1rem; opacity: 0; }
        /* En escritorio el offcanvas se comporta como sidebar estático */
        @media (min-width: 992px) {
            .filtros-sidebar-inline.offcanvas {
                position: sticky; top: 92px;
                width: 288px; min-width: 288px;
                max-width: none;
                max-height: calc(100vh - 112px);
                border: 1px solid var(--line);
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                transform: none !important;
                visibility: visible !important;
                background: var(--card);
                padding: 24px 20px;
                overflow-y: auto;
            }
            .filtros-sidebar-inline .offcanvas-header { display: none; }
            .filtros-sidebar-inline .offcanvas-body { padding: 0 !important; overflow: visible; }
        }
        /* Botón Filtros: oculto en escritorio, sticky en móvil/tablet */
        .btn-filtros-sticky { display: none; }
        @media (max-width: 991.98px) {
            #mainLayout { flex-direction: column; }
            .filtros-sidebar-inline {
                width: min(400px, 92vw) !important;
                min-width: 0 !important;
                position: fixed; top: 0; left: 0; bottom: 0;
                max-height: none; max-width: none;
                border-radius: 0;
                padding: 0;
                z-index: 1045;
            }
            .btn-filtros-sticky {
                display: flex; align-items: center; justify-content: center; gap: 8px;
                position: sticky; top: 74px; z-index: 5;
                width: 100%; margin-bottom: 18px;
                background: var(--card); border: 1px solid var(--line-strong);
                border-radius: 999px; padding: 11px 16px;
                font-weight: 600; color: var(--ink);
                box-shadow: var(--shadow);
                transition: border-color .2s ease, color .2s ease;
            }
            .btn-filtros-sticky:hover { border-color: var(--accent); color: var(--accent); }
        }
        .filtro-seccion { padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--line); }
        .filtro-seccion:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .filtro-seccion h6 {
            font-family: var(--font-body);
            font-weight: 700; font-size: .76rem; letter-spacing: .1em; text-transform: uppercase;
            color: var(--ink-soft); margin-bottom: 14px; display: flex; align-items: center; gap: 7px;
        }
        .filtro-seccion h6 i { color: var(--accent); }
        .categoria-option { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; cursor: pointer; }
        .categoria-option input { cursor: pointer; }

        /* Slider doble */
        .range-slider-container { position: relative; width: 100%; height: 30px; margin-top: 15px; }
        .range-slider-container input[type="range"] {
            position: absolute; width: 100%; height: 5px; background: none; pointer-events: none;
            -webkit-appearance: none; -moz-appearance: none; appearance: none; top: 50%; transform: translateY(-50%);
        }
        .range-slider-container input[type="range"]::-webkit-slider-thumb {
            height: 20px; width: 20px; border-radius: 50%; background: var(--navy);
            pointer-events: auto; -webkit-appearance: none; cursor: pointer; border: 3px solid #fff;
            box-shadow: 0 1px 4px rgba(18,21,43,.35); transition: transform .1s;
        }
        .range-slider-container input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.15); }
        .range-slider-container input[type="range"]::-moz-range-thumb {
            height: 20px; width: 20px; border-radius: 50%; background: var(--navy);
            pointer-events: auto; -moz-appearance: none; cursor: pointer; border: 3px solid #fff;
            box-shadow: 0 1px 4px rgba(18,21,43,.35); transition: transform .1s;
        }
        .range-slider-container input[type="range"]::-moz-range-thumb:hover { transform: scale(1.15); }
        .slider-track { position: absolute; width: 100%; height: 5px; background: var(--line-strong); border-radius: 3px; top: 50%; transform: translateY(-50%); z-index: 0; }

        /* Pills de filtros activos */
        .pill-filtro {
            background: var(--accent-100); color: var(--accent-600);
            border: 1px solid transparent; border-radius: 999px;
            padding: 5px 12px; font-size: .82rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 7px; transition: all .2s;
        }
        .pill-filtro:hover { background: var(--accent); color: #fff; }
        .pill-filtro button { border: none; background: none; color: inherit; padding: 0; font-size: .8rem; cursor: pointer; display: flex; align-items: center; }

        .filtros-badge { background-color: var(--accent); color: #fff; font-size: .72rem; padding: 2px 6px; border-radius: 50%; font-weight: 700; }

        /* ── Offcanvas carrito ─────────────────────────────── */
        .offcanvas { background: var(--paper); }
        .cart-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 15px 0; border-bottom: 1px dashed var(--line-strong); gap: 12px;
        }
        .cart-item-info h6 { font-size: .95rem; font-weight: 650; margin: 0; color: var(--ink); }
        .cart-item-info small { color: var(--muted); font-size: .8rem; }
        .qty-controls {
            display: flex; align-items: center; gap: 2px;
            background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 3px;
        }
        .qty-controls button {
            border: none; background: transparent; border-radius: 7px; width: 27px; height: 27px;
            display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--ink);
            transition: background .15s ease;
        }
        .qty-controls button:hover { background: var(--paper-2); }
        .qty-controls input { width: 38px; border: none; background: transparent; text-align: center; font-weight: 700; font-size: .9rem; }

        .offcanvas-cart-footer { background: var(--card); border-top: 1px solid var(--line); }

        /* ── Modal tallas + cesta en móvil ──────────────────── */
        @media (max-width: 575.98px) {
            #tallaTiendaModal .modal-dialog { margin: .5rem; }
            #tallaTiendaModal .modal-content { border-radius: 16px; }
            #tallaTiendaModal .modal-body { padding: 1rem; }
            #tallaTiendaModal .modal-footer { padding: 0 1rem 1rem; }
            .qty-controls button { width: 34px; height: 34px; }
            .qty-controls input { width: 34px; font-size: .95rem; }
            .cart-item { padding: 12px 0; }
        }

        .no-results-msg { animation: n-fade-up .4s ease-out; }

        /* ── Vista de detalle del producto (página completa) ── */
        #productoDetalle { display: none; }
        #productoDetalle.activo { display: block; animation: n-fade-up .45s cubic-bezier(.16,1,.3,1) both; }
        .detalle-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--card); border: 1px solid var(--line-strong); border-radius: 999px;
            padding: 10px 18px; font-weight: 600; font-size: .88rem; color: var(--ink);
            text-decoration: none; margin-bottom: 24px;
            transition: border-color .2s ease, color .2s ease, transform .15s ease;
        }
        .detalle-back:hover { border-color: var(--accent); color: var(--accent); transform: translateX(-3px); }
        .detalle-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 44px; align-items: start; }
        @media (max-width: 768px) {
            .detalle-layout { grid-template-columns: 1fr; gap: 28px; }
        }
        .detalle-img-wrap {
            position: relative;
            background: #ffffff;
            border: 1px solid var(--line); border-radius: var(--radius);
            min-height: 420px; display: flex; align-items: center; justify-content: center;
            padding: 32px; overflow: hidden;
        }
        .detalle-img-wrap img {
            max-width: 100%; max-height: 380px; width: auto; height: auto;
            object-fit: contain; border-radius: 8px;
        }
        .detalle-img-wrap i { font-size: 6rem; color: var(--line-strong); }
        .detalle-img-wrap .detalle-sello {
            position: absolute; top: 18px; left: 18px;
            background: var(--accent); color: #fff; font-size: .68rem; font-weight: 700;
            letter-spacing: .14em; text-transform: uppercase;
            padding: 6px 14px; border-radius: 999px;
        }
        .detalle-categoria {
            font-size: .72rem; color: var(--accent); text-transform: uppercase;
            letter-spacing: .16em; font-weight: 700; margin-bottom: 8px;
        }
        .detalle-titulo {
            font-family: var(--font-display); font-weight: 700;
            font-size: clamp(1.8rem, 3vw, 2.4rem); line-height: 1.12;
            color: var(--navy); margin-bottom: 14px;
        }
        .detalle-precio { font-size: 1.8rem; font-weight: 800; color: var(--ink); margin-bottom: 6px; }
        .detalle-precio .desde { font-size: .85rem; color: var(--muted); font-weight: 600; margin-right: 6px; }
        .detalle-stock { font-size: .85rem; font-weight: 600; margin-bottom: 26px; }
        .detalle-stock.ok { color: var(--sage-700); }
        .detalle-stock.agotado { color: var(--accent); }
        .detalle-talla-label {
            display: block; font-size: .72rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: var(--ink-soft); margin-bottom: 10px;
        }
        .detalle-tallas { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 26px; }
        .detalle-talla {
            min-width: 52px; height: 42px; border-radius: 10px; font-weight: 600; font-size: .85rem;
            border: 1.5px solid var(--line-strong); background: var(--card); color: var(--ink);
            transition: all .18s ease; cursor: pointer;
        }
        .detalle-talla:hover:not(:disabled) { border-color: var(--navy); color: var(--navy); }
        .detalle-talla.activa { background: var(--navy); border-color: var(--navy); color: #fff; box-shadow: 0 6px 16px rgba(var(--navy-rgb),.24); }
        .detalle-talla:disabled { opacity: .5; cursor: not-allowed; }
        .talla-pill-agotada { position: relative; overflow: hidden; }
        .talla-pill-agotada::after {
            content: '';
            position: absolute;
            inset: -4px;
            background: linear-gradient(to top right, transparent calc(50% - 1px), #a8adb8 calc(50% - 1px), #a8adb8 calc(50% + 1px), transparent calc(50% + 1px));
            pointer-events: none;
        }
        .detalle-cantidad-wrap { display: flex; align-items: center; gap: 14px; margin-bottom: 26px; }
        .detalle-cantidad-wrap .qty-btn {
            width: 44px; height: 44px; border-radius: 12px; border: 1.5px solid var(--line-strong);
            background: var(--card); color: var(--ink); font-size: 1.3rem; line-height: 1;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            transition: border-color .18s ease, color .18s ease;
        }
        .detalle-cantidad-wrap .qty-btn:hover { border-color: var(--accent); color: var(--accent); }
        .detalle-cantidad-wrap input {
            width: 84px; height: 44px; text-align: center; font-weight: 700; font-size: 1rem;
            border: 1.5px solid var(--line-strong); border-radius: 12px; color: var(--ink);
        }
        .detalle-add {
            width: 100%; border: none; border-radius: 999px; padding: 15px 24px;
            background: var(--accent); color: #fff; font-weight: 700; font-size: 1rem;
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            transition: background .2s ease, transform .15s ease, box-shadow .2s ease;
        }
        .detalle-add:hover:not(:disabled) { background: var(--accent-600); transform: translateY(-1px); box-shadow: 0 10px 24px rgba(var(--accent-rgb),.3); }
        .detalle-add:disabled { opacity: .55; cursor: not-allowed; }
        .detalle-info { margin-top: 26px; padding-top: 20px; border-top: 1px dashed var(--line-strong); }
        .detalle-info p { font-size: .85rem; color: var(--muted); margin: 0; }
        .detalle-info i { color: var(--accent); margin-right: 6px; }
        @media (max-width: 575.98px) {
            .detalle-layout { gap: 20px; }
            .detalle-img-wrap { min-height: 260px; padding: 18px; }
            .detalle-img-wrap img { max-height: 230px; }
            .detalle-img-wrap i { font-size: 4rem; }
            .detalle-titulo { font-size: 1.5rem; margin-bottom: 10px; }
            .detalle-precio { font-size: 1.5rem; }
            .detalle-tallas { gap: 8px; margin-bottom: 20px; }
            .detalle-talla { min-width: 46px; height: 40px; }
            .detalle-cantidad-wrap { gap: 10px; margin-bottom: 20px; }
            .detalle-cantidad-wrap .qty-btn { width: 42px; height: 42px; }
            .detalle-add { padding: 13px 20px; }
            .detalle-back { margin-bottom: 12px; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top n-nav">
        <div class="container">
            <!-- Brand / Título -->
            <a class="n-brand" href="#">
                <span>
                    <span class="n-brand-name">NISSI<em>.</em></span>
                    <span class="n-brand-sub">Uniforme escolar</span>
                </span>
            </a>

            <div class="d-flex gap-2 align-items-center">
                <?php if ($clienteLogueado): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary px-3 fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i><span class="nav-label"> <?php echo htmlspecialchars(explode(' ', $clienteNombre)[0] ?? 'Mi Cuenta'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="/mis-pedidos"><i class="bi bi-bag-check me-2"></i>Mis Pedidos</a></li>
                            <li><a class="dropdown-item" href="/mi-cuenta"><i class="bi bi-person-gear me-2"></i>Mi Cuenta</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" id="btnCerrarSesionCliente"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="/cuenta" class="btn btn-outline-primary px-3 fw-semibold">
                        <i class="bi bi-person"></i><span class="nav-label"> Iniciar sesión</span>
                    </a>
                <?php endif; ?>

                <!-- Mi Cesta -->
                <button class="btn btn-dark position-relative px-3 fw-semibold" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                    <i class="bi bi-bag-fill"></i><span class="nav-label"> Mi Cesta</span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary border border-white" id="cartBadge">0</span>
                </button>
            </div>
        </div>
    </nav>

    <div class="container n-grain">

        <!-- ── Hero editorial ── -->
        <div class="hero n-reveal" id="heroTienda">
            <div class="hero-kicker">Click &amp; Collect · Uniformes escolares</div>
            <h1>Vuelve a clases con el uniforme <em>que se ve bien.</em></h1>
            <p>Encuentra el uniforme completo de tu colegio, reserva online y recógelo en tienda cuando esté listo. Rápido, simple y seguro.</p>
            <div class="hero-badges">
                <span class="h-badge"><i class="bi bi-credit-card"></i> Paga con tarjeta o Yape</span>
                <span class="h-badge"><i class="bi bi-shop"></i> Recojo en tienda</span>
                <span class="h-badge"><i class="bi bi-mortarboard"></i> Entrega en el colegio</span>
            </div>
        </div>

        <!-- ── Encabezado del catálogo ── -->
        <div class="catalog-head n-reveal n-reveal-1" id="catalogHead">
            <div>
                <div class="n-kicker">Catálogo</div>
                <h2>Uniforme y más</h2>
                <p>Filtra por categoría, precio o busca por nombre.</p>
            </div>
            <span class="n-chip n-chip--navy"><i class="bi bi-bag-check"></i> <span id="catalogoConteo">0 productos</span></span>
        </div>

        <!-- Botón Filtros (solo móvil/tablet) -->
        <button type="button" class="btn btn-filtros-sticky" data-bs-toggle="offcanvas" data-bs-target="#filtrosSidebar">
            <i class="bi bi-funnel"></i> Filtros
            <span class="position-relative ms-1">
                <span class="badge rounded-pill bg-primary filtros-badge d-none" id="filtrosCountBadge">0</span>
            </span>
        </button>

        <!-- ── Contenedor principal: Sidebar + Catálogo ── -->
        <div class="d-flex gap-4 align-items-start" id="mainLayout">

            <!-- SIDEBAR DE FILTROS -->
            <aside id="filtrosSidebar" class="filtros-sidebar-inline offcanvas offcanvas-start" tabindex="-1" aria-labelledby="filtrosOffcanvasLabel">
                <div class="offcanvas-header border-bottom">
                    <h5 class="offcanvas-title" id="filtrosOffcanvasLabel"><i class="bi bi-funnel me-2" style="color:var(--accent);"></i>Filtros</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
                </div>
                <div class="offcanvas-body p-0 p-sm-3">

                <!-- Buscador -->
                <div class="filtro-seccion">
                    <h6><i class="bi bi-search text-primary"></i> Buscar</h6>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="filtroNombre" class="form-control border-start-0 ps-0" style="box-shadow:none;" placeholder="Nombre del producto...">
                    </div>
                </div>

                <!-- Categorías -->
                <div class="filtro-seccion">
                    <h6><i class="bi bi-tag text-primary"></i> Categorías</h6>
                    <div id="categoriasFiltroContainer">
                        <!-- Checkboxes dinámicos -->
                    </div>
                </div>

                <!-- Rango de Precios -->
                <div class="filtro-seccion">
                    <h6><i class="bi bi-cash-stack text-primary"></i> Rango de Precio</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="input-group input-group-sm" style="width:45%;">
                            <span class="input-group-text">S/</span>
                            <input type="number" id="inputMin" class="form-control" placeholder="Min" min="0">
                        </div>
                        <span class="text-muted">al</span>
                        <div class="input-group input-group-sm" style="width:45%;">
                            <span class="input-group-text">S/</span>
                            <input type="number" id="inputMax" class="form-control" placeholder="Max" min="0">
                        </div>
                    </div>
                    <div class="range-slider-container">
                        <div class="slider-track" id="sliderTrack"></div>
                        <input type="range" id="sliderMin" min="0" max="100" value="0">
                        <input type="range" id="sliderMax" min="0" max="100" value="100">
                    </div>
                </div>

                <!-- Ordenar por -->
                <div class="filtro-seccion">
                    <h6><i class="bi bi-sort-down text-primary"></i> Ordenar por</h6>
                    <div class="d-flex flex-column gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ordenarProductos" id="ordenDefault" value="default" checked>
                            <label class="form-check-label" for="ordenDefault">Relevancia</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ordenarProductos" id="ordenPrecioAsc" value="precio_asc">
                            <label class="form-check-label" for="ordenPrecioAsc">Precio: Menor a Mayor</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ordenarProductos" id="ordenPrecioDesc" value="precio_desc">
                            <label class="form-check-label" for="ordenPrecioDesc">Precio: Mayor a Menor</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ordenarProductos" id="ordenNombreAsc" value="nombre_asc">
                            <label class="form-check-label" for="ordenNombreAsc">Nombre: A-Z</label>
                        </div>
                    </div>
                </div>

                <!-- Botón Limpiar -->
                <button class="btn btn-outline-primary w-100 py-2 fw-semibold" id="btnLimpiarFiltros">
                    <i class="bi bi-trash"></i> Limpiar Filtros
                </button>

                </div><!-- /.offcanvas-body -->
            </aside>

            <!-- CATÁLOGO DE PRODUCTOS -->
            <div id="catalogoWrapper" class="flex-grow-1 w-100">

                <!-- Pills de filtros activos -->
                <div id="pillsFiltros" class="d-flex flex-wrap gap-2 mb-3 align-items-center"></div>

                <div class="row g-4 mb-4" id="catalogoContainer">
                    <!-- Spinner inicial -->
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted fw-semibold">Cargando catálogo...</p>
                    </div>
                </div>

            </div><!-- /#catalogoWrapper -->

        </div><!-- /#mainLayout -->
    </div><!-- /.container -->

    <!-- ── VISTA DE DETALLE DEL PRODUCTO (página completa) ── -->
    <div class="container n-grain" id="productoDetalle">
        <a href="#" class="detalle-back" onclick="volverCatalogo(event)"><i class="bi bi-arrow-left"></i> Volver al catálogo</a>
        <div class="detalle-layout">
            <div class="detalle-img-wrap">
                <span class="detalle-sello" id="detalleSello">Producto</span>
                <img id="detalleImagen" src="" alt="" style="display:none;">
                <i class="bi bi-box-seam" id="detalleIcono"></i>
            </div>
            <div>
                <div class="detalle-categoria" id="detalleCategoria"></div>
                <h1 class="detalle-titulo" id="detalleNombre"></h1>
                <div class="detalle-precio"><span class="desde" id="detalleDesde"></span><span id="detallePrecio"></span></div>
                <div class="detalle-stock" id="detalleStock"></div>

                <div id="detalleTallasBlock" style="display:none;">
                    <label class="detalle-talla-label"><i class="bi bi-rulers me-1"></i> Tallas disponibles</label>
                    <div class="detalle-tallas" id="detalleTallas"></div>
                </div>

                <div class="detalle-cantidad-wrap">
                    <button type="button" class="qty-btn" id="detalleCantidadDec">−</button>
                    <input type="number" id="detalleCantidad" value="1" min="1">
                    <button type="button" class="qty-btn" id="detalleCantidadInc">+</button>
                </div>

                <button class="detalle-add" id="detalleAddBtn">
                    <i class="bi bi-bag-plus"></i> Agregar a la cesta
                </button>

                <div class="detalle-info">
                    <p><i class="bi bi-shop"></i> Recojo en tienda o entrega en el colegio.</p>
                    <p class="mt-1"><i class="bi bi-credit-card"></i> Paga con tarjeta, Yape o Plin al reservar.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Selector de Talla para Tienda -->
    <div class="modal fade" id="tallaTiendaModal" tabindex="-1" aria-labelledby="tallaTiendaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
                <div class="modal-header border-0 py-3 px-4" style="background: var(--navy); border-radius: 18px 18px 0 0;">
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0" id="tallaTiendaModalLabel">Seleccionar talla</h6>
                        <small class="text-white opacity-75" id="tallaTiendaNombreProducto">Producto</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow:none;"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted mb-2" style="font-size:11px; text-transform:uppercase; letter-spacing:.1em;">Tallas disponibles</label>
                        <div id="tallaTiendaPills" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background: var(--paper-2);">
                        <i class="bi bi-tag-fill fs-4" style="color:var(--accent);"></i>
                        <div class="flex-grow-1">
                            <div class="text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.1em;">Precio / Stock</div>
                            <div class="d-flex gap-3 align-items-baseline">
                                <span class="fw-bold fs-5" style="color:var(--navy);" id="tallaTiendaPrecio">S/ 0.00</span>
                                <span class="text-muted" style="font-size:13px;" id="tallaTiendaStock">Seleccione una talla</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size:12px; text-transform:uppercase; letter-spacing:.1em;">Cantidad</label>
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-outline-secondary" id="tallaTiendaDecBtn"
                                    style="width:38px; height:38px; border-radius:10px; padding:0; font-size:18px; line-height:1;">−</button>
                            <input type="number" id="tallaTiendaCantidad" class="form-control text-center fw-bold"
                                   value="1" min="1" style="width:80px; border-radius:10px; height:38px; font-size:15px; box-shadow:none;">
                            <button type="button" class="btn btn-outline-secondary" id="tallaTiendaIncBtn"
                                    style="width:38px; height:38px; border-radius:10px; padding:0; font-size:18px; line-height:1;">+</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn fw-semibold w-100" id="tallaTiendaAddBtn" disabled
                            style="border-radius:999px; background:var(--navy); color:#fff; border:none; padding: 13px 20px;">
                        <i class="bi bi-bag-plus me-1"></i> Agregar a la cesta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Carrito -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><i class="bi bi-bag-heart me-2" style="color:var(--accent);"></i>Tu Cesta</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="flex-grow-1 overflow-auto px-4 py-2" id="cartItemsContainer">
                <div class="text-center text-muted py-5 mt-5">
                    <i class="bi bi-basket2 fs-1 mb-3 d-block" style="color:var(--line-strong);"></i>
                    <p>Tu cesta está vacía</p>
                </div>
            </div>
            <div class="p-4 mt-auto offcanvas-cart-footer">
                <div class="d-flex justify-content-between align-items-center mb-3 fw-bold fs-5">
                    <span class="text-muted" style="font-size:.95rem;">Total</span>
                    <span id="cartTotal" style="color:var(--navy); font-family:var(--font-display);">S/ 0.00</span>
                </div>
                <button class="btn btn-primary w-100 py-3 fw-bold fs-6" id="btnCheckout" disabled>
                    Proceder al pago <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <p class="text-center text-muted mt-3 mb-0" style="font-size:.74rem;">
                    <i class="bi bi-shield-lock me-1"></i>Pago seguro por tarjeta o Yape/Plin
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        
        let catalogo = [];
        let cart = [];
        try {
            const carritoGuardado = sessionStorage.getItem('nissi_cart');
            if (carritoGuardado) cart = JSON.parse(carritoGuardado) || [];
        } catch (e) { cart = []; }

        // Estado de los filtros
        let filtroNombreVal = '';
        let filtroCategoriasVal = [];
        let filtroPrecioMin = 0;
        let filtroPrecioMax = 1000;
        let precioLimiteMin = 0;
        let precioLimiteMax = 1000;
        let filtroOrdenVal = 'default';

        document.addEventListener('DOMContentLoaded', () => {
            cargarCatalogo();
            inicializarEventosFiltros();
            actualizarCarrito();

            // Al abrir el carrito, ocultar el botón Filtros (sticky) para que no
            // quede "brillante" sobre el backdrop oscuro del offcanvas.
            const cartEl = document.getElementById('cartOffcanvas');
            const btnFiltros = document.querySelector('.btn-filtros-sticky');
            if (cartEl && btnFiltros) {
                cartEl.addEventListener('show.bs.offcanvas', () => { btnFiltros.style.visibility = 'hidden'; });
                cartEl.addEventListener('hidden.bs.offcanvas', () => { btnFiltros.style.visibility = ''; });
            }

            const btnLogout = document.getElementById('btnCerrarSesionCliente');
            if (btnLogout) {
                btnLogout.addEventListener('click', async (e) => {
                    e.preventDefault();
                    await fetch('controllers/C_ClienteAuth.php?action=logout', { method: 'POST' });
                    window.location.reload();
                });
            }
        });

        async function cargarCatalogo() {
            try {
                const res = await fetch('./controllers/C_Ecommerce.php?action=catalogo_agrupado');
                const json = await res.json();
                if (json.success) {
                    catalogo = json.data;
                    calcularLimitesPrecio();
                    inicializarFiltroCategorias();
                    aplicarFiltros();
                }
            } catch (e) {
                console.error(e);
            }
        }

        function calcularLimitesPrecio() {
            if (catalogo.length === 0) return;
            const precios = catalogo.map(item => parseFloat(item.precio_desde));
            precioLimiteMin = Math.floor(Math.min(...precios));
            precioLimiteMax = Math.ceil(Math.max(...precios));
            
            // Evitar cruces o valores iguales
            if (precioLimiteMin === precioLimiteMax) {
                precioLimiteMax = precioLimiteMin + 1;
            }

            filtroPrecioMin = precioLimiteMin;
            filtroPrecioMax = precioLimiteMax;

            const inputMin = document.getElementById('inputMin');
            const inputMax = document.getElementById('inputMax');
            const sliderMin = document.getElementById('sliderMin');
            const sliderMax = document.getElementById('sliderMax');

            inputMin.min = precioLimiteMin;
            inputMin.max = precioLimiteMax;
            inputMin.value = precioLimiteMin;

            inputMax.min = precioLimiteMin;
            inputMax.max = precioLimiteMax;
            inputMax.value = precioLimiteMax;

            sliderMin.min = precioLimiteMin;
            sliderMin.max = precioLimiteMax;
            sliderMin.value = precioLimiteMin;

            sliderMax.min = precioLimiteMin;
            sliderMax.max = precioLimiteMax;
            sliderMax.value = precioLimiteMax;

            actualizarDisenoSlider();
        }

        function actualizarDisenoSlider() {
            const sliderMin = document.getElementById('sliderMin');
            const sliderMax = document.getElementById('sliderMax');
            const track = document.getElementById('sliderTrack');

            if (parseInt(sliderMin.value) > parseInt(sliderMax.value)) {
                if (document.activeElement === sliderMin) {
                    sliderMin.value = sliderMax.value;
                } else {
                    sliderMax.value = sliderMin.value;
                }
            }

            const valMin = parseInt(sliderMin.value);
            const valMax = parseInt(sliderMax.value);

            document.getElementById('inputMin').value = valMin;
            document.getElementById('inputMax').value = valMax;

            filtroPrecioMin = valMin;
            filtroPrecioMax = valMax;

            const percentMin = ((valMin - precioLimiteMin) / (precioLimiteMax - precioLimiteMin)) * 100;
            const percentMax = ((valMax - precioLimiteMin) / (precioLimiteMax - precioLimiteMin)) * 100;

            track.style.background = `linear-gradient(to right, var(--line-strong) ${percentMin}%, var(--navy) ${percentMin}%, var(--navy) ${percentMax}%, #e6e6eb ${percentMax}%)`;
        }

        function inicializarFiltroCategorias() {
            const container = document.getElementById('categoriasFiltroContainer');
            if (catalogo.length === 0) return;
            const categoriasUnicas = [...new Set(catalogo.map(item => item.categoria))];
            
            let html = '';
            categoriasUnicas.forEach(cat => {
                html += `
                    <div class="categoria-option">
                        <div class="form-check w-100">
                            <input class="form-check-input filter-category-checkbox" type="checkbox" value="${cat}" id="chkCat_${cat}">
                            <label class="form-check-label w-100" for="chkCat_${cat}">
                                ${cat}
                            </label>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;

            document.querySelectorAll('.filter-category-checkbox').forEach(chk => {
                chk.addEventListener('change', () => {
                    filtroCategoriasVal = Array.from(document.querySelectorAll('.filter-category-checkbox:checked')).map(c => c.value);
                    aplicarFiltros();
                });
            });
        }

        function inicializarEventosFiltros() {
            document.getElementById('filtroNombre').addEventListener('input', (e) => {
                filtroNombreVal = e.target.value.trim().toLowerCase();
                aplicarFiltros();
            });

            const inputMin = document.getElementById('inputMin');
            const inputMax = document.getElementById('inputMax');
            const sliderMin = document.getElementById('sliderMin');
            const sliderMax = document.getElementById('sliderMax');

            inputMin.addEventListener('change', (e) => {
                let val = Math.max(precioLimiteMin, Math.min(precioLimiteMax, parseInt(e.target.value) || 0));
                if (val > filtroPrecioMax) val = filtroPrecioMax;
                inputMin.value = val;
                sliderMin.value = val;
                actualizarDisenoSlider();
                aplicarFiltros();
            });

            inputMax.addEventListener('change', (e) => {
                let val = Math.max(precioLimiteMin, Math.min(precioLimiteMax, parseInt(e.target.value) || 0));
                if (val < filtroPrecioMin) val = filtroPrecioMin;
                inputMax.value = val;
                sliderMax.value = val;
                actualizarDisenoSlider();
                aplicarFiltros();
            });

            sliderMin.addEventListener('input', () => {
                actualizarDisenoSlider();
                aplicarFiltros();
            });

            sliderMax.addEventListener('input', () => {
                actualizarDisenoSlider();
                aplicarFiltros();
            });

            document.querySelectorAll('input[name="ordenarProductos"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    filtroOrdenVal = e.target.value;
                    aplicarFiltros();
                });
            });

            document.getElementById('btnLimpiarFiltros').addEventListener('click', limpiarFiltros);
        }

        function limpiarFiltros() {
            filtroNombreVal = '';
            document.getElementById('filtroNombre').value = '';

            document.querySelectorAll('.filter-category-checkbox').forEach(chk => chk.checked = false);
            filtroCategoriasVal = [];

            filtroPrecioMin = precioLimiteMin;
            filtroPrecioMax = precioLimiteMax;
            document.getElementById('inputMin').value = precioLimiteMin;
            document.getElementById('inputMax').value = precioLimiteMax;
            document.getElementById('sliderMin').value = precioLimiteMin;
            document.getElementById('sliderMax').value = precioLimiteMax;
            actualizarDisenoSlider();

            document.getElementById('ordenDefault').checked = true;
            filtroOrdenVal = 'default';

            aplicarFiltros();
        }

        function aplicarFiltros() {
            let productos = [...catalogo];

            // 1. Filtrar por nombre
            if (filtroNombreVal !== '') {
                productos = productos.filter(p => p.nombre.toLowerCase().includes(filtroNombreVal));
            }

            // 2. Filtrar por categorías
            if (filtroCategoriasVal.length > 0) {
                productos = productos.filter(p => filtroCategoriasVal.includes(p.categoria));
            }

            // 3. Filtrar por precio
            productos = productos.filter(p => {
                const precio = parseFloat(p.precio_desde);
                return precio >= filtroPrecioMin && precio <= filtroPrecioMax;
            });

            // 4. Ordenamiento. Los productos con stock disponible SIEMPRE van primero,
            //    sea cual sea la opción elegida; el criterio secundario ordena al resto.
            const porDisponible = (a, b) => (b.disponible ? 1 : 0) - (a.disponible ? 1 : 0);
            if (filtroOrdenVal === 'precio_asc') {
                productos.sort((a, b) => porDisponible(a, b) || (parseFloat(a.precio_desde) - parseFloat(b.precio_desde)));
            } else if (filtroOrdenVal === 'precio_desc') {
                productos.sort((a, b) => porDisponible(a, b) || (parseFloat(b.precio_desde) - parseFloat(a.precio_desde)));
            } else if (filtroOrdenVal === 'nombre_asc') {
                productos.sort((a, b) => porDisponible(a, b) || a.nombre.localeCompare(b.nombre));
            }

            renderProductosCatalogo(productos);
            actualizarBadgesYFiltrosPills();
        }

        function actualizarBadgesYFiltrosPills() {
            let totalFiltrosActivos = 0;
            const containerPills = document.getElementById('pillsFiltros');
            let htmlPills = '';

            if (filtroNombreVal !== '') {
                totalFiltrosActivos++;
                htmlPills += `
                    <div class="pill-filtro">
                        Buscar: "${filtroNombreVal}"
                        <button onclick="removerFiltroNombre()"><i class="bi bi-x-circle-fill"></i></button>
                    </div>
                `;
            }

            if (filtroCategoriasVal.length > 0) {
                totalFiltrosActivos += filtroCategoriasVal.length;
                filtroCategoriasVal.forEach(cat => {
                    htmlPills += `
                        <div class="pill-filtro">
                            Categoría: ${cat}
                            <button onclick="removerFiltroCategoria('${cat}')"><i class="bi bi-x-circle-fill"></i></button>
                        </div>
                    `;
                });
            }

            if (filtroPrecioMin > precioLimiteMin || filtroPrecioMax < precioLimiteMax) {
                totalFiltrosActivos++;
                htmlPills += `
                    <div class="pill-filtro">
                        Precio: S/ ${filtroPrecioMin} - S/ ${filtroPrecioMax}
                        <button onclick="removerFiltroPrecio()"><i class="bi bi-x-circle-fill"></i></button>
                    </div>
                `;
            }

            if (filtroOrdenVal !== 'default') {
                totalFiltrosActivos++;
                let labelOrden = 'Relevancia';
                if (filtroOrdenVal === 'precio_asc') labelOrden = 'Precio ↑';
                if (filtroOrdenVal === 'precio_desc') labelOrden = 'Precio ↓';
                if (filtroOrdenVal === 'nombre_asc') labelOrden = 'Nombre A-Z';
                
                htmlPills += `
                    <div class="pill-filtro">
                        Orden: ${labelOrden}
                        <button onclick="removerFiltroOrden()"><i class="bi bi-x-circle-fill"></i></button>
                    </div>
                `;
            }

            if (totalFiltrosActivos > 0) {
                htmlPills += `
                    <button class="btn btn-link btn-sm text-decoration-none text-muted fw-bold ps-1" onclick="limpiarFiltros()">
                        Limpiar todos
                    </button>
                `;
            }

            containerPills.innerHTML = htmlPills;

            const badge = document.getElementById('filtrosCountBadge');
            if (badge) {
                if (totalFiltrosActivos > 0) {
                    badge.innerText = totalFiltrosActivos;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            }
        }

        window.removerFiltroNombre = () => {
            filtroNombreVal = '';
            document.getElementById('filtroNombre').value = '';
            aplicarFiltros();
        };

        window.removerFiltroCategoria = (cat) => {
            const chk = document.getElementById(`chkCat_${cat}`);
            if (chk) chk.checked = false;
            filtroCategoriasVal = filtroCategoriasVal.filter(c => c !== cat);
            aplicarFiltros();
        };

        window.removerFiltroPrecio = () => {
            filtroPrecioMin = precioLimiteMin;
            filtroPrecioMax = precioLimiteMax;
            document.getElementById('inputMin').value = precioLimiteMin;
            document.getElementById('inputMax').value = precioLimiteMax;
            document.getElementById('sliderMin').value = precioLimiteMin;
            document.getElementById('sliderMax').value = precioLimiteMax;
            actualizarDisenoSlider();
            aplicarFiltros();
        };

        window.removerFiltroOrden = () => {
            document.getElementById('ordenDefault').checked = true;
            filtroOrdenVal = 'default';
            aplicarFiltros();
        };

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
        }

        window.agregarItemDirectoBtn = (btn) => {
            agregarItemDirecto(
                parseInt(btn.dataset.id),
                btn.dataset.nombre,
                parseFloat(btn.dataset.precio),
                parseInt(btn.dataset.stock),
                ''
            );
        };

        /* ═══ Vista de detalle del producto (página completa) ═══ */
        let productoDetalleActual = null;
        let tallaDetalleSeleccionada = null;

        window.abrirProducto = (item) => {
            productoDetalleActual = item;
            tallaDetalleSeleccionada = null;

            document.getElementById('heroTienda').style.display = 'none';
            document.getElementById('catalogHead').style.display = 'none';
            document.getElementById('mainLayout').style.display = 'none';
            document.getElementById('productoDetalle').classList.add('activo');
            document.querySelector('.container.n-grain').scrollTo?.({ top: 0, behavior: 'smooth' });
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Imagen
            const img = document.getElementById('detalleImagen');
            const icono = document.getElementById('detalleIcono');
            if (item.imagen && item.imagen !== '' && item.imagen !== 'null') {
                img.src = 'assets/productos/' + encodeURIComponent(item.imagen);
                img.style.display = '';
                icono.style.display = 'none';
            } else {
                let ic = 'bi-box-seam';
                const cat = (item.categoria || '').toLowerCase();
                if (cat.includes('polo') || cat.includes('camisa') || cat.includes('uniforme')) ic = 'bi-person-badge';
                else if (cat.includes('zapato') || cat.includes('calzado')) ic = 'bi-caret-down-square';
                else if (cat.includes('mochila') || cat.includes('útil') || cat.includes('util')) ic = 'bi-backpack';
                else if (cat.includes('accesorio')) ic = 'bi-bounding-box-circles';
                img.style.display = 'none';
                icono.className = 'bi ' + ic;
                icono.style.display = '';
            }

            document.getElementById('detalleCategoria').textContent = item.categoria;
            document.getElementById('detalleNombre').textContent = item.nombre;
            document.getElementById('detallePrecio').textContent = 'S/ ' + parseFloat(item.precio_desde).toFixed(2);
            document.getElementById('detalleDesde').textContent = item.tiene_tallas ? 'Desde' : '';
            document.getElementById('detalleCantidad').value = 1;

            const blockTallas = document.getElementById('detalleTallasBlock');
            const contTallas = document.getElementById('detalleTallas');
            contTallas.innerHTML = '';

            if (item.tiene_tallas) {
                blockTallas.style.display = '';
                item.variantes.forEach(v => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'detalle-talla' + (v.stock <= 0 ? '' : '');
                    btn.textContent = v.talla;
                    btn.disabled = v.stock <= 0;
                    btn.title = v.stock <= 0 ? 'Sin stock' : (v.stock + ' disponibles');
                    btn.addEventListener('click', () => {
                        tallaDetalleSeleccionada = v;
                        contTallas.querySelectorAll('.detalle-talla').forEach(b => b.classList.remove('activa'));
                        btn.classList.add('activa');
                        document.getElementById('detallePrecio').textContent = 'S/ ' + parseFloat(v.precio).toFixed(2);
                        document.getElementById('detalleStock').textContent = v.stock <= 0 ? 'Agotado' : (v.stock + ' disponibles');
                        document.getElementById('detalleStock').className = 'detalle-stock ' + (v.stock <= 0 ? 'agotado' : 'ok');
                        document.getElementById('detalleCantidad').max = v.stock;
                        document.getElementById('detalleCantidad').value = 1;
                        document.getElementById('detalleAddBtn').disabled = v.stock <= 0;
                    });
                    contTallas.appendChild(btn);
                });
                const disponible = item.disponible;
                document.getElementById('detalleStock').textContent = disponible ? (item.stock_total + ' disponibles en total') : 'Agotado';
                document.getElementById('detalleStock').className = 'detalle-stock ' + (disponible ? 'ok' : 'agotado');
                document.getElementById('detalleAddBtn').disabled = true;
                if (item.variantes.length === 1) {
                    contTallas.querySelector('.detalle-talla')?.click();
                }
            } else {
                blockTallas.style.display = 'none';
                const v = item.variantes[0];
                const agotado = !v.stock_ilimitado && v.stock <= 0;
                document.getElementById('detalleStock').textContent = v.stock_ilimitado ? 'Stock ilimitado' : (agotado ? 'Agotado' : (v.stock + ' disponibles'));
                document.getElementById('detalleStock').className = 'detalle-stock ' + (agotado ? 'agotado' : 'ok');
                document.getElementById('detalleCantidad').max = v.stock_ilimitado ? 999 : v.stock;
                document.getElementById('detalleAddBtn').disabled = agotado;
            }
        };

        window.volverCatalogo = (e) => {
            if (e) e.preventDefault();
            document.getElementById('heroTienda').style.display = '';
            document.getElementById('catalogHead').style.display = '';
            document.getElementById('mainLayout').style.display = '';
            document.getElementById('productoDetalle').classList.remove('activo');
            productoDetalleActual = null;
            tallaDetalleSeleccionada = null;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        document.getElementById('detalleCantidadDec')?.addEventListener('click', () => {
            const input = document.getElementById('detalleCantidad');
            input.value = Math.max(1, (parseInt(input.value) || 1) - 1);
        });
        document.getElementById('detalleCantidadInc')?.addEventListener('click', () => {
            const input = document.getElementById('detalleCantidad');
            const max = parseInt(input.max) || 999;
            input.value = Math.min(max, (parseInt(input.value) || 1) + 1);
        });

        document.getElementById('detalleAddBtn')?.addEventListener('click', () => {
            if (!productoDetalleActual) return;
            const qty = Math.max(1, parseInt(document.getElementById('detalleCantidad').value) || 1);

            if (productoDetalleActual.tiene_tallas) {
                if (!tallaDetalleSeleccionada) {
                    Swal.fire({ icon: 'warning', title: 'Selecciona una talla', text: 'Elige la talla que necesitas para continuar.' });
                    return;
                }
                const v = tallaDetalleSeleccionada;
                agregarItemDirecto(v.id_producto, `${productoDetalleActual.nombre} (T-${v.talla})`, v.precio, v.stock, 'Und', qty);
            } else {
                const v = productoDetalleActual.variantes[0];
                agregarItemDirecto(v.id_producto, productoDetalleActual.nombre, v.precio, v.stock_ilimitado ? 999 : v.stock, 'Und', qty);
            }
            volverCatalogo();
        });

        function renderProductosCatalogo(productos) {
            const container = document.getElementById('catalogoContainer');
            if (productos.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted no-results-msg">
                        <i class="bi bi-search-heart fs-1 mb-3 d-block text-primary"></i>
                        <p class="fw-semibold">No se encontraron productos con los filtros seleccionados.</p>
                        <button class="btn btn-primary btn-sm mt-2 fw-semibold px-3 rounded-pill" onclick="limpiarFiltros()">Limpiar Filtros</button>
                    </div>
                `;
                return;
            }

            let html = '';
            productos.forEach(item => {
                let imgHtml = '';
                if (item.imagen && item.imagen !== '' && item.imagen !== 'null') {
                    imgHtml = `<img src="assets/productos/${encodeURIComponent(item.imagen)}" alt="${escapeHtml(item.nombre)}">`;
                } else {
                    let icon = 'bi-box-seam';
                    let cat = item.categoria.toLowerCase();
                    if (cat.includes('ave') || cat.includes('pavo')) icon = 'bi-twitter';
                    else if (cat.includes('huevo')) icon = 'bi-egg-fill';
                    else if (cat.includes('lácteo') || cat.includes('leche')) icon = 'bi-droplet-fill';
                    imgHtml = `<i class="bi ${icon}"></i>`;
                }

                if (item.tiene_tallas) {
                    const agotadoTallas = !item.disponible;
                    const stockHtmlTallas = agotadoTallas
                        ? '<div class="product-stock" style="background:#eef2f6;color:#6b7f92"><i class="bi bi-x-circle me-1"></i>Agotado</div>'
                        : `<div class="product-stock" style="color:#23284E"><i class="bi bi-rulers"></i> ${item.variantes.length} tallas</div>`;
                    const btnTallas = agotadoTallas
                        ? `<button class="btn-add btn-add--size" disabled style="background:#eef2f6;color:#8ba0b2;border-color:#e2eaf2;cursor:not-allowed;"><i class="bi bi-bag-x me-1"></i> Agotado</button>`
                        : `<button class="btn-add btn-add--size" onclick='event.stopPropagation(); abrirModalTallasTienda(${JSON.stringify(item).replace(/'/g, "&#39;")})'>
                            <i class="bi bi-rulers me-1"></i> Elegir talla
                        </button>`;
                    html += `
                        <div class="col-6 col-lg-3">
                            <div class="product-card" role="button" onclick='abrirProducto(${JSON.stringify(item).replace(/'/g, "&#39;")})'>
                                <div class="product-img-wrap">
                                    ${imgHtml}
                                </div>
                                <div class="product-info">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="product-category">${escapeHtml(item.categoria)}</div>
                                        ${stockHtmlTallas}
                                    </div>
                                    <h3 class="product-title">${escapeHtml(item.nombre)}</h3>
                                    <div class="mt-auto pt-3">
                                        <div class="product-price mb-3">
                                            <span class="fs-6 text-muted fw-normal me-1">Desde</span>S/ ${parseFloat(item.precio_desde).toFixed(2)}
                                        </div>
                                        ${btnTallas}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // Producto simple
                    const v = item.variantes[0];
                    const agotadoSimple = !v.stock_ilimitado && v.stock <= 0;
                    const stockHtmlSimple = v.stock_ilimitado
                        ? '<div class="product-stock" style="background:#e0f1ef;color:#2d7168"><i class="bi bi-infinity me-1"></i>Stock ilimitado</div>'
                        : agotadoSimple
                            ? '<div class="product-stock" style="background:#eef2f6;color:#6b7f92"><i class="bi bi-x-circle me-1"></i>Agotado</div>'
                            : `<div class="product-stock" style="background:rgba(var(--navy-rgb),.07);color:#23284E">${v.stock} disp.</div>`;
                    const btnSimple = agotadoSimple
                        ? `<button class="btn-add" disabled style="background:#eef2f6;color:#8ba0b2;border-color:#e2eaf2;cursor:not-allowed;"><i class="bi bi-bag-x me-1"></i> Agotado</button>`
                        : `<button class="btn-add" data-id="${v.id_producto}" data-nombre="${escapeHtml(item.nombre)}" data-precio="${v.precio}" data-stock="${v.stock}" onclick="event.stopPropagation(); agregarItemDirectoBtn(this)">
                            <i class="bi bi-bag-plus me-1"></i> Añadir a la cesta
                        </button>`;
                    html += `
                        <div class="col-6 col-lg-3">
                            <div class="product-card" role="button" onclick='abrirProducto(${JSON.stringify(item).replace(/'/g, "&#39;")})'>
                                <div class="product-img-wrap">
                                    ${imgHtml}
                                </div>
                                <div class="product-info">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="product-category">${escapeHtml(item.categoria)}</div>
                                        ${stockHtmlSimple}
                                    </div>
                                    <h3 class="product-title">${escapeHtml(item.nombre)}</h3>
                                    <div class="mt-auto pt-3">
                                        <div class="product-price mb-3">S/ ${parseFloat(item.precio_desde).toFixed(2)}</div>
                                        ${btnSimple}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            container.innerHTML = html;
            const conteo = document.getElementById('catalogoConteo');
            if (conteo) conteo.textContent = productos.length + (productos.length === 1 ? ' producto' : ' productos');
        }

        let modalTallaTiendaInstance = null;
        let tallaSeleccionadaTienda = null;

        window.abrirModalTallasTienda = (producto) => {
            if (!modalTallaTiendaInstance) {
                modalTallaTiendaInstance = new bootstrap.Modal(document.getElementById('tallaTiendaModal'));
            }
            document.getElementById('tallaTiendaNombreProducto').textContent = producto.nombre;
            tallaSeleccionadaTienda = null;
            document.getElementById('tallaTiendaCantidad').value = 1;
            document.getElementById('tallaTiendaPrecio').textContent = 'S/ 0.00';
            document.getElementById('tallaTiendaStock').textContent = 'Seleccione una talla';
            document.getElementById('tallaTiendaAddBtn').disabled = true;

            const pillsContainer = document.getElementById('tallaTiendaPills');
            pillsContainer.innerHTML = '';
            
            producto.variantes.forEach(v => {
                const btn = document.createElement('button');
                btn.className = 'btn fw-semibold';
                btn.textContent = v.talla;
                btn.style.cssText = 'min-width:48px; height:38px; border-radius:8px; font-size:13px; border:1.5px solid #e5e7eb; background:#f3f4f6; color:#374151;';

                if (v.stock <= 0) {
                    btn.disabled = true;
                    btn.title = 'Agotado';
                    btn.className = 'btn fw-semibold talla-pill-agotada';
                    btn.style.opacity = '.45';
                    btn.style.color = '#9ca3af';
                    btn.style.cursor = 'not-allowed';
                    pillsContainer.appendChild(btn);
                    return;
                }
                
                btn.addEventListener('click', () => {
                    tallaSeleccionadaTienda = {
                        id_producto: v.id_producto,
                        nombre: `${producto.nombre} (T-${v.talla})`,
                        precio: v.precio,
                        stock: v.stock,
                        unidad: 'Und'
                    };

                    Array.from(pillsContainer.children).forEach(b => {
                        if (b.disabled) return;
                        b.style.background = '#f3f4f6';
                        b.style.color = '#374151';
                        b.style.borderColor = '#e5e7eb';
                    });
                    btn.style.background = 'var(--navy)';
                    btn.style.color = '#fff';
                    btn.style.borderColor = 'var(--navy)';

                    const agotadoTalla = v.stock <= 0;
                    document.getElementById('tallaTiendaPrecio').textContent = 'S/ ' + parseFloat(v.precio).toFixed(2);
                    document.getElementById('tallaTiendaStock').textContent = agotadoTalla ? 'Agotado' : (v.stock + ' disponibles');
                    document.getElementById('tallaTiendaStock').style.color = agotadoTalla ? '#BD1721' : '';
                    document.getElementById('tallaTiendaCantidad').max = v.stock;
                    document.getElementById('tallaTiendaCantidad').value = 1;
                    document.getElementById('tallaTiendaAddBtn').disabled = agotadoTalla;
                });
                
                pillsContainer.appendChild(btn);
            });
            
            modalTallaTiendaInstance.show();
        };

        document.addEventListener('DOMContentLoaded', () => {
            const decBtn = document.getElementById('tallaTiendaDecBtn');
            const incBtn = document.getElementById('tallaTiendaIncBtn');
            const addBtn = document.getElementById('tallaTiendaAddBtn');
            const qtyInput = document.getElementById('tallaTiendaCantidad');

            if(decBtn) {
                decBtn.addEventListener('click', () => {
                    qtyInput.value = Math.max(1, parseInt(qtyInput.value) - 1);
                });
                incBtn.addEventListener('click', () => {
                    if(!tallaSeleccionadaTienda) return;
                    qtyInput.value = Math.min(tallaSeleccionadaTienda.stock, parseInt(qtyInput.value) + 1);
                });
                addBtn.addEventListener('click', () => {
                    if(!tallaSeleccionadaTienda) return;
                    const qty = parseInt(qtyInput.value) || 1;
                    // Llama a la lógica directa pasandole la cantidad preestablecida en vez de sumarle +1 siempre
                    agregarItemDirecto(
                        tallaSeleccionadaTienda.id_producto, 
                        tallaSeleccionadaTienda.nombre, 
                        tallaSeleccionadaTienda.precio, 
                        tallaSeleccionadaTienda.stock, 
                        tallaSeleccionadaTienda.unidad,
                        qty
                    );
                    modalTallaTiendaInstance.hide();
                });
            }
        });

        window.agregarItemDirecto = (id_producto, nombre, precio, stock_max, unidad, qty = 1) => {
            const existing = cart.find(i => i.id_producto === id_producto);
            if (existing) {
                if (existing.cantidad + qty > stock_max) {
                    Swal.fire({ icon: 'warning', text: 'Stock máximo alcanzado para este producto' });
                    return;
                }
                existing.cantidad += qty;
                existing.subtotal = existing.cantidad * precio;
            } else {
                if (qty > stock_max) {
                    Swal.fire({ icon: 'warning', text: 'Stock insuficiente' });
                    return;
                }
                cart.push({
                    id_producto: id_producto,
                    nombre: nombre,
                    precio: parseFloat(precio),
                    cantidad: qty,
                    peso_neto: qty,
                    stock_max: parseInt(stock_max),
                    subtotal: qty * parseFloat(precio),
                    unidad: unidad || 'Und',
                    es_pesado: false
                });
            }
            actualizarCarrito();
            
            const offcanvas = new bootstrap.Offcanvas(document.getElementById('cartOffcanvas'));
            offcanvas.show();
        };

        window.modificarCart = (id, change) => {
            const item = cart.find(i => i.id_producto == id);
            if (!item) return;

            let newQty = item.cantidad + change;
            if (newQty <= 0) {
                cart = cart.filter(i => i.id_producto != id);
            } else {
                if (newQty > item.stock_max) {
                    Swal.fire({ icon: 'warning', text: 'Stock máximo alcanzado' });
                    return;
                }
                item.cantidad = newQty;
                let unitPeso = item.peso_neto / (item.cantidad - change);
                item.peso_neto = newQty * unitPeso;
                item.subtotal = (item.es_pesado ? item.peso_neto : item.cantidad) * item.precio;
            }
            actualizarCarrito();
        };

        function actualizarCarrito() {
            const container = document.getElementById('cartItemsContainer');
            const badge = document.getElementById('cartBadge');
            const totalEl = document.getElementById('cartTotal');
            const btnCheckout = document.getElementById('btnCheckout');

            let qtyTotal = 0;
            let sumTotal = 0;
            let html = '';

            cart.forEach(item => {
                qtyTotal += item.cantidad;
                sumTotal += item.subtotal;

                let extraInfo = item.es_pesado ? `<br><small class="text-primary">Peso aprox: ${item.peso_neto} Kg</small>` : '';

                html += `
                    <div class="cart-item">
                        <div class="cart-item-info pe-2">
                            <h6 class="text-truncate" style="max-width: 150px;">${escapeHtml(item.nombre)}</h6>
                            <small>S/ ${item.precio.toFixed(2)} ${item.es_pesado ? 'x Kg' : '/ ' + item.unidad}</small>
                            ${extraInfo}
                            <div class="fw-bold mt-1 text-dark">S/ ${item.subtotal.toFixed(2)}</div>
                        </div>
                        <div class="qty-controls">
                            <button onclick="modificarCart(${item.id_producto}, -1)"><i class="bi bi-dash"></i></button>
                            <input type="text" value="${item.cantidad}" readonly>
                            <button onclick="modificarCart(${item.id_producto}, 1)"><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                `;
            });

            if (cart.length === 0) {
                html = `
                    <div class="text-center text-muted py-5 mt-5">
                        <i class="bi bi-basket2 fs-1 mb-3 d-block"></i>
                        <p>Tu cesta está vacía</p>
                    </div>
                `;
                btnCheckout.disabled = true;
            } else {
                btnCheckout.disabled = false;
            }

            container.innerHTML = html;
            badge.innerText = qtyTotal;
            totalEl.innerText = `S/ ${sumTotal.toFixed(2)}`;

            sessionStorage.setItem('nissi_cart', JSON.stringify(cart));
        }

        // Checkout Logic — redirect to checkout (reorganizado en views/public/)
        document.getElementById('btnCheckout').addEventListener('click', () => {
            if (cart.length === 0) return;
            sessionStorage.setItem('nissi_cart', JSON.stringify(cart));
            window.location.href = '/compra';
        });
    </script>
</body>
</html>
