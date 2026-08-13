<?php
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
require_once dirname(__DIR__) . '/models/M_Promocion.php';
$promociones = M_Promocion::singleton()->listar();
$mesesNombre = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Setiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$bimestreNombre = [1=>'1er Bimestre',2=>'2do Bimestre',3=>'3er Bimestre',4=>'4to Bimestre'];
$anioActual = (int) date('Y');
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Promociones</h4>
            <p class="text-muted mb-0">"Módulo del bimestre" a cambio del pago de pensión de un mes específico.</p>
        </div>
        <button class="gp-btn-primary border-0" data-bs-toggle="modal" data-bs-target="#nuevaPromocionModal">
            <i class="bi bi-plus-lg me-1"></i>Nueva Promoción
        </button>
    </div>

    <div class="row g-3">
        <?php foreach ($promociones as $p): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="gp-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($p['nombre']); ?></h6>
                        <span class="gp-badge-success"><?php echo $bimestreNombre[$p['bimestre']] ?? $p['bimestre']; ?></span>
                    </div>
                    <p class="text-muted mb-1" style="font-size:12px;">Requiere pago de pensión: <strong><?php echo $mesesNombre[$p['mes_requerido']] . ' ' . $p['anio_requerido']; ?></strong></p>
                    <p class="text-muted mb-2" style="font-size:12px;">
                        <?php echo $p['exige_matriculado'] ? '<i class="bi bi-check2"></i> Solo matriculados' : '<i class="bi bi-dash"></i> Incluye no matriculados'; ?>
                        &nbsp;·&nbsp;
                        <?php echo $p['monto_minimo'] !== null ? 'Mínimo S/ ' . number_format($p['monto_minimo'], 2) : 'Cualquier pago > 0'; ?>
                    </p>
                    <?php if (!empty($p['fecha_limite_pago'])): ?>
                        <p class="text-muted mb-2" style="font-size:12px;"><i class="bi bi-calendar-x"></i> Pagado hasta el <strong><?php echo date('d/m/Y', strtotime($p['fecha_limite_pago'])); ?></strong></p>
                    <?php endif; ?>
                    <?php if (!empty($p['exige_pension_completa'])): ?>
                        <p class="text-muted mb-2" style="font-size:12px;"><i class="bi bi-percent"></i> Respeta descuentos individuales por alumno</p>
                    <?php endif; ?>
                    <?php if (!empty($p['descripcion'])): ?>
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <span class="text-muted" style="font-size:12px;">Beneficiarios:</span>
                        <span class="fw-bold" style="font-size:18px;" id="contador-<?php echo $p['id_promocion']; ?>">…</span>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-sm btn-outline-secondary flex-fill edit-promo-btn"
                            data-id="<?php echo $p['id_promocion']; ?>"
                            data-nombre="<?php echo htmlspecialchars($p['nombre']); ?>"
                            data-bimestre="<?php echo $p['bimestre']; ?>"
                            data-mes="<?php echo $p['mes_requerido']; ?>"
                            data-anio="<?php echo $p['anio_requerido']; ?>"
                            data-monto="<?php echo $p['monto_minimo'] ?? ''; ?>"
                            data-fecha-limite="<?php echo $p['fecha_limite_pago'] ?? ''; ?>"
                            data-pension-completa="<?php echo $p['exige_pension_completa']; ?>"
                            data-matriculado="<?php echo $p['exige_matriculado']; ?>"
                            data-neto="<?php echo $p['exige_neto_positivo']; ?>"
                            data-descripcion="<?php echo htmlspecialchars($p['descripcion'] ?? ''); ?>">
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                        <button class="btn btn-sm btn-outline-primary flex-fill asignar-productos-btn"
                            data-id="<?php echo $p['id_promocion']; ?>"
                            data-nombre="<?php echo htmlspecialchars($p['nombre']); ?>">
                            <i class="bi bi-box-seam"></i> Productos
                        </button>
                        <a href="entregas?id_promocion=<?php echo $p['id_promocion']; ?>" class="btn btn-sm gp-btn-primary border-0 flex-fill">
                            <i class="bi bi-truck"></i> Ver Entregas
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($promociones)): ?>
            <div class="col-12">
                <div class="gp-card text-center py-5 text-muted">
                    <i class="bi bi-gift" style="font-size:32px;"></i>
                    <p class="mb-0 mt-2">Todavía no hay promociones creadas.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Formulario compartido entre Nueva y Editar (mismos ids con sufijo new_/edit_).
function camposPromocion(string $prefijo, array $meses, array $bimestres, int $anioActual): void {
?>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:13px;">Nombre</label>
        <input type="text" class="form-control" id="<?php echo $prefijo; ?>_nombre" placeholder="Ej. Módulo 3er Bimestre" required autocomplete="off">
    </div>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label fw-semibold" style="font-size:13px;">Bimestre</label>
            <select class="form-select" id="<?php echo $prefijo; ?>_bimestre" required>
                <?php foreach ($bimestres as $num => $nombre): ?>
                    <option value="<?php echo $num; ?>"><?php echo $nombre; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6">
            <label class="form-label fw-semibold" style="font-size:13px;">Año requerido</label>
            <input type="number" class="form-control" id="<?php echo $prefijo; ?>_anio" value="<?php echo $anioActual; ?>" min="2000" max="2100" required>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:13px;">Mes de pensión requerido</label>
        <select class="form-select" id="<?php echo $prefijo; ?>_mes" required>
            <?php foreach ($meses as $num => $nombre): ?>
                <option value="<?php echo $num; ?>"><?php echo $nombre; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:13px;">Monto mínimo (opcional)</label>
        <input type="number" step="0.01" class="form-control" id="<?php echo $prefijo; ?>_monto" placeholder="Vacío = cualquier pago mayor a 0">
        <small class="text-muted" style="font-size:11px;">Se usa como piso solo para alumnos sin pensión pactada detectada todavía.</small>
    </div>
    <div class="form-check mb-3 p-2 bg-light rounded border">
        <input class="form-check-input" type="checkbox" id="<?php echo $prefijo; ?>_pension_completa">
        <label class="form-check-label fw-semibold" style="font-size:13px;" for="<?php echo $prefijo; ?>_pension_completa">
            Respetar descuentos individuales
        </label>
        <div class="text-muted" style="font-size:11px;">
            No todos pagan lo mismo (hay alumnos con descuento exclusivo, becas o convenios). Con esto activo, cada alumno se compara contra <em>su propia</em> pensión habitual — detectada sola con el monto que paga mes a mes — en vez de exigirle 290/300 a todos por igual.
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:13px;">Fecha límite de pago (opcional)</label>
        <input type="date" class="form-control" id="<?php echo $prefijo; ?>_fecha_limite">
        <small class="text-muted" style="font-size:11px;">Vacío = cualquier fecha de pago vale. Si la fijas, solo cuentan los pagos hechos hasta ese día.</small>
    </div>
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="<?php echo $prefijo; ?>_matriculado" checked>
        <label class="form-check-label" style="font-size:13px;" for="<?php echo $prefijo; ?>_matriculado">Solo alumnos matriculados</label>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="<?php echo $prefijo; ?>_neto" checked>
        <label class="form-check-label" style="font-size:13px;" for="<?php echo $prefijo; ?>_neto">Exigir neto de pensión positivo</label>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:13px;">Descripción (opcional)</label>
        <textarea class="form-control" id="<?php echo $prefijo; ?>_descripcion" rows="2"></textarea>
    </div>
    <div class="alert alert-light border d-flex justify-content-between align-items-center py-2 px-3 mb-0" style="font-size:13px;">
        <span>Beneficiarios estimados:</span>
        <strong id="<?php echo $prefijo; ?>_contador">—</strong>
    </div>
<?php
}
?>

<!-- Modal Nueva Promoción -->
<div class="modal fade" id="nuevaPromocionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Nueva Promoción</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevaPromocion">
                <div class="modal-body p-4"><?php camposPromocion('new', $mesesNombre, $bimestreNombre, $anioActual); ?></div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Crear Promoción</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Promoción -->
<div class="modal fade" id="editarPromocionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Editar Promoción</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarPromocion">
                <input type="hidden" id="edit_id">
                <div class="modal-body p-4"><?php camposPromocion('edit', $mesesNombre, $bimestreNombre, $anioActual); ?></div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Asignar Productos (NISSI) -->
<div class="modal fade" id="productosPromoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Productos de la Promoción</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-2" id="productosPromoDesc" style="font-size:13px;"></p>
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopiarAnterior">
                        <i class="bi bi-copy me-1"></i>Copiar del bimestre anterior
                    </button>
                    <button type="button" class="btn gp-btn-primary border-0 btn-sm" id="btnAgregarFila">
                        <i class="bi bi-plus-lg me-1"></i>Agregar línea
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tablaProductosPromo" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Nivel</th><th>Grado</th><th>Producto</th>
                                <th style="width:110px;">Cantidad</th><th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="gp-btn-primary border-0" id="btnGuardarProductos">Guardar asignación</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const PROMOCIONES = <?php echo json_encode($promociones); ?>;

    async function contarBeneficiarios(mes, anio, matriculado, neto, monto, fechaLimite, pensionCompleta) {
        const params = new URLSearchParams({ mes, anio, exige_matriculado: matriculado ? 1 : 0, exige_neto_positivo: neto ? 1 : 0, exige_pension_completa: pensionCompleta ? 1 : 0 });
        if (monto) params.append('monto_minimo', monto);
        if (fechaLimite) params.append('fecha_limite_pago', fechaLimite);
        const res = await fetch('./controllers/C_Promocion.php?action=previsualizar_beneficiarios&' + params.toString());
        const data = await res.json();
        return data.success ? data.beneficiarios : '—';
    }

    // Contadores de las tarjetas ya existentes
    PROMOCIONES.forEach(async (p) => {
        const el = document.getElementById('contador-' + p.id_promocion);
        if (el) el.innerText = await contarBeneficiarios(p.mes_requerido, p.anio_requerido, p.exige_matriculado, p.exige_neto_positivo, p.monto_minimo, p.fecha_limite_pago, p.exige_pension_completa);
    });

    function conectarContadorEnVivo(prefijo) {
        const campos = ['mes', 'anio', 'matriculado', 'neto', 'monto', 'fecha_limite', 'pension_completa'].map(c => document.getElementById(`${prefijo}_${c}`));
        const contador = document.getElementById(`${prefijo}_contador`);
        async function actualizar() {
            contador.innerText = '…';
            const [mes, anio, matriculado, neto, monto, fechaLimite, pensionCompleta] = campos;
            contador.innerText = await contarBeneficiarios(mes.value, anio.value, matriculado.checked, neto.checked, monto.value, fechaLimite.value, pensionCompleta.checked);
        }
        campos.forEach(c => c.addEventListener('change', actualizar));
        document.getElementById(`${prefijo}_monto`).addEventListener('input', () => { clearTimeout(window.__t); window.__t = setTimeout(actualizar, 400); });
        actualizar();
    }

    document.getElementById('nuevaPromocionModal').addEventListener('shown.bs.modal', () => conectarContadorEnVivo('new'), { once: true });

    document.getElementById('formNuevaPromocion').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            nombre: document.getElementById('new_nombre').value.trim(),
            bimestre: document.getElementById('new_bimestre').value,
            mes_requerido: document.getElementById('new_mes').value,
            anio_requerido: document.getElementById('new_anio').value,
            monto_minimo: document.getElementById('new_monto').value,
            fecha_limite_pago: document.getElementById('new_fecha_limite').value,
            exige_pension_completa: document.getElementById('new_pension_completa').checked ? 1 : 0,
            exige_matriculado: document.getElementById('new_matriculado').checked ? 1 : 0,
            exige_neto_positivo: document.getElementById('new_neto').checked ? 1 : 0,
            descripcion: document.getElementById('new_descripcion').value.trim(),
        };
        try {
            const res = await fetch('./controllers/C_Promocion.php?action=crear', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                Swal.fire({ icon: 'success', title: '¡Creada!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        }
    });

    // Editar
    const editModal = new bootstrap.Modal(document.getElementById('editarPromocionModal'));
    document.querySelectorAll('.edit-promo-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_id').value = btn.dataset.id;
            document.getElementById('edit_nombre').value = btn.dataset.nombre;
            document.getElementById('edit_bimestre').value = btn.dataset.bimestre;
            document.getElementById('edit_mes').value = btn.dataset.mes;
            document.getElementById('edit_anio').value = btn.dataset.anio;
            document.getElementById('edit_monto').value = btn.dataset.monto;
            document.getElementById('edit_fecha_limite').value = btn.dataset.fechaLimite;
            document.getElementById('edit_pension_completa').checked = btn.dataset.pensionCompleta === '1';
            document.getElementById('edit_matriculado').checked = btn.dataset.matriculado === '1';
            document.getElementById('edit_neto').checked = btn.dataset.neto === '1';
            document.getElementById('edit_descripcion').value = btn.dataset.descripcion;
            editModal.show();
            conectarContadorEnVivo('edit');
        });
    });

    document.getElementById('formEditarPromocion').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            id_promocion: document.getElementById('edit_id').value,
            nombre: document.getElementById('edit_nombre').value.trim(),
            bimestre: document.getElementById('edit_bimestre').value,
            mes_requerido: document.getElementById('edit_mes').value,
            anio_requerido: document.getElementById('edit_anio').value,
            monto_minimo: document.getElementById('edit_monto').value,
            fecha_limite_pago: document.getElementById('edit_fecha_limite').value,
            exige_pension_completa: document.getElementById('edit_pension_completa').checked ? 1 : 0,
            exige_matriculado: document.getElementById('edit_matriculado').checked ? 1 : 0,
            exige_neto_positivo: document.getElementById('edit_neto').checked ? 1 : 0,
            descripcion: document.getElementById('edit_descripcion').value.trim(),
        };
        try {
            const res = await fetch('./controllers/C_Promocion.php?action=actualizar', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                editModal.hide();
                Swal.fire({ icon: 'success', title: '¡Actualizada!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        }
    });

    // ---------- Modal Productos (NISSI) ----------
    let productosPromoId = null;
    let CATALOGO_PRODUCTOS = [];
    let NIVELES_PROMO = [];
    let GRADOS_PROMO = [];
    const productosModal = new bootstrap.Modal(document.getElementById('productosPromoModal'));
    const tablaProductos = document.getElementById('tablaProductosPromo').querySelector('tbody');

    function fresherSelect(select, opciones) {
        const val = select.value;
        select.innerHTML = opciones.map(o => `<option value="${o[0]}" ${String(val) === String(o[0]) ? 'selected' : ''}>${o[1]}</option>`).join('');
    }

    function agregarFilaProducto(detalle) {
        const tr = document.createElement('tr');
        const d = detalle || {};
        tr.innerHTML = `
            <td><select class="form-select form-select-sm prod-nivel"></select></td>
            <td><select class="form-select form-select-sm prod-grado"><option value="">—</option></select></td>
            <td><select class="form-select form-select-sm prod-producto"></select></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm prod-cantidad" value="${d.cantidad ?? 1}"></td>
            <td><button type="button" class="btn btn-sm btn-link text-danger p-0" title="Quitar"><i class="bi bi-trash"></i></button></td>
        `;
        const selNivel = tr.querySelector('.prod-nivel');
        const selGrado = tr.querySelector('.prod-grado');
        const selPro = tr.querySelector('.prod-producto');
        fresherSelect(selNivel, NIVELES_PROMO.map(n => [n.id_nivel, n.nombre]));
        if (d.id_nivel) selNivel.value = d.id_nivel;
        selGrado.dataset.allGrados = JSON.stringify(GRADOS_PROMO);
        function pintarGradosFila() {
            const gr = GRADOS_PROMO.filter(g => g.id_nivel == selNivel.value);
            fresherSelect(selGrado, gr.map(g => [g.id_grado, g.nombre]));
            if (d.id_grado) selGrado.value = d.id_grado;
        }
        selNivel.addEventListener('change', pintarGradosFila);
        pintarGradosFila();
        fresherSelect(selPro, CATALOGO_PRODUCTOS.map(p => [p.id_producto, (p.categoria ? p.categoria + ' — ' : '') + p.nombre]));
        if (d.id_producto) selPro.value = d.id_producto;
        tr.querySelector('button').addEventListener('click', () => tr.remove());
        tablaProductos.appendChild(tr);
    }

    function leerFilasProducto() {
        return [...tablaProductos.querySelectorAll('tr')].map(tr => ({
            id_nivel: tr.querySelector('.prod-nivel').value,
            id_grado: tr.querySelector('.prod-grado').value,
            id_producto: tr.querySelector('.prod-producto').value,
            cantidad: tr.querySelector('.prod-cantidad').value,
        }));
    }

    document.querySelectorAll('.asignar-productos-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            productosPromoId = btn.dataset.id;
            document.getElementById('productosPromoDesc').innerText = 'Asignando productos para: ' + btn.dataset.nombre;
            tablaProductos.innerHTML = '';
            try {
                const res = await fetch('./controllers/C_Promocion.php?action=productos&id_promocion=' + btn.dataset.id);
                const data = await res.json();
                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
                    return;
                }
                CATALOGO_PRODUCTOS = data.productos;
                NIVELES_PROMO = data.niveles;
                GRADOS_PROMO = data.grados;
                document.getElementById('btnCopiarAnterior').style.display = data.promocion_anterior ? '' : 'none';
                (data.detalle.length ? data.detalle : [{}]).forEach(row => agregarFilaProducto(row));
                productosModal.show();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
            }
        });
    });

    document.getElementById('btnAgregarFila').addEventListener('click', () => agregarFilaProducto({}));

    document.getElementById('btnCopiarAnterior').addEventListener('click', async () => {
        if (productosPromoId === null) return;
        try {
            const res = await fetch('./controllers/C_Promocion.php?action=copiar_productos', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_promocion: productosPromoId })
            });
            const data = await res.json();
            if (data.ok) {
                const res2 = await fetch('./controllers/C_Promocion.php?action=productos&id_promocion=' + productosPromoId);
                const data2 = await res2.json();
                tablaProductos.innerHTML = '';
                data2.detalle.forEach(row => agregarFilaProducto(row));
                Swal.fire({ icon: 'success', title: 'Copiado', text: data.mensaje, showConfirmButton: false, timer: 1500 });
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo copiar', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        }
    });

    document.getElementById('btnGuardarProductos').addEventListener('click', async () => {
        if (productosPromoId === null) return;
        const btn = document.getElementById('btnGuardarProductos');
        btn.disabled = true;
        try {
            const res = await fetch('./controllers/C_Promocion.php?action=guardar_productos', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_promocion: productosPromoId, filas: leerFilasProducto() })
            });
            const data = await res.json();
            if (data.ok) {
                productosModal.hide();
                Swal.fire({ icon: 'success', title: 'Guardado', text: data.mensaje, showConfirmButton: false, timer: 1500 });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
