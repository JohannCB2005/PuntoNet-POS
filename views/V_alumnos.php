<?php
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
require_once dirname(__DIR__) . '/models/M_Alumno.php';
require_once dirname(__DIR__) . '/models/M_Nivel.php';

$modelAlumno = M_Alumno::singleton();
$modelNivel = M_Nivel::singleton();
$alumnos = $modelAlumno->listar();
$niveles = $modelNivel->listarNiveles();
$grados = $modelNivel->listarGrados();
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Alumnos</h4>
            <p class="text-muted mb-0">Padrón importado de cubicol. Total: <span id="totalAlumnos"><?php echo count($alumnos); ?></span></p>
        </div>
        <button class="gp-btn-primary border-0" data-bs-toggle="modal" data-bs-target="#nuevoAlumnoModal">
            <i class="bi bi-plus-lg me-1"></i>Agregar Manualmente
        </button>
    </div>

    <div class="gp-card">
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" id="searchAlumnos" placeholder="Buscar por código o nombre...">
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" id="filtroNivel">
                    <option value="">Todos los niveles</option>
                    <?php foreach ($niveles as $n): ?>
                        <option value="<?php echo htmlspecialchars($n['nombre']); ?>"><?php echo htmlspecialchars($n['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" id="filtroMatricula">
                    <option value="">Matriculado y no matriculado</option>
                    <option value="1">Solo Matriculados</option>
                    <option value="0">Solo No matriculados</option>
                </select>
            </div>
        </div>
        <div class="mb-2">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="filtroRevisar">
                <label class="form-check-label" style="font-size:12px;" for="filtroRevisar">
                    Solo pensiones a revisar (montos que varían mes a mes)
                </label>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tableAlumnos" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre Completo</th>
                        <th>Nivel</th>
                        <th>Grado</th>
                        <th>Sec.</th>
                        <th>Pensión pactada</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alumnos as $a): ?>
                        <?php $ambigua = (int) $a['pension_origen'] === 3; ?>
                        <tr class="alumno-row" data-nivel="<?php echo htmlspecialchars($a['nivel_nombre'] ?? ''); ?>" data-matriculado="<?php echo (int) $a['matriculado']; ?>" data-revisar="<?php echo $ambigua ? '1' : '0'; ?>">
                            <td class="text-muted"><?php echo htmlspecialchars($a['codigo']); ?></td>
                            <td><?php echo htmlspecialchars($a['nombre_completo']); ?></td>
                            <td><?php echo htmlspecialchars($a['nivel_nombre'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($a['grado_nombre'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($a['seccion'] ?? ''); ?></td>
                            <td>
                                <?php if ($a['pension_pactada'] !== null): ?>
                                    S/ <?php echo number_format((float) $a['pension_pactada'], 2); ?>
                                    <?php if ($ambigua): ?>
                                        <span class="gp-badge-warning ms-1" title="El monto varió mes a mes: se tomó el más reciente, pero conviene confirmarlo a mano."><i class="bi bi-exclamation-triangle"></i> Revisar</span>
                                    <?php elseif ((int) $a['pension_origen'] === 2): ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">Manual</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?php echo $a['matriculado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                    <?php echo $a['matriculado'] == 1 ? 'Matriculado' : 'No matriculado'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-link text-muted p-1 edit-alumno-btn"
                                    data-id="<?php echo $a['id_alumno']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($a['nombre_completo']); ?>"
                                    data-nivel="<?php echo (int) $a['id_nivel']; ?>"
                                    data-grado="<?php echo (int) $a['id_grado']; ?>"
                                    data-seccion="<?php echo htmlspecialchars($a['seccion'] ?? ''); ?>"
                                    data-matriculado="<?php echo (int) $a['matriculado']; ?>"
                                    data-pension="<?php echo $a['pension_pactada'] ?? ''; ?>"
                                    title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Alumno -->
<div class="modal fade" id="nuevoAlumnoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Agregar Alumno Manualmente</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoAlumno">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Código</label>
                        <input type="text" class="form-control" id="new_codigo" placeholder="Ej. A20260099" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Nombre Completo</label>
                        <input type="text" class="form-control" id="new_nombre" placeholder="APELLIDOS, Nombres" required autocomplete="off">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Nivel</label>
                            <select class="form-select" id="new_nivel" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($niveles as $n): ?>
                                    <option value="<?php echo $n['id_nivel']; ?>"><?php echo htmlspecialchars($n['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Grado</label>
                            <select class="form-select" id="new_grado" required>
                                <option value="">Seleccionar nivel primero</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Sección</label>
                            <input type="text" class="form-control" id="new_seccion" maxlength="5" placeholder="A">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Matrícula</label>
                            <select class="form-select" id="new_matriculado">
                                <option value="1" selected>Matriculado</option>
                                <option value="0">No matriculado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Alumno -->
<div class="modal fade" id="editarAlumnoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Editar Alumno</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarAlumno">
                <input type="hidden" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Nombre Completo</label>
                        <input type="text" class="form-control" id="edit_nombre" required autocomplete="off">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Nivel</label>
                            <select class="form-select" id="edit_nivel" required>
                                <?php foreach ($niveles as $n): ?>
                                    <option value="<?php echo $n['id_nivel']; ?>"><?php echo htmlspecialchars($n['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Grado</label>
                            <select class="form-select" id="edit_grado" required></select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Sección</label>
                            <input type="text" class="form-control" id="edit_seccion" maxlength="5">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Matrícula</label>
                            <select class="form-select" id="edit_matriculado">
                                <option value="1">Matriculado</option>
                                <option value="0">No matriculado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold" style="font-size:13px;">Pensión pactada (opcional)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_pension" placeholder="Vacío = dejar que el sistema la detecte sola">
                        <small class="text-muted" style="font-size:11px;">Fijar un monto aquí evita que la próxima importación de comprobantes lo recalcule.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const GRADOS = <?php echo json_encode($grados, JSON_UNESCAPED_UNICODE); ?>;

    function pintarGrados(select, idNivel, idGradoSeleccionado) {
        const opciones = GRADOS.filter(g => g.id_nivel == idNivel);
        select.innerHTML = opciones.map(g => `<option value="${g.id_grado}" ${g.id_grado == idGradoSeleccionado ? 'selected' : ''}>${g.nombre}</option>`).join('');
    }

    // 1. Filtrado dinámico de la tabla + contador "Total" en vivo
    const search = document.getElementById('searchAlumnos');
    const filtroNivel = document.getElementById('filtroNivel');
    const filtroMatricula = document.getElementById('filtroMatricula');
    const filtroRevisar = document.getElementById('filtroRevisar');
    const totalAlumnos = document.getElementById('totalAlumnos');
    function aplicarFiltros() {
        const q = search.value.trim().toLowerCase();
        const nivel = filtroNivel.value;
        const matricula = filtroMatricula.value;
        const soloRevisar = filtroRevisar.checked;
        let visibles = 0;
        document.querySelectorAll('.alumno-row').forEach(row => {
            const texto = row.innerText.toLowerCase();
            const coincideTexto = !q || texto.includes(q);
            const coincideNivel = !nivel || row.dataset.nivel === nivel;
            const coincideMatricula = matricula === '' || row.dataset.matriculado === matricula;
            const coincideRevisar = !soloRevisar || row.dataset.revisar === '1';
            const visible = coincideTexto && coincideNivel && coincideMatricula && coincideRevisar;
            row.style.display = visible ? '' : 'none';
            if (visible) visibles++;
        });
        totalAlumnos.innerText = visibles;
    }
    search.addEventListener('input', aplicarFiltros);
    filtroNivel.addEventListener('change', aplicarFiltros);
    filtroMatricula.addEventListener('change', aplicarFiltros);
    filtroRevisar.addEventListener('change', aplicarFiltros);

    // 2. Nivel -> Grado dependiente (modal Nuevo)
    document.getElementById('new_nivel').addEventListener('change', function () {
        pintarGrados(document.getElementById('new_grado'), this.value, null);
    });

    // 3. Submit Nuevo
    document.getElementById('formNuevoAlumno').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            codigo: document.getElementById('new_codigo').value.trim(),
            nombre_completo: document.getElementById('new_nombre').value.trim(),
            id_nivel: document.getElementById('new_nivel').value,
            id_grado: document.getElementById('new_grado').value,
            seccion: document.getElementById('new_seccion').value.trim(),
            matriculado: document.getElementById('new_matriculado').value,
        };
        try {
            const res = await fetch('./controllers/C_Alumno.php?action=crear', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                Swal.fire({ icon: 'success', title: '¡Creado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        }
    });

    // 4. Abrir modal Editar
    const editModalEl = document.getElementById('editarAlumnoModal');
    const editModal = new bootstrap.Modal(editModalEl);
    document.querySelectorAll('.edit-alumno-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_id').value = btn.dataset.id;
            document.getElementById('edit_nombre').value = btn.dataset.nombre;
            document.getElementById('edit_nivel').value = btn.dataset.nivel;
            pintarGrados(document.getElementById('edit_grado'), btn.dataset.nivel, btn.dataset.grado);
            document.getElementById('edit_seccion').value = btn.dataset.seccion;
            document.getElementById('edit_matriculado').value = btn.dataset.matriculado;
            document.getElementById('edit_pension').value = btn.dataset.pension;
            editModal.show();
        });
    });
    document.getElementById('edit_nivel').addEventListener('change', function () {
        pintarGrados(document.getElementById('edit_grado'), this.value, null);
    });

    // 5. Submit Editar
    document.getElementById('formEditarAlumno').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            id_alumno: document.getElementById('edit_id').value,
            nombre_completo: document.getElementById('edit_nombre').value.trim(),
            id_nivel: document.getElementById('edit_nivel').value,
            id_grado: document.getElementById('edit_grado').value,
            seccion: document.getElementById('edit_seccion').value.trim(),
            matriculado: document.getElementById('edit_matriculado').value,
        };
        try {
            const res = await fetch('./controllers/C_Alumno.php?action=actualizar', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
                return;
            }
            // Segunda llamada, igual que el cambio de contraseña en Usuarios: la
            // pensión pactada es un dato aparte del alta/edición básica del alumno.
            const resPension = await fetch('./controllers/C_Alumno.php?action=fijar_pension', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_alumno: payload.id_alumno, pension_pactada: document.getElementById('edit_pension').value })
            });
            const dataPension = await resPension.json();
            if (!dataPension.success) {
                Swal.fire({ icon: 'warning', title: 'Alumno actualizado, pero...', text: dataPension.mensaje, confirmButtonColor: '#0f766e' });
                return;
            }
            editModal.hide();
            Swal.fire({ icon: 'success', title: '¡Actualizado!', showConfirmButton: false, timer: 1500 })
                .then(() => window.location.reload());
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        }
    });
});
</script>
