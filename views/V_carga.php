<?php
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
require_once dirname(__DIR__) . '/models/M_Importacion.php';
$historial = M_Importacion::singleton()->historial(10);
$mesesNombre = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Setiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$anioActual = (int) date('Y');
?>
<div class="container-fluid px-0">
    <div class="mb-4">
        <h4 class="fw-bold mb-1">Carga de Datos</h4>
        <p class="text-muted mb-0">Sube el padrón de alumnos y los comprobantes de pago tal como los bajas de cubicol (conviértelos a .xlsx con "Guardar como" si vienen en .xls).</p>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="gp-card h-100">
                <h6 class="fw-bold mb-1"><i class="bi bi-people-fill me-1"></i> Padrón de Alumnos</h6>
                <p class="text-muted" style="font-size:12px;">"Reporte alumnos General" — se sube ~1 vez al año o cuando cambie algo. Actualiza por código: si ya existe, solo actualiza lo que cambió.</p>
                <input type="file" class="form-control mb-2" id="archivoPadron" accept=".xlsx,.csv">
                <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                    <select class="form-select form-select-sm d-none" id="hojaPadron" style="max-width:220px;"></select>
                    <button class="btn btn-sm gp-btn-primary border-0" id="btnPreviewPadron" disabled>
                        <i class="bi bi-eye me-1"></i>Previsualizar
                    </button>
                    <button class="btn btn-sm btn-success border-0 d-none" id="btnImportarPadron">
                        <i class="bi bi-cloud-upload me-1"></i>Importar Padrón
                    </button>
                </div>
                <div id="previewPadron"></div>
                <div id="resumenPadron" class="mt-2"></div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="gp-card h-100">
                <h6 class="fw-bold mb-1"><i class="bi bi-receipt me-1"></i> Comprobantes de Pago</h6>
                <p class="text-muted" style="font-size:12px;">"Reporte-ConsultaComprobantes" — súbelo completo, con todo el histórico. El sistema clasifica cada pago por su propio mes/año; filtras por periodo después, en Pagos, Promociones o Entregas.</p>
                <input type="file" class="form-control mb-2" id="archivoPagos" accept=".xlsx,.csv">
                <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                    <select class="form-select form-select-sm d-none" id="hojaPagos" style="max-width:220px;"></select>
                    <button class="btn btn-sm gp-btn-primary border-0" id="btnPreviewPagos" disabled>
                        <i class="bi bi-eye me-1"></i>Previsualizar
                    </button>
                </div>
                <div class="d-none" id="accionesPagos">
                    <button class="btn btn-sm btn-success border-0" id="btnImportarPagos">
                        <i class="bi bi-cloud-upload me-1"></i>Importar Todo el Histórico
                    </button>
                    <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#filtroPeriodoPagos">
                        Restringir a un solo periodo (opcional)
                    </button>
                    <div class="collapse mt-2" id="filtroPeriodoPagos">
                        <div class="d-flex gap-2 align-items-center flex-wrap border rounded p-2">
                            <label class="small text-muted mb-0">Solo pensión de:</label>
                            <select class="form-select form-select-sm" id="mesPagos" style="max-width:140px;">
                                <?php foreach ($mesesNombre as $num => $nombre): ?>
                                    <option value="<?php echo $num; ?>" <?php echo $num == (int) date('n') ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select form-select-sm" id="anioPagos" style="max-width:110px;">
                                <?php for ($y = $anioActual - 1; $y <= $anioActual + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $anioActual ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="previewPagos"></div>
                <div id="resumenPagos" class="mt-2"></div>
            </div>
        </div>
    </div>

    <div class="gp-card">
        <h6 class="fw-bold mb-3">Historial de Importaciones</h6>
        <div class="table-responsive">
            <table class="table table-sm" style="font-size:12px;">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Tipo</th><th>Archivo</th><th>Periodo</th>
                        <th>Leídas</th><th>Nuevos</th><th>Actualiz.</th><th>Sin cambio</th><th>Sin cruce</th><th>Usuario</th>
                    </tr>
                </thead>
                <tbody id="tbodyHistorial">
                    <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($h['fecha_registro']); ?></td>
                            <td><?php echo $h['tipo'] == 1 ? 'Padrón' : 'Comprobantes'; ?></td>
                            <td><?php echo htmlspecialchars($h['nombre_archivo']); ?></td>
                            <td><?php echo $h['mes_filtro'] ? ($mesesNombre[$h['mes_filtro']] . ' ' . $h['anio_filtro']) : '—'; ?></td>
                            <td><?php echo $h['filas_leidas']; ?></td>
                            <td class="text-success fw-semibold"><?php echo $h['nuevos']; ?></td>
                            <td><?php echo $h['actualizados']; ?></td>
                            <td class="text-muted"><?php echo $h['sin_cambios']; ?></td>
                            <td class="<?php echo $h['sin_cruce'] > 0 ? 'text-danger fw-semibold' : 'text-muted'; ?>"><?php echo $h['sin_cruce']; ?></td>
                            <td><?php echo htmlspecialchars($h['username'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($historial)): ?>
                        <tr><td colspan="10" class="text-muted text-center py-3">Sin importaciones todavía.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    function tablaPreview(filas) {
        if (!filas.length) return '<p class="text-muted small mb-0">Sin filas para mostrar.</p>';
        const anchoMax = Math.max(...filas.map(f => f.length));
        let html = '<div class="table-responsive" style="max-height:360px;"><table class="table table-sm table-bordered" style="font-size:11px;">';
        html += '<thead><tr><th>#</th>';
        for (let c = 0; c < anchoMax; c++) html += `<th>col${c}</th>`;
        html += '</tr></thead><tbody>';
        filas.forEach((fila, i) => {
            html += `<tr><td class="text-muted">${i}</td>`;
            for (let c = 0; c < anchoMax; c++) {
                const v = fila[c] ?? '';
                html += `<td>${v === '' ? '<span class="text-muted">·</span>' : escapeHtml(v)}</td>`;
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.innerText = s;
        return d.innerHTML;
    }

    function resumenHtml(r) {
        return `<div class="d-flex flex-wrap gap-2">
            <span class="gp-badge-success">Nuevos: ${r.nuevos}</span>
            <span class="badge bg-secondary-subtle text-secondary-emphasis">Actualizados: ${r.actualizados}</span>
            <span class="badge bg-light text-muted border">Sin cambios: ${r.sin_cambios}</span>
            ${r.omitidos ? `<span class="gp-badge-warning">Omitidos: ${r.omitidos}</span>` : ''}
            ${('sin_cruce' in r) ? `<span class="${r.sin_cruce > 0 ? 'gp-badge-danger' : 'badge bg-light text-muted border'}">Sin cruce: ${r.sin_cruce}</span>` : ''}
        </div>`;
    }

    // ---------- Zona Padrón ----------
    const inputPadron = document.getElementById('archivoPadron');
    const hojaPadron = document.getElementById('hojaPadron');
    const btnPreviewPadron = document.getElementById('btnPreviewPadron');
    const btnImportarPadron = document.getElementById('btnImportarPadron');
    const previewPadron = document.getElementById('previewPadron');
    const resumenPadron = document.getElementById('resumenPadron');

    inputPadron.addEventListener('change', () => {
        btnPreviewPadron.disabled = !inputPadron.files.length;
        btnImportarPadron.classList.add('d-none');
        hojaPadron.classList.add('d-none');
        previewPadron.innerHTML = '';
        resumenPadron.innerHTML = '';
    });

    async function previsualizarPadron(hoja) {
        if (!inputPadron.files.length) return;
        const fd = new FormData();
        fd.append('archivo', inputPadron.files[0]);
        if (hoja) fd.append('hoja', hoja);

        btnPreviewPadron.disabled = true;
        try {
            const res = await fetch('./controllers/C_Importacion.php?action=preflight', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'No se pudo leer el archivo', text: data.mensaje, confirmButtonColor: '#0f766e' });
                return;
            }
            if (data.hojas.length > 1) {
                hojaPadron.innerHTML = data.hojas.map(h => `<option value="${h}" ${h === data.hoja_activa ? 'selected' : ''}>${h}</option>`).join('');
                hojaPadron.classList.remove('d-none');
                hojaPadron.onchange = () => previsualizarPadron(hojaPadron.value);
            } else {
                hojaPadron.classList.add('d-none');
            }
            previewPadron.innerHTML = `<p class="text-muted small mb-2">Hoja: <strong>${data.hoja_activa}</strong> — primeras ${data.filas.length} filas de "${data.nombre_archivo}"</p>` + tablaPreview(data.filas);
            btnImportarPadron.classList.remove('d-none');
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        } finally {
            btnPreviewPadron.disabled = false;
        }
    }
    btnPreviewPadron.addEventListener('click', () => previsualizarPadron(null));

    btnImportarPadron.addEventListener('click', async () => {
        const fd = new FormData();
        fd.append('archivo', inputPadron.files[0]);
        if (!hojaPadron.classList.contains('d-none')) fd.append('hoja', hojaPadron.value);

        btnImportarPadron.disabled = true;
        const orig = btnImportarPadron.innerHTML;
        btnImportarPadron.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importando...';
        try {
            const res = await fetch('./controllers/C_Importacion.php?action=importar_padron', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                resumenPadron.innerHTML = resumenHtml(data.resumen);
                Swal.fire({ icon: 'success', title: 'Padrón importado', text: `${data.resumen.filas_leidas} alumnos procesados.`, confirmButtonColor: '#0f766e' })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error al importar', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        } finally {
            btnImportarPadron.disabled = false;
            btnImportarPadron.innerHTML = orig;
        }
    });

    // ---------- Zona Comprobantes ----------
    const inputPagos = document.getElementById('archivoPagos');
    const hojaPagos = document.getElementById('hojaPagos');
    const btnPreviewPagos = document.getElementById('btnPreviewPagos');
    const accionesPagos = document.getElementById('accionesPagos');
    const btnImportarPagos = document.getElementById('btnImportarPagos');
    const previewPagos = document.getElementById('previewPagos');
    const resumenPagos = document.getElementById('resumenPagos');

    inputPagos.addEventListener('change', () => {
        btnPreviewPagos.disabled = !inputPagos.files.length;
        accionesPagos.classList.add('d-none');
        hojaPagos.classList.add('d-none');
        previewPagos.innerHTML = '';
        resumenPagos.innerHTML = '';
    });

    async function previsualizarPagos(hoja) {
        if (!inputPagos.files.length) return;
        const fd = new FormData();
        fd.append('archivo', inputPagos.files[0]);
        if (hoja) fd.append('hoja', hoja);

        btnPreviewPagos.disabled = true;
        try {
            const res = await fetch('./controllers/C_Importacion.php?action=preflight', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'No se pudo leer el archivo', text: data.mensaje, confirmButtonColor: '#0f766e' });
                return;
            }
            if (data.hojas.length > 1) {
                hojaPagos.innerHTML = data.hojas.map(h => `<option value="${h}" ${h === data.hoja_activa ? 'selected' : ''}>${h}</option>`).join('');
                hojaPagos.classList.remove('d-none');
                hojaPagos.onchange = () => previsualizarPagos(hojaPagos.value);
            } else {
                hojaPagos.classList.add('d-none');
            }
            previewPagos.innerHTML = `<p class="text-muted small mb-2">Hoja: <strong>${data.hoja_activa}</strong> — primeras ${data.filas.length} filas de "${data.nombre_archivo}"</p>` + tablaPreview(data.filas);
            accionesPagos.classList.remove('d-none');
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        } finally {
            btnPreviewPagos.disabled = false;
        }
    }
    btnPreviewPagos.addEventListener('click', () => previsualizarPagos(null));

    btnImportarPagos.addEventListener('click', async () => {
        const fd = new FormData();
        fd.append('archivo', inputPagos.files[0]);
        if (!hojaPagos.classList.contains('d-none')) fd.append('hoja', hojaPagos.value);
        // Solo se manda mes/año si el usuario abrió el filtro opcional; si no, se
        // importa TODO el histórico de pensiones del archivo.
        const filtroAbierto = document.getElementById('filtroPeriodoPagos').classList.contains('show');
        if (filtroAbierto) {
            fd.append('mes', document.getElementById('mesPagos').value);
            fd.append('anio', document.getElementById('anioPagos').value);
        }

        btnImportarPagos.disabled = true;
        const orig = btnImportarPagos.innerHTML;
        btnImportarPagos.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importando...';
        try {
            const res = await fetch('./controllers/C_Importacion.php?action=importar_comprobantes', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                resumenPagos.innerHTML = resumenHtml(data.resumen);
                const msgCruce = data.resumen.sin_cruce > 0 ? ` ${data.resumen.sin_cruce} sin cruzar — revisa el módulo de Conciliación.` : ' Todos los pagos cruzaron con un alumno.';
                Swal.fire({ icon: 'success', title: 'Comprobantes importados', text: `${data.resumen.filas_leidas} pagos procesados.${msgCruce}`, confirmButtonColor: '#0f766e' })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error al importar', text: data.mensaje, confirmButtonColor: '#0f766e' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0f766e' });
        } finally {
            btnImportarPagos.disabled = false;
            btnImportarPagos.innerHTML = orig;
        }
    });
});
</script>
