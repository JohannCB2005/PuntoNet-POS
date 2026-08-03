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
        <div>
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
document.addEventListener('DOMContentLoaded', () => {
    cargarPedidos();

    document.getElementById('btnActualizar').addEventListener('click', cargarPedidos);

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    async function cargarPedidos() {
        try {
            const res = await fetch('./controllers/C_Ecommerce.php?action=listar_pedidos');
            const result = await res.json();
            if (result.success) {
                const tbody = document.querySelector('#tablaPedidos tbody');
                tbody.innerHTML = '';
                if (result.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay pedidos online.</td></tr>';
                } else {
                    result.data.forEach(p => {
                        let estadoBadge = '';
                        let acciones = `<button class="btn btn-sm btn-info text-white fw-bold me-1" onclick="verDetalles(${p.id_pedido})"><i class="bi bi-eye"></i></button>`;

                        if (p.estado == 1) {
                            estadoBadge = '<span class="badge bg-warning text-dark">Pendiente</span>';
                            acciones += `
                                <button class="btn btn-sm btn-success fw-bold me-1" onclick="gestionarPedido(${p.id_pedido}, 'aprobar')"><i class="bi bi-check-lg"></i> Aprobar</button>
                                <button class="btn btn-sm btn-danger fw-bold" onclick="gestionarPedido(${p.id_pedido}, 'rechazar')"><i class="bi bi-x-lg"></i> Rechazar</button>
                            `;
                        } else if (p.estado == 2) {
                            estadoBadge = '<span class="badge bg-success">Aprobado / Entregado</span>';
                            acciones += `<a href="index.php?modulo=historial&buscar=${p.id_venta}" class="btn btn-sm btn-outline-primary fw-bold">Ver Venta #${p.id_venta}</a>`;
                        } else if (p.estado == 3) {
                            estadoBadge = '<span class="badge bg-secondary">Esperando pago</span>';
                        } else if (p.estado == 4) {
                            estadoBadge = '<span class="badge bg-dark">Expirado / Pago fallido</span>';
                        } else {
                            estadoBadge = '<span class="badge bg-danger">Rechazado</span>';
                        }

                        let cliente = p.apellidos ? `${p.apellidos}, ${p.nombres_razon_social}` : p.nombres_razon_social;
                        let referenciaPago = p.payment_intent_id || p.nro_operacion_yape || '—';

                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-bold">#${p.id_pedido}</td>
                                <td>${escapeHtml(p.fecha_pedido)}</td>
                                <td>
                                    <div class="fw-semibold">${escapeHtml(cliente)}</div>
                                    <small class="text-muted">DNI: ${escapeHtml(p.numero_documento)} | Tel: ${escapeHtml(p.telefono || '-')}</small>
                                </td>
                                <td class="font-monospace">${escapeHtml(referenciaPago)}</td>
                                <td class="fw-bold text-success">S/ ${parseFloat(p.total).toFixed(2)}</td>
                                <td>${estadoBadge}</td>
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
        let title = accion === 'aprobar' ? '¿Aprobar Pedido?' : '¿Rechazar Pedido?';
        let text = accion === 'aprobar'
            ? 'Se generará una Venta en el sistema con el pago ya verificado.'
            : 'El stock reservado será devuelto al inventario.';
            
        Swal.fire({
            title: title,
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const res = await fetch('./controllers/C_Ecommerce.php?action=gestionar_pedido', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_pedido, accion })
                    });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                        cargarPedidos();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
                    }
                } catch(e) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red.' });
                }
            }
        });
    };
});
</script>
