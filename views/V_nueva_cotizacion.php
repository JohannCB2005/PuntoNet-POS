<?php
// Validar sesión activa del usuario
if (!isset($_SESSION['id_usuario'])) {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

// Cargar modelos requeridos para poblar la vista del punto de venta
require_once dirname(__DIR__) . '/models/M_Producto.php';
require_once dirname(__DIR__) . '/models/M_Cliente.php';
require_once dirname(__DIR__) . '/models/M_Categoria.php';
require_once dirname(__DIR__) . '/models/M_Caja.php';

// Listar productos activos en catálogo
$modelProducto = M_Producto::singleton();
$productos = $modelProducto->listar();

// Construir mapa de productos agrupados para el selector de tallas en POS
// Estructura: [ id_padre => ['nombre'=>..., 'imagen'=>..., 'categoria'=>..., 'variantes'=>[...]] ]
$productosAgrupados = [];
foreach ($productos as $ins) {
    if ($ins['es_agrupador'] == 1) {
        $productosAgrupados[$ins['id_producto']] = [
            'nombre'    => $ins['nombre'],
            'categoria' => $ins['categoria'],
            'imagen'    => $ins['imagen'] ?? null,
            'variantes' => [],
        ];
    }
}
foreach ($productos as $ins) {
    if ($ins['id_producto_padre'] && isset($productosAgrupados[$ins['id_producto_padre']]) && $ins['stock_piezas'] > 0) {
        $productosAgrupados[$ins['id_producto_padre']]['variantes'][] = [
            'id_producto' => (int) $ins['id_producto'],
            'talla'     => $ins['talla'] ?? 'S/T',
            'precio'    => (float) $ins['precio_unitario'],
            'stock'     => (float) $ins['stock_piezas'],
            'unidad'    => $ins['abreviatura'],
        ];
    }
}

// Listar clientes registrados
$modelCliente = M_Cliente::singleton();
$clientesRaw = $modelCliente->listarClientes();

$clientes = [];
foreach ($clientesRaw as $c) {
    $clientes[] = [
        'id_cliente'          => $c['id_cliente'],
        'tipo_cliente'        => $c['tipo_cliente'],
        'numero_documento'    => $c['numero_documento'],
        'nombres_razon_social'=> $c['nombres_razon_social'],
        'apellidos'           => $c['apellidos']
    ];
}

// Listar categorías activas para los filtros rápidos
$modelCat = M_Categoria::singleton();
$categorias = $modelCat->listar();


?>

<style>
    /* ── Menú desplegable de autocompletado de clientes/trabajadores ── */
    #clientAutocompleteDropdown {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: 100%;
        z-index: 1055;
        margin-top: 4px;
        background: #ffffff;
        border: 1px solid rgba(0,0,0,0.10);
        border-radius: 10px;
        box-shadow: 0 12px 28px -6px rgba(0,0,0,0.12), 0 6px 10px -4px rgba(0,0,0,0.08);
        max-height: 260px;
        overflow-y: auto;
        padding: 4px 0;
    }

    #clientAutocompleteDropdown.open {
        display: block;
    }

    .client-dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        font-size: 13.5px;
        color: #374151;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.12s ease, color 0.12s ease;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .client-dropdown-item:hover, .client-dropdown-item:focus {
        background-color: #f0fdf4;
        color: #0284c7;
        outline: none;
    }

    .client-dropdown-item strong {
        color: #111827;
    }

    .client-dropdown-item:hover strong {
        color: #0284c7;
    }

    .client-dropdown-divider {
        height: 1px;
        background: #e5e7eb;
        margin: 4px 0;
    }

    .client-dropdown-empty {
        padding: 12px 14px;
        font-size: 13px;
        color: #9ca3af;
        text-align: center;
    }

    .client-dropdown-create {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        font-size: 13.5px;
        color: #15803d;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.12s ease;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .client-dropdown-create:hover {
        background-color: #f0fdf4;
    }

    #clientTabs .nav-link {
        color: #6b7280;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
    }
    
    #clientTabs .nav-link.active {
        color: #0284c7 !important;
        border-bottom: 3px solid #0284c7 !important;
        font-weight: 600;
    }

    #clearClientSelectionBtn:hover {
        color: #dc3545 !important;
        background-color: transparent !important;
    }

    .client-autocomplete-wrapper {
        position: relative;
    }

    .gp-btn-primary:disabled {
        background-color: #e5e7eb !important;
        border-color: #e5e7eb !important;
        color: #9ca3af !important;
        cursor: not-allowed;
    }
</style>

<div class="container-fluid px-0">


    <!-- Fila Superior: Datos de Cliente e Identidad Fiscal -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="gp-card p-3" style="border-radius: 12px;">
                <h6 class="mb-3 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-cart3 text-primary"></i>
                    Datos del Cliente
                </h6>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold d-block text-muted mb-1" style="font-size: 12px;">Validez (Días)</label>
                        <select class="form-select form-select-sm text-sm fw-semibold" id="cotValidezSelect" style="height: 38px; border-color: #ced4da; box-shadow: none;">
                            <option value="7">7 Días</option>
                            <option value="15" selected>15 Días</option>
                            <option value="30">30 Días</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-8">
                        <input type="hidden" id="cartClientId" value="1">
                        <label class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Cliente</label>
                        <div class="client-autocomplete-wrapper">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="border-color: #ced4da;">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-1" id="clientAutocompleteInput" placeholder="Escriba DNI, RUC o Nombre..." autocomplete="off" style="font-size: 13.5px; box-shadow: none; border-color: #ced4da; height: 38px;">
                                <button type="button" class="btn btn-outline-secondary border-start-0 text-muted d-none" id="clearClientSelectionBtn" style="border-color: #ced4da; background: transparent;">
                                    <i class="bi bi-x-lg" style="font-size: 11px;"></i>
                                </button>
                            </div>
                            
                            <!-- Dropdown dinámico para el autocompletado en tiempo real -->
                            <div id="clientAutocompleteDropdown">
                                <!-- Opciones cargadas por JS -->
                            </div>
                        </div>
                        
                        <!-- Etiqueta del Cliente Seleccionado -->
                        <div id="selectedClientBadge" class="mt-2 d-none" style="font-size: 13px;">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 px-2.5 py-1.5 fw-semibold d-inline-flex align-items-center gap-1">
                                <i class="bi bi-person-check-fill"></i> 
                                <span id="selectedClientText">Público General</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Columna del Catálogo de Productos -->
        <div class="col-12 col-lg-7 col-xl-8">
            <div class="gp-card h-100 d-flex flex-column" style="min-height: 600px;">
                <div class="mb-4">
                    <h4 class="fw-bold text-dark mb-1">Nueva Cotización</h4>
                    <p class="text-muted mb-4" style="font-size: 13.5px;">Selecciona los productos para agregarlos a la cotización.</p>
                    
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <div class="input-group" style="width: 250px; max-width: 100%;">
                            <span class="input-group-text bg-transparent border-end-0 text-muted" id="search-pos-addon">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0 text-sm" id="searchCatalog" placeholder="Buscar productos..." aria-label="Buscar" aria-describedby="search-pos-addon" style="box-shadow: none;">
                        </div>
                        
                        <!-- Píldoras de Filtro por Categorías -->
                        <!-- Ver nota en V_nueva_venta.php: sin min-width estas píldoras no
                             envuelven y en móvil quedan en una franja inservible. -->
                        <div class="d-flex gap-2 overflow-auto pb-2 pb-md-0" style="flex: 1; min-width: 240px; white-space: nowrap; scrollbar-width: none;" id="categoryFilterPills">
                            <style>#categoryFilterPills::-webkit-scrollbar { display: none; }</style>
                            <button class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold cat-filter-btn active" data-cat="all">Todos</button>
                            <?php foreach ($categorias as $cat): ?>
                                <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold cat-filter-btn" style="border-color: #e5e7eb; color: #4b5563;" data-cat="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Cuadrícula de Tarjetas de Productos -->
                <div class="flex-grow-1 overflow-auto pe-1" style="max-height: 480px;" id="catalogGrid">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                        <?php foreach ($productos as $ins): ?>
                            <?php if ($ins['estado'] == 1 && !$ins['id_producto_padre'] && $ins['es_agrupador'] == 0): ?>
                                <?php $ilimitado = ($ins['stock_ilimitado'] ?? 0) == 1; ?>
                                <!-- TARJETA SIMPLE: producto sin variantes de talla -->
                                <div class="col product-card"
                                     data-nombre="<?php echo htmlspecialchars(strtolower($ins['nombre'])); ?>"
                                     data-categoria="<?php echo htmlspecialchars($ins['categoria']); ?>">
                                    <div class="card h-100 border border-light shadow-sm hover-shadow-md transition-all position-relative" style="border-radius: 12px; overflow: hidden;">
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <?php if ($ilimitado): ?>
                                                <span class="badge bg-success rounded-pill px-2.5 py-1 fw-bold" style="font-size: 10px;"><i class="bi bi-infinity"></i> Stock ilimitado</span>
                                            <?php elseif ($ins['stock_piezas'] <= 0): ?>
                                                <span class="badge bg-danger rounded-pill px-2.5 py-1 fw-bold" style="font-size: 10px;">Agotado</span>
                                            <?php elseif ($ins['stock_piezas'] <= 20): ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 fw-bold" style="font-size: 10px;">Bajo Stock (<?php echo number_format($ins['stock_piezas'], 1); ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold" style="font-size: 10px;">Stock: <?php echo number_format($ins['stock_piezas'], 1); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                                            <div class="mb-3 pt-2">
                                                <span class="text-muted text-uppercase fw-bold" style="font-size: 9px; letter-spacing: 0.5px;"><?php echo htmlspecialchars($ins['categoria']); ?></span>
                                                <h6 class="card-title fw-bold text-dark mb-1 text-truncate-2" style="font-size: 14px; min-height: 38px;"><?php echo htmlspecialchars($ins['nombre']); ?></h6>
                                                <small class="text-muted" style="font-size: 11px;">U.M: <?php echo htmlspecialchars($ins['unidad']); ?></small>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                                <div class="d-flex flex-column">
                                                    <span class="text-muted" style="font-size: 10px;">Precio Unit.</span>
                                                    <span class="fw-bold text-primary" style="font-size: 15px;">S/ <?php echo number_format($ins['precio_unitario'], 2); ?></span>
                                                </div>
                                                <button class="btn btn-primary bg-gradient border-0 add-to-cart-btn"
                                                        data-id="<?php echo $ins['id_producto']; ?>"
                                                        data-nombre="<?php echo htmlspecialchars($ins['nombre']); ?>"
                                                        data-precio="<?php echo $ins['precio_unitario']; ?>"
                                                        data-stock="<?php echo $ilimitado ? 9999 : $ins['stock_piezas']; ?>"
                                                        data-stockilimitado="<?php echo $ilimitado ? 1 : 0; ?>"
                                                        data-unidad="<?php echo htmlspecialchars($ins['abreviatura']); ?>"
                                                        style="width: 32px; height: 32px; border-radius: 8px; padding: 0; background-color: #0284c7;"
                                                        <?php echo (!$ilimitado && $ins['stock_piezas'] <= 0) ? 'disabled' : ''; ?>>
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($ins['estado'] == 1 && $ins['es_agrupador'] == 1): ?>
                                <?php
                                $variantesDisp = $productosAgrupados[$ins['id_producto']]['variantes'] ?? [];
                                $precioDesde   = !empty($variantesDisp) ? min(array_column($variantesDisp, 'precio')) : 0;
                                $stockTotal    = !empty($variantesDisp) ? array_sum(array_column($variantesDisp, 'stock')) : 0;
                                if (empty($variantesDisp)) continue;
                                ?>
                                <!-- TARJETA PADRE: producto con variantes de talla -->
                                <div class="col product-card"
                                     data-nombre="<?php echo htmlspecialchars(strtolower($ins['nombre'])); ?>"
                                     data-categoria="<?php echo htmlspecialchars($ins['categoria']); ?>">
                                    <div class="card h-100 border border-light shadow-sm hover-shadow-md transition-all position-relative" style="border-radius: 12px; overflow: hidden;">
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <span class="badge bg-info text-dark rounded-pill px-2.5 py-1 fw-bold" style="font-size: 10px;"><i class="bi bi-rulers"></i> <?php echo count($variantesDisp); ?> tallas</span>
                                        </div>
                                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                                            <div class="mb-3 pt-2">
                                                <span class="text-muted text-uppercase fw-bold" style="font-size: 9px; letter-spacing: 0.5px;"><?php echo htmlspecialchars($ins['categoria']); ?></span>
                                                <h6 class="card-title fw-bold text-dark mb-1 text-truncate-2" style="font-size: 14px; min-height: 38px;"><?php echo htmlspecialchars($ins['nombre']); ?></h6>
                                                <small class="text-muted" style="font-size: 11px;">Stock total: <?php echo number_format($stockTotal); ?> und.</small>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                                <div class="d-flex flex-column">
                                                    <span class="text-muted" style="font-size: 10px;">Desde</span>
                                                    <span class="fw-bold text-primary" style="font-size: 15px;">S/ <?php echo number_format($precioDesde, 2); ?></span>
                                                </div>
                                                <button class="btn open-talla-picker-btn"
                                                        data-padre-id="<?php echo $ins['id_producto']; ?>"
                                                        data-nombre="<?php echo htmlspecialchars($ins['nombre']); ?>"
                                                        style="height: 32px; border-radius: 8px; padding: 0 10px; font-size: 12px; background: #eff6ff; border: 1.5px solid #0284c7; color: #0284c7; white-space: nowrap;">
                                                    <i class="bi bi-rulers me-1"></i>Elegir talla
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna del Carrito de Compras -->
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="gp-card h-100 d-flex flex-column justify-content-between" style="min-height: 600px;">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-cart3 text-primary"></i>
                            Carrito de Venta
                        </h6>
                        <span class="badge bg-primary rounded-pill px-2" id="cartItemCountBadge">0 items</span>
                    </div>

                    <!-- Listado Dinámico de Productos Agregados -->
                    <div class="overflow-auto mb-3 pe-1" style="max-height: 250px; min-height: 180px;" id="cartList">
                        <div class="text-center py-5 text-muted" id="emptyCartMessage">
                            <i class="bi bi-cart fs-2 mb-2 d-block"></i>
                            <p style="font-size: 13px;" class="mb-1">El carrito está vacío</p>
                            <small class="text-muted" style="font-size: 11px;">Agrega productos desde el catálogo.</small>
                        </div>
                    </div>
                </div>



                <!-- Resumen Financiero Desglosado con IGV -->
                <div>
                    <div class="bg-light p-3 rounded-3 mb-3" style="font-size: 13px;">

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Subtotal</span>
                            <span class="fw-semibold text-dark" id="summarySubtotal">S/ 0.00</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">IGV (18%)</span>
                            <span class="fw-semibold text-dark" id="summaryIgv">S/ 0.00</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <span class="fw-bold text-dark" style="font-size: 14px;">Total</span>
                            <span class="fw-bold text-primary" style="font-size: 16px;" id="summaryTotal">S/ 0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Observaciones</label>
                        <textarea id="cotObservaciones" class="form-control form-control-sm" rows="2" placeholder="Opcional..." style="border-color: #ced4da; box-shadow: none;"></textarea>
                    </div>

                    <!-- Confirmar Cotización -->
                    <button class="gp-btn-primary w-100 border-0 py-2.5 d-flex align-items-center justify-content-center gap-2" id="submitSaleBtn" style="background-color: #0284c7;" disabled>
                        <span>Generar Cotización</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Modal: Selector de Talla para Productos con Variantes (POS) -->
<div class="modal fade" id="tallaPosModal" tabindex="-1" aria-labelledby="tallaPosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 py-3 px-4" style="background: #1d4ed8; border-radius: 16px 16px 0 0;">
                <div>
                    <h6 class="modal-title fw-bold text-white mb-0" id="tallaPosModalLabel">Seleccionar Talla</h6>
                    <small class="text-white opacity-75" id="tallaPosNombreProducto">Producto</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow:none;"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Pills de tallas -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted mb-2" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">Talla disponible</label>
                    <div id="tallaPosPickerPills" class="d-flex flex-wrap gap-2"></div>
                </div>

                <!-- Info de precio y stock -->
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background: #f0f4ff;">
                    <i class="bi bi-tag-fill fs-4 text-primary"></i>
                    <div class="flex-grow-1">
                        <div class="text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Precio / Stock</div>
                        <div class="d-flex gap-3 align-items-baseline">
                            <span class="fw-bold fs-5 text-primary" id="tallaPosSelectedPrecio">S/ 0.00</span>
                            <span class="text-muted" style="font-size:13px;" id="tallaPosSelectedStock">Seleccione una talla</span>
                        </div>
                    </div>
                </div>

                <!-- Cantidad -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">Cantidad</label>
                    <div class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-outline-secondary" id="tallaPosDecBtn"
                                style="width:38px; height:38px; border-radius:10px; padding:0; font-size:18px; line-height:1;">−</button>
                        <input type="number" id="tallaPosCantidad" class="form-control text-center fw-bold"
                               value="1" min="1" style="width:80px; border-radius:10px; height:38px; font-size:15px; box-shadow:none;">
                        <button type="button" class="btn btn-outline-secondary" id="tallaPosIncBtn"
                                style="width:38px; height:38px; border-radius:10px; padding:0; font-size:18px; line-height:1;">+</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;">Cancelar</button>
                <button type="button" class="btn fw-semibold" id="tallaPosAddBtn" disabled
                        style="border-radius:10px; background:#1d4ed8; color:#fff; border:none; padding: 8px 20px;">
                    <i class="bi bi-cart-plus-fill me-1"></i> Agregar al carrito
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registro de Nuevo Cliente en caliente -->
<div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-labelledby="nuevoClienteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="nuevoClienteModalLabel" style="font-size: 16px;">Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Inputs ocultos para compatibilidad de base de datos -->
                <input type="hidden" id="modalNombreComercial" value="">
                <input type="hidden" id="modalDiasCredito" value="0">
                <input type="hidden" id="modalCodInterno" value="">
                <input type="hidden" id="modalNacionalidad" value="PE">
                <input type="hidden" id="modalCodBarra" value="">
                <input type="hidden" id="modalDireccion" value="">
                <input type="hidden" id="modalApellidos" value="">
                <input type="checkbox" id="modalAgenteRetencion" class="d-none">

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Tipo Doc. Identidad <span class="text-danger">*</span></label>
                        <select class="form-select" id="modalTipoDoc" style="box-shadow: none; height: 38px; font-size: 13.5px;">
                            <option value="1">DNI</option>
                            <option value="2">RUC</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Número <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="modalNumDoc" placeholder="Ej. 78945612" style="box-shadow: none; height: 38px; font-size: 13.5px;">
                            <button type="button" class="btn btn-primary fw-semibold d-flex align-items-center gap-1 px-3" id="modalSearchApiBtn" style="height: 38px; border: none; background-color: #0284c7;">
                                <i class="bi bi-search"></i> <span id="modalSearchApiBtnText">RENIEC</span>
                            </button>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted fw-semibold mb-1">Nombre / Razón Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalNombre" placeholder="Nombres o Razón Social" style="box-shadow: none; height: 38px; font-size: 13.5px;">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Tipo de cliente</label>
                        <select class="form-select" id="modalTipoCliente" style="box-shadow: none; height: 38px; font-size: 13.5px;">
                            <option value="1" selected>Natural</option>
                            <option value="2">Jurídica</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Teléfono</label>
                        <input type="text" class="form-control" id="modalTelefono" placeholder="Teléfono / Celular" style="box-shadow: none; height: 38px; font-size: 13.5px;">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light py-3 d-flex justify-content-end gap-2" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-light fw-semibold px-4" data-bs-dismiss="modal" style="font-size: 13.5px; height: 38px;">Cancelar</button>
                <button type="button" class="btn btn-primary fw-semibold px-4" id="modalSaveClientBtn" style="font-size: 13.5px; height: 38px; background-color: #0284c7; border: none;">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Generación Automática e Impresión de Ticket post-venta -->
<div class="modal fade" id="imprimirTicketModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; height: 92vh;">
            <div class="modal-header bg-white border-bottom py-3" style="flex-shrink: 0; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 16px;">
                    <i class="bi bi-check-circle-fill text-primary"></i>
                    Cotización registrada con éxito
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;" onclick="window.location.reload()"></button>
            </div>

            <!-- Selector de formato de papel -->
            <div class="d-flex justify-content-center gap-2 py-2 bg-light border-bottom" style="flex-shrink: 0;">
                <button class="btn btn-primary btn-sm px-3 ticket-format-btn active" data-format="80mm" style="background-color: #0284c7; border: none;">
                    <i class="bi bi-receipt"></i> Ticket 80mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 ticket-format-btn" data-format="58mm" style="border-color: #0284c7; color: #0284c7;">
                    <i class="bi bi-receipt"></i> Ticket 58mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 ticket-format-btn" data-format="a4" style="border-color: #0284c7; color: #0284c7;">
                    <i class="bi bi-file-earmark-text"></i> A4
                </button>
            </div>

            <!-- Visor Iframe para previsualizar ticket -->
            <div class="modal-body p-0 position-relative" style="flex: 1 1 auto; overflow: hidden; background-color: #525659;">
                <div id="pdfLoadingSpinner" class="position-absolute top-50 start-50 translate-middle text-white d-flex flex-column align-items-center" style="z-index: 20;">
                    <div class="spinner-border mb-2" role="status"></div>
                    <span style="font-size: 14px;">Generando comprobante...</span>
                </div>
                <iframe
                    id="pdfPreviewFrame"
                    src=""
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; display: none; z-index: 10; background: #fff;">
                </iframe>
            </div>

            <!-- Botones de Acción -->
            <div class="modal-footer border-top bg-white py-2 px-4 d-flex justify-content-between align-items-center" style="flex-shrink: 0; border-radius: 0 0 12px 12px;">
                <button class="btn btn-primary d-flex align-items-center gap-2 px-4" onclick="printCurrentIframe()" style="background-color: #0284c7; border: none;">
                    <i class="bi bi-printer-fill"></i> Imprimir
                </button>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary px-4" onclick="window.location.href='/cotizaciones'">
                        <i class="bi bi-list-ul"></i> Ir al listado
                    </button>
                    <button class="btn btn-primary px-4" onclick="window.location.reload()" style="background-color: #0284c7; border: none;">
                        <i class="bi bi-plus-lg"></i> Nueva cotización
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lógica JS: Manejo de Carrito, Autocompletado y API de Identificación Sunat/Reniec -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Estado local del carrito
        let cart = [];

        // Matriz de clientes para búsquedas inmediatas en memoria
        const clientsList = <?php echo json_encode($clientes); ?>;

        // Elementos DOM del catálogo
        const searchInput = document.getElementById('searchCatalog');
        const catFilterBtns = document.querySelectorAll('.cat-filter-btn');
        const productCards = document.querySelectorAll('.product-card');
        const cartList = document.getElementById('cartList');
        const emptyCartMessage = document.getElementById('emptyCartMessage');
        const clearCartBtn = document.getElementById('clearCart');
        const submitSaleBtn = document.getElementById('submitSaleBtn');
        
        // Elementos del Autocompletado de Clientes
        const clientAutocompleteInput = document.getElementById('clientAutocompleteInput');
        const clearClientSelectionBtn = document.getElementById('clearClientSelectionBtn');
        const clientAutocompleteDropdown = document.getElementById('clientAutocompleteDropdown');
        const cartClientId = document.getElementById('cartClientId');
        const selectedClientBadge = document.getElementById('selectedClientBadge');
        const selectedClientText = document.getElementById('selectedClientText');

        // Elementos de importes del resumen
        const summarySubtotal = document.getElementById('summarySubtotal');
        const summaryIgv = document.getElementById('summaryIgv');
        const summaryTotal = document.getElementById('summaryTotal');

        // Elementos del formulario de registro rápido
        const modalTipoDoc = document.getElementById('modalTipoDoc');
        const modalNumDoc = document.getElementById('modalNumDoc');
        const modalSearchApiBtn = document.getElementById('modalSearchApiBtn');
        const modalSearchApiBtnText = document.getElementById('modalSearchApiBtnText');
        const modalNombre = document.getElementById('modalNombre');
        const modalNombreComercial = document.getElementById('modalNombreComercial');
        const modalDiasCredito = document.getElementById('modalDiasCredito');
        const modalCodInterno = document.getElementById('modalCodInterno');
        const modalNacionalidad = document.getElementById('modalNacionalidad');
        const modalTipoCliente = document.getElementById('modalTipoCliente');
        const modalCodBarra = document.getElementById('modalCodBarra');
        const modalAgenteRetencion = document.getElementById('modalAgenteRetencion');
        const modalDireccion = document.getElementById('modalDireccion');
        const modalTelefono = document.getElementById('modalTelefono');
        const modalSaveClientBtn = document.getElementById('modalSaveClientBtn');

        // Navegación de pestañas en modal
        const tabButtons = document.querySelectorAll('#clientTabs button');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => {
                    b.classList.remove('active', 'text-primary', 'border-bottom', 'border-3', 'border-primary');
                    b.classList.add('text-muted');
                });
                btn.classList.add('active', 'text-primary', 'border-bottom', 'border-3', 'border-primary');
                btn.classList.remove('text-muted');
            });
        });

        // Cambiar etiquetas dinámicamente según el documento seleccionado en el modal
        modalTipoDoc.addEventListener('change', () => {
            if (modalTipoDoc.value === '1') {
                modalSearchApiBtnText.innerText = 'RENIEC';
                modalTipoCliente.value = '1';
            } else {
                modalSearchApiBtnText.innerText = 'SUNAT';
                modalTipoCliente.value = '2';
            }
        });

        function updateDocumentMode() {
            cartClientId.value = '1';
            selectedClientText.innerText = 'Público General';
            clientAutocompleteInput.value = '';
            clearClientSelectionBtn.classList.add('d-none');
            validateSubmitBtn();
        }

        // Cambiar marcador de posición
        function updateInputConstraints() {
            if (!clientAutocompleteInput) return;
            clientAutocompleteInput.removeAttribute('maxlength');
            clientAutocompleteInput.placeholder = "Ingrese DNI, RUC o Nombre...";
        }

        updateInputConstraints();

        // Filtrar y renderizar el desplegable de autocompletado de clientes
        function showAutocompleteDropdown() {
            const rawVal = clientAutocompleteInput.value;
            const val = rawVal.toLowerCase().trim();
            
            let matches = [];
            if (val === '') {
                matches = clientsList.slice(0, 8); // Sugerir los primeros 8 por defecto
            } else {
                matches = clientsList.filter(c => 
                    c.numero_documento.toLowerCase().includes(val) || 
                    c.nombres_razon_social.toLowerCase().includes(val) || 
                    (c.apellidos && c.apellidos.toLowerCase().includes(val))
                );
            }

            let dropdownHtml = '';
            if (matches.length > 0) {
                matches.forEach(c => {
                    const label = c.apellidos ? `${c.apellidos}, ${c.nombres_razon_social}` : c.nombres_razon_social;
                    dropdownHtml += `
                        <button type="button" class="client-dropdown-item client-opt"
                            data-id="${c.id_cliente}"
                            data-trabajador-id="${c.id_trabajador || ''}"
                            data-doc="${c.numero_documento}"
                            data-name="${label.trim()}"
                            data-es-trabajador="${c.es_trabajador}"
                            data-tipo="${c.tipo_cliente}">
                            <strong>${c.numero_documento}</strong>&nbsp;–&nbsp;${label.trim()}
                        </button>
                    `;
                });
                if (val !== '') {
                    dropdownHtml += `<div class="client-dropdown-divider"></div>`;
                    dropdownHtml += `
                        <button type="button" class="client-dropdown-create" id="createClientDropdownBtn" data-value="${rawVal}">
                            <i class="bi bi-person-plus-fill"></i> Crear cliente "${rawVal}"
                        </button>
                    `;
                }
            } else {
                dropdownHtml += `<div class="client-dropdown-empty">No se encontraron resultados</div>`;
                dropdownHtml += `<div class="client-dropdown-divider"></div>`;
                dropdownHtml += `
                    <button type="button" class="client-dropdown-create" id="createClientDropdownBtn" data-value="${rawVal}">
                        <i class="bi bi-person-plus-fill"></i> Crear cliente "${rawVal}"
                    </button>
                `;
            }

            clientAutocompleteDropdown.innerHTML = dropdownHtml;
            clientAutocompleteDropdown.classList.add('open');

            // Adjuntar oyentes de eventos a las coincidencias sugeridas
            clientAutocompleteDropdown.querySelectorAll('.client-opt').forEach(opt => {
                opt.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // Impedir que el desenfoque cierre el menú antes de seleccionar
                    const id = opt.dataset.id;
                    const tid = opt.dataset.trabajadorId;
                    const doc = opt.dataset.doc;
                    const name = opt.dataset.name;
                    const es_trabajador = opt.dataset.esTrabajador === 'true';
                    const tipo = opt.dataset.tipo;
                    selectClient(id, tid, es_trabajador, doc, name, tipo);
                });
            });

            // Opción de creación rápida
            const createBtn = document.getElementById('createClientDropdownBtn');
            if (createBtn) {
                createBtn.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    openQuickCreateModal(createBtn.dataset.value);
                });
            }
        }

        function hideAutocompleteDropdown() {
            clientAutocompleteDropdown.classList.remove('open');
        }

        // Fijar el cliente seleccionado en el estado global de la venta
        async function selectClient(id_cliente, id_trabajador, es_trabajador, doc, name, tipo) {
            cartClientId.value = id_cliente || '';
            clientAutocompleteInput.value = `${doc} - ${name}`;
            selectedClientText.innerText = name;
            clearClientSelectionBtn.classList.remove('d-none');
            hideAutocompleteDropdown();
            
            validateSubmitBtn();
            renderCart();
        }

        // Limpiar selección de cliente
        clearClientSelectionBtn.addEventListener('click', () => {
            cartClientId.value = '1';
            selectedClientText.innerText = 'Público General';
            clientAutocompleteInput.value = '';
            clearClientSelectionBtn.classList.add('d-none');
            
            validateSubmitBtn();
            renderCart();
        });



        // Filtrar entrada numérica o caracteres de texto
        clientAutocompleteInput.addEventListener('input', () => {
            let val = clientAutocompleteInput.value;
            const hasLetters = /[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ]/.test(val);
            
            if (!hasLetters) {
                val = val.replace(/\D/g, '');
                const maxLen = 11;
                if (val.length > maxLen) {
                    val = val.substring(0, maxLen);
                }
                clientAutocompleteInput.value = val;
            } else {
                val = val.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.,\-&]/g, '');
                clientAutocompleteInput.value = val;
            }
            showAutocompleteDropdown();
        });
        clientAutocompleteInput.addEventListener('focus', showAutocompleteDropdown);

        clientAutocompleteInput.addEventListener('blur', () => {
            setTimeout(hideAutocompleteDropdown, 150);
        });

        // Lanzar y configurar modal de creación rápida
        function openQuickCreateModal(typedVal) {
            hideAutocompleteDropdown();
            
            modalNombre.value = '';
            document.getElementById('modalApellidos').value = '';
            modalNombreComercial.value = '';
            modalDiasCredito.value = '0';
            modalCodInterno.value = '';
            modalCodBarra.value = '';
            modalAgenteRetencion.checked = false;
            modalDireccion.value = '';
            modalTelefono.value = '';

            const num = typedVal.replace(/\D/g, '');
            modalNumDoc.value = num;

            // Detección automática por la longitud de dígitos
            if (num.length === 11) {
                modalTipoDoc.value = '2';
                modalSearchApiBtnText.innerText = 'SUNAT';
                modalTipoCliente.value = '2';
            } else if (num.length === 8) {
                modalTipoDoc.value = '1';
                modalSearchApiBtnText.innerText = 'RENIEC';
                modalTipoCliente.value = '1';
            } else {
                modalTipoDoc.value = '1';
                modalSearchApiBtnText.innerText = 'RENIEC';
                modalTipoCliente.value = '1';
            }

            tabButtons.forEach((b, idx) => {
                if (idx === 0) {
                    b.classList.add('active', 'text-primary', 'border-bottom', 'border-3', 'border-primary');
                    b.classList.remove('text-muted');
                } else {
                    b.classList.remove('active', 'text-primary', 'border-bottom', 'border-3', 'border-primary');
                    b.classList.add('text-muted');
                }
            });
            
            const triggerEl = document.querySelector('#clientTabs button[data-bs-target="#tab-datos"]');
            if (triggerEl) {
                const tab = new bootstrap.Tab(triggerEl);
                tab.show();
            }

            const createModal = new bootstrap.Modal(document.getElementById('nuevoClienteModal'));
            createModal.show();
        }

        // Consultar API RENIEC/SUNAT a través del backend
        modalSearchApiBtn.addEventListener('click', async () => {
            const docNum = modalNumDoc.value.trim();
            const docType = modalTipoDoc.value;

            if (docNum === '') {
                Swal.fire({ icon: 'warning', title: 'Número requerido', text: 'Debe ingresar el número de documento para realizar la consulta.', confirmButtonColor: '#0284c7' });
                return;
            }

            if (docType === '1' && docNum.length !== 8) {
                Swal.fire({ icon: 'warning', title: 'DNI Inválido', text: 'El DNI debe tener exactamente 8 dígitos.', confirmButtonColor: '#0284c7' });
                return;
            }

            if (docType === '2' && docNum.length !== 11) {
                Swal.fire({ icon: 'warning', title: 'RUC Inválido', text: 'El RUC debe tener exactamente 11 dígitos.', confirmButtonColor: '#0284c7' });
                return;
            }

            modalSearchApiBtn.disabled = true;
            const originalText = modalSearchApiBtnText.innerText;
            modalSearchApiBtnText.innerText = 'Buscando...';

            try {
                const response = await fetch('./controllers/C_Cliente.php?action=buscar_api_only', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ numero_documento: docNum })
                });
                const res = await response.json();

                if (res.success) {
                    modalNombre.value = res.data.nombre;
                    document.getElementById('modalApellidos').value = res.data.apellidos || '';
                    modalDireccion.value = res.data.direccion;
                    modalTipoCliente.value = res.data.tipo_cliente;

                    Swal.fire({ icon: 'success', title: '¡Datos Obtenidos!', text: 'Los datos del cliente se cargaron exitosamente.', showConfirmButton: false, timer: 1500 });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error de consulta', text: res.mensaje, confirmButtonColor: '#0284c7' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor para la consulta de API.', confirmButtonColor: '#0284c7' });
            } finally {
                modalSearchApiBtn.disabled = false;
                modalSearchApiBtnText.innerText = originalText;
            }
        });

        // Guardar el nuevo cliente registrado desde el modal de la venta
        modalSaveClientBtn.addEventListener('click', async () => {
            const tipo_documento = parseInt(modalTipoDoc.value);
            const numero_documento = modalNumDoc.value.trim();
            const fullName = modalNombre.value.trim();
            const storedApellidos = document.getElementById('modalApellidos').value.trim();
            const direccion = modalDireccion.value.trim();
            const telefono = modalTelefono.value.trim();
            const tipo_cliente = parseInt(modalTipoCliente.value);

            // Separar nombres y apellidos del nombre completo
            let nombres_razon_social = fullName;
            let apellidos = storedApellidos;
            if (fullName.indexOf(',') !== -1) {
                // Formato "APELLIDOS, NOMBRES" - extraer cada parte
                const parts = fullName.split(',');
                if (!apellidos) {
                    apellidos = parts[0].trim();
                }
                nombres_razon_social = parts.slice(1).join(',').trim();
            }

            if (numero_documento === '' || nombres_razon_social === '') {
                Swal.fire({ icon: 'warning', title: 'Campos obligatorios', text: 'Debe ingresar el Número de documento y el Nombre / Razón Social.', confirmButtonColor: '#0284c7' });
                return;
            }

            modalSaveClientBtn.disabled = true;

            try {
                const response = await fetch('./controllers/C_Cliente.php?action=crear', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo_documento,
                        numero_documento,
                        nombres_razon_social,
                        apellidos,
                        direccion,
                        telefono,
                        tipo_cliente
                    })
                });
                const res = await response.json();

                if (res.success) {
                    Swal.fire({ icon: 'success', title: '¡Cliente Guardado!', text: res.mensaje, showConfirmButton: false, timer: 1500 });

                    // Agregar al listado en memoria
                    const newCli = res.cliente;
                    clientsList.push(newCli);

                    // Seleccionarlo automáticamente
                    const cliLabel = newCli.apellidos ? `${newCli.apellidos}, ${newCli.nombres_razon_social}` : newCli.nombres_razon_social;
                    selectClient(newCli.id_cliente, null, false, newCli.numero_documento, cliLabel, newCli.tipo_cliente);

                    const modalEl = document.getElementById('nuevoClienteModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error al registrar', text: res.mensaje, confirmButtonColor: '#0284c7' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor para registrar el cliente.', confirmButtonColor: '#0284c7' });
            } finally {
                modalSaveClientBtn.disabled = false;
            }
        });

        // Filtrado local del Catálogo de Productos
        let currentCategory = 'all';
        function filterCatalog() {
            const query = searchInput.value.toLowerCase().trim();

            productCards.forEach(card => {
                const nameMatch = card.dataset.nombre.includes(query);
                const categoryMatch = (currentCategory === 'all' || card.dataset.categoria === currentCategory);

                if (nameMatch && categoryMatch) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        if (searchInput) searchInput.addEventListener('input', filterCatalog);
        
        catFilterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                catFilterBtns.forEach(b => {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-primary');
                    b.style.borderColor = '#0284c7';
                    b.style.color = '#0284c7';
                    b.style.backgroundColor = 'transparent';
                });
                btn.classList.add('btn-primary', 'active');
                btn.classList.remove('btn-outline-primary');
                btn.style.borderColor = '';
                btn.style.color = '#fff';
                btn.style.backgroundColor = '#0284c7';
                
                currentCategory = btn.dataset.cat;
                filterCatalog();
            });
        });



        // ── Lógica del Modal de Selección de Talla (POS) ──
        const productosAgrupados = <?php echo json_encode($productosAgrupados); ?>;
        const tallaPosModal    = new bootstrap.Modal(document.getElementById('tallaPosModal'));
        const tallaPosNombre   = document.getElementById('tallaPosNombreProducto');
        const tallaPosPills    = document.getElementById('tallaPosPickerPills');
        const tallaPosPrec     = document.getElementById('tallaPosSelectedPrecio');
        const tallaPosSt       = document.getElementById('tallaPosSelectedStock');
        const tallaPosCant     = document.getElementById('tallaPosCantidad');
        const tallaPosDecBtn   = document.getElementById('tallaPosDecBtn');
        const tallaPosIncBtn   = document.getElementById('tallaPosIncBtn');
        const tallaPosAddBtn   = document.getElementById('tallaPosAddBtn');

        let tallaSeleccionada  = null; // { id_producto, talla, precio, stock, unidad }

        function seleccionarTalla(variante, pillEl) {
            tallaSeleccionada = variante;
            // Actualizar pills
            tallaPosPills.querySelectorAll('.talla-pill').forEach(p => {
                p.classList.remove('active');
                p.style.background   = '#f3f4f6';
                p.style.color        = '#374151';
                p.style.borderColor  = '#e5e7eb';
                p.style.fontWeight   = '500';
            });
            pillEl.classList.add('active');
            pillEl.style.background  = '#1d4ed8';
            pillEl.style.color       = '#fff';
            pillEl.style.borderColor = '#1d4ed8';
            pillEl.style.fontWeight  = '700';
            // Actualizar info
            tallaPosPrec.textContent = 'S/ ' + parseFloat(variante.precio).toFixed(2);
            tallaPosSt.textContent   = variante.stock + ' disponibles';
            tallaPosCant.value = 1;
            tallaPosCant.max   = variante.stock;
            tallaPosAddBtn.disabled  = false;
        }

        document.querySelectorAll('.open-talla-picker-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const idPadre  = parseInt(btn.dataset.padreId);
                const nombre   = btn.dataset.nombre;
                const producto = productosAgrupados[idPadre];
                if (!producto) return;

                tallaPosNombre.textContent = nombre;
                tallaSeleccionada = null;
                tallaPosCant.value = 1;
                tallaPosPrec.textContent = 'S/ 0.00';
                tallaPosSt.textContent   = 'Seleccione una talla';
                tallaPosAddBtn.disabled  = true;

                // Renderizar pills de tallas
                tallaPosPills.innerHTML = '';
                producto.variantes.forEach(v => {
                    const pill = document.createElement('button');
                    pill.type        = 'button';
                    pill.className   = 'talla-pill btn fw-semibold';
                    pill.textContent = v.talla;
                    pill.style.cssText = 'min-width:48px; height:38px; border-radius:8px; font-size:13px; border:1.5px solid #e5e7eb; background:#f3f4f6; color:#374151;';
                    pill.addEventListener('click', () => seleccionarTalla(v, pill));
                    tallaPosPills.appendChild(pill);
                });

                tallaPosModal.show();
            });
        });

        tallaPosDecBtn.addEventListener('click', () => {
            const val = parseInt(tallaPosCant.value) - 1;
            tallaPosCant.value = Math.max(1, val);
        });

        tallaPosIncBtn.addEventListener('click', () => {
            if (!tallaSeleccionada) return;
            const val = parseInt(tallaPosCant.value) + 1;
            tallaPosCant.value = Math.min(tallaSeleccionada.stock, val);
        });

        tallaPosAddBtn.addEventListener('click', () => {
            if (!tallaSeleccionada) return;
            const cantidad = parseInt(tallaPosCant.value) || 1;
            const nombre   = tallaPosNombre.textContent + ' (T-' + tallaSeleccionada.talla + ')';
            // Reutilizar la misma función de carrito que el botón add-to-cart
            const id       = tallaSeleccionada.id_producto;
            const precio   = tallaSeleccionada.precio;
            const stock    = tallaSeleccionada.stock;
            const unidad   = tallaSeleccionada.unidad || 'Und';

            const existing = cart.find(item => item.id_producto === id);
            if (existing) {
                const newQty = existing.cantidad + cantidad;
                if (newQty > stock) {
                    Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: `Solo hay ${stock} unidades de esta talla.`, confirmButtonColor: '#1d4ed8' });
                    return;
                }
                existing.cantidad = newQty;
                existing.subtotal = existing.cantidad * precio;
            } else {
                cart.push({ id_producto: id, nombre, precio, stock, unidad, cantidad, subtotal: cantidad * precio });
            }
            tallaPosAddBtn.blur();
            tallaPosModal.hide();
            renderCart();
        });

        // Agregar artículo al carrito y evaluar stock en vivo
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id);
                const nombre = btn.dataset.nombre;
                const precio = parseFloat(btn.dataset.precio);
                const stock = parseFloat(btn.dataset.stock);
                const unidad = btn.dataset.unidad;

                const existing = cart.find(item => item.id_producto === id);
                if (existing) {
                    if (existing.cantidad + 1 > stock) {
                        Swal.fire({ icon: 'warning', title: 'Stock Insuficiente', text: `Solo hay ${stock} unidades disponibles de este producto.`, confirmButtonColor: '#0284c7' });
                        return;
                    }
                    existing.cantidad += 1;
                    existing.subtotal = existing.cantidad * existing.precio;
                } else {
                    cart.push({
                        id_producto: id,
                        nombre: nombre,
                        precio: precio,
                        stock: stock,
                        unidad: unidad,
                        cantidad: 1,
                        subtotal: precio
                    });
                }

                renderCart();
            });
        });



        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', () => {
                cart = [];
                renderCart();
            });
        }

        // Modificar cantidad en línea en la vista del carrito
        function updateQuantity(id, newQty) {
            const item = cart.find(i => i.id_producto === id);
            if (!item) return;

            if (newQty <= 0) {
                cart = cart.filter(i => i.id_producto !== id);
            } else if (newQty > item.stock) {
                Swal.fire({ icon: 'warning', title: 'Stock Insuficiente', text: `El stock disponible es de ${item.stock} ${item.unidad}.`, confirmButtonColor: '#0284c7' });
                item.cantidad = item.stock;
                item.subtotal = item.cantidad * item.precio;
            } else {
                item.cantidad = newQty;
                item.subtotal = item.cantidad * item.precio;
            }
            renderCart();
        }

        // Validar si la venta cumple las condiciones mínimas para ser cobrada
        function validateSubmitBtn() {
            const hasItems = cart.length > 0;
            const hasClient = (cartClientId.value && cartClientId.value !== '');
            submitSaleBtn.disabled = !(hasItems && hasClient);
        }

        // Dibujar el estado actual del Carrito en el HTML
        function renderCart() {
            const cartItemCountBadge = document.getElementById('cartItemCountBadge');
            if (cartItemCountBadge) {
                cartItemCountBadge.innerText = `${cart.length} items`;
            }

            if (cart.length === 0) {
                cartList.innerHTML = '';
                cartList.appendChild(emptyCartMessage);
                
                summarySubtotal.innerText = 'S/ 0.00';
                summaryIgv.innerText = 'S/ 0.00';
                summaryTotal.innerText = 'S/ 0.00';
                validateSubmitBtn();
                return;
            }

            if (document.getElementById('emptyCartMessage')) {
                cartList.innerHTML = '';
            }

            let cartHtml = '<div class="list-group list-group-flush">';
            let totalGeneral = 0;

            cart.forEach(item => {
                totalGeneral += item.subtotal;

                let renderQtyInfo = `<div class="d-flex align-items-center border rounded-2" style="height: 32px; width: 105px; overflow: hidden; background: #fff;">
                                    <button class="btn btn-light rounded-0 border-0 p-0 text-secondary d-flex align-items-center justify-content-center" 
                                            onclick="window.posDecrease(${item.id_producto})" style="width: 30px; height: 100%; background: #f8f9fa;">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" class="form-control border-0 text-center p-0 m-0 fw-semibold text-dark" 
                                           value="${item.cantidad}" step="0.01" min="0.01" 
                                           style="font-size: 13px; box-shadow: none; width: 45px; height: 100%; -moz-appearance: textfield; background: #fff;" 
                                           onchange="window.posChange(${item.id_producto}, this.value)"
                                           oninput="this.style.appearance = 'none'; this.style.webkitAppearance = 'none';">
                                    <button class="btn btn-light rounded-0 border-0 p-0 text-secondary d-flex align-items-center justify-content-center" 
                                            onclick="window.posIncrease(${item.id_producto})" style="width: 30px; height: 100%; background: #f8f9fa;">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <style>
                                    input[type=number]::-webkit-inner-spin-button, 
                                    input[type=number]::-webkit-outer-spin-button { 
                                        -webkit-appearance: none; 
                                        margin: 0; 
                                    }
                                </style>`;
                


                cartHtml += `
                    <div class="list-group-item px-0 py-2.5 border-bottom bg-transparent d-flex flex-column gap-1">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark text-truncate" style="font-size: 13px; max-width: 180px;">${item.nombre}</span>
                            <span class="fw-bold text-dark" style="font-size: 13.5px;">S/ ${item.subtotal.toFixed(2)}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            ${renderQtyInfo}
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted" style="font-size: 11px;">S/ ${item.precio.toFixed(2)} / ${item.unidad}</span>
                                <button class="btn btn-link text-danger p-0 border-0" onclick="window.posRemove(${item.id_producto})">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            cartHtml += '</div>';
            cartList.innerHTML = cartHtml;

            // Desglose tributario (Total es precio con IGV incluido, subtotal = total/1.18)
            const totalFinal = Math.max(0, totalGeneral);
            const subtotalVal = totalFinal / 1.18;
            const igvVal = totalFinal - subtotalVal;

            summarySubtotal.innerText = `S/ ${subtotalVal.toFixed(2)}`;
            summaryIgv.innerText = `S/ ${igvVal.toFixed(2)}`;
            summaryTotal.innerText = `S/ ${totalFinal.toFixed(2)}`;
            validateSubmitBtn();
        }

        // Exponer funciones visuales del carrito al ámbito global
        window.posDecrease = (id) => {
            const item = cart.find(i => i.id_producto === id);
            if (item) updateQuantity(id, item.cantidad - 1);
        };
        window.posIncrease = (id) => {
            const item = cart.find(i => i.id_producto === id);
            if (item) updateQuantity(id, item.cantidad + 1);
        };
        window.posChange = (id, val) => {
            const num = parseFloat(val);
            if (isNaN(num) || num <= 0) {
                updateQuantity(id, 0);
            } else {
                updateQuantity(id, num);
            }
        };
        window.posRemove = (id) => {
            cart = cart.filter(i => i.id_producto !== id);
            renderCart();
        };

        // Enviar Transacción Final a base de datos por AJAX
        if (submitSaleBtn) {
            submitSaleBtn.addEventListener('click', async () => {
                const id_cliente = parseInt(cartClientId.value) || null;
                const validez = parseInt(document.getElementById('cotValidezSelect').value);
                const observaciones = document.getElementById('cotObservaciones').value;
                const clientInput = document.getElementById('clientAutocompleteInput');
                const cliente_manual = clientInput ? clientInput.value : '';
                
                let totalGeneral = 0;
                cart.forEach(item => totalGeneral += item.subtotal);
                const subtotalVal = totalGeneral / 1.18;
                const igvVal = totalGeneral - subtotalVal;
                
                const dataToSend = {
                    id_cliente,
                    cliente_nombre_manual: cliente_manual,
                    validez: validez,
                    observaciones: observaciones,
                    subtotal: subtotalVal,
                    igv: igvVal,
                    total: totalGeneral,
                    detalles: cart.map(item => ({
                        id_producto: item.id_producto,
                        cantidad: item.cantidad,
                        precio: item.precio,
                        subtotal: item.subtotal
                    }))
                };
                
                Swal.fire({
                    title: '¿Generar cotización?',
                    text: `Se generará una cotización por un total de S/ ${totalGeneral.toFixed(2)}`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0284c7',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Generar',
                    cancelButtonText: 'Cancelar'
                }).then(async (res) => {
                    if (res.isConfirmed) {
                        submitSaleBtn.disabled = true;
                        try {
                            const response = await fetch('./controllers/C_Cotizacion.php?action=crear', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(dataToSend)
                            });
                            const result = await response.json();

                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Cotización Creada!',
                                    text: result.mensaje,
                                    showConfirmButton: false,
                                    timer: 1000
                                }).then(() => {
                                    cart = [];
                                    renderCart();
                                    
                                    // Levantar Modal de previsualización e Impresión de Comprobante
                                    openPrintModal(result.id_cotizacion);
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: result.mensaje, confirmButtonColor: '#0284c7' });
                                submitSaleBtn.disabled = false;
                            }
                        } catch (err) {
                            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
                            submitSaleBtn.disabled = false;
                        }
                    }
                });
            });
        }
        
        // --- CONTROL DEL MODAL DE IMPRESIÓN ---
        let currentPrintId = null;
        let currentPrintFormat = '80mm';

        window.openPrintModal = function(id_venta) {
            currentPrintId = id_venta;
            const modal = new bootstrap.Modal(document.getElementById('imprimirTicketModal'));
            modal.show();
            loadIframePreview();
        };

        const formatBtns = document.querySelectorAll('.ticket-format-btn');
        formatBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                formatBtns.forEach(b => {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-primary');
                });
                btn.classList.add('btn-primary', 'active');
                btn.classList.remove('btn-outline-primary');
                
                currentPrintFormat = btn.dataset.format;
                loadIframePreview();
            });
        });

        // Renderizar el comprobante en caliente cambiando el src del visor iframe
        function loadIframePreview() {
            const iframe = document.getElementById('pdfPreviewFrame');
            const spinner = document.getElementById('pdfLoadingSpinner');
            
            iframe.style.display = 'none';
            spinner.style.display = 'flex';
            
            const url = `views/V_cotizacion_print.php?id=${currentPrintId}&format=${currentPrintFormat}`;
            
            iframe.onload = function() {
                spinner.style.display = 'none';
                iframe.style.display = 'block';
            };
            
            iframe.src = url;
        }

        // Llamar a la ventana de impresión interna del iframe
        window.printCurrentIframe = function() {
            const iframe = document.getElementById('pdfPreviewFrame');
            if (iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            }
        };
    });
</script>
