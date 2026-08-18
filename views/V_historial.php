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

// Ventana de cambio de talla por rol: Vendedor 1 día, Administrador 7 (seguro para feriados).
// Se resuelve aquí con dias_transcurridos, que ya viene calculado en SQL (DATEDIFF contra NOW()
// de MySQL) — nunca se compara una fecha de BD contra el reloj de PHP, que en este entorno
// corre en una zona horaria distinta y ya rompió el checkout una vez por esa mezcla.
$diasMaxCambioTalla = ($_SESSION['rol'] === 'Administrador') ? 7 : 1;
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
                        <th scope="col" class="pb-3 text-center">SUNAT</th>
                        <th scope="col" class="pb-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
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
                                    <?php if ((int) $v['origen'] === 3): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info px-2.5 py-1.5 fw-semibold" style="font-size: 11px;" title="Diferencia de precio por un cambio de talla, no es una venta nueva">
                                            <i class="bi bi-arrow-left-right"></i> Cambio de talla
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-2.5 py-1.5 fw-semibold" style="font-size: 11px;">
                                            <?php
                                                if ($v['tipo_comprobante'] == 1) echo 'Boleta';
                                                elseif ($v['tipo_comprobante'] == 2) echo 'Factura';
                                                else echo 'Nota de Venta';
                                             ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold <?php echo $v['total'] < 0 ? 'text-danger' : 'text-dark'; ?>">
                                    S/ <?php echo number_format($v['total'], 2); ?>
                                    <?php if ((int) $v['origen'] === 3 && $v['total'] < 0): ?>
                                        <div class="text-muted fw-normal" style="font-size: 10.5px;">Devuelto al cliente</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?php echo $v['estado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                        <?php echo $v['estado'] == 1 ? 'Completada' : 'Anulada'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $estadoSunat = (int) $v['estado_sunat'];
                                        $sunatBadges = [
                                            0 => ['—', 'bg-light text-muted'],
                                            1 => ['Pendiente', 'bg-warning bg-opacity-10 text-warning'],
                                            2 => ['Aceptado', 'bg-success bg-opacity-10 text-success'],
                                            3 => ['Rechazado', 'bg-danger bg-opacity-10 text-danger'],
                                            4 => ['Baja en trámite', 'bg-info bg-opacity-10 text-info'],
                                            5 => ['Dado de baja', 'bg-secondary bg-opacity-10 text-secondary'],
                                        ];
                                        [$sunatTexto, $sunatClase] = $sunatBadges[$estadoSunat] ?? $sunatBadges[0];
                                        $sunatTitulo = trim(($v['serie'] ? $v['serie'] . '-' . str_pad($v['correlativo'], 8, '0', STR_PAD_LEFT) . ' — ' : '') . ($v['sunat_mensaje'] ?? ''));
                                    ?>
                                    <span class="badge <?php echo $sunatClase; ?> px-2 py-1.5 fw-semibold" style="font-size: 10.5px;" title="<?php echo htmlspecialchars($sunatTitulo); ?>">
                                        <?php echo $sunatTexto; ?>
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
                                                data-puede-cambiar="<?php echo ($v['estado'] == 1 && (int) $v['origen'] !== 3 && (int) $v['dias_transcurridos'] <= $diasMaxCambioTalla) ? '1' : '0'; ?>"
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
                                        <!-- Botón Compartir por Enlace (comprobante público estilo tukifac) -->
                                        <button class="btn btn-link text-muted p-1 share-link-btn"
                                                data-id="<?php echo $v['id_venta']; ?>"
                                                data-token="<?php echo htmlspecialchars($v['token_publico'] ?? ''); ?>"
                                                title="Compartir enlace del comprobante">
                                            <i class="bi bi-link-45deg"></i>
                                        </button>
                                        <!-- Botón Anular (Retorna productos al stock; si el comprobante ya fue
                                             aceptado por SUNAT y está dentro de 7 días, además encola la baja) -->
                                        <?php if ($v['estado'] == 1): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-danger cancel-sale-btn"
                                                    data-id="<?php echo $v['id_venta']; ?>"
                                                    title="Anular Venta">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                        <!-- Reenviar: el envío automático falló, reintentar a mano. Solo
                                             Administrador, igual que el endpoint — emitir consume un
                                             correlativo real y declara un monto ante SUNAT. -->
                                        <?php if ($_SESSION['rol'] === 'Administrador' && in_array($estadoSunat, [1, 3], true)): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-primary sunat-reenviar-btn"
                                                    data-id="<?php echo $v['id_venta']; ?>"
                                                    title="Reenviar a SUNAT">
                                                <i class="bi bi-cloud-arrow-up-fill"></i>
                                            </button>
                                        <?php elseif ($estadoSunat === 4): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-primary sunat-consultar-baja-btn"
                                                    data-id="<?php echo $v['id_venta']; ?>"
                                                    title="Consultar estado de la baja">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        <?php elseif ($_SESSION['rol'] === 'Administrador' && $v['estado'] == 0 && $estadoSunat === 2): ?>
                                            <!-- La venta ya se anuló localmente pero el aviso automático a SUNAT
                                                 (Comunicación de Baja / Resumen de baja) falló por red: sin este
                                                 botón no había forma de reintentarlo desde la UI. -->
                                            <button class="btn btn-link text-muted p-1 hover-text-danger sunat-dar-de-baja-btn"
                                                    data-id="<?php echo $v['id_venta']; ?>"
                                                    title="Reintentar aviso de baja a SUNAT">
                                                <i class="bi bi-send-exclamation-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                        <!-- Ver XML/CDR guardado, para depurar un rechazo -->
                                        <?php if ($estadoSunat !== 0): ?>
                                            <button class="btn btn-link text-muted p-1 sunat-ver-cdr-btn"
                                                    data-id="<?php echo $v['id_venta']; ?>"
                                                    title="Ver estado SUNAT">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </button>
                                        <?php endif; ?>
                                        <!-- Nota de Crédito: única salida legal cuando ya no se puede anular
                                             directamente (comprobante aceptado hace más de 7 días). Solo Admin. -->
                                        <?php if ($_SESSION['rol'] === 'Administrador' && $estadoSunat === 2 && $v['estado'] == 1): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-danger sunat-nota-credito-btn"
                                                    data-id="<?php echo $v['id_venta']; ?>"
                                                    data-total="<?php echo number_format($v['total'], 2); ?>"
                                                    title="Emitir Nota de Crédito">
                                                <i class="bi bi-receipt-cutoff"></i>
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
                    <h5 class="fw-bold text-dark mb-1">NISSI</h5>
                    <p class="text-muted mb-2" style="font-size: 12px;">Gestión de Productos y Ventas</p>
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

                <!-- Tabla de Productos/Productos -->
                <div class="border-top pt-3">
                    <h6 class="fw-bold text-dark mb-3" style="font-size: 13px;">Detalle de Productos</h6>
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

<!-- Modal: Cambio de Talla -->
<div class="modal fade" id="cambioTallaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Cambiar talla</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4">
                <div id="cambioTallaLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div id="cambioTallaError" class="alert alert-danger" style="display:none;"></div>
                <div id="cambioTallaContent" style="display:none;">
                    <p class="mb-2 text-muted" style="font-size: 13px;">
                        Talla actual: <strong id="cambioTallaActual" class="text-dark"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size: 13px;">Nueva talla</label>
                        <select class="form-select" id="cambioTallaSelect"></select>
                    </div>
                    <div id="cambioTallaDiferenciaBox" class="p-3 rounded-3 mb-3" style="background:#f8fafc;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted" style="font-size: 13px;">Diferencia</span>
                            <strong id="cambioTallaDiferencia" style="font-size: 15px;">S/ 0.00</strong>
                        </div>
                        <p id="cambioTallaDiferenciaNota" class="text-muted mb-0 mt-1" style="font-size: 12px;"></p>
                    </div>
                    <div id="cambioTallaMetodoPagoBox" class="mb-3" style="display:none;">
                        <label class="form-label fw-semibold" style="font-size: 13px;">Método de pago de la diferencia</label>
                        <select class="form-select" id="cambioTallaMetodoPago">
                            <option value="1">Efectivo</option>
                            <option value="2">Yape/Plin</option>
                            <option value="3">Tarjeta</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                <button type="button" class="gp-btn-primary border-0 fw-semibold" id="btnConfirmarCambioTalla" style="border-radius: 8px;" disabled>
                    Confirmar cambio
                </button>
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
                <button class="btn btn-primary btn-sm px-3 hist-format-btn active" data-format="80mm" style="background-color: #23284E; border: none;">
                    <i class="bi bi-receipt"></i> Ticket 80mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 hist-format-btn" data-format="58mm" style="border-color: #23284E; color: #23284E;">
                    <i class="bi bi-receipt"></i> Ticket 58mm
                </button>
                <button class="btn btn-outline-primary btn-sm px-3 hist-format-btn" data-format="a4" style="border-color: #23284E; color: #23284E;">
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
            <div class="modal-footer border-top bg-white py-2 px-4 d-flex justify-content-between align-items-center" style="flex-shrink: 0; border-radius: 0 0 12px 12px;">
                <button class="btn btn-primary d-flex align-items-center gap-2 px-4" id="histPrintBtn" style="background-color: #23284E; border: none;">
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
                const puedeCambiarTalla = btn.dataset.puedeCambiar === '1';
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
                            // Mostrar piezas + peso si el producto tiene peso variable (pavos)
                            const cantDisplay = pesoNeto > 0
                                ? `${piezas} pzs · ${pesoNeto.toFixed(2)} Kg`
                                : `${piezas} ${item.abreviatura}`;
                            // Solo tiene sentido cambiar la talla de productos con variantes
                            // (id_producto_padre no nulo) y dentro de la ventana permitida.
                            const puedeCambiarLinea = puedeCambiarTalla && item.id_producto_padre;
                            const btnCambiar = puedeCambiarLinea
                                ? `<button type="button" class="btn btn-link btn-sm p-0 ms-2 cambiar-talla-btn"
                                        data-id-detalle="${item.id_detalle}"
                                        data-producto-nombre="${escapeHtmlHist(item.producto_nombre)}"
                                        title="Cambiar talla">
                                        <i class="bi bi-arrow-left-right"></i>
                                   </button>`
                                : '';
                            rowsHtml += `
                                <tr>
                                    <td class="ps-0 text-dark fw-medium">${item.producto_nombre}${btnCambiar}</td>
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
                    text: 'Esta acción devolverá los productos vendidos al stock del inventario.',
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
                                    confirmButtonColor: '#23284E'
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

        // 3b. Acciones SUNAT: reenviar envío/baja fallidos, consultar una baja en
        //     trámite, ver el CDR guardado y emitir Nota de Crédito (venta aceptada
        //     hace más de 7 días, ya no se puede anular directamente).
        document.querySelectorAll('.sunat-reenviar-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                Swal.fire({
                    title: '¿Reenviar a SUNAT?',
                    text: 'Se reintentará el envío de este comprobante.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#23284E',
                    confirmButtonText: 'Sí, reenviar',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (!result.isConfirmed) return;
                    try {
                        const response = await fetch('./controllers/C_Sunat.php?action=reenviar', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id_venta: id })
                        });
                        const data = await response.json();
                        Swal.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.success ? 'Enviado' : 'No se pudo enviar',
                            text: data.mensaje,
                            confirmButtonColor: '#23284E'
                        }).then(() => { if (data.success) window.location.reload(); });
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
                    }
                });
            });
        });

        document.querySelectorAll('.sunat-consultar-baja-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                try {
                    const response = await fetch(`./controllers/C_Sunat.php?action=consultar_baja&id_venta=${id}`);
                    const data = await response.json();
                    Swal.fire({
                        icon: data.success ? 'success' : 'info',
                        title: 'Estado de la baja',
                        text: data.mensaje,
                        confirmButtonColor: '#23284E'
                    }).then(() => window.location.reload());
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
                }
            });
        });

        document.querySelectorAll('.sunat-dar-de-baja-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                Swal.fire({
                    title: 'Reintentar aviso de baja a SUNAT',
                    text: 'Esta venta ya está anulada localmente; el intento anterior de avisarle a SUNAT falló. ¿Reintentar?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Sí, reintentar',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (!result.isConfirmed) return;
                    try {
                        const response = await fetch('./controllers/C_Sunat.php?action=dar_de_baja', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id_venta: id, motivo: 'Anulación solicitada por el usuario' })
                        });
                        const data = await response.json();
                        Swal.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.success ? 'Baja encolada' : 'No se pudo enviar',
                            text: data.mensaje,
                            confirmButtonColor: '#23284E'
                        }).then(() => { if (data.success) window.location.reload(); });
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
                    }
                });
            });
        });

        document.querySelectorAll('.sunat-ver-cdr-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                try {
                    const response = await fetch(`./controllers/C_Sunat.php?action=ver_cdr&tipo_documento=venta&id_referencia=${id}`);
                    const data = await response.json();
                    if (!data.success) {
                        Swal.fire({ icon: 'info', title: 'Sin información', text: data.mensaje, confirmButtonColor: '#23284E' });
                        return;
                    }
                    Swal.fire({
                        icon: 'info',
                        title: 'Estado SUNAT',
                        html: `<p class="text-start mb-1"><b>Fecha:</b> ${escapeHtmlHist(data.data.fecha)}</p>
                               <p class="text-start mb-1"><b>XML firmado:</b> ${data.data.xml_firmado ? 'guardado (' + data.data.xml_firmado.length + ' caracteres)' : '—'}</p>
                               <p class="text-start mb-0"><b>CDR:</b> ${data.data.cdr_zip_base64 ? 'guardado' : 'aún no llega'}</p>`,
                        confirmButtonColor: '#23284E'
                    });
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
                }
            });
        });

        document.querySelectorAll('.sunat-nota-credito-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const total = btn.dataset.total;
                let lineas = [];

                try {
                    const res = await fetch(`./controllers/C_Venta.php?action=detalles&id_venta=${id}`);
                    const datos = await res.json();
                    if (Array.isArray(datos) && datos.length > 0) lineas = datos;
                } catch (e) { /* se cae a NC total clásica */ }

                const lineasHtml = lineas.length > 0
                    ? lineas.map((l, i) => `
                        <div class="nc-linea d-flex align-items-center gap-2 py-1 border-bottom" data-idx="${i}">
                            <input type="checkbox" class="form-check-input nc-linea-check mt-0" data-idx="${i}" checked>
                            <span class="flex-grow-1 text-start" style="font-size: 13px;">${escapeHtmlHist(l.producto_nombre)}</span>
                            <input type="number" class="form-control form-control-sm nc-linea-cant text-end"
                                   style="width: 72px;" min="0.01" max="${parseFloat(l.piezas)}" step="0.01"
                                   value="${parseFloat(l.piezas)}" data-idx="${i}">
                            <span class="nc-linea-sub text-end text-muted" style="width: 84px; font-size: 13px;">
                                S/ ${(parseFloat(l.piezas) * parseFloat(l.precio_venta)).toFixed(2)}
                            </span>
                        </div>`).join('')
                    : '<p class="text-start text-muted" style="font-size: 13px;">No se pudo cargar el detalle.</p>';

                const { value: form } = await Swal.fire({
                    title: 'Emitir Nota de Crédito',
                    html: `
                        <p class="text-start text-muted mb-2" style="font-size: 13px;">
                            Esta venta ya fue aceptada por SUNAT hace más de 7 días: ya no se puede anular
                            directamente. La Nota de Crédito la anula ante SUNAT y devuelve el dinero desde
                            la caja de hoy. Marca solo las líneas que devuelves para una devolución parcial.
                        </p>
                        <div class="text-start mb-2 nc-lineas" style="max-height: 180px; overflow-y: auto;">${lineasHtml}</div>
                        <div class="text-start d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold" style="font-size: 13px;">Total a devolver</span>
                            <span class="fw-bold nc-total" style="font-size: 14px; color: #23284E;">S/ ${total}</span>
                        </div>
                        <input id="ncMotivo" class="swal2-input" placeholder="Motivo de la anulación">
                        <select id="ncMetodo" class="swal2-select">
                            <option value="1">Efectivo</option>
                            <option value="2">Tarjeta</option>
                            <option value="3">Yape/Plin</option>
                        </select>
                    `,
                    didOpen: () => {
                        document.querySelectorAll('.nc-linea').forEach(row => {
                            const idx = row.dataset.idx;
                            const linea = lineas[idx];
                            const check = row.querySelector('.nc-linea-check');
                            const cant  = row.querySelector('.nc-linea-cant');
                            const subEl = row.querySelector('.nc-linea-sub');

                            const recalcular = () => {
                                const totalSel = lineas.reduce((acc, l, j) => {
                                    const r = document.querySelector(`.nc-linea[data-idx="${j}"]`);
                                    if (!r.querySelector('.nc-linea-check').checked) return acc;
                                    return acc + (parseFloat(r.querySelector('.nc-linea-cant').value) || 0) * parseFloat(l.precio_venta);
                                }, 0);
                                subEl.innerText = 'S/ ' + (parseFloat(cant.value) * parseFloat(linea.precio_venta)).toFixed(2);
                                document.querySelector('.nc-total').innerText = 'S/ ' + totalSel.toFixed(2);
                            };
                            check.addEventListener('change', recalcular);
                            cant.addEventListener('input', recalcular);
                            cant.addEventListener('blur', () => {
                                const v = parseFloat(cant.value) || 0;
                                const max = parseFloat(linea.piezas);
                                if (v > max) { cant.value = max; recalcular(); }
                                if (v < 0.01) { cant.value = 0.01; recalcular(); }
                            });
                        });
                    },
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Emitir Nota de Crédito',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const motivo = document.getElementById('ncMotivo').value.trim();
                        const metodo_pago = parseInt(document.getElementById('ncMetodo').value, 10);
                        if (!motivo) { Swal.showValidationMessage('Indica el motivo de la anulación.'); return false; }

                        const items = [];
                        let monto = 0;
                        for (const l of lineas) {
                            const r = document.querySelector(`.nc-linea[data-idx="${lineas.indexOf(l)}"]`);
                            if (!r.querySelector('.nc-linea-check').checked) continue;
                            const cant = parseFloat(r.querySelector('.nc-linea-cant').value) || 0;
                            const max = parseFloat(l.piezas);
                            if (cant < 0.01 || cant > max) {
                                Swal.showValidationMessage(`Cantidad inválida para ${l.producto_nombre} (máx. ${max}).`);
                                return false;
                            }
                            items.push({ id_detalle: l.id_detalle, cantidad: cant });
                            monto += cant * parseFloat(l.precio_venta);
                        }
                        if (items.length === 0) {
                            Swal.showValidationMessage('Selecciona al menos una línea a devolver.');
                            return false;
                        }
                        monto = Math.round(monto * 100) / 100;
                        return { motivo, pagos: [{ metodo_pago, monto }], items };
                    }
                });
                if (!form) return;

                try {
                    const response = await fetch('./controllers/C_Sunat.php?action=nota_credito', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_venta: id, motivo: form.motivo, pagos: form.pagos, items: form.items })
                    });
                    const data = await response.json();
                    Swal.fire({
                        icon: data.success ? 'success' : 'error',
                        title: data.success ? 'Nota de Crédito emitida' : 'No se pudo emitir',
                        text: data.mensaje,
                        confirmButtonColor: '#23284E'
                    }).then(() => { if (data.success) window.location.reload(); });
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
                }
            });
        });

        // 4. Cambio de talla: no edita la venta original (queda intacta), solo mueve
        //    stock hoy y —si hay diferencia de precio— la cobra/devuelve como una venta
        //    nueva (origen=3). El servidor vuelve a validar todo (ventana, hermandad,
        //    stock); esto es solo la UX.
        function escapeHtmlHist(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
        }

        const cambioTallaModalEl = document.getElementById('cambioTallaModal');
        const cambioTallaModal   = new bootstrap.Modal(cambioTallaModalEl);
        let cambioTallaIdDetalle = null;
        let cambioTallaHermanas  = [];

        document.getElementById('ticketItems').addEventListener('click', async (e) => {
            const btn = e.target.closest('.cambiar-talla-btn');
            if (!btn) return;

            cambioTallaIdDetalle = btn.dataset.idDetalle;

            document.getElementById('cambioTallaLoading').style.display = 'block';
            document.getElementById('cambioTallaError').style.display   = 'none';
            document.getElementById('cambioTallaContent').style.display = 'none';
            document.getElementById('btnConfirmarCambioTalla').disabled = true;

            cambioTallaModal.show();

            try {
                const res  = await fetch(`./controllers/C_CambioTalla.php?action=tallas_disponibles&id_detalle=${cambioTallaIdDetalle}`);
                const json = await res.json();

                document.getElementById('cambioTallaLoading').style.display = 'none';

                if (!json.success) {
                    document.getElementById('cambioTallaError').textContent = json.mensaje || 'No se pudo cargar la información.';
                    document.getElementById('cambioTallaError').style.display = 'block';
                    return;
                }

                const data = json.data;
                cambioTallaHermanas = data.hermanas || [];

                document.getElementById('cambioTallaActual').textContent =
                    `${data.producto_vigente.nombre} — S/ ${parseFloat(data.producto_vigente.precio_unitario).toFixed(2)}`;

                const select = document.getElementById('cambioTallaSelect');
                if (cambioTallaHermanas.length === 0) {
                    select.innerHTML = '<option value="">Sin otras tallas con stock</option>';
                    document.getElementById('cambioTallaContent').style.display = 'block';
                    return;
                }

                select.innerHTML = cambioTallaHermanas.map(h => `
                    <option value="${h.id_producto}">
                        Talla ${escapeHtmlHist(h.talla || '—')} — S/ ${parseFloat(h.precio_unitario).toFixed(2)} (stock: ${parseFloat(h.stock_piezas)})
                    </option>
                `).join('');

                actualizarDiferenciaCambioTalla();
                document.getElementById('cambioTallaContent').style.display = 'block';
                document.getElementById('btnConfirmarCambioTalla').disabled = false;
            } catch (err) {
                document.getElementById('cambioTallaLoading').style.display = 'none';
                document.getElementById('cambioTallaError').textContent = 'Error de conexión.';
                document.getElementById('cambioTallaError').style.display = 'block';
            }
        });

        function actualizarDiferenciaCambioTalla() {
            const select = document.getElementById('cambioTallaSelect');
            const hermana = cambioTallaHermanas.find(h => String(h.id_producto) === select.value);
            if (!hermana) return;

            const diferencia = parseFloat(hermana.diferencia_unitaria);
            const diferenciaEl = document.getElementById('cambioTallaDiferencia');
            const notaEl        = document.getElementById('cambioTallaDiferenciaNota');
            const metodoBox     = document.getElementById('cambioTallaMetodoPagoBox');

            diferenciaEl.textContent = `S/ ${diferencia.toFixed(2)}`;
            diferenciaEl.className   = diferencia > 0 ? 'text-danger' : (diferencia < 0 ? 'text-success' : 'text-muted');

            if (diferencia > 0) {
                notaEl.textContent = 'El cliente paga esta diferencia.';
                metodoBox.style.display = 'block';
            } else if (diferencia < 0) {
                notaEl.textContent = 'Se le devuelve esta diferencia al cliente.';
                metodoBox.style.display = 'block';
            } else {
                notaEl.textContent = 'Sin diferencia de precio.';
                metodoBox.style.display = 'none';
            }
        }

        document.getElementById('cambioTallaSelect').addEventListener('change', actualizarDiferenciaCambioTalla);

        document.getElementById('btnConfirmarCambioTalla').addEventListener('click', async () => {
            const select = document.getElementById('cambioTallaSelect');
            const idProductoEntrante = select.value;
            if (!idProductoEntrante) return;

            const metodoPago = document.getElementById('cambioTallaMetodoPago').value;
            const btnConfirmar = document.getElementById('btnConfirmarCambioTalla');
            btnConfirmar.disabled = true;
            btnConfirmar.textContent = 'Guardando...';

            try {
                const res  = await fetch('./controllers/C_CambioTalla.php?action=registrar', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        id_detalle: cambioTallaIdDetalle,
                        id_producto_entrante: idProductoEntrante,
                        metodo_pago: metodoPago,
                    }),
                });
                const json = await res.json();

                if (json.ok) {
                    cambioTallaModal.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Talla cambiada',
                        text: json.mensaje,
                        showConfirmButton: false,
                        timer: 1500,
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'No se pudo cambiar la talla', text: json.mensaje, confirmButtonColor: '#23284E' });
                    btnConfirmar.disabled = false;
                    btnConfirmar.textContent = 'Confirmar cambio';
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
                btnConfirmar.disabled = false;
                btnConfirmar.textContent = 'Confirmar cambio';
            }
        });

        // 5. Lógica para impresión de tickets con Iframe reactivo
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

        // Compartir enlace público del comprobante (estilo tukifac): el cliente lo
        // abre y ve el comprobante sin necesidad de cuenta ni del panel.
        document.querySelectorAll('.share-link-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const token = btn.dataset.token;
                if (!token) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin enlace disponible',
                        text: 'Esta venta se registró antes de activarse los enlaces públicos y no tiene token. Solo las ventas nuevas los generan.',
                        confirmButtonColor: '#23284E'
                    });
                    return;
                }
                const url = `${window.location.origin}/comprobante?id=${btn.dataset.id}&t=${token}`;
                Swal.fire({
                    title: 'Enlace del comprobante',
                    html: `
                        <div class="text-start mb-2 text-muted" style="font-size:13px;">Cualquiera con este enlace puede ver el comprobante. Envíalo al cliente para que lo abra o lo guarde en PDF.</div>
                        <div class="d-flex align-items-center gap-2">
                            <input id="shareLinkInput" class="form-control form-control-sm" readonly value="${url}" onclick="this.select()">
                            <button type="button" class="btn btn-primary btn-sm flex-shrink-0" id="shareCopyBtn" style="background-color:#23284E; border:none;">
                                <i class="bi bi-clipboard me-1"></i>Copiar
                            </button>
                        </div>
                    `,
                    showCancelButton: false,
                    confirmButtonText: 'Cerrar',
                    confirmButtonColor: '#23284E',
                    didOpen: () => {
                        document.getElementById('shareCopyBtn').addEventListener('click', async () => {
                            const input = document.getElementById('shareLinkInput');
                            input.select();
                            try {
                                await navigator.clipboard.writeText(url);
                                Swal.fire({
                                    icon: 'success', title: 'Enlace copiado', text: 'Pégalo donde quieras compartirlo.',
                                    timer: 1500, showConfirmButton: false, confirmButtonColor: '#23284E'
                                });
                            } catch (e) {
                                Swal.fire({ icon: 'error', title: 'No se pudo copiar', text: 'Selecciona el enlace y cópialo con Ctrl+C.', confirmButtonColor: '#23284E' });
                            }
                        });
                    }
                });
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
