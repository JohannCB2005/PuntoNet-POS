<?php
// Validar que exista una sesión activa
if (!isset($_SESSION['id_usuario'])) {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

// Cargar el modelo de Venta para listar el historial
require_once dirname(__DIR__) . '/models/M_Venta.php';
$modelVenta = M_Venta::singleton();

// Restricción por rol: Si es vendedor, solo puede ver su propio historial, de lo contrario (admin) carga todo
$id_vendedor = ($_SESSION['rol'] !== 'Administrador') ? $_SESSION['id_usuario'] : null;
$ventas = $modelVenta->listar($id_vendedor);
?>

<div class="container-fluid px-0">
    <!-- Encabezado de Página -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Historial de Ventas</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Consulta, anula o visualiza los comprobantes de venta emitidos.</p>
        </div>
    </div>

    <!-- Panel de Historial -->
    <div class="gp-card">
        <!-- Barra de Búsqueda y Filtros en tiempo real -->
        <div class="row g-3 mb-3 align-items-end">
            <div class="col-12 col-md-3">
                <label for="searchVentas" class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted" id="search-addon" style="height: 38px;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0 text-sm" id="searchVentas" placeholder="Buscar por código o cliente..." aria-label="Buscar" aria-describedby="search-addon" style="box-shadow: none; font-size: 13.5px; height: 38px;">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label for="typeFilter" class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Tipo Comprobante</label>
                <select class="form-select text-sm" id="typeFilter" style="font-size: 13.5px; height: 38px; box-shadow: none;">
                    <option value="all">Todos</option>
                    <option value="1">Boleta</option>
                    <option value="2">Factura</option>
                    <option value="3">Nota de Venta</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="dateDesde" class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Desde</label>
                <input type="date" class="form-control text-sm" id="dateDesde" style="font-size: 13.5px; height: 38px; box-shadow: none;">
            </div>
            <div class="col-6 col-md-2">
                <label for="dateHasta" class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Hasta</label>
                <input type="date" class="form-control text-sm" id="dateHasta" style="font-size: 13.5px; height: 38px; box-shadow: none;">
            </div>
            <div class="col-6 col-md-3">
                <label for="statusFilter" class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Estado</label>
                <select class="form-select text-sm" id="statusFilter" style="font-size: 13.5px; height: 38px; box-shadow: none;">
                    <option value="all">Todos los estados</option>
                    <option value="Completada">Completadas</option>
                    <option value="Anulada">Anuladas</option>
                </select>
            </div>
        </div>

        <!-- Tabla del Historial de Ventas -->
        <div class="table-responsive">
            <table class="table align-middle text-sm" id="tableVentas" style="font-size: 14px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th scope="col" class="pb-3 text-center">Código</th>
                        <th scope="col" class="pb-3">Fecha / Hora</th>
                        <th scope="col" class="pb-3">Cliente</th>
                        <th scope="col" class="pb-3">Vendedor</th>
                        <th scope="col" class="pb-3 text-center">Tipo</th>
                        <th scope="col" class="pb-3 text-center">Total</th>
                        <th scope="col" class="pb-3 text-center">Estado</th>
                        <th scope="col" class="pb-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt-cutoff fs-2 mb-2 d-block"></i>
                                No se encontraron ventas registradas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ventas as $v): ?>
                            <tr class="border-bottom venta-row" 
                                                data-cliente="<?php echo htmlspecialchars(strtolower($v['cliente'])); ?>"
                                data-dni="<?php echo htmlspecialchars($v['numero_documento'] ?? ''); ?>"
                                data-codigo="v-<?php echo str_pad($v['id_venta'], 6, '0', STR_PAD_LEFT); ?>"
                                data-estado="<?php echo $v['estado'] == 1 ? 'Completada' : 'Anulada'; ?>"
                                data-tipo="<?php echo $v['tipo_comprobante']; ?>"
                                data-fecha="<?php echo date('Y-m-d', strtotime($v['fecha'])); ?>">
                                <td class="py-3 font-mono fw-bold text-dark text-center">
                                    V-<?php echo str_pad($v['id_venta'], 6, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($v['fecha'])); ?>
                                </td>
                                <td class="fw-semibold text-dark">
                                    <?php echo htmlspecialchars($v['cliente']); ?>
                                </td>
                                <td class="text-muted">
                                    <?php echo htmlspecialchars($v['vendedor']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2.5 py-1.5 fw-semibold" style="font-size: 11px;">
                                        <?php 
                                            if ($v['tipo_comprobante'] == 1) echo 'Boleta';
                                            elseif ($v['tipo_comprobante'] == 2) echo 'Factura';
                                            else echo 'Nota de Venta';
                                         ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold text-dark">
                                    S/ <?php echo number_format($v['total'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?php echo $v['estado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                        <?php echo $v['estado'] == 1 ? 'Completada' : 'Anulada'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1 justify-content-center">
                                        <!-- Botón Ver Detalles (Modal con AJAX) -->
                                        <button class="btn btn-link text-muted p-1 hover-text-primary view-details-btn" 
                                                data-id="<?php echo $v['id_venta']; ?>"
                                                data-codigo="V-<?php echo str_pad($v['id_venta'], 6, '0', STR_PAD_LEFT); ?>"
                                                data-cliente="<?php echo htmlspecialchars($v['cliente']); ?>"
                                                data-fecha="<?php echo date('d/m/Y H:i', strtotime($v['fecha'])); ?>"
                                                data-total="<?php echo number_format($v['total'], 2); ?>"
                                                data-tipo="<?php echo $v['tipo_comprobante'] == 1 ? 'Boleta' : ($v['tipo_comprobante'] == 2 ? 'Factura' : 'Nota de Venta'); ?>"
                                                data-estado="<?php echo $v['estado'] == 1 ? 'Completada' : 'Anulada'; ?>"
                                                title="Ver Detalle">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                        <!-- Botón Imprimir Comprobante (Modal Iframe con formatos) -->
                                        <button class="btn btn-link text-muted p-1 print-ticket-btn"
                                                data-id="<?php echo $v['id_venta']; ?>"
                                                title="Imprimir Comprobante"
                                                style="color: #6b7280;">
                                            <i class="bi bi-printer-fill"></i>
                                        </button>
                                        <!-- Botón Anular (Retorna insumos al stock) -->
                                        <?php if ($v['estado'] == 1): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-danger cancel-sale-btn" 
                                                    data-id="<?php echo $v['id_venta']; ?>"
                                                    title="Anular Venta">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Detalle de Venta (Comprobante Visual) -->
<div class="modal fade" id="detalleVentaModal" tabindex="-1" aria-labelledby="detalleVentaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="detalleVentaModalLabel">Comprobante de Pago</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4" id="ticketContent">
                <!-- Encabezado de la Boleta -->
                <div class="text-center mb-4 border-bottom pb-3">
                    <h5 class="fw-bold text-dark mb-1">PuntoNet</h5>
                    <p class="text-muted mb-2" style="font-size: 12px;">Gestión de Insumos y Ventas</p>
                    <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 px-3 py-1.5 fw-bold" id="ticketCodigo" style="font-size: 13px;">
                        V-000000
                    </div>
                </div>

                <!-- Detalles de la Transacción -->
                <div class="row g-2 mb-4" style="font-size: 12.5px;">
                    <div class="col-6">
                        <span class="text-muted d-block">Fecha / Hora</span>
                        <strong class="text-dark" id="ticketFecha">--/--/---- --:--</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Tipo Comprobante</span>
                        <strong class="text-dark" id="ticketTipo">Boleta</strong>
                    </div>
                    <div class="col-12 mt-2">
                        <span class="text-muted d-block">Cliente</span>
                        <strong class="text-dark" id="ticketCliente">Público General</strong>
                    </div>
                </div>

                <!-- Tabla de Productos/Insumos -->
                <div class="border-top pt-3">
                    <h6 class="fw-bold text-dark mb-3" style="font-size: 13px;">Detalle de Insumos</h6>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" style="font-size: 12.5px;">
                            <thead>
                                <tr class="text-muted border-bottom" style="font-size: 11px;">
                                    <th scope="col" class="ps-0">Descripción</th>
                                    <th scope="col" class="text-center">Cant.</th>
                                    <th scope="col" class="text-end">P. Unit</th>
                                    <th scope="col" class="text-end pe-0">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="ticketItems">
                                <!-- Filas dinámicas cargadas por AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección de Importes y Desglose de IGV -->
                <div class="border-top mt-3 pt-3 bg-light p-3 rounded-3" style="font-size: 13px;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-semibold text-dark" id="ticketSubtotal">S/ 0.00</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted">IGV (18%)</span>
                        <span class="fw-semibold text-dark" id="ticketIgv">S/ 0.00</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <strong class="text-dark" style="font-size: 14px;">Total General</strong>
                        <strong class="text-success" style="font-size: 15px;" id="ticketTotal">S/ 0.00</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-semibold w-100" data-bs-dismiss="modal" style="border-radius: 8px;">Cerrar Comprobante</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Imprimir Comprobante desde Historial -->
<div class="modal fade" id="historialPrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; height: 92vh;">
            <div class="modal-header bg-white border-bottom py-3" style="flex-shrink: 0; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 16px;">
                    <i class="bi bi-printer-fill text-success"></i>
                    Comprobante de Venta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>

            <!-- Selector dinámico de formato para impresión -->
            <div class="d-flex justify-content-center gap-2 py-2 bg-light border-bottom" style="flex-shrink: 0;">
                <button class="btn btn-primary btn-sm px-3 hist-format-btn active" data-format="80mm" style="background-color: #0284c7; border: none;">
                    <i class="bi bi-receipt"></i> Ticket 80mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 hist-format-btn" data-format="58mm" style="border-color: #0284c7; color: #0284c7;">
                    <i class="bi bi-receipt"></i> Ticket 58mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 hist-format-btn" data-format="a4" style="border-color: #0284c7; color: #0284c7;">
                    <i class="bi bi-file-earmark-text"></i> A4
                </button>
            </div>

            <!-- Visor iframe para previsualizar el PDF dinámico -->
            <div class="modal-body p-0 position-relative" style="flex: 1 1 auto; overflow: hidden; background-color: #525659;">
                <div id="histPrintSpinner" class="position-absolute top-50 start-50 translate-middle text-white d-flex flex-column align-items-center" style="z-index: 20;">
                    <div class="spinner-border mb-2" role="status"></div>
                    <span style="font-size: 14px;">Generando comprobante...</span>
                </div>
                <iframe id="histPrintFrame" src=""
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; display: none; z-index: 10; background: #fff;">
                </iframe>
            </div>

            <!-- Botones de Acción de Impresión -->
            <div class="modal-footer border-top bg-white py-2 px-4 d-flex justify-content-between" style="flex-shrink: 0; border-radius: 0 0 12px 12px;">
                <button class="btn btn-primary d-flex align-items-center gap-2 px-4" id="histPrintBtn" style="background-color: #0284c7; border: none;">
                    <i class="bi bi-printer-fill"></i> Imprimir
                </button>
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para Filtrado en Historial y Peticiones AJAX -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchVentas');
        const statusFilter = document.getElementById('statusFilter');
        const typeFilter = document.getElementById('typeFilter');
        const dateDesde = document.getElementById('dateDesde');
        const dateHasta = document.getElementById('dateHasta');
        const rows = document.querySelectorAll('.venta-row');

        // 1. Filtrado dinámico multivariable en el frontend
        function filterVentas() {
            const query = searchInput.value.toLowerCase().trim();
            const status = statusFilter.value;
            const type = typeFilter.value;
            const desde = dateDesde.value; 
            const hasta = dateHasta.value; 

            rows.forEach(row => {
                const cliente = row.dataset.cliente || '';
                const dni = row.dataset.dni || '';
                const codigo = row.dataset.codigo || '';
                const textMatch = cliente.includes(query) || dni.includes(query) || codigo.includes(query);
                const statusMatch = (status === 'all' || row.dataset.estado === status);
                const typeMatch = (type === 'all' || row.dataset.tipo === type);
                
                let dateMatch = true;
                const rowDate = row.dataset.fecha; 
                if (desde && rowDate < desde) {
                    dateMatch = false;
                }
                if (hasta && rowDate > hasta) {
                    dateMatch = false;
                }

                if (textMatch && statusMatch && typeMatch && dateMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (searchInput) searchInput.addEventListener('input', filterVentas);
        if (statusFilter) statusFilter.addEventListener('change', filterVentas);
        if (typeFilter) typeFilter.addEventListener('change', filterVentas);
        if (dateDesde) dateDesde.addEventListener('change', filterVentas);
        if (dateHasta) dateHasta.addEventListener('change', filterVentas);

        // 2. Cargar detalles del comprobante por AJAX al abrir el modal
        const detailsModal = new bootstrap.Modal(document.getElementById('detalleVentaModal'));
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                document.getElementById('ticketCodigo').innerText = btn.dataset.codigo;
                document.getElementById('ticketCliente').innerText = btn.dataset.cliente;
                document.getElementById('ticketFecha').innerText = btn.dataset.fecha;
                document.getElementById('ticketTipo').innerText = btn.dataset.tipo;
                
                const totalFloat = parseFloat(btn.dataset.total.replace(/,/g, ''));
                const subtotalFloat = totalFloat / 1.18;
                const igvFloat = totalFloat - subtotalFloat;

                document.getElementById('ticketSubtotal').innerText = 'S/ ' + subtotalFloat.toFixed(2);
                document.getElementById('ticketIgv').innerText = 'S/ ' + igvFloat.toFixed(2);
                document.getElementById('ticketTotal').innerText = 'S/ ' + totalFloat.toFixed(2);

                const itemsBody = document.getElementById('ticketItems');
                itemsBody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm text-success" role="status"></div> Cargando detalle...</td></tr>';

                detailsModal.show();

                try {
                    const response = await fetch(`./controllers/C_Venta.php?action=detalles&id_venta=${id}`);
                    const details = await response.json();
                    
                    if (details && details.length > 0) {
                        let rowsHtml = '';
                        details.forEach(item => {
                            const piezas = parseFloat(item.piezas);
                            const pesoNeto = parseFloat(item.peso_neto || 0);
                            const prec = parseFloat(item.precio_venta);
                            const subt = parseFloat(item.subtotal);
                            // Mostrar piezas + peso si el insumo tiene peso variable (pavos)
                            const cantDisplay = pesoNeto > 0
                                ? `${piezas} pzs · ${pesoNeto.toFixed(2)} Kg`
                                : `${piezas} ${item.abreviatura}`;
                            rowsHtml += `
                                <tr>
                                    <td class="ps-0 text-dark fw-medium">${item.insumo_nombre}</td>
                                    <td class="text-center text-muted">${cantDisplay}</td>
                                    <td class="text-end text-muted">S/ ${prec.toFixed(2)}</td>
                                    <td class="text-end pe-0 fw-semibold text-dark">S/ ${subt.toFixed(2)}</td>
                                </tr>
                            `;
                        });
                        itemsBody.innerHTML = rowsHtml;
                    } else {
                        itemsBody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">No se pudieron cargar los detalles.</td></tr>';
                    }
                } catch (error) {
                    itemsBody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error de conexión.</td></tr>';
                }
            });
        });

        // 3. Confirmación y ejecución AJAX para anular una venta
        document.querySelectorAll('.cancel-sale-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                Swal.fire({
                    title: '¿Anular esta venta?',
                    text: 'Esta acción devolverá los insumos vendidos al stock del inventario.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, anular venta',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_Venta.php?action=anular', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id_venta: id })
                            });
                            const data = await response.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Venta Anulada!',
                                    text: data.mensaje,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => window.location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.mensaje,
                                    confirmButtonColor: '#0284c7'
                                });
                            }
                        } catch (err) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de red',
                                text: 'No se pudo contactar al servidor.'
                            });
                        }
                    }
                });
            });
        });

        // 4. Lógica para impresión de tickets con Iframe reactivo
        let histCurrentId     = null;
        let histCurrentFormat = '80mm';

        const histPrintModal  = new bootstrap.Modal(document.getElementById('historialPrintModal'));
        const histPrintFrame  = document.getElementById('histPrintFrame');
        const histPrintSpinner= document.getElementById('histPrintSpinner');
        const histFormatBtns  = document.querySelectorAll('.hist-format-btn');

        // Escuchar clics en el botón de impresión del historial para levantar el modal
        document.querySelectorAll('.print-ticket-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                histCurrentId     = btn.dataset.id;
                histCurrentFormat = '80mm';

                // Restablecer estilos en los botones del selector de formatos
                histFormatBtns.forEach(b => {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-primary');
                });
                histFormatBtns[0].classList.add('btn-primary', 'active');
                histFormatBtns[0].classList.remove('btn-outline-primary');

                histPrintModal.show();
                loadHistIframe();
            });
        });

        // Alternar el formato del comprobante (80mm, 58mm, A4) en caliente
        histFormatBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                histFormatBtns.forEach(b => {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-primary');
                });
                btn.classList.add('btn-primary', 'active');
                btn.classList.remove('btn-outline-primary');
                histCurrentFormat = btn.dataset.format;
                loadHistIframe();
            });
        });

        // Carga dinámica del Iframe que renderiza la vista de ticket para la impresión limpia
        function loadHistIframe() {
            histPrintFrame.style.display = 'none';
            histPrintSpinner.style.display = 'flex';

            histPrintFrame.onload = () => {
                histPrintSpinner.style.display = 'none';
                histPrintFrame.style.display = 'block';
            };

            histPrintFrame.src = `views/V_ticket_print.php?id=${histCurrentId}&format=${histCurrentFormat}`;
        }

        // Ejecutar foco e impresión de la ventana del iframe de impresión
        document.getElementById('histPrintBtn').addEventListener('click', () => {
            if (histPrintFrame.contentWindow) {
                histPrintFrame.contentWindow.focus();
                histPrintFrame.contentWindow.print();
            }
        });

        // Resetear iframe al cerrar modal
        // Blur fix para aria-hidden en historialPrintModal
        document.getElementById('historialPrintModal').addEventListener('hide.bs.modal', () => {
            if (document.activeElement && document.getElementById('historialPrintModal').contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });

        document.getElementById('historialPrintModal').addEventListener('hidden.bs.modal', () => {
            histPrintFrame.src = '';
            histPrintFrame.style.display = 'none';
            histPrintSpinner.style.display = 'flex';
        });

        // Blur fix para aria-hidden en detalleVentaModal
        document.getElementById('detalleVentaModal').addEventListener('hide.bs.modal', () => {
            if (document.activeElement && document.getElementById('detalleVentaModal').contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
    });
</script>
