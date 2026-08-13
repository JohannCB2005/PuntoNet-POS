<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/models/M_Cliente.php';
require_once dirname(dirname(__DIR__)) . '/models/M_Producto.php';

// La compra requiere cuenta — ya no existe checkout como invitado.
if (!isset($_SESSION['id_cliente'])) {
    header('Location: V_cuenta.php?volver=checkout');
    exit;
}

$clienteCuenta = M_Cliente::singleton()->obtenerClientePorId((int) $_SESSION['id_cliente']);
$niveles = M_Producto::singleton()->obtenerNiveles();
$grados = M_Producto::singleton()->obtenerGrados();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- Krypton exige este viewport exacto; sin maximum-scale/user-scalable avisa CLIENT_705. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout Seguro — NISSI STORE</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../../assets/logo.svg">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Izipay / Krypton: estilos del formulario embebido. El script se carga
         dinámicamente al generar el FormToken (ver initIzipay más abajo), porque
         kr-public-key debe ir en la etiqueta y el token no existe hasta entonces. -->
    <link rel="stylesheet" href="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.css">

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

        /* ─── Contenedor del formulario de Izipay ─────── */
        #izipay-form-container {
            background: #f8fafc;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            min-height: 52px;
        }
        .pasarela-nota {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            font-size: 0.78rem;
            color: var(--muted);
        }
        /* El botón de pago lo pinta Krypton dentro de su propio formulario. */
        .kr-embedded { width: 100%; }

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
        <a href="../../store.php" class="co-nav-brand">
            <img src="../../assets/logo.svg" alt="Logo" height="28"
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
                <a href="../../store.php" class="btn btn-primary rounded-pill px-4">Volver a la tienda</a>
            </div>

            <!-- ══ SECCIÓN 1: Datos del Cliente (de la cuenta) ══ -->
            <div class="co-card" id="cardDatos">
                <h2 class="co-card-title">
                    <span class="step-badge">1</span>
                    Datos del Cliente
                </h2>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="co-label">DNI</label>
                        <input type="text" class="co-input" value="<?php echo htmlspecialchars($clienteCuenta['numero_documento'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-6">
                        <label class="co-label">Teléfono / Celular</label>
                        <input type="tel" class="co-input" value="<?php echo htmlspecialchars($clienteCuenta['telefono'] ?? 'No registrado'); ?>" disabled>
                    </div>
                    <div class="col-6">
                        <label class="co-label">Nombres</label>
                        <input type="text" class="co-input" value="<?php echo htmlspecialchars($clienteCuenta['nombres_razon_social'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-6">
                        <label class="co-label">Apellidos</label>
                        <input type="text" class="co-input" value="<?php echo htmlspecialchars($clienteCuenta['apellidos'] ?? ''); ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- ══ SECCIÓN 1.5: Tipo de comprobante ══ -->
            <div class="co-card" id="cardComprobante">
                <h2 class="co-card-title">
                    <span class="step-badge">2</span>
                    ¿Boleta o Factura?
                </h2>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="tipoComprobante" id="comprobanteBoleta" value="1" checked>
                        <label class="btn btn-outline-primary w-100 py-2" for="comprobanteBoleta">
                            <i class="bi bi-receipt me-1"></i> Boleta
                        </label>
                    </div>
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="tipoComprobante" id="comprobanteFactura" value="2">
                        <label class="btn btn-outline-primary w-100 py-2" for="comprobanteFactura">
                            <i class="bi bi-building me-1"></i> Factura
                        </label>
                    </div>
                </div>

                <div id="camposFactura" style="display:none;">
                    <div class="row g-3 align-items-end">
                        <div class="col-8">
                            <label class="co-label">RUC</label>
                            <input type="text" id="coRuc" class="co-input" maxlength="11" inputmode="numeric" placeholder="11 dígitos">
                        </div>
                        <div class="col-4">
                            <button type="button" id="btnBuscarRuc" class="btn btn-outline-primary w-100">
                                <span id="rucSpinner" class="spinner-border spinner-border-sm" style="display:none;"></span>
                                <span id="rucBtnLabel">Buscar</span>
                            </button>
                        </div>
                        <div class="col-12">
                            <label class="co-label">Razón social</label>
                            <input type="text" id="coRazonSocial" class="co-input" disabled placeholder="Se completa al buscar el RUC">
                        </div>
                    </div>
                    <p id="rucError" class="text-danger mb-0 mt-2" style="display:none; font-size:0.85rem;"></p>
                </div>
            </div>

            <!-- ══ SECCIÓN 3: Entrega ══ -->
            <div class="co-card" id="cardEntrega">
                <h2 class="co-card-title">
                    <span class="step-badge">3</span>
                    ¿Cómo quieres recibir tu pedido?
                </h2>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="tipoEntrega" id="entregaTienda" value="1" checked>
                        <label class="btn btn-outline-primary w-100 py-2" for="entregaTienda">
                            <i class="bi bi-shop me-1"></i> Recoger en tienda
                        </label>
                    </div>
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="tipoEntrega" id="entregaColegio" value="2">
                        <label class="btn btn-outline-primary w-100 py-2" for="entregaColegio">
                            <i class="bi bi-mortarboard me-1"></i> Entregar en el colegio
                        </label>
                    </div>
                </div>

                <div id="camposEntregaColegio" style="display:none;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="co-label">Nombre del estudiante</label>
                            <input type="text" id="coEstudiante" class="co-input" placeholder="Nombre y apellidos del estudiante">
                        </div>
                        <div class="col-6">
                            <label class="co-label">Nivel</label>
                            <select id="coNivel" class="co-input">
                                <option value="">Seleccionar</option>
                                <?php foreach ($niveles as $n): ?>
                                    <option value="<?php echo $n['id_nivel']; ?>"><?php echo htmlspecialchars($n['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="co-label">Grado</label>
                            <select id="coGrado" class="co-input" disabled>
                                <option value="">Elige primero el nivel</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="co-label">Observaciones (opcional)</label>
                            <textarea id="coObservaciones" class="co-input" rows="2" placeholder="Ej: entregar en dirección, horario de recreo, etc."></textarea>
                        </div>
                    </div>
                </div>
                <p id="entregaTiendaMsg" class="text-muted mb-0" style="font-size:0.85rem;">
                    <i class="bi bi-info-circle"></i> Podrás recogerlo en nuestra tienda una vez confirmado el pago.
                </p>
            </div>

            <!-- ══ SECCIÓN 4: Pago con Izipay ══ -->
            <div class="co-card" id="cardPago">
                <h2 class="co-card-title">
                    <span class="step-badge">4</span>
                    Método de Pago
                </h2>

                <div class="pasarela-nota">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Pago procesado por Izipay · Encriptación SSL · No almacenamos datos de tu tarjeta</span>
                </div>

                <!-- Error de pago -->
                <div id="payment-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span id="payment-error-msg"></span>
                </div>

                <!-- Paso A: botón que reserva el pedido y pide el formulario de pago.
                     Krypton necesita el FormToken (y por tanto el pedido ya creado)
                     antes de poder renderizar el formulario de tarjeta. -->
                <button id="btn-pay" class="btn-pay">
                    <span class="spinner-sm" id="pay-spinner"></span>
                    <i class="bi bi-lock-fill" id="pay-icon"></i>
                    <span id="pay-label">Continuar al pago</span>
                </button>

                <!-- Paso B: aquí Krypton monta el formulario de tarjeta -->
                <div id="izipay-form-container" class="d-none">
                    <!-- El div .kr-embedded se inserta por JS justo antes de renderizar.
                         Si estuviera aquí desde el inicio, Krypton lo auto-renderizaría
                         (sin token) al cargarse y chocaría con nuestro render real. -->
                </div>

                <p class="security-note" id="reservaNota" style="display:none;">
                    <i class="bi bi-clock-history"></i>
                    Tu pedido queda reservado 10 minutos mientras completas el pago.
                </p>

                <div class="text-center mt-3">
                    <a href="../../store.php" class="btn-back">
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
        document.getElementById('pay-label').textContent       = `Continuar al pago — S/ ${total.toFixed(2)}`;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. Pago con Izipay (formulario embebido Krypton).
    //
    //    Krypton exige el FormToken ANTES de poder pintar el formulario, y el
    //    orderId va dentro de ese token. Por eso el pedido se crea primero
    //    (reservando stock) y solo después aparece el formulario de tarjeta.
    //    El monto SIEMPRE lo calcula el servidor a partir de {id_producto, cantidad}.
    // ─────────────────────────────────────────────────────────────
    let pedidoCreado = null;   // { id_pedido, token } una vez reservado
    let kryptonCargado = false;

    // Carga kr-payment-form.min.js una sola vez, con la clave pública que
    // devuelve el servidor (es la única credencial que puede ver el navegador).
    function cargarKrypton(publicKey) {
        if (kryptonCargado) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js';
            s.setAttribute('kr-public-key', publicKey);
            s.setAttribute('kr-post-url-success', 'V_checkout_success.php');
            s.setAttribute('kr-language', 'es-ES');
            s.onload = () => {
                const ext = document.createElement('script');
                ext.src = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.js';
                ext.onload = () => { kryptonCargado = true; resolve(); };
                ext.onerror = () => reject(new Error('No se pudo cargar el tema del formulario.'));
                document.head.appendChild(ext);
            };
            s.onerror = () => reject(new Error('No se pudo cargar la pasarela de pago.'));
            document.head.appendChild(s);
        });
    }

    // Paso A: reservar el pedido. Devuelve false si algo impide continuar.
    async function reservarPedido() {
        const payload = {
            carrito: cart.map(i => ({ id_producto: i.id_producto, cantidad: i.cantidad })),
            tipo_entrega: parseInt(document.querySelector('input[name="tipoEntrega"]:checked').value),
            observaciones: document.getElementById('coObservaciones').value.trim(),
            tipo_comprobante: parseInt(document.querySelector('input[name="tipoComprobante"]:checked').value),
        };
        if (payload.tipo_entrega === 2) {
            payload.estudiante_nombre = document.getElementById('coEstudiante').value.trim();
            payload.id_nivel = parseInt(document.getElementById('coNivel').value);
            payload.id_grado = parseInt(document.getElementById('coGrado').value);
        }
        if (payload.tipo_comprobante === 2) {
            payload.ruc_facturacion = document.getElementById('coRuc').value.trim();
        }

        const res  = await fetch('../../controllers/C_Ecommerce.php?action=crear_pedido', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const json = await res.json();

        if (!json.success) {
            if (json.requiere_login) {
                window.location.href = 'V_cuenta.php?volver=checkout';
                return false;
            }
            showError(json.mensaje || 'No pudimos reservar tu pedido.');
            return false;
        }

        pedidoCreado = { id_pedido: json.id_pedido, token: json.token };
        return true;
    }

    // Paso B: pedir el FormToken de ese pedido y montar el formulario de tarjeta.
    async function montarFormularioPago() {
        const res = await fetch('../../controllers/C_Izipay.php?action=form_token', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(pedidoCreado),
        });
        const json = await res.json();

        if (!json.success || !json.formToken) {
            showError(json.mensaje || 'No pudimos iniciar el pago.');
            return false;
        }

        const contenedor = document.getElementById('izipay-form-container');
        const yaEstabaCargado = kryptonCargado;

        // El div debe existir ANTES de que Krypton se inicialice: si se añade durante
        // la inicialización, la propia librería avisa de parpadeo en la interfaz.
        contenedor.innerHTML = '<div class="kr-embedded"></div>';
        contenedor.classList.remove('d-none');
        document.getElementById('reservaNota').style.display = '';
        document.getElementById('btn-pay').style.display = 'none';

        // Los datos de entrega ya están comprometidos en el pedido: permitir
        // cambiarlos ahora daría un pedido distinto al que se está cobrando.
        bloquearDatosEntrega();

        await cargarKrypton(json.public_key);

        if (!window.KR) {
            showError('No se pudo cargar la pasarela de pago.');
            return false;
        }

        KR.onError(err => showError(err.errorMessage || 'No se pudo procesar el pago.'));

        // En un reintento Krypton ya está inicializado y con un formulario montado
        // sobre el token anterior: hay que retirarlo o el render nuevo falla.
        if (yaEstabaCargado) {
            await KR.removeForms();
        }

        // El token se entrega por setFormConfig, que además dispara el render. NO se
        // llama a renderElements() después: sería un segundo render y Krypton avisaría
        // de "un formulario ya está renderizado".
        await KR.setFormConfig({ formToken: json.formToken, 'kr-language': 'es-ES' });

        return true;
    }

    function bloquearDatosEntrega() {
        document.querySelectorAll('#cardDatos input, #cardDatos select, #cardDatos textarea')
            .forEach(el => { el.disabled = true; });
    }

    // ─────────────────────────────────────────────────────────────
    // 2.5 Comprobante: Boleta vs Factura (RUC validado contra SUNAT antes de pagar)
    // ─────────────────────────────────────────────────────────────
    // Solo se puede continuar con Factura si el RUC actual ya fue confirmado por
    // consultar_ruc. Se resetea cada vez que el campo cambia: no basta con haber
    // buscado un RUC antes si el cliente lo edita después sin volver a buscar.
    let rucValidado = false;

    document.querySelectorAll('input[name="tipoComprobante"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const esFactura = document.getElementById('comprobanteFactura').checked;
            document.getElementById('camposFactura').style.display = esFactura ? 'block' : 'none';
        });
    });

    document.getElementById('coRuc').addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
        rucValidado = false;
        document.getElementById('coRazonSocial').value = '';
        document.getElementById('rucError').style.display = 'none';
    });

    document.getElementById('btnBuscarRuc').addEventListener('click', async () => {
        const ruc = document.getElementById('coRuc').value.trim();
        const errorEl = document.getElementById('rucError');
        errorEl.style.display = 'none';

        if (ruc.length !== 11) {
            errorEl.textContent = 'El RUC debe tener 11 dígitos.';
            errorEl.style.display = 'block';
            return;
        }

        const btn = document.getElementById('btnBuscarRuc');
        document.getElementById('rucSpinner').style.display = 'inline-block';
        document.getElementById('rucBtnLabel').textContent = 'Buscando...';
        btn.disabled = true;

        try {
            const res  = await fetch('../../controllers/C_ClienteAuth.php?action=consultar_ruc', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ ruc }),
            });
            const json = await res.json();

            if (!json.success) {
                errorEl.textContent = json.mensaje || 'No se pudo validar el RUC.';
                errorEl.style.display = 'block';
                rucValidado = false;
                return;
            }

            document.getElementById('coRazonSocial').value = json.data.razon_social;
            rucValidado = true;
        } catch (e) {
            errorEl.textContent = 'Error de conexión al validar el RUC.';
            errorEl.style.display = 'block';
            rucValidado = false;
        } finally {
            document.getElementById('rucSpinner').style.display = 'none';
            document.getElementById('rucBtnLabel').textContent = 'Buscar';
            btn.disabled = false;
        }
    });

    function validateComprobante() {
        if (!document.getElementById('comprobanteFactura').checked) return true;
        return rucValidado && document.getElementById('coRuc').value.trim().length === 11;
    }

    // ─────────────────────────────────────────────────────────────
    // 3. Entrega: recojo en tienda vs. entrega en colegio (cascada nivel → grado)
    // ─────────────────────────────────────────────────────────────
    const gradosPorNivel = <?php echo json_encode(array_map(fn($g) => ['id_grado' => (int) $g['id_grado'], 'id_nivel' => (int) $g['id_nivel'], 'nombre' => $g['nombre']], $grados)); ?>;

    document.querySelectorAll('input[name="tipoEntrega"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const esColegio = document.getElementById('entregaColegio').checked;
            document.getElementById('camposEntregaColegio').style.display = esColegio ? 'block' : 'none';
            document.getElementById('entregaTiendaMsg').style.display = esColegio ? 'none' : 'block';
        });
    });

    document.getElementById('coNivel').addEventListener('change', function() {
        const idNivel = parseInt(this.value);
        const selectGrado = document.getElementById('coGrado');
        if (!idNivel) {
            selectGrado.innerHTML = '<option value="">Elige primero el nivel</option>';
            selectGrado.disabled = true;
            return;
        }
        const opciones = gradosPorNivel.filter(g => g.id_nivel === idNivel);
        selectGrado.innerHTML = '<option value="">Seleccionar</option>' +
            opciones.map(g => `<option value="${g.id_grado}">${g.nombre}</option>`).join('');
        selectGrado.disabled = false;
    });

    function validateEntrega() {
        if (!document.getElementById('entregaColegio').checked) return true;

        const campos = ['coEstudiante', 'coNivel', 'coGrado'];
        let valid = true;
        campos.forEach(id => {
            const el = document.getElementById(id);
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                valid = false;
            } else {
                el.classList.remove('is-invalid');
            }
        });
        return valid;
    }

    ['coEstudiante', 'coNivel', 'coGrado'].forEach(id => {
        document.getElementById(id).addEventListener('input', () => {
            document.getElementById(id).classList.remove('is-invalid');
        });
        document.getElementById(id).addEventListener('change', () => {
            document.getElementById(id).classList.remove('is-invalid');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 4. "Continuar al pago": reserva el pedido y muestra el formulario.
    //    A partir de ahí el cobro lo gestiona el formulario de Krypton, que al
    //    completarse hace POST a V_checkout_success.php con kr-answer + kr-hash.
    // ─────────────────────────────────────────────────────────────
    document.getElementById('btn-pay').addEventListener('click', async () => {
        hideError();

        if (!validateComprobante()) {
            document.getElementById('cardComprobante').scrollIntoView({ behavior: 'smooth', block: 'center' });
            showError('Busca y confirma el RUC para emitir Factura, o cambia a Boleta.');
            return;
        }
        if (!validateEntrega()) {
            document.getElementById('cardEntrega').scrollIntoView({ behavior: 'smooth', block: 'center' });
            showError('Completa el nombre del estudiante, nivel y grado para la entrega en el colegio.');
            return;
        }
        if (cart.length === 0) {
            showError('Tu carrito está vacío.');
            return;
        }

        setLoading(true);
        try {
            // Si el cliente ya reservó antes y falló al montar el formulario, no se
            // vuelve a crear el pedido: se reutiliza el mismo y se pide otro token.
            if (!pedidoCreado && !(await reservarPedido())) {
                setLoading(false);
                return;
            }
            if (!(await montarFormularioPago())) {
                setLoading(false);
                return;
            }
        } catch (e) {
            showError('Error de conexión con la pasarela de pago. Intenta de nuevo.');
            setLoading(false);
            return;
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
            : `Continuar al pago — S/ ${totalAmount.toFixed(2)}`;
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
