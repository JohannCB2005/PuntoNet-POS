<?php
if (!isset($_SESSION['id_usuario'])) {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
$esAdmin = ($_SESSION['rol'] === 'Administrador');
?>

<style>
    .badge-sep-1 { background-color: #f59e0b; color: #fff; } /* Pendiente */
    .badge-sep-2 { background-color: #10b981; color: #fff; } /* Despachada */
    .badge-sep-0 { background-color: #6b7280; color: #fff; } /* Anulada */
    .fila-vencida { background-color: #fef2f2 !important; }
    .hover-text-primary:hover { color: #23284E !important; }
    .hover-text-danger:hover  { color: #ef4444 !important; }
    .hover-text-success:hover { color: #10b981 !important; }
</style>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Separaciones</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Pedidos apartados con anticipo, pendientes de completar el pago y despachar.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" id="filtroEstado" style="width: auto;">
                <option value="1" selected>Pendientes</option>
                <option value="2">Despachadas</option>
                <option value="0">Anuladas</option>
                <option value="">Todas</option>
            </select>
            <a href="/nueva-venta" class="gp-btn-primary d-flex align-items-center gap-2 border-0 text-decoration-none">
                <i class="bi bi-bookmark-star"></i>
                <span>Nueva separación</span>
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13.5px;">
                    <thead class="table-light">
                        <tr class="text-muted" style="font-size: 12px;">
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Vence</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Abonado</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodySeparaciones">
                        <tr><td colspan="9" class="text-center text-muted py-4">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ver detalle -->
<div class="modal fade" id="modalVerSeparacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Separación <span id="verCodigo"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Cliente</span><span class="fw-semibold" id="verCliente"></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Vendedor</span><span class="fw-semibold" id="verVendedor"></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Vence</span><span class="fw-semibold" id="verVence"></span></div>
                <hr>
                <h6 class="fw-bold text-primary mb-2" style="font-size: 13px;">Historial de pagos</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm" style="font-size: 12.5px;">
                        <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th class="text-end">Monto</th></tr></thead>
                        <tbody id="verAbonosBody"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Total mercadería</span><span id="verTotal"></span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Abonado</span><span id="verAbonado"></span></div>
                <div class="d-flex justify-content-between pt-2 border-top">
                    <span class="fw-bold">Saldo pendiente</span>
                    <span class="fw-bold text-primary" id="verSaldo"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Abonar / Despachar (comparten la tabla de líneas de pago) -->
<div class="modal fade" id="modalPagoSeparacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="pagoModalTitulo">Abonar</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="pagoIdSeparacion">
                <input type="hidden" id="pagoModo"> <!-- 'abonar' | 'despachar' -->

                <div class="bg-light p-3 rounded-3 mb-3" style="font-size: 13px;">
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">Saldo pendiente</span><span class="fw-bold text-primary" id="pagoSaldoActual">S/ 0.00</span></div>
                </div>

                <div class="mb-3" id="pagoComprobanteWrap" style="display: none;">
                    <label class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Comprobante a emitir</label>
                    <select class="form-select form-select-sm" id="pagoTipoComprobante">
                        <option value="3" selected>Nota de Venta</option>
                        <option value="1">Boleta</option>
                        <option value="2">Factura</option>
                    </select>
                </div>

                <div class="mb-3" id="pagoLineasWrap">
                    <label class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Forma de pago</label>
                    <div class="table-responsive" style="overflow: visible;">
                        <table class="table table-sm align-middle mb-1" style="font-size: 12.5px;">
                            <thead>
                                <tr class="text-muted" style="font-size: 11px;">
                                    <th style="min-width:120px;">Método de pago</th>
                                    <th style="min-width:110px;">Referencia</th>
                                    <th style="min-width:90px;">Monto</th>
                                    <th style="width:32px;"></th>
                                </tr>
                            </thead>
                            <tbody id="pagoSepBody"></tbody>
                        </table>
                    </div>
                    <a href="#" id="btnAgregarPagoSep" class="d-inline-flex align-items-center gap-1 text-decoration-none" style="font-size: 12.5px;">
                        <i class="bi bi-plus-circle-fill"></i> Agregar pago
                    </a>
                    <div id="pagoSepDiferenciaHint" class="badge w-100 mt-2 py-2"></div>
                </div>

                <button class="gp-btn-primary w-100 border-0 py-2.5" id="btnConfirmarPagoSep" disabled>
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Anular (solo Administrador) -->
<div class="modal fade" id="modalAnularSeparacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Anular separación</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="anularIdSeparacion">
                <p class="text-muted" style="font-size: 13px;">
                    El stock reservado vuelve al inventario. Los pagos ya cobrados <strong>no</strong> se anulan
                    ni se devuelven automáticamente.
                </p>
                <label class="form-label fw-semibold text-muted mb-1" style="font-size: 12px;">Motivo</label>
                <textarea class="form-control" id="anularMotivo" rows="3" placeholder="Ej. El cliente ya no recogerá el pedido"></textarea>
                <button class="btn btn-danger w-100 mt-3" id="btnConfirmarAnular">Anular separación</button>
            </div>
        </div>
    </div>
</div>

<script>
const ES_ADMIN = <?php echo $esAdmin ? 'true' : 'false'; ?>;
let separacionesCache = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarSeparaciones();
    document.getElementById('filtroEstado').addEventListener('change', cargarSeparaciones);
});

async function cargarSeparaciones() {
    const estado = document.getElementById('filtroEstado').value;
    const url = './controllers/C_Separacion.php?action=listar' + (estado !== '' ? `&estado=${estado}` : '');
    const tbody = document.getElementById('tbodySeparaciones');
    try {
        const resp = await fetch(url);
        const json = await resp.json();
        separacionesCache = json.success ? json.data : [];
        renderTabla();
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">No se pudo cargar el listado.</td></tr>';
    }
}

function badgeEstado(s) {
    if (s.estado == 2) return '<span class="badge badge-sep-2">Despachada</span>';
    if (s.estado == 0) return '<span class="badge badge-sep-0">Anulada</span>';
    return s.vencida ? '<span class="badge badge-sep-0" style="background-color:#ef4444;">Vencida</span>' : '<span class="badge badge-sep-1">Pendiente</span>';
}

function renderTabla() {
    const tbody = document.getElementById('tbodySeparaciones');
    if (separacionesCache.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No hay separaciones en este filtro.</td></tr>';
        return;
    }
    tbody.innerHTML = separacionesCache.map(s => {
        const esPendiente = s.estado == 1;
        let acciones = `<button class="btn btn-link text-muted p-1 hover-text-primary" title="Ver detalle" onclick="abrirVer(${s.id_separacion})"><i class="bi bi-eye-fill"></i></button>`;
        if (esPendiente) {
            acciones += `<button class="btn btn-link text-muted p-1 hover-text-success" title="Abonar" onclick="abrirPago(${s.id_separacion}, 'abonar')"><i class="bi bi-cash-coin"></i></button>`;
            acciones += `<button class="btn btn-link text-muted p-1 hover-text-primary" title="Despachar" onclick="abrirPago(${s.id_separacion}, 'despachar')"><i class="bi bi-box-seam-fill"></i></button>`;
            if (ES_ADMIN) {
                acciones += `<button class="btn btn-link text-muted p-1 hover-text-danger" title="Anular" onclick="abrirAnular(${s.id_separacion})"><i class="bi bi-x-circle-fill"></i></button>`;
            }
        }
        return `
            <tr class="${s.vencida && esPendiente ? 'fila-vencida' : ''}">
                <td><span class="fw-bold text-primary">${s.codigo}</span></td>
                <td>${s.cliente}</td>
                <td>${s.fecha}</td>
                <td>${s.fecha_vencimiento}</td>
                <td class="text-end">S/ ${parseFloat(s.total).toFixed(2)}</td>
                <td class="text-end">S/ ${parseFloat(s.abonado).toFixed(2)}</td>
                <td class="text-end fw-bold">S/ ${parseFloat(s.saldo).toFixed(2)}</td>
                <td class="text-center">${badgeEstado(s)}</td>
                <td class="text-center">${acciones}</td>
            </tr>
        `;
    }).join('');
}

// ── Modal Ver ──
async function abrirVer(id) {
    const resp = await fetch(`./controllers/C_Separacion.php?action=detalle&id_separacion=${id}`);
    const json = await resp.json();
    if (!json.success) { Swal.fire('Error', json.mensaje, 'error'); return; }
    const s = json.data;

    document.getElementById('verCodigo').innerText = s.codigo;
    document.getElementById('verCliente').innerText = s.cliente;
    document.getElementById('verVendedor').innerText = s.vendedor;
    document.getElementById('verVence').innerText = s.fecha_vencimiento;
    document.getElementById('verTotal').innerText = `S/ ${parseFloat(s.total).toFixed(2)}`;
    document.getElementById('verAbonado').innerText = `S/ ${parseFloat(s.abonado).toFixed(2)}`;
    document.getElementById('verSaldo').innerText = `S/ ${parseFloat(s.saldo).toFixed(2)}`;

    const nombresMetodo = { 1: 'Efectivo', 2: 'Yape/Plin', 3: 'Tarjeta', 4: 'Mixto', 5: 'Transferencia' };
    document.getElementById('verAbonosBody').innerHTML = s.abonos.map(a => `
        <tr>
            <td>${a.fecha}</td>
            <td>${a.es_anticipo == 1 ? 'Anticipo' : 'Abono'} · ${nombresMetodo[a.metodo_pago] || ''}</td>
            <td class="text-end">S/ ${parseFloat(a.total).toFixed(2)}</td>
        </tr>
    `).join('');

    new bootstrap.Modal(document.getElementById('modalVerSeparacion')).show();
}

// ── Modal Abonar / Despachar (líneas de pago, mismo patrón que Nueva Venta) ──
let pagoSepLineas = [];
let pagoSepSaldo = 0;
let pagoSepEditadoManualmente = false;

const pagoSepBody = document.getElementById('pagoSepBody');
const pagoSepHint = document.getElementById('pagoSepDiferenciaHint');
const btnAgregarPagoSep = document.getElementById('btnAgregarPagoSep');
const btnConfirmarPagoSep = document.getElementById('btnConfirmarPagoSep');

async function abrirPago(id, modo) {
    const resp = await fetch(`./controllers/C_Separacion.php?action=detalle&id_separacion=${id}`);
    const json = await resp.json();
    if (!json.success) { Swal.fire('Error', json.mensaje, 'error'); return; }
    const s = json.data;

    document.getElementById('pagoIdSeparacion').value = id;
    document.getElementById('pagoModo').value = modo;
    pagoSepSaldo = parseFloat(s.saldo);
    document.getElementById('pagoSaldoActual').innerText = `S/ ${pagoSepSaldo.toFixed(2)}`;
    document.getElementById('pagoModalTitulo').innerText = modo === 'despachar' ? `Despachar ${s.codigo}` : `Abonar a ${s.codigo}`;

    const comprobanteWrap = document.getElementById('pagoComprobanteWrap');
    const lineasWrap = document.getElementById('pagoLineasWrap');

    if (modo === 'despachar') {
        comprobanteWrap.style.display = 'block';
        if (pagoSepSaldo <= 0.01) {
            // Ya está pagado por completo: no hace falta ninguna línea de pago, solo elegir comprobante.
            lineasWrap.style.display = 'none';
            pagoSepLineas = [];
        } else {
            lineasWrap.style.display = 'block';
            pagoSepLineas = [{ metodo_pago: 1, referencia: '', monto: pagoSepSaldo }];
        }
    } else {
        comprobanteWrap.style.display = 'none';
        lineasWrap.style.display = 'block';
        pagoSepLineas = [{ metodo_pago: 1, referencia: '', monto: 0 }];
    }
    pagoSepEditadoManualmente = false;
    renderPagoSep();
    new bootstrap.Modal(document.getElementById('modalPagoSeparacion')).show();
}

function renderPagoSep() {
    if (pagoSepLineas.length === 1 && !pagoSepEditadoManualmente) {
        const modo = document.getElementById('pagoModo').value;
        pagoSepLineas[0].monto = (modo === 'despachar') ? pagoSepSaldo : 0;
    }
    pagoSepBody.innerHTML = pagoSepLineas.map((p, idx) => `
        <tr>
            <td>
                <select class="form-select form-select-sm ps-metodo" data-idx="${idx}" style="font-size:12px;">
                    <option value="1" ${p.metodo_pago === 1 ? 'selected' : ''}>💵 Efectivo</option>
                    <option value="2" ${p.metodo_pago === 2 ? 'selected' : ''}>📱 Yape/Plin</option>
                    <option value="3" ${p.metodo_pago === 3 ? 'selected' : ''}>💳 Tarjeta</option>
                    <option value="5" ${p.metodo_pago === 5 ? 'selected' : ''}>🏦 Transferencia</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm ps-referencia" data-idx="${idx}" value="${p.referencia}" placeholder="Opcional" style="font-size:12px;"></td>
            <td><input type="number" class="form-control form-control-sm ps-monto" data-idx="${idx}" value="${p.monto.toFixed(2)}" step="0.01" min="0" style="font-size:12px;"></td>
            <td class="text-center">
                ${pagoSepLineas.length > 1 ? `<button type="button" class="btn btn-sm btn-danger py-0 px-2 ps-eliminar" data-idx="${idx}" style="border-radius:6px;"><i class="bi bi-trash3-fill" style="font-size:11px;"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
    actualizarHintPagoSep();
}

function actualizarHintPagoSep() {
    const modo = document.getElementById('pagoModo').value;
    const suma = pagoSepLineas.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0);
    let ok;
    if (modo === 'despachar') {
        if (pagoSepSaldo <= 0.01) {
            pagoSepHint.className = 'badge w-100 mt-2 py-2 bg-success';
            pagoSepHint.innerText = 'Sin saldo pendiente — solo se marcará como despachada.';
            ok = true;
        } else {
            const diferencia = Math.round((pagoSepSaldo - suma) * 100) / 100;
            ok = Math.abs(diferencia) <= 0.01;
            pagoSepHint.className = 'badge w-100 mt-2 py-2 ' + (ok ? 'bg-success' : 'bg-danger');
            pagoSepHint.innerText = ok ? 'Listo para despachar ✓' : (diferencia > 0 ? `Falta: S/ ${diferencia.toFixed(2)}` : `Sobra: S/ ${Math.abs(diferencia).toFixed(2)}`);
        }
    } else {
        ok = suma > 0 && suma <= pagoSepSaldo + 0.01;
        pagoSepHint.className = 'badge w-100 mt-2 py-2 ' + (ok ? 'bg-success' : 'bg-danger');
        pagoSepHint.innerText = ok ? `Abono válido · Saldo tras abonar: S/ ${Math.max(0, pagoSepSaldo - suma).toFixed(2)}` : (suma <= 0 ? 'Ingresa un monto mayor a cero' : `El abono supera el saldo (S/ ${pagoSepSaldo.toFixed(2)})`);
    }
    btnConfirmarPagoSep.disabled = !ok;
}

pagoSepBody.addEventListener('change', (e) => {
    const idx = parseInt(e.target.dataset.idx);
    if (isNaN(idx)) return;
    if (e.target.classList.contains('ps-metodo')) {
        pagoSepLineas[idx].metodo_pago = parseInt(e.target.value);
    } else if (e.target.classList.contains('ps-referencia')) {
        pagoSepLineas[idx].referencia = e.target.value.trim();
    } else if (e.target.classList.contains('ps-monto')) {
        pagoSepLineas[idx].monto = parseFloat(e.target.value) || 0;
        pagoSepEditadoManualmente = true;
    }
    actualizarHintPagoSep();
});

pagoSepBody.addEventListener('click', (e) => {
    const btn = e.target.closest('.ps-eliminar');
    if (!btn) return;
    const idx = parseInt(btn.dataset.idx);
    pagoSepLineas.splice(idx, 1);
    if (pagoSepLineas.length === 1) pagoSepEditadoManualmente = false;
    renderPagoSep();
});

btnAgregarPagoSep.addEventListener('click', (e) => {
    e.preventDefault();
    const objetivo = document.getElementById('pagoModo').value === 'despachar' ? pagoSepSaldo : pagoSepSaldo;
    const sumaActual = pagoSepLineas.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0);
    const restante = Math.max(0, Math.round((objetivo - sumaActual) * 100) / 100);
    pagoSepLineas.push({ metodo_pago: 1, referencia: '', monto: restante });
    pagoSepEditadoManualmente = true;
    renderPagoSep();
});

btnConfirmarPagoSep.addEventListener('click', async () => {
    const id_separacion = document.getElementById('pagoIdSeparacion').value;
    const modo = document.getElementById('pagoModo').value;
    const pagos = pagoSepLineas.map(p => ({ metodo_pago: p.metodo_pago, monto: parseFloat(p.monto) || 0, referencia: p.referencia || null }));

    const endpoint = modo === 'despachar' ? 'despachar' : 'abonar';
    const payload = { id_separacion, pagos };
    if (modo === 'despachar') {
        payload.tipo_comprobante = parseInt(document.getElementById('pagoTipoComprobante').value);
    }

    btnConfirmarPagoSep.disabled = true;
    try {
        const resp = await fetch(`./controllers/C_Separacion.php?action=${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await resp.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalPagoSeparacion')).hide();
            Swal.fire({ icon: 'success', title: modo === 'despachar' ? '¡Separación despachada!' : '¡Abono registrado!', text: json.mensaje, timer: 1500, showConfirmButton: false });
            cargarSeparaciones();
        } else {
            Swal.fire('Error', json.mensaje, 'error');
            btnConfirmarPagoSep.disabled = false;
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudo contactar al servidor.', 'error');
        btnConfirmarPagoSep.disabled = false;
    }
});

// ── Modal Anular ──
function abrirAnular(id) {
    document.getElementById('anularIdSeparacion').value = id;
    document.getElementById('anularMotivo').value = '';
    new bootstrap.Modal(document.getElementById('modalAnularSeparacion')).show();
}

document.getElementById('btnConfirmarAnular').addEventListener('click', async () => {
    const id_separacion = document.getElementById('anularIdSeparacion').value;
    const motivo = document.getElementById('anularMotivo').value.trim();
    if (!motivo) {
        Swal.fire('Atención', 'Debes indicar el motivo de anulación.', 'warning');
        return;
    }
    Swal.fire({
        title: '¿Anular esta separación?',
        text: 'El stock reservado volverá al inventario. Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, anular'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            const resp = await fetch('./controllers/C_Separacion.php?action=anular', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_separacion, motivo })
            });
            const json = await resp.json();
            if (json.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalAnularSeparacion')).hide();
                Swal.fire('Anulada', json.mensaje, 'success');
                cargarSeparaciones();
            } else {
                Swal.fire('Error', json.mensaje, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo contactar al servidor.', 'error');
        }
    });
});
</script>
