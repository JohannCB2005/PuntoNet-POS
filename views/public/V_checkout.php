<?php
require_once dirname(dirname(__DIR__)) . '/config/stripe.php';
$stripePublicKey = STRIPE_PK;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Seguro — NISSI STORE</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/Logo navegador PuntoNet.png">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/"></script>

    <style>
        :root {
            --primary:       #0284c7;
            --primary-dark:  #0369a1;
            --primary-light: #f0f9ff;
            --surface:       #ffffff;
            --bg:            #f1f5f9;
            --border:        #e2e8f0;
            --text:          #0f172a;
            --muted:         #64748b;
            --success:       #0284c7;
            --danger:        #dc2626;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ─── Navbar ─────────────────────────────────── */
        .co-nav {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .co-nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
        }
        .co-nav-brand .brand-accent { color: var(--primary); }
        .co-nav-secure {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.78rem;
            color: var(--muted);
            margin-left: 18px;
            padding-left: 18px;
            border-left: 1px solid var(--border);
        }

        /* ─── Main Layout ────────────────────────────── */
        .co-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 16px 60px;
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 28px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .co-wrapper { grid-template-columns: 1fr; }
            .co-summary-col { order: -1; }
        }

        /* ─── Cards ──────────────────────────────────── */
        .co-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px 32px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
        }
        .co-card + .co-card { margin-top: 20px; }

        .co-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .co-card-title .step-badge {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* ─── Form Fields ────────────────────────────── */
        .co-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .co-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: var(--text);
            background: #f8fafc;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .co-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
            background: #fff;
        }
        .co-input.is-invalid { border-color: var(--danger); }
        .co-field-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 500px) { .co-field-group { grid-template-columns: 1fr; } }

        /* ─── Divider ─────────────────────────────────── */
        .co-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }

        /* ─── Stripe Container ───────────────────────── */
        #stripe-element-container {
            background: #f8fafc;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            min-height: 52px;
            transition: border-color .2s;
        }
        #stripe-element-container.StripeElement--focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
        }
        .stripe-logo-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }
        .stripe-logo-row img {
            height: 22px;
            opacity: .55;
        }
        .stripe-logo-row span {
            font-size: 0.78rem;
            color: var(--muted);
        }

        /* ─── Pay Button ──────────────────────────────── */
        .btn-pay {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.3);
        }
        .btn-pay:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.38);
        }
        .btn-pay:active:not(:disabled) { transform: translateY(0); }
        .btn-pay:disabled { opacity: .6; cursor: not-allowed; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--muted);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            text-decoration: none;
            transition: color .2s;
            margin-top: 12px;
        }
        .btn-back:hover { color: var(--text); }

        /* ─── Error Message ───────────────────────────── */
        #payment-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.88rem;
            color: var(--danger);
            margin-top: 14px;
            display: none;
            align-items: center;
            gap: 8px;
        }
        #payment-error.show { display: flex; }

        /* ─── Order Summary ───────────────────────────── */
        .co-summary-col { position: sticky; top: 90px; }

        .summary-title {
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
        }
        .summary-items { list-style: none; padding: 0; margin: 0; }
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border);
            gap: 10px;
        }
        .summary-item:last-child { border-bottom: none; }
        .summary-item-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text);
        }
        .summary-item-sub {
            font-size: 0.78rem;
            color: var(--muted);
            margin-top: 2px;
        }
        .summary-item-price {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text);
            white-space: nowrap;
        }
        .summary-totals { margin-top: 16px; }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .summary-total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            border-top: 2px solid var(--border);
            padding-top: 14px;
            margin-top: 6px;
        }
        .summary-total-row .total-amount { color: var(--primary); }
        .badge-free {
            background: var(--primary-light);
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* ─── Empty cart warning ─────────────────────── */
        #empty-cart-msg {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
        #empty-cart-msg i { font-size: 3rem; margin-bottom: 12px; display: block; }

        /* ─── Spinner ─────────────────────────────────── */
        .spinner-sm {
            width: 18px; height: 18px;
            border: 2.5px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── Security note ───────────────────────────── */
        .security-note {
            font-size: 0.75rem;
            color: var(--muted);
            text-align: center;
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="co-nav">
        <a href="../../tienda.php" class="co-nav-brand">
            <img src="../../assets/Logo navegador PuntoNet.png" alt="Logo" height="28"
                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.insertAdjacentHTML('beforebegin','<span style=\'font-size:1.4rem;\'>🌿</span> ');">
            <span><span class="brand-accent">NISSI STORE</span> Checkout</span>
        </a>
        <div class="co-nav-secure">
            <i class="bi bi-lock-fill text-success"></i>
            Pago 100% seguro
        </div>
    </nav>

    <!-- Main -->
    <main class="co-wrapper" id="mainWrapper">

        <!-- ─── LEFT: Forms ─── -->
        <div id="formCol">

            <!-- Empty cart fallback -->
            <div id="empty-cart-msg" class="co-card" style="display:none;">
                <i class="bi bi-basket2"></i>
                <p class="fw-semibold mb-3">Tu cesta está vacía.</p>
                <a href="../../tienda.php" class="btn btn-primary rounded-pill px-4">Volver a la tienda</a>
            </div>

            <!-- ══ SECCIÓN 1: Datos del Cliente ══ -->
            <div class="co-card" id="cardDatos">
                <h2 class="co-card-title">
                    <span class="step-badge">1</span>
                    Datos del Cliente
                </h2>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="co-label">DNI</label>
                        <input type="text" id="coDni" class="co-input" maxlength="8" placeholder="12345678">
                    </div>
                    <div class="col-6">
                        <label class="co-label">Teléfono / Celular</label>
                        <input type="tel" id="coTelefono" class="co-input" maxlength="15" placeholder="987654321">
                    </div>
                    <div class="col-6">
                        <label class="co-label">Nombres</label>
                        <input type="text" id="coNombres" class="co-input" placeholder="Juan Carlos">
                    </div>
                    <div class="col-6">
                        <label class="co-label">Apellidos</label>
                        <input type="text" id="coApellidos" class="co-input" placeholder="Pérez García">
                    </div>
                </div>
            </div>

            <!-- ══ SECCIÓN 2: Pasarela de Pago Stripe ══ -->
            <div class="co-card" id="cardPago">
                <h2 class="co-card-title">
                    <span class="step-badge">2</span>
                    Método de Pago
                </h2>

                <div class="stripe-logo-row">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg" alt="Stripe">
                    <span>Encriptación SSL · Datos protegidos</span>
                </div>

                <!-- Stripe Payment Element se montará aquí -->
                <div id="stripe-element-container">
                    <div id="payment-element">
                        <!-- Stripe inserts UI here -->
                    </div>
                </div>

                <!-- Error de pago -->
                <div id="payment-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span id="payment-error-msg"></span>
                </div>

                <!-- Botón principal -->
                <button id="btn-pay" class="btn-pay" disabled>
                    <span class="spinner-sm" id="pay-spinner"></span>
                    <i class="bi bi-shield-lock-fill" id="pay-icon"></i>
                    <span id="pay-label">Confirmar y Pagar</span>
                </button>

                <p class="security-note">
                    <i class="bi bi-lock-fill"></i>
                    Transacción segura con Stripe. No almacenamos datos de tu tarjeta.
                </p>

                <div class="text-center mt-3">
                    <a href="../../tienda.php" class="btn-back">
                        <i class="bi bi-arrow-left"></i> Volver a la tienda
                    </a>
                </div>
            </div>
        </div><!-- /formCol -->

        <!-- ─── RIGHT: Order Summary ─── -->
        <div class="co-summary-col">
            <div class="co-card">
                <div class="summary-title">
                    <i class="bi bi-bag-check-fill text-success fs-5"></i>
                    Tu Pedido
                </div>

                <ul class="summary-items" id="summaryItems">
                    <!-- Filled by JS -->
                </ul>

                <div class="summary-totals">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span id="summarySubtotal">S/ 0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Envío / Recojo</span>
                        <span class="badge-free">GRATIS</span>
                    </div>
                    <div class="summary-total-row">
                        <span>TOTAL</span>
                        <span class="total-amount" id="summaryTotal">S/ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

    </main><!-- /co-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ─────────────────────────────────────────────────────────────
    // 1. Load cart from sessionStorage (solo para el render inicial;
    //    el precio y el total definitivos siempre vienen del servidor).
    // ─────────────────────────────────────────────────────────────
    const cart = JSON.parse(sessionStorage.getItem('puntonet_cart') || '[]');
    let totalAmount = cart.reduce((s, i) => s + i.subtotal, 0);
    let paymentIntentId = null;

    if (cart.length === 0) {
        document.getElementById('empty-cart-msg').style.display = 'block';
        document.getElementById('cardDatos').style.display = 'none';
        document.getElementById('cardPago').style.display  = 'none';
        document.querySelector('.co-summary-col').style.display = 'none';
    } else {
        renderSummary(cart.map(i => ({ nombre: i.nombre, cantidad: i.cantidad, precio_unitario: i.precio, subtotal: i.subtotal })), totalAmount);
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    function renderSummary(items, total) {
        const ul = document.getElementById('summaryItems');
        ul.innerHTML = items.map(item => `
            <li class="summary-item">
                <div>
                    <div class="summary-item-name">${escapeHtml(item.nombre)}</div>
                    <div class="summary-item-sub">
                        x${item.cantidad} · S/ ${parseFloat(item.precio_unitario).toFixed(2)}
                    </div>
                </div>
                <div class="summary-item-price">S/ ${parseFloat(item.subtotal).toFixed(2)}</div>
            </li>
        `).join('');

        const fmt = n => `S/ ${n.toFixed(2)}`;
        document.getElementById('summarySubtotal').textContent = fmt(total);
        document.getElementById('summaryTotal').textContent    = fmt(total);
        document.getElementById('pay-label').textContent       = `Confirmar y Pagar — S/ ${total.toFixed(2)}`;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. Stripe initialization — el monto SIEMPRE lo calcula el servidor
    //    a partir de {id_producto, cantidad}; nunca enviamos precios.
    // ─────────────────────────────────────────────────────────────
    const stripe   = Stripe('<?php echo htmlspecialchars($stripePublicKey); ?>');
    let elements   = null;
    let clientSecret = null;

    async function initStripe() {
        if (cart.length === 0) return;

        try {
            const res = await fetch('../../controllers/C_PaymentIntent.php?action=crear', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    carrito: cart.map(i => ({ id_producto: i.id_producto, cantidad: i.cantidad })),
                }),
            });

            // Guard: check the response is actually JSON before parsing
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const raw = await res.text();
                console.error('Respuesta no-JSON de C_PaymentIntent.php:', raw);
                showError('Error del servidor de pagos (respuesta inesperada). Revisa la consola para más detalles.');
                return;
            }

            const data = await res.json();

            if (data.error) {
                showError('Error: ' + data.error);
                return;
            }
            if (!data.client_secret) {
                showError('No se recibió client_secret de Stripe. Verifica la configuración del servidor.');
                return;
            }
            clientSecret = data.client_secret;
            paymentIntentId = data.payment_intent_id;
            totalAmount = parseFloat(data.total);
            renderSummary(data.items, totalAmount);

            const appearance = {
                theme: 'stripe',
                variables: {
                    colorPrimary:      '#15803d',
                    colorBackground:   '#f8fafc',
                    colorText:         '#0f172a',
                    colorDanger:       '#dc2626',
                    fontFamily:        'Inter, system-ui, sans-serif',
                    spacingUnit:       '4px',
                    borderRadius:      '8px',
                    fontSizeBase:      '15px',
                },
                rules: {
                    '.Input': {
                        border:          '1.5px solid #e2e8f0',
                        boxShadow:       'none',
                        padding:         '11px 14px',
                    },
                    '.Input:focus': {
                        border:          '1.5px solid #15803d',
                        boxShadow:       '0 0 0 3px rgba(21,128,61,.12)',
                    },
                    '.Label': {
                        fontSize:        '0.78rem',
                        fontWeight:      '600',
                        textTransform:   'uppercase',
                        letterSpacing:   '0.4px',
                        color:           '#64748b',
                    },
                }
            };

            elements = stripe.elements({ appearance, clientSecret });

            const paymentElement = elements.create('payment', {
                layout: { type: 'tabs', defaultCollapsed: false },
                // Nunca guardar la tarjeta ni mostrar opción de guardarla
                terms: {
                    card:       'never',
                    applePay:   'never',
                    googlePay:  'never',
                    paypal:     'never',
                    auBecsDebit:'never',
                    bancontact: 'never',
                    ideal:      'never',
                    sepaDebit:  'never',
                    sofort:     'never',
                    usBankAccount: 'never',
                },
                // Desactivar wallets (Apple Pay / Google Pay) — requieren HTTPS y dominio verificado
                wallets: {
                    applePay:  'never',
                    googlePay: 'never',
                },
                // Ocultar campo de guardar para uso futuro
                savePaymentMethod: { payment_method_save: 'hidden' },
            });
            paymentElement.mount('#payment-element');

            paymentElement.on('ready', () => {
                document.getElementById('btn-pay').disabled = false;
            });

        } catch (err) {
            showError('Error al conectar con el servidor de pagos. Intenta de nuevo.');
            console.error(err);
        }
    }

    initStripe();

    // ─────────────────────────────────────────────────────────────
    // 3. Form validation helpers
    // ─────────────────────────────────────────────────────────────
    function validateDatos() {
        const fields = [
            { id: 'coDni',      label: 'DNI' },
            { id: 'coNombres',  label: 'Nombres' },
            { id: 'coApellidos',label: 'Apellidos' },
            { id: 'coTelefono', label: 'Teléfono' },
        ];
        let valid = true;
        fields.forEach(f => {
            const el = document.getElementById(f.id);
            const val = el.value.trim();
            if (!val) {
                el.classList.add('is-invalid');
                valid = false;
            } else {
                el.classList.remove('is-invalid');
            }
        });

        const dniEl = document.getElementById('coDni');
        if (dniEl.value.trim().length !== 8) {
            dniEl.classList.add('is-invalid');
            valid = false;
        }
        return valid;
    }

    // Remove invalid class on input
    ['coDni','coNombres','coApellidos','coTelefono'].forEach(id => {
        document.getElementById(id).addEventListener('input', () => {
            document.getElementById(id).classList.remove('is-invalid');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 4. Pay button — submit flow
    // ─────────────────────────────────────────────────────────────
    document.getElementById('btn-pay').addEventListener('click', async () => {
        hideError();

        // Validate customer data first
        if (!validateDatos()) {
            document.getElementById('cardDatos').scrollIntoView({ behavior: 'smooth', block: 'center' });
            showError('Por favor completa correctamente tus datos personales antes de pagar.');
            return;
        }

        if (!elements || !clientSecret || !paymentIntentId) {
            showError('El sistema de pago aún no está listo. Espera un momento.');
            return;
        }

        setLoading(true);

        // First: register the order (estado "pendiente de pago") and reserve stock.
        // Nunca enviamos precios ni el total: el servidor los recalcula desde el carrito.
        const clientePayload = {
            cliente: {
                dni:       document.getElementById('coDni').value.trim(),
                nombres:   document.getElementById('coNombres').value.trim(),
                apellidos: document.getElementById('coApellidos').value.trim(),
                telefono:  document.getElementById('coTelefono').value.trim(),
                direccion: '',
            },
            carrito: cart.map(i => ({ id_producto: i.id_producto, cantidad: i.cantidad })),
            payment_intent_id: paymentIntentId,
        };

        let id_pedido = null;
        let token = null;
        try {
            const pedidoRes = await fetch('../../controllers/C_Ecommerce.php?action=crear_pedido', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(clientePayload),
            });
            const pedidoData = await pedidoRes.json();

            if (!pedidoData.success) {
                showError(pedidoData.mensaje || 'Error al registrar el pedido.');
                setLoading(false);
                return;
            }
            id_pedido = pedidoData.id_pedido;
            token = pedidoData.token;
        } catch (e) {
            showError('Error de conexión al registrar el pedido.');
            setLoading(false);
            return;
        }

        // Second: confirm payment with Stripe
        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: window.location.origin
                    + window.location.pathname.replace('V_checkout.php', 'V_checkout_success.php')
                    + '?id=' + id_pedido + '&t=' + encodeURIComponent(token),
            },
        });

        // If we reach here, there was an error (redirect on success)
        if (error) {
            if (error.type === 'card_error' || error.type === 'validation_error') {
                showError(error.message);
            } else {
                showError('Ocurrió un error inesperado. Por favor intenta de nuevo.');
            }
        }
        setLoading(false);
    });

    // ─────────────────────────────────────────────────────────────
    // 5. Helpers
    // ─────────────────────────────────────────────────────────────
    function setLoading(loading) {
        const btn     = document.getElementById('btn-pay');
        const spinner = document.getElementById('pay-spinner');
        const icon    = document.getElementById('pay-icon');
        const label   = document.getElementById('pay-label');

        btn.disabled = loading;
        spinner.style.display = loading ? 'block' : 'none';
        icon.style.display    = loading ? 'none'  : 'inline';
        label.textContent     = loading
            ? 'Procesando pago...'
            : `Confirmar y Pagar — S/ ${totalAmount.toFixed(2)}`;
    }

    function showError(msg) {
        const el = document.getElementById('payment-error');
        document.getElementById('payment-error-msg').textContent = msg;
        el.classList.add('show');
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideError() {
        document.getElementById('payment-error').classList.remove('show');
    }
    </script>
</body>
</html>
