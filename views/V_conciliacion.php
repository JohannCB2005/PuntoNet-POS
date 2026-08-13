<?php
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
require_once dirname(__DIR__) . '/models/M_Pago.php';
$anioActual = (int) date('Y');
$verTodo = isset($_GET['todo']) && $_GET['todo'] === '1';
$pendientes = M_Pago::singleton()->pendientesConciliacion($verTodo ? 0 : $anioActual);
$totalHistorico = $verTodo ? count($pendientes) : count(M_Pago::singleton()->pendientesConciliacion(0));
?>
<div class="container-fluid px-0">
    <div class="mb-4">
        <h4 class="fw-bold mb-1">Conciliación de Pagos</h4>
        <p class="text-muted mb-0">
            Pagos que no cruzaron automáticamente con ningún alumno del padrón (nombre no coincide o hay más de un candidato).
            Total <?php echo $verTodo ? '(todo el histórico)' : "($anioActual)"; ?>: <?php echo count($pendientes); ?>
            <?php if (!$verTodo && $totalHistorico > count($pendientes)): ?>
                — <a href="conciliacion?todo=1">ver también <?php echo $totalHistorico - count($pendientes); ?> de años anteriores</a>
            <?php elseif ($verTodo): ?>
                — <a href="conciliacion">volver a solo <?php echo $anioActual; ?></a>
            <?php endif; ?>
        </p>
    </div>

    <div class="gp-card">
        <?php if (empty($pendientes)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle" style="font-size:32px;"></i>
                <p class="mb-0 mt-2">No hay pagos pendientes de conciliar.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>N° Boleta</th><th>Nombre (comprobante)</th><th>Concepto</th><th>Total</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): ?>
                            <tr>
                                <td class="text-muted"><?php echo htmlspecialchars($p['numero']); ?></td>
                                <td><?php echo htmlspecialchars($p['nombre_comprobante']); ?></td>
                                <td><?php echo htmlspecialchars($p['concepto']); ?></td>
                                <td>S/ <?php echo number_format((float) $p['total'], 2); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm gp-btn-primary border-0 btn-conciliar"
                                        data-id="<?php echo $p['id_pago']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($p['nombre_comprobante']); ?>"
                                        data-normalizado="<?php echo htmlspecialchars($p['nombre_normalizado']); ?>">
                                        <i class="bi bi-link-45deg me-1"></i>Conciliar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-descartar" data-id="<?php echo $p['id_pago']; ?>">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Conciliar -->
<div class="modal fade" id="conciliarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Asignar Pago a un Alumno</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-2" style="font-size:13px;">Nombre en el comprobante: <strong id="conciliarNombreComprobante"></strong></p>
                <input type="hidden" id="conciliarIdPago">
                <label class="form-label fw-semibold" style="font-size:13px;">Buscar alumno</label>
                <input type="text" class="form-control mb-2" id="conciliarBuscar" placeholder="Escribe apellidos...">
                <div id="conciliarCandidatos" style="max-height:280px; overflow-y:auto;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('conciliarModal');
    const modal = new bootstrap.Modal(modalEl);
    const inputBuscar = document.getElementById('conciliarBuscar');
    const divCandidatos = document.getElementById('conciliarCandidatos');
    const inputIdPago = document.getElementById('conciliarIdPago');
    let timeoutBuscar = null;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.innerText = s;
        return d.innerHTML;
    }

    async function buscarCandidatos(termino) {
        if (!termino || termino.trim().length < 2) {
            divCandidatos.innerHTML = '<p class="text-muted small">Escribe al menos 2 letras.</p>';
            return;
        }
        const res = await fetch('./controllers/C_Pago.php?action=candidatos&nombre_normalizado=' + encodeURIComponent(termino.toUpperCase()));
        const data = await res.json();
        if (!data.length) {
            divCandidatos.innerHTML = '<p class="text-muted small">Sin coincidencias.</p>';
            return;
        }
        divCandidatos.innerHTML = data.map(a => `
            <div class="d-flex justify-content-between align-items-center border-bottom py-2 candidato-item" data-id="${a.id_alumno}">
                <div>
                    <div class="fw-semibold">${escapeHtml(a.nombre_completo)}</div>
                    <small class="text-muted">${escapeHtml(a.codigo)}</small>
                </div>
                <button class="btn btn-sm btn-success border-0 btn-elegir-candidato" data-id="${a.id_alumno}">Elegir</button>
            </div>
        `).join('');
    }

    document.querySelectorAll('.btn-conciliar').forEach(btn => {
        btn.addEventListener('click', () => {
            inputIdPago.value = btn.dataset.id;
            document.getElementById('conciliarNombreComprobante').innerText = btn.dataset.nombre;
            inputBuscar.value = btn.dataset.normalizado.split(' ').slice(0, 2).join(' ');
            divCandidatos.innerHTML = '';
            buscarCandidatos(inputBuscar.value);
            modal.show();
        });
    });

    inputBuscar.addEventListener('input', () => {
        clearTimeout(timeoutBuscar);
        timeoutBuscar = setTimeout(() => buscarCandidatos(inputBuscar.value), 300);
    });

    divCandidatos.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-elegir-candidato');
        if (!btn) return;
        const idAlumno = btn.dataset.id;
        try {
            const res = await fetch('./controllers/C_Pago.php?action=conciliar', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_pago: inputIdPago.value, id_alumno: idAlumno })
            });
            const data = await res.json();
            if (data.success) {
                modal.hide();
                Swal.fire({ icon: 'success', title: '¡Conciliado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        }
    });

    document.querySelectorAll('.btn-descartar').forEach(btn => {
        btn.addEventListener('click', async () => {
            const confirm = await Swal.fire({
                icon: 'warning', title: '¿Descartar este pago?',
                text: 'No aparecerá más en la bandeja de conciliación (queda sin alumno asignado).',
                showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, descartar', cancelButtonText: 'Cancelar'
            });
            if (!confirm.isConfirmed) return;
            try {
                const res = await fetch('./controllers/C_Pago.php?action=descartar', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_pago: btn.dataset.id, motivo: 'Descartado desde bandeja de conciliación' })
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Descartado', showConfirmButton: false, timer: 1200 })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
            }
        });
    });
});
</script>
