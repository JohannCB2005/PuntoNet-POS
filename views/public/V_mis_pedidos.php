<?php
require_once dirname(__DIR__, 2) . '/config/sesion_segura.php';
session_start();
if (!isset($_SESSION['id_cliente'])) {
    header('Location: /cuenta?volver=mis_pedidos');
    exit;
}
require_once dirname(dirname(__DIR__)) . '/config/soporte.php';
$clienteNombre = $_SESSION['cliente_nombre'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos — NISSI</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/favicon-nissi.svg?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/tienda.css?v=4">
    <?php require_once dirname(__DIR__, 2) . '/config/marca.php'; echo marcaCss(); ?>

    <style>
        body { background: var(--paper); color: var(--ink); }
        .navbar { background: rgba(255,255,255,.9); backdrop-filter: blur(14px); }
        .navbar-brand { font-family: var(--font-display); font-weight: 800; color: var(--navy) !important; }
        .navbar-brand em { font-style: normal; color: var(--accent); }
        @media (max-width: 576px) { .nav-label { display: none; } }
        .page-title { font-family: var(--font-display); font-weight: 700; color: var(--navy); }
        .pedido-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 22px 26px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .pedido-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); border-color: var(--line-strong); }
        .pedido-numero { font-family: var(--font-display); font-weight: 700; font-size: 1.15rem; color: var(--navy); }
        .pedido-fecha { color: var(--muted); font-size: .85rem; }
        .pedido-total { font-family: var(--font-display); font-weight: 700; color: var(--accent); font-size: 1.2rem; }
        .entrega-info { font-size: .85rem; color: var(--muted); margin-top: 8px; }
        .item-row { display:flex; justify-content:space-between; font-size:.88rem; padding:6px 0; border-bottom:1px dashed var(--line); }
        .item-row:last-child { border-bottom:none; }
        .empty-state { text-align:center; padding:60px 20px; color: var(--muted); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/tienda">
                <span>NISSI<em>.</em></span>
            </a>
            <div class="d-flex gap-2">
                <a href="/mi-cuenta" class="btn btn-outline-primary rounded-pill btn-sm">
                    <i class="bi bi-person-gear"></i><span class="nav-label"> Mi Cuenta</span>
                </a>
                <a href="/tienda" class="btn btn-outline-primary rounded-pill btn-sm">
                    <i class="bi bi-shop"></i><span class="nav-label"> Volver a la tienda</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container" style="padding-top:90px; padding-bottom:60px; max-width:760px;">
        <h3 class="page-title mb-1">Mis Pedidos</h3>
        <p class="text-muted mb-2">Hola<?php echo $clienteNombre ? ', ' . htmlspecialchars($clienteNombre) : ''; ?>. Aquí puedes ver el estado de tus compras.</p>
        <p class="text-muted mb-4" style="font-size: 0.85rem;">
            <i class="bi bi-info-circle"></i>
            ¿Necesitas cancelar un pedido? Coordina con Atención al Cliente<?php echo WHATSAPP_ATENCION ? ' por WhatsApp al <strong>' . htmlspecialchars(WHATSAPP_ATENCION) . '</strong>' : ''; ?>.
        </p>

        <div id="listaPedidos">
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
    </div>

    <script>
    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    function etiquetaEstado(estado, tipoEntrega) {
        const esColegio = parseInt(tipoEntrega) === 2;
        const mapa = {
            3: { texto: 'Verificando pago', clase: 'bg-secondary' },
            1: { texto: 'Pago confirmado — Pendiente de entrega', clase: 'bg-warning text-dark' },
            5: { texto: esColegio ? 'Preparado para envío al colegio' : 'Listo para recoger', clase: 'bg-info text-dark' },
            2: { texto: esColegio ? 'Entregado al estudiante' : 'Entregado', clase: 'bg-success' },
            0: { texto: 'Rechazado', clase: 'bg-danger' },
            4: { texto: 'Pago no completado', clase: 'bg-dark' },
        };
        const info = mapa[estado] || mapa[4];
        return `<span class="badge ${info.clase}">${info.texto}</span>`;
    }

    async function cargarPedidos() {
        const cont = document.getElementById('listaPedidos');
        try {
            const res = await fetch('../../controllers/C_Ecommerce.php?action=mis_pedidos');
            const json = await res.json();

            if (!json.success || !json.data || json.data.length === 0) {
                cont.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-bag-x fs-1 mb-3 d-block"></i>
                        <p class="fw-semibold">Todavía no tienes pedidos.</p>
                        <a href="/tienda" class="btn btn-primary rounded-pill px-4">Ir a la tienda</a>
                    </div>
                `;
                return;
            }

            cont.innerHTML = json.data.map(p => {
                const fecha = new Date(p.fecha_pedido).toLocaleDateString('es-PE', { day: '2-digit', month: 'long', year: 'numeric' });
                const numero = '#' + String(p.id_pedido).padStart(6, '0');

                let entregaHtml = '<i class="bi bi-shop"></i> Recojo en tienda';
                if (parseInt(p.tipo_entrega) === 2) {
                    entregaHtml = `<i class="bi bi-mortarboard"></i> Entrega en colegio — ${escapeHtml(p.estudiante_nombre)} (${escapeHtml(p.nivel_nombre || '')} ${escapeHtml(p.grado_nombre || '')})`;
                }
                if (p.observaciones) {
                    entregaHtml += `<br><i class="bi bi-chat-left-text"></i> ${escapeHtml(p.observaciones)}`;
                }
                if (parseInt(p.estado) === 0 && p.motivo_rechazo) {
                    entregaHtml += `<br><span class="text-danger"><i class="bi bi-exclamation-circle"></i> Motivo: ${escapeHtml(p.motivo_rechazo)}</span>`;
                }

                const itemsHtml = (p.detalles || []).map(d => `
                    <div class="item-row">
                        <span>${escapeHtml(d.nombre)} x${parseFloat(d.cantidad)}</span>
                        <span class="fw-semibold">S/ ${parseFloat(d.subtotal).toFixed(2)}</span>
                    </div>
                `).join('');

                return `
                    <div class="pedido-card">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="pedido-numero">${numero}</div>
                                <div class="pedido-fecha">${fecha}</div>
                            </div>
                            <div class="text-end">
                                <div class="pedido-total">S/ ${parseFloat(p.total).toFixed(2)}</div>
                                ${etiquetaEstado(parseInt(p.estado), p.tipo_entrega)}
                            </div>
                        </div>
                        <div class="entrega-info">${entregaHtml}</div>
                        <hr>
                        ${itemsHtml}
                    </div>
                `;
            }).join('');
        } catch (e) {
            cont.innerHTML = '<p class="text-danger text-center">No pudimos cargar tus pedidos. Intenta de nuevo.</p>';
        }
    }

    cargarPedidos();
    </script>
</body>
</html>
