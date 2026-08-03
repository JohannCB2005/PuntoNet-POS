<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado — PuntoNet</title>
    <link rel="icon" type="image/png" href="../../assets/Logo navegador PuntoNet.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --primary:      #0284c7;
            --primary-dark: #0369a1;
            --surface:      #ffffff;
            --bg:           #f8fafc;
            --border:       #e2e8f0;
            --text:         #0f172a;
            --muted:        #64748b;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
        }
        /* ─── Success Check Animation ──────────────────── */
        .check-circle {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pop .5s cubic-bezier(.16,1,.3,1) both;
        }
        .check-circle i {
            font-size: 2.8rem;
            color: var(--primary);
            animation: fadeIn .4s .3s both;
        }
        @keyframes pop {
            0%   { transform: scale(0); opacity: 0; }
            80%  { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.5); }
            to   { opacity: 1; transform: scale(1); }
        }

        /* ─── Card ─────────────────────────────────────── */
        .ticket-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 36px 40px;
            max-width: 620px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,.07);
            animation: slideUp .5s .1s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        @media (max-width: 480px) { .ticket-card { padding: 24px 20px; } }

        /* ─── Title ─────────────────────────────────────── */
        .ticket-title {
            text-align: center;
            margin-bottom: 28px;
        }
        .ticket-title h1 {
            font-size: 1.55rem;
            font-weight: 800;
            margin: 0 0 6px;
        }
        .ticket-title p {
            font-size: 0.9rem;
            color: var(--muted);
            margin: 0;
        }

        /* ─── Ticket Number Badge ───────────────────────── */
        .ticket-number {
            background: var(--primary-light);
            border: 2px dashed var(--primary);
            border-radius: 14px;
            padding: 14px 20px;
            text-align: center;
            margin-bottom: 28px;
        }
        .ticket-number .label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: .6px;
        }
        .ticket-number .number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
            letter-spacing: 2px;
        }

        /* ─── Info Grid ─────────────────────────────────── */
        .info-section {
            margin-bottom: 24px;
        }
        .info-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        @media (max-width: 420px) { .info-grid { grid-template-columns: 1fr; } }
        .info-item .info-label {
            font-size: 0.73rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 3px;
        }
        .info-item .info-value {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text);
        }

        /* ─── Divider ───────────────────────────────────── */
        .co-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }
        /* Dashed divider (ticket tear) */
        .ticket-tear {
            border: none;
            border-top: 2px dashed var(--border);
            margin: 20px 0;
        }

        /* ─── Items Table ───────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .items-table th {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 0 0 10px;
            border-bottom: 1px solid var(--border);
        }
        .items-table th:last-child, .items-table td:last-child { text-align: right; }
        .items-table td {
            padding: 10px 0;
            border-bottom: 1px dashed #f0f0f0;
            vertical-align: top;
        }
        .items-table tr:last-child td { border-bottom: none; }
        .item-name { font-weight: 600; }
        .item-sub  { font-size: 0.78rem; color: var(--muted); }

        /* ─── Totals ─────────────────────────────────────── */
        .totals-block {
            border-top: 2px solid var(--border);
            padding-top: 14px;
            margin-top: 8px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .total-row.grand {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text);
            margin-top: 6px;
        }
        .total-row.grand .amount { color: var(--primary); }

        /* ─── Status Badge ───────────────────────────────── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fefce8;
            border: 1px solid #fde047;
            color: #854d0e;
            font-size: 0.83rem;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 30px;
        }

        /* ─── Pickup notice ─────────────────────────────── */
        .pickup-box {
            background: var(--primary-light);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 28px;
        }
        .pickup-box i { color: var(--primary); font-size: 1.3rem; flex-shrink: 0; margin-top: 1px; }
        .pickup-box p { margin: 0; font-size: 0.88rem; color: var(--primary-dark); font-weight: 500; }

        /* ─── Action Buttons ─────────────────────────────── */
        .action-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-home {
            flex: 1;
            padding: 13px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: background .2s, transform .15s;
            box-shadow: 0 4px 12px rgba(21,128,61,.25);
        }
        .btn-home:hover { background: var(--primary-dark); transform: translateY(-1px); color: #fff; }

        .btn-print {
            padding: 13px 20px;
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 7px;
            transition: border-color .2s, background .2s;
        }
        .btn-print:hover { border-color: var(--primary); background: var(--primary-light); }

        /* ─── Loading state ─────────────────────────────── */
        .loading-placeholder {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        /* ─── Print only ─────────────────────────────────── */
        @media print {
            body { background: white; padding: 0; }
            .ticket-card { box-shadow: none; border: none; max-width: 100%; }
            .action-row, .pickup-box { display: none; }
        }
    </style>
</head>
<body>

    <div class="ticket-card" id="ticketCard">
        <!-- Loading state -->
        <div class="loading-placeholder" id="loadingState">
            <div class="spinner-border text-success mb-3" role="status"></div>
            <p class="fw-semibold">Cargando tu comprobante...</p>
        </div>

        <!-- Content (hidden until loaded) -->
        <div id="ticketContent" style="display:none">

            <!-- Check animation -->
            <div class="check-circle">
                <i class="bi bi-check-lg"></i>
            </div>

            <div class="ticket-title">
                <h1>¡Pedido Confirmado!</h1>
                <p>Tu pedido ha sido registrado y está pendiente de procesamiento.</p>
            </div>

            <!-- Ticket number -->
            <div class="ticket-number">
                <div class="label">Número de Ticket</div>
                <div class="number" id="ticketNum">#—</div>
            </div>

            <!-- Customer info -->
            <div class="info-section">
                <div class="info-section-title">
                    <i class="bi bi-person-fill"></i> Datos del Cliente
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nombre</div>
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
                        <div class="info-label">Fecha y Hora</div>
                        <div class="info-value" id="infoFecha">—</div>
                    </div>
                </div>
            </div>

            <hr class="ticket-tear">

            <!-- Items -->
            <div class="info-section">
                <div class="info-section-title">
                    <i class="bi bi-bag-fill"></i> Detalle de Compra
                </div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="text-align:center">Cant.</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- filled by JS -->
                    </tbody>
                </table>

                <div class="totals-block">
                    <div class="total-row">
                        <span>Subtotal</span>
                        <span id="totalSubtotal">—</span>
                    </div>
                    <div class="total-row">
                        <span>Envío / Recojo</span>
                        <span style="color:#0284c7;font-weight:700">GRATIS</span>
                    </div>
                    <div class="total-row grand">
                        <span>Total Pagado</span>
                        <span class="amount" id="totalGrand">—</span>
                    </div>
                </div>
            </div>

            <hr class="co-divider">

            <!-- Status -->
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <div>
                    <div class="info-label mb-1" style="font-size:.73rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px">Estado del pedido</div>
                    <span class="status-badge">
                        <i class="bi bi-hourglass-split"></i>
                        Pendiente de Procesamiento
                    </span>
                </div>
                <div>
                    <div class="info-label mb-1" style="font-size:.73rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px">Método de Pago</div>
                    <div style="font-size:.9rem;font-weight:600;">
                        <i class="bi bi-credit-card-fill text-primary"></i> Stripe
                    </div>
                </div>
            </div>

            <!-- Pickup notice -->
            <div class="pickup-box">
                <i class="bi bi-geo-alt-fill"></i>
                <p><strong>¿Cómo recoger tu pedido?</strong><br>
                Acércate a las instalaciones de <strong>PuntoNet</strong> con tu número de ticket. El personal verificará tu compra y hará entrega de tus productos.</p>
            </div>

            <!-- Actions -->
            <div class="action-row">
                <a href="../../tienda.php" class="btn-home">
                    <i class="bi bi-shop"></i> Volver a la Tienda
                </a>
                <button class="btn-print" onclick="window.print()">
                    <i class="bi bi-printer-fill"></i> Imprimir
                </button>
            </div>

        </div><!-- /ticketContent -->

        <!-- Error state -->
        <div id="errorState" style="display:none; text-align:center; padding:40px 20px;">
            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
            <h2 class="mt-3 fw-bold">Pedido no encontrado</h2>
            <p class="text-muted">No pudimos recuperar los datos de tu pedido.</p>
            <a href="../../tienda.php" class="btn btn-success rounded-pill px-4 mt-2">Volver a la Tienda</a>
        </div>

    </div><!-- /ticket-card -->

    <script>
    (async function() {
        // Stripe agrega ?payment_intent=...&redirect_status=... al volver del pago.
        const params      = new URLSearchParams(window.location.search);
        const id_pedido   = parseInt(params.get('id') || '0');
        const token       = params.get('t') || '';
        const paymentIntentId = params.get('payment_intent') || '';
        const redirectStatus  = params.get('redirect_status') || '';

        if (!id_pedido || !token) {
            showError();
            return;
        }

        if (redirectStatus === 'failed' || redirectStatus === 'canceled') {
            showError();
            return;
        }

        try {
            // 1. Verificar el pago contra Stripe y confirmar el pedido (idempotente).
            if (paymentIntentId) {
                await fetch('../../controllers/C_PaymentIntent.php?action=confirmar', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ id_pedido, payment_intent_id: paymentIntentId }),
                });
            }

            // 2. Traer el comprobante (requiere el token público del pedido).
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
            return String(str).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
        }

        function render(d) {
            // Ticket number (zero-padded)
            document.getElementById('ticketNum').textContent = '#' + String(d.id_pedido).padStart(6, '0');

            // Customer
            const fullName = [d.nombres_razon_social, d.apellidos].filter(Boolean).join(' ');
            document.getElementById('infoNombre').textContent   = fullName || '—';
            document.getElementById('infoDni').textContent      = d.numero_documento || '—';
            document.getElementById('infoTelefono').textContent = d.telefono || '—';
            document.getElementById('infoFecha').textContent    = formatFecha(d.fecha_pedido);

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

            // Estado real del pedido: 1/2 = pagado, 3 = aún verificando, 0/4 = no confirmado
            const estado = parseInt(d.estado);
            const badge = document.querySelector('.status-badge');
            if (estado === 1 || estado === 2) {
                badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> Pago Confirmado';
            } else if (estado === 3) {
                badge.innerHTML = '<i class="bi bi-hourglass-split"></i> Verificando Pago...';
            } else {
                badge.innerHTML = '<i class="bi bi-x-circle-fill"></i> Pago no confirmado';
            }

            // Show content
            document.getElementById('loadingState').style.display  = 'none';
            document.getElementById('ticketContent').style.display = 'block';

            // Clear cart from session after successful display
            sessionStorage.removeItem('puntonet_cart');
        }

        function showError() {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('errorState').style.display   = 'block';
        }

        function formatFecha(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString('es-PE', {
                day:    '2-digit',
                month:  'long',
                year:   'numeric',
                hour:   '2-digit',
                minute: '2-digit',
            });
        }
    })();
    </script>

</body>
</html>
