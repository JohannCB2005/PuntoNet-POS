<?php
if (!isset($_SESSION['id_usuario'])) {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

require_once dirname(__DIR__) . '/models/M_Producto.php';
require_once dirname(__DIR__) . '/models/M_Cliente.php';

$modelProducto = M_Producto::singleton();
$productos = $modelProducto->listar();

$modelCliente = M_Cliente::singleton();
$clientesRaw = $modelCliente->listarClientes();
?>
<!-- CSS DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<!-- CSS Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .table-cotizaciones tbody tr:hover { background-color: #f8fafc; }
    .badge-estado-1 { background-color: #f59e0b; color: #fff; } /* Pendiente */
    .badge-estado-2 { background-color: #10b981; color: #fff; } /* Convertida */
    .badge-estado-3 { background-color: #ef4444; color: #fff; } /* Vencida */
    .badge-estado-0 { background-color: #6b7280; color: #fff; } /* Anulada */

    .select2-container--bootstrap-5 .select2-selection {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        min-height: 38px;
    }

    /* Botones acción estilo Historial */
    .hover-text-primary:hover { color: #23284E !important; }
    .hover-text-danger:hover  { color: #ef4444 !important; }
</style>

<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Cotizaciones</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Administra presupuestos y cotizaciones para clientes.</p>
        </div>
        <a href="/nueva-cotizacion" class="gp-btn-primary d-flex align-items-center gap-2 border-0 text-decoration-none">
            <i class="bi bi-plus-lg"></i>
            <span>Nueva Cotización</span>
        </a>
    </div>

    <div class="gp-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-cotizaciones" id="tablaCotizaciones" style="font-size: 14px;">
                <thead class="table-light">
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th class="pb-3 text-center">N° Cotización</th>
                        <th class="pb-3">Fecha Emisión</th>
                        <th class="pb-3">Vencimiento</th>
                        <th class="pb-3">Cliente</th>
                        <th class="pb-3 text-center">Total</th>
                        <th class="pb-3 text-center">Estado</th>
                        <th class="pb-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyCotizaciones">
                    <!-- Contenido dinámico JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nueva Cotización -->
<div class="modal fade" id="modalNuevaCotizacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i>Nueva Cotización</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- Columna Izquierda: Datos del cliente y carrito -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-person-fill me-2"></i>Datos del Cliente</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-8">
                                        <label class="form-label text-muted" style="font-size: 13px;">Buscar Cliente Registrado (Opcional)</label>
                                        <select class="form-select select2-clientes" id="cot_id_cliente">
                                            <option value="">-- Consumidor Final / No Registrado --</option>
                                            <?php foreach($clientesRaw as $c): ?>
                                                <option value="<?php echo $c['id_cliente']; ?>">
                                                    <?php echo htmlspecialchars($c['nombres_razon_social'] . ' ' . $c['apellidos']); ?> (<?php echo $c['numero_documento']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-muted" style="font-size: 13px;">O Escribir Nombre (Puntual)</label>
                                        <input type="text" class="form-control form-control-sm border-0 bg-light" id="cot_cliente_manual" placeholder="Ej. Juan Pérez">
                                    </div>
                                </div>

                                <h6 class="fw-bold mb-3 text-primary mt-4"><i class="bi bi-cart-fill me-2"></i>Productos Cotizados</h6>
                                
                                <!-- Buscador de Productos -->
                                <div class="mb-3">
                                    <select class="form-select select2-productos" id="cot_buscador_producto">
                                        <option value="">Buscar producto / producto...</option>
                                        <?php foreach($productos as $i): ?>
                                            <?php if($i['estado'] == 1): ?>
                                            <option value="<?php echo $i['id_producto']; ?>" 
                                                    data-precio="<?php echo $i['precio_unitario']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($i['nombre']); ?>">
                                                <?php echo htmlspecialchars($i['nombre']); ?> - S/ <?php echo $i['precio_unitario']; ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Tabla Carrito -->
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th style="width: 100px;">Cant.</th>
                                                <th style="width: 120px;">P.Unitario</th>
                                                <th style="width: 100px;">Subtotal</th>
                                                <th style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cot_cart_body">
                                            <tr><td colspan="5" class="text-center text-muted">No hay productos en la cotización</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Resumen y Guardar -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-4 d-flex flex-column">
                                <h6 class="fw-bold mb-4 text-primary"><i class="bi bi-receipt me-2"></i>Resumen</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" style="font-size: 13px;">Validez (Días)</label>
                                    <select class="form-select" id="cot_validez">
                                        <option value="7">7 Días</option>
                                        <option value="15" selected>15 Días</option>
                                        <option value="30">30 Días</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold" style="font-size: 13px;">Observaciones (Opcional)</label>
                                    <textarea class="form-control bg-light border-0" id="cot_observaciones" rows="2" placeholder="Ej. Precios sujetos a stock..."></textarea>
                                </div>

                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Subtotal (Gravado)</span>
                                        <span class="fw-bold" id="cot_lbl_subtotal">S/ 0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">IGV (18%)</span>
                                        <span class="fw-bold" id="cot_lbl_igv">S/ 0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between pt-3 border-top mb-4">
                                        <span class="fw-bold fs-5">TOTAL</span>
                                        <span class="fw-bold fs-4 text-primary" id="cot_lbl_total">S/ 0.00</span>
                                    </div>
                                    
                                    <button class="gp-btn-primary w-100 border-0 py-3 fw-bold rounded-3 shadow-sm d-flex justify-content-center align-items-center gap-2" id="btnGuardarCotizacion">
                                        <i class="bi bi-save"></i> Generar Cotización
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Convertir a Venta -->
<div class="modal fade" id="modalConvertir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-cart-check-fill me-2"></i>Convertir a Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="conv_id_cotizacion">
                <p class="text-muted mb-4">Completa los datos para registrar la venta. Se descontará el stock correspondiente.</p>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tipo de Comprobante</label>
                    <select class="form-select" id="conv_comprobante">
                        <option value="1">Boleta de Venta</option>
                        <option value="2">Factura Electrónica</option>
                        <option value="3">Nota de Venta (Interna)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Método de Pago</label>
                    <select class="form-select" id="conv_metodo_pago">
                        <option value="1" selected>💵 Efectivo</option>
                        <option value="2">📱 Yape / Plin</option>
                        <option value="3">💳 Tarjeta POS</option>
                    </select>
                </div>
                <button class="gp-btn-primary w-100 border-0 py-2 fw-bold" id="btnConfirmarConversion">Procesar Venta</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Imprimir Cotización (iframe, igual que Historial) -->
<div class="modal fade" id="cotPrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; height: 92vh;">
            <div class="modal-header bg-white border-bottom py-3" style="flex-shrink: 0; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 16px;">
                    <i class="bi bi-file-earmark-text-fill text-primary"></i>
                    Cotización
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>

            <!-- Selector de formato -->
            <div class="d-flex justify-content-center gap-2 py-2 bg-light border-bottom" style="flex-shrink: 0;">
                <button class="btn btn-primary btn-sm px-3 cot-format-btn active" data-format="80mm" style="background-color: #23284E; border: none;">
                    <i class="bi bi-receipt"></i> Ticket 80mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 cot-format-btn" data-format="58mm" style="border-color: #23284E; color: #23284E;">
                    <i class="bi bi-receipt"></i> Ticket 58mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 cot-format-btn" data-format="a4" style="border-color: #23284E; color: #23284E;">
                    <i class="bi bi-file-earmark-text"></i> A4
                </button>
            </div>

            <!-- Visor iframe -->
            <div class="modal-body p-0 position-relative" style="flex: 1 1 auto; overflow: hidden; background-color: #525659;">
                <div id="cotPrintSpinner" class="position-absolute top-50 start-50 translate-middle text-white d-flex flex-column align-items-center" style="z-index: 20;">
                    <div class="spinner-border mb-2" role="status"></div>
                    <span style="font-size: 14px;">Generando cotización...</span>
                </div>
                <iframe id="cotPrintFrame" src=""
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; display: none; z-index: 10; background: #fff;">
                </iframe>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-top bg-white py-2 px-4 d-flex justify-content-between align-items-center" style="flex-shrink: 0; border-radius: 0 0 12px 12px;">
                <button class="btn btn-primary d-flex align-items-center gap-2 px-4" id="cotPrintBtn" style="background-color: #23284E; border: none;">
                    <i class="bi bi-printer-fill"></i> Imprimir
                </button>
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let dtCotizaciones = null;
let carritoCotizacion = [];

document.addEventListener('DOMContentLoaded', () => {
    // Init DataTable con traducción embebida para evitar CORS
    dtCotizaciones = $('#tablaCotizaciones').DataTable({
        language: {
            sProcessing: "Procesando...",
            sLengthMenu: "Mostrar _MENU_ registros",
            sZeroRecords: "No se encontraron resultados",
            sEmptyTable: "Ningún dato disponible en esta tabla",
            sInfo: "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            sInfoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
            sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
            sSearch: "Buscar:",
            oPaginate: { sFirst: "Primero", sLast: "Último", sNext: "Siguiente", sPrevious: "Anterior" }
        },
        ordering: false
    });

    cargarCotizaciones();

    // Init Select2
    $('#cot_id_cliente').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalNuevaCotizacion') });
    $('#cot_buscador_producto').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalNuevaCotizacion') });

    // Agregar al carrito
    $('#cot_buscador_producto').on('select2:select', function (e) {
        let data = e.params.data;
        let id_producto = data.id;
        let element = $(data.element);
        let nombre = element.data('nombre');
        let precio = parseFloat(element.data('precio'));

        if(id_producto) {
            let existe = carritoCotizacion.find(i => i.id_producto == id_producto);
            if(existe) {
                existe.cantidad++;
                existe.subtotal = existe.cantidad * existe.precio;
            } else {
                carritoCotizacion.push({
                    id_producto: id_producto,
                    nombre: nombre,
                    cantidad: 1,
                    precio: precio,
                    subtotal: precio
                });
            }
            renderCarrito();
            $('#cot_buscador_producto').val('').trigger('change');
        }
    });

    // Guardar cotización
    document.getElementById('btnGuardarCotizacion').addEventListener('click', async () => {
        if (carritoCotizacion.length === 0) {
            Swal.fire('Atención', 'Agrega al menos un producto a la cotización', 'warning');
            return;
        }
        
        let id_cliente = document.getElementById('cot_id_cliente').value;
        let cliente_manual = document.getElementById('cot_cliente_manual').value;
        
        if (!id_cliente && !cliente_manual) {
            Swal.fire('Atención', 'Debe seleccionar un cliente o escribir un nombre.', 'warning');
            return;
        }

        let total = carritoCotizacion.reduce((sum, i) => sum + i.subtotal, 0);
        let subtotal = total / 1.18;
        let igv = total - subtotal;

        let data = {
            id_cliente: id_cliente,
            cliente_nombre_manual: cliente_manual,
            validez: document.getElementById('cot_validez').value,
            observaciones: document.getElementById('cot_observaciones').value,
            subtotal: subtotal.toFixed(2),
            igv: igv.toFixed(2),
            total: total.toFixed(2),
            detalles: carritoCotizacion
        };

        const btn = document.getElementById('btnGuardarCotizacion');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';

        try {
            const resp = await fetch('./controllers/C_Cotizacion.php?action=crear', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await resp.json();
            
            if (result.success) {
                Swal.fire('¡Éxito!', result.mensaje, 'success');
                $('#modalNuevaCotizacion').modal('hide');
                carritoCotizacion = [];
                renderCarrito();
                document.getElementById('cot_id_cliente').value = '';
                $('#cot_id_cliente').trigger('change');
                document.getElementById('cot_cliente_manual').value = '';
                document.getElementById('cot_observaciones').value = '';
                cargarCotizaciones();
                
                // Abrir modal de impresión
                abrirModalImpresion(result.id_cotizacion);
            } else {
                Swal.fire('Error', result.mensaje, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save"></i> Generar Cotización';
        }
    });

    // Procesar Conversión a Venta
    document.getElementById('btnConfirmarConversion').addEventListener('click', async () => {
        let id_cot = document.getElementById('conv_id_cotizacion').value;
        let tipo_comp = document.getElementById('conv_comprobante').value;
        let met_pago = document.getElementById('conv_metodo_pago').value;

        const btn = document.getElementById('btnConfirmarConversion');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const resp = await fetch('./controllers/C_Cotizacion.php?action=convertir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_cotizacion: id_cot, tipo_comprobante: tipo_comp, metodo_pago: met_pago })
            });
            const result = await resp.json();
            if (result.success) {
                Swal.fire('¡Venta Exitosa!', result.mensaje, 'success');
                $('#modalConvertir').modal('hide');
                cargarCotizaciones();
            } else {
                Swal.fire('Error', result.mensaje, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'Fallo de conexión', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Procesar Venta';
        }
    });

    // ── Modal de Impresión de Cotización ──
    let cotCurrentId     = null;
    let cotCurrentFormat = '80mm';

    const cotPrintModal   = new bootstrap.Modal(document.getElementById('cotPrintModal'));
    const cotPrintFrame   = document.getElementById('cotPrintFrame');
    const cotPrintSpinner = document.getElementById('cotPrintSpinner');
    const cotFormatBtns   = document.querySelectorAll('.cot-format-btn');

    window.abrirModalImpresion = function(id) {
        cotCurrentId     = id;
        cotCurrentFormat = '80mm';

        cotFormatBtns.forEach(b => {
            b.classList.remove('btn-primary', 'active');
            b.classList.add('btn-outline-primary');
            b.style.backgroundColor = 'transparent';
            b.style.color = '#23284E';
        });
        cotFormatBtns[0].classList.add('btn-primary', 'active');
        cotFormatBtns[0].classList.remove('btn-outline-primary');
        cotFormatBtns[0].style.backgroundColor = '#23284E';
        cotFormatBtns[0].style.color = '#fff';

        cotPrintModal.show();
        loadCotIframe();
    };

    cotFormatBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            cotFormatBtns.forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-outline-primary');
                b.style.backgroundColor = 'transparent';
                b.style.color = '#23284E';
            });
            btn.classList.add('btn-primary', 'active');
            btn.classList.remove('btn-outline-primary');
            btn.style.backgroundColor = '#23284E';
            btn.style.color = '#fff';
            cotCurrentFormat = btn.dataset.format;
            loadCotIframe();
        });
    });

    function loadCotIframe() {
        cotPrintFrame.style.display = 'none';
        cotPrintSpinner.style.display = 'flex';

        cotPrintFrame.onload = () => {
            cotPrintSpinner.style.display = 'none';
            cotPrintFrame.style.display = 'block';
        };

        cotPrintFrame.src = `views/V_cotizacion_print.php?id=${cotCurrentId}&format=${cotCurrentFormat}`;
    }

    document.getElementById('cotPrintBtn').addEventListener('click', () => {
        if (cotPrintFrame.contentWindow) {
            cotPrintFrame.contentWindow.focus();
            cotPrintFrame.contentWindow.print();
        }
    });

    // Quitar el foco antes de que Bootstrap añada aria-hidden, evitando el warning de accesibilidad
    document.getElementById('cotPrintModal').addEventListener('hide.bs.modal', () => {
        if (document.activeElement && document.getElementById('cotPrintModal').contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });

    document.getElementById('modalConvertir').addEventListener('hide.bs.modal', () => {
        if (document.activeElement && document.getElementById('modalConvertir').contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });

    document.getElementById('modalNuevaCotizacion').addEventListener('hide.bs.modal', () => {
        if (document.activeElement && document.getElementById('modalNuevaCotizacion').contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });

    document.getElementById('cotPrintModal').addEventListener('hidden.bs.modal', () => {
        cotPrintFrame.src = '';
        cotPrintFrame.style.display = 'none';
        cotPrintSpinner.style.display = 'flex';
    });
});

async function cargarCotizaciones() {
    try {
        const resp = await fetch('./controllers/C_Cotizacion.php?action=listar');
        const json = await resp.json();
        
        dtCotizaciones.clear();
        json.data.forEach(c => {
            let badge = '';
            let btnConvertir = '';
            let btnAnular = '';

            if (c.estado == 1) {
                badge = '<span class="badge badge-estado-1">Pendiente</span>';
                btnConvertir = `<button class="btn btn-link text-muted p-1 hover-text-primary" title="Convertir a Venta" onclick="abrirConversion(${c.id_cotizacion})"><i class="bi bi-cart-check-fill"></i></button>`;
                btnAnular    = `<button class="btn btn-link text-muted p-1 hover-text-danger" title="Anular" onclick="anularCotizacion(${c.id_cotizacion})"><i class="bi bi-x-circle-fill"></i></button>`;
            } else if (c.estado == 2) {
                badge = '<span class="badge badge-estado-2">Convertida</span>';
            } else if (c.estado == 3) {
                badge = '<span class="badge badge-estado-3">Vencida</span>';
            } else {
                badge = '<span class="badge badge-estado-0">Anulada</span>';
            }

            let acciones = `
                <div class="d-inline-flex gap-1 justify-content-center">
                    <button class="btn btn-link text-muted p-1 hover-text-primary" title="Imprimir Cotización" onclick="abrirModalImpresion(${c.id_cotizacion})">
                        <i class="bi bi-printer-fill"></i>
                    </button>
                    ${btnConvertir}
                    ${btnAnular}
                </div>
            `;

            dtCotizaciones.row.add([
                `<span class="fw-bold text-primary">${c.codigo}</span>`,
                c.fecha_emision,
                c.fecha_vencimiento,
                c.cliente,
                `S/ ${parseFloat(c.total).toFixed(2)}`,
                badge,
                acciones
            ]);
        });
        dtCotizaciones.draw();
    } catch(e) {
        console.error('Error al cargar cotizaciones', e);
    }
}

function renderCarrito() {
    const tbody = document.getElementById('cot_cart_body');
    tbody.innerHTML = '';
    
    if (carritoCotizacion.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay productos en la cotización</td></tr>';
        document.getElementById('cot_lbl_subtotal').innerText = 'S/ 0.00';
        document.getElementById('cot_lbl_igv').innerText = 'S/ 0.00';
        document.getElementById('cot_lbl_total').innerText = 'S/ 0.00';
        return;
    }

    let total = 0;
    carritoCotizacion.forEach((item, index) => {
        total += item.subtotal;
        tbody.innerHTML += `
            <tr>
                <td><span style="font-size: 13px;" class="fw-semibold text-dark">${item.nombre}</span></td>
                <td>
                    <input type="number" class="form-control form-control-sm text-center bg-light border-0" value="${item.cantidad}" min="1" step="1" onchange="updateCant(${index}, this.value)">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm text-center bg-light border-0" value="${item.precio}" min="0" step="0.10" onchange="updatePrecio(${index}, this.value)">
                </td>
                <td class="fw-bold text-end">S/ ${item.subtotal.toFixed(2)}</td>
                <td class="text-end">
                    <button class="btn btn-sm text-danger border-0 p-0" onclick="removeItem(${index})"><i class="bi bi-trash-fill fs-6"></i></button>
                </td>
            </tr>
        `;
    });

    let subtotal = total / 1.18;
    let igv = total - subtotal;
    
    document.getElementById('cot_lbl_subtotal').innerText = 'S/ ' + subtotal.toFixed(2);
    document.getElementById('cot_lbl_igv').innerText = 'S/ ' + igv.toFixed(2);
    document.getElementById('cot_lbl_total').innerText = 'S/ ' + total.toFixed(2);
}

window.updateCant = function(index, val) {
    let cant = parseFloat(val);
    if(cant > 0) {
        carritoCotizacion[index].cantidad = cant;
        carritoCotizacion[index].subtotal = cant * carritoCotizacion[index].precio;
        renderCarrito();
    }
}
window.updatePrecio = function(index, val) {
    let p = parseFloat(val);
    if(p >= 0) {
        carritoCotizacion[index].precio = p;
        carritoCotizacion[index].subtotal = carritoCotizacion[index].cantidad * p;
        renderCarrito();
    }
}
window.removeItem = function(index) {
    carritoCotizacion.splice(index, 1);
    renderCarrito();
}

window.abrirConversion = function(id) {
    document.getElementById('conv_id_cotizacion').value = id;
    $('#modalConvertir').modal('show');
}

window.anularCotizacion = function(id) {
    Swal.fire({
        title: '¿Anular Cotización?',
        text: "No podrás revertir esto.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, anular'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                let formData = new FormData();
                formData.append('id', id);
                const resp = await fetch('./controllers/C_Cotizacion.php?action=anular', { method: 'POST', body: formData });
                const json = await resp.json();
                if(json.success) {
                    Swal.fire('Anulada', 'Cotización anulada con éxito', 'success');
                    cargarCotizaciones();
                } else {
                    Swal.fire('Error', json.mensaje, 'error');
                }
            } catch(e) { Swal.fire('Error', 'Fallo de conexión', 'error'); }
        }
    });
}
</script>
