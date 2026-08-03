<?php
// Restricción de seguridad: El acceso a reportes ejecutivos está limitado al rol de Administrador
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
?>

<!-- CDNs para exportación y gráficos -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Estilos para tabs */
    .nav-tabs .nav-link {
        color: #4b5563;
        font-weight: 600;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 10px 20px;
        transition: all 0.2s ease;
    }
    .nav-tabs .nav-link.active {
        color: #0284c7;
        border-bottom: 3px solid #0284c7;
        background: transparent;
    }
    .nav-tabs .nav-link:hover:not(.active) {
        border-bottom: 3px solid #d1d5db;
        color: #1f2937;
    }
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
            <h4 class="mb-1 fw-bold text-dark">Reportes y Análisis</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Métricas, ventas e inventario del negocio.</p>
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
            <button class="btn btn-primary btn-sm px-3 fw-semibold" id="btnFiltrar" style="height: 31px; background-color: #0284c7; border: none;">
                <i class="bi bi-funnel"></i> Aplicar
            </button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm px-3 fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown" style="height: 31px;">
                    <i class="bi bi-download"></i> Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><button class="dropdown-item" id="btnExportPDF"><i class="bi bi-file-pdf text-danger me-2"></i> Reporte PDF</button></li>
                    <li><button class="dropdown-item" id="btnExportExcel"><i class="bi bi-file-excel text-success me-2"></i> Reporte Excel</button></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Navegación de Tabs -->
    <ul class="nav nav-tabs mb-4 border-bottom-0" id="reportesTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Resumen</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ventas-tab" data-bs-toggle="tab" data-bs-target="#ventas" type="button" role="tab">Ventas</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="inventario-tab" data-bs-toggle="tab" data-bs-target="#inventario" type="button" role="tab">Inventario</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="clientes-tab" data-bs-toggle="tab" data-bs-target="#clientes" type="button" role="tab">Clientes</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="beneficio-tab" data-bs-toggle="tab" data-bs-target="#beneficio" type="button" role="tab">Costo-Beneficio</button>
        </li>
    </ul>

    <!-- Contenido de los Tabs -->
    <div class="tab-content" id="reportesTabContent">
        
        <!-- TAB 1: RESUMEN -->
        <div class="tab-pane fade show active" id="resumen" role="tabpanel">
            <!-- KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="gp-card">
                        <span class="text-muted fw-semibold d-block mb-1" style="font-size: 12px;">INGRESOS TOTALES</span>
                        <h3 class="mb-0 fw-bold text-dark" id="kpi-total">S/ 0.00</h3>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="gp-card">
                        <span class="text-muted fw-semibold d-block mb-1" style="font-size: 12px;">NÚMERO DE VENTAS</span>
                        <h3 class="mb-0 fw-bold text-dark" id="kpi-ventas">0</h3>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="gp-card">
                        <span class="text-muted fw-semibold d-block mb-1" style="font-size: 12px;">TICKET PROMEDIO</span>
                        <h3 class="mb-0 fw-bold text-dark" id="kpi-promedio">S/ 0.00</h3>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="gp-card">
                        <span class="text-muted fw-semibold d-block mb-1" style="font-size: 12px;">CLIENTES ÚNICOS</span>
                        <h3 class="mb-0 fw-bold text-dark" id="kpi-clientes">0</h3>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="gp-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="mb-0 fw-bold text-dark">Evolución de Ingresos</h6>
                            <select class="form-select form-select-sm w-auto" id="agrupacionGrafico">
                                <option value="dia" selected>Por Día</option>
                                <option value="semana">Por Semana</option>
                                <option value="mes">Por Mes</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="chartTendencia"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="gp-card h-100">
                        <h6 class="mb-4 fw-bold text-dark">Top Productos Vendidos</h6>
                        <div id="topProductosContainer">
                            <!-- Llenado por JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: VENTAS -->
        <div class="tab-pane fade" id="ventas" role="tabpanel">
            <div class="gp-card">
                <h6 class="mb-4 fw-bold text-dark">Registro de Ventas</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaVentas">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>N° Venta</th>
                                <th>Comprobante</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Llenado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: INVENTARIO -->
        <div class="tab-pane fade" id="inventario" role="tabpanel">
            <div class="gp-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="mb-0 fw-bold text-dark">Estado del Inventario</h6>
                    <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">
                        Valor Total: <span id="valorTotalInventario" class="fw-bold fs-6">S/ 0.00</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaInventario">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>P. Unitario</th>
                                <th>Valor Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Llenado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: CLIENTES -->
        <div class="tab-pane fade" id="clientes" role="tabpanel">
            <div class="gp-card">
                <h6 class="mb-4 fw-bold text-dark">Ranking de Clientes</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaClientes">
                        <thead class="table-light">
                            <tr>
                                <th>N° Doc</th>
                                <th>Cliente</th>
                                <th>Compras Realizadas</th>
                                <th>Total Gastado</th>
                                <th>Última Compra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Llenado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 5: BENEFICIO -->
        <div class="tab-pane fade" id="beneficio" role="tabpanel">
            <div class="gp-card">
                <h6 class="mb-4 fw-bold text-dark">Análisis Costo-Beneficio por Producto</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaBeneficio">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Cantidad Vendida</th>
                                <th class="text-end">Ingresos</th>
                                <th class="text-end">Costo Total</th>
                                <th class="text-end">Beneficio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Llenado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // Variables globales para gráficos y datos
    let chartTendencia = null;
    let dataResumen = null;
    let dataVentas = null;
    let dataInventario = null;
    let dataClientes = null;
    let dataBeneficio = null;

    // Elementos DOM
    const btnFiltrar = document.getElementById('btnFiltrar');
    const inputDesde = document.getElementById('filtroDesde');
    const inputHasta = document.getElementById('filtroHasta');
    const selectAgrupacion = document.getElementById('agrupacionGrafico');

    // Inicializar Tablas y Gráficos
    cargarTodosLosDatos();

    // Eventos
    btnFiltrar.addEventListener('click', cargarTodosLosDatos);
    selectAgrupacion.addEventListener('change', cargarResumen);

    async function cargarTodosLosDatos() {
        const desde = inputDesde.value;
        const hasta = inputHasta.value;

        if (!desde || !hasta) {
            Swal.fire({ icon: 'warning', text: 'Debe seleccionar un rango de fechas' });
            return;
        }

        btnFiltrar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cargando...';
        btnFiltrar.disabled = true;

        await Promise.all([
            cargarResumen(),
            cargarVentas(),
            cargarInventario(),
            cargarClientes(),
            cargarBeneficio()
        ]);

        btnFiltrar.innerHTML = '<i class="bi bi-funnel"></i> Aplicar';
        btnFiltrar.disabled = false;
    }

    async function cargarResumen() {
        const desde = inputDesde.value;
        const hasta = inputHasta.value;
        const agrupacion = selectAgrupacion.value;

        try {
            const res = await fetch(`./controllers/C_Reporte.php?action=dashboard_data&desde=${desde}&hasta=${hasta}&agrupacion=${agrupacion}`);
            const data = await res.json();
            
            if (data.success) {
                dataResumen = data.data;
                actualizarResumen(dataResumen);
            }
        } catch (e) {
            console.error("Error al cargar resumen", e);
        }
    }

    function actualizarResumen(data) {
        // KPIs
        document.getElementById('kpi-total').innerText = 'S/ ' + parseFloat(data.kpis.total_ventas).toFixed(2);
        document.getElementById('kpi-ventas').innerText = data.kpis.num_ventas;
        document.getElementById('kpi-promedio').innerText = 'S/ ' + parseFloat(data.kpis.ticket_promedio).toFixed(2);
        document.getElementById('kpi-clientes').innerText = data.kpis.clientes_unicos;

        // Gráfico Tendencia
        if (chartTendencia) { chartTendencia.destroy(); }
        const ctx = document.getElementById('chartTendencia').getContext('2d');
        const labels = data.tendencia.map(t => t.periodo);
        const values = data.tendencia.map(t => parseFloat(t.ingresos));

        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(21, 128, 61, 0.25)');
        gradient.addColorStop(1, 'rgba(21, 128, 61, 0.01)');

        chartTendencia = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ingresos S/',
                    data: values,
                    borderColor: '#0284c7',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // Top Productos
        const topCont = document.getElementById('topProductosContainer');
        topCont.innerHTML = '';
        if (data.top_productos.length === 0) {
            topCont.innerHTML = '<p class="text-muted text-center py-4">Sin datos en el período.</p>';
        } else {
            let maxIngreso = Math.max(...data.top_productos.map(i => parseFloat(i.ingresos)));
            data.top_productos.forEach(item => {
                let pct = (parseFloat(item.ingresos) / maxIngreso) * 100;
                topCont.innerHTML += `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                            <span class="fw-semibold">${item.nombre}</span>
                            <span class="fw-bold">S/ ${parseFloat(item.ingresos).toFixed(2)}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: ${pct}%"></div>
                        </div>
                        <small class="text-muted" style="font-size:11px;">Vendidos: ${item.piezas_vendidas} ${item.unidad}</small>
                    </div>
                `;
            });
        }
    }

    async function cargarVentas() {
        const desde = inputDesde.value;
        const hasta = inputHasta.value;
        try {
            const res = await fetch(`./controllers/C_Reporte.php?action=ventas_list&desde=${desde}&hasta=${hasta}`);
            const json = await res.json();
            if (json.success) {
                dataVentas = json.data;
                const tbody = document.querySelector('#tablaVentas tbody');
                tbody.innerHTML = '';
                if (dataVentas.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No hay ventas en este período</td></tr>';
                } else {
                    dataVentas.forEach(v => {
                        let c = v.tipo_comprobante == 1 ? 'Boleta' : (v.tipo_comprobante == 2 ? 'Factura' : 'Nota');
                        let cli = v.cliente_apellidos ? `${v.cliente_apellidos}, ${v.cliente_nombres}` : v.cliente_nombres;
                        tbody.innerHTML += `
                            <tr>
                                <td>${v.fecha}</td>
                                <td>#${v.id_venta}</td>
                                <td>${c}</td>
                                <td>${cli} <br><small class="text-muted">${v.cliente_doc}</small></td>
                                <td>${v.vendedor}</td>
                                <td class="text-end fw-bold">S/ ${parseFloat(v.total).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                }
            }
        } catch (e) { console.error(e); }
    }

    async function cargarInventario() {
        try {
            // El inventario es actual, no depende de fechas, pero enviamos igual
            const res = await fetch(`./controllers/C_Reporte.php?action=inventario_list`);
            const json = await res.json();
            if (json.success) {
                dataInventario = json.data;
                const tbody = document.querySelector('#tablaInventario tbody');
                tbody.innerHTML = '';
                let sumaValor = 0;
                
                dataInventario.forEach(i => {
                    let v = parseFloat(i.valor_stock);
                    sumaValor += v;
                    let badge = '';
                    if (i.estado_stock === 'Agotado') badge = '<span class="badge bg-danger">Agotado</span>';
                    else if (i.estado_stock === 'Bajo') badge = '<span class="badge bg-warning text-dark">Bajo Stock</span>';
                    else badge = '<span class="badge bg-success">Normal</span>';

                    tbody.innerHTML += `
                        <tr>
                            <td class="fw-semibold">${i.nombre}</td>
                            <td>${i.categoria}</td>
                            <td>${parseFloat(i.stock_piezas).toFixed(2)} ${i.unidad}</td>
                            <td>S/ ${parseFloat(i.precio_unitario).toFixed(2)}</td>
                            <td class="fw-bold">S/ ${v.toFixed(2)}</td>
                            <td>${badge}</td>
                        </tr>
                    `;
                });
                document.getElementById('valorTotalInventario').innerText = 'S/ ' + sumaValor.toFixed(2);
            }
        } catch (e) { console.error(e); }
    }

    async function cargarClientes() {
        const desde = inputDesde.value;
        const hasta = inputHasta.value;
        try {
            const res = await fetch(`./controllers/C_Reporte.php?action=clientes_list&desde=${desde}&hasta=${hasta}`);
            const json = await res.json();
            if (json.success) {
                dataClientes = json.data;
                const tbody = document.querySelector('#tablaClientes tbody');
                tbody.innerHTML = '';
                if (dataClientes.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No hay clientes en este período</td></tr>';
                } else {
                    dataClientes.forEach(c => {
                        let cli = c.apellidos ? `${c.apellidos}, ${c.nombres_razon_social}` : c.nombres_razon_social;
                        tbody.innerHTML += `
                            <tr>
                                <td>${c.numero_documento}</td>
                                <td class="fw-semibold">${cli}</td>
                                <td>${c.compras}</td>
                                <td class="fw-bold text-success">S/ ${parseFloat(c.total_gastado).toFixed(2)}</td>
                                <td>${c.ultima_compra}</td>
                            </tr>
                        `;
                    });
                }
            }
        } catch (e) { console.error(e); }
    }

    async function cargarBeneficio() {
        const desde = inputDesde.value;
        const hasta = inputHasta.value;
        try {
            const res = await fetch(`./controllers/C_Reporte.php?action=beneficio_list&desde=${desde}&hasta=${hasta}`);
            const json = await res.json();
            if (json.success) {
                dataBeneficio = json.data;
                const tbody = document.querySelector('#tablaBeneficio tbody');
                tbody.innerHTML = '';
                if (dataBeneficio.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No hay datos en este período</td></tr>';
                } else {
                    dataBeneficio.forEach(b => {
                        let isPositive = parseFloat(b.beneficio) >= 0;
                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-semibold">${b.producto}</td>
                                <td>${b.categoria}</td>
                                <td>${parseFloat(b.cantidad_vendida).toFixed(2)}</td>
                                <td class="text-end">S/ ${parseFloat(b.ingresos).toFixed(2)}</td>
                                <td class="text-end">S/ ${parseFloat(b.costo_total).toFixed(2)}</td>
                                <td class="text-end fw-bold ${isPositive ? 'text-success' : 'text-danger'}">S/ ${parseFloat(b.beneficio).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                }
            }
        } catch (e) { console.error(e); }
    }

    // ==========================================
    // EXPORTACIÓN A PDF (jsPDF + AutoTable)
    // ==========================================
    document.getElementById('btnExportPDF').addEventListener('click', () => {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        
        const tabActivo = document.querySelector('#reportesTab .nav-link.active').id;
        const title = "Reporte de " + document.querySelector('#reportesTab .nav-link.active').innerText;
        const desde = inputDesde.value;
        const hasta = inputHasta.value;

        // Cabecera Documento
        doc.setFontSize(22);
        doc.setFont("helvetica", "bold");
        doc.text("PuntoNet", 40, 50);
        
        doc.setFontSize(14);
        doc.text(title, 40, 80);
        
        doc.setFontSize(10);
        doc.setFont("helvetica", "normal");
        doc.text(`Período: ${desde} al ${hasta}`, 40, 100);
        doc.text(`Generado: ${new Date().toLocaleString()}`, 40, 115);

        let heads = [];
        let body = [];

        if (tabActivo === 'resumen-tab') {
            heads = [['KPI', 'Valor']];
            body = [
                ['Ingresos Totales', document.getElementById('kpi-total').innerText],
                ['Número de Ventas', document.getElementById('kpi-ventas').innerText],
                ['Ticket Promedio', document.getElementById('kpi-promedio').innerText],
                ['Clientes Únicos', document.getElementById('kpi-clientes').innerText]
            ];
        } 
        else if (tabActivo === 'ventas-tab') {
            heads = [['Fecha', 'N°', 'Tipo', 'Cliente', 'Doc', 'Vendedor', 'Total']];
            body = dataVentas.map(v => [
                v.fecha.substring(0,10), 
                v.id_venta, 
                v.tipo_comprobante == 1 ? 'Boleta' : 'Factura', 
                v.cliente_apellidos ? `${v.cliente_apellidos}, ${v.cliente_nombres}` : v.cliente_nombres,
                v.cliente_doc,
                v.vendedor,
                `S/ ${parseFloat(v.total).toFixed(2)}`
            ]);
        }
        else if (tabActivo === 'inventario-tab') {
            heads = [['Producto', 'Categoría', 'Stock', 'P.Unitario', 'Total', 'Estado']];
            body = dataInventario.map(i => [
                i.nombre, i.categoria, `${parseFloat(i.stock_piezas).toFixed(2)} ${i.unidad}`,
                `S/ ${parseFloat(i.precio_unitario).toFixed(2)}`,
                `S/ ${parseFloat(i.valor_stock).toFixed(2)}`,
                i.estado_stock
            ]);
        }
        else if (tabActivo === 'clientes-tab') {
            heads = [['Doc', 'Cliente', 'Compras', 'Gastado', 'Últ. Compra']];
            body = dataClientes.map(c => [
                c.numero_documento,
                c.apellidos ? `${c.apellidos}, ${c.nombres_razon_social}` : c.nombres_razon_social,
                c.compras,
                `S/ ${parseFloat(c.total_gastado).toFixed(2)}`,
                c.ultima_compra.substring(0,10)
            ]);
        }
        else if (tabActivo === 'beneficio-tab') {
            heads = [['Producto', 'Categoría', 'Cantidad Vendida', 'Ingresos', 'Costo Total', 'Beneficio']];
            body = dataBeneficio.map(b => [
                b.producto, b.categoria, parseFloat(b.cantidad_vendida).toFixed(2),
                `S/ ${parseFloat(b.ingresos).toFixed(2)}`,
                `S/ ${parseFloat(b.costo_total).toFixed(2)}`,
                `S/ ${parseFloat(b.beneficio).toFixed(2)}`
            ]);
        }

        doc.autoTable({
            startY: 140,
            head: heads,
            body: body,
            theme: 'striped',
            headStyles: { fillColor: [21, 128, 61] } // Verde success
        });

        doc.save(`Reporte_${title.replace(' ', '_')}.pdf`);
    });

    // ==========================================
    // EXPORTACIÓN A EXCEL (SheetJS)
    // ==========================================
    document.getElementById('btnExportExcel').addEventListener('click', () => {
        const tabActivo = document.querySelector('#reportesTab .nav-link.active').id;
        const title = document.querySelector('#reportesTab .nav-link.active').innerText;
        
        let ws;
        if (tabActivo === 'resumen-tab') {
            const data = [
                ['KPI', 'Valor'],
                ['Ingresos Totales', document.getElementById('kpi-total').innerText],
                ['Número de Ventas', document.getElementById('kpi-ventas').innerText],
                ['Ticket Promedio', document.getElementById('kpi-promedio').innerText],
                ['Clientes Únicos', document.getElementById('kpi-clientes').innerText]
            ];
            ws = XLSX.utils.aoa_to_sheet(data);
        } 
        else if (tabActivo === 'ventas-tab') {
            const data = dataVentas.map(v => ({
                'Fecha': v.fecha,
                'ID': v.id_venta,
                'Comprobante': v.tipo_comprobante == 1 ? 'Boleta' : 'Factura',
                'Cliente': v.cliente_apellidos ? `${v.cliente_apellidos}, ${v.cliente_nombres}` : v.cliente_nombres,
                'Documento': v.cliente_doc,
                'Vendedor': v.vendedor,
                'Total': parseFloat(v.total)
            }));
            ws = XLSX.utils.json_to_sheet(data);
        }
        else if (tabActivo === 'inventario-tab') {
            const data = dataInventario.map(i => ({
                'Producto': i.nombre,
                'Categoría': i.categoria,
                'Stock': parseFloat(i.stock_piezas),
                'Unidad': i.unidad,
                'Precio Unit.': parseFloat(i.precio_unitario),
                'Valor Total': parseFloat(i.valor_stock),
                'Estado': i.estado_stock
            }));
            ws = XLSX.utils.json_to_sheet(data);
        }
        else if (tabActivo === 'clientes-tab') {
            const data = dataClientes.map(c => ({
                'N° Doc': c.numero_documento,
                'Cliente': c.apellidos ? `${c.apellidos}, ${c.nombres_razon_social}` : c.nombres_razon_social,
                'Compras': parseInt(c.compras),
                'Total Gastado': parseFloat(c.total_gastado),
                'Última Compra': c.ultima_compra
            }));
            ws = XLSX.utils.json_to_sheet(data);
        }
        else if (tabActivo === 'beneficio-tab') {
            const data = dataBeneficio.map(b => ({
                'Producto': b.producto,
                'Categoría': b.categoria,
                'Cantidad Vendida': parseFloat(b.cantidad_vendida),
                'Ingresos': parseFloat(b.ingresos),
                'Costo Total': parseFloat(b.costo_total),
                'Beneficio': parseFloat(b.beneficio)
            }));
            ws = XLSX.utils.json_to_sheet(data);
        }

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, title);
        XLSX.writeFile(wb, `Reporte_${title}.xlsx`);
    });

});
</script>
