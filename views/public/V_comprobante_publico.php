<?php
/**
 * views/public/V_comprobante_publico.php
 * Comprobante público por enlace (estilo tukifac).
 *
 * Standalone (no pasa por index.php): lo invoca C_ComprobantePublico.php con la
 * variable $comprobante ya resuelta y validada por token (null = no encontrado).
 * Sin sesión, sin tocar estado: página de solo lectura para clientes anónimos.
 *
 * Reutiliza el tema de la tienda (tienda.css + marcaCss) para verse bien en
 * celular, donde el cliente abrirá el enlace compartido.
 */

$nombresMetodo = [1 => 'Efectivo', 2 => 'Yape/Plin', 3 => 'Tarjeta'];
$tipoDoc       = 'BOLETA DE VENTA';
$codigo        = '';
$fechaEmision  = '';
$fechaVenc     = '';
$subtotal      = 0.0;
$igv           = 0.0;
$total         = 0.0;
$estado        = '';
$esComprobanteSunat = false;
$qrValor       = '';

if ($comprobante) {
    $tipoDoc = $comprobante['tipo_comprobante'] == 1 ? 'BOLETA DE VENTA'
             : ($comprobante['tipo_comprobante'] == 2 ? 'FACTURA ELECTRÓNICA' : 'NOTA DE VENTA');
    $codigo  = 'V-' . str_pad((string) $comprobante['id_venta'], 6, '0', STR_PAD_LEFT);
    $fechaEmision = date('d/m/Y H:i', strtotime($comprobante['fecha']));
    $fechaVenc    = date('d/m/Y', strtotime($comprobante['fecha_vencimiento'] ?: $comprobante['fecha']));
    $subtotal = (float) $comprobante['subtotal'];
    $igv      = (float) $comprobante['igv'];
    $total    = (float) $comprobante['total'];
    $estado   = $comprobante['estado'] == 1 ? 'COMPLETADA' : 'ANULADA';

    $esComprobanteSunat = !empty($comprobante['serie']);
    if ($esComprobanteSunat) {
        $codigo = $comprobante['serie'] . '-' . str_pad((string) $comprobante['correlativo'], 8, '0', STR_PAD_LEFT);
        $tipoDocSunat = $comprobante['tipo_comprobante'] == 2 ? '01' : '03';
        $catalogo06   = [1 => '1', 2 => '6', 3 => '7'];
        $tipoDocCliente = empty($comprobante['numero_documento'])
            ? '0'
            : ($catalogo06[(int) ($comprobante['tipo_documento'] ?? 1)] ?? '1');
        $numDocCliente = $comprobante['numero_documento'] ?: '00000000';
        $qrValor = implode('|', [
            SUNAT_RUC, $tipoDocSunat, $comprobante['serie'], $comprobante['correlativo'],
            number_format($igv, 2, '.', ''), number_format($total, 2, '.', ''),
            date('Y-m-d', strtotime($comprobante['fecha'])),
            $tipoDocCliente, $numDocCliente, $comprobante['sunat_hash'] ?? '',
        ]);
    }
}

$formaPagoTexto = '';
if ($comprobante && !empty($comprobante['pagos'])) {
    $formaPagoTexto = implode(' · ', array_map(function ($p) use ($nombresMetodo) {
        $nombre = $nombresMetodo[(int) $p['metodo_pago']] ?? 'Otro';
        return $nombre . ': S/' . number_format((float) $p['monto'], 2);
    }, $comprobante['pagos']));
}
if ($formaPagoTexto === '') { $formaPagoTexto = 'EFECTIVO / TRANSFERENCIA'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $codigo ? $codigo . ' — ' . htmlspecialchars($tipoDoc) : 'Comprobante — NISSI'; ?></title>
    <link rel="icon" type="image/svg+xml" href="../../assets/favicon-nissi.svg?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/tienda.css?v=4">
    <?php echo marcaCss(); ?>
    <style>
        body { background: var(--paper); color: var(--ink); }
        .page-wrap { padding: 48px 16px 60px; max-width: 860px; margin: 0 auto; }

        .comprobante-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 26px 30px;
            border-bottom: 1px solid var(--line);
            flex-wrap: wrap;
        }
        .doc-header img { width: 96px; height: auto; }
        .doc-title { text-align: right; }
        .doc-title .type {
            font-family: var(--font-display); font-weight: 700; font-size: 1.05rem;
            color: var(--navy); text-transform: uppercase; letter-spacing: .02em;
        }
        .doc-title .code { font-weight: 700; color: var(--accent); font-size: .95rem; }

        .body-block { padding: 22px 30px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 26px; margin-bottom: 18px; }
        .info-row { display: flex; gap: 6px; font-size: .9rem; padding: 2px 0; }
        .info-label { color: var(--muted); white-space: nowrap; }
        .info-value { font-weight: 600; color: var(--ink); word-break: break-word; }
        @media (max-width: 576px) { .info-grid { grid-template-columns: 1fr; } }

        .items-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .items-table thead { background: var(--bg-main); }
        .items-table th {
            padding: 9px 10px; text-align: left; font-size: .75rem; text-transform: uppercase;
            color: var(--muted); letter-spacing: .04em; border-bottom: 1px solid var(--line);
        }
        .items-table th.r, .items-table td.r { text-align: right; }
        .items-table td { padding: 10px; border-bottom: 1px solid var(--line); vertical-align: top; }

        .totals { display: flex; justify-content: flex-end; margin-top: 16px; }
        .totals-inner { width: 260px; }
        .totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: .9rem; }
        .totals-row.grand {
            border-top: 2px solid var(--navy); margin-top: 6px; padding-top: 10px;
            font-weight: 700; font-size: 1.05rem; color: var(--navy);
        }

        .estado-badge {
            display: inline-block; padding: 3px 12px; border-radius: 999px;
            font-size: .72rem; font-weight: 700; letter-spacing: .05em;
        }
        .estado-badge.ok { background: var(--sage-100); color: var(--sage); }
        .estado-badge.ko { background: var(--accent-100); color: var(--accent); }

        .foot-note { padding: 18px 30px 26px; text-align: center; color: var(--muted); font-size: .8rem; border-top: 1px solid var(--line); }
        .qr-block { display: flex; align-items: center; gap: 18px; margin-top: 18px; flex-wrap: wrap; }
        .qr-block .qr-txt { font-size: .78rem; color: var(--muted); line-height: 1.5; }

        .notfound { text-align: center; padding: 60px 20px; }
        .notfound i { font-size: 3rem; color: var(--accent); }
        .notfound h1 { font-family: var(--font-display); font-weight: 700; color: var(--navy); margin: 18px 0 8px; }
        .notfound p { color: var(--muted); margin: 0 auto; max-width: 420px; }
        @media print {
            body { background: #fff; }
            .page-wrap { padding: 0; max-width: none; }
            .comprobante-card { border: none; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
<?php if (!$comprobante): ?>
    <div class="comprobante-card notfound">
        <i class="bi bi-receipt-cutoff"></i>
        <h1>Comprobante no encontrado</h1>
        <p>El enlace es inválido o el comprobante ya no está disponible. Verifica que la dirección esté completa.</p>
    </div>
<?php else: ?>
    <div class="comprobante-card">
        <div class="doc-header">
            <img src="<?php echo htmlspecialchars(marcaLogo('LOGO_CLARO', '../../assets/logo-nissi.svg')); ?>" alt="<?php echo htmlspecialchars(marcaVar('nombre')); ?>">
            <div class="doc-title">
                <div class="type"><?php echo htmlspecialchars($tipoDoc); ?></div>
                <div class="code"><?php echo htmlspecialchars($codigo); ?></div>
            </div>
        </div>

        <div class="body-block">
            <div class="info-grid">
                <div>
                    <div class="info-row"><span class="info-label">Cliente:</span><span class="info-value"><?php echo htmlspecialchars($comprobante['cliente'] ?: 'Público General'); ?></span></div>
                    <div class="info-row"><span class="info-label">Doc.:</span><span class="info-value"><?php echo htmlspecialchars($comprobante['numero_documento'] ?: '—'); ?></span></div>
                    <div class="info-row"><span class="info-label">Estado:</span><span class="info-value"><span class="estado-badge <?php echo $comprobante['estado'] == 1 ? 'ok' : 'ko'; ?>"><?php echo htmlspecialchars($estado); ?></span></span></div>
                </div>
                <div>
                    <div class="info-row"><span class="info-label">Emisión:</span><span class="info-value"><?php echo htmlspecialchars($fechaEmision); ?></span></div>
                    <div class="info-row"><span class="info-label">Vencimiento:</span><span class="info-value"><?php echo htmlspecialchars($fechaVenc); ?></span></div>
                    <div class="info-row"><span class="info-label">Atendió:</span><span class="info-value"><?php echo htmlspecialchars($comprobante['vendedor']); ?></span></div>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th class="r">Cant.</th>
                        <th class="r">P.Unit</th>
                        <th class="r">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comprobante['detalles'] as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['producto_nombre']); ?></td>
                        <td class="r"><?php echo number_format($d['piezas'], 2); ?></td>
                        <td class="r"><?php echo number_format($d['precio_venta'], 2); ?></td>
                        <td class="r"><?php echo number_format($d['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <div class="totals-inner">
                    <div class="totals-row"><span>Subtotal</span><span>S/ <?php echo number_format($subtotal, 2); ?></span></div>
                    <div class="totals-row"><span>IGV (18%)</span><span>S/ <?php echo number_format($igv, 2); ?></span></div>
                    <div class="totals-row grand"><span>TOTAL</span><span>S/ <?php echo number_format($total, 2); ?></span></div>
                    <div class="totals-row" style="color:var(--muted); font-size:.8rem;"><span>Forma de pago</span><span><?php echo htmlspecialchars($formaPagoTexto); ?></span></div>
                </div>
            </div>

            <?php if ($esComprobanteSunat): ?>
            <div class="qr-block">
                <div id="qrSunat"></div>
                <div class="qr-txt">
                    <p>Representación impresa del comprobante electrónico.</p>
                    <p>Consulta su validez en <strong>www.sunat.gob.pe</strong>.</p>
                    <p style="word-break:break-all; margin-bottom:0;">Hash: <?php echo htmlspecialchars($comprobante['sunat_hash'] ?? '—'); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="foot-note">
            <button type="button" class="btn btn-outline-secondary btn-sm no-print" onclick="window.print()">
                <i class="bi bi-printer-fill me-1"></i> Imprimir / guardar PDF
            </button>
            <div class="mt-2"><?php echo htmlspecialchars(marcaVar('nombre')); ?> · Este comprobante se muestra solo a quien tiene el enlace.</div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php if ($comprobante && $esComprobanteSunat): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('qrSunat'), {
        text: <?php echo json_encode($qrValor); ?>,
        width: 108,
        height: 108,
        correctLevel: QRCode.CorrectLevel.M,
    });
</script>
<?php endif; ?>
</body>
</html>