<?php
// Módulo de planilla: solo Administrador ve las comisiones de todos los vendedores.
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
?>

<style>
    .hover-text-primary:hover { color: #0284c7 !important; }
    .badge-concepto-venta { background-color: #0284c7; color: #fff; }
    .badge-concepto-cambio_talla { background-color: #f59e0b; color: #fff; }
    .badge-concepto-separacion { background-color: #10b981; color: #fff; }
    .badge-concepto-online { background-color: #8b5cf6; color: #fff; }
</style>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Comisiones</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Comisión devengada por vendedor, calculada sobre lo realmente entregado y cobrado.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <input type="date" class="form-control form-control-sm" id="filtroDesde" style="width: auto;">
            <span class="text-muted">a</span>
            <input type="date" class="form-control form-control-sm" id="filtroHasta" style="width: auto;">
            <button class="btn btn-outline-primary btn-sm" id="btnFiltrar"><i class="bi bi-search"></i> Filtrar</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <p class="text-muted mb-1" style="font-size: 13px;">Total devengado en el periodo</p>
                    <h3 class="fw-bold text-primary mb-0" id="totalDevengado">S/ 0.00</h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <p class="text-muted mb-1" style="font-size: 13px;">Retenido en separaciones pendientes <span class="text-muted" style="font-size:11px;">(no pagable aún)</span></p>
                    <h3 class="fw-bold text-warning mb-0" id="totalPendiente">S/ 0.00</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13.5px;">
                    <thead class="table-light">
                        <tr class="text-muted" style="font-size: 12px;">
                            <th>Vendedor</th>
                            <th class="text-center">Nº ventas</th>
                            <th class="text-end">Comisión devengada</th>
                            <th class="text-center">Detalle</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResumen">
                        <tr><td colspan="4" class="text-center text-muted py-4">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mt-4" id="cardPendientes" style="display: none;">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3" style="font-size: 14px;">
                <i class="bi bi-hourglass-split text-warning"></i> Separaciones pendientes (comisión retenida)
            </h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr class="text-muted" style="font-size: 11px;">
                            <th>Vendedor</th>
                            <th>Separación</th>
                            <th>Vence</th>
                            <th class="text-end">Comisión retenida</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPendientes"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Detalle de comisiones de un vendedor -->
<div class="modal fade" id="modalDetalleComision" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark">Detalle de comisiones — <span id="detVendedorNombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-sm" style="font-size: 12.5px;">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th>Concepto</th>
                                <th class="text-end">Comisión</th>
                            </tr>
                        </thead>
                        <tbody id="detalleComisionBody"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between pt-2 border-top">
                    <span class="fw-bold">Total</span>
                    <span class="fw-bold text-primary" id="detalleComisionTotal"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const hoy = new Date();
    const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    document.getElementById('filtroDesde').value = primerDiaMes.toISOString().slice(0, 10);
    document.getElementById('filtroHasta').value = hoy.toISOString().slice(0, 10);

    cargarResumen();
    cargarPendientes();

    document.getElementById('btnFiltrar').addEventListener('click', cargarResumen);
});

async function cargarResumen() {
    const desde = document.getElementById('filtroDesde').value;
    const hasta = document.getElementById('filtroHasta').value;
    const tbody = document.getElementById('tbodyResumen');
    try {
        const resp = await fetch(`./controllers/C_Comision.php?action=resumen&desde=${desde}&hasta=${hasta}`);
        const json = await resp.json();
        const filas = json.success ? json.data : [];

        if (filas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Sin comisiones devengadas en este periodo.</td></tr>';
            document.getElementById('totalDevengado').innerText = 'S/ 0.00';
            return;
        }

        let total = 0;
        tbody.innerHTML = filas.map(f => {
            total += parseFloat(f.comision_total);
            return `
                <tr>
                    <td class="fw-semibold">${f.vendedor}</td>
                    <td class="text-center">${f.num_ventas}</td>
                    <td class="text-end fw-bold text-primary">S/ ${parseFloat(f.comision_total).toFixed(2)}</td>
                    <td class="text-center">
                        <button class="btn btn-link text-muted p-1 hover-text-primary" title="Ver detalle" onclick="abrirDetalle(${f.id_usuario}, '${f.vendedor.replace(/'/g, "\\'")}')">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        document.getElementById('totalDevengado').innerText = `S/ ${total.toFixed(2)}`;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">No se pudo cargar el resumen.</td></tr>';
    }
}

async function cargarPendientes() {
    try {
        const resp = await fetch('./controllers/C_Comision.php?action=pendientes');
        const json = await resp.json();
        const filas = json.success ? json.data : [];
        const card = document.getElementById('cardPendientes');
        const tbody = document.getElementById('tbodyPendientes');

        if (filas.length === 0) {
            card.style.display = 'none';
            document.getElementById('totalPendiente').innerText = 'S/ 0.00';
            return;
        }

        card.style.display = 'block';
        let total = 0;
        tbody.innerHTML = filas.map(f => {
            total += parseFloat(f.comision_pendiente);
            return `
                <tr>
                    <td>${f.vendedor}</td>
                    <td><span class="fw-semibold text-primary">${f.codigo}</span></td>
                    <td>${f.fecha_vencimiento}</td>
                    <td class="text-end fw-bold text-warning">S/ ${parseFloat(f.comision_pendiente).toFixed(2)}</td>
                </tr>
            `;
        }).join('');
        document.getElementById('totalPendiente').innerText = `S/ ${total.toFixed(2)}`;
    } catch (e) {
        document.getElementById('cardPendientes').style.display = 'none';
    }
}

async function abrirDetalle(id_usuario, vendedor) {
    const desde = document.getElementById('filtroDesde').value;
    const hasta = document.getElementById('filtroHasta').value;
    document.getElementById('detVendedorNombre').innerText = vendedor;

    const resp = await fetch(`./controllers/C_Comision.php?action=detalle&id_usuario=${id_usuario}&desde=${desde}&hasta=${hasta}`);
    const json = await resp.json();
    const filas = json.success ? json.data : [];

    const badgeClase = {
        'venta': 'badge-concepto-venta',
        'cambio_talla': 'badge-concepto-cambio_talla',
    };

    let total = 0;
    document.getElementById('detalleComisionBody').innerHTML = filas.map(f => {
        total += parseFloat(f.comision);
        let clase = badgeClase[f.tipo] || 'badge-concepto-venta';
        if (f.concepto.startsWith('Separación')) clase = 'badge-concepto-separacion';
        if (f.concepto.startsWith('Pedido online')) clase = 'badge-concepto-online';
        return `
            <tr>
                <td>${f.fecha.slice(0, 10)}</td>
                <td>${f.producto}</td>
                <td class="text-center">${parseFloat(f.cantidad).toFixed(2)}</td>
                <td><span class="badge ${clase}" style="font-size:10.5px;">${f.concepto}</span></td>
                <td class="text-end fw-bold">S/ ${parseFloat(f.comision).toFixed(2)}</td>
            </tr>
        `;
    }).join('');
    document.getElementById('detalleComisionTotal').innerText = `S/ ${total.toFixed(2)}`;

    new bootstrap.Modal(document.getElementById('modalDetalleComision')).show();
}
</script>
