import re

with open('views/V_nueva_cotizacion.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Remove caja check
caja_check = """// Verificar si el usuario actual tiene una apertura de caja activa
$modelCaja = M_Caja::singleton();
$cajaAbierta = $modelCaja->obtenerCajaAbierta($_SESSION['id_usuario']);"""
content = content.replace(caja_check, "")

caja_alert = """<?php if (!$cajaAbierta): ?>
<div class="alert border-0 bg-white shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-4">
    <div class="bg-danger bg-opacity-10 p-3 rounded-circle text-danger">
        <i class="bi bi-box-seam fs-3"></i>
    </div>
    <div class="flex-grow-1">
        <h5 class="fw-bold text-dark mb-1">Caja Cerrada</h5>
        <p class="text-muted mb-0">No puedes registrar ventas porque no has aperturado tu caja de hoy. Por favor, abre tu caja para continuar.</p>
    </div>
    <a href="index.php?modulo=caja" class="btn btn-danger px-4 rounded-pill fw-semibold">Aperturar Caja <i class="bi bi-arrow-right ms-2"></i></a>
</div>
<?php else: ?>"""
content = content.replace(caja_alert, "")
content = content.replace("<?php endif; // Fin if caja abierta ?>", "")

# Disable caja condition in JS
content = content.replace("<?php echo $cajaAbierta ? 'false' : 'true'; ?>", "false")

# Change headers
content = content.replace('<h4 class="mb-1 fw-bold text-dark">Nueva Venta</h4>', '<h4 class="mb-1 fw-bold text-dark">Nueva Cotización</h4>')
content = content.replace('<p class="text-muted mb-0" style="font-size: 14px;">Registra ventas, emite boletas/facturas rápidamente.</p>', '<p class="text-muted mb-0" style="font-size: 14px;">Crea presupuestos y cotizaciones.</p>')

# Replace Document Type and Payment Method with Validez and Observaciones
doc_payment_html = """                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted" style="font-size: 13px;">Comprobante</label>
                            <select class="form-select form-select-sm shadow-none" id="docTypeSelect">
                                <option value="1" selected>Boleta de Venta</option>
                                <option value="2">Factura Electrónica</option>
                                <option value="3">Nota de Venta (Interna)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted" style="font-size: 13px;">Método de Pago</label>
                            <select class="form-select form-select-sm shadow-none" id="metodoPagoSelect">
                                <option value="1" selected>Efectivo</option>
                                <option value="2">Yape</option>
                                <option value="3">Plin</option>
                                <option value="4">Tarjeta (POS)</option>
                                <option value="5">Transferencia</option>
                            </select>
                        </div>"""

new_validez_html = """                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted" style="font-size: 13px;">Validez (Días)</label>
                            <select class="form-select form-select-sm shadow-none" id="cotValidezSelect">
                                <option value="7">7 Días</option>
                                <option value="15" selected>15 Días</option>
                                <option value="30">30 Días</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted" style="font-size: 13px;">Observaciones</label>
                            <textarea class="form-control form-control-sm shadow-none" id="cotObservaciones" rows="2" placeholder="Ej. Precios sujetos a stock..."></textarea>
                        </div>"""
content = content.replace(doc_payment_html, new_validez_html)

# Remove modalValeHtml and payment toggle (since there's no payment)
vale_btn = """                        <button class="btn btn-outline-secondary btn-sm w-100 mt-2 rounded-2" data-bs-toggle="modal" data-bs-target="#modalVale">
                            <i class="bi bi-gift"></i> Pagar con Vale
                        </button>"""
content = content.replace(vale_btn, "")

submit_btn = """                        <button class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold shadow-sm d-flex justify-content-center align-items-center gap-2 mt-3" id="submitSaleBtn" disabled>
                            <i class="bi bi-check-circle"></i> Confirmar Venta
                        </button>"""
submit_btn_new = """                        <button class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold shadow-sm d-flex justify-content-center align-items-center gap-2 mt-3" id="submitSaleBtn" disabled>
                            <i class="bi bi-check-circle"></i> Generar Cotización
                        </button>"""
content = content.replace(submit_btn, submit_btn_new)

# JS AJAX call update
js_ajax_old = """                const tipo_comprobante = parseInt(docTypeSelect.value);
                const metodo_pago = parseInt(document.getElementById('metodoPagoSelect').value);
                
                let totalGeneral = 0;
                cart.forEach(item => totalGeneral += item.subtotal);
                
                const dataToSend = {
                    id_cliente,
                    id_trabajador: null,
                    tipo_comprobante,
                    metodo_pago,
                    total: totalGeneral,
                    id_vale: null,
                    pago_vale: 0,
                    pago_efectivo: totalGeneral,
                    cart: cart.map(item => ({
                        id_insumo: item.id_insumo,
                        piezas: item.cantidad,
                        peso_neto: 0,
                        precio: item.precio,
                        subtotal: item.subtotal
                    }))
                };

                let docLabel = 'Boleta';
                if (tipo_comprobante === 2) docLabel = 'Factura';
                else if (tipo_comprobante === 3) docLabel = 'Nota de Venta';
                
                Swal.fire({
                    title: '¿Confirmar venta?',
                    text: `Se registrará una ${docLabel} por un total de S/ ${totalFinal.toFixed(2)}`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0284c7',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Registrar',
                    cancelButtonText: 'Cancelar'
                }).then(async (res) => {
                    if (res.isConfirmed) {
                        submitSaleBtn.disabled = true;
                        try {
                            const response = await fetch('./controllers/C_Venta.php?action=crear', {"""

js_ajax_new = """                const validez = parseInt(document.getElementById('cotValidezSelect').value);
                const observaciones = document.getElementById('cotObservaciones').value;
                const cliente_manual = document.getElementById('clientNameInput').value; // fallback
                
                let totalGeneral = 0;
                cart.forEach(item => totalGeneral += item.subtotal);
                const subtotalVal = totalGeneral / 1.18;
                const igvVal = totalGeneral - subtotalVal;
                
                const dataToSend = {
                    id_cliente,
                    cliente_nombre_manual: cliente_manual,
                    validez: validez,
                    observaciones: observaciones,
                    subtotal: subtotalVal,
                    igv: igvVal,
                    total: totalGeneral,
                    detalles: cart.map(item => ({
                        id_insumo: item.id_insumo,
                        cantidad: item.cantidad,
                        precio: item.precio,
                        subtotal: item.subtotal
                    }))
                };
                
                Swal.fire({
                    title: '¿Generar cotización?',
                    text: `Se generará una cotización por un total de S/ ${totalFinal.toFixed(2)}`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0284c7',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Generar',
                    cancelButtonText: 'Cancelar'
                }).then(async (res) => {
                    if (res.isConfirmed) {
                        submitSaleBtn.disabled = true;
                        try {
                            const response = await fetch('./controllers/C_Cotizacion.php?action=crear', {"""

content = content.replace(js_ajax_old, js_ajax_new)

# Success handler
success_old = """                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Venta Registrada!',
                                    text: result.mensaje,
                                    showConfirmButton: false,
                                    timer: 1000
                                }).then(() => {
                                    cart = [];
                                    renderCart();
                                    
                                    // Levantar Modal de previsualización e Impresión de Comprobante
                                    openPrintModal(result.id_venta);
                                });"""

success_new = """                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Cotización Creada!',
                                    text: result.mensaje,
                                    showConfirmButton: false,
                                    timer: 1000
                                }).then(() => {
                                    cart = [];
                                    renderCart();
                                    
                                    // Levantar Modal de previsualización e Impresión de Comprobante
                                    openPrintModal(result.id_cotizacion);
                                });"""
content = content.replace(success_old, success_new)

# Printing iframe target
content = content.replace("`views/V_ticket_print.php?id=${currentPrintId}&format=${currentPrintFormat}`", "`views/V_cotizacion_print.php?id=${currentPrintId}&format=${currentPrintFormat}`")

with open('views/V_nueva_cotizacion.php', 'w', encoding='utf-8') as f:
    f.write(content)

