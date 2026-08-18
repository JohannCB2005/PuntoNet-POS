<?php
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
require_once dirname(__DIR__) . '/models/M_Pago.php';
require_once dirname(__DIR__) . '/models/M_Alumno.php';
require_once dirname(__DIR__) . '/config/conexion.php';

$todosAlumnos = M_Alumno::singleton()->listar();
$mesesNombre = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Setiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$anioActual = (int) date('Y');
$aniosDisponibles = M_Pago::singleton()->aniosDisponibles();

// Filtros: año (Todos por defecto → todos los años) y mes (Todos por defecto).
$anioFiltro = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int) $_GET['anio'] : null;
$mesFiltro  = isset($_GET['mes']) && $_GET['mes'] !== '' ? (int) $_GET['mes'] : null;
$busqueda   = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

// Paginación: 25 comprobantes por página, del más reciente al más antiguo.
$porPagina = 25;
$totalPagos = M_Pago::singleton()->contarFiltrado($mesFiltro, $anioFiltro, $busqueda);
$totalPaginas = max(1, (int) ceil($totalPagos / $porPagina));
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
if ($pagina > $totalPaginas) $pagina = $totalPaginas;
$offset = ($pagina - 1) * $porPagina;

$pagos = M_Pago::singleton()->listarFiltrado($mesFiltro, $anioFiltro, $busqueda, $porPagina, $offset);

// Base para los enlaces de paginación (conserva los filtros activos).
$paramsPagina = ['modulo' => 'pagos'];
if ($anioFiltro !== null) $paramsPagina['anio'] = $anioFiltro;
if ($mesFiltro !== null) $paramsPagina['mes'] = $mesFiltro;
if ($busqueda !== '') $paramsPagina['q'] = $busqueda;
function urlPagina(array $params, int $p): string {
    $params['pagina'] = $p;
    return '?' . http_build_query($params);
}
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Pagos y Pensiones</h4>
            <p class="text-muted mb-0">
                Todos los comprobantes de pago de pensión. Filtra por año, mes y busca por boleta, nombre o alumno.
                <?php if ($anioFiltro !== null && $anioFiltro !== $anioActual): ?>
                    El histórico de <?php echo $anioFiltro; ?> se conserva en la base de datos.
                <?php endif; ?>
            </p>
        </div>
        <button class="gp-btn-primary border-0" data-bs-toggle="modal" data-bs-target="#nuevoPagoModal">
            <i class="bi bi-plus-lg me-1"></i>Registrar Pago Manual
        </button>
    </div>

    <div class="gp-card">
        <form class="row g-2 mb-3" method="get">
            <input type="hidden" name="modulo" value="pagos">
            <div class="col-auto">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:11px;">Año</label>
                <select class="form-select form-select-sm" name="anio" onchange="this.form.submit()">
                    <option value="" <?php echo $anioFiltro === null ? 'selected' : ''; ?>>Todos</option>
                    <?php foreach ($aniosDisponibles as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo $anioFiltro === (int) $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:11px;">Mes</label>
                <select class="form-select form-select-sm" name="mes" onchange="this.form.submit()">
                    <option value="" <?php echo $mesFiltro === null ? 'selected' : ''; ?>>Todos</option>
                    <?php foreach ($mesesNombre as $num => $nombre): ?>
                        <option value="<?php echo $num; ?>" <?php echo $mesFiltro === $num ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:11px;">Buscar</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Boleta, nombre del comprobante o alumno...">
                    <button class="btn btn-outline-secondary" type="submit">Buscar</button>
                    <?php if ($busqueda !== ''): ?>
                        <a class="btn btn-outline-secondary" href="?modulo=pagos">Limpiar</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 text-muted d-flex align-items-center" style="font-size:13px;">
                <?php
                $partes = [];
                if ($anioFiltro !== null) $partes[] = $anioFiltro;
                if ($mesFiltro !== null) $partes[] = $mesesNombre[$mesFiltro] ?? '';
                if ($busqueda !== '') $partes[] = 'buscando "' . $busqueda . '"';
                ?>
                <span>
                    <i class="bi bi-funnel me-1"></i>
                    <?php echo $partes ? 'Filtro: ' . implode(' · ', array_filter($partes)) : 'Todos los años y meses'; ?>
                    — <?php echo $totalPagos; ?> pagos en total
                    <?php if ($totalPagos > 0): ?>
                        (mostrando <?php echo $offset + 1; ?>–<?php echo min($offset + $porPagina, $totalPagos); ?>)
                    <?php endif; ?>
                </span>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>N° Boleta</th><th>Nombre (comprobante)</th><th>Alumno cruzado</th>
                        <th>Fecha Pago</th><th>Monto</th><th>Total</th><th>Medio</th><th>Cruce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $p): ?>
                        <tr>
                            <td class="text-muted"><?php echo htmlspecialchars($p['numero']); ?></td>
                            <td><?php echo htmlspecialchars($p['nombre_comprobante']); ?></td>
                            <td>
                                <?php if ($p['id_alumno']): ?>
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i><?php echo htmlspecialchars($p['alumno_nombre']); ?></span>
                                    <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($p['codigo']); ?></div>
                                <?php else: ?>
                                    <span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sin cruzar</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['fecha_pago'] ?? '—'); ?></td>
                            <td>S/ <?php echo number_format((float) $p['monto'], 2); ?></td>
                            <td>S/ <?php echo number_format((float) $p['total'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['observacion'] ?? '—'); ?></td>
                            <td>
                                <?php
                                $etiquetas = [0 => 'Sin cruzar', 1 => 'Automático', 2 => 'Manual', 3 => 'Alta manual'];
                                $clase = $p['metodo_cruce'] == 0 ? 'gp-badge-danger' : 'gp-badge-success';
                                ?>
                                <span class="<?php echo $clase; ?>"><?php echo $etiquetas[$p['metodo_cruce']] ?? '—'; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pagos)): ?>
                        <tr><td colspan="8" class="text-muted text-center py-4">
                            Sin comprobantes<?php
                                $filtrosMsg = [];
                                if ($anioFiltro !== null) $filtrosMsg[] = $anioFiltro;
                                if ($mesFiltro !== null) $filtrosMsg[] = $mesesNombre[$mesFiltro] ?? '';
                                if ($busqueda !== '') $filtrosMsg[] = '«' . $busqueda . '»';
                                echo $filtrosMsg ? ' para ' . implode(' · ', $filtrosMsg) : ' registrados';
                            ?>.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-1 pt-2 pb-1" style="font-size:13px;">
                <span class="text-muted">
                    Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?>
                    (<?php echo $totalPagos; ?> comprobantes)
                </span>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo urlPagina($paramsPagina, $pagina - 1); ?>">&laquo;</a>
                    </li>
                    <?php
                    $rango = 2;
                    $inicio = max(1, $pagina - $rango);
                    $fin = min($totalPaginas, $pagina + $rango);
                    if ($inicio > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?php echo urlPagina($paramsPagina, 1); ?>">1</a></li>
                        <?php if ($inicio > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($p = $inicio; $p <= $fin; $p++): ?>
                        <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo urlPagina($paramsPagina, $p); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($fin < $totalPaginas): ?>
                        <?php if ($fin < $totalPaginas - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?php echo urlPagina($paramsPagina, $totalPaginas); ?>"><?php echo $totalPaginas; ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo urlPagina($paramsPagina, $pagina + 1); ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo Pago Manual -->
<div class="modal fade" id="nuevoPagoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius:15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Registrar Pago Manual</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoPago">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Alumno</label>
                        <select class="form-select" id="new_pago_alumno" required>
                            <option value="">Buscar y seleccionar...</option>
                            <?php foreach ($todosAlumnos as $a): ?>
                                <option value="<?php echo $a['id_alumno']; ?>"><?php echo htmlspecialchars($a['codigo'] . ' — ' . $a['nombre_completo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">N° de Boleta</label>
                        <input type="text" class="form-control" id="new_pago_numero" placeholder="Ej. 0008999" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Mes de pensión</label>
                            <select class="form-select" id="new_pago_mes" required>
                                <?php foreach ($mesesNombre as $num => $nombre): ?>
                                    <option value="<?php echo $num; ?>" <?php echo $num == (int) date('n') ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:13px;">Año</label>
                            <input type="number" class="form-control" id="new_pago_anio" value="<?php echo $anioActual; ?>" min="2000" max="2100" required>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold" style="font-size:13px;">Fecha de pago</label>
                        <input type="date" class="form-control" id="new_pago_fecha" value="<?php echo date('Y-m-d'); ?>" required>
                        <small class="text-muted" style="font-size:11px;">Importa si la promoción tiene fecha límite de pago.</small>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold" style="font-size:13px;">Monto (S/)</label>
                        <input type="number" step="0.01" class="form-control" id="new_pago_total" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('formNuevoPago').addEventListener('submit', async (e) => {
        e.preventDefault();
        const select = document.getElementById('new_pago_alumno');
        const nombreAlumno = select.options[select.selectedIndex].text.split('—').slice(1).join('—').trim();
        const payload = {
            id_alumno: select.value,
            numero: document.getElementById('new_pago_numero').value.trim(),
            nombre_comprobante: nombreAlumno,
            mes_concepto: document.getElementById('new_pago_mes').value,
            anio_concepto: document.getElementById('new_pago_anio').value,
            fecha_pago: document.getElementById('new_pago_fecha').value,
            total: document.getElementById('new_pago_total').value,
        };
        try {
            const res = await fetch('./controllers/C_Pago.php?action=crear', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                Swal.fire({ icon: 'success', title: '¡Registrado!', text: data.mensaje, showConfirmButton: false, timer: 1500 }).then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        }
    });
});
</script>
