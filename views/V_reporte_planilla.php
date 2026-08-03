<?php
// Restricción de seguridad: El acceso a reportes ejecutivos está limitado al rol de Administrador
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

require_once dirname(__DIR__) . '/models/M_Facultad.php';
$facultades = M_Facultad::singleton()->listar();
?>

<!-- CDNs para exportación -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    .gp-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
    }
</style>

<div class="container-fluid px-0">
    <!-- Encabezado y Filtros -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Reporte: Cargo a Planilla</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Lista de deudas a descontar del sueldo de trabajadores UNP.</p>
        </div>
        
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label text-muted fw-semibold mb-1" style="font-size: 12px;">Desde</label>
                <input type="date" id="filtroDesde" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label class="form-label text-muted fw-semibold mb-1" style="font-size: 12px;">Hasta</label>
                <input type="date" id="filtroHasta" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label class="form-label text-muted fw-semibold mb-1" style="font-size: 12px;">Facultad/Dependencia</label>
                <select id="filtroFacultad" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach($facultades as $fac): ?>
                        <option value="<?= $fac['id_facultad'] ?>"><?= htmlspecialchars($fac['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-success btn-sm px-3 fw-semibold" id="btnFiltrar" style="height: 31px;">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm px-3 fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown" style="height: 31px;">
                    <i class="bi bi-download"></i> Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><button class="dropdown-item" id="btnExportPDF"><i class="bi bi-file-pdf text-danger me-2"></i> Exportar a PDF</button></li>
                    <li><button class="dropdown-item" id="btnExportExcel"><i class="bi bi-file-excel text-success me-2"></i> Exportar a Excel</button></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Resultados -->
    <div class="gp-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="mb-0 fw-bold text-dark">Detalle de Descuentos</h6>
            <div class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2">
                Total a Descontar: <span id="totalDescuento" class="fw-bold fs-6">S/ 0.00</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaPlanilla">
                <thead class="table-light">
                    <tr>
                        <th>Fecha Venta</th>
                        <th>N° Comprobante</th>
                        <th>DNI</th>
                        <th>Trabajador</th>
                        <th>Código Planilla</th>
                        <th>Tipo y Facultad</th>
                        <th class="text-end">Monto a Descontar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" class="text-center py-5 text-muted">Use los filtros para generar el reporte.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let dataPlanilla = [];

    const btnFiltrar = document.getElementById('btnFiltrar');
    const inputDesde = document.getElementById('filtroDesde');
    const inputHasta = document.getElementById('filtroHasta');
    const selectFacultad = document.getElementById('filtroFacultad');
    const tbody = document.querySelector('#tablaPlanilla tbody');
    const totalDescuentoLabel = document.getElementById('totalDescuento');

    btnFiltrar.addEventListener('click', cargarReporte);

    // Cargar por defecto
    cargarReporte();

    async function cargarReporte() {
        const desde = inputDesde.value;
        const hasta = inputHasta.value;
        const id_facultad = selectFacultad.value;

        if (!desde || !hasta) {
            Swal.fire({ icon: 'warning', text: 'Debe seleccionar un rango de fechas' });
            return;
        }

        btnFiltrar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cargando...';
        btnFiltrar.disabled = true;

        try {
            const url = `./controllers/C_Venta.php?action=reporte_planilla&desde=${desde}&hasta=${hasta}&id_facultad=${id_facultad}`;
            const res = await fetch(url);
            const json = await res.json();
            
            if (json.success) {
                dataPlanilla = json.data;
                tbody.innerHTML = '';
                
                let sumaTotal = 0;
                
                if (dataPlanilla.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron cargos a planilla en el período y facultad seleccionados.</td></tr>';
                } else {
                    dataPlanilla.forEach(r => {
                        let total = parseFloat(r.total);
                        sumaTotal += total;
                        
                        let nomCliente = r.cliente_apellidos ? `${r.cliente_apellidos}, ${r.cliente_nombres}` : r.cliente_nombres;
                        let facultadTxt = r.facultad_nombre || 'No asignada';
                        let tipoTrabTxt = r.tipo_trabajador_nombre || '-';
                        let codPlanilla = r.codigo_planilla || '-';
                        
                        tbody.innerHTML += `
                            <tr>
                                <td>${r.fecha.substring(0,10)}</td>
                                <td>#${r.id_venta}</td>
                                <td>${r.cliente_doc}</td>
                                <td class="fw-semibold">${nomCliente}</td>
                                <td><span class="badge bg-secondary">${codPlanilla}</span></td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span style="font-size: 13px;">${facultadTxt}</span>
                                        <small class="text-muted" style="font-size: 11px;">${tipoTrabTxt}</small>
                                    </div>
                                </td>
                                <td class="text-end fw-bold text-danger">S/ ${total.toFixed(2)}</td>
                            </tr>
                        `;
                    });
                }
                
                totalDescuentoLabel.innerText = 'S/ ' + sumaTotal.toFixed(2);
            } else {
                Swal.fire({ icon: 'error', text: json.mensaje });
            }
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', text: 'Error de red al cargar el reporte.' });
        } finally {
            btnFiltrar.innerHTML = '<i class="bi bi-funnel"></i> Filtrar';
            btnFiltrar.disabled = false;
        }
    }

    // ==========================================
    // EXPORTACIÓN A PDF (jsPDF + AutoTable)
    // ==========================================
    document.getElementById('btnExportPDF').addEventListener('click', () => {
        if (dataPlanilla.length === 0) return Swal.fire({ icon: 'warning', text: 'No hay datos para exportar' });
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        const desde = inputDesde.value;
        const hasta = inputHasta.value;
        const facTxt = selectFacultad.options[selectFacultad.selectedIndex].text;

        doc.setFontSize(22);
        doc.setFont("helvetica", "bold");
        doc.text("PuntoNet", 40, 50);
        
        doc.setFontSize(14);
        doc.text("Reporte de Descuentos por Planilla", 40, 80);
        
        doc.setFontSize(10);
        doc.setFont("helvetica", "normal");
        doc.text(`Período: ${desde} al ${hasta}`, 40, 100);
        doc.text(`Facultad/Dependencia: ${facTxt}`, 40, 115);
        doc.text(`Generado: ${new Date().toLocaleString()}`, 40, 130);

        let heads = [['Fecha', 'Venta', 'DNI', 'Trabajador', 'Código', 'Facultad', 'Monto (S/)']];
        let body = dataPlanilla.map(r => [
            r.fecha.substring(0,10),
            r.id_venta,
            r.cliente_doc,
            r.cliente_apellidos ? `${r.cliente_apellidos}, ${r.cliente_nombres}` : r.cliente_nombres,
            r.codigo_planilla || '-',
            r.facultad_nombre || '-',
            parseFloat(r.total).toFixed(2)
        ]);

        doc.autoTable({
            startY: 150,
            head: heads,
            body: body,
            theme: 'striped',
            headStyles: { fillColor: [220, 53, 69] } // Rojo danger
        });

        doc.save(`Reporte_Planilla_${desde}_al_${hasta}.pdf`);
    });

    // ==========================================
    // EXPORTACIÓN A EXCEL (SheetJS)
    // ==========================================
    document.getElementById('btnExportExcel').addEventListener('click', () => {
        if (dataPlanilla.length === 0) return Swal.fire({ icon: 'warning', text: 'No hay datos para exportar' });
        
        const data = dataPlanilla.map(r => ({
            'Fecha Venta': r.fecha,
            'N° Venta': r.id_venta,
            'DNI/Documento': r.cliente_doc,
            'Nombres Completos': r.cliente_apellidos ? `${r.cliente_apellidos}, ${r.cliente_nombres}` : r.cliente_nombres,
            'Tipo Trabajador': r.tipo_trabajador_nombre || '-',
            'Facultad / Dependencia': r.facultad_nombre || '-',
            'Código Planilla': r.codigo_planilla || '-',
            'Monto a Descontar (S/)': parseFloat(r.total)
        }));

        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Descuentos Planilla");
        XLSX.writeFile(wb, `Reporte_Planilla_${inputDesde.value}_al_${inputHasta.value}.xlsx`);
    });
});
</script>
