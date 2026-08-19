<?php
// Restricción de acceso: Solo usuarios Administradores pueden gestionar el catálogo de productos
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

// Cargar modelos requeridos para las relaciones de categoría y unidades en los formularios
require_once dirname(__DIR__) . '/models/M_Producto.php';
require_once dirname(__DIR__) . '/models/M_Categoria.php';
require_once dirname(__DIR__) . '/models/M_Unidad.php';
require_once dirname(__DIR__) . '/models/M_TipoVariante.php';

$modelProducto = M_Producto::singleton();
$productos = $modelProducto->listar();

$modelCat = M_Categoria::singleton();
$categorias = $modelCat->listar();

$modelUni = M_Unidad::singleton();
$unidades = $modelUni->listar();

$tallas = $modelProducto->obtenerTallas();
$tiposCorbata = $modelProducto->obtenerTiposCorbata();
$bimestres = $modelProducto->obtenerBimestres();

$modelTipoVar = M_TipoVariante::singleton();
$tiposVariante = $modelTipoVar->listarActivos();

$isAdmin = ($_SESSION['rol'] === 'Administrador');
?>

<style>
    .ios-switch { position: relative; display: inline-flex; align-items: center; cursor: pointer; }
    .ios-switch input { position: absolute; opacity: 0; width: 0; height: 0; margin: 0; }
    .ios-switch .ios-slider {
        position: relative; display: inline-flex; flex-shrink: 0;
        width: 72px; height: 40px; background: #e9e9ea; border-radius: 40px;
        transition: background-color .25s ease; box-shadow: inset 0 0 0 rgba(0,0,0,0);
    }
    .ios-switch .ios-slider::after {
        content: ''; position: absolute; top: 3px; left: 3px;
        width: 34px; height: 34px; background: #fff; border-radius: 50%;
        box-shadow: 0 2px 6px rgba(0,0,0,.25); transition: transform .25s ease;
    }
    .ios-switch input:checked + .ios-slider { background: #34c759; }
    .ios-switch input:checked + .ios-slider::after { transform: translateX(32px); }
</style>

<div class="container-fluid px-0">
    <!-- Encabezado de Página -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1e293b; letter-spacing: -0.5px;">Productos</h2>
            <p class="text-muted mb-0" style="font-size: 14px;">Administra los productos disponibles en Confecciones NISSI.</p>
        </div>
        <?php if ($isAdmin): ?>
            <button class="gp-btn-primary d-flex align-items-center gap-2 border-0" data-bs-toggle="modal" data-bs-target="#nuevoProductoModal">
                <i class="bi bi-plus-lg"></i>
                <span>Nuevo Producto</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Panel de Productos -->
    <div class="gp-card">
        <!-- Barra de Búsqueda -->
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted" id="search-addon">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0 text-sm" id="searchProductos" placeholder="Buscar producto..." aria-label="Buscar" aria-describedby="search-addon" style="box-shadow: none; font-size: 14px;">
                </div>
            </div>
        </div>

        <!-- Tabla del Inventario de Productos -->
        <div class="table-responsive">
            <?php
            // Pre-calcular etiquetas de variantes por padre para los tooltips
            $etiquetasPorPadre = [];
            foreach ($productos as $ins) {
                if ($ins['id_producto_padre']) {
                    $id_padre = $ins['id_producto_padre'];
                    if (!isset($etiquetasPorPadre[$id_padre])) $etiquetasPorPadre[$id_padre] = [];
                    if (!empty($ins['etiqueta_variante'])) {
                        $etiquetasPorPadre[$id_padre][] = $ins['etiqueta_variante'];
                    }
                }
            }
            ?>
            <table class="table align-middle text-sm" id="tableProductos" style="font-size: 14px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th scope="col" class="pb-3">Producto</th>
                        <th scope="col" class="pb-3">Categoría / Detalles</th>
                        <th scope="col" class="pb-3 text-end">Precio</th>
                        <th scope="col" class="pb-3 text-end">Stock</th>
                        <th scope="col" class="pb-3">Unidad</th>
                        <th scope="col" class="pb-3">Estado</th>
                        <?php if ($isAdmin): ?>
                            <th scope="col" class="pb-3 text-end">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? '7' : '6'; ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam-fill fs-2 mb-2 d-block"></i>
                                No se encontraron productos.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $ins): ?>
                            <!-- Resaltar visualmente si el producto está bajo la cuota mínima de 20 unidades -->
                            <?php $ilimitado = ($ins['stock_ilimitado'] ?? 0) == 1; ?>
                            <?php $lowStock = ($ins['stock_piezas'] <= 20) && !$ilimitado; ?>
                            <?php 
                            $esPadre = ($ins['es_agrupador'] == 1);
                            $esHijo = ($ins['id_producto_padre'] != null);
                            $rowStyle = '';
                            if ($esPadre) $rowStyle = 'background-color: #f8fafc; border-left: 4px solid #3b82f6;';
                            if ($esHijo)  $rowStyle = 'background-color: #ffffff;';
                            ?>
                            <tr class="border-bottom producto-row <?php echo $esHijo ? 'child-row padre-'.$ins['id_producto_padre'] : ''; ?>" style="<?php echo $rowStyle; ?><?php echo $esHijo ? ' display: none;' : ''; ?>">
                                <td class="py-3">
                                    <div class="d-flex align-items-center <?php echo $esHijo ? 'ps-4' : ''; ?>">
                                        <?php if ($esPadre): ?>
                                            <button class="btn btn-sm btn-link p-0 text-dark me-2 toggle-children-btn" data-padre="<?php echo $ins['id_producto']; ?>" style="box-shadow:none;">
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        <?php endif; ?>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold text-dark">
                                                <?php if ($esHijo): ?><i class="bi bi-arrow-return-right text-muted me-2"></i><?php endif; ?>
                                                <?php echo htmlspecialchars($ins['nombre']); ?>
                                            </span>
                                            <span class="text-muted font-mono <?php echo $esHijo ? 'ms-4' : ''; ?>" style="font-size: 11px;">PROD-<?php echo str_pad($ins['id_producto'], 3, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted">
                                    <?php if (!$esHijo): ?>
                                        <span class="badge <?php echo $esPadre ? 'bg-primary' : 'bg-secondary'; ?> mb-1"><?php echo htmlspecialchars($ins['categoria']); ?></span><br>
                                        <?php if (!empty($ins['categorias_ids'])): ?>
                                            <?php $totalCats = count(array_filter(explode(',', $ins['categorias_ids']), 'strlen')); ?>
                                            <?php if ($totalCats > 1): ?>
                                                <span class="badge bg-light text-dark border mb-1" style="font-size:10px;">+<?php echo $totalCats - 1; ?> etiqueta<?php echo $totalCats - 1 > 1 ? 's' : ''; ?></span><br>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($esPadre):
                                        $listaEtiquetas = isset($etiquetasPorPadre[$ins['id_producto']]) ? implode(', ', $etiquetasPorPadre[$ins['id_producto']]) : 'Sin variantes';
                                    ?>
                                        <span class="text-primary fw-bold" style="cursor:pointer;" data-bs-toggle="tooltip" title="Variantes: <?php echo htmlspecialchars($listaEtiquetas); ?>">
                                            <i class="bi bi-info-circle-fill me-1"></i>Ver Variantes
                                        </span>
                                    <?php elseif ($esHijo): ?>
                                        <small>Variante: <strong class="text-primary"><?php echo htmlspecialchars($ins['etiqueta_variante'] ?? '-'); ?></strong></small>
                                        <?php if ($ins['tipo_variante'] === 'bimestre'): ?>
                                            <br><small><?php echo htmlspecialchars($ins['nivel'] ?? '-'); ?> / <?php echo htmlspecialchars($ins['grado'] ?? '-'); ?> · <?php echo htmlspecialchars($ins['area'] ?? '-'); ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($ins['tipo_variante'] === 'bimestre'): ?>
                                        <small><?php echo htmlspecialchars($ins['nivel'] ?? '-'); ?> / <?php echo htmlspecialchars($ins['grado'] ?? '-'); ?></small><br>
                                        <small><?php echo htmlspecialchars($ins['area'] ?? '-'); ?> - <?php echo htmlspecialchars($ins['bimestre'] ?? '-'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-medium">
                                    <?php if ($esPadre): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        S/ <?php echo number_format($ins['precio_unitario'], 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($esPadre): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif ($ilimitado): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill fw-bold" style="font-size: 11px;">
                                            <i class="bi bi-infinity me-1"></i>Stock ilimitado
                                        </span>
                                    <?php else: ?>
                                        <span class="<?php echo $lowStock ? 'text-danger fw-bold' : 'text-dark fw-medium'; ?>">
                                            <?php echo number_format($ins['stock_piezas'], 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?php echo htmlspecialchars($ins['unidad']); ?> (<?php echo htmlspecialchars($ins['abreviatura']); ?>)
                                </td>
                                <td>
                                    <span class="<?php echo $ins['estado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                        <?php echo $ins['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <?php if ($isAdmin): ?>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button class="btn btn-link text-muted p-1 hover-text-primary edit-producto-btn" 
                                                    data-id="<?php echo $ins['id_producto']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($ins['nombre']); ?>"
                                                    data-categoria="<?php echo $ins['categoria']; ?>"
                                                    data-categorias="<?php echo htmlspecialchars($ins['categorias_ids'] ?? ''); ?>"
                                                    data-unidad="<?php echo $ins['unidad']; ?>"
                                                    data-precio="<?php echo $ins['precio_unitario']; ?>"
                                                    data-costo="<?php echo $ins['costo_produccion']; ?>"
                                                    data-comision="<?php echo $ins['comision']; ?>"
                                                    data-stock="<?php echo $ins['stock_piezas']; ?>"
                                                    data-stockilimitado="<?php echo $ins['stock_ilimitado'] ?? 0; ?>"
                                                    data-imagen="<?php echo htmlspecialchars($ins['imagen'] ?? ''); ?>"
                                                    data-talla="<?php echo $ins['id_talla'] ?? ''; ?>"
                                                    data-tipocorbata="<?php echo $ins['id_tipo_corbata'] ?? ''; ?>"
                                                    data-nivel="<?php echo $ins['id_nivel'] ?? ''; ?>"
                                                    data-grado="<?php echo $ins['id_grado'] ?? ''; ?>"
                                                    data-area="<?php echo $ins['id_area'] ?? ''; ?>"
                                                    data-bimestre="<?php echo $ins['id_bimestre'] ?? ''; ?>"
                                                    data-tipo="<?php echo $ins['tipo_variante'] ?? ''; ?>"
                                                    data-etiqueta="<?php echo htmlspecialchars($ins['etiqueta_variante'] ?? ''); ?>"
                                                    data-espadre="<?php echo $ins['es_agrupador']; ?>"
                                                    data-padreid="<?php echo $ins['id_producto_padre'] ?? ''; ?>"
                                                    title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button class="btn btn-link text-muted p-1 hover-text-success update-stock-btn"
                                                    <?php echo $ilimitado ? 'data-ilimitado="1"' : ''; ?>
                                                    data-id="<?php echo $ins['id_producto']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($ins['nombre']); ?>"
                                                    data-stock="<?php echo $ins['stock_piezas']; ?>"
                                                    data-precio="<?php echo $ins['precio_unitario']; ?>"
                                                    data-espadre="<?php echo $ins['es_agrupador']; ?>"
                                                    data-padreid="<?php echo $ins['id_producto_padre'] ?? ''; ?>"
                                                    data-etiqueta="<?php echo htmlspecialchars($ins['etiqueta_variante'] ?? '-'); ?>"
                                                    data-dimension="<?php echo $ins['id_talla'] ?? ($ins['id_tipo_corbata'] ?? ($ins['id_bimestre'] ?? '')); ?>"
                                                    title="Actualizar Stock">
                                                <i class="bi bi-box-seam"></i>
                                            </button>
                                            <button class="btn btn-link text-muted p-1 hover-text-danger delete-producto-btn" 
                                                    data-id="<?php echo $ins['id_producto']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($ins['nombre']); ?>"
                                                    title="Eliminar">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal: Actualizar Stock (Integrado con Kardex) -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3 px-4" id="stockModalHeader" style="border-radius: 15px 15px 0 0;">
                <div>
                    <h6 class="modal-title fw-bold text-white mb-0" id="stockModalLabel">Actualizar Stock</h6>
                    <small class="text-white opacity-75" id="stockModalSubtitle">Producto</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow:none;"></button>
            </div>
            <form id="formStock">
                <input type="hidden" id="stock_id_producto">
                <input type="hidden" id="stock_precio_unit">
                <div class="modal-body p-4">

                    <!-- Stock actual -->
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background: #f0f4ff;">
                        <i class="bi bi-box-seam fs-4 text-primary"></i>
                        <div>
                            <div class="text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Stock actual</div>
                            <div class="fw-bold fs-5" id="stock_actual_display">—</div>
                        </div>
                    </div>

                    <!-- Tipo de movimiento -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Tipo de movimiento</label>
                        <div class="d-flex gap-2">
                            <button type="button" id="btnTipoEntrada" class="btn flex-fill fw-semibold stock-tipo-btn active-tipo"
                                    onclick="setTipoStock('entrada')"
                                    style="border-radius:10px; border: 2px solid #23284E; background:#eef1f7; color:#23284E; font-size:14px; padding:10px;">
                                <i class="bi bi-plus-circle-fill me-1"></i> Agregar
                            </button>
                            <button type="button" id="btnTipoSalida" class="btn flex-fill fw-semibold stock-tipo-btn"
                                    onclick="setTipoStock('salida')"
                                    style="border-radius:10px; border: 2px solid #e5e7eb; background:#f9fafb; color:#6b7280; font-size:14px; padding:10px;">
                                <i class="bi bi-dash-circle-fill me-1"></i> Descontar
                            </button>
                        </div>
                        <input type="hidden" id="stock_tipo" value="entrada">
                    </div>

                    <!-- Cantidad -->
                    <div class="mb-3">
                        <label for="stock_cantidad" class="form-label fw-semibold" style="font-size:13px;">Cantidad</label>
                        <input type="number" class="form-control" id="stock_cantidad" min="0.01" step="0.01"
                               placeholder="Ej. 10" style="border-radius:10px; height:46px; font-size:14px;" required>
                    </div>

                    <!-- Campo condicional Entrada: Boleta -->
                    <div class="mb-3" id="campo_boleta">
                        <label for="stock_referencia" class="form-label fw-semibold" style="font-size:13px;">
                            <i class="bi bi-receipt me-1 text-primary"></i> Nº de Boleta / Factura
                        </label>
                        <input type="text" class="form-control" id="stock_referencia"
                               placeholder="Ej. B001-00123" style="border-radius:10px; height:46px; font-size:14px;">
                        <div class="form-text">Documento de compra asociado a esta entrada de stock.</div>
                    </div>

                    <!-- Campo condicional Salida: Motivo -->
                    <div class="mb-3 d-none" id="campo_motivo">
                        <label for="stock_motivo" class="form-label fw-semibold" style="font-size:13px;">
                            <i class="bi bi-chat-left-text me-1 text-danger"></i> Motivo del descuento
                        </label>
                        <select class="form-select" id="stock_motivo_select" style="border-radius:10px; height:46px; font-size:14px;" onchange="toggleMotivoOtro()">
                            <option value="">— Seleccione un motivo —</option>
                            <option value="Merma">Merma / Deterioro</option>
                            <option value="Devolución">Devolución a proveedor</option>
                            <option value="Pérdida">Pérdida / Robo</option>
                            <option value="Ajuste de inventario">Ajuste de inventario</option>
                            <option value="Otro">Otro...</option>
                        </select>
                        <input type="text" class="form-control mt-2 d-none" id="stock_motivo_otro"
                               placeholder="Describe el motivo..." style="border-radius:10px; font-size:14px;">
                    </div>

                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0" id="btnConfirmarStock">
                        <i class="bi bi-check-lg me-1"></i> Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Actualizar Stock Masivo (Padre) -->
<div class="modal fade" id="stockMasivoModal" tabindex="-1" aria-labelledby="stockMasivoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3 px-4" id="stockMasivoModalHeader" style="border-radius: 15px 15px 0 0;">
                <div>
                    <h6 class="modal-title fw-bold text-white mb-0" id="stockMasivoModalLabel">Actualizar Stock por Variantes</h6>
                    <small class="text-white opacity-75" id="stockMasivoSubtitle">Producto Padre</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow:none;"></button>
            </div>
            <form id="formStockMasivo">
                <input type="hidden" id="stock_masivo_id_padre">
                <div class="modal-body p-4">
                    
                    <!-- Tipo de movimiento -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold" style="font-size:13px;">Tipo de movimiento global</label>
                        <div class="d-flex gap-2">
                            <button type="button" id="btnTipoMasivoEntrada" class="btn flex-fill fw-semibold active-tipo"
                                    onclick="setTipoStockMasivo('entrada')"
                                    style="border-radius:10px; border: 2px solid #23284E; background:#eef1f7; color:#23284E; font-size:14px; padding:10px;">
                                <i class="bi bi-plus-circle-fill me-1"></i> Agregar Stock
                            </button>
                            <button type="button" id="btnTipoMasivoSalida" class="btn flex-fill fw-semibold"
                                    onclick="setTipoStockMasivo('salida')"
                                    style="border-radius:10px; border: 2px solid #e5e7eb; background:#f9fafb; color:#6b7280; font-size:14px; padding:10px;">
                                <i class="bi bi-dash-circle-fill me-1"></i> Descontar Stock
                            </button>
                        </div>
                        <input type="hidden" id="stock_masivo_tipo" value="entrada">
                    </div>

                    <!-- Referencia / Motivo -->
                    <div class="mb-4" id="campo_masivo_boleta">
                        <label class="form-label fw-semibold" style="font-size:13px;">Nº de Boleta / Factura</label>
                        <input type="text" class="form-control" id="stock_masivo_referencia" placeholder="Opcional" style="border-radius:10px;">
                    </div>
                    
                    <div class="mb-4 d-none" id="campo_masivo_motivo">
                        <label class="form-label fw-semibold text-danger" style="font-size:13px;">Motivo del descuento</label>
                        <select class="form-select" id="stock_masivo_motivo_select" style="border-radius:10px;">
                            <option value="Merma">Merma / Deterioro</option>
                            <option value="Devolución">Devolución a proveedor</option>
                            <option value="Pérdida">Pérdida / Robo</option>
                            <option value="Ajuste de inventario">Ajuste de inventario</option>
                        </select>
                    </div>

                    <!-- Tabla de Variantes -->
                    <label class="form-label fw-semibold" style="font-size:13px;">Cantidades por Variante</label>
                    <div class="table-responsive" style="border: 1px solid #e5e7eb; border-radius: 10px;">
                        <table class="table table-borderless align-middle mb-0 text-sm">
                            <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                <tr>
                                    <th class="py-3 px-3 text-muted">Variante</th>
                                    <th class="py-3 px-3 text-end text-muted">Stock Actual</th>
                                    <th class="py-3 px-3 text-end text-muted" style="width: 140px;">Cantidad a <span id="masivoAccionTexto">Sumar</span></th>
                                </tr>
                            </thead>
                            <tbody id="stockMasivoTbody">
                                <!-- Filas generadas dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0" id="btnConfirmarStockMasivo">
                        <i class="bi bi-check-lg me-1"></i> Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Producto -->

<div class="modal fade" id="nuevoProductoModal" tabindex="-1" aria-labelledby="nuevoProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="nuevoProductoModalLabel">Nuevo Producto</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formNuevoProducto">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="new_nombre" class="form-label fw-semibold" style="font-size: 13px;">Nombre del producto</label>
                        <input type="text" class="form-control" id="new_nombre" placeholder="Ej. Maíz a granel" required autocomplete="off">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="new_categoria" class="form-label fw-semibold" style="font-size: 13px;">Categorías</label>
                            <input type="hidden" id="new_id_categoria" value="">
                            <input type="text" class="form-control form-control-sm mb-2" id="new_buscar_cat" placeholder="Buscar categoría…" autocomplete="off" style="font-size:12.5px;">
                            <div class="border rounded-3 p-2" id="new_cat_lista" style="max-height:180px; overflow-y:auto; background:#fafbfc;">
                                <?php foreach ($categorias as $cat): ?>
                                    <div class="form-check cat-item" data-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                        <input class="form-check-input cat-check" type="checkbox" id="new_cat_<?php echo $cat['id_categoria']; ?>"
                                               value="<?php echo $cat['id_categoria']; ?>" data-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                               data-prefix="new">
                                        <label class="form-check-label fw-semibold" style="font-size:12.5px; cursor:pointer;" for="new_cat_<?php echo $cat['id_categoria']; ?>">
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted" style="font-size:11px;">La primera marcada es la categoría principal.</small>
                        </div>
                        <div class="col-6">
                            <label for="new_unidad" class="form-label fw-semibold" style="font-size: 13px;">Unidad de medida</label>
                            <select class="form-select" id="new_unidad" required>
                                <option value="" disabled selected>Seleccionar</option>
                                <?php foreach ($unidades as $uni): ?>
                                    <option value="<?php echo $uni['id_unidad']; ?>"><?php echo htmlspecialchars($uni['nombre']); ?> (<?php echo htmlspecialchars($uni['abreviatura']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div id="new_campos_precio_stock">
                            <div class="row">
                                <div class="col-3">
                                    <label for="new_costo" class="form-label fw-semibold" style="font-size: 13px;">Costo base (S/)</label>
                                    <input type="number" class="form-control" id="new_costo" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="col-3">
                                    <label for="new_precio" class="form-label fw-semibold" style="font-size: 13px;">Precio Venta (S/)</label>
                                    <input type="number" class="form-control" id="new_precio" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="col-3">
                                    <label for="new_comision" class="form-label fw-semibold" style="font-size: 13px;">Comisión (S/)</label>
                                    <input type="number" class="form-control" id="new_comision" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="col-3">
                                    <label for="new_stock" class="form-label fw-semibold" style="font-size: 13px;">Stock inicial</label>
                                    <input type="number" class="form-control" id="new_stock" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="mt-3 mb-3">
                                <label class="ios-switch" for="new_stock_ilimitado">
                                    <input type="checkbox" id="new_stock_ilimitado" role="switch">
                                    <span class="ios-slider"></span>
                                    <span class="ms-3 fw-semibold" style="font-size:14px; cursor:pointer;">
                                        <i class="bi bi-infinity me-1 text-success"></i> Stock ilimitado <span class="text-muted" style="font-weight:400;">(servicio: no controla stock, ej. envío)</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tipo de variante -->
                    <div class="mt-3 p-3 rounded-3" style="background:#f8fafc; border:1.5px solid #e2e8f0;">
                        <label for="new_tipo_variante" class="form-label fw-semibold mb-1" style="font-size:13px;">
                            <i class="bi bi-grid-3x3-gap me-1 text-primary"></i> Tipo de variante
                        </label>
                        <select class="form-select" id="new_tipo_variante" onchange="updateForm('new')">
                            <option value="">Sin variantes (producto simple)</option>
                            <?php foreach ($tiposVariante as $tv): ?>
                                <?php $desc = ($tv['modo'] === 'libre') ? ' (se escribe la etiqueta)' : ''; ?>
                                <option value="<?php echo htmlspecialchars($tv['codigo']); ?>"><?php echo htmlspecialchars($tv['nombre'] . $desc); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size:11px;">Al elegir un tipo de variante, precio, costo, comisión y stock se configuran por variante (igual que tallas).</small>
                    </div>

                    <!-- Panel de Variantes (oculto por defecto) -->
                    <div id="new_grupo_variantes" class="mt-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0" id="new_grupo_variantes_titulo" style="font-size:13px;">Configurar Variantes</h6>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" id="new_precio_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Precio base" style="width:110px;">
                                <input type="number" id="new_costo_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Costo base" style="width:110px;">
                                <input type="number" id="new_comision_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Comisión base" style="width:110px;">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="new_aplicar_precio_todos" style="font-size:12px; white-space:nowrap;">Aplicar a todos</button>
                            </div>
                        </div>
                        <div id="new_variantes_rows" class="d-flex flex-column gap-2"></div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="new_imagen" class="form-label fw-semibold" style="font-size: 13px;"><i class="bi bi-image text-success me-1"></i>Imagen del producto (Opcional)</label>
                        <input type="file" class="form-control" id="new_imagen" accept="image/*">
                    </div>
                    <!-- Grupo Uniformes -->
                    <div id="new_grupo_uniformes" style="display: none;" class="mt-3 p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-3" style="font-size: 13px;">Atributos de Uniforme</h6>
                        <div class="row">
                            <div class="col-6">
                                <label for="new_talla" class="form-label fw-semibold" style="font-size: 13px;">Talla</label>
                                <select class="form-select" id="new_talla">
                                    <option value="">N/A</option>
                                    <?php foreach ($tallas as $t): ?>
                                        <option value="<?php echo $t['id_talla']; ?>"><?php echo htmlspecialchars($t['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="new_tipo_corbata" class="form-label fw-semibold" style="font-size: 13px;">Tipo Corbata</label>
                                <select class="form-select" id="new_tipo_corbata">
                                    <option value="">N/A</option>
                                    <?php foreach ($tiposCorbata as $tc): ?>
                                        <option value="<?php echo $tc['id_tipo_corbata']; ?>"><?php echo htmlspecialchars($tc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Crear registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Producto -->
<div class="modal fade" id="editarProductoModal" tabindex="-1" aria-labelledby="editarProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="editarProductoModalLabel">Editar Registro</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formEditarProducto">
                <input type="hidden" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_nombre" class="form-label fw-semibold" style="font-size: 13px;">Nombre del producto</label>
                        <input type="text" class="form-control" id="edit_nombre" required autocomplete="off">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="edit_id_categoria" class="form-label fw-semibold" style="font-size: 13px;">Categorías</label>
                            <input type="hidden" id="edit_id_categoria" value="">
                            <input type="text" class="form-control form-control-sm mb-2" id="edit_buscar_cat" placeholder="Buscar categoría…" autocomplete="off" style="font-size:12.5px;">
                            <div class="border rounded-3 p-2" id="edit_cat_lista" style="max-height:180px; overflow-y:auto; background:#fafbfc;">
                                <?php foreach ($categorias as $cat): ?>
                                    <div class="form-check cat-item" data-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                        <input class="form-check-input cat-check" type="checkbox" id="edit_cat_<?php echo $cat['id_categoria']; ?>"
                                               value="<?php echo $cat['id_categoria']; ?>" data-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                               data-prefix="edit">
                                        <label class="form-check-label fw-semibold" style="font-size:12.5px; cursor:pointer;" for="edit_cat_<?php echo $cat['id_categoria']; ?>">
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted" style="font-size:11px;">La primera marcada es la categoría principal.</small>
                        </div>
                        <div class="col-6">
                            <label for="edit_unidad" class="form-label fw-semibold" style="font-size: 13px;">Unidad de medida</label>
                            <select class="form-select" id="edit_unidad" required>
                                <?php foreach ($unidades as $uni): ?>
                                    <option value="<?php echo $uni['id_unidad']; ?>"><?php echo htmlspecialchars($uni['nombre']); ?> (<?php echo htmlspecialchars($uni['abreviatura']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="edit_campos_precio_stock" class="row">
                        <div class="col-3">
                            <label for="edit_costo" class="form-label fw-semibold" style="font-size: 13px;">Costo base (S/)</label>
                            <input type="number" class="form-control" id="edit_costo" step="0.01" min="0">
                        </div>
                        <div class="col-3">
                            <label for="edit_precio" class="form-label fw-semibold" style="font-size: 13px;">Precio Venta (S/)</label>
                            <input type="number" class="form-control" id="edit_precio" step="0.01" min="0">
                        </div>
                        <div class="col-3">
                            <label for="edit_comision" class="form-label fw-semibold" style="font-size: 13px;">Comisión (S/)</label>
                            <input type="number" class="form-control" id="edit_comision" step="0.01" min="0">
                        </div>
                        <div class="col-3">
                            <label for="edit_stock" class="form-label fw-semibold" style="font-size: 13px;">Stock</label>
                            <input type="number" class="form-control" id="edit_stock" step="0.01" min="0" disabled title="Actualice el stock desde el panel principal usando el botón del Kardex">
                        </div>
                        <div class="col-12 mt-3 mb-3">
                            <label class="ios-switch" for="edit_stock_ilimitado">
                                <input type="checkbox" id="edit_stock_ilimitado" role="switch">
                                <span class="ios-slider"></span>
                                <span class="ms-3 fw-semibold" style="font-size:14px; cursor:pointer;">
                                    <i class="bi bi-infinity me-1 text-success"></i> Stock ilimitado <span class="text-muted" style="font-weight:400;">(servicio: no controla stock)</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Tipo de variante (Editar) -->
                    <div class="mt-3 p-3 rounded-3" style="background:#f8fafc; border:1.5px solid #e2e8f0;">
                        <label for="edit_tipo_variante" class="form-label fw-semibold mb-1" style="font-size:13px;">
                            <i class="bi bi-grid-3x3-gap me-1 text-primary"></i> Tipo de variante
                        </label>
                        <select class="form-select" id="edit_tipo_variante" onchange="updateForm('edit')">
                            <option value="">Sin variantes (producto simple)</option>
                            <?php foreach ($tiposVariante as $tv): ?>
                                <?php $desc = ($tv['modo'] === 'libre') ? ' (se escribe la etiqueta)' : ''; ?>
                                <option value="<?php echo htmlspecialchars($tv['codigo']); ?>"><?php echo htmlspecialchars($tv['nombre'] . $desc); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size:11px;">Al elegir un tipo de variante, precio, costo y comisión se configuran por variante (igual que tallas).</small>
                    </div>

                    <!-- Panel de Variantes para Editar -->
                    <div id="edit_grupo_variantes" class="mt-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0" id="edit_grupo_variantes_titulo" style="font-size:13px;">Configurar Variantes</h6>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" id="edit_precio_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Precio base" style="width:110px;">
                                <input type="number" id="edit_costo_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Costo base" style="width:110px;">
                                <input type="number" id="edit_comision_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Comisión base" style="width:110px;">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="edit_aplicar_precio_todos" style="font-size:12px; white-space:nowrap;">Aplicar a todos</button>
                            </div>
                        </div>
                        <div id="edit_variantes_rows" class="d-flex flex-column gap-2"></div>
                        <small class="text-muted d-block mt-2" style="font-size:11px;">* El stock de las variantes debe actualizarse desde el panel principal usando el botón de la caja.</small>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="edit_imagen" class="form-label fw-semibold" style="font-size: 13px;"><i class="bi bi-image text-success me-1"></i>Nueva Imagen (Opcional)</label>
                        <input type="file" class="form-control" id="edit_imagen" accept="image/*">
                        <div id="edit_imagen_preview" class="mt-2 d-none">
                            <span class="text-muted" style="font-size: 11px;">Imagen actual:</span>
                            <img src="" id="edit_imagen_img" class="d-block mt-1 border rounded" style="max-height: 85px; max-width: 100%; object-fit: contain;">
                        </div>
                    </div>
                    <!-- Grupo Uniformes -->
                    <div id="edit_grupo_uniformes" style="display: none;" class="mt-3 p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-3" style="font-size: 13px;">Atributos de Uniforme</h6>
                        <div class="row">
                            <div class="col-6">
                                <label for="edit_talla" class="form-label fw-semibold" style="font-size: 13px;">Talla</label>
                                <select class="form-select" id="edit_talla">
                                    <option value="">N/A</option>
                                    <?php foreach ($tallas as $t): ?>
                                        <option value="<?php echo $t['id_talla']; ?>"><?php echo htmlspecialchars($t['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="edit_tipo_corbata" class="form-label fw-semibold" style="font-size: 13px;">Tipo Corbata</label>
                                <select class="form-select" id="edit_tipo_corbata">
                                    <option value="">N/A</option>
                                    <?php foreach ($tiposCorbata as $tc): ?>
                                        <option value="<?php echo $tc['id_tipo_corbata']; ?>"><?php echo htmlspecialchars($tc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- JavaScript para CRUD de Productos -->

<script>
    // Datos de opciones para cada tipo de variante (inyectados desde PHP)
    const DATA_OPCIONES = {
        talla: <?php echo json_encode(array_map(fn($t) => ['id' => (int)$t['id_talla'], 'nombre' => $t['nombre']], $tallas)); ?>,
        corbata: <?php echo json_encode(array_map(fn($t) => ['id' => (int)$t['id_tipo_corbata'], 'nombre' => $t['nombre']], $tiposCorbata)); ?>,
        bimestre: <?php echo json_encode(array_map(fn($b) => ['id' => (int)$b['id_bimestre'], 'nombre' => $b['nombre']], $bimestres)); ?>
        <?php foreach ($tiposVariante as $tv): ?>
            <?php if ($tv['tipo'] === 'personalizado'): ?>,
        '<?php echo $tv['codigo']; ?>': <?php echo json_encode(array_map(fn($o) => ['id' => (int)$o['id_opcion'], 'nombre' => $o['nombre']], $tv['opciones'] ?? []), JSON_UNESCAPED_UNICODE); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    };
    const NOMBRE_TIPO = { talla: 'Tallas', corbata: 'Tipos de corbata', bimestre: 'Bimestres', libre: 'Etiquetas' };
    const DATA_TIPOS = {
        <?php foreach ($tiposVariante as $tv): ?>
        '<?php echo $tv['codigo']; ?>': '<?php echo htmlspecialchars($tv['nombre']); ?>',
        <?php endforeach; ?>
    };

    // 'libre' y los tipos personalizados (v{id}) guardan la etiqueta/opción en nombre_variante
    const esTipoEtiqueta = (tipo) => tipo === 'libre' || (typeof tipo === 'string' && tipo.startsWith('v'));

    document.addEventListener('DOMContentLoaded', () => {
        // Inicializar tooltips de Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Contraer y expandir filas hijas
        document.querySelectorAll('.toggle-children-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const padreId = btn.dataset.padre;
                const icon = btn.querySelector('i');
                const isCollapsed = icon.classList.contains('bi-chevron-right');

                document.querySelectorAll(`.child-row.padre-${padreId}`).forEach(row => {
                    row.style.display = isCollapsed ? 'table-row' : 'none';
                });

                icon.classList.toggle('bi-chevron-right');
                icon.classList.toggle('bi-chevron-down');
            });
        });

        // 1. Filtrado dinámico de productos (búsqueda)
        const searchInput = document.getElementById('searchProductos');
        const rows = document.querySelectorAll('.producto-row');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase().trim();
                rows.forEach(row => {
                    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
                });
            });
        }

        <?php if ($isAdmin): ?>
        // ============================================================
        // Utilidades del formulario (nuevo y editar)
        // ============================================================

        const editIsHijo = () => false; // (mantenido por compatibilidad)

        // Devuelve los ids de categorías marcadas en un prefix (new/edit)
        function categoriasMarcadas(prefix) {
            const checks = document.querySelectorAll(`.cat-check[data-prefix="${prefix}"]`);
            return Array.from(checks).filter(c => c.checked).map(c => c.value);
        }

        // Actualiza la categoría primaria (primera marcada) y la visibilidad de grupos
        function actualizarPrimaria(prefix) {
            const ids = categoriasMarcadas(prefix);
            document.getElementById(prefix + '_id_categoria').value = ids.length ? ids[0] : '';
        }

        // Pinta las filas de variantes según el tipo elegido
        function renderVariantes(prefix, tipo) {
            const container = document.getElementById(prefix + '_variantes_rows');
            const titulo = document.getElementById(prefix + '_grupo_variantes_titulo');
            if (!container) return;
            container.innerHTML = '';
            if (titulo) titulo.textContent = NOMBRE_TIPO[tipo] || DATA_TIPOS[tipo] || 'Configurar Variantes';

            if (!tipo) return;

            if (esTipoEtiqueta(tipo)) {
                if (prefix === 'new') addFilaLibre(prefix, null, null, null, null, null, null);
                const btnAdd = document.createElement('div');
                btnAdd.className = 'text-center mt-1';
                btnAdd.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary" id="' + prefix + '_add_libre">+ Añadir etiqueta</button>';
                container.appendChild(btnAdd);
                const addBtn = document.getElementById(prefix + '_add_libre');
                if (addBtn) addBtn.addEventListener('click', () => addFilaLibre(prefix, null, null, null, null, null, null));
                return;
            }

            (DATA_OPCIONES[tipo] || []).forEach(op => {
                const row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 p-2 rounded-3 var-row';
                row.style.cssText = 'background:#f9fafb; border:1px solid #e5e7eb;';
                row.innerHTML = `
                    <input type="checkbox" class="form-check-input var-check flex-shrink-0"
                           data-dim="${op.id}" data-nombre="${op.nombre}">
                    <label class="fw-semibold mb-0 flex-shrink-0" style="font-size:13px; min-width:70px;">${op.nombre}</label>
                    <input type="number" class="form-control form-control-sm var-precio" step="0.01" min="0" placeholder="Precio" style="max-width:100px;" disabled>
                    <input type="number" class="form-control form-control-sm var-costo" step="0.01" min="0" placeholder="Costo" style="max-width:100px;" disabled>
                    <input type="number" class="form-control form-control-sm var-comision" step="0.01" min="0" placeholder="Comisión" style="max-width:100px;" disabled>
                    <input type="number" class="form-control form-control-sm var-stock" step="1" min="0" placeholder="Stock" style="max-width:80px;" disabled>
                    <input type="hidden" class="var-idproducto" value="">
                `;
                container.appendChild(row);
                const chk = row.querySelector('.var-check');
                chk.addEventListener('change', () => {
                    row.querySelectorAll('input[type="number"]').forEach(i => i.disabled = !chk.checked);
                });
            });
        }

        // Fila de variante libre (etiqueta escrita)
        function addFilaLibre(prefix, nombre, precio, costo, comision, stock, idProducto) {
            const container = document.getElementById(prefix + '_variantes_rows');
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 p-2 rounded-3 var-row';
            row.style.cssText = 'background:#f9fafb; border:1px solid #e5e7eb;';
            row.innerHTML = `
                <input type="text" class="form-control form-control-sm var-libre-nombre" placeholder="Etiqueta (ej. Azul, Rojo)" value="${nombre || ''}" style="max-width:150px;">
                <input type="number" class="form-control form-control-sm var-precio" step="0.01" min="0" placeholder="Precio" value="${precio ?? ''}" style="max-width:100px;">
                <input type="number" class="form-control form-control-sm var-costo" step="0.01" min="0" placeholder="Costo" value="${costo ?? ''}" style="max-width:100px;">
                <input type="number" class="form-control form-control-sm var-comision" step="0.01" min="0" placeholder="Comisión" value="${comision ?? ''}" style="max-width:100px;">
                <input type="number" class="form-control form-control-sm var-stock" step="1" min="0" placeholder="Stock" value="${stock ?? ''}" style="max-width:80px;">
                <input type="hidden" class="var-idproducto" value="${idProducto || ''}">
                <button type="button" class="btn btn-sm btn-outline-danger var-libre-del" title="Quitar"><i class="bi bi-x-lg"></i></button>
            `;
            const btnAdd = document.getElementById(prefix + '_add_libre');
            const refNode = btnAdd ? btnAdd.closest('.text-center') : null;
            if (refNode && container.contains(refNode)) container.insertBefore(row, refNode);
            else container.appendChild(row);
            row.querySelector('.var-libre-del').addEventListener('click', () => row.remove());
        }

        // Actualiza visibilidad de todo el formulario según tipo + categorías
        function updateForm(prefix) {
            const tipo = document.getElementById(prefix + '_tipo_variante').value || '';
            const grupoVariantes = document.getElementById(prefix + '_grupo_variantes');
            const panelPrecio = document.getElementById(prefix + '_campos_precio_stock');
            const esPadreEdit = (prefix === 'edit' && document.querySelector('.edit-producto-btn[data-id="' + document.getElementById('edit_id').value + '"]')?.dataset.espadre === "1");

            if (tipo && (prefix === 'new' || esPadreEdit)) {
                if (grupoVariantes) grupoVariantes.classList.remove('d-none');
                if (panelPrecio) panelPrecio.classList.add('d-none');
            } else {
                if (grupoVariantes) grupoVariantes.classList.add('d-none');
                if (panelPrecio) panelPrecio.classList.remove('d-none');
            }
            renderVariantes(prefix, tipo);
        }

        // Eventos de checkboxes de categoría
        document.querySelectorAll('.cat-check').forEach(chk => {
            chk.addEventListener('change', () => actualizarPrimaria(chk.dataset.prefix));
        });

        // Buscador de categorías (nuevo y editar)
        ['new', 'edit'].forEach(prefix => {
            const input = document.getElementById(prefix + '_buscar_cat');
            const lista = document.getElementById(prefix + '_cat_lista');
            if (input && lista) {
                input.addEventListener('input', () => {
                    const q = input.value.toLowerCase().trim();
                    lista.querySelectorAll('.cat-item').forEach(item => {
                        item.style.display = item.dataset.nombre.toLowerCase().includes(q) ? '' : 'none';
                    });
                });
            }
        });

        // --- Modal NUEVO ---
        const formNuevo = document.getElementById('formNuevoProducto');
        if (formNuevo) {
            document.getElementById('nuevoProductoModal').addEventListener('show.bs.modal', () => {
                // reset
                document.getElementById('new_tipo_variante').value = '';
                document.querySelectorAll('.cat-check[data-prefix="new"]').forEach(c => c.checked = false);
                actualizarPrimaria('new');
                updateForm('new');
            });

            // Aplicar precio/costo/comisión a todos
            document.getElementById('new_aplicar_precio_todos').addEventListener('click', () => {
                const precio = document.getElementById('new_precio_base_variante').value;
                const costo = document.getElementById('new_costo_base_variante').value;
                const comision = document.getElementById('new_comision_base_variante').value;
                document.querySelectorAll('#new_variantes_rows .var-row').forEach(row => {
                    const sel = row.querySelector('.var-check');
                    if (sel && sel.checked) {
                        if (precio !== '') row.querySelector('.var-precio').value = precio;
                        if (costo !== '') row.querySelector('.var-costo').value = costo;
                        if (comision !== '') row.querySelector('.var-comision').value = comision;
                    }
                });
            });

            formNuevo.addEventListener('submit', async (e) => {
                e.preventDefault();
                const nombre = document.getElementById('new_nombre').value.trim();
                const id_categoria = document.getElementById('new_id_categoria').value;
                const id_unidad = document.getElementById('new_unidad').value;
                const tipo = document.getElementById('new_tipo_variante').value;

                if (!id_categoria || !id_unidad || !nombre) {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Completa nombre, al menos una categoría y la unidad.' });
                    return;
                }

                const payload = new FormData();
                payload.append('nombre', nombre);
                payload.append('id_categoria', id_categoria);
                payload.append('id_unidad', id_unidad);
                categoriasMarcadas('new').forEach(id => payload.append('categorias[]', id));

                let endpoint = './controllers/C_Producto.php?action=crear';

                if (tipo) {
                    endpoint = './controllers/C_Producto.php?action=crear_con_variantes';
                    payload.append('tipo_variante', tipo);
                    let idx = 0;
                    const rows = document.querySelectorAll('#new_variantes_rows .var-row');
                    rows.forEach(row => {
                        if (tipo === 'libre') {
                            const etiqueta = row.querySelector('.var-libre-nombre').value.trim();
                            if (!etiqueta) return;
                            payload.append(`variantes[${idx}][nombre_variante]`, etiqueta);
                        } else if (esTipoEtiqueta(tipo)) {
                            const chk = row.querySelector('.var-check');
                            if (!chk || !chk.checked) return;
                            payload.append(`variantes[${idx}][nombre_variante]`, chk.dataset.nombre);
                        } else {
                            const chk = row.querySelector('.var-check');
                            if (!chk.checked) return;
                            payload.append(`variantes[${idx}][id_dimension]`, chk.dataset.dim);
                        }
                        payload.append(`variantes[${idx}][precio_unitario]`, row.querySelector('.var-precio').value || 0);
                        payload.append(`variantes[${idx}][costo_produccion]`, row.querySelector('.var-costo').value || 0);
                        payload.append(`variantes[${idx}][comision]`, row.querySelector('.var-comision').value || 0);
                        payload.append(`variantes[${idx}][stock_piezas]`, row.querySelector('.var-stock').value || 0);
                        idx++;
                    });
                    if (idx === 0) {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debes configurar al menos una variante.' });
                        return;
                    }
                } else {
                    payload.append('precio_unitario', document.getElementById('new_precio').value);
                    payload.append('costo_produccion', document.getElementById('new_costo').value);
                    payload.append('comision', document.getElementById('new_comision').value || 0);
                    payload.append('stock', document.getElementById('new_stock').value);
                    payload.append('stock_ilimitado', document.getElementById('new_stock_ilimitado').checked ? 1 : 0);
                }

                const fileInput = document.getElementById('new_imagen');
                const file = (fileInput && fileInput.files.length > 0) ? fileInput.files[0] : null;
                if (file) payload.append('imagen', file);

                try {
                    const response = await fetch(endpoint, { method: 'POST', body: payload });
                    const data = await response.json();
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: '¡Creado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // --- Modal EDITAR ---
        const editModal = new bootstrap.Modal(document.getElementById('editarProductoModal'));
        const selectUni = document.getElementById('edit_unidad');

        // Mapa nombre de categoría -> id (para ubicar la primaria en la edición)
        const catNameToId = {};
        document.querySelectorAll('.cat-check[data-prefix="edit"]').forEach(c => { catNameToId[c.dataset.nombre] = c.value; });

        // Poblar filas de variantes desde los botones hijos del padre en edición
        function populateVariantes(btn, tipo) {
            const children = document.querySelectorAll(`.edit-producto-btn[data-padreid="${btn.dataset.id}"]`);
            if (tipo === 'libre') {
                children.forEach(childBtn => {
                    addFilaLibre('edit', childBtn.dataset.etiqueta, childBtn.dataset.precio, childBtn.dataset.costo, childBtn.dataset.comision, null, childBtn.dataset.id);
                });
                return;
            }
            document.querySelectorAll('#edit_variantes_rows .var-row').forEach(row => {
                row.querySelectorAll('input[type="number"]').forEach(i => i.disabled = true);
            });
            const esCustom = esTipoEtiqueta(tipo);
            children.forEach(childBtn => {
                // Tipos personalizados (v{id}) matchean por nombre de opción; los demás por id_dimension
                const chk = esCustom
                    ? Array.from(document.querySelectorAll('#edit_variantes_rows .var-check')).find(c => c.dataset.nombre === childBtn.dataset.etiqueta)
                    : document.querySelector(`#edit_variantes_rows .var-check[data-dim="${childBtn.dataset.talla || childBtn.dataset.tipocorbata || childBtn.dataset.bimestre}"]`);
                if (chk) {
                    const row = chk.closest('.var-row');
                    chk.checked = true;
                    row.querySelector('.var-precio').value = childBtn.dataset.precio;
                    row.querySelector('.var-costo').value = childBtn.dataset.costo;
                    row.querySelector('.var-comision').value = childBtn.dataset.comision || 0;
                    row.querySelector('.var-stock').value = '';
                    row.querySelectorAll('input[type="number"]').forEach(i => i.disabled = false);
                    row.querySelector('.var-idproducto').value = childBtn.dataset.id;
                }
            });
        }

        document.querySelectorAll('.edit-producto-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const isPadre = btn.dataset.espadre === "1";
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_nombre').value = btn.dataset.nombre;

                // Reset de categorías y tipo
                document.querySelectorAll('.cat-check[data-prefix="edit"]').forEach(c => c.checked = false);
                document.getElementById('edit_tipo_variante').value = '';
                renderVariantes('edit', '');

                // Marcar categorías (pivote) y primaria
                const cats = (btn.dataset.categorias || '').split(',').filter(x => x !== '');
                cats.forEach(id => {
                    const chk = document.getElementById('edit_cat_' + id);
                    if (chk) chk.checked = true;
                });
                const idPrimaria = catNameToId[btn.dataset.categoria];
                document.getElementById('edit_id_categoria').value = idPrimaria || (cats.length ? cats[0] : '');

                if (isPadre) {
                    document.getElementById('edit_campos_precio_stock').classList.add('d-none');
                    document.getElementById('editarProductoModalLabel').textContent = "Editar Familia de Producto";
                    document.getElementById('edit_stock_ilimitado').checked = false;
                    const tipo = btn.dataset.tipo || 'talla';
                    document.getElementById('edit_tipo_variante').value = tipo;
                    updateForm('edit');
                    populateVariantes(btn, tipo);
                } else {
                    document.getElementById('edit_campos_precio_stock').classList.remove('d-none');
                    document.getElementById('editarProductoModalLabel').textContent = "Editar Producto";
                    document.getElementById('edit_precio').value = btn.dataset.precio;
                    document.getElementById('edit_costo').value = btn.dataset.costo;
                    document.getElementById('edit_comision').value = btn.dataset.comision || 0;
                    document.getElementById('edit_stock').value = btn.dataset.stock;
                    const chkEditIlimitado = document.getElementById('edit_stock_ilimitado');
                    chkEditIlimitado.checked = btn.dataset.stockilimitado === "1";
                    chkEditIlimitado.dispatchEvent(new Event('change'));
                    // Preservar tipo de variante de un hijo (no se muestra el panel)
                    document.getElementById('edit_tipo_variante').value = btn.dataset.tipo || '';
                }

                // Previsualizar imagen
                const imagen = btn.dataset.imagen;
                const previewDiv = document.getElementById('edit_imagen_preview');
                const previewImg = document.getElementById('edit_imagen_img');
                document.getElementById('edit_imagen').value = '';
                if (imagen && imagen !== '' && imagen !== 'null') {
                    previewImg.src = `./assets/productos/${imagen}`;
                    previewDiv.classList.remove('d-none');
                } else {
                    previewDiv.classList.add('d-none');
                    previewImg.src = '';
                }

                Array.from(selectUni.options).forEach(opt => {
                    if (opt.text.startsWith(btn.dataset.unidad)) opt.selected = true;
                });

                document.getElementById('edit_talla').value = btn.dataset.talla;
                document.getElementById('edit_tipo_corbata').value = btn.dataset.tipocorbata;

                editModal.show();
            });
        });

        document.getElementById('edit_aplicar_precio_todos').addEventListener('click', () => {
            const precio = document.getElementById('edit_precio_base_variante').value;
            const costo = document.getElementById('edit_costo_base_variante').value;
            const comision = document.getElementById('edit_comision_base_variante').value;
            document.querySelectorAll('#edit_variantes_rows .var-row').forEach(row => {
                const sel = row.querySelector('.var-check');
                if (sel && sel.checked) {
                    if (precio !== '') row.querySelector('.var-precio').value = precio;
                    if (costo !== '') row.querySelector('.var-costo').value = costo;
                    if (comision !== '') row.querySelector('.var-comision').value = comision;
                }
            });
        });

        // Guardar edición
        const formEditar = document.getElementById('formEditarProducto');
        if (formEditar) {
            formEditar.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id_producto = document.getElementById('edit_id').value;
                const nombre = document.getElementById('edit_nombre').value.trim();
                const id_categoria = document.getElementById('edit_id_categoria').value;
                const id_unidad = document.getElementById('edit_unidad').value;
                const isPadre = document.querySelector('.edit-producto-btn[data-id="' + id_producto + '"]')?.dataset.espadre === "1";
                const tipo = document.getElementById('edit_tipo_variante').value || '';

                if (!id_categoria || !id_unidad || !nombre) {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Completa nombre, al menos una categoría y la unidad.' });
                    return;
                }

                const formData = new FormData();
                formData.append('id_producto', id_producto);
                formData.append('nombre', nombre);
                formData.append('id_categoria', id_categoria);
                formData.append('id_unidad', id_unidad);
                categoriasMarcadas('edit').forEach(id => formData.append('categorias[]', id));

                if (isPadre) {
                    formData.append('tipo_variante', tipo);
                    let idx = 0;
                    document.querySelectorAll('#edit_variantes_rows .var-row').forEach(row => {
                        if (tipo === 'libre') {
                            const etiqueta = row.querySelector('.var-libre-nombre').value.trim();
                            if (!etiqueta) return;
                            formData.append(`variantes[${idx}][nombre_variante]`, etiqueta);
                        } else if (esTipoEtiqueta(tipo)) {
                            const chk = row.querySelector('.var-check');
                            if (!chk || !chk.checked) return;
                            formData.append(`variantes[${idx}][nombre_variante]`, chk.dataset.nombre);
                        } else {
                            const chk = row.querySelector('.var-check');
                            if (!chk.checked) return;
                            formData.append(`variantes[${idx}][id_dimension]`, chk.dataset.dim);
                        }
                        formData.append(`variantes[${idx}][precio_unitario]`, row.querySelector('.var-precio').value || 0);
                        formData.append(`variantes[${idx}][costo_produccion]`, row.querySelector('.var-costo').value || 0);
                        formData.append(`variantes[${idx}][comision]`, row.querySelector('.var-comision').value || 0);
                        const idChild = row.querySelector('.var-idproducto').value;
                        if (idChild) formData.append(`variantes[${idx}][id_producto]`, idChild);
                        idx++;
                    });
                    if (idx === 0) {
                        Swal.fire({ icon: 'warning', text: 'Debes configurar al menos una variante.' });
                        return;
                    }
                } else {
                    formData.append('precio_unitario', document.getElementById('edit_precio').value);
                    formData.append('costo_produccion', document.getElementById('edit_costo').value);
                    formData.append('comision', document.getElementById('edit_comision').value || 0);
                    formData.append('stock', document.getElementById('edit_stock').value);
                    formData.append('stock_ilimitado', document.getElementById('edit_stock_ilimitado').checked ? 1 : 0);
                    formData.append('tipo_variante', tipo);
                }

                const fileInput = document.getElementById('edit_imagen');
                if (fileInput && fileInput.files.length > 0) {
                    formData.append('imagen', fileInput.files[0]);
                }

                const endpoint = isPadre ? './controllers/C_Producto.php?action=actualizar_con_variantes' : './controllers/C_Producto.php?action=actualizar';

                try {
                    const response = await fetch(endpoint, { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) {
                        editModal.hide();
                        Swal.fire({ icon: 'success', title: '¡Actualizado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // 5. Eliminar lógicamente un producto
        document.querySelectorAll('.delete-producto-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id_producto = btn.dataset.id;
                const nombre = btn.dataset.nombre;

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `Deseas eliminar el producto "${nombre}"`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_Producto.php?action=eliminar', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id_producto })
                            });
                            const data = await response.json();
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: '¡Eliminado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                                    .then(() => window.location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                            }
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                        }
                    }
                });
            });
        });

        // Manejador para Actualizar Stock
        const stockModal = new bootstrap.Modal(document.getElementById('stockModal'));
        const stockMasivoModal = new bootstrap.Modal(document.getElementById('stockMasivoModal'));

        document.querySelectorAll('.update-stock-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.ilimitado === "1") {
                    Swal.fire({ icon: 'info', title: 'Stock ilimitado', text: 'Este producto es un servicio: no administra stock.', confirmButtonColor: '#23284E' });
                    return;
                }
                const esPadre = btn.dataset.espadre === "1";
                const id_producto = btn.dataset.id;

                if (esPadre) {
                    document.getElementById('stock_masivo_id_padre').value = id_producto;
                    document.getElementById('stockMasivoSubtitle').textContent = btn.dataset.nombre;

                    const tbody = document.getElementById('stockMasivoTbody');
                    tbody.innerHTML = '';

                    const hijos = document.querySelectorAll(`.update-stock-btn[data-padreid="${id_producto}"]`);
                    hijos.forEach(hijoBtn => {
                        const tr = document.createElement('tr');
                        tr.className = "border-bottom";
                        tr.innerHTML = `
                            <td class="px-3 fw-bold text-primary">${hijoBtn.dataset.etiqueta}</td>
                            <td class="px-3 text-end fw-semibold">${parseFloat(hijoBtn.dataset.stock).toLocaleString('es-PE')}</td>
                            <td class="px-3">
                                <input type="number" class="form-control text-end masivo-cantidad-input"
                                       data-id="${hijoBtn.dataset.id}" data-precio="${hijoBtn.dataset.precio || 0}"
                                       min="0" step="1" placeholder="0" style="border-radius:8px;">
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    if (hijos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-muted">No se encontraron variantes para este producto.</td></tr>`;
                    }

                    document.getElementById('stock_masivo_referencia').value = '';
                    setTipoStockMasivo('entrada');
                    stockMasivoModal.show();
                } else {
                    document.getElementById('stock_id_producto').value = btn.dataset.id;
                    document.getElementById('stock_precio_unit').value = btn.dataset.precio || 0;
                    document.getElementById('stockModalSubtitle').textContent = btn.dataset.nombre;
                    document.getElementById('stock_actual_display').textContent = parseFloat(btn.dataset.stock).toLocaleString('es-PE') + ' und.';
                    document.getElementById('stock_cantidad').value = '';
                    document.getElementById('stock_referencia').value = '';
                    document.getElementById('stock_motivo_select').value = '';
                    document.getElementById('stock_motivo_otro').value = '';
                    document.getElementById('stock_motivo_otro').classList.add('d-none');
                    setTipoStock('entrada');
                    stockModal.show();
                }
            });
        });

        window.setTipoStock = function(tipo) {
            document.getElementById('stock_tipo').value = tipo;
            const btnEntrada = document.getElementById('btnTipoEntrada');
            const btnSalida  = document.getElementById('btnTipoSalida');
            const campoBoleta = document.getElementById('campo_boleta');
            const campoMotivo = document.getElementById('campo_motivo');
            const header      = document.getElementById('stockModalHeader');
            const btnConfirmar = document.getElementById('btnConfirmarStock');

            if (tipo === 'entrada') {
                btnEntrada.style.cssText = 'border-radius:10px; border:2px solid #23284E; background:#eef1f7; color:#23284E; font-size:14px; padding:10px;';
                btnSalida.style.cssText  = 'border-radius:10px; border:2px solid #e5e7eb; background:#f9fafb; color:#6b7280; font-size:14px; padding:10px;';
                campoBoleta.classList.remove('d-none');
                campoMotivo.classList.add('d-none');
                header.style.background = '#23284E';
                btnConfirmar.style.background = '';
            } else {
                btnSalida.style.cssText  = 'border-radius:10px; border:2px solid #ef4444; background:#fef2f2; color:#ef4444; font-size:14px; padding:10px;';
                btnEntrada.style.cssText = 'border-radius:10px; border:2px solid #e5e7eb; background:#f9fafb; color:#6b7280; font-size:14px; padding:10px;';
                campoBoleta.classList.add('d-none');
                campoMotivo.classList.remove('d-none');
                header.style.background = '#ef4444';
                btnConfirmar.style.background = '#ef4444';
            }
        };

        window.setTipoStockMasivo = function(tipo) {
            document.getElementById('stock_masivo_tipo').value = tipo;
            const btnEntrada = document.getElementById('btnTipoMasivoEntrada');
            const btnSalida  = document.getElementById('btnTipoMasivoSalida');
            const campoBoleta = document.getElementById('campo_masivo_boleta');
            const campoMotivo = document.getElementById('campo_masivo_motivo');
            const header      = document.getElementById('stockMasivoModalHeader');
            const btnConfirmar = document.getElementById('btnConfirmarStockMasivo');
            const spanAccion  = document.getElementById('masivoAccionTexto');

            if (tipo === 'entrada') {
                btnEntrada.style.cssText = 'border-radius:10px; border:2px solid #23284E; background:#eef1f7; color:#23284E; font-size:14px; padding:10px;';
                btnSalida.style.cssText  = 'border-radius:10px; border:2px solid #e5e7eb; background:#f9fafb; color:#6b7280; font-size:14px; padding:10px;';
                campoBoleta.classList.remove('d-none');
                campoMotivo.classList.add('d-none');
                header.style.background = '#23284E';
                btnConfirmar.style.background = '';
                spanAccion.textContent = 'Sumar';
            } else {
                btnSalida.style.cssText  = 'border-radius:10px; border:2px solid #ef4444; background:#fef2f2; color:#ef4444; font-size:14px; padding:10px;';
                btnEntrada.style.cssText = 'border-radius:10px; border:2px solid #e5e7eb; background:#f9fafb; color:#6b7280; font-size:14px; padding:10px;';
                campoBoleta.classList.add('d-none');
                campoMotivo.classList.remove('d-none');
                header.style.background = '#ef4444';
                btnConfirmar.style.background = '#ef4444';
                spanAccion.textContent = 'Restar';
            }
        };

        window.toggleMotivoOtro = function() {
            const sel = document.getElementById('stock_motivo_select');
            const otro = document.getElementById('stock_motivo_otro');
            otro.classList.toggle('d-none', sel.value !== 'Otro');
        };

        document.getElementById('formStock').addEventListener('submit', async (e) => {
            e.preventDefault();
            const tipo = document.getElementById('stock_tipo').value;
            const cantidad = parseFloat(document.getElementById('stock_cantidad').value);
            const id_producto = document.getElementById('stock_id_producto').value;
            const precio_unitario = parseFloat(document.getElementById('stock_precio_unit').value) || 0;

            let referencia = '';
            let concepto = '';

            if (tipo === 'entrada') {
                referencia = document.getElementById('stock_referencia').value.trim();
                concepto = referencia ? `Entrada de stock — Boleta ${referencia}` : 'Entrada de stock manual';
            } else {
                const motSel = document.getElementById('stock_motivo_select').value;
                const motOtro = document.getElementById('stock_motivo_otro').value.trim();
                const motivo = motSel === 'Otro' ? motOtro : motSel;
                if (!motivo) { Swal.fire({ icon:'warning', title:'Falta motivo', text:'Debes indicar el motivo del descuento.' }); return; }
                referencia = motivo;
                concepto = `Salida de stock — ${motivo}`;
            }

            if (!cantidad || cantidad <= 0) {
                Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'Ingresa una cantidad mayor a 0.' });
                return;
            }

            try {
                const response = await fetch('./controllers/C_Kardex.php?action=registrar_movimiento', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_producto, tipo, cantidad, precio_unitario, referencia, concepto })
                });
                const data = await response.json();
                if (data.success) {
                    stockModal.hide();
                    Swal.fire({ icon: 'success', title: '¡Registrado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
            }
        });

        const formStockMasivo = document.getElementById('formStockMasivo');
        if (formStockMasivo) {
            formStockMasivo.addEventListener('submit', async (e) => {
                e.preventDefault();
                const tipo = document.getElementById('stock_masivo_tipo').value;

                let referencia = '';
                let concepto = '';

                if (tipo === 'entrada') {
                    referencia = document.getElementById('stock_masivo_referencia').value.trim();
                    concepto = referencia ? `Entrada de stock — Boleta ${referencia}` : 'Entrada de stock manual';
                } else {
                    const motivo = document.getElementById('stock_masivo_motivo_select').value;
                    referencia = motivo;
                    concepto = `Salida de stock — ${motivo}`;
                }

                const inputs = document.querySelectorAll('.masivo-cantidad-input');
                const updates = [];

                inputs.forEach(input => {
                    const val = parseFloat(input.value);
                    if (val && val > 0) {
                        updates.push({ id_producto: input.dataset.id, precio_unitario: input.dataset.precio, cantidad: val });
                    }
                });

                if (updates.length === 0) {
                    Swal.fire({ icon: 'warning', text: 'Debes ingresar al menos una cantidad mayor a 0.' });
                    return;
                }

                const btnConfirmar = document.getElementById('btnConfirmarStockMasivo');
                const originalHtml = btnConfirmar.innerHTML;
                btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...';
                btnConfirmar.disabled = true;

                try {
                    let successes = 0;
                    for (const up of updates) {
                        const res = await fetch('./controllers/C_Kardex.php?action=registrar_movimiento', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                id_producto: up.id_producto,
                                tipo: tipo,
                                cantidad: up.cantidad,
                                precio_unitario: parseFloat(up.precio_unitario) || 0,
                                referencia: referencia,
                                concepto: concepto
                            })
                        });
                        const data = await res.json();
                        if (data.success) successes++;
                    }
                    stockMasivoModal.hide();
                    Swal.fire({ icon: 'success', title: '¡Actualizado!', text: `Se actualizaron ${successes} variantes correctamente.`, showConfirmButton: false, timer: 1500 })
                        .then(() => window.location.reload());
                } catch (error) {
                    btnConfirmar.innerHTML = originalHtml;
                    btnConfirmar.disabled = false;
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Hubo un error al actualizar algunas variantes.' });
                }
            });
        }

        // Exponer funciones globales usadas por atributos onchange
        window.updateForm = updateForm;
        <?php endif; ?>
    });
</script>
