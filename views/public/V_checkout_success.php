<?php
/**
 * Retorno del formulario de pago de Izipay.
 *
 * Krypton hace POST aquí con `kr-answer` (el resultado en JSON) y `kr-hash` (su firma).
 * La firma se valida con IZIPAY_HMAC_SHA256 — ojo, NO con la contraseña, que es la que
 * usa la IPN. Ver models/M_Izipay.php.
 *
 * Esta página es una comodidad para el cliente, no la fuente de verdad del cobro: la
 * confirmación autorizada llega por la IPN (controllers/C_IzipayIPN.php), que se dispara
 * aunque el cliente cierre el navegador. Aun así se confirma también aquí para que el
 * pedido quede al día de inmediato, y confirmarPagoPedido() es idempotente.
 */
session_start();
require_once dirname(dirname(__DIR__)) . '/models/M_Ecommerce.php';
require_once dirname(dirname(__DIR__)) . '/models/M_Izipay.php';

$modelo   = M_Ecommerce::singleton();
$izipay   = M_Izipay::singleton();

$idPedido = 0;
$token    = '';
$errorPago = '';

if (!empty($_POST['kr-answer'])) {
    // Camino normal: venimos del formulario de pago.
    $krAnswer = (string) $_POST['kr-answer'];
    $krHash   = (string) ($_POST['kr-hash'] ?? '');

    if (!$izipay->verificarFirmaNavegador($krAnswer, $krHash)) {
        // Respuesta manipulada: no se toca el pedido bajo ningún concepto.
        $errorPago = 'No pudimos validar la respuesta de la pasarela de pago.';
    } else {
        $resultado = $izipay->parsearRespuesta($krAnswer);
        $pedidoRef = $resultado['orderId'] !== '' ? $modelo->buscarPorReferencia($resultado['orderId']) : null;

        if (!$pedidoRef) {
            $errorPago = 'No encontramos el pedido asociado a este pago.';
        } else {
            $idPedido = (int) $pedidoRef['id_pedido'];
            if ($resultado['orderStatus'] === 'PAID') {
                // Reconsulta a Izipay por dentro y es idempotente frente a la IPN.
                $modelo->confirmarPagoPedido($idPedido, $resultado['orderId']);
            } else {
                $errorPago = 'El pago no se completó. Puedes intentarlo de nuevo desde tu carrito.';
            }
            $token = $modelo->tokenPublicoDe($idPedido);
        }
    }
} else {
    // Acceso directo por GET (recarga de la página, enlace guardado): se muestra el
    // pedido si se acompaña del token público, sin tocar ningún estado.
    $idPedido = intval($_GET['id'] ?? 0);
    $token    = trim((string) ($_GET['t'] ?? ''));
    if ($idPedido <= 0 || $token === '') {
        $errorPago = 'Solicitud inválida.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pedido Confirmado — PuntoNet</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --primary:      #0284c7;
            --primary-dark: #0369a1;
            --primary-light:#f0f9ff;
            --surface:      #ffffff;
            --bg:           #f8fafc;
            --border:       #e2e8f0;
            --text:         #0f172a;
            --muted:        #64748b;
            --success:      #16a34a;
        }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* ─── Navbar (mismo patrón que V_mis_pedidos.php / V_cuenta.php) ── */
        .navbar { background: rgba(255,255,255,.95); box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .navbar-brand { font-weight: 800; color: var(--primary) !important; }

        .page-wrap { padding-top: 90px; padding-bottom: 60px; max-width: 780px; margin: 0 auto; }

        /* ─── Encabezado de confirmación ─────────────────── */
        .confirm-header { text-align: center; padding: 8px 16px 32px; }
        .confirm-icon {
            width: 76px; height: 76px; border-radius: 50%;
            background: #f0fdf4; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            animation: pop .5s cubic-bezier(.16,1,.3,1) both;
        }
        .confirm-icon i { font-size: 2.3rem; color: var(--success); }
        @keyframes pop {
            0%   { transform: scale(0); opacity: 0; }
            80%  { transform: scale(1.08); }
            100% { transform: scale(1); opacity: 1; }
        }
        .confirm-header h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 6px; }
        .confirm-header p { color: var(--muted); margin: 0; font-size: .95rem; }
        .confirm-header .pedido-ref { font-weight: 700; color: var(--text); }

        /* ─── Tarjetas ────────────────────────────────────── */
        .card-block {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 20px;
        }
        @media (max-width: 480px) { .card-block { padding: 20px; } }
        .card-block h2 {
            font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
            color: var(--muted); margin: 0 0 18px; display: flex; align-items: center; gap: 7px;
        }

        /* ─── Timeline de estado (pagado → preparado → entregado) ── */
        .status-steps { display: flex; align-items: flex-start; }
        .status-step { flex: 1; text-align: center; position: relative; }
        .status-step .dot {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--border); color: #fff;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 8px; font-size: .95rem; position: relative; z-index: 1;
        }
        .status-step.done .dot { background: var(--success); }
        .status-step.current .dot { background: var(--primary); box-shadow: 0 0 0 4px var(--primary-light); }
        .status-step .label { font-size: .74rem; font-weight: 600; color: var(--muted); }
        .status-step.done .label, .status-step.current .label { color: var(--text); }
        .status-step:not(:last-child)::after {
            content: '';
            position: absolute; top: 17px; left: 50%; width: 100%; height: 2px;
            background: var(--border); z-index: 0;
        }
        .status-step.done:not(:last-child)::after { background: var(--success); }
        .status-rejected {
            display: flex; align-items: flex-start; gap: 10px;
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
            padding: 14px 16px; font-size: .88rem; color: #7f1d1d;
        }

        /* ─── Info grid (cliente / entrega) ──────────────── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 480px) { .info-grid { grid-template-columns: 1fr; } }
        .info-item .info-label {
            font-size: .72rem; color: var(--muted); font-weight: 600;
            text-transform: uppercase; letter-spacing: .4px; margin-bottom: 3px;
        }
        .info-item .info-value { font-size: .92rem; font-weight: 600; }

        /* ─── Items de compra ─────────────────────────────── */
        .items-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        .items-table th {
            font-size: .72rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .4px;
            padding: 0 0 10px; border-bottom: 1px solid var(--border); text-align: left;
        }
        .items-table th:last-child, .items-table td:last-child { text-align: right; }
        .items-table td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .items-table tr:last-child td { border-bottom: none; }
        .item-name { font-weight: 600; }
        .item-sub { font-size: .78rem; color: var(--muted); }

        .totals-block { border-top: 2px solid var(--border); padding-top: 14px; margin-top: 8px; }
        .total-row { display: flex; justify-content: space-between; font-size: .88rem; color: var(--muted); margin-bottom: 8px; }
        .total-row.grand { font-size: 1.1rem; font-weight: 800; color: var(--text); margin-top: 6px; }
        .total-row.grand .amount { color: var(--primary); }

        /* ─── Aviso de entrega ────────────────────────────── */
        .delivery-box {
            display: flex; align-items: flex-start; gap: 12px;
            background: var(--primary-light); border-radius: 12px; padding: 16px 18px;
        }
        .delivery-box i { color: var(--primary); font-size: 1.25rem; flex-shrink: 0; margin-top: 1px; }
        .delivery-box p { margin: 0; font-size: .88rem; color: var(--primary-dark); font-weight: 500; }
        .delivery-box strong { color: var(--text); }

        /* ─── Botones ─────────────────────────────────────── */
        .action-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-main {
            flex: 1; min-width: 180px; padding: 13px;
            background: var(--primary); color: #fff; border: none; border-radius: 12px;
            font-weight: 700; font-size: .95rem; text-align: center; text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: background .2s, transform .15s;
        }
        .btn-main:hover { background: var(--primary-dark); color: #fff; transform: translateY(-1px); }
        .btn-secondary {
            padding: 13px 20px; background: #fff; border: 1.5px solid var(--border); border-radius: 12px;
            font-weight: 600; font-size: .95rem; color: var(--text); text-decoration: none;
            display: flex; align-items: center; gap: 7px; transition: border-color .2s, background .2s;
        }
        .btn-secondary:hover { border-color: var(--primary); background: var(--primary-light); color: var(--text); }

        .loading-placeholder { text-align: center; padding: 80px 20px; color: var(--muted); }

        @media print {
            .navbar, .action-row { display: none; }
            body { background: #fff; }
            .card-block { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2" href="../../store.php">
                <img src="../../assets/logo.svg" alt="PuntoNet" height="30">
                <span>PuntoNet</span>
            </a>
            <a href="../../store.php" class="btn btn-outline-primary rounded-pill btn-sm">
                <i class="bi bi-shop me-1"></i> Volver a la tienda
            </a>
        </div>
    </nav>

    <div class="page-wrap">
        <!-- Loading state -->
        <div class="loading-placeholder" id="loadingState">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p class="fw-semibold">Cargando tu comprobante...</p>
        </div>

        <!-- Content (hidden until loaded) -->
        <div id="ticketContent" style="display:none">

            <div class="confirm-header" id="confirmHeader">
                <div class="confirm-icon"><i class="bi bi-check-lg"></i></div>
                <h1 id="confirmTitulo">¡Gracias por tu compra!</h1>
                <p id="confirmSubtitulo">Tu pedido <span class="pedido-ref" id="ticketNum">#—</span> fue registrado correctamente.</p>
            </div>

            <!-- Estado del pedido -->
            <div class="card-block">
                <h2><i class="bi bi-signpost-split"></i> Estado de tu pedido</h2>
                <div id="statusStepsWrap"></div>
            </div>

            <!-- Entrega: no aplica si el pedido fue rechazado, no hay nada que entregar -->
            <div class="card-block" id="deliveryCard">
                <h2><i class="bi bi-geo-alt-fill"></i> Cómo vas a recibir tu pedido</h2>
                <div class="delivery-box" id="deliveryBox"></div>
            </div>

            <!-- Cliente + compra -->
            <div class="card-block">
                <h2><i class="bi bi-person-fill"></i> Datos del pedido</h2>
                <div class="info-grid mb-2">
                    <div class="info-item">
                        <div class="info-label">Cliente</div>
                        <div class="info-value" id="infoNombre">—</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">DNI</div>
                        <div class="info-value" id="infoDni">—</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value" id="infoTelefono">—</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Fecha de compra</div>
                        <div class="info-value" id="infoFecha">—</div>
                    </div>
                </div>
            </div>

            <!-- Detalle de compra -->
            <div class="card-block">
                <h2><i class="bi bi-bag-fill"></i> Detalle de compra</h2>
                <table class="items-table">
                    <thead>
                        <tr><th>Producto</th><th style="text-align:center">Cant.</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody id="itemsBody"><!-- filled by JS --></tbody>
                </table>
                <div class="totals-block">
                    <div class="total-row">
                        <span>Subtotal</span>
                        <span id="totalSubtotal">—</span>
                    </div>
                    <div class="total-row">
                        <span>Envío / Recojo</span>
                        <span style="color:var(--success);font-weight:700">GRATIS</span>
                    </div>
                    <div class="total-row grand">
                        <span id="totalLabel">Total Pagado</span>
                        <span class="amount" id="totalGrand">—</span>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="action-row">
                <a href="V_mis_pedidos.php" class="btn-main">
                    <i class="bi bi-receipt"></i> Ver mis pedidos
                </a>
                <a href="../../store.php" class="btn-secondary">
                    <i class="bi bi-shop"></i> Seguir comprando
                </a>
                <button class="btn-secondary" onclick="window.print()">
                    <i class="bi bi-printer-fill"></i> Imprimir
                </button>
            </div>

        </div><!-- /ticketContent -->

        <!-- Error state -->
        <div id="errorState" style="display:none;">
            <div class="card-block text-center py-5">
                <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
                <h2 class="mt-3 fw-bold" id="errorTitulo" style="text-transform:none;letter-spacing:normal;font-size:1.3rem;">Pedido no encontrado</h2>
                <p class="text-muted" id="errorMensaje">No pudimos recuperar los datos de tu pedido.</p>
                <a href="../../store.php" class="btn btn-primary rounded-pill px-4 mt-2">Volver a la Tienda</a>
            </div>
        </div>

    </div><!-- /page-wrap -->

    <script>
    (async function() {
        // El pago ya se validó y confirmó en servidor (ver la cabecera PHP de este
        // archivo). Aquí solo queda pintar el comprobante.
        const id_pedido = <?php echo (int) $idPedido; ?>;
        const token     = <?php echo json_encode($token); ?>;
        const errorPago = <?php echo json_encode($errorPago); ?>;

        if (errorPago) {
            showError(errorPago);
            return;
        }
        if (!id_pedido || !token) {
            showError();
            return;
        }

        try {
            // Traer el comprobante (requiere el token público del pedido).
            const res  = await fetch(`../../controllers/C_Ecommerce.php?action=get_pedido&id=${id_pedido}&t=${encodeURIComponent(token)}`);
            const json = await res.json();

            if (!json.success || !json.data) {
                showError();
                return;
            }

            render(json.data);
        } catch (e) {
            showError();
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
        }

        // Mismos 3 hitos que usa el panel admin y "Mis Pedidos": 1 pagado, 5
        // preparado, 2 entregado. El texto del paso "preparado" cambia según la
        // modalidad, igual que en V_pedidos_online.php / V_mis_pedidos.php.
        function renderSteps(estado, esColegio) {
            const pasos = [
                { clave: 1, icono: 'bi-credit-card-2-front-fill', label: 'Pagado' },
                { clave: 5, icono: 'bi-box-seam-fill', label: esColegio ? 'Preparado para envío' : 'Listo para recoger' },
                { clave: 2, icono: 'bi-check-circle-fill', label: esColegio ? 'Entregado al estudiante' : 'Entregado' },
            ];
            const alcanzado = { 1: 1, 5: 2, 2: 3 }[estado] || 1;

            return pasos.map((p, i) => {
                const idx = i + 1;
                const cls = idx < alcanzado ? 'done' : (idx === alcanzado ? 'current' : '');
                const icono = idx <= alcanzado ? p.icono : 'bi-circle';
                return `
                    <div class="status-step ${cls}">
                        <div class="dot"><i class="bi ${icono}"></i></div>
                        <div class="label">${p.label}</div>
                    </div>
                `;
            }).join('');
        }

        function render(d) {
            const esColegio = parseInt(d.tipo_entrega) === 2;
            const estado = parseInt(d.estado);

            document.getElementById('ticketNum').textContent = '#' + String(d.id_pedido).padStart(6, '0');

            // El encabezado y el total cambian de tono si el pedido se rechazó: no hay
            // nada que agradecer ni "total pagado" que mostrar como si fuera a entregarse.
            const iconoEl = document.querySelector('.confirm-icon');
            if (estado === 0) {
                iconoEl.style.background = '#fef2f2';
                iconoEl.innerHTML = '<i class="bi bi-x-lg" style="color:#dc2626"></i>';
                document.getElementById('confirmTitulo').textContent = 'Tu pedido no pudo procesarse';
                document.getElementById('confirmSubtitulo').innerHTML =
                    `El pedido <span class="pedido-ref">#${String(d.id_pedido).padStart(6, '0')}</span> fue rechazado.`;
                document.getElementById('totalLabel').textContent = 'Monto a devolver';
            } else if (estado === 3 || estado === 4) {
                iconoEl.style.background = '#fefce8';
                iconoEl.innerHTML = '<i class="bi bi-hourglass-split" style="color:#a16207"></i>';
                document.getElementById('confirmTitulo').textContent = 'Verificando tu pago';
                document.getElementById('confirmSubtitulo').textContent = 'Estamos confirmando tu pago con la pasarela.';
            }

            // La caja de entrega no aplica si el pedido no se va a entregar.
            document.getElementById('deliveryCard').style.display = (estado === 0) ? 'none' : '';

            // Cliente
            const fullName = [d.nombres_razon_social, d.apellidos].filter(Boolean).join(' ');
            document.getElementById('infoNombre').textContent   = fullName || '—';
            document.getElementById('infoDni').textContent      = d.numero_documento || '—';
            document.getElementById('infoTelefono').textContent = d.telefono || '—';
            document.getElementById('infoFecha').textContent    = formatFecha(d.fecha_pedido);

            // Estado: pasos normales para 1/5/2, aviso especial para rechazado (0) y
            // "verificando" mientras el pago aún no se confirma (3/4).
            const stepsWrap = document.getElementById('statusStepsWrap');
            if (estado === 0) {
                stepsWrap.innerHTML = `
                    <div class="status-rejected">
                        <i class="bi bi-x-circle-fill fs-5"></i>
                        <div>
                            <strong>Tu pedido fue rechazado.</strong>
                            ${d.motivo_rechazo ? `<div class="mt-1">${escapeHtml(d.motivo_rechazo)}</div>` : ''}
                            <div class="mt-1">Revisa tu correo para coordinar la devolución de tu dinero.</div>
                        </div>
                    </div>
                `;
            } else if (estado === 3 || estado === 4) {
                stepsWrap.innerHTML = `
                    <div class="d-flex align-items-center gap-2 text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                        <span>Verificando tu pago con la pasarela...</span>
                    </div>
                `;
            } else {
                stepsWrap.innerHTML = `<div class="status-steps">${renderSteps(estado, esColegio)}</div>`;
            }

            // Entrega
            const deliveryBox = document.getElementById('deliveryBox');
            if (esColegio) {
                deliveryBox.innerHTML = `
                    <i class="bi bi-mortarboard-fill"></i>
                    <p><strong>Entrega en el colegio</strong><br>
                    Se entregará a <strong>${escapeHtml(d.estudiante_nombre)}</strong>
                    ${d.nivel_nombre ? ` (${escapeHtml(d.nivel_nombre)} ${escapeHtml(d.grado_nombre || '')})` : ''}
                    una vez que el pedido esté preparado.
                    ${d.observaciones ? `<br><span class="text-muted">${escapeHtml(d.observaciones)}</span>` : ''}</p>
                `;
            } else {
                deliveryBox.innerHTML = `
                    <i class="bi bi-shop"></i>
                    <p><strong>Recojo en tienda</strong><br>
                    Acércate a las instalaciones de <strong>PuntoNet</strong> con tu número de pedido una vez que
                    te avisemos que está listo.
                    ${d.observaciones ? `<br><span class="text-muted">${escapeHtml(d.observaciones)}</span>` : ''}</p>
                `;
            }

            // Items
            const tbody = document.getElementById('itemsBody');
            tbody.innerHTML = (d.detalles || []).map(item => `
                <tr>
                    <td>
                        <div class="item-name">${escapeHtml(item.nombre)}</div>
                        <div class="item-sub">S/ ${parseFloat(item.precio_unitario).toFixed(2)} / ${escapeHtml(item.abreviatura)}</div>
                    </td>
                    <td style="text-align:center">${item.cantidad}</td>
                    <td style="text-align:right;font-weight:700">S/ ${parseFloat(item.subtotal).toFixed(2)}</td>
                </tr>
            `).join('');

            const total = parseFloat(d.total || 0);
            const fmt   = n => `S/ ${n.toFixed(2)}`;
            document.getElementById('totalSubtotal').textContent = fmt(total);
            document.getElementById('totalGrand').textContent    = fmt(total);

            document.getElementById('loadingState').style.display  = 'none';
            document.getElementById('ticketContent').style.display = 'block';

            // Clear cart from session after successful display
            sessionStorage.removeItem('puntonet_cart');
        }

        function showError(mensaje) {
            if (mensaje) {
                document.getElementById('errorTitulo').textContent  = 'No pudimos completar tu pago';
                document.getElementById('errorMensaje').textContent = mensaje;
            }
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('errorState').style.display   = 'block';
        }

        function formatFecha(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString('es-PE', {
                day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        }
    })();
    </script>

</body>
</html>
