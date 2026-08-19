<?php
if (!isset($_SESSION['id_usuario'])) {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Pedidos Online (Click & Collect)</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Gestiona las compras realizadas a través de la tienda web.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="chkIncluirNoCompletados">
                <label class="form-check-label text-muted" style="font-size: 13px;" for="chkIncluirNoCompletados">
                    Ver intentos de pago no completados
                </label>
            </div>
            <button class="btn btn-outline-primary fw-semibold shadow-sm" id="btnActualizar">
                <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
            </button>
        </div>
    </div>

    <!-- Lista de Pedidos -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaPedidos">
                    <thead class="table-light">
                        <tr>
                            <th>N° Pedido</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Comprobante</th>
                            <th>Entrega</th>
                            <th>Referencia de Pago</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
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

<!-- Modal Detalles de Pedido -->
<div class="modal fade" id="modalDetallePedido" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Detalle del Pedido #<span id="detIdPedido"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="table-responsive">
                    <table class="table table-sm" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="detallesCuerpo"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVerPago" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold">Pago por verificación manual — Pedido #<span id="vpIdPedido"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <dl class="row mb-0" style="font-size: 13px;">
                    <dt class="col-sm-4 text-muted">Medio usado</dt>
                    <dd class="col-sm-8 fw-semibold" id="vpMedio">—</dd>
                    <dt class="col-sm-4 text-muted">N° de operación</dt>
                    <dd class="col-sm-8 font-monospace" id="vpReferencia">—</dd>
                    <dt class="col-sm-4 text-muted">Captura</dt>
                    <dd class="col-sm-8">
                        <img id="vpCaptura" src="" alt="Captura del comprobante" class="img-fluid d-none rounded" style="max-width:220px;border:1px solid #dee2e6;">
                        <span id="vpSinCaptura" class="text-muted">Sin captura</span>
                    </dd>
                </dl>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
const ROL_USUARIO = '<?php echo addslashes($_SESSION['rol']); ?>';

document.addEventListener('DOMContentLoaded', () => {
    cargarPedidos();

    document.getElementById('btnActualizar').addEventListener('click', cargarPedidos);
    document.getElementById('chkIncluirNoCompletados').addEventListener('change', cargarPedidos);

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    // Para incrustar cadenas dentro de un onclick='...' de una sola comilla.
    function escapeJs(str) {
        return String(str ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // Etiqueta del estado 5 depende de la modalidad de entrega — es lo que hace útil el seguimiento.
    function etiquetaPreparado(tipoEntrega) {
        return parseInt(tipoEntrega) === 2 ? 'Preparado para envío al colegio' : 'Listo para recoger';
    }
    function etiquetaEntregado(tipoEntrega) {
        return parseInt(tipoEntrega) === 2 ? 'Entregado al estudiante' : 'Entregado';
    }

    async function cargarPedidos() {
        try {
            const incluirNoCompletados = document.getElementById('chkIncluirNoCompletados').checked;
            const url = './controllers/C_Ecommerce.php?action=listar_pedidos' + (incluirNoCompletados ? '&incluir_no_completados=1' : '');
            const res = await fetch(url);
            const result = await res.json();
            if (result.success) {
                const tbody = document.querySelector('#tablaPedidos tbody');
                tbody.innerHTML = '';
                if (result.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No hay pedidos online.</td></tr>';
                } else {
                    result.data.forEach(p => {
                        const estado = parseInt(p.estado);
                        let estadoBadge = '';
                        let fechasHtml = '';
                        let acciones = `<button class="btn btn-sm btn-info text-white fw-bold me-1" onclick="verDetalles(${p.id_pedido})"><i class="bi bi-eye"></i></button>`;
                        const puedeRechazar = ROL_USUARIO === 'Administrador';

                        if (estado === 1) {
                            estadoBadge = '<span class="badge bg-warning text-dark">Pagado — en proceso</span>';
                            acciones += `
                                <button class="btn btn-sm btn-primary fw-bold me-1" onclick="gestionarPedido(${p.id_pedido}, 'preparar')"><i class="bi bi-box-seam"></i> Preparar</button>
                                <button class="btn btn-sm btn-success fw-bold me-1" onclick="gestionarPedido(${p.id_pedido}, 'entregar', ${p.requiere_codigo})"><i class="bi bi-check-lg"></i> Entregar</button>
                            `;
                            if (puedeRechazar) {
                                acciones += `<button class="btn btn-sm btn-danger fw-bold" onclick="gestionarPedido(${p.id_pedido}, 'rechazar')"><i class="bi bi-x-lg"></i> Rechazar</button>`;
                            }
                        } else if (estado === 5) {
                            estadoBadge = `<span class="badge bg-info text-dark">${etiquetaPreparado(p.tipo_entrega)}</span>`;
                            fechasHtml = p.fecha_preparado ? `<div class="small text-muted mt-1">Preparado: ${escapeHtml(p.fecha_preparado)}</div>` : '';
                            acciones += `<button class="btn btn-sm btn-success fw-bold me-1" onclick="gestionarPedido(${p.id_pedido}, 'entregar', ${p.requiere_codigo})"><i class="bi bi-check-lg"></i> Entregar</button>`;
                            if (puedeRechazar) {
                                acciones += `<button class="btn btn-sm btn-danger fw-bold" onclick="gestionarPedido(${p.id_pedido}, 'rechazar')"><i class="bi bi-x-lg"></i> Rechazar</button>`;
                            }
                        } else if (estado === 2) {
                            estadoBadge = `<span class="badge bg-success">${etiquetaEntregado(p.tipo_entrega)}</span>`;
                            fechasHtml = p.fecha_entregado ? `<div class="small text-muted mt-1">Entregado: ${escapeHtml(p.fecha_entregado)}</div>` : '';
                            acciones += `<a href="/historial?buscar=${p.id_venta}" class="btn btn-sm btn-outline-primary fw-bold">Ver Venta #${p.id_venta}</a>`;
                        } else if (estado === 6) {
                            estadoBadge = '<span class="badge bg-primary">Pago enviado — verificación manual</span>';
                            fechasHtml = p.fecha_verificacion ? `<div class="small text-muted mt-1">Reportado: ${escapeHtml(p.fecha_verificacion)}</div>` : '';
                            acciones += `
                                <button class="btn btn-sm btn-secondary fw-bold me-1" onclick="verPago(${p.id_pedido}, '${escapeJs(p.medio_pago_usado || '')}', '${escapeJs(p.referencia_cliente || '')}', '${escapeJs(p.captura_pago || '')}')"><i class="bi bi-receipt"></i> Ver pago</button>
                            `;
                            if (puedeRechazar) {
                                acciones += `
                                    <button class="btn btn-sm btn-success fw-bold me-1" onclick="gestionarPedido(${p.id_pedido}, 'aprobar')"><i class="bi bi-check-lg"></i> Aprobar</button>
                                    <button class="btn btn-sm btn-danger fw-bold" onclick="gestionarPedido(${p.id_pedido}, 'rechazar')"><i class="bi bi-x-lg"></i> Rechazar</button>
                                `;
                            }
                        } else if (estado === 3) {
                            estadoBadge = '<span class="badge bg-secondary">Esperando pago</span>';
                        } else if (estado === 4) {
                            estadoBadge = '<span class="badge bg-dark">Expirado / Pago fallido</span>';
                        } else {
                            estadoBadge = '<span class="badge bg-danger">Rechazado</span>';
                            if (p.motivo_rechazo) {
                                fechasHtml = `<div class="small text-muted mt-1" title="${escapeHtml(p.motivo_rechazo)}"><i class="bi bi-chat-left-text"></i> ${escapeHtml(p.motivo_rechazo)}</div>`;
                            }
                        }

                        let cliente = p.apellidos ? `${p.apellidos}, ${p.nombres_razon_social}` : p.nombres_razon_social;

                        // Boleta/Factura: con Factura se factura a la entidad con RUC resuelta en
                        // el checkout (razon_social_facturacion), NO a la cuenta del comprador.
                        let comprobanteHtml = '<span class="badge bg-light text-dark border">Boleta</span>';
                        if (parseInt(p.tipo_comprobante) === 2) {
                            comprobanteHtml = `<span class="badge bg-info-subtle text-info-emphasis border">Factura</span>`;
                            if (p.razon_social_facturacion) {
                                comprobanteHtml += `<div class="small text-muted mt-1">${escapeHtml(p.razon_social_facturacion)}<br>RUC: ${escapeHtml(p.ruc_facturacion)}</div>`;
                            }
                        }

                        // Ya viene escapado y con marcado: NO volver a pasarlo por escapeHtml().
                        // El UUID es lo que se busca en el Back Office de Izipay para
                        // cuadrar el pedido con la transacción concreta.
                        let referenciaPagoHtml = escapeHtml(p.referencia_pago || p.nro_operacion_yape || '—');
                        if (p.transaccion_uuid) {
                            referenciaPagoHtml += `<div class="small text-muted" style="font-size:.7rem;word-break:break-all;" title="UUID de la transaccion en Izipay">${escapeHtml(p.transaccion_uuid)}</div>`;
                        }

                        let entregaHtml = '<span class="badge bg-light text-dark border"><i class="bi bi-shop"></i> Recojo en tienda</span>';
                        if (parseInt(p.tipo_entrega) === 2) {
                            entregaHtml = `
                                <span class="badge bg-light text-dark border"><i class="bi bi-mortarboard"></i> Entrega en colegio</span>
                                <div class="small text-muted mt-1">
                                    ${escapeHtml(p.estudiante_nombre)}<br>
                                    ${escapeHtml(p.nivel_nombre || '')} ${escapeHtml(p.grado_nombre || '')}
                                </div>
                            `;
                        } else if (p.recoge_dni || p.recoge_nombre) {
                            entregaHtml += `
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-person-check"></i> Recoge:
                                    ${escapeHtml(p.recoge_nombre || '—')}
                                    <span class="text-nowrap">· DNI ${escapeHtml(p.recoge_dni || '—')}</span>
                                    ${p.quien_recoge === 'yo' ? '<span class="badge ms-1" style="background:#1f86c6;color:#fff;">el comprador</span>' : '<span class="badge ms-1" style="background:#3aa0dc;color:#fff;">otra persona</span>'}
                                </div>
                            `;
                        }
                        if (p.observaciones) {
                            entregaHtml += `<div class="small text-muted mt-1"><i class="bi bi-chat-left-text"></i> ${escapeHtml(p.observaciones)}</div>`;
                        }

                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-bold">#${p.id_pedido}</td>
                                <td>${escapeHtml(p.fecha_pedido)}</td>
                                <td>
                                    <div class="fw-semibold">${escapeHtml(cliente)}</div>
                                    <small class="text-muted">DNI: ${escapeHtml(p.numero_documento)} | Tel: ${escapeHtml(p.telefono || '-')}</small>
                                </td>
                                <td>${comprobanteHtml}</td>
                                <td>${entregaHtml}</td>
                                <td class="font-monospace">${referenciaPagoHtml}</td>
                                <td class="fw-bold text-success">S/ ${parseFloat(p.total).toFixed(2)}</td>
                                <td>${estadoBadge}${fechasHtml}</td>
                                <td class="text-end">${acciones}</td>
                            </tr>
                        `;
                    });
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    window.verDetalles = async (id_pedido) => {
        document.getElementById('detIdPedido').innerText = id_pedido;
        try {
            const res = await fetch(`./controllers/C_Ecommerce.php?action=detalles_pedido&id=${id_pedido}`);
            const result = await res.json();
            if (result.success) {
                let html = '';
                result.data.forEach(d => {
                    let cantInfo = `${parseFloat(d.cantidad).toFixed(2)} ${escapeHtml(d.abreviatura)}`;
                    html += `
                        <tr>
                            <td>${escapeHtml(d.nombre)}</td>
                            <td>${cantInfo}</td>
                            <td class="text-end fw-semibold">S/ ${parseFloat(d.subtotal).toFixed(2)}</td>
                        </tr>
                    `;
                });
                document.getElementById('detallesCuerpo').innerHTML = html;
                new bootstrap.Modal(document.getElementById('modalDetallePedido')).show();
            }
        } catch(e) { console.error(e); }
    };

    window.verPago = (id_pedido, medio, referencia, captura) => {
        const etiquetas = { yape: 'Yape', plin: 'Plin', izipay_qr: 'Izipay QR', bcp: 'BCP', bbva: 'BBVA', interbank: 'Interbank', scotiabank: 'Scotiabank' };
        document.getElementById('vpIdPedido').innerText   = id_pedido;
        document.getElementById('vpMedio').innerText       = etiquetas[medio] || medio || '—';
        document.getElementById('vpReferencia').innerText  = referencia || '—';
        const img = document.getElementById('vpCaptura');
        const sin = document.getElementById('vpSinCaptura');
        if (captura) {
            img.src = captura;
            img.classList.remove('d-none');
            sin.style.display = 'none';
        } else {
            img.classList.add('d-none');
            img.removeAttribute('src');
            sin.style.display = '';
        }
        new bootstrap.Modal(document.getElementById('modalVerPago')).show();
    };

    window.gestionarPedido = (id_pedido, accion, requiereCodigo) => {
        if (accion === 'rechazar') {
            Swal.fire({
                title: '¿Rechazar pedido?',
                html: 'El stock reservado será devuelto al inventario. El motivo que escribas <strong>se le enviará al cliente por correo</strong>, junto con la indicación de coordinar la devolución del dinero por WhatsApp con Atención al Cliente.',
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Motivo del rechazo (obligatorio)...',
                showCancelButton: true,
                confirmButtonText: 'Rechazar pedido',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                inputValidator: (value) => {
                    if (!value || !value.trim()) return 'Debes escribir un motivo.';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarGestion(id_pedido, accion, result.value.trim());
                }
            });
            return;
        }

        // Entregar con código de confirmación: se pide el código que dicta el cliente
        // (el cajero nunca ve el esperado) y se verifica en el servidor. El salto es
        // la última opción y pide una segunda confirmación.
        if (accion === 'entregar' && requiereCodigo) {
            Swal.fire({
                title: 'Código de confirmación',
                html: 'Pide al cliente el <strong>código de confirmación de 4 dígitos</strong> que recibió por correo y en "Mis compras".',
                icon: 'question',
                input: 'text',
                inputAttributes: { maxlength: '4', inputmode: 'numeric', pattern: '[0-9]*', autocomplete: 'off' },
                inputPlaceholder: 'Ej: 4821',
                showCancelButton: true,
                confirmButtonText: 'Verificar y entregar',
                cancelButtonText: 'Cancelar',
                showDenyButton: true,
                denyButtonText: 'Saltar verificación',
                inputValidator: (value) => {
                    if (!value || !/^\d{4}$/.test(value.trim())) return 'Escribe el código de 4 dígitos del cliente.';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarGestion(id_pedido, 'entregar', null, { codigo_confirmacion: result.value.trim() });
                } else if (result.isDenied) {
                    Swal.fire({
                        title: '¿Saltar verificación?',
                        text: 'Solo como última opción (cliente que no tiene su código). Asegúrate de que es el cliente.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, saltar',
                        confirmButtonColor: '#dc3545',
                        cancelButtonText: 'Cancelar'
                    }).then((r) => {
                        if (r.isConfirmed) enviarGestion(id_pedido, 'entregar', null, { saltar_codigo: true });
                    });
                }
            });
            return;
        }

        const titulos = { preparar: '¿Marcar como preparado?', entregar: '¿Marcar como entregado?', aprobar: '¿Aprobar este pago?' };
        const textos = {
            preparar: 'El pedido pasará a estado "preparado", listo para su recojo o envío.',
            entregar: 'Se generará la venta correspondiente y se notificará al cliente por correo.',
            aprobar: 'Confirmas que ya recibiste el pago. El pedido pasará a "Pagado — en proceso" y el cliente será notificado por correo.'
        };

        Swal.fire({
            title: titulos[accion] || '¿Confirmar acción?',
            text: textos[accion] || '',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarGestion(id_pedido, accion, null);
            }
        });
    };

    async function enviarGestion(id_pedido, accion, motivo, extra) {
        try {
            const res = await fetch('./controllers/C_Ecommerce.php?action=gestionar_pedido', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ id_pedido, accion, motivo }, extra || {}))
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarPedidos();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red.' });
        }
    }
});
</script>
