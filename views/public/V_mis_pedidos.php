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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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

        /* Código de confirmación del pedido */
        .codigo-confirmacion-wrap {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            background: #f6f6f8; border: 1px dashed var(--line-strong); border-radius: 12px;
            padding: 10px 14px; margin-top: 10px;
        }
        .codigo-confirmacion-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .codigo-confirmacion { font-family: var(--font-display); font-weight: 800; font-size: 1.5rem; letter-spacing: .14em; color: var(--navy); }

        /* Medios de pago manual */
        .pm-opciones { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; justify-content: center; }
        .pm-chip {
            flex: 1 1 120px; max-width: 150px; min-width: 104px;
            border: 2px solid var(--line); background: #fff; border-radius: 14px;
            padding: 8px; cursor: pointer; text-align: center;
            transition: border-color .15s, box-shadow .15s, transform .15s;
        }
        .pm-chip img { width: 100%; height: 56px; object-fit: contain; border-radius: 10px; background: #fff; }
        .pm-chip:hover { border-color: var(--line-strong); box-shadow: 0 6px 16px rgba(0,0,0,.10); transform: translateY(-2px); }
        .pm-chip.activo { border-color: var(--sage); box-shadow: 0 6px 16px rgba(0,0,0,.16); }
        .pm-chip .pm-chip-label { font-size: .72rem; color: var(--muted); font-weight: 600; }
        .pm-chip.pm-banco { padding: 4px; background: transparent; }
        .pm-chip.pm-banco img { height: 68px; object-fit: cover; border-radius: 10px; background: transparent; }
        .pm-qr-wrap { display: inline-block; background: #fff; border-radius: 0; padding: 14px; box-shadow: 0 8px 20px rgba(0,0,0,.14); border: 1px solid var(--line); overflow: hidden; }
        .pm-qr-wrap img, .pm-qr-wrap canvas { border-radius: 0; display: block; }
        .pm-cuenta-linea { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 9px 14px; margin-bottom: 8px; }
        .pm-cuenta-linea .pm-cuenta-valor { font-family: var(--font-display), monospace; font-weight: 700; font-size: .9rem; letter-spacing: .5px; word-break: break-all; }
        .pm-copiar { flex: 0 0 auto; border: 1px solid var(--line-strong); background: #fff; border-radius: 9px; padding: 6px 12px; font-size: .75rem; font-weight: 700; color: var(--navy); cursor: pointer; transition: all .15s; }
        .pm-copiar:hover { background: var(--navy); color: #fff; border-color: var(--navy); }
        .pm-copiar.copiado { background: var(--sage); border-color: var(--sage); color: #fff; }
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

    <!-- Modal: reportar pago por verificación manual -->
    <div class="modal fade" id="modalReportePago" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="bi bi-check-lg text-success"></i> Reportar pago</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="modalReportePagoBody"><!-- JS --></div>
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
            6: { texto: 'Pago enviado — en verificación manual', clase: 'bg-primary' },
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

                // Un pedido manual pendiente (estado 3) permite al cliente reportar
                // su pago desde aquí si salió del checkout sin hacerlo.
                const esManual = parseInt(p.estado) === 3
                    && (p.metodo_pago_online === 'billetera' || p.metodo_pago_online === 'transferencia');

                // Código de confirmación (4 dígitos): lo presenta el cliente al recojo.
                // Solo se muestra si el switch de la tienda está activo (llega desde el backend).
                const codigoHtml = p.codigo_confirmacion ? `
                    <div class="codigo-confirmacion-wrap">
                        <div>
                            <div class="codigo-confirmacion-label">Código de confirmación</div>
                            <div class="codigo-confirmacion">${escapeHtml(p.codigo_confirmacion)}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="copiarCodigo(this, '${escapeHtml(p.codigo_confirmacion)}')" title="Copiar código">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>` : '';

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
                                ${esManual ? `
                                <button type="button" class="btn btn-sm btn-success rounded-pill mt-2 reportar-pago"
                                        data-id-pedido="${p.id_pedido}"
                                        data-token="${escapeHtml(p.token_publico)}"
                                        data-metodo="${escapeHtml(p.metodo_pago_online)}">
                                    <i class="bi bi-check-lg"></i> Reportar pago
                                </button>` : ''}
                            </div>
                        </div>
                        <div class="entrega-info">${entregaHtml}</div>
                        ${codigoHtml}
                        <hr>
                        ${itemsHtml}
                    </div>
                `;
            }).join('');
        } catch (e) {
            cont.innerHTML = '<p class="text-danger text-center">No pudimos cargar tus pedidos. Intenta de nuevo.</p>';
        }
    }

    // ── Reporte de pago manual desde Mis Pedidos ──
    // Un pedido con verificación manual pendiente (estado 3) permite al cliente
    // reportar su pago aquí si salió del checkout sin completarlo.
    let manualInfo = null;

    async function cargarManualInfo() {
        if (manualInfo) return;
        try {
            const res  = await fetch('../../controllers/C_Ecommerce.php?action=pasarelas', { cache: 'no-store' });
            const json = await res.json();
            manualInfo = (json.success && json.manual) ? json.manual : null;
        } catch (e) { manualInfo = null; }
    }

    document.getElementById('listaPedidos').addEventListener('click', async (ev) => {
        const btn = ev.target.closest('.reportar-pago');
        if (!btn) return;
        const idPedido = btn.dataset.idPedido;
        const token    = btn.dataset.token;
        const metodo   = btn.dataset.metodo;

        await cargarManualInfo();
        if (!manualInfo || !manualInfo.datos) {
            Swal.fire('Error', 'No se pudo cargar la información de pago. Intenta de nuevo.', 'error');
            return;
        }

        const d = manualInfo.datos;
        let seleccionHtml = '', ocultoMedio = '';
        let valorInicial = '';

        if (metodo === 'billetera') {
            const b = d.billetera;
            let chips = '';
            if (b.qr_yape_contenido) chips += `<div class="pm-chip pm-billetera" data-medio="yape"><img src="../../${escapeHtml(b.logo_yape)}" alt="Yape"><span class="pm-chip-label">Yape</span></div>`;
            if (b.qr_plin_contenido) chips += `<div class="pm-chip pm-billetera" data-medio="plin"><img src="../../${escapeHtml(b.logo_plin)}" alt="Plin"><span class="pm-chip-label">Plin</span></div>`;
            if (b.qr_izipay) chips += `<div class="pm-chip pm-billetera" data-medio="izipay_qr"><img src="../../${escapeHtml(b.qr_izipay)}" alt="Izipay QR"><span class="pm-chip-label">Izipay QR</span></div>`;
            seleccionHtml = `
                <div class="fw-semibold mb-2" style="font-size:.9rem;">¿Cómo vas a pagar?</div>
                <div class="pm-opciones">${chips}</div>
                <div id="pmr-destino" class="text-center mb-3"></div>`;
            valorInicial = b.qr_yape_contenido ? 'yape' : (b.qr_plin_contenido ? 'plin' : 'izipay_qr');
            ocultoMedio = `<input type="hidden" id="pmr-medio" value="${valorInicial}">`;
        } else {
            seleccionHtml = `
                <div class="fw-semibold mb-2" style="font-size:.9rem;">¿A qué banco vas a transferir?</div>
                <div class="pm-opciones">
                    ${d.bancos.map(b => `
                        <div class="pm-chip pm-banco" data-medio="${escapeHtml(b.codigo)}" title="${escapeHtml(b.nombre)}">
                            ${b.logo ? `<img src="../../${escapeHtml(b.logo)}" alt="${escapeHtml(b.nombre)}">` : ''}
                        </div>`).join('')}
                </div>
                <div id="pmr-destino" class="mb-3"></div>`;
            valorInicial = d.bancos[0] ? d.bancos[0].codigo : '';
            ocultoMedio = `<input type="hidden" id="pmr-medio" value="${valorInicial}">`;
        }

        document.getElementById('modalReportePagoBody').innerHTML = `
            ${d.instrucciones ? `<p class="text-muted" style="font-size:.8rem;">${escapeHtml(d.instrucciones)}</p>` : ''}
            ${seleccionHtml}
            ${ocultoMedio}
            <label class="form-label fw-semibold">Número de operación <span class="text-danger">*</span></label>
            <input class="form-control mb-3" id="pmr-referencia" placeholder="Ej: 123456789" maxlength="255">
            <p id="pmr-msg" class="text-danger small" style="display:none;"></p>
            <button class="btn btn-success w-100 fw-bold" id="pmr-enviar"><i class="bi bi-check-lg"></i> Ya pagué — Reportar pago</button>
        `;

        function pintarDestinoPmr() {
            const medio = document.getElementById('pmr-medio').value;
            const cont  = document.getElementById('pmr-destino');
            if (!cont) return;
            if (metodo === 'billetera') {
                const b = d.billetera;
                if (medio === 'yape' || medio === 'plin') {
                    const contenido = medio === 'yape' ? b.qr_yape_contenido : b.qr_plin_contenido;
                    if (!contenido) { cont.innerHTML = ''; return; }
                    cont.innerHTML = `
                        <div class="pm-qr-wrap" id="pmr-qr">
                            <div class="text-muted" style="font-size:.8rem;margin-bottom:8px;">Escanea con tu app <strong>${medio === 'yape' ? 'Yape' : 'Plin'}</strong></div>
                        </div>
                        ${b.titular ? `<div class="text-muted mt-2" style="font-size:.8rem;">Titular: <strong>${escapeHtml(b.titular)}</strong></div>` : ''}`;
                    const wrap = document.getElementById('pmr-qr');
                    if (typeof QRCode !== 'undefined') {
                        new QRCode(wrap, { text: contenido, width: 150, height: 150, correctLevel: QRCode.CorrectLevel.M });
                    } else {
                        const img = document.createElement('img');
                        img.src = medio === 'yape' ? '../../assets/pagos/qr-yape-original.jpeg' : '../../assets/pagos/qr-plin-original.jpeg';
                        img.style.width = '150px';
                        wrap.appendChild(img);
                    }
                } else if (medio === 'izipay_qr') {
                    cont.innerHTML = `
                        <div class="pm-qr-wrap">
                            <img src="../../${escapeHtml(b.qr_izipay)}" alt="Izipay QR" style="width:150px;">
                        </div>
                        ${b.titular ? `<div class="text-muted mt-2" style="font-size:.8rem;">Titular: <strong>${escapeHtml(b.titular)}</strong></div>` : ''}`;
                }
                return;
            }
            const banco = d.bancos.find(x => x.codigo === medio);
            if (!banco) { cont.innerHTML = ''; return; }
            cont.innerHTML = `
                ${banco.titular ? `<div class="text-muted mb-2" style="font-size:.8rem;">Titular: <strong>${escapeHtml(banco.titular)}</strong></div>` : ''}
                <div class="pm-cuenta-linea">
                    <div class="text-start">
                        <div class="small text-muted" style="font-size:.72rem;">N° de cuenta</div>
                        <div class="pm-cuenta-valor">${escapeHtml(banco.cuenta)}</div>
                    </div>
                    <button type="button" class="pm-copiar" data-copiar="${escapeHtml(banco.cuenta)}"><i class="bi bi-clipboard me-1"></i>Copiar</button>
                </div>
                ${banco.cci ? `
                <div class="pm-cuenta-linea">
                    <div class="text-start">
                        <div class="small text-muted" style="font-size:.72rem;">CCI (cuenta interbancaria)</div>
                        <div class="pm-cuenta-valor">${escapeHtml(banco.cci)}</div>
                    </div>
                    <button type="button" class="pm-copiar" data-copiar="${escapeHtml(banco.cci)}"><i class="bi bi-clipboard me-1"></i>Copiar</button>
                </div>` : ''}`;
            cont.querySelectorAll('.pm-copiar').forEach(b => b.addEventListener('click', () => copiarPmr(b, b.dataset.copiar)));
        }

        function copiarCodigo(btn, texto) {
            const icono = btn.querySelector('i');
            const avisar = () => {
                btn.innerHTML = '<i class="bi bi-check-lg text-success"></i> Copiado';
                setTimeout(() => { btn.innerHTML = icono.outerHTML; }, 1800);
            };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(texto).then(avisar).catch(() => copiarFallback(btn, texto, avisar));
            } else {
                copiarFallback(btn, texto, avisar);
            }
        }
        function copiarFallback(btn, texto, avisar) {
            const ta = document.createElement('textarea');
            ta.value = texto; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { if (document.execCommand('copy')) avisar(); } catch (e) {}
            document.body.removeChild(ta);
        }

        function copiarPmr(btn, texto) {
            let ok = false;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(texto).then(() => { ok = true; feedback(); }).catch(() => fallback());
            } else { fallback(); }
            function feedback() {
                btn.classList.add('copiado');
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Copiado';
                setTimeout(() => { btn.classList.remove('copiado'); btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar'; }, 1800);
            }
            function fallback() {
                const ta = document.createElement('textarea');
                ta.value = texto; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                try { if (document.execCommand('copy')) feedback(); } catch (e) {}
                document.body.removeChild(ta);
            }
        }

        pintarDestinoPmr();
        document.querySelectorAll('#modalReportePagoBody .pm-chip').forEach(chip => {
            if (chip.dataset.medio === document.getElementById('pmr-medio').value) chip.classList.add('activo');
            chip.addEventListener('click', () => {
                document.querySelectorAll('#modalReportePagoBody .pm-chip').forEach(c => c.classList.remove('activo'));
                chip.classList.add('activo');
                document.getElementById('pmr-medio').value = chip.dataset.medio;
                pintarDestinoPmr();
            });
        });

        const enviar = document.getElementById('pmr-enviar');
        enviar.onclick = async () => {
            enviar.disabled = true;
            enviar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
            const formData = new FormData();
            formData.append('id_pedido', idPedido);
            formData.append('token', token);
            formData.append('medio_pago', document.getElementById('pmr-medio').value);
            formData.append('referencia', document.getElementById('pmr-referencia').value.trim());
            const msg = document.getElementById('pmr-msg');
            try {
                const res  = await fetch('../../controllers/C_PagoManual.php?action=reportar', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalReportePago')).hide();
                    Swal.fire({ icon: 'success', title: 'Pago reportado', text: json.mensaje || 'Recibimos tu pago. Un administrador lo verificará.', confirmButtonText: 'OK' });
                    cargarPedidos();
                } else {
                    enviar.disabled = false;
                    enviar.innerHTML = '<i class="bi bi-check-lg"></i> Ya pagué — Reportar pago';
                    msg.style.display = 'block';
                    msg.textContent = json.mensaje || 'No pudimos registrar tu pago.';
                }
            } catch (e) {
                enviar.disabled = false;
                enviar.innerHTML = '<i class="bi bi-check-lg"></i> Ya pagué — Reportar pago';
                msg.style.display = 'block';
                msg.textContent = 'Error de conexión. Intenta de nuevo.';
            }
        };

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportePago')).show();
    });

    cargarPedidos();
    </script>
</body>
</html>
