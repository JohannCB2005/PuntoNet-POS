<?php
// Tienda en Línea Pública
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NISSI STORE | Tienda de Uniformes</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/Logo navegador PuntoNet.png">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #1d4ed8;
            --bg-main: #f0f4ff;
            --primary-hover: #1e40af;
            --secondary: #f3f4f6;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-dark);
            padding-top: 70px;
        }
        /* Navbar */
        .navbar {
            background-color: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .navbar-brand {
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--primary) !important;
        }
        
        /* Hero Section */
        .hero {
            position: relative;
            background-image: url('assets/tienda_hero.png');
            background-size: cover;
            background-position: center;
            padding: 80px 20px;
            color: white;
            border-radius: 20px;
            margin: 20px 0;
            box-shadow: 0 10px 25px rgba(29, 78, 216, 0.15);
            text-align: center;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(29, 78, 216, 0.85) 0%, rgba(30, 64, 175, 0.9) 100%);
            z-index: 1;
        }
        .hero h1, .hero p {
            position: relative;
            z-index: 2;
        }
        .hero h1 { font-weight: 800; letter-spacing: -1px; margin-bottom: 15px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .hero p { font-size: 1.1rem; opacity: 0.9; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }

        /* Product Cards */
        .product-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
            border-color: #d1d5db;
        }
        .product-img-wrap {
            height: 160px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #9ca3af;
            overflow: hidden;
            border-bottom: 1px solid #f3f4f6;
            position: relative;
            padding: 12px;
        }
        .product-img-wrap img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .product-card:hover .product-img-wrap img {
            transform: scale(1.08);
        }
        .product-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .product-title { font-weight: 700; font-size: 1.1rem; margin-bottom: 5px; color: var(--text-dark); }
        .product-category { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 15px; }
        .product-price { font-size: 1.3rem; font-weight: 800; color: var(--primary); }
        .product-stock { font-size: 0.85rem; color: #1d4ed8; background: #dbeafe; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
        
        .btn-add {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: auto;
            transition: all 0.2s;
        }
        .btn-add:hover { background: var(--primary-hover); transform: scale(1.02); }
        .btn-add:active { transform: scale(0.98); }

        /* Cart Offcanvas */
        .offcanvas-header { border-bottom: 1px solid #e5e7eb; }
        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .cart-item-info h6 { font-size: 0.95rem; font-weight: 700; margin: 0; }
        .cart-item-info small { color: var(--text-muted); font-size: 0.8rem; }
        .qty-controls {
            display: flex;
            align-items: center;
            background: var(--secondary);
            border-radius: 8px;
            padding: 2px;
        }
        .qty-controls button {
            border: none;
            background: white;
            border-radius: 6px;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-dark);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .qty-controls input {
            width: 40px;
            border: none;
            background: transparent;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Modal Yape */
        .yape-qr {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 15px;
            border: 2px dashed #00e0a1;
            padding: 10px;
            margin-bottom: 15px;
        }

        /* Sidebar integrado */
        .filtros-sidebar-inline {
            width: 280px;
            min-width: 280px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 24px 20px;
            position: sticky;
            top: 80px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            transform-origin: left;
        }
        .filtros-sidebar-inline.sidebar-hidden {
            width: 0;
            min-width: 0;
            padding: 0;
            border: none;
            overflow: hidden;
            margin-right: -1rem; /* Adjust gap when hidden */
            opacity: 0;
        }
        @media (max-width: 768px) {
            #mainLayout { flex-direction: column; }
            .filtros-sidebar-inline {
                width: 100%;
                min-width: 100%;
                position: static;
                max-height: none;
                margin-bottom: 20px;
            }
            .filtros-sidebar-inline.sidebar-hidden {
                display: none;
                margin-right: 0;
            }
        }

        /* Badge de filtros */
        .filtros-badge {
            background-color: #ef4444;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 50%;
            font-weight: 700;
        }

        /* Filtros laterales (Offcanvas) */
        .offcanvas-start {
            width: 320px !important;
        }
        .filtro-seccion {
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .filtro-seccion:last-child {
            border-bottom: none;
        }
        .filtro-seccion h6 {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .categoria-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0;
            cursor: pointer;
        }
        .categoria-option input {
            cursor: pointer;
        }

        /* Slider Doble */
        .range-slider-container {
            position: relative;
            width: 100%;
            height: 30px;
            margin-top: 15px;
        }
        .range-slider-container input[type="range"] {
            position: absolute;
            width: 100%;
            height: 5px;
            background: none;
            pointer-events: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            top: 50%;
            transform: translateY(-50%);
        }
        .range-slider-container input[type="range"]::-webkit-slider-thumb {
            height: 18px;
            width: 18px;
            border-radius: 50%;
            background: var(--primary);
            pointer-events: auto;
            -webkit-appearance: none;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            transition: transform 0.1s;
        }
        .range-slider-container input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.15);
        }
        .range-slider-container input[type="range"]::-moz-range-thumb {
            height: 18px;
            width: 18px;
            border-radius: 50%;
            background: var(--primary);
            pointer-events: auto;
            -moz-appearance: none;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            transition: transform 0.1s;
        }
        .range-slider-container input[type="range"]::-moz-range-thumb:hover {
            transform: scale(1.15);
        }
        .slider-track {
            position: absolute;
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 0;
        }

        /* Pills de Filtros Activos */
        .pill-filtro {
            background-color: #e8f5e9;
            color: var(--primary);
            border: 1px solid #c8e6c9;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .pill-filtro:hover {
            background-color: #c8e6c9;
        }
        .pill-filtro button {
            border: none;
            background: none;
            color: var(--primary);
            padding: 0;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        /* Animación Sin Resultados */
        .no-results-msg {
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container justify-content-between">
            <!-- Brand / Título -->
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <img src="assets/Logo navegador PuntoNet.png" alt="NISSI" height="30">
                <span>NISSI <small class="text-muted fw-normal fs-6">STORE</small></span>
            </a>

            <!-- Mi Cesta -->
            <button class="btn btn-dark position-relative rounded-pill px-3 fw-semibold shadow-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                <i class="bi bi-bag-fill me-1"></i> Mi Cesta
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" id="cartBadge">0</span>
            </button>
        </div>
    </nav>

    <div class="container">

        <!-- ── Hero Banner ── -->
        <div class="hero">
            <h1>Bienvenido a NISSI STORE</h1>
            <p>Encuentra los mejores uniformes escolares. Reserva online y recoge en tienda.</p>
        </div>

        <!-- ── Contenedor principal: Sidebar + Catálogo ── -->
        <div class="d-flex gap-4 align-items-start" id="mainLayout">

            <!-- SIDEBAR DE FILTROS -->
            <aside id="filtrosSidebar" class="filtros-sidebar-inline">

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

    <!-- Modal Selector de Talla para Tienda -->
    <div class="modal fade" id="tallaTiendaModal" tabindex="-1" aria-labelledby="tallaTiendaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 py-3 px-4" style="background: var(--primary); border-radius: 16px 16px 0 0;">
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0" id="tallaTiendaModalLabel">Seleccionar Talla</h6>
                        <small class="text-white opacity-75" id="tallaTiendaNombreProducto">Producto</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow:none;"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-muted mb-2" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">Tallas disponibles</label>
                        <div id="tallaTiendaPills" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background: #f0f4ff;">
                        <i class="bi bi-tag-fill fs-4 text-primary"></i>
                        <div class="flex-grow-1">
                            <div class="text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Precio / Stock</div>
                            <div class="d-flex gap-3 align-items-baseline">
                                <span class="fw-bold fs-5 text-primary" id="tallaTiendaPrecio">S/ 0.00</span>
                                <span class="text-muted" style="font-size:13px;" id="tallaTiendaStock">Seleccione una talla</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Cantidad</label>
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
                            style="border-radius:10px; background:var(--primary); color:#fff; border:none; padding: 12px 20px;">
                        <i class="bi bi-cart-plus-fill me-1"></i> Agregar al carrito
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Carrito -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas">
        <div class="offcanvas-header bg-light">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-bag-check-fill text-primary me-2"></i>Tu Cesta</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="flex-grow-1 overflow-auto px-4 py-2" id="cartItemsContainer">
                <div class="text-center text-muted py-5 mt-5">
                    <i class="bi bi-basket2 fs-1 mb-3 d-block"></i>
                    <p>Tu cesta está vacía</p>
                </div>
            </div>
            <div class="p-4 bg-light border-top mt-auto">
                <div class="d-flex justify-content-between mb-3 fw-bold fs-5 text-dark">
                    <span>Total:</span>
                    <span id="cartTotal">S/ 0.00</span>
                </div>
                <button class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm fs-6" id="btnCheckout" disabled>
                    Proceder al Pago <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        
        let catalogo = [];
        let cart = [];

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

            track.style.background = `linear-gradient(to right, #e5e7eb ${percentMin}%, var(--primary) ${percentMin}%, var(--primary) ${percentMax}%, #e5e7eb ${percentMax}%)`;
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

            // 4. Ordenamiento
            if (filtroOrdenVal === 'precio_asc') {
                productos.sort((a, b) => parseFloat(a.precio_desde) - parseFloat(b.precio_desde));
            } else if (filtroOrdenVal === 'precio_desc') {
                productos.sort((a, b) => parseFloat(b.precio_desde) - parseFloat(a.precio_desde));
            } else if (filtroOrdenVal === 'nombre_asc') {
                productos.sort((a, b) => a.nombre.localeCompare(b.nombre));
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
                    html += `
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="product-card">
                                <div class="product-img-wrap">
                                    ${imgHtml}
                                </div>
                                <div class="product-info">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="product-category">${escapeHtml(item.categoria)}</div>
                                        <div class="product-stock text-info"><i class="bi bi-rulers"></i> ${item.variantes.length} tallas</div>
                                    </div>
                                    <h3 class="product-title">${escapeHtml(item.nombre)}</h3>
                                    <div class="mt-auto pt-3">
                                        <div class="product-price mb-3">
                                            <span class="fs-6 text-muted fw-normal me-1">Desde</span>S/ ${parseFloat(item.precio_desde).toFixed(2)}
                                        </div>
                                        <button class="btn-add text-primary" style="background:#eff6ff; border:1.5px solid #3b82f6;" onclick='abrirModalTallasTienda(${JSON.stringify(item).replace(/'/g, "&#39;")})'>
                                            <i class="bi bi-rulers me-1"></i> Elegir talla
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // Producto simple
                    const v = item.variantes[0];
                    html += `
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="product-card">
                                <div class="product-img-wrap">
                                    ${imgHtml}
                                </div>
                                <div class="product-info">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="product-category">${escapeHtml(item.categoria)}</div>
                                        <div class="product-stock">${v.stock} disp.</div>
                                    </div>
                                    <h3 class="product-title">${escapeHtml(item.nombre)}</h3>
                                    <div class="mt-auto pt-3">
                                        <div class="product-price mb-3">S/ ${parseFloat(item.precio_desde).toFixed(2)}</div>
                                        <button class="btn-add" data-id="${v.id_producto}" data-nombre="${escapeHtml(item.nombre)}" data-precio="${v.precio}" data-stock="${v.stock}" onclick="agregarItemDirectoBtn(this)">
                                            <i class="bi bi-cart-plus-fill me-1"></i> Añadir a Cesta
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            container.innerHTML = html;
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
                
                btn.addEventListener('click', () => {
                    tallaSeleccionadaTienda = {
                        id_producto: v.id_producto,
                        nombre: `${producto.nombre} (T-${v.talla})`,
                        precio: v.precio,
                        stock: v.stock,
                        unidad: 'Und'
                    };
                    
                    Array.from(pillsContainer.children).forEach(b => {
                        b.style.background = '#f3f4f6';
                        b.style.color = '#374151';
                        b.style.borderColor = '#e5e7eb';
                    });
                    btn.style.background = 'var(--primary)';
                    btn.style.color = '#fff';
                    btn.style.borderColor = 'var(--primary)';
                    
                    document.getElementById('tallaTiendaPrecio').textContent = 'S/ ' + parseFloat(v.precio).toFixed(2);
                    document.getElementById('tallaTiendaStock').textContent = v.stock + ' disponibles';
                    document.getElementById('tallaTiendaCantidad').max = v.stock;
                    document.getElementById('tallaTiendaCantidad').value = 1;
                    document.getElementById('tallaTiendaAddBtn').disabled = false;
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
        }

        // Checkout Logic — redirect to checkout (reorganizado en views/public/)
        document.getElementById('btnCheckout').addEventListener('click', () => {
            if (cart.length === 0) return;
            sessionStorage.setItem('puntonet_cart', JSON.stringify(cart));
            window.location.href = 'views/public/V_checkout.php';
        });
    </script>
</body>
</html>
