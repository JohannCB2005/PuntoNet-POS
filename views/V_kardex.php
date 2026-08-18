<?php
// Validar sesión activa en el sistema
if (!isset($_SESSION['id_usuario'])) { 
    echo "<h1>Acceso denegado</h1>"; 
    exit; 
}

// Cargar el modelo de Kardex para obtener los productos del selector
require_once dirname(__DIR__) . '/models/M_Kardex.php';
$modelKardex = M_Kardex::singleton();
$productos = $modelKardex->listarProductos();
?>

<div class="container-fluid px-0">
    <!-- Encabezado de la Sección -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Kardex de Inventario</h4>
            <p class="text-muted mb-0" style="font-size:14px;">Registro cronológico de entradas, salidas y saldos valorizados por producto.</p>
        </div>
        <!-- Botones para exportar información (deshabilitados hasta seleccionar un producto) -->
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" id="btnExportExcel" style="font-size:13px;" disabled>
                <i class="bi bi-file-earmark-excel"></i> Exportar Excel
            </button>
            <button class="gp-btn-primary d-flex align-items-center gap-2 border-0" id="btnExportPDF" disabled>
                <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
            </button>
        </div>
    </div>

    <!-- Selector de Productos -->
    <div class="gp-card mb-3">
        <div class="row align-items-center">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">Producto / Artículo</label>
                <select class="form-select" id="selectProducto" style="font-size:14px; box-shadow:none;">
                    <option value="">— Seleccione un producto —</option>
                    <?php
                    // Agrupar por producto padre (o categoría si es simple)
                    $gruposKardex = [];
                    foreach ($productos as $ins) {
                        $grupo = $ins['nombre_padre'] ?? null;
                        if ($grupo) {
                            $gruposKardex[$grupo][] = $ins;
                        } else {
                            $gruposKardex['__simples__'][] = $ins;
                        }
                    }
                    foreach ($gruposKardex as $nombreGrupo => $items):
                        if ($nombreGrupo === '__simples__'):
                    ?>
                        <optgroup label="— Productos individuales —">
                            <?php foreach ($items as $ins): ?>
                                <option value="<?= $ins['id_producto'] ?>"
                                        data-precio="<?= $ins['precio_unitario'] ?>"
                                        data-stock="<?= $ins['stock_piezas'] ?>"
                                        data-unidad="<?= htmlspecialchars($ins['abreviatura']) ?>"
                                        data-categoria="<?= htmlspecialchars($ins['categoria']) ?>"
                                        data-shorttext="<?= htmlspecialchars($ins['nombre']) ?>"
                                        data-fulltext="<?= htmlspecialchars($ins['nombre']) ?>">
                                    <?= htmlspecialchars($ins['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php else: ?>
                        <optgroup label="<?= htmlspecialchars($nombreGrupo) ?>">
                            <?php foreach ($items as $ins): 
                                $shortText = htmlspecialchars($ins['talla'] ? 'Talla ' . $ins['talla'] : $ins['nombre']);
                                $fullText = htmlspecialchars($nombreGrupo . ' - ' . ($ins['talla'] ? 'Talla ' . $ins['talla'] : $ins['nombre']));
                            ?>
                                <option value="<?= $ins['id_producto'] ?>"
                                        data-precio="<?= $ins['precio_unitario'] ?>"
                                        data-stock="<?= $ins['stock_piezas'] ?>"
                                        data-unidad="<?= htmlspecialchars($ins['abreviatura']) ?>"
                                        data-categoria="<?= htmlspecialchars($ins['categoria']) ?>"
                                        data-shorttext="<?= $shortText ?>"
                                        data-fulltext="<?= $fullText ?>">
                                    <?= $shortText ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Resumen Acumulado (Ocultas hasta que se elija un producto del selector) -->
    <div id="kardexSummary" class="gp-card mb-3 d-none">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-2 border-end">
                <div class="text-muted fw-semibold mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Código</div>
                <div class="fw-bold" id="sumCodigo" style="font-size:15px;">—</div>
            </div>
            <div class="col-6 col-md-2 border-end">
                <div class="text-muted fw-semibold mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Categoría</div>
                <div class="fw-bold" id="sumCategoria" style="font-size:15px;">—</div>
            </div>
            <div class="col-6 col-md-2 border-end">
                <div class="text-muted fw-semibold mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Valuación</div>
                <div class="fw-bold text-muted" id="sumValuacion" style="font-size:15px;">Prom. Ponderado</div>
            </div>
            <div class="col-6 col-md-2 border-end">
                <div class="text-muted fw-semibold mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Total Ingresos</div>
                <div class="fw-bold text-success d-flex align-items-center justify-content-center gap-1" id="sumEntradas" style="font-size:15px;">—</div>
            </div>
            <div class="col-6 col-md-2 border-end">
                <div class="text-muted fw-semibold mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Total Salidas</div>
                <div class="fw-bold text-danger d-flex align-items-center justify-content-center gap-1" id="sumSalidas" style="font-size:15px;">—</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="text-muted fw-semibold mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Stock Actual</div>
                <div class="fw-bold" id="sumStock" style="font-size:15px;">—</div>
                <div class="text-muted" id="sumStockVal" style="font-size:12px;">—</div>
            </div>
        </div>
    </div>

    <!-- Filtros de Búsqueda Avanzada (Ocultos inicialmente) -->
    <div id="kardexFilters" class="gp-card mb-3 d-none">
        <div class="row align-items-center g-2">
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:11px;">Tipo</label>
                <select class="form-select form-select-sm" id="filterTipo" style="box-shadow:none; font-size:13px;">
                    <option value="todos">Todos</option>
                    <option value="entrada">Entradas</option>
                    <option value="salida">Salidas</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:11px;">Desde</label>
                <input type="date" class="form-control form-control-sm" id="filterDesde" style="box-shadow:none; font-size:13px;">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:11px;">Hasta</label>
                <input type="date" class="form-control form-control-sm" id="filterHasta" style="box-shadow:none; font-size:13px;">
            </div>
            <div class="col-12 col-md-4 ms-auto">
                <label class="form-label fw-semibold text-muted mb-1" style="font-size:11px;">Buscar</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="filterBusqueda" placeholder="Buscar por N° de documento o concepto..." style="box-shadow:none; font-size:13px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor Principal de la Tabla del Kardex -->
    <div id="kardexTableWrap" class="gp-card d-none">
        <!-- Spinner de Carga -->
        <div id="kardexLoading" class="text-center py-5 d-none">
            <div class="spinner-border text-success" role="status"></div>
            <p class="text-muted mt-2 mb-0" style="font-size:14px;">Cargando movimientos...</p>
        </div>
        <!-- Alerta de Tabla Vacía -->
        <div id="kardexEmpty" class="text-center py-5 d-none">
            <i class="bi bi-inbox" style="font-size:2.5rem; color:#ccc;"></i>
            <p class="text-muted mt-2 mb-0">No hay movimientos registrados para este producto.</p>
        </div>
        <!-- Tabla Estilo Sunat (Entradas, Salidas y Saldos Ponderados) -->
        <div class="table-responsive" id="kardexTableContainer">
            <table class="table align-middle mb-0" style="font-size:13px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size:11px; text-transform:uppercase; letter-spacing:.4px;">
                        <th class="pb-3" rowspan="2">Fecha</th>
                        <th class="pb-3" rowspan="2">Tipo Doc.</th>
                        <th class="pb-3" rowspan="2">N° Doc.</th>
                        <th class="pb-3" rowspan="2">Concepto</th>
                        <th class="pb-2 text-center text-success border-start border-2" colspan="3">Entradas</th>
                        <th class="pb-2 text-center text-danger border-start border-2" colspan="3">Salidas</th>
                        <th class="pb-2 text-center border-start border-2" colspan="3">Saldos</th>
                    </tr>
                    <tr class="text-muted" style="font-size:11px;">
                        <th class="pb-3 text-end border-start border-2">Cant.</th>
                        <th class="pb-3 text-end">C. Unit.</th>
                        <th class="pb-3 text-end">C. Total</th>
                        <th class="pb-3 text-end border-start border-2">Cant.</th>
                        <th class="pb-3 text-end">C. Unit.</th>
                        <th class="pb-3 text-end">C. Total</th>
                        <th class="pb-3 text-end border-start border-2">Cant.</th>
                        <th class="pb-3 text-end">C. Unit.</th>
                        <th class="pb-3 text-end">C. Total</th>
                    </tr>
                </thead>
                <tbody id="kardexTbody"></tbody>
            </table>
        </div>
    </div>

    <!-- Indicador inicial cuando no hay un producto seleccionado -->
    <div id="kardexPlaceholder" class="gp-card text-center py-5">
        <i class="bi bi-journal-text" style="font-size:3rem; color:#c8e6c9;"></i>
        <h6 class="mt-3 fw-semibold text-muted">Selecciona un producto para ver su Kardex</h6>
        <p class="text-muted mb-0" style="font-size:13px;">Elige un producto del selector superior para visualizar el registro de movimientos.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Referencias a los filtros y el selector de productos
    const selectProducto   = document.getElementById('selectProducto');
    
    // Función para manejar el texto visual del select
    function updateSelectedText() {
        const selected = selectProducto.options[selectProducto.selectedIndex];
        if (selected && selected.dataset.fulltext) {
            // Revertir temporalmente todos al formato corto para el dropdown
            Array.from(selectProducto.options).forEach(opt => {
                if (opt.dataset.shorttext && opt !== selected) {
                    opt.textContent = opt.dataset.shorttext;
                }
            });
            // Cambiar solo el elegido a texto completo
            selected.textContent = selected.dataset.fulltext;
        }
    }
    
    selectProducto.addEventListener('focus', function() {
        Array.from(this.options).forEach(opt => {
            if (opt.dataset.shorttext) {
                opt.textContent = opt.dataset.shorttext;
            }
        });
    });
    selectProducto.addEventListener('blur', updateSelectedText);
    selectProducto.addEventListener('change', updateSelectedText);

    const filterTipo     = document.getElementById('filterTipo');
    const filterDesde    = document.getElementById('filterDesde');
    const filterHasta    = document.getElementById('filterHasta');
    const filterBusqueda = document.getElementById('filterBusqueda');

    // Referencias a contenedores visuales de UI
    const kardexSummary      = document.getElementById('kardexSummary');
    const kardexFilters      = document.getElementById('kardexFilters');
    const kardexTableWrap    = document.getElementById('kardexTableWrap');
    const kardexPlaceholder  = document.getElementById('kardexPlaceholder');
    const kardexLoading      = document.getElementById('kardexLoading');
    const kardexEmpty        = document.getElementById('kardexEmpty');
    const kardexTableCont    = document.getElementById('kardexTableContainer');
    const kardexTbody        = document.getElementById('kardexTbody');

    // Referencias a campos de cabecera analítica de Kardex
    const sumCodigo   = document.getElementById('sumCodigo');
    const sumCategoria = document.getElementById('sumCategoria');
    const sumEntradas = document.getElementById('sumEntradas');
    const sumSalidas  = document.getElementById('sumSalidas');
    const sumStock    = document.getElementById('sumStock');
    const sumStockVal = document.getElementById('sumStockVal');

    // Botones de acción
    const btnExportExcel = document.getElementById('btnExportExcel');
    const btnExportPDF   = document.getElementById('btnExportPDF');

    let debounceTimer = null;
    let currentData   = null; // Almacena el JSON de la consulta actual para exportación

    // Formatear a Moneda local (S/)
    function fmt(n) {
        if (n === null || n === undefined) return '—';
        return 'S/ ' + parseFloat(n).toFixed(2);
    }

    // Formatear cantidades numéricas y concatenar su unidad de medida
    function fmtNum(n, unidad) {
        if (n === null || n === undefined) return '—';
        return parseFloat(n).toLocaleString('es-PE', {minimumFractionDigits: 0, maximumFractionDigits: 2}) + (unidad ? ' ' + unidad : '');
    }

    // Formatear fecha y hora al estándar de Perú
    function fmtFecha(f) {
        if (!f) return '—';
        const d = new Date(f);
        return d.toLocaleDateString('es-PE', {day:'2-digit', month:'2-digit', year:'numeric'}) + ' ' +
               d.toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
    }

    // Petición AJAX al controlador C_Kardex.php para reconstruir los movimientos
    async function cargarKardex() {
        const id = selectProducto.value;
        if (!id) return;

        const opt    = selectProducto.options[selectProducto.selectedIndex];
        const unidad = opt.dataset.unidad || '';

        // Levantar spinner y ocultar tabla / alertas de datos vacíos
        kardexLoading.classList.remove('d-none');
        kardexEmpty.classList.add('d-none');
        kardexTableCont.classList.add('d-none');

        const params = new URLSearchParams({
            action:    'movimientos',
            id_producto: id,
            tipo:      filterTipo.value,
            desde:     filterDesde.value,
            hasta:     filterHasta.value,
            busqueda:  filterBusqueda.value
        });

        try {
            const res  = await fetch('./controllers/C_Kardex.php?' + params.toString());
            const json = await res.json();

            kardexLoading.classList.add('d-none');

            if (!json.success) {
                Swal.fire({ icon:'error', title:'Error', text: json.mensaje, confirmButtonColor:'#23284E' });
                return;
            }

            currentData = json.data;
            const d = json.data;

            // Rellenar métricas analíticas superiores
            sumCodigo.textContent    = 'INS-' + String(id).padStart(3, '0');
            sumCategoria.textContent = opt.dataset.categoria || '—';
            sumEntradas.innerHTML    = `<i class="bi bi-arrow-down-circle-fill text-success"></i> ${fmtNum(d.total_entrada_cant, unidad)}`;
            sumSalidas.innerHTML     = `<i class="bi bi-arrow-up-circle-fill text-danger"></i> ${fmtNum(d.total_salida_cant, unidad)}`;
            sumStock.textContent     = fmtNum(d.stock_actual, unidad);
            sumStockVal.textContent  = fmt(d.stock_actual * d.precio_unitario);

            // Activar botones de exportación
            btnExportExcel.disabled = false;
            btnExportPDF.disabled   = false;

            // Verificar si hay registros
            if (!d.rows || d.rows.length === 0) {
                kardexEmpty.classList.remove('d-none');
                kardexTableCont.classList.add('d-none');
                return;
            }

            // Dibujar dinámicamente filas del Kardex
            kardexTbody.innerHTML = '';
            d.rows.forEach(row => {
                const isEntrada = row.tipo_movimiento === 'entrada';
                const isSaldo   = row.tipo_doc === 'Saldo Inicial';

                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #f0f0f0';

                // Distinguir la fila del Saldo Inicial con fondo verde suave
                if (isSaldo) {
                    tr.style.background = '#f9fdf9';
                }

                tr.innerHTML = `
                    <td class="text-muted" style="white-space:nowrap; font-size:12px;">${fmtFecha(row.fecha)}</td>
                    <td>
                        <span class="badge rounded-pill ${isSaldo ? 'bg-secondary' : isEntrada ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}" style="font-size:11px; font-weight:600;">
                            ${row.tipo_doc}
                        </span>
                    </td>
                    <td class="fw-semibold" style="color:#23284E; font-size:12px;">${row.numero_doc}</td>
                    <td class="text-muted" style="font-size:12px;">${row.concepto}</td>

                    <!-- COLUMNAS ENTRADAS -->
                    <td class="text-end border-start border-2 ${isEntrada ? 'fw-semibold text-success' : 'text-muted'}">${isEntrada ? fmtNum(row.entrada_cant, unidad) : '—'}</td>
                    <td class="text-end ${isEntrada ? '' : 'text-muted'}">${isEntrada ? fmt(row.entrada_cu) : '—'}</td>
                    <td class="text-end ${isEntrada ? 'fw-semibold' : 'text-muted'}">${isEntrada ? fmt(row.entrada_ct) : '—'}</td>

                    <!-- COLUMNAS SALIDAS -->
                    <td class="text-end border-start border-2 ${!isEntrada && !isSaldo ? 'fw-semibold text-danger' : 'text-muted'}">
                        ${!isEntrada && !isSaldo 
                            ? (row.salida_peso > 0 
                                ? fmtNum(row.salida_cant, 'pzs') + '<br><small style="font-size:10px;">' + fmtNum(row.salida_peso, 'Kg') + '</small>' 
                                : fmtNum(row.salida_cant, unidad)) 
                            : '—'}
                    </td>
                    <td class="text-end ${!isEntrada && !isSaldo ? '' : 'text-muted'}">${!isEntrada && !isSaldo ? fmt(row.salida_cu) : '—'}</td>
                    <td class="text-end ${!isEntrada && !isSaldo ? 'fw-semibold' : 'text-muted'}">${!isEntrada && !isSaldo ? fmt(row.salida_ct) : '—'}</td>

                    <!-- COLUMNAS SALDOS (PROMEDIO PONDERADO) -->
                    <td class="text-end border-start border-2 fw-bold">${fmtNum(row.saldo_cant, unidad)}</td>
                    <td class="text-end">${fmt(row.saldo_cu)}</td>
                    <td class="text-end fw-semibold">${fmt(row.saldo_ct)}</td>
                `;
                kardexTbody.appendChild(tr);
            });

            kardexTableCont.classList.remove('d-none');

        } catch (err) {
            kardexLoading.classList.add('d-none');
            Swal.fire({ icon:'error', title:'Error de red', text:'No se pudo contactar al servidor.', confirmButtonColor:'#23284E' });
        }
    }

    // Manejar el cambio de producto del dropdown
    function onProductChange() {
        const id = selectProducto.value;
        if (!id) {
            kardexSummary.classList.add('d-none');
            kardexFilters.classList.add('d-none');
            kardexTableWrap.classList.add('d-none');
            kardexPlaceholder.classList.remove('d-none');
            btnExportExcel.disabled = true;
            btnExportPDF.disabled   = true;
            return;
        }
        kardexPlaceholder.classList.add('d-none');
        kardexSummary.classList.remove('d-none');
        kardexFilters.classList.remove('d-none');
        kardexTableWrap.classList.remove('d-none');
        cargarKardex();
    }

    // Retardar ligeramente la recarga (Debounce) para búsquedas rápidas en teclado
    function onFilterChange() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarKardex, 350);
    }

    selectProducto.addEventListener('change', onProductChange);
    filterTipo.addEventListener('change', onFilterChange);
    filterDesde.addEventListener('change', onFilterChange);
    filterHasta.addEventListener('change', onFilterChange);
    filterBusqueda.addEventListener('input', onFilterChange);

    // Exportar a PDF (dispara el cuadro de impresión nativo del navegador)
    btnExportPDF.addEventListener('click', () => {
        window.print();
    });

    // Exportar a formato CSV legible directamente por Excel
    btnExportExcel.addEventListener('click', () => {
        if (!currentData || !currentData.rows) return;
        const opt    = selectProducto.options[selectProducto.selectedIndex];
        const nombre = opt.text;
        let csv = '\uFEFF'; // BOM para codificar correctamente caracteres en español y UTF-8 en Excel
        csv += `Kardex de Inventario - ${nombre}\n`;
        csv += `Fecha,Tipo Doc.,N° Doc.,Concepto,Entrada Cant.,Entrada C.U.,Entrada C.T.,Salida Cant.,Salida C.U.,Salida C.T.,Saldo Cant.,Saldo C.U.,Saldo C.T.\n`;

        currentData.rows.forEach(r => {
            const f = (v) => v === null || v === undefined ? '' : v;
            csv += [
                f(r.fecha), f(r.tipo_doc), f(r.numero_doc), f(r.concepto),
                f(r.entrada_cant), f(r.entrada_cu), f(r.entrada_ct),
                f(r.salida_cant), f(r.salida_cu), f(r.salida_ct),
                f(r.saldo_cant), f(r.saldo_cu), f(r.saldo_ct)
            ].join(',') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = `kardex_${nombre.replace(/\s+/g,'_')}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    });
});
</script>
