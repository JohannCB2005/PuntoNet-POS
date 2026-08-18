<?php
require_once dirname(__DIR__, 2) . '/config/sesion_segura.php';
session_start();
require_once dirname(dirname(__DIR__)) . '/models/M_ClienteWeb.php';
require_once dirname(dirname(__DIR__)) . '/models/M_Producto.php';

// La compra requiere cuenta — ya no existe checkout como invitado.
if (!isset($_SESSION['id_cliente'])) {
    header('Location: /cuenta?volver=checkout');
    exit;
}

$clienteCuenta = M_ClienteWeb::singleton()->obtenerPorId((int) $_SESSION['id_cliente']);
$niveles = M_Producto::singleton()->obtenerNiveles();
$grados = M_Producto::singleton()->obtenerGrados();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- Krypton exige este viewport exacto; sin maximum-scale/user-scalable avisa CLIENT_705. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout Seguro — NISSI</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../../assets/favicon-nissi.svg?v=3">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Izipay / Krypton: estilos del formulario embebido. El script se carga
         dinámicamente al generar el FormToken (ver initIzipay más abajo), porque
         kr-public-key debe ir en la etiqueta y el token no existe hasta entonces. -->
    <link rel="stylesheet" href="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.css">
    <!-- Tema NISSI -->
    <link rel="stylesheet" href="../../assets/css/tienda.css?v=4">
    <?php require_once dirname(__DIR__, 2) . '/config/marca.php'; echo marcaCss(); ?>

    <style>
        * { box-sizing: border-box; }

        body {
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ─── Navbar ─────────────────────────────────── */
        .co-nav {
            background: rgba(255,255,255,.9);
            -webkit-backdrop-filter: blur(14px);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--line);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .co-nav-inner { display: flex; align-items: center; justify-content: space-between; width: 100%; max-width: 1100px; gap: 16px; }
        .co-nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .co-nav-brand img { height: 32px; width: auto; }
        .co-nav-brand .brand-name {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.25rem;
            letter-spacing: .02em;
            color: var(--navy);
            line-height: 1;
        }
        .co-nav-brand .brand-name em { font-style: normal; color: var(--accent); }
        .co-nav-brand .brand-ctx {
            display: block; font-family: var(--font-body);
            font-size: .6rem; font-weight: 700; letter-spacing: .28em;
            text-transform: uppercase; color: var(--muted); margin-top: 3px;
        }
        .co-nav-secure {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: .78rem;
            font-weight: 600;
            color: var(--sage-700);
            background: var(--sage-100);
            padding: 7px 14px;
            border-radius: 999px;
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
            .co-summary-col { order: -1; position: static; }
        }
        @media (max-width: 575.98px) {
            .co-wrapper { gap: 18px; }
            .co-card { padding: 22px 18px; }
            .co-card .row.g-3 > .col-6 { flex: 0 0 100%; max-width: 100%; }
        }

        /* ─── Cards ──────────────────────────────────── */
        .co-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 28px 30px;
            box-shadow: var(--shadow);
        }
        .co-card + .co-card { margin-top: 20px; }

        .co-card-title {
            font-family: var(--font-display);
            font-size: 1.12rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .co-card-title .step-badge {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: var(--navy);
            color: #fff;
            font-family: var(--font-body);
            font-size: .82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(var(--navy-rgb),.3);
        }

        /* ─── Form Fields ────────────────────────────── */
        .co-label {
            font-size: .72rem;
            font-weight: 700;
            color: var(--ink-soft);
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: .08em;
            display: block;
        }
        .co-input {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid var(--line-strong);
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: .95rem;
            color: var(--ink);
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .co-input:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(var(--navy-rgb),.12);
        }
        .co-input.is-invalid { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(var(--accent-rgb),.12); }
        .co-input:disabled { background: var(--paper-2); color: var(--muted); cursor: not-allowed; }
        select.co-input { appearance: auto; }
        .co-field-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 500px) { .co-field-group { grid-template-columns: 1fr; } }

        /* ─── Divider ─────────────────────────────────── */
        .co-divider { border: none; border-top: 1px solid var(--line); margin: 24px 0; }

        /* ─── Contenedor del formulario de Izipay ─────── */
        #izipay-form-container {
            background: var(--paper);
            border: 1.5px solid var(--line);
            border-radius: var(--radius-sm);
            padding: 16px;
            min-height: 52px;
        }
        .pasarela-nota {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 16px;
            font-size: .78rem;
            color: var(--muted);
            background: var(--paper-2);
            border-radius: var(--radius-sm);
            padding: 11px 14px;
        }
        .pasarela-nota i { color: var(--sage); margin-top: 1px; }
        .kr-embedded { width: 100%; }

        /* ─── Pay Button ──────────────────────────────── */
        .btn-pay {
            width: 100%;
            padding: 16px;
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 999px;
            font-family: var(--font-body);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 22px;
            box-shadow: 0 8px 22px rgba(var(--navy-rgb),.3);
        }
        .btn-pay:hover:not(:disabled) {
            background: var(--navy-700);
            transform: translateY(-1px);
            box-shadow: 0 10px 26px rgba(var(--navy-rgb),.38);
        }
        .btn-pay:active:not(:disabled) { transform: translateY(0); }
        .btn-pay:disabled { opacity: .6; cursor: not-allowed; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .88rem;
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
        .btn-back:hover { color: var(--navy); }

        /* ─── Error Message ───────────────────────────── */
        #payment-error {
            background: var(--danger-100);
            border: 1px solid #f5d6d9;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            font-size: .88rem;
            color: var(--danger);
            margin-top: 14px;
            display: none;
            align-items: center;
            gap: 8px;
        }
        #payment-error.show { display: flex; }

        /* ─── Order Summary ───────────────────────────── */
        @media (min-width: 900.01px) {
            .co-summary-col { position: sticky; top: 90px; }
        }

        .summary-title {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 18px;
            color: var(--navy);
        }
        .summary-items { list-style: none; padding: 0; margin: 0; }
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px dashed var(--line-strong);
            gap: 10px;
        }
        .summary-item:last-child { border-bottom: none; }
        .summary-item-name { font-size: .88rem; font-weight: 600; color: var(--ink); }
        .summary-item-sub { font-size: .78rem; color: var(--muted); margin-top: 2px; }
        .summary-item-price { font-size: .9rem; font-weight: 700; color: var(--ink); white-space: nowrap; }
        .summary-totals { margin-top: 16px; }
        .summary-row { display: flex; justify-content: space-between; font-size: .88rem; color: var(--muted); margin-bottom: 8px; }
        .summary-total-row {
            display: flex;
            justify-content: space-between;
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--ink);
            border-top: 2px solid var(--line-strong);
            padding-top: 14px;
            margin-top: 6px;
        }
        .summary-total-row .total-amount { color: var(--accent); }
        .badge-free {
            background: var(--sage-100);
            color: var(--sage-700);
            font-size: .72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
        }

        /* ─── Empty cart warning ─────────────────────── */
        #empty-cart-msg { text-align: center; padding: 40px 20px; color: var(--muted); }
        #empty-cart-msg i { font-size: 3rem; margin-bottom: 12px; display: block; color: var(--line-strong); }

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
            font-size: .75rem;
            color: var(--muted);
            text-align: center;
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        /* Radio-tarjeta de selección del checkout */
        .btn-check + .btn { border-radius: var(--radius-sm); }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="co-nav">
        <div class="co-nav-inner">
            <a href="/tienda" class="co-nav-brand">
                <span>
                    <span class="brand-name">NISSI<em>.</em></span>
                    <span class="brand-ctx">Checkout seguro</span>
                </span>
            </a>
            <div class="co-nav-secure">
                <i class="bi bi-lock-fill"></i>
                Pago 100% seguro
            </div>
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
                <a href="/tienda" class="btn btn-primary rounded-pill px-4">Volver a la tienda</a>
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

                <div id="bloqueRecogida" class="mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="quienRecoge" id="recogeYo" value="yo" checked>
                            <label class="btn btn-outline-primary w-100 py-2" for="recogeYo">
                                <i class="bi bi-person-check me-1"></i> Lo recogeré yo
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="quienRecoge" id="recogeOtra" value="otra">
                            <label class="btn btn-outline-accent w-100 py-2" for="recogeOtra">
                                <i class="bi bi-person-plus me-1"></i> Lo recogerá otra persona
                            </label>
                        </div>
                    </div>
                    <p id="recogeYoMsg" class="text-muted mb-0 mt-2" style="font-size:0.85rem;">
                        <i class="bi bi-info-circle"></i> Recogerá
                        <strong><?php echo htmlspecialchars(trim(($clienteCuenta['nombres_razon_social'] ?? '') . ' ' . ($clienteCuenta['apellidos'] ?? '')) ?: 'el titular de la cuenta'); ?></strong>
                        (DNI <?php echo htmlspecialchars($clienteCuenta['numero_documento'] ?? '—'); ?>).
                    </p>
                    <div id="camposRecogeOtra" class="row g-3 mt-1" style="display:none;">
                        <div class="col-6">
                            <label class="co-label">DNI de quien recogerá</label>
                            <input type="text" id="coRecogeDni" class="co-input" maxlength="8" inputmode="numeric" placeholder="8 dígitos">
                        </div>
                        <div class="col-6">
                            <label class="co-label">Nombres y apellidos</label>
                            <input type="text" id="coRecogeNombre" class="co-input" placeholder="Nombre y apellidos completos">
                        </div>
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
                    <span id="pasarelaNotaText">Pago procesado por Izipay (tarjeta) o TAYPI (QR Yape/Plin) · Encriptación SSL · No almacenamos datos de tu tarjeta</span>
                </div>

                <!-- Método de pago -->
                <div class="row g-2 mb-3">
                    <div class="col">
                        <input type="radio" class="btn-check" name="metodoPago" id="metodoTarjeta" value="tarjeta" checked>
                        <label class="btn btn-outline-primary w-100 py-2" for="metodoTarjeta">
                            <i class="bi bi-credit-card me-1"></i> Tarjeta
                        </label>
                    </div>
                    <div class="col">
                        <input type="radio" class="btn-check" name="metodoPago" id="metodoQr" value="qr">
                        <label class="btn btn-outline-primary w-100 py-2" for="metodoQr">
                            <i class="bi bi-qr-code me-1"></i> Yape / Plin
                        </label>
                    </div>
                </div>
                <p class="text-muted mb-0" style="font-size:0.8rem;" id="metodoPagoNota">
                    <i class="bi bi-info-circle"></i> Paga con tu tarjeta de débito o crédito.
                </p>

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
                    <a href="/tienda" class="btn-back">
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
    const cart = JSON.parse(sessionStorage.getItem('nissi_cart') || sessionStorage.getItem('puntonet_cart') || '[]');
    let totalAmount = cart.reduce((s, i) => s + i.subtotal, 0);

    // Pasarelas habilitadas en el panel (Configuración → Pagos). Si el administrador
    // deshabilitó una, no se muestra su método de pago. Con una sola disponible se
    // selecciona por defecto; con ninguna, se bloquea el botón de pago.
    let pasarelas = { taypi: true, izipay: true };
    (async () => {
        try {
            const res = await fetch('../../controllers/C_Ecommerce.php?action=pasarelas', { cache: 'no-store' });
            const json = await res.json();
            if (json.success) pasarelas = { taypi: !!json.taypi, izipay: !!json.izipay };
        } catch (e) { /* ante error, se asume que ambas están habilitadas */ }

        const wrapTarjeta = document.getElementById('metodoTarjeta').closest('.col');
        const wrapQr      = document.getElementById('metodoQr').closest('.col');

        if (!pasarelas.izipay && wrapTarjeta) wrapTarjeta.style.display = 'none';
        if (!pasarelas.taypi && wrapQr) wrapQr.style.display = 'none';

        // La advertencia de pago solo menciona las pasarelas activas.
        const notaText = document.getElementById('pasarelaNotaText');
        if (notaText) {
            const partes = [];
            if (pasarelas.izipay) partes.push('Izipay (tarjeta)');
            if (pasarelas.taypi)  partes.push('TAYPI (QR Yape/Plin)');
            if (partes.length > 0) {
                notaText.textContent = `Pago procesado por ${partes.join(' o ')} · Encriptación SSL · No almacenamos datos de tu tarjeta`;
            } else {
                notaText.closest('.pasarela-nota').style.display = 'none';
            }
        }

        // Reacomodar la selección por defecto según lo que quede disponible.
        if (!pasarelas.izipay && pasarelas.taypi) {
            document.getElementById('metodoQr').checked = true;
            document.getElementById('metodoPagoNota').innerHTML =
                '<i class="bi bi-info-circle"></i> Escanea el código QR con Yape, Plin o tu app bancaria.';
        } else if (!pasarelas.izipay && !pasarelas.taypi) {
            document.getElementById('btn-pay').disabled = true;
            document.getElementById('btn-pay').innerHTML =
                '<i class="bi bi-exclamation-triangle"></i> Pagos no disponibles';
            document.getElementById('metodoPagoNota').innerHTML =
                '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Los pagos en línea están deshabilitados temporalmente.';
        }
    })();

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
            s.setAttribute('kr-post-url-success', '/confirmacion');
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
        } else {
            payload.quien_recoge = document.querySelector('input[name="quienRecoge"]:checked').value;
            if (payload.quien_recoge === 'otra') {
                payload.recoge_dni = document.getElementById('coRecogeDni').value.trim();
                payload.recoge_nombre = document.getElementById('coRecogeNombre').value.trim();
            }
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
                window.location.href = '/cuenta?volver=checkout';
                return false;
            }
            showError(json.mensaje || 'No pudimos reservar tu pedido.');
            return false;
        }

        pedidoCreado = { id_pedido: json.id_pedido, token: json.token };
        activarProteccionAbandono();
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

        // Cuando Krypton envía el pago, la página navega a V_checkout_success.php.
        // Marcar el pago como en progreso antes de eso evita que el pagehide libere
        // el stock de un cobro que sí se está procesando.
        const contenedorForm = document.getElementById('izipay-form-container');
        if (contenedorForm && !contenedorForm.dataset.proteccionAbandono) {
            contenedorForm.dataset.proteccionAbandono = '1';
            contenedorForm.addEventListener('submit', () => {
                pagoEnProgreso = true;
                desactivarProteccionAbandono();
            }, true);
        }

        return true;
    }

    function bloquearDatosEntrega() {
        document.querySelectorAll('#cardDatos input, #cardDatos select, #cardDatos textarea')
            .forEach(el => { el.disabled = true; });
        document.querySelectorAll('#cardEntrega input, #cardEntrega select, #cardEntrega textarea')
            .forEach(el => { el.disabled = true; });
    }

    // ─────────────────────────────────────────────────────────────
    // 2.5 Método de pago: Tarjeta (Izipay) o QR (TAYPI)
    // ─────────────────────────────────────────────────────────────
    let checkoutJSCargado = false;

    function metodoPagoSeleccionado() {
        return document.querySelector('input[name="metodoPago"]:checked').value;
    }

    document.querySelectorAll('input[name="metodoPago"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const esQr = metodoPagoSeleccionado() === 'qr';
            document.getElementById('metodoPagoNota').innerHTML = esQr
                ? '<i class="bi bi-info-circle"></i> Escanea el código QR con Yape, Plin o tu app bancaria.'
                : '<i class="bi bi-info-circle"></i> Paga con tu tarjeta de débito o crédito.';
        });
    });

    // Carga checkout.js de TAYPI. El host depende del modo (sandbox vs producción):
    // con claves test_ el script debe venir de sandbox.taypi.pe, si no checkout.js
    // valida la public key contra app.taypi.pe y responde "API key inválida".
    // El backend expone la URL correcta en json.checkout_js_url.
    function cargarCheckoutJS(checkoutJsUrl) {
        if (checkoutJSCargado || window.Taypi) { checkoutJSCargado = true; return Promise.resolve(); }
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = checkoutJsUrl;
            s.onload = () => { checkoutJSCargado = true; resolve(); };
            s.onerror = () => reject(new Error('No se pudo cargar la pasarela de pago QR.'));
            document.head.appendChild(s);
        });
    }

    // Paso B (QR): crear el pago en TAYPI y abrir el modal con el QR.
    async function montarPagoQR() {
        const res  = await fetch('../../controllers/C_Taypi.php?action=crear_pago', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(pedidoCreado),
        });
        const json = await res.json();

        if (!json.success || !json.checkout_token) {
            showError(json.mensaje || 'No pudimos iniciar el pago QR.');
            return false;
        }

        // Los datos de entrega ya están comprometidos en el pedido.
        bloquearDatosEntrega();
        document.getElementById('reservaNota').style.display = '';
        document.getElementById('btn-pay').style.display = 'none';

        try {
            await cargarCheckoutJS(json.checkout_js_url);
        } catch (e) {
            showError(e.message);
            return false;
        }

        if (!window.Taypi) {
            showError('No se pudo cargar la pasarela de pago QR.');
            return false;
        }

        window.Taypi.publicKey = json.public_key;

        window.Taypi.open({
            sessionToken: json.checkout_token,
            onSuccess: (result) => {
                // El callback del navegador NO es la fuente de verdad: el webhook
                // confirma el cobro en servidor. Aquí solo se redirige al comprobante.
                pagoEnProgreso = true;   // no liberar stock en pagehide
                desactivarProteccionAbandono();
                window.location.href = `/confirmacion?id=${pedidoCreado.id_pedido}&t=${encodeURIComponent(pedidoCreado.token)}`;
            },
            onExpired: () => {
                showError('El código QR expiró. Intenta de nuevo.');
                document.getElementById('btn-pay').style.display = '';
            },
            onClose: () => {
                document.getElementById('btn-pay').style.display = '';
            },
            onError: (error) => {
                showError(error && error.message ? error.message : 'Ocurrió un error al mostrar el QR.');
                document.getElementById('btn-pay').style.display = '';
            },
        });

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // 2.6 Comprobante: Boleta vs Factura (RUC validado contra SUNAT antes de pagar)
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
            document.getElementById('bloqueRecogida').style.display = esColegio ? 'none' : 'block';
        });
    });

    // Quién recoge en tienda: el comprador o una persona con su DNI y nombres.
    document.querySelectorAll('input[name="quienRecoge"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const esOtra = document.getElementById('recogeOtra').checked;
            document.getElementById('camposRecogeOtra').style.display = esOtra ? 'flex' : 'none';
            document.getElementById('recogeYoMsg').style.display = esOtra ? 'none' : 'block';
        });
    });
    ['coRecogeDni', 'coRecogeNombre'].forEach(id => {
        document.getElementById(id).addEventListener('input', () => {
            document.getElementById(id).classList.remove('is-invalid');
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
        if (document.getElementById('entregaColegio').checked) {
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

        // Recojo en tienda: si recoge otra persona, DNI de 8 dígitos + nombres.
        if (document.getElementById('recogeOtra').checked) {
            const dni = document.getElementById('coRecogeDni').value.trim();
            const nombre = document.getElementById('coRecogeNombre').value.trim();
            let valid = true;
            if (!/^\d{8}$/.test(dni)) {
                document.getElementById('coRecogeDni').classList.add('is-invalid');
                valid = false;
            } else {
                document.getElementById('coRecogeDni').classList.remove('is-invalid');
            }
            if (nombre === '') {
                document.getElementById('coRecogeNombre').classList.add('is-invalid');
                valid = false;
            } else {
                document.getElementById('coRecogeNombre').classList.remove('is-invalid');
            }
            return valid;
        }
        return true;
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
            showError('Completa los datos de entrega: estudiante (colegio) o DNI y nombres de quien recoge (tienda).');
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

            const ok = metodoPagoSeleccionado() === 'qr'
                ? await montarPagoQR()
                : await montarFormularioPago();

            if (!ok) {
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
    // Protección contra abandono: si el cliente cierra/abandona la ventana con su
    // pedido pendiente (stock reservado), se muestra una alerta y, si confirma
    // salir, se libera el stock de inmediato vía beacon (no esperar los 10 min de
    // expiración). El endpoint verifica contra la pasarela antes de liberar, así
    // que cerrar justo después de pagar NO pierde el pedido (se confirma).
    let reservaActiva   = false;
    let pagoEnProgreso  = false;   // true desde que el pago se envía/confirma

    function activarProteccionAbandono() {
        if (reservaActiva) return;
        reservaActiva = true;
        window.addEventListener('beforeunload', manejarBeforeUnload);
        window.addEventListener('pagehide', manejarPageHide);
    }

    function desactivarProteccionAbandono() {
        reservaActiva = false;
        window.removeEventListener('beforeunload', manejarBeforeUnload);
        window.removeEventListener('pagehide', manejarPageHide);
    }

    function manejarBeforeUnload(e) {
        if (!reservaActiva || pagoEnProgreso) return;
        e.preventDefault();
        // La mayoría de navegadores ignoran el texto y muestran un diálogo propio;
        // el returnValue sigue siendo la forma estándar de pedir confirmación.
        e.returnValue = 'Tienes un pedido pendiente de pago. Si sales ahora se liberará el stock reservado.';
    }

    function manejarPageHide() {
        // pagehide se dispara al cerrar pestaña/ventana o navegar fuera. Si el pago
        // ya se envió o confirmó no se libera nada (el endpoint igualmente revalida).
        if (!reservaActiva || pagoEnProgreso || !pedidoCreado) return;
        try {
            navigator.sendBeacon(
                `../../controllers/C_Ecommerce.php?action=cancelar_pedido&id=${pedidoCreado.id_pedido}&t=${encodeURIComponent(pedidoCreado.token)}`
            );
        } catch (e) { /* best-effort: el barrido de expirados lo cubre igual */ }
    }

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
