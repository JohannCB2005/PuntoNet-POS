<?php
// Gestión de series ante SUNAT y estado del barrido de pendientes. Solo Administrador:
// crear una serie mal (repetida o con el correlativo inicial equivocado) puede hacer que
// SUNAT rechace todo lo que se emita en ella.
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
?>

<style>
    .hover-text-primary:hover { color: #0284c7 !important; }
</style>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Series SUNAT</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">
                Series y correlativos usados para emitir comprobantes electrónicos. El envío pendiente
                se procesa aparte, con el botón de abajo.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-primary btn-sm" id="btnProcesarPendientes">
                <i class="bi bi-cloud-arrow-up-fill"></i> Enviar pendientes a SUNAT
            </button>
            <button class="btn btn-primary btn-sm" id="btnNuevaSerie">
                <i class="bi bi-plus-lg"></i> Nueva serie
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13.5px;">
                    <thead class="table-light">
                        <tr class="text-muted" style="font-size: 12px;">
                            <th>Tipo</th>
                            <th>Serie</th>
                            <th class="text-center">Correlativo actual</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodySeries">
                        <tr><td colspan="5" class="text-center text-muted py-4">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const TIPO_COMPROBANTE_LABEL = { 1: 'Boleta', 2: 'Factura', 4: 'Nota de Crédito (Boleta)', 5: 'Nota de Crédito (Factura)' };

document.addEventListener('DOMContentLoaded', () => {
    cargarSeries();

    document.getElementById('btnNuevaSerie').addEventListener('click', crearSerie);
    document.getElementById('btnProcesarPendientes').addEventListener('click', procesarPendientes);
});

async function cargarSeries() {
    const tbody = document.getElementById('tbodySeries');
    try {
        const resp = await fetch('./controllers/C_Sunat.php?action=listar_series');
        const json = await resp.json();
        const filas = json.success ? json.data : [];

        if (filas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay series configuradas todavía.</td></tr>';
            return;
        }

        tbody.innerHTML = filas.map(f => `
            <tr>
                <td>${TIPO_COMPROBANTE_LABEL[f.tipo_comprobante] || f.tipo_comprobante}</td>
                <td class="fw-semibold">${f.serie}</td>
                <td class="text-center">${f.correlativo_actual}</td>
                <td class="text-center">
                    <span class="badge ${f.estado == 1 ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary'}">
                        ${f.estado == 1 ? 'Activa' : 'Inactiva'}
                    </span>
                </td>
                <td class="text-center">
                    <button class="btn btn-link text-muted p-1 hover-text-primary" title="${f.estado == 1 ? 'Desactivar' : 'Activar'}"
                            onclick="cambiarEstadoSerie(${f.id_serie}, ${f.estado == 1 ? 0 : 1})">
                        <i class="bi ${f.estado == 1 ? 'bi-toggle-on' : 'bi-toggle-off'}"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">No se pudo cargar las series.</td></tr>';
    }
}

async function crearSerie() {
    const { value: form } = await Swal.fire({
        title: 'Nueva serie',
        html: `
            <select id="serieTipo" class="swal2-select">
                <option value="1">Boleta</option>
                <option value="2">Factura</option>
                <option value="4">Nota de Crédito (Boleta)</option>
                <option value="5">Nota de Crédito (Factura)</option>
            </select>
            <input id="serieCodigo" class="swal2-input" placeholder="Serie (ej. B002, F002, BC02, FC02)" maxlength="4">
            <input id="serieCorrelativoInicial" type="number" min="0" class="swal2-input" placeholder="Correlativo inicial (0 si empieza de cero)" value="0">
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonColor: '#0284c7',
        confirmButtonText: 'Crear serie',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const tipo_comprobante = parseInt(document.getElementById('serieTipo').value, 10);
            const serie = document.getElementById('serieCodigo').value.trim().toUpperCase();
            const correlativo_inicial = parseInt(document.getElementById('serieCorrelativoInicial').value, 10) || 0;
            if (!serie) { Swal.showValidationMessage('Indica el código de la serie.'); return false; }
            return { tipo_comprobante, serie, correlativo_inicial };
        }
    });
    if (!form) return;

    try {
        const resp = await fetch('./controllers/C_Sunat.php?action=crear_serie', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form)
        });
        const json = await resp.json();
        if (json.success) {
            Swal.fire({ icon: 'success', title: 'Serie creada', showConfirmButton: false, timer: 1200 });
            cargarSeries();
        } else {
            Swal.fire({ icon: 'error', title: 'No se pudo crear', text: json.mensaje, confirmButtonColor: '#0284c7' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
    }
}

async function cambiarEstadoSerie(id_serie, activa) {
    try {
        const resp = await fetch('./controllers/C_Sunat.php?action=cambiar_estado_serie', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_serie, activa })
        });
        const json = await resp.json();
        if (json.success) {
            cargarSeries();
        } else {
            Swal.fire({ icon: 'error', title: 'No se pudo actualizar', text: json.mensaje || 'Intenta de nuevo.', confirmButtonColor: '#0284c7' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
    }
}

async function procesarPendientes() {
    const btn = document.getElementById('btnProcesarPendientes');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    try {
        const resp = await fetch('./controllers/C_Sunat.php?action=procesar_pendientes', { method: 'POST' });
        const json = await resp.json();
        if (json.success) {
            const d = json.data;
            Swal.fire({
                icon: 'success',
                title: 'Barrido completado',
                text: `${d.emitidos} emitidos, ${d.bajas_consultadas} bajas consultadas, ${d.notas_credito} notas de crédito reintentadas.`,
                confirmButtonColor: '#0284c7'
            });
        } else {
            Swal.fire({ icon: 'error', title: 'No se pudo procesar', text: json.mensaje, confirmButtonColor: '#0284c7' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo contactar al servidor.' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> Enviar pendientes a SUNAT';
    }
}
</script>
