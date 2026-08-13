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
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold text-dark">Detalle del Pedido #<span id="detIdPedido"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
            <div class="modal-footer border-top px-4 py-3 bg-light">
                <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Cerrar</button>
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
                                <button class="btn btn-sm btn-success fw-bold me-1" onclick="gestionarPedido(${p.id_pedido}, 'entregar')"><i class="bi bi-check-lg"></i> Entregar</button>
                            `;
                            if (puedeRechazar) {
                                acciones += `<button class="btn btn-sm btn-danger fw-bold" onclick="gestionarPedido(${p.id_pedido}, 'rechazar')"><i class="bi bi-x-lg"></i> Rechazar</button>`;
                            }
                        } else if (estado === 5) {
                            estadoBadge = `<span class="badge bg-info text-dark">${etiquetaPreparado(p.tipo_entrega)}</span>`;
                            fechasHtml = p.fecha_preparado ? `<div class="small text-muted mt-1">Preparado: ${escapeHtml(p.fecha_preparado)}</div>` : '';
                            acciones += `<button class="btn btn-sm btn-success fw-bold me-1" onclick="gestionarPedido(${p.id_pedido}, 'entregar')"><i class="bi bi-check-lg"></i> Entregar</button>`;
                            if (puedeRechazar) {
                                acciones += `<button class="btn btn-sm btn-danger fw-bold" onclick="gestionarPedido(${p.id_pedido}, 'rechazar')"><i class="bi bi-x-lg"></i> Rechazar</button>`;
                            }
                        } else if (estado === 2) {
                            estadoBadge = `<span class="badge bg-success">${etiquetaEntregado(p.tipo_entrega)}</span>`;
                            fechasHtml = p.fecha_entregado ? `<div class="small text-muted mt-1">Entregado: ${escapeHtml(p.fecha_entregado)}</div>` : '';
                            acciones += `<a href="/historial?buscar=${p.id_venta}" class="btn btn-sm btn-outline-primary fw-bold">Ver Venta #${p.id_venta}</a>`;
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

    window.gestionarPedido = (id_pedido, accion) => {
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

        const titulos = { preparar: '¿Marcar como preparado?', entregar: '¿Marcar como entregado?' };
        const textos = {
            preparar: 'El pedido pasará a estado "preparado", listo para su recojo o envío.',
            entregar: 'Se generará la venta correspondiente y se notificará al cliente por correo.'
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

    async function enviarGestion(id_pedido, accion, motivo) {
        try {
            const res = await fetch('./controllers/C_Ecommerce.php?action=gestionar_pedido', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_pedido, accion, motivo })
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
