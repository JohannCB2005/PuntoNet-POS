<?php
// Restricción de acceso: Solo administradores logueados pueden ver el control global de cajas
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

// Cargar el modelo de Caja para listar reportes
require_once dirname(__DIR__) . '/models/M_Caja.php';
$model = M_Caja::singleton();

// Fecha de filtro (por defecto hoy según MySQL, no según PHP: se compara contra
// DATE(fecha_apertura) y ambos relojes difieren 5 horas — ver fechaHoyBD()).
require_once dirname(__DIR__) . '/config/conexion.php';
$hoyBD = fechaHoyBD();
$fechaFiltro = isset($_GET['fecha']) ? $_GET['fecha'] : $hoyBD;
$cajas = $model->listarPorFecha($fechaFiltro);
?>
<div class="container-fluid px-0">
    <!-- Encabezado y Formulario de Búsqueda por Fecha -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Control de Cajas</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Supervisa las aperturas y cierres de todos los usuarios.</p>
        </div>
        <form class="d-flex gap-2" method="GET" action="/">
            <input type="hidden" name="modulo" value="control-cajas">
            <input type="date" name="fecha" class="form-control" value="<?php echo htmlspecialchars($fechaFiltro); ?>" max="<?php echo htmlspecialchars($hoyBD); ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <!-- Panel de Resultados del Turno -->
    <div class="gp-card">
        <div class="table-responsive">
            <table class="table align-middle text-sm" style="font-size: 14px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th scope="col" class="pb-3">Usuario</th>
                        <th scope="col" class="pb-3">Horario</th>
                        <th scope="col" class="pb-3 text-end">M. Inicial</th>
                        <th scope="col" class="pb-3 text-end">Ventas Totales</th>
                        <th scope="col" class="pb-3 text-end">M. Cierre</th>
                        <th scope="col" class="pb-3 text-end">Diferencia</th>
                        <th scope="col" class="pb-3 text-center">Estado</th>
                        <th scope="col" class="pb-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cajas)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-safe-fill fs-2 mb-2 d-block"></i>
                                No hay registros de cajas en esta fecha.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        // Variables acumuladoras para mostrar totales consolidados al pie de la tabla
                        $sum_apertura = 0;
                        $sum_ventas = 0;
                        $sum_cierre = 0;
                        $sum_dif = 0;
                        foreach ($cajas as $caja): 
                            $sum_apertura += $caja['monto_apertura'];
                            
                            // Si la caja sigue activa, calcular en vivo el total de ventas acumuladas
                            $ventas = $caja['total_ventas'] !== null ? $caja['total_ventas'] : $model->calcularVentasAcumuladas($caja['id_caja']);
                            $sum_ventas += $ventas;
                            if ($caja['monto_cierre'] !== null) $sum_cierre += $caja['monto_cierre'];
                            if ($caja['diferencia'] !== null) $sum_dif += $caja['diferencia'];
                        ?>
                            <tr class="border-bottom">
                                <td class="py-3">
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($caja['nombre_completo']); ?></span>
                                        <span class="text-muted font-mono" style="font-size: 11px;">@<?php echo htmlspecialchars($caja['username']); ?></span>
                                    </div>
                                </td>
                                <td class="text-muted">
                                    A: <?php echo date('h:i A', strtotime($caja['fecha_apertura'])); ?> <br>
                                    C: <?php echo $caja['fecha_cierre'] ? date('h:i A', strtotime($caja['fecha_cierre'])) : '-'; ?>
                                </td>
                                <td class="text-end fw-medium">S/ <?php echo number_format($caja['monto_apertura'], 2); ?></td>
                                <td class="text-end text-success fw-bold">+ S/ <?php echo number_format($ventas, 2); ?> <br><small class="text-muted">(<?php echo $caja['num_ventas'] !== null ? $caja['num_ventas'] : '-'; ?> op.)</small></td>
                                <td class="text-end fw-bold">
                                    <?php echo $caja['monto_cierre'] !== null ? 'S/ ' . number_format($caja['monto_cierre'], 2) : '-'; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($caja['estado'] == 1): ?>
                                        <span class="text-muted fst-italic">En curso</span>
                                    <?php else: ?>
                                        <!-- Mostrar diferencia con colores indicativos (Verde/Azul para sobrantes, Rojo para faltantes) -->
                                        <?php if ($caja['diferencia'] > 0): ?>
                                            <span class="text-primary fw-bold">+ S/ <?php echo number_format($caja['diferencia'], 2); ?></span>
                                        <?php elseif ($caja['diferencia'] < 0): ?>
                                            <span class="text-danger fw-bold">- S/ <?php echo number_format(abs($caja['diferencia']), 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-success fw-bold">S/ 0.00</span>
                                        <?php endif; ?>
                                        <?php if ($caja['observaciones']): ?>
                                            <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($caja['observaciones']); ?>"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($caja['estado'] == 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">Abierta</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill">Cerrada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-link text-muted p-1 hover-text-primary ver-detalle-btn" data-id="<?= $caja['id_caja'] ?>" title="Ver Detalle"><i class="bi bi-eye-fill"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Fila de Totales del Día Consolidados -->
                        <tr class="bg-light">
                            <td colspan="2" class="text-end fw-bold">TOTALES DEL DÍA:</td>
                            <td class="text-end fw-bold">S/ <?php echo number_format($sum_apertura, 2); ?></td>
                            <td class="text-end fw-bold text-success">S/ <?php echo number_format($sum_ventas, 2); ?></td>
                            <td class="text-end fw-bold">S/ <?php echo number_format($sum_cierre, 2); ?></td>
                            <td class="text-end fw-bold <?php echo $sum_dif > 0 ? 'text-primary' : ($sum_dif < 0 ? 'text-danger' : 'text-success'); ?>">
                                S/ <?php echo number_format($sum_dif, 2); ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detalle de Caja -->
<div class="modal fade" id="cajaDetalleModal" tabindex="-1" aria-labelledby="cajaDetalleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="cajaDetalleModalLabel">Detalle de Caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4" id="cajaResumenCards">
                    <!-- Se llenará dinámicamente -->
                </div>
                <h6 class="fw-bold mb-3">Ventas Realizadas</h6>
                <div class="table-responsive">
                    <table class="table align-middle text-sm" style="font-size: 14px;">
                        <thead>
                            <tr class="text-muted border-bottom">
                                <th>N° Venta</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Método</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="cajaVentasBody">
                            <!-- Se llenará dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inicializar tooltips para ver observaciones de descuadres de caja al pasar el mouse
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    const cajaDetalleModalEl = document.getElementById('cajaDetalleModal');
    if (cajaDetalleModalEl) {
        const cajaDetalleModal = new bootstrap.Modal(cajaDetalleModalEl);
        
        document.querySelectorAll('.ver-detalle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const idCaja = this.getAttribute('data-id');
                fetch(`controllers/C_Caja.php?action=detalle&id_caja=${idCaja}`)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            const resumen = data.data.resumen;
                            const resumenHtml = `
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold text-muted mb-3"><i class="bi bi-cash-stack text-success"></i> Efectivo</h6>
                                            <div class="d-flex justify-content-between mb-1"><span>Sistema:</span> <strong>S/ ${parseFloat(resumen.efectivo.sistema).toFixed(2)}</strong></div>
                                            <div class="d-flex justify-content-between mb-1"><span>Declarado:</span> <strong>S/ ${parseFloat(resumen.efectivo.declarado).toFixed(2)}</strong></div>
                                            <div class="d-flex justify-content-between border-top pt-1 mt-1"><span>Diferencia:</span> <strong class="${resumen.efectivo.diferencia < 0 ? 'text-danger' : (resumen.efectivo.diferencia > 0 ? 'text-primary' : 'text-success')}">S/ ${parseFloat(resumen.efectivo.diferencia).toFixed(2)}</strong></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold text-muted mb-3"><i class="bi bi-phone text-info"></i> Yape / Plin</h6>
                                            <div class="d-flex justify-content-between mb-1"><span>Sistema:</span> <strong>S/ ${parseFloat(resumen.yape.sistema).toFixed(2)}</strong></div>
                                            <div class="d-flex justify-content-between mb-1"><span>Declarado:</span> <strong>S/ ${parseFloat(resumen.yape.declarado).toFixed(2)}</strong></div>
                                            <div class="d-flex justify-content-between border-top pt-1 mt-1"><span>Diferencia:</span> <strong class="${resumen.yape.diferencia < 0 ? 'text-danger' : (resumen.yape.diferencia > 0 ? 'text-primary' : 'text-success')}">S/ ${parseFloat(resumen.yape.diferencia).toFixed(2)}</strong></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold text-muted mb-3"><i class="bi bi-credit-card text-warning"></i> Tarjeta</h6>
                                            <div class="d-flex justify-content-between mb-1"><span>Sistema:</span> <strong>S/ ${parseFloat(resumen.tarjeta.sistema).toFixed(2)}</strong></div>
                                            <div class="d-flex justify-content-between mb-1"><span>Declarado:</span> <strong>S/ ${parseFloat(resumen.tarjeta.declarado).toFixed(2)}</strong></div>
                                            <div class="d-flex justify-content-between border-top pt-1 mt-1"><span>Diferencia:</span> <strong class="${resumen.tarjeta.diferencia < 0 ? 'text-danger' : (resumen.tarjeta.diferencia > 0 ? 'text-primary' : 'text-success')}">S/ ${parseFloat(resumen.tarjeta.diferencia).toFixed(2)}</strong></div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            document.getElementById('cajaResumenCards').innerHTML = resumenHtml;
                            
                            let ventasHtml = '';
                            if(data.data.ventas && data.data.ventas.length > 0) {
                                data.data.ventas.forEach(v => {
                                    let icono = 'bi-cash';
                                    let textClass = 'text-success';
                                    let emoji = '💵';
                                    const metodoLower = v.metodo_pago.toLowerCase();
                                    if(metodoLower.includes('mixto')) {
                                        // Pagada con más de un método (ver pagos_venta para el desglose real).
                                        icono = 'bi-shuffle'; textClass = 'text-primary'; emoji = '🔀';
                                    } else if(metodoLower.includes('yape') || metodoLower.includes('plin')) {
                                        icono = 'bi-phone'; textClass = 'text-info'; emoji = '📱';
                                    } else if(metodoLower.includes('tarjeta')) {
                                        icono = 'bi-credit-card'; textClass = 'text-warning'; emoji = '💳';
                                    }
                                    
                                    let time = '-';
                                    if(v.fecha_venta) {
                                        const dateObj = new Date(v.fecha_venta);
                                        if(!isNaN(dateObj)) {
                                            time = dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                                        } else {
                                            time = v.fecha_venta.split(' ')[1] || v.fecha_venta;
                                        }
                                    }

                                    ventasHtml += `
                                        <tr>
                                            <td class="fw-medium">#${v.id_venta}</td>
                                            <td class="text-muted">${time}</td>
                                            <td>${v.nombre_cliente || 'Público General'}</td>
                                            <td><span class="${textClass}">${emoji} ${v.metodo_pago}</span></td>
                                            <td class="text-end fw-bold">S/ ${parseFloat(v.total).toFixed(2)}</td>
                                        </tr>
                                    `;
                                });
                            } else {
                                ventasHtml = '<tr><td colspan="5" class="text-center text-muted py-3">No hay ventas registradas en esta caja.</td></tr>';
                            }
                            document.getElementById('cajaVentasBody').innerHTML = ventasHtml;
                            
                            cajaDetalleModal.show();
                        } else {
                            alert('Error al cargar el detalle: ' + (data.message || 'Error desconocido'));
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching detalle caja:', err);
                        alert('Ocurrió un error de red al intentar obtener el detalle.');
                    });
            });
        });
    }
});
</script>
