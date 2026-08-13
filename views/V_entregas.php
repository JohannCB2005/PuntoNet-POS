<?php
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Administrador', 'Vendedor'], true)) {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
require_once dirname(__DIR__) . '/models/M_Promocion.php';
require_once dirname(__DIR__) . '/models/M_Entrega.php';

if (empty($_SESSION['csrf_entrega'])) {
    $_SESSION['csrf_entrega'] = bin2hex(random_bytes(32));
}

$idPromocionSolicitada = isset($_GET['id_promocion']) ? (int) $_GET['id_promocion'] : 0;
$modelPromo = M_Promocion::singleton();
$promocion = $idPromocionSolicitada > 0 ? $modelPromo->obtenerPorId($idPromocionSolicitada) : $modelPromo->obtenerUltima();
$promociones = $modelPromo->listar();
$beneficiarios = $promocion ? M_Entrega::singleton()->listarBeneficiarios($promocion) : [];
$bimestreNombre = [1=>'1er Bimestre',2=>'2do Bimestre',3=>'3er Bimestre',4=>'4to Bimestre'];
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Entrega de Módulos</h4>
            <p class="text-muted mb-0"><?php echo $promocion ? htmlspecialchars($promocion['nombre']) : 'Sin promoción configurada'; ?></p>
        </div>
        <?php if (!empty($promociones)): ?>
        <select class="form-select" style="max-width:280px;" onchange="window.location.href='entregas?id_promocion='+this.value">
            <?php foreach ($promociones as $p): ?>
                <option value="<?php echo $p['id_promocion']; ?>" <?php echo $promocion && $p['id_promocion'] == $promocion['id_promocion'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['nombre']); ?> (<?php echo $bimestreNombre[$p['bimestre']] ?? $p['bimestre']; ?> <?php echo $p['anio_requerido']; ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <?php if (!$promocion): ?>
        <div class="gp-card text-center py-5 text-muted">
            <i class="bi bi-gift" style="font-size:32px;"></i>
            <p class="mb-0 mt-2">Todavía no se ha creado ninguna promoción. <?php echo $_SESSION['rol'] === 'Administrador' ? 'Crea una desde el módulo Promociones.' : 'Pide al administrador que configure una.'; ?></p>
        </div>
    <?php else: ?>
        <?php
            $totalEntregados = count(array_filter($beneficiarios, fn($b) => !empty($b['id_entrega'])));
        ?>
        <div class="gp-card mb-3">
            <div class="d-flex gap-4 flex-wrap">
                <div><span class="text-muted" style="font-size:12px;">Beneficiarios</span><div class="fw-bold" style="font-size:20px;"><?php echo count($beneficiarios); ?></div></div>
                <div><span class="text-muted" style="font-size:12px;">Entregados</span><div class="fw-bold text-success" style="font-size:20px;"><?php echo $totalEntregados; ?></div></div>
                <div><span class="text-muted" style="font-size:12px;">Pendientes</span><div class="fw-bold text-warning" style="font-size:20px;"><?php echo count($beneficiarios) - $totalEntregados; ?></div></div>
            </div>
        </div>

        <div class="gp-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <input type="text" class="form-control form-control-sm" style="max-width:320px;" id="searchEntregas" placeholder="Buscar por nombre, código o boleta...">
                <button class="btn btn-sm btn-outline-secondary" id="btnExportarActa">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar Acta (CSV)
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tablaEntregas" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>N° Boleta</th><th>Apellidos y Nombres</th><th>Nivel</th><th>Grado</th><th>Sección</th><th>Monto</th><th>Estado</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($beneficiarios as $b): ?>
                            <tr class="entrega-row">
                                <td class="text-muted">
                                    <?php echo htmlspecialchars($b['numero_boleta'] ?? '—'); ?>
                                    <?php if ($b['num_boletas'] > 1): ?><span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">×<?php echo $b['num_boletas']; ?></span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($b['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($b['nivel'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($b['grado'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($b['seccion'] ?? ''); ?></td>
                                <td>S/ <?php echo number_format((float) $b['neto'], 2); ?></td>
                                <td>
                                    <?php if (!empty($b['id_entrega'])): ?>
                                        <span class="gp-badge-success"><i class="bi bi-check-circle-fill"></i> Entregado</span>
                                    <?php else: ?>
                                        <span class="gp-badge-warning"><i class="bi bi-clock-fill"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (!empty($b['id_entrega'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary btn-ver" data-id="<?php echo $b['id_entrega']; ?>">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-anular"
                                            data-id="<?php echo $b['id_entrega']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($b['nombre_completo']); ?>">
                                            <i class="bi bi-x-circle"></i> Anular
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm gp-btn-primary border-0 btn-entregar"
                                            data-id-alumno="<?php echo $b['id_alumno']; ?>"
                                            data-id-pago="<?php echo $b['id_pago']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($b['nombre_completo']); ?>">
                                            <i class="bi bi-box-seam"></i> Entregar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($beneficiarios)): ?>
                            <tr><td colspan="8" class="text-muted text-center py-4">No hay alumnos que cumplan la condición de esta promoción todavía.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Switch estilo iOS para "Entregado en el colegio" — grande a propósito
       (56x32, con área de toque completa en el <label>) para que sea fácil de
       presionar con el pulgar desde el celular, no solo un checkbox de 16px. */
    .toggle-en-colegio {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px;
        background-color: var(--gp-primary-light);
        border: 1px solid rgba(15, 118, 110, 0.15);
        border-radius: 10px;
        cursor: pointer;
        min-height: 44px;
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 56px;
        height: 32px;
        flex-shrink: 0;
    }
    .toggle-switch input {
        opacity: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        position: absolute;
        z-index: 1;
        cursor: pointer;
    }
    .toggle-slider {
        position: absolute;
        inset: 0;
        background-color: #d1d5db;
        border-radius: 999px;
        transition: background-color 0.2s ease;
    }
    .toggle-slider::before {
        content: '';
        position: absolute;
        width: 26px;
        height: 26px;
        left: 3px;
        top: 3px;
        background-color: #ffffff;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        transition: transform 0.2s ease;
    }
    .toggle-switch input:checked ~ .toggle-slider {
        background-color: #22c55e;
    }
    .toggle-switch input:checked ~ .toggle-slider::before {
        transform: translateX(24px);
    }
    .toggle-switch input:focus-visible ~ .toggle-slider {
        outline: 2px solid var(--gp-primary);
        outline-offset: 2px;
    }
    .toggle-texto {
        display: flex;
        flex-direction: column;
    }
</style>

<!-- Modal Entregar -->
<div class="modal fade" id="entregarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Entregar Módulo</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEntregar">
                <input type="hidden" id="entregar_id_alumno">
                <input type="hidden" id="entregar_id_pago">
                <input type="hidden" id="entregar_origen_datos" value="2">
                <div class="modal-body p-4">
                    <p class="text-muted mb-3" style="font-size:13px;">Alumno: <strong id="entregar_alumno_nombre"></strong></p>

                    <label class="toggle-en-colegio mb-3" for="entregar_en_colegio">
                        <span class="toggle-switch">
                            <input type="checkbox" id="entregar_en_colegio">
                            <span class="toggle-slider"></span>
                        </span>
                        <span class="toggle-texto">
                            <span class="fw-semibold" style="font-size:13px;">Entregado en el colegio directamente al estudiante</span>
                            <span class="text-muted d-block" style="font-size:11px;">No pide DNI — se registra al propio alumno como receptor.</span>
                        </span>
                    </label>

                    <div id="entregar_campos_dni">
                        <label class="form-label fw-semibold" style="font-size:13px;">DNI de quien recoge</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="entregar_dni" maxlength="8" placeholder="8 dígitos">
                            <button type="button" class="btn btn-outline-secondary" id="btnBuscarDni">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>

                        <label class="form-label fw-semibold" style="font-size:13px;">Apellidos y Nombres</label>
                        <input type="text" class="form-control mb-2" id="entregar_nombre_receptor" placeholder="Se completa al buscar el DNI (o digítalo a mano)">

                        <label class="form-label fw-semibold" style="font-size:13px;">Parentesco (opcional)</label>
                        <select class="form-select" id="entregar_parentesco">
                            <option value="">— No especificado —</option>
                            <option value="Padre">Padre</option>
                            <option value="Madre">Madre</option>
                            <option value="Apoderado">Apoderado</option>
                            <option value="El propio alumno">El propio alumno</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0" id="btnConfirmarEntrega">Confirmar Entrega</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver -->
<div class="modal fade" id="verEntregaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Detalle de la Entrega</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="verEntregaBody">
                <div class="text-center py-3"><span class="spinner-border"></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Anular -->
<div class="modal fade" id="anularEntregaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header bg-danger text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Anular Entrega</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAnularEntrega">
                <input type="hidden" id="anular_id_entrega">
                <div class="modal-body p-4">
                    <p class="text-muted mb-3" style="font-size:13px;">Alumno: <strong id="anular_alumno_nombre"></strong></p>
                    <div class="alert alert-warning py-2" style="font-size:12px;">
                        El alumno volverá a aparecer como "Pendiente" y podrá recibir el módulo de nuevo.
                    </div>
                    <label class="form-label fw-semibold" style="font-size:13px;">Motivo de la anulación</label>
                    <textarea class="form-control" id="anular_motivo" rows="3" placeholder="Ej. Se entregó por error a otro alumno" required></textarea>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmarAnular">Anular Entrega</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const CSRF_ENTREGA = '<?php echo $_SESSION['csrf_entrega']; ?>';
    const BENEFICIARIOS = <?php echo json_encode($beneficiarios, JSON_UNESCAPED_UNICODE); ?>;
    const NOMBRE_PROMOCION = <?php echo json_encode($promocion['nombre'] ?? '', JSON_UNESCAPED_UNICODE); ?>;

    // Exportar acta de entrega a CSV (mismo patrón que Tienda NISSI: BOM + Blob,
    // sin librerías — ver views/V_kardex.php de ese proyecto).
    const btnExportar = document.getElementById('btnExportarActa');
    if (btnExportar) {
        btnExportar.addEventListener('click', () => {
            let csv = '\uFEFF';
            csv += `Acta de Entrega - ${NOMBRE_PROMOCION}\n`;
            csv += 'N Boleta,Codigo,Apellidos y Nombres,Nivel,Grado,Seccion,Monto,Estado,DNI Receptor,Nombre Receptor,Fecha Entrega\n';
            BENEFICIARIOS.forEach(b => {
                const fila = [
                    b.numero_boleta ?? '', b.codigo, b.nombre_completo, b.nivel ?? '', b.grado ?? '', b.seccion ?? '',
                    b.neto, b.id_entrega ? 'Entregado' : 'Pendiente', b.dni_receptor ?? '', b.nombre_receptor ?? '', b.fecha_entrega ?? ''
                ].map(v => `"${String(v).replace(/"/g, '""')}"`);
                csv += fila.join(',') + '\n';
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            const url = URL.createObjectURL(blob);
            a.href = url;
            a.download = `acta_entrega_${NOMBRE_PROMOCION.replace(/\s+/g, '_')}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        });
    }

    // Filtro de búsqueda client-side
    const search = document.getElementById('searchEntregas');
    if (search) {
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            document.querySelectorAll('.entrega-row').forEach(row => {
                row.style.display = !q || row.innerText.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ---------- Modal Entregar ----------
    const entregarModalEl = document.getElementById('entregarModal');
    const entregarModal = entregarModalEl ? new bootstrap.Modal(entregarModalEl) : null;

    const chkEnColegio = document.getElementById('entregar_en_colegio');
    const camposDni = document.getElementById('entregar_campos_dni');
    const inputDni = document.getElementById('entregar_dni');
    const inputNombreReceptor = document.getElementById('entregar_nombre_receptor');
    const selectParentesco = document.getElementById('entregar_parentesco');

    function alternarEntregaEnColegio() {
        const enColegio = chkEnColegio.checked;
        camposDni.classList.toggle('d-none', enColegio);
        inputDni.required = !enColegio;
        inputNombreReceptor.required = !enColegio;
        if (enColegio) {
            inputNombreReceptor.value = document.getElementById('entregar_alumno_nombre').innerText;
            inputNombreReceptor.readOnly = true;
            selectParentesco.value = 'El propio alumno';
            selectParentesco.disabled = true;
            document.getElementById('entregar_origen_datos').value = 3;
        } else {
            inputNombreReceptor.readOnly = false;
            selectParentesco.disabled = false;
            document.getElementById('entregar_origen_datos').value = 2;
        }
    }
    chkEnColegio.addEventListener('change', alternarEntregaEnColegio);

    document.querySelectorAll('.btn-entregar').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('formEntregar').reset();
            document.getElementById('entregar_id_alumno').value = btn.dataset.idAlumno;
            document.getElementById('entregar_id_pago').value = btn.dataset.idPago;
            document.getElementById('entregar_origen_datos').value = 2;
            document.getElementById('entregar_alumno_nombre').innerText = btn.dataset.nombre;
            chkEnColegio.checked = false;
            alternarEntregaEnColegio();
            entregarModal.show();
        });
    });

    const btnBuscarDni = document.getElementById('btnBuscarDni');
    if (btnBuscarDni) {
        btnBuscarDni.addEventListener('click', async () => {
            const dni = document.getElementById('entregar_dni').value.trim();
            if (dni.length !== 8) {
                Swal.fire({ icon: 'warning', title: 'DNI inválido', text: 'Debe tener 8 dígitos.', confirmButtonColor: '#0f766e' });
                return;
            }
            btnBuscarDni.disabled = true;
            const orig = btnBuscarDni.innerHTML;
            btnBuscarDni.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const res = await fetch('./controllers/C_Entrega.php?action=buscar_dni', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ dni })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('entregar_nombre_receptor').value = data.nombre_completo;
                    document.getElementById('entregar_origen_datos').value = 1;
                    Swal.fire({ icon: 'success', title: '¡Encontrado!', showConfirmButton: false, timer: 1000 });
                } else {
                    document.getElementById('entregar_origen_datos').value = 2;
                    Swal.fire({ icon: 'warning', title: 'No encontrado', text: data.mensaje + ' Puedes digitar el nombre a mano.', confirmButtonColor: '#0f766e' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar a RENIEC. Puedes digitar el nombre a mano.', confirmButtonColor: '#0f766e' });
            } finally {
                btnBuscarDni.disabled = false;
                btnBuscarDni.innerHTML = orig;
            }
        });
    }

    const formEntregar = document.getElementById('formEntregar');
    if (formEntregar) {
        formEntregar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                id_promocion: <?php echo $promocion ? (int) $promocion['id_promocion'] : 0; ?>,
                id_alumno: document.getElementById('entregar_id_alumno').value,
                id_pago: document.getElementById('entregar_id_pago').value,
                dni_receptor: document.getElementById('entregar_dni').value.trim(),
                nombre_receptor: document.getElementById('entregar_nombre_receptor').value.trim(),
                parentesco: document.getElementById('entregar_parentesco').value,
                origen_datos: document.getElementById('entregar_origen_datos').value,
                csrf_entrega: CSRF_ENTREGA,
            };
            const btn = document.getElementById('btnConfirmarEntrega');
            btn.disabled = true;
            try {
                const res = await fetch('./controllers/C_Entrega.php?action=registrar', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.ok) {
                    entregarModal.hide();
                    Swal.fire({ icon: 'success', title: '¡Entregado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'No se pudo entregar', text: data.mensaje, confirmButtonColor: '#0f766e' });
                    btn.disabled = false;
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
                btn.disabled = false;
            }
        });
    }

    // ---------- Modal Ver ----------
    const verModalEl = document.getElementById('verEntregaModal');
    const verModal = verModalEl ? new bootstrap.Modal(verModalEl) : null;
    const verBody = document.getElementById('verEntregaBody');

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.innerText = s ?? '';
        return d.innerHTML;
    }

    document.querySelectorAll('.btn-ver').forEach(btn => {
        btn.addEventListener('click', async () => {
            verBody.innerHTML = '<div class="text-center py-3"><span class="spinner-border"></span></div>';
            verModal.show();
            const res = await fetch('./controllers/C_Entrega.php?action=obtener&id_entrega=' + btn.dataset.id);
            const data = await res.json();
            if (!data.success) {
                verBody.innerHTML = `<p class="text-danger">${escapeHtml(data.mensaje)}</p>`;
                return;
            }
            const en = data.entrega;
            const enColegio = String(en.origen_datos) === '3';
            verBody.innerHTML = `
                <div class="mb-2"><span class="text-muted" style="font-size:12px;">Alumno</span><div class="fw-semibold">${escapeHtml(en.alumno_nombre)} (${escapeHtml(en.codigo)})</div></div>
                <div class="mb-2"><span class="text-muted" style="font-size:12px;">Promoción</span><div>${escapeHtml(en.nombre_promocion)}</div></div>
                <hr>
                ${enColegio ? '<div class="mb-2"><span class="gp-badge-success"><i class="bi bi-building"></i> Entregado en el colegio al propio alumno</span></div>' : ''}
                <div class="mb-2"><span class="text-muted" style="font-size:12px;">Recogido por</span><div class="fw-semibold">${escapeHtml(en.nombre_receptor)}</div></div>
                <div class="mb-2"><span class="text-muted" style="font-size:12px;">DNI</span><div>${escapeHtml(en.dni_receptor) || '—'}</div></div>
                <div class="mb-2"><span class="text-muted" style="font-size:12px;">Parentesco</span><div>${escapeHtml(en.parentesco) || '—'}</div></div>
                <div class="mb-2"><span class="text-muted" style="font-size:12px;">Fecha y hora</span><div>${escapeHtml(en.fecha_entrega)}</div></div>
                <div class="mb-0"><span class="text-muted" style="font-size:12px;">Atendido por</span><div>${escapeHtml(en.username) || '—'}</div></div>
            `;
        });
    });

    // ---------- Modal Anular ----------
    const anularModalEl = document.getElementById('anularEntregaModal');
    const anularModal = anularModalEl ? new bootstrap.Modal(anularModalEl) : null;

    document.querySelectorAll('.btn-anular').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('formAnularEntrega').reset();
            document.getElementById('anular_id_entrega').value = btn.dataset.id;
            document.getElementById('anular_alumno_nombre').innerText = btn.dataset.nombre;
            anularModal.show();
        });
    });

    const formAnular = document.getElementById('formAnularEntrega');
    if (formAnular) {
        formAnular.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                id_entrega: document.getElementById('anular_id_entrega').value,
                motivo: document.getElementById('anular_motivo').value.trim(),
                csrf_entrega: CSRF_ENTREGA,
            };
            const btn = document.getElementById('btnConfirmarAnular');
            btn.disabled = true;
            try {
                const res = await fetch('./controllers/C_Entrega.php?action=anular', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    anularModal.hide();
                    Swal.fire({ icon: 'success', title: 'Entrega anulada', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'No se pudo anular', text: data.mensaje, confirmButtonColor: '#0f766e' });
                    btn.disabled = false;
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
                btn.disabled = false;
            }
        });
    }
});
</script>
