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

$modelProducto = M_Producto::singleton();
$productos = $modelProducto->listar();

$modelCat = M_Categoria::singleton();
$categorias = $modelCat->listar();

$modelUni = M_Unidad::singleton();
$unidades = $modelUni->listar();

$tallas = $modelProducto->obtenerTallas();
$tiposCorbata = $modelProducto->obtenerTiposCorbata();
$niveles = $modelProducto->obtenerNiveles();
$grados = $modelProducto->obtenerGrados();
$areas = $modelProducto->obtenerAreas();
$bimestres = $modelProducto->obtenerBimestres();

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
            // Pre-calcular tallas por padre para los tooltips
            $tallasPorPadre = [];
            foreach ($productos as $ins) {
                if ($ins['id_producto_padre']) {
                    $id_padre = $ins['id_producto_padre'];
                    if (!isset($tallasPorPadre[$id_padre])) $tallasPorPadre[$id_padre] = [];
                    if (!empty($ins['talla'])) {
                        $tallasPorPadre[$id_padre][] = $ins['talla'];
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
                                    <?php endif; ?>
                                    <?php if ($esPadre): 
                                        $listaTallas = isset($tallasPorPadre[$ins['id_producto']]) ? implode(', ', $tallasPorPadre[$ins['id_producto']]) : 'Sin tallas';
                                    ?>
                                        <span class="text-primary fw-bold" style="cursor:pointer;" data-bs-toggle="tooltip" title="Tallas: <?php echo htmlspecialchars($listaTallas); ?>">
                                            <i class="bi bi-info-circle-fill me-1"></i>Ver Tallas
                                        </span>
                                    <?php elseif ($ins['categoria'] === 'Uniformes' || $esHijo): ?>
                                        <small>Talla: <strong class="<?php echo $esHijo ? 'text-primary' : ''; ?>"><?php echo htmlspecialchars($ins['talla'] ?? '-'); ?></strong></small>
                                        <?php if ($ins['tipo_corbata']): ?>
                                            <br><small>Corbata: <?php echo htmlspecialchars($ins['tipo_corbata']); ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($ins['categoria'] === 'Módulos'): ?>
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
                                                    data-talla="<?php echo htmlspecialchars($ins['talla'] ?? '-'); ?>"
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
                    <h6 class="modal-title fw-bold text-white mb-0" id="stockMasivoModalLabel">Actualizar Stock por Tallas</h6>
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

                    <!-- Tabla de Tallas -->
                    <label class="form-label fw-semibold" style="font-size:13px;">Cantidades por Talla</label>
                    <div class="table-responsive" style="border: 1px solid #e5e7eb; border-radius: 10px;">
                        <table class="table table-borderless align-middle mb-0 text-sm">
                            <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                <tr>
                                    <th class="py-3 px-3 text-muted">Talla</th>
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
    <div class="modal-dialog modal-dialog-centered">
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
                            <label for="new_categoria" class="form-label fw-semibold" style="font-size: 13px;">Categoría</label>
                            <select class="form-select" id="new_categoria" required onchange="toggleGroups('new')">
                                <option value="" disabled selected>Seleccionar</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" data-name="<?php echo htmlspecialchars($cat['nombre']); ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
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

                    <!-- Toggle: Variantes de Talla -->
                    <div class="mt-3 p-3 rounded-3" style="background:#f8fafc; border:1.5px solid #e2e8f0;">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="new_tiene_variantes" role="switch" style="cursor:pointer;">
                            <label class="form-check-label fw-semibold" for="new_tiene_variantes" style="font-size:13px; cursor:pointer;">
                                <i class="bi bi-rulers me-1 text-primary"></i> Este producto se vende en múltiples tallas
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size:11px;">Al activar esta opción, los campos de precio y stock se configuran por talla.</small>
                    </div>

                    <!-- Panel de Variantes por Talla (oculto por defecto) -->
                    <div id="new_grupo_variantes" class="mt-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0" style="font-size:13px;">Configurar Tallas</h6>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" id="new_precio_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Precio base" style="width:110px;">
                                <input type="number" id="new_costo_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Costo base" style="width:110px;">
                                <input type="number" id="new_comision_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Comisión base" style="width:110px;">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="new_aplicar_precio_todos" style="font-size:12px; white-space:nowrap;">Aplicar a todos</button>
                            </div>
                        </div>
                        <div id="new_tallas_container" class="d-flex flex-column gap-2">
                            <?php foreach ($tallas as $t): ?>
                            <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background:#f9fafb; border:1px solid #e5e7eb;">
                                <input type="checkbox" class="form-check-input new-talla-check flex-shrink-0"
                                       id="new_talla_check_<?= $t['id_talla'] ?>"
                                       value="<?= $t['id_talla'] ?>"
                                       data-nombre="<?= htmlspecialchars($t['nombre']) ?>">
                                <label class="fw-semibold mb-0 flex-shrink-0" style="font-size:13px; min-width:50px;" for="new_talla_check_<?= $t['id_talla'] ?>">
                                    <?= htmlspecialchars($t['nombre']) ?>
                                </label>
                                <input type="number" class="form-control form-control-sm new-talla-precio" step="0.01" min="0" placeholder="Precio" style="max-width:100px;" disabled>
                                <input type="number" class="form-control form-control-sm new-talla-costo" step="0.01" min="0" placeholder="Costo" style="max-width:100px;" disabled>
                                <input type="number" class="form-control form-control-sm new-talla-comision" step="0.01" min="0" placeholder="Comisión" style="max-width:100px;" disabled>
                                <input type="number" class="form-control form-control-sm new-talla-stock" step="1" min="0" placeholder="Stock" style="max-width:80px;" disabled>
                            </div>
                            <?php endforeach; ?>
                        </div>
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

                    <!-- Grupo Módulos -->
                    <div id="new_grupo_modulos" style="display: none;" class="mt-3 p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-3" style="font-size: 13px;">Atributos de Módulo</h6>
                        <div class="row mb-2">
                            <div class="col-6">
                                <label for="new_nivel" class="form-label fw-semibold" style="font-size: 13px;">Nivel</label>
                                <select class="form-select" id="new_nivel">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($niveles as $n): ?>
                                        <option value="<?php echo $n['id_nivel']; ?>"><?php echo htmlspecialchars($n['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="new_grado" class="form-label fw-semibold" style="font-size: 13px;">Grado</label>
                                <select class="form-select" id="new_grado">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($grados as $g): ?>
                                        <option value="<?php echo $g['id_grado']; ?>"><?php echo htmlspecialchars($g['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label for="new_area" class="form-label fw-semibold" style="font-size: 13px;">Área / Curso</label>
                                <select class="form-select" id="new_area">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($areas as $a): ?>
                                        <option value="<?php echo $a['id_area']; ?>"><?php echo htmlspecialchars($a['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="new_bimestre" class="form-label fw-semibold" style="font-size: 13px;">Bimestre</label>
                                <select class="form-select" id="new_bimestre">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($bimestres as $b): ?>
                                        <option value="<?php echo $b['id_bimestre']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
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
    <div class="modal-dialog modal-dialog-centered">
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
                            <label for="edit_categoria" class="form-label fw-semibold" style="font-size: 13px;">Categoría</label>
                            <select class="form-select" id="edit_categoria" required onchange="toggleGroups('edit')">
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" data-name="<?php echo htmlspecialchars($cat['nombre']); ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
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

                    <!-- Panel de Variantes por Talla para Editar -->
                    <div id="edit_grupo_variantes" class="mt-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0" style="font-size:13px;">Configurar Tallas</h6>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" id="edit_precio_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Precio base" style="width:110px;">
                                <input type="number" id="edit_costo_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Costo base" style="width:110px;">
                                <input type="number" id="edit_comision_base_variante" class="form-control form-control-sm" step="0.01" min="0" placeholder="Comisión base" style="width:110px;">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="edit_aplicar_precio_todos" style="font-size:12px; white-space:nowrap;">Aplicar a todos</button>
                            </div>
                        </div>
                        <div id="edit_tallas_container" class="d-flex flex-column gap-2">
                            <?php foreach ($tallas as $t): ?>
                            <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background:#f9fafb; border:1px solid #e5e7eb;">
                                <input type="checkbox" class="form-check-input edit-talla-check flex-shrink-0"
                                       id="edit_talla_check_<?= $t['id_talla'] ?>"
                                       value="<?= $t['id_talla'] ?>"
                                       data-nombre="<?= htmlspecialchars($t['nombre']) ?>">
                                <label class="fw-semibold mb-0 flex-shrink-0" style="font-size:13px; min-width:50px;" for="edit_talla_check_<?= $t['id_talla'] ?>">
                                    <?= htmlspecialchars($t['nombre']) ?>
                                </label>
                                <input type="number" class="form-control form-control-sm edit-talla-precio" step="0.01" min="0" placeholder="Precio" style="max-width:100px;" disabled>
                                <input type="number" class="form-control form-control-sm edit-talla-costo" step="0.01" min="0" placeholder="Costo" style="max-width:100px;" disabled>
                                <input type="number" class="form-control form-control-sm edit-talla-comision" step="0.01" min="0" placeholder="Comisión" style="max-width:100px;" disabled>
                                <input type="hidden" class="edit-talla-idproducto" value="">
                            </div>
                            <?php endforeach; ?>
                        </div>
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

                    <!-- Grupo Módulos -->
                    <div id="edit_grupo_modulos" style="display: none;" class="mt-3 p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-3" style="font-size: 13px;">Atributos de Módulo</h6>
                        <div class="row mb-2">
                            <div class="col-6">
                                <label for="edit_nivel" class="form-label fw-semibold" style="font-size: 13px;">Nivel</label>
                                <select class="form-select" id="edit_nivel">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($niveles as $n): ?>
                                        <option value="<?php echo $n['id_nivel']; ?>"><?php echo htmlspecialchars($n['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="edit_grado" class="form-label fw-semibold" style="font-size: 13px;">Grado</label>
                                <select class="form-select" id="edit_grado">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($grados as $g): ?>
                                        <option value="<?php echo $g['id_grado']; ?>"><?php echo htmlspecialchars($g['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label for="edit_area" class="form-label fw-semibold" style="font-size: 13px;">Área / Curso</label>
                                <select class="form-select" id="edit_area">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($areas as $a): ?>
                                        <option value="<?php echo $a['id_area']; ?>"><?php echo htmlspecialchars($a['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="edit_bimestre" class="form-label fw-semibold" style="font-size: 13px;">Bimestre</label>
                                <select class="form-select" id="edit_bimestre">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($bimestres as $b): ?>
                                        <option value="<?php echo $b['id_bimestre']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
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
                    const text = row.innerText.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        <?php if ($isAdmin): ?>
        // --- Lógica del Panel de Variantes ---
        const chkTieneVariantes = document.getElementById('new_tiene_variantes');
        const grupoVariantes = document.getElementById('new_grupo_variantes');
        const panelPrecioStock = document.getElementById('new_campos_precio_stock');
        const tallaChecks = document.querySelectorAll('.new-talla-check');

        if (chkTieneVariantes) {
            chkTieneVariantes.addEventListener('change', function() {
                if (this.checked) {
                    grupoVariantes.classList.remove('d-none');
                    panelPrecioStock.classList.add('d-none');
                    document.getElementById('new_precio').removeAttribute('required');
                    document.getElementById('new_costo').removeAttribute('required');
                    document.getElementById('new_stock').removeAttribute('required');
                } else {
                    grupoVariantes.classList.add('d-none');
                    panelPrecioStock.classList.remove('d-none');
                    document.getElementById('new_precio').setAttribute('required', 'required');
                    document.getElementById('new_costo').setAttribute('required', 'required');
                    document.getElementById('new_stock').setAttribute('required', 'required');
                }
            });
        }

        const chkNuevoIlimitado = document.getElementById('new_stock_ilimitado');
        if (chkNuevoIlimitado) {
            chkNuevoIlimitado.addEventListener('change', function() {
                const stockInput = document.getElementById('new_stock');
                if (this.checked) {
                    stockInput.value = '0';
                    stockInput.disabled = true;
                    stockInput.setAttribute('title', 'Stock ilimitado: este producto no administra stock');
                } else {
                    stockInput.disabled = false;
                    stockInput.removeAttribute('title');
                }
            });
        }

        tallaChecks.forEach(chk => {
            chk.addEventListener('change', function() {
                const container = this.closest('div');
                const inputs = container.querySelectorAll('input[type="number"]');
                inputs.forEach(input => input.disabled = !this.checked);
            });
        });

        const btnAplicarTodos = document.getElementById('new_aplicar_precio_todos');
        if (btnAplicarTodos) {
            btnAplicarTodos.addEventListener('click', () => {
                const precioBase = document.getElementById('new_precio_base_variante').value;
                const costoBase = document.getElementById('new_costo_base_variante').value;
                const comisionBase = document.getElementById('new_comision_base_variante').value;
                tallaChecks.forEach(chk => {
                    if (chk.checked) {
                        const container = chk.closest('div');
                        if (precioBase) container.querySelector('.new-talla-precio').value = precioBase;
                        if (costoBase) container.querySelector('.new-talla-costo').value = costoBase;
                        if (comisionBase) container.querySelector('.new-talla-comision').value = comisionBase;
                    }
                });
            });
        }

         // 2. Registro de Producto por AJAX
        const formNuevo = document.getElementById('formNuevoProducto');
        if (formNuevo) {
            formNuevo.addEventListener('submit', async (e) => {
                e.preventDefault();
                const nombre = document.getElementById('new_nombre').value.trim();
                const id_categoria = document.getElementById('new_categoria').value;
                const id_unidad = document.getElementById('new_unidad').value;

                let payload;
                let isVariantes = chkTieneVariantes && chkTieneVariantes.checked;
                let endpoint = './controllers/C_Producto.php?action=crear';
                let contentType = 'application/json';

                if (isVariantes) {
                    endpoint = './controllers/C_Producto.php?action=crear_con_variantes';
                    let variantes = [];
                    tallaChecks.forEach(chk => {
                        if (chk.checked) {
                            const container = chk.closest('div');
                            variantes.push({
                                id_talla: chk.value,
                                precio_unitario: container.querySelector('.new-talla-precio').value || 0,
                                costo_produccion: container.querySelector('.new-talla-costo').value || 0,
                                comision: container.querySelector('.new-talla-comision').value || 0,
                                stock_piezas: container.querySelector('.new-talla-stock').value || 0
                            });
                        }
                    });

                    if (variantes.length === 0) {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debes seleccionar al menos una talla.' });
                        return;
                    }

                    payload = new FormData();
                    payload.append('nombre', nombre);
                    payload.append('id_categoria', id_categoria);
                    payload.append('id_unidad', id_unidad);
                    
                    // Convertir el array de variantes a JSON en el FormData no es directo en PHP sin decode
                    // Mejor usar JSON pero necesitamos enviar la imagen. 
                    // El controlador PHP para crear_con_variantes espera JSON. Cambiaremos cómo se envía abajo.
                } else {
                    payload = new FormData();
                    payload.append('nombre', nombre);
                    payload.append('id_categoria', id_categoria);
                    payload.append('id_unidad', id_unidad);
                    payload.append('precio_unitario', document.getElementById('new_precio').value);
                    payload.append('costo_produccion', document.getElementById('new_costo').value);
                    payload.append('comision', document.getElementById('new_comision').value || 0);
                    payload.append('stock', document.getElementById('new_stock').value);
                    payload.append('stock_ilimitado', document.getElementById('new_stock_ilimitado').checked ? 1 : 0);
                    
                    if (document.getElementById('new_grupo_uniformes').style.display === 'block') {
                        payload.append('id_talla', document.getElementById('new_talla').value);
                        payload.append('id_tipo_corbata', document.getElementById('new_tipo_corbata').value);
                    }
                    if (document.getElementById('new_grupo_modulos').style.display === 'block') {
                        payload.append('id_nivel', document.getElementById('new_nivel').value);
                        payload.append('id_grado', document.getElementById('new_grado').value);
                        payload.append('id_area', document.getElementById('new_area').value);
                        payload.append('id_bimestre', document.getElementById('new_bimestre').value);
                    }
                }

                // Aquí reconstruimos la lógica de envío
                const fileInput = document.getElementById('new_imagen');
                const file = (fileInput && fileInput.files.length > 0) ? fileInput.files[0] : null;

                try {
                    let response;
                    if (isVariantes) {
                        // Para variantes enviamos JSON (si no hay imagen) o enviamos form data pero no podemos mandar array fácilmente
                        // La mejor forma de mandar el array de variantes en PHP FormData es recorrerlo:
                        payload = new FormData();
                        payload.append('nombre', nombre);
                        payload.append('id_categoria', id_categoria);
                        payload.append('id_unidad', id_unidad);
                        if (file) payload.append('imagen', file);
                        
                        let idx = 0;
                        tallaChecks.forEach(chk => {
                            if (chk.checked) {
                                const container = chk.closest('div');
                                payload.append(`variantes[${idx}][id_talla]`, chk.value);
                                payload.append(`variantes[${idx}][precio_unitario]`, container.querySelector('.new-talla-precio').value || 0);
                                payload.append(`variantes[${idx}][costo_produccion]`, container.querySelector('.new-talla-costo').value || 0);
                                payload.append(`variantes[${idx}][comision]`, container.querySelector('.new-talla-comision').value || 0);
                                payload.append(`variantes[${idx}][stock_piezas]`, container.querySelector('.new-talla-stock').value || 0);
                                idx++;
                            }
                        });

                        response = await fetch(endpoint, { method: 'POST', body: payload });
                    } else {
                        if (file) payload.append('imagen', file);
                        response = await fetch(endpoint, { method: 'POST', body: payload });
                    }
                    
                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Creado!',
                            text: data.mensaje,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.mensaje,
                            confirmButtonColor: '#23284E'
                        });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // 3. Rellenar campos en el modal de Edición
        const editModal = new bootstrap.Modal(document.getElementById('editarProductoModal'));
        const selectCat = document.getElementById('edit_categoria');
        const selectUni = document.getElementById('edit_unidad');

        document.querySelectorAll('.edit-producto-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const isPadre = btn.dataset.espadre === "1";
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_nombre').value = btn.dataset.nombre;

                if (isPadre) {
                    document.getElementById('edit_campos_precio_stock').classList.add('d-none');
                    document.getElementById('edit_grupo_variantes').classList.remove('d-none');
                    document.getElementById('editarProductoModalLabel').textContent = "Editar Familia de Producto";
                    document.getElementById('edit_stock_ilimitado').checked = false;
                    
                    // Limpiar y poblar checkboxes de tallas
                    const idPadre = btn.dataset.id;
                    const children = document.querySelectorAll(`.edit-producto-btn[data-padreid="${idPadre}"]`);
                    const checks = document.querySelectorAll('.edit-talla-check');
                    
                    // Reset all
                    checks.forEach(chk => {
                        chk.checked = false;
                        const container = chk.closest('div');
                        const p = container.querySelector('.edit-talla-precio');
                        const c = container.querySelector('.edit-talla-costo');
                        const co = container.querySelector('.edit-talla-comision');
                        const idHidden = container.querySelector('.edit-talla-idproducto');
                        p.disabled = true; c.disabled = true; co.disabled = true;
                        p.value = ''; c.value = ''; co.value = ''; idHidden.value = '';
                    });

                    // Populate from children
                    children.forEach(childBtn => {
                        const tallaId = childBtn.dataset.talla; // wait, data-talla is currently id_talla! Yes!
                        const chk = document.getElementById(`edit_talla_check_${tallaId}`);
                        if (chk) {
                            chk.checked = true;
                            const container = chk.closest('div');
                            const p = container.querySelector('.edit-talla-precio');
                            const c = container.querySelector('.edit-talla-costo');
                            const co = container.querySelector('.edit-talla-comision');
                            const idHidden = container.querySelector('.edit-talla-idproducto');
                            p.disabled = false; c.disabled = false; co.disabled = false;
                            p.value = childBtn.dataset.precio;
                            c.value = childBtn.dataset.costo;
                            co.value = childBtn.dataset.comision || 0;
                            idHidden.value = childBtn.dataset.id;
                        }
                    });

                } else {
                    document.getElementById('edit_campos_precio_stock').classList.remove('d-none');
                    document.getElementById('edit_grupo_variantes').classList.add('d-none');
                    document.getElementById('editarProductoModalLabel').textContent = "Editar Producto";

                    document.getElementById('edit_precio').value = btn.dataset.precio;
                    document.getElementById('edit_costo').value = btn.dataset.costo;
                    document.getElementById('edit_comision').value = btn.dataset.comision || 0;
                    document.getElementById('edit_stock').value = btn.dataset.stock;
                    const chkEditIlimitado = document.getElementById('edit_stock_ilimitado');
                    chkEditIlimitado.checked = btn.dataset.stockilimitado === "1";
                    chkEditIlimitado.dispatchEvent(new Event('change'));
                }

                // Previsualizar la imagen actual si existe
                const imagen = btn.dataset.imagen;
                const previewDiv = document.getElementById('edit_imagen_preview');
                const previewImg = document.getElementById('edit_imagen_img');
                // Limpiar input file viejo
                document.getElementById('edit_imagen').value = '';
                if (imagen && imagen !== '' && imagen !== 'null') {
                    previewImg.src = `./assets/productos/${imagen}`;
                    previewDiv.classList.remove('d-none');
                } else {
                    previewDiv.classList.add('d-none');
                    previewImg.src = '';
                }

                // Mapear los dropdown de categoría y unidades dinámicamente
                Array.from(selectCat.options).forEach(opt => {
                    if (opt.text === btn.dataset.categoria) opt.selected = true;
                });
                toggleGroups('edit'); // Actualizar visibilidad de grupos
                
                Array.from(selectUni.options).forEach(opt => {
                    if (opt.text.startsWith(btn.dataset.unidad)) opt.selected = true;
                });

                // Mapear atributos (solo relevantes para simples/hijos)
                document.getElementById('edit_talla').value = btn.dataset.talla;
                document.getElementById('edit_tipo_corbata').value = btn.dataset.tipocorbata;
                document.getElementById('edit_nivel').value = btn.dataset.nivel;
                document.getElementById('edit_grado').value = btn.dataset.grado;
                document.getElementById('edit_area').value = btn.dataset.area;
                document.getElementById('edit_bimestre').value = btn.dataset.bimestre;

                editModal.show();
            });
        });
        
        // Habilitar inputs al chequear variantes en el edit modal
        document.querySelectorAll('.edit-talla-check').forEach(chk => {
            chk.addEventListener('change', (e) => {
                const container = e.target.closest('div');
                const inputs = container.querySelectorAll('input[type="number"]');
                inputs.forEach(input => input.disabled = !e.target.checked);
            });
        });

        document.getElementById('edit_stock_ilimitado')?.addEventListener('change', function() {
            const stockInput = document.getElementById('edit_stock');
            if (this.checked) stockInput.value = '0';
        });

        document.getElementById('edit_aplicar_precio_todos')?.addEventListener('click', () => {
            const precio = document.getElementById('edit_precio_base_variante').value;
            const costo = document.getElementById('edit_costo_base_variante').value;
            const comision = document.getElementById('edit_comision_base_variante').value;
            document.querySelectorAll('.edit-talla-check:checked').forEach(chk => {
                const container = chk.closest('div');
                if (precio !== '') container.querySelector('.edit-talla-precio').value = precio;
                if (costo !== '') container.querySelector('.edit-talla-costo').value = costo;
                if (comision !== '') container.querySelector('.edit-talla-comision').value = comision;
            });
        });

        // 4. Guardar cambios del Producto editado
        const formEditar = document.getElementById('formEditarProducto');
        if (formEditar) {
            formEditar.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id_producto = document.getElementById('edit_id').value;
                const nombre = document.getElementById('edit_nombre').value.trim();
                const id_categoria = document.getElementById('edit_categoria').value;
                const id_unidad = document.getElementById('edit_unidad').value;
                
                const isPadre = !document.getElementById('edit_grupo_variantes').classList.contains('d-none');

                let formData = new FormData();
                let endpoint = './controllers/C_Producto.php?action=actualizar';

                if (isPadre) {
                    endpoint = './controllers/C_Producto.php?action=actualizar_con_variantes';
                    formData.append('id_producto', id_producto);
                    formData.append('nombre', nombre);
                    formData.append('id_categoria', id_categoria);
                    formData.append('id_unidad', id_unidad);
                    
                    let idx = 0;
                    let hasVariants = false;
                    document.querySelectorAll('.edit-talla-check').forEach(chk => {
                        if (chk.checked) {
                            hasVariants = true;
                            const container = chk.closest('div');
                            formData.append(`variantes[${idx}][id_talla]`, chk.value);
                            formData.append(`variantes[${idx}][precio_unitario]`, container.querySelector('.edit-talla-precio').value || 0);
                            formData.append(`variantes[${idx}][costo_produccion]`, container.querySelector('.edit-talla-costo').value || 0);
                            formData.append(`variantes[${idx}][comision]`, container.querySelector('.edit-talla-comision').value || 0);
                            const idChild = container.querySelector('.edit-talla-idproducto').value;
                            if (idChild) {
                                formData.append(`variantes[${idx}][id_producto]`, idChild);
                            }
                            idx++;
                        }
                    });

                    if (!hasVariants) {
                        Swal.fire({ icon: 'warning', text: 'Debe seleccionar al menos una talla.' });
                        return;
                    }
                } else {
                    const precio_unitario = document.getElementById('edit_precio').value;
                    const costo_produccion = document.getElementById('edit_costo').value;
                    const comision = document.getElementById('edit_comision').value || 0;
                    const stock = document.getElementById('edit_stock').value;

                    formData.append('id_producto', id_producto);
                    formData.append('nombre', nombre);
                    formData.append('id_categoria', id_categoria);
                    formData.append('id_unidad', id_unidad);
                    formData.append('precio_unitario', precio_unitario);
                    formData.append('costo_produccion', costo_produccion);
                    formData.append('comision', comision);
                    formData.append('stock', stock);
                    formData.append('stock_ilimitado', document.getElementById('edit_stock_ilimitado').checked ? 1 : 0);
                    
                    // Add attributes
                    if (document.getElementById('edit_grupo_uniformes').style.display === 'block') {
                        formData.append('id_talla', document.getElementById('edit_talla').value);
                        formData.append('id_tipo_corbata', document.getElementById('edit_tipo_corbata').value);
                    }
                    if (document.getElementById('edit_grupo_modulos').style.display === 'block') {
                        formData.append('id_nivel', document.getElementById('edit_nivel').value);
                        formData.append('id_grado', document.getElementById('edit_grado').value);
                        formData.append('id_area', document.getElementById('edit_area').value);
                        formData.append('id_bimestre', document.getElementById('edit_bimestre').value);
                    }
                }

                const fileInput = document.getElementById('edit_imagen');
                if (fileInput && fileInput.files.length > 0) {
                    formData.append('imagen', fileInput.files[0]);
                }

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        editModal.hide();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: data.mensaje,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.mensaje,
                            confirmButtonColor: '#23284E'
                        });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // 5. Eliminar lógicamente un producto del catálogo activo
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
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Eliminado!',
                                    text: data.mensaje,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => window.location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.mensaje,
                                            confirmButtonColor: '#23284E'
                                });
                            }
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                        }
                    }
                });
            });
        });
        
        // Manejador para Actualizar Stock — abre el modal detallado
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
                    // Cargar Modal Masivo
                    document.getElementById('stock_masivo_id_padre').value = id_producto;
                    document.getElementById('stockMasivoSubtitle').textContent = btn.dataset.nombre;
                    
                    // Buscar hijos en la tabla
                    const tbody = document.getElementById('stockMasivoTbody');
                    tbody.innerHTML = '';
                    
                    const hijos = document.querySelectorAll(`.update-stock-btn[data-padreid="${id_producto}"]`);
                    hijos.forEach(hijoBtn => {
                        const tr = document.createElement('tr');
                        tr.className = "border-bottom";
                        tr.innerHTML = `
                            <td class="px-3 fw-bold text-primary">${hijoBtn.dataset.talla}</td>
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
                        tbody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-muted">No se encontraron tallas para este producto.</td></tr>`;
                    }
                    
                    document.getElementById('stock_masivo_referencia').value = '';
                    setTipoStockMasivo('entrada');
                    stockMasivoModal.show();
                    
                } else {
                    // Modal Normal
                    document.getElementById('stock_id_producto').value  = btn.dataset.id;
                    document.getElementById('stock_precio_unit').value = btn.dataset.precio || 0;
                    document.getElementById('stockModalSubtitle').textContent = btn.dataset.nombre;
                    document.getElementById('stock_actual_display').textContent = parseFloat(btn.dataset.stock).toLocaleString('es-PE') + ' und.';
                    // Reset form
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

        // Alternar entre Agregar / Descontar (Modal Normal)
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

        // Alternar entre Agregar / Descontar (Modal Masivo)
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

        // Submit del formulario de stock
        document.getElementById('formStock').addEventListener('submit', async (e) => {
            e.preventDefault();
            const tipo     = document.getElementById('stock_tipo').value;
            const cantidad = parseFloat(document.getElementById('stock_cantidad').value);
            const id_producto = document.getElementById('stock_id_producto').value;
            const precio_unitario = parseFloat(document.getElementById('stock_precio_unit').value) || 0;

            let referencia = '';
            let concepto   = '';

            if (tipo === 'entrada') {
                referencia = document.getElementById('stock_referencia').value.trim();
                concepto   = referencia ? `Entrada de stock — Boleta ${referencia}` : 'Entrada de stock manual';
            } else {
                const motSel = document.getElementById('stock_motivo_select').value;
                const motOtro = document.getElementById('stock_motivo_otro').value.trim();
                const motivo = motSel === 'Otro' ? motOtro : motSel;
                if (!motivo) { Swal.fire({ icon:'warning', title:'Falta motivo', text:'Debes indicar el motivo del descuento.' }); return; }
                referencia = motivo;
                concepto   = `Salida de stock — ${motivo}`;
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

        // Submit Masivo de Stock
        const formStockMasivo = document.getElementById('formStockMasivo');
        if (formStockMasivo) {
            formStockMasivo.addEventListener('submit', async (e) => {
                e.preventDefault();
                const tipo = document.getElementById('stock_masivo_tipo').value;
                
                let referencia = '';
                let concepto = '';
                
                if (tipo === 'entrada') {
                    referencia = document.getElementById('stock_masivo_referencia').value.trim();
                    concepto   = referencia ? `Entrada de stock — Boleta ${referencia}` : 'Entrada de stock manual';
                } else {
                    const motivo = document.getElementById('stock_masivo_motivo_select').value;
                    referencia = motivo;
                    concepto   = `Salida de stock — ${motivo}`;
                }

                // Recolectar tallas a actualizar
                const inputs = document.querySelectorAll('.masivo-cantidad-input');
                const updates = [];
                
                inputs.forEach(input => {
                    const val = parseFloat(input.value);
                    if (val && val > 0) {
                        updates.push({
                            id_producto: input.dataset.id,
                            precio_unitario: input.dataset.precio,
                            cantidad: val
                        });
                    }
                });
                
                if (updates.length === 0) {
                    Swal.fire({ icon: 'warning', text: 'Debes ingresar al menos una cantidad mayor a 0.' });
                    return;
                }
                
                // Mostrar cargando
                const btnConfirmar = document.getElementById('btnConfirmarStockMasivo');
                const originalHtml = btnConfirmar.innerHTML;
                btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...';
                btnConfirmar.disabled = true;

                try {
                    let successes = 0;
                    for (const up of updates) {
                        const payload = {
                            id_producto: up.id_producto,
                            tipo: tipo,
                            cantidad: up.cantidad,
                            precio_unitario: parseFloat(up.precio_unitario) || 0,
                            referencia: referencia,
                            concepto: concepto
                        };
                        
                        const res = await fetch('./controllers/C_Kardex.php?action=registrar_movimiento', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json();
                        if (data.success) successes++;
                    }
                    
                    stockMasivoModal.hide();
                    Swal.fire({ icon: 'success', title: '¡Actualizado!', text: `Se actualizaron ${successes} tallas correctamente.`, showConfirmButton: false, timer: 1500 })
                        .then(() => window.location.reload());
                } catch (error) {
                    btnConfirmar.innerHTML = originalHtml;
                    btnConfirmar.disabled = false;
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Hubo un error al actualizar algunas tallas.' });
                }
            });
        }

        // 6. Funcionalidad para mostrar/ocultar atributos

        window.toggleGroups = function(prefix) {
            const selectCat = document.getElementById(prefix + '_categoria');
            if(selectCat.selectedIndex === -1) return;
            const catName = selectCat.options[selectCat.selectedIndex].getAttribute('data-name');
            
            const groupUniformes = document.getElementById(prefix + '_grupo_uniformes');
            const groupModulos = document.getElementById(prefix + '_grupo_modulos');
            
            if (catName === 'Uniformes') {
                groupUniformes.style.display = 'block';
                groupModulos.style.display = 'none';
            } else if (catName === 'Módulos') {
                groupUniformes.style.display = 'none';
                groupModulos.style.display = 'block';
            } else {
                groupUniformes.style.display = 'none';
                groupModulos.style.display = 'none';
            }
        };
        <?php endif; ?>
    });
</script>
