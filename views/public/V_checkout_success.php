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
require_once dirname(__DIR__, 2) . '/config/sesion_segura.php';
session_start();
// Nunca cachear esta página: refleja el estado en vivo del pago y contiene el JS
// de monitoreo (SSE + polling). Si un proxy o el navegador sirvieran una copia
// vieja, el cliente vería "Verificando..." para siempre aunque el pago ya se
// haya confirmado — lo que pasaba antes: solo se arreglaba con F5.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
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
    <title>Pedido Confirmado — NISSI</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/favicon-nissi.svg?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Tema NISSI -->
    <link rel="stylesheet" href="../../assets/css/tienda.css?v=4">
    <?php require_once dirname(__DIR__, 2) . '/config/marca.php'; echo marcaCss(); ?>

    <style>
        body { background: var(--paper); color: var(--ink); }

        /* ─── Navbar (mismo patrón que el resto de la tienda) ── */
        .navbar { background: rgba(255,255,255,.9); backdrop-filter: blur(14px); }
        .navbar-brand { font-family: var(--font-display); font-weight: 800; color: var(--navy) !important; letter-spacing: .02em; }
        .navbar-brand em { font-style: normal; color: var(--accent); }
        @media (max-width: 576px) { .nav-label { display: none; } }

        .page-wrap { padding-top: 96px; padding-bottom: 60px; max-width: 780px; margin: 0 auto; }

        /* ─── Encabezado de confirmación ─────────────────── */
        .confirm-header { text-align: center; padding: 8px 16px 34px; }
        .confirm-icon {
            width: 84px; height: 84px; border-radius: 50%;
            background: var(--sage-100); display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 12px 30px rgba(46,107,79,.25);
            animation: n-pop .55s cubic-bezier(.16,1,.3,1) both;
        }
        .confirm-icon i { font-size: 2.5rem; color: var(--sage); }
        .confirm-header h1 {
            font-family: var(--font-display); font-weight: 700;
            font-size: clamp(1.6rem, 4vw, 2.1rem); margin: 0 0 8px; color: var(--navy);
            letter-spacing: -.01em;
        }
        .confirm-header p { color: var(--muted); margin: 0; font-size: .95rem; }
        .confirm-header .pedido-ref { font-weight: 700; color: var(--accent); }
        .confirm-kicker { margin-bottom: 14px; }

        /* ─── Tarjetas ────────────────────────────────────── */
        .card-block {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 24px 28px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        @media (max-width: 480px) { .card-block { padding: 20px; } }
        .card-block h2 {
            font-family: var(--font-body);
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .14em;
            color: var(--muted); margin: 0 0 18px; display: flex; align-items: center; gap: 8px;
        }
        .card-block h2 i { color: var(--accent); }

        /* ─── Timeline de estado (pagado → preparado → entregado) ── */
        .status-steps { display: flex; align-items: flex-start; }
        .status-step { flex: 1; text-align: center; position: relative; }
        .status-step .dot {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--paper-3); color: var(--muted);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 8px; font-size: .95rem; position: relative; z-index: 1;
            border: 1.5px solid var(--line-strong);
        }
        .status-step.done .dot { background: var(--sage); border-color: var(--sage); color: #fff; }
        .status-step.current .dot { background: var(--navy); border-color: var(--navy); color: #fff; box-shadow: 0 0 0 5px rgba(var(--navy-rgb),.12); }
        .status-step .label { font-size: .74rem; font-weight: 600; color: var(--muted); }
        .status-step.done .label, .status-step.current .label { color: var(--ink); }
        .status-step:not(:last-child)::after {
            content: '';
            position: absolute; top: 18px; left: 50%; width: 100%; height: 2px;
            background: var(--line-strong); z-index: 0;
        }
        .status-step.done:not(:last-child)::after { background: var(--sage); }
        .status-rejected {
            display: flex; align-items: flex-start; gap: 10px;
            background: var(--danger-100); border: 1px solid #f5d6d9; border-radius: var(--radius-sm);
            padding: 14px 16px; font-size: .88rem; color: #9e121b;
        }

        /* ─── Info grid (cliente / entrega) ──────────────── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 480px) { .info-grid { grid-template-columns: 1fr; } }
        .info-item .info-label {
            font-size: .68rem; color: var(--muted); font-weight: 700;
            text-transform: uppercase; letter-spacing: .12em; margin-bottom: 3px;
        }
        .info-item .info-value { font-size: .94rem; font-weight: 600; }

        /* Código de confirmación del pedido */
        .confirm-codigo {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            background: var(--paper); border: 2px dashed var(--accent); border-radius: 12px;
            padding: 12px 16px; margin-top: 14px;
        }
        .confirm-codigo-label { font-size: .68rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .12em; }
        .confirm-codigo-value { font-family: var(--font-display); font-weight: 800; font-size: 1.6rem; letter-spacing: .16em; color: var(--navy); }

        /* ─── Items de compra ─────────────────────────────── */
        .items-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .items-table th {
            font-size: .7rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .12em;
            padding: 0 0 10px; border-bottom: 1px solid var(--line-strong); text-align: left;
        }
        .items-table th:last-child, .items-table td:last-child { text-align: right; }
        .items-table td { padding: 11px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        .items-table tr:last-child td { border-bottom: none; }
        .item-name { font-weight: 600; }
        .item-sub { font-size: .78rem; color: var(--muted); }

        .totals-block { border-top: 2px solid var(--line-strong); padding-top: 14px; margin-top: 8px; }
        .total-row { display: flex; justify-content: space-between; font-size: .9rem; color: var(--muted); margin-bottom: 8px; }
        .total-row.grand {
            font-family: var(--font-display);
            font-size: 1.2rem; font-weight: 700; color: var(--ink); margin-top: 8px;
        }
        .total-row.grand .amount { color: var(--accent); }

        /* ─── Aviso de entrega ────────────────────────────── */
        .delivery-box {
            display: flex; align-items: flex-start; gap: 12px;
            background: var(--paper-2); border: 1px solid var(--line);
            border-radius: var(--radius-sm); padding: 16px 18px;
        }
        .delivery-box i { color: var(--accent); font-size: 1.25rem; flex-shrink: 0; margin-top: 1px; }
        .delivery-box p { margin: 0; font-size: .88rem; color: var(--ink-soft); }
        .delivery-box strong { color: var(--ink); }

        /* ─── Botones ─────────────────────────────────────── */
        .action-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-main {
            flex: 1; min-width: 180px; padding: 13px;
            background: var(--navy); color: #fff; border: none; border-radius: 999px;
            font-weight: 700; font-size: .95rem; text-align: center; text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: background .2s, transform .15s, box-shadow .2s;
        }
        .btn-main:hover { background: var(--navy-700); color: #fff; transform: translateY(-1px); box-shadow: 0 8px 18px rgba(var(--navy-rgb),.26); }
        .btn-secondary {
            padding: 13px 20px; background: var(--card); border: 1.5px solid var(--line-strong); border-radius: 999px;
            font-weight: 600; font-size: .95rem; color: var(--ink); text-decoration: none;
            display: flex; align-items: center; gap: 7px; transition: border-color .2s, background .2s, color .2s;
        }
        .btn-secondary:hover { border-color: var(--navy); background: var(--paper-2); color: var(--navy); }

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
            <a class="navbar-brand d-flex align-items-center gap-2" href="/tienda">
                <span>NISSI<em>.</em></span>
            </a>
            <a href="/tienda" class="btn btn-outline-primary rounded-pill btn-sm">
                <i class="bi bi-shop"></i><span class="nav-label"> Volver a la tienda</span>
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
                <div class="n-kicker confirm-kicker">Comprobante de compra</div>
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
                <!-- Código de confirmación (si el switch de la tienda está activo) -->
                <div id="codigoConfirmacionBox" class="confirm-codigo" style="display:none;">
                    <div>
                        <div class="confirm-codigo-label">Código de confirmación</div>
                        <div class="confirm-codigo-value" id="infoCodigoConfirmacion">—</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="copiarCodigoSuccess(this, document.getElementById('infoCodigoConfirmacion').textContent)" title="Copiar código">
                        <i class="bi bi-clipboard"></i>
                    </button>
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
                <a href="/mis-pedidos" class="btn-main">
                    <i class="bi bi-receipt"></i> Ver mis pedidos
                </a>
                <a href="/tienda" class="btn-secondary">
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
                <a href="/tienda" class="btn btn-primary rounded-pill px-4 mt-2">Volver a la Tienda</a>
            </div>
        </div>

    </div><!-- /page-wrap -->

    <script>
    (async function() {
        // El pago se confirma en servidor vía el webhook (TAYPI) o la IPN (Izipay).
        // Esta página pinta el comprobante y, si el pedido aún figura "verificando"
        // (estado 3/4), consulta de nuevo cada pocos segundos hasta que la pasarela
        // confirme el cobro en background — sin obligar al cliente a recargar.
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

        let pollTimer = null;
        let pintado   = false;
        let source    = null;

        // Carga el comprobante una vez. Si sigue "verificando", se queda esperando:
        // primero via SSE (notificación en tiempo real) y, si SSE no está disponible
        // o se corta, con polling cada 4 s. Nunca se recarga la página.
        async function cargarPedido() {
            // Mientras el pedido esté pendiente y se haya cobrado por QR de TAYPI, se
            // consulta TAYPI directamente (verificar_pago): el webhook puede tardar
            // ~50 s y no queremos que el cliente espere tanto para ver su pago
            // confirmado. El webhook queda como respaldo/fuente de verdad.
            // Idempotente: si ya está pagado, no hace nada.
            // Los pedidos por verificación manual (billetera/transferencia) NO tienen
            // payment_id_taypi: nunca se consulta la pasarela por ellos.
            const pedido = await getPedido();
            const datosActuales = pedido.data || {};
            const estadoActual  = parseInt(datosActuales.estado);
            if ((estadoActual === 3 || estadoActual === 4) && datosActuales.payment_id_taypi) {
                await fetch(`../../controllers/C_Taypi.php?action=verificar_pago&id=${id_pedido}&t=${encodeURIComponent(token)}`, { cache: 'no-store' })
                    .then(r => r.json())
                    .catch(() => null);
            }
            const json = await getPedido();
            if (!json.success || !json.data) {
                if (!pintado) showError();
                return;
            }
            const estado = parseInt(json.data.estado);
            render(json.data);

            if (estado === 3 || estado === 4) {
                if (!source) iniciarSSE();
            } else {
                detenerMonitoreo();
            }
        }

        async function getPedido() {
            const res  = await fetch(`../../controllers/C_Ecommerce.php?action=get_pedido&id=${id_pedido}&t=${encodeURIComponent(token)}`, {
                cache: 'no-store',
            });
            return res.json();
        }

        function copiarCodigoSuccess(btn, texto) {
            const icono = btn.querySelector('i');
            const avisar = () => {
                btn.innerHTML = '<i class="bi bi-check-lg text-success"></i> Copiado';
                setTimeout(() => { btn.innerHTML = icono.outerHTML; }, 1800);
            };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(texto).then(avisar).catch(() => {
                    const ta = document.createElement('textarea');
                    ta.value = texto; ta.style.position = 'fixed'; ta.style.opacity = '0';
                    document.body.appendChild(ta); ta.select();
                    try { if (document.execCommand('copy')) avisar(); } catch (e) {}
                    document.body.removeChild(ta);
                });
            }
        }

        function iniciarSSE() {
            if (!('EventSource' in window)) return;
            try {
                source = new EventSource(`../../controllers/C_PagoStatus.php?id=${id_pedido}&t=${encodeURIComponent(token)}&_=${Date.now()}`);
            } catch (e) {
                iniciarPolling();
                return;
            }

            source.addEventListener('pago_confirmado', (ev) => {
                try {
                    const data = JSON.parse(ev.data);
                    if (data && data.estado !== undefined) {
                        render(data);
                    }
                } catch (e) { /* payload inválido: el polling lo corregirá */ }
                detenerMonitoreo();
            });

            source.onerror = () => {
                // SSE cortado o agotado el tiempo (timeout): se cae al polling.
                if (source) { source.close(); source = null; }
                iniciarPolling();
            };
        }

        function iniciarPolling() {
            if (pollTimer) return;
            pollTimer = setInterval(async () => {
                try { await cargarPedido(); } catch (e) { /* reintenta en el siguiente tick */ }
            }, 4000);
        }

        function detenerMonitoreo() {
            if (source) { source.close(); source = null; }
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }

        try {
            await cargarPedido();
        } catch (e) {
            if (!pintado) showError();
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
            pintado = true;

            document.getElementById('ticketNum').textContent = '#' + String(d.id_pedido).padStart(6, '0');

            // El encabezado y el total cambian de tono si el pedido se rechazó: no hay
            // nada que agradecer ni "total pagado" que mostrar como si fuera a entregarse.
            const iconoEl = document.querySelector('.confirm-icon');
            if (estado === 0) {
                iconoEl.style.background = 'var(--danger-100)';
                iconoEl.innerHTML = '<i class="bi bi-x-lg" style="color:var(--danger)"></i>';
                document.getElementById('confirmTitulo').textContent = 'Tu pedido no pudo procesarse';
                document.getElementById('confirmSubtitulo').innerHTML =
                    `El pedido <span class="pedido-ref">#${String(d.id_pedido).padStart(6, '0')}</span> fue rechazado.`;
                document.getElementById('totalLabel').textContent = 'Monto a devolver';
            } else if (estado === 6) {
                // Pago por verificación manual reportado: no hay pasarela que confirmar,
                // el admin lo aprueba en "Pedidos Online". Estado estático (no hace poll).
                iconoEl.style.background = 'var(--amber-100)';
                iconoEl.innerHTML = '<i class="bi bi-shield-check" style="color:var(--amber)"></i>';
                document.getElementById('confirmTitulo').textContent = 'Pago enviado para verificación';
                document.getElementById('confirmSubtitulo').innerHTML =
                    `Tu pedido <span class="pedido-ref">#${String(d.id_pedido).padStart(6, '0')}</span> está en revisión.`;
                document.getElementById('totalLabel').textContent = 'Total a pagar';
            } else if (estado === 3 || estado === 4) {
                iconoEl.style.background = 'var(--amber-100)';
                iconoEl.innerHTML = '<i class="bi bi-hourglass-split" style="color:var(--amber)"></i>';
                document.getElementById('confirmTitulo').textContent = 'Verificando tu pago';
                document.getElementById('confirmSubtitulo').textContent = 'Estamos confirmando tu pago con la pasarela.';
            } else {
                // Confirmado (1 pagado, 5 preparado, 2 entregado): se restaura el
                // encabezado de éxito por si antes se pintó el de "verificando".
                iconoEl.style.background = 'var(--sage-100)';
                iconoEl.innerHTML = '<i class="bi bi-check-lg" style="color:var(--sage-700)"></i>';
                document.getElementById('confirmTitulo').textContent = '¡Gracias por tu compra!';
                document.getElementById('confirmSubtitulo').innerHTML =
                    `Tu pedido <span class="pedido-ref">#${String(d.id_pedido).padStart(6, '0')}</span> fue registrado correctamente.`;
                document.getElementById('totalLabel').textContent = 'Total Pagado';
            }

            // La caja de entrega no aplica si el pedido no se va a entregar.
            document.getElementById('deliveryCard').style.display = (estado === 0) ? 'none' : '';

            // Cliente
            const fullName = [d.nombres_razon_social, d.apellidos].filter(Boolean).join(' ');
            document.getElementById('infoNombre').textContent   = fullName || '—';
            document.getElementById('infoDni').textContent      = d.numero_documento || '—';
            document.getElementById('infoTelefono').textContent = d.telefono || '—';
            document.getElementById('infoFecha').textContent    = formatFecha(d.fecha_pedido);

            // Código de confirmación (4 dígitos): solo si el switch de la tienda
            // está activo (llega desde el backend; null si está desactivado).
            const codigoBox = document.getElementById('codigoConfirmacionBox');
            if (codigoBox) {
                const codigo = d.codigo_confirmacion || '';
                codigoBox.style.display = codigo ? '' : 'none';
                document.getElementById('infoCodigoConfirmacion').textContent = codigo || '—';
            }

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
            } else if (estado === 6) {
                stepsWrap.innerHTML = `
                    <div class="status-rejected">
                        <i class="bi bi-shield-check fs-5"></i>
                        <div>
                            <strong>Recibimos tu pago. Está en verificación manual.</strong>
                            <div class="mt-1">Un administrador lo aprobará en breve y te avisaremos por correo.</div>
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
                const recoge = (d.quien_recoge === 'otra' && (d.recoge_nombre || d.recoge_dni))
                    ? `Lo recogerá <strong>${escapeHtml(d.recoge_nombre || '—')}</strong>${d.recoge_dni ? ` (DNI ${escapeHtml(d.recoge_dni)})` : ''}.`
                    : (d.recoge_nombre ? `Lo recogerá <strong>${escapeHtml(d.recoge_nombre)}</strong>${d.recoge_dni ? ` (DNI ${escapeHtml(d.recoge_dni)})` : ''}.` : null);
                deliveryBox.innerHTML = `
                    <i class="bi bi-shop"></i>
                    <p><strong>Recojo en tienda</strong><br>
                    Acércate a las instalaciones de <strong>NISSI</strong> con tu número de pedido una vez que
                    te avisemos que está listo.
                    ${recoge ? `<br>${recoge}` : ''}
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
            sessionStorage.removeItem('nissi_cart');
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
