<?php
// Generación del Comprobante para Impresión - Standalone
// Valida sesión activa; inicia si es que no se ha arrancado previamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado.');
}
header('X-Frame-Options: SAMEORIGIN');

// Cargar la conexión y el modelo de Ventas
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/config/sunat.php';
require_once dirname(__DIR__) . '/config/marca.php';
require_once dirname(__DIR__) . '/models/M_Venta.php';

$id_venta       = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_separacion  = isset($_GET['separacion']) ? intval($_GET['separacion']) : 0;
$format         = isset($_GET['format']) ? $_GET['format'] : '80mm'; // Formatos válidos: 80mm | 58mm | a4

if ($id_venta <= 0 && $id_separacion <= 0) { die('Falta el parámetro id o separacion.'); }

$model  = M_Venta::singleton();
$nombresMetodo = [1 => 'Efectivo', 2 => 'Yape/Plin', 3 => 'Tarjeta'];

// Banner "ABONO A SEPARACIÓN" + resumen mercadería/pagado/saldo, solo cuando se
// imprime UNA venta puntual (anticipo o abono) que pertenece a una separación.
$infoSeparacionAbono = null;
// Tabla de abonos + resumen "Total · Anticipo · Saldo cancelado", solo en el
// comprobante FINAL de una separación ya despachada (impreso por ?separacion=).
$abonosParaImprimir  = null;

if ($id_separacion > 0) {
    // ========= MODO: Comprobante final de una separación (mercadería completa) =========
    require_once dirname(__DIR__) . '/models/M_Separacion.php';
    $sep = M_Separacion::singleton()->obtenerPorId($id_separacion);
    if (!$sep) { die('Separación no encontrada.'); }

    $detalles = $model->obtenerDetallesPorVenta($sep['id_venta_anticipo']);
    $codigo   = $sep['codigo'];
    $venta    = ['cliente' => $sep['cliente'], 'numero_documento' => $sep['numero_documento'], 'vendedor' => $sep['vendedor']];

    $tipoComprobanteFinal = $sep['tipo_comprobante_final'] ?: 3;
    $tipo_doc = $tipoComprobanteFinal == 1 ? 'BOLETA DE VENTA'
              : ($tipoComprobanteFinal == 2 ? 'FACTURA ELECTRÓNICA' : 'NOTA DE VENTA');
    $fecha_emision = date('Y-m-d / H:i:s', strtotime($sep['fecha_despacho'] ?: $sep['fecha']));
    $fecha_venc    = date('Y-m-d', strtotime($sep['fecha']));
    $subtotal = $sep['total'] / 1.18;
    $igv      = $sep['total'] - $subtotal;
    $total    = $sep['total'];
    $estado   = $sep['estado'] == 2 ? 'DESPACHADA' : ($sep['estado'] == 1 ? 'PENDIENTE' : 'ANULADA');

    $abonosParaImprimir = $sep['abonos'];
    $anticipoMonto = 0.0;
    $saldoCancelado = 0.0;
    foreach ($abonosParaImprimir as $a) {
        if ($a['es_anticipo'] == 1) { $anticipoMonto += (float) $a['total']; }
        else { $saldoCancelado += (float) $a['total']; }
    }
    $formaPagoTexto = sprintf(
        'Total: S/%s · Anticipo: S/%s · Saldo cancelado: S/%s',
        number_format($total, 2), number_format($anticipoMonto, 2), number_format($saldoCancelado, 2)
    );
} else {
    // ========= MODO: Comprobante de una venta puntual (el flujo de siempre) =========
    // Restricción por rol: Vendedor regular solo puede acceder a sus propios comprobantes
    $id_vendedor = ($_SESSION['rol'] !== 'Administrador') ? $_SESSION['id_usuario'] : null;
    $ventas = $model->listar($id_vendedor);
    $venta  = null;
    foreach ($ventas as $v) {
        if ($v['id_venta'] == $id_venta) { $venta = $v; break; }
    }
    if (!$venta) { die('Venta no encontrada.'); }

    // Recuperar productos vendidos de la transacción y variables globales de facturación
    $detalles = $model->obtenerDetallesPorVenta($id_venta);
    $codigo   = 'V-' . str_pad($venta['id_venta'], 6, '0', STR_PAD_LEFT);

    // Desglose real de pago mixto: una o más líneas en pagos_venta (efectivo/yape/tarjeta).
    $stmtPagos = Conexion::singleton()->getConexion()->prepare(
        "SELECT metodo_pago, monto FROM pagos_venta WHERE id_venta = ? ORDER BY id_pago"
    );
    $stmtPagos->execute([$id_venta]);
    $pagosVenta = $stmtPagos->fetchAll();
    $formaPagoTexto = implode(' · ', array_map(function ($p) use ($nombresMetodo) {
        $nombre = $nombresMetodo[$p['metodo_pago']] ?? 'Otro';
        return $nombre . ': S/' . number_format($p['monto'], 2);
    }, $pagosVenta));
    if ($formaPagoTexto === '') {
        $formaPagoTexto = 'EFECTIVO / TRANSFERENCIA';
    }
    $tipo_doc = $venta['tipo_comprobante'] == 1 ? 'BOLETA DE VENTA'
              : ($venta['tipo_comprobante'] == 2 ? 'FACTURA ELECTRÓNICA' : 'NOTA DE VENTA');
    $fecha_emision  = date('Y-m-d / H:i:s', strtotime($venta['fecha']));
    $fecha_venc     = date('Y-m-d', strtotime($venta['fecha_vencimiento'] ?: $venta['fecha']));
    // Persistidos al registrar la venta (M_Venta::registrarEnTransaccion()), no
    // calculados aquí: SUNAT valida el IGV al céntimo y un total/1.18 al vuelo
    // puede descuadrar por redondeo frente a lo que se envió a SUNAT.
    $subtotal = (float) $venta['subtotal'];
    $igv      = (float) $venta['igv'];
    $total    = $venta['total'];
    $estado   = $venta['estado'] == 1 ? 'COMPLETADA' : 'ANULADA';

    // Si esta venta puntual es un anticipo/abono de una separación, se imprime como
    // tal: banner + Valor mercadería / Pagado hoy / Saldo pendiente.
    $stmtInfoSep = Conexion::singleton()->getConexion()->prepare(
        "SELECT s.id_separacion, s.codigo, s.total AS total_mercaderia,
                IFNULL((SELECT SUM(v2.total) FROM ventas v2 WHERE v2.id_separacion = s.id_separacion AND v2.estado = 1), 0) AS abonado
         FROM ventas v INNER JOIN separaciones s ON s.id_separacion = v.id_separacion
         WHERE v.id_venta = ?"
    );
    $stmtInfoSep->execute([$id_venta]);
    $infoSeparacionAbono = $stmtInfoSep->fetch() ?: null;
}

// Bloque de pagos estilo tukifac: una línea por cada pago (PAGOS:) + saldo.
$pagosLista = [];
$saldoPendiente = 0.0;
if ($id_separacion > 0) {
    foreach ($abonosParaImprimir as $a) {
        $pagosLista[] = '- ' . date('Y-m-d', strtotime($a['fecha'])) . ' - '
            . ($a['es_anticipo'] == 1 ? 'Anticipo' : 'Abono') . ' - S/ ' . number_format($a['total'], 2);
    }
    $saldoPendiente = max(0, $total - $anticipoMonto - $saldoCancelado);
} else {
    $fechaPago = date('Y-m-d', strtotime($venta['fecha']));
    $pagadoVenta = 0.0;
    foreach ($pagosVenta as $p) {
        $nombre = $nombresMetodo[$p['metodo_pago']] ?? 'Otro';
        $monto  = (float) $p['monto'];
        $pagadoVenta += $monto;
        $pagosLista[] = '- ' . $fechaPago . ' - ' . $nombre . ' - S/ ' . number_format($monto, 2);
    }
    $saldoPendiente = max(0, (float) $total - $pagadoVenta);
}

// Ajustes del ancho y tipografía para formatos de ticketeras térmicas
$isTicket = ($format === '80mm' || $format === '58mm');
$ticketW  = $format === '58mm' ? '56mm' : '76mm';
$ticketFs = $format === '58mm' ? '9px'  : '11px';

// Serie-correlativo real, hash y código QR SUNAT — solo aplican a una venta
// puntual con serie asignada (un comprobante que realmente se envía a SUNAT).
// El comprobante final de una separación combina varias ventas y no es en sí
// mismo un documento SUNAT, así que no lleva estos elementos.
$esComprobanteSunat = !$id_separacion && !empty($venta['serie']);
$codigoReal = $esComprobanteSunat
    ? ($venta['serie'] . '-' . str_pad((string) $venta['correlativo'], 8, '0', STR_PAD_LEFT))
    : $codigo;
$qrValor = '';
if ($esComprobanteSunat) {
    $stmtCli = Conexion::singleton()->getConexion()->prepare(
        "SELECT p.tipo_documento, p.numero_documento FROM ventas v
         LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
         LEFT JOIN personas p ON c.id_persona = p.id_persona
         WHERE v.id_venta = ?"
    );
    $stmtCli->execute([$id_venta]);
    $cli = $stmtCli->fetch();
    $tipoDocSunat = $venta['tipo_comprobante'] == 2 ? '01' : '03';
    $catalogo06 = [1 => '1', 2 => '6', 3 => '7'];
    $tipoDocCliente = empty($cli['numero_documento']) ? '0' : ($catalogo06[(int) $cli['tipo_documento']] ?? '1');
    $numDocCliente = $cli['numero_documento'] ?: '00000000';
    // Formato QR SUNAT: RUC|tipoDoc|serie|correlativo|IGV|total|fechaEmision|tipoDocReceptor|numDocReceptor|hash
    $qrValor = implode('|', [
        SUNAT_RUC, $tipoDocSunat, $venta['serie'], $venta['correlativo'],
        number_format($igv, 2, '.', ''), number_format($total, 2, '.', ''),
        date('Y-m-d', strtotime($venta['fecha'])),
        $tipoDocCliente, $numDocCliente, $venta['sunat_hash'] ?? '',
    ]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?php echo $codigo; ?> — <?php echo $tipo_doc; ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

<?php if (!$isTicket): /* ===== ESTILOS PARA FORMATO A4 ===== */ ?>
@page { size: A4; margin: 12mm 15mm; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #000;
    background: #fff;
}

/* Encabezado estilo tukifac: logo a la izquierda, razón social + datos a la derecha */
.header { margin-bottom: 14px; }
.header-main {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    text-align: left;
}
.header-main .logo {
    width: 110px;
    height: auto;
    flex-shrink: 0;
}
.header-main .brand-left { font-size: 8.5pt; line-height: 1.5; }
.header-main .brand-left .biz-name {
    font-size: 13pt;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.header-main .brand-right { text-align: right; margin-left: auto; }
.header-main .doc-type { font-size: 11pt; font-weight: 800; text-transform: uppercase; }
.header-main .doc-code { font-size: 12pt; font-weight: bold; margin-top: 3px; }

/* Separadores */
.sep { border: none; border-top: 1px dotted #555; margin: 12px 0; }

/* Grid de Información (Cliente / Doc / Dirección | Fecha / Vendedor / Estado) */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 30px;
    margin-bottom: 14px;
}
.info-row {
    display: flex;
    gap: 6px;
    padding: 2px 0;
    font-size: 9pt;
}
.info-label { white-space: nowrap; }
.info-value { font-weight: 600; }

/* Tabla de Productos (cabecera con rayas punteadas como tukifac) */
.prod-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    font-size: 8.5pt;
}
.prod-table thead tr {
    border-top: 1px solid #000;
    border-bottom: 1px solid #000;
}
.prod-table th {
    padding: 4px 5px;
    text-align: left;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 7.5pt;
}
.prod-table th.r { text-align: right; }
.prod-table tbody tr { border-bottom: 1px dotted #bbb; }
.prod-table td { padding: 4px 5px; vertical-align: top; }
.prod-table td.r { text-align: right; }

/* Tabla de Importes */
.totals-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}
.totals-table {
    width: 240px;
    border-collapse: collapse;
    font-size: 9.5pt;
}
.totals-table td { padding: 3px 6px; }
.totals-table td.label { text-align: right; padding-right: 12px; }
.totals-table td.value { text-align: right; font-weight: 600; white-space: nowrap; }
.totals-table .grand td {
    border-top: 1.5px solid #000;
    font-weight: bold;
    font-size: 10.5pt;
}
.totals-table .split td { color: #444; font-size: 8.5pt; }

/* Pie de Página (estilo tukifac: CONDICIÓN DE PAGO + PAGOS) */
.footer { margin-top: 18px; font-size: 9pt; }
.footer p { margin-bottom: 3px; }
.footer .pago-line { margin-left: 12px; }

<?php else: /* ===== ESTILOS PARA TICKETERAS TÉRMICAS ===== */ ?>
@page { size: <?php echo $ticketW; ?> auto; margin: 3mm; }
body {
    font-family: 'Courier New', Courier, monospace;
    font-size: <?php echo $ticketFs; ?>;
    color: #000;
    background: #fff;
    width: <?php echo $ticketW; ?>;
}
.tc { text-align: center; }
.tr { text-align: right; }
.tl { text-align: left; }
.bold { font-weight: bold; }
p { margin: 1px 0; }

.brand-logo {
    font-family: Georgia, serif;
    font-size: <?php echo $format === '58mm' ? '22pt' : '28pt'; ?>;
    font-weight: 900;
    letter-spacing: -1px;
    text-align: center;
    line-height: 1;
    margin-bottom: 4px;
    color: #000;
}
.brand-logo-img {
    display: block;
    margin: 0 auto 4px auto;
    width: <?php echo $format === '58mm' ? '40px' : '56px'; ?>;
    height: auto;
}
.biz-info { text-align: center; line-height: 1.5; margin-bottom: 4px; }
.biz-info .biz-name { font-weight: bold; text-transform: uppercase; }

.sep  { border: none; border-top: 1px dashed #000; margin: 6px 0; }
.sep2 { border: none; border-top: 1px solid #000; margin: 4px 0; }

.doc-box { text-align: center; margin: 4px 0; }
.doc-box .doc-type { font-weight: bold; font-size: 1.1em; }
.doc-box .doc-code { font-weight: bold; }

/* Tabla de Metadatos */
.info-block { margin: 4px 0; }
.info-block table { width: 100%; border-collapse: collapse; }
.info-block td { padding: 1px 2px; vertical-align: top; }
.info-block td:first-child { font-weight: bold; white-space: nowrap; padding-right: 4px; }

/* Tabla de Productos del ticket térmico */
.prod-table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: <?php echo $ticketFs; ?>; }
.prod-table th { text-align: left; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 2px 1px; font-size: <?php echo $format === '58mm' ? '7px' : '8px'; ?>; }
.prod-table th.r { text-align: right; }
.prod-table td { padding: 3px 1px; border-bottom: 1px dotted #aaa; vertical-align: top; }
.prod-table td.r { text-align: right; }

.total-row { display: flex; justify-content: space-between; font-weight: bold; padding: 2px 0; }
.total-row.grand { font-size: 1.15em; border-top: 1px solid #000; margin-top: 4px; padding-top: 4px; }
.total-row.split { font-size: 0.9em; font-weight: normal; color: #444; }

.footer-msg { text-align: center; margin-top: 8px; font-weight: bold; }
<?php endif; ?>
</style>
</head>
<body>

<?php if (!$isTicket): /* ========= MAQUETADO A4 (estilo tukifac) ========= */ ?>
<div class="header">
    <div class="header-main">
        <img class="logo" src="<?php echo htmlspecialchars(marcaLogo('LOGO_CLARO', '../assets/logo.svg')); ?>" alt="<?php echo htmlspecialchars(SUNAT_NOMBRE_COMERCIAL ?: 'NISSI'); ?>">
        <div class="brand-left">
            <div class="biz-name"><?php echo htmlspecialchars(SUNAT_RAZON_SOCIAL ?: 'Confecciones NISSI'); ?></div>
            <div>RUC <?php echo htmlspecialchars(SUNAT_RUC_EMISOR ?: '20000000000'); ?></div>
            <div><?php echo htmlspecialchars(SUNAT_DIRECCION ?: 'Av. Principal S/N'); ?></div>
            <div><?php echo htmlspecialchars(SUNAT_EMAIL ?: ''); ?></div>
            <div><?php echo htmlspecialchars(SUNAT_TELEFONO ?: ''); ?></div>
        </div>
        <div class="brand-right">
            <div class="doc-type"><?php echo $tipo_doc; ?></div>
            <div class="doc-code"><?php echo $codigoReal; ?></div>
        </div>
    </div>
</div>

<?php if ($infoSeparacionAbono): ?>
<div style="background:#fef3c7; border:1.5px dashed #d97706; padding:8px 14px; margin-bottom:14px; font-size:9.5pt; font-weight:bold; text-align:center; color:#92400e;">
    ABONO A SEPARACIÓN <?php echo htmlspecialchars($infoSeparacionAbono['codigo']); ?>
</div>
<?php endif; ?>

<hr class="sep">

<div class="info-grid">
    <div>
        <div class="info-row"><span class="info-label">Cliente:</span><span class="info-value"><?php echo htmlspecialchars($venta['cliente']); ?></span></div>
        <div class="info-row"><span class="info-label">Doc.:</span><span class="info-value"><?php echo htmlspecialchars($venta['numero_documento'] ?: '—'); ?></span></div>
        <div class="info-row"><span class="info-label">Dirección:</span><span class="info-value">—</span></div>
        <div class="info-row"><span class="info-label">Estado:</span><span class="info-value"><?php echo $estado; ?></span></div>
    </div>
    <div>
        <div class="info-row"><span class="info-label">Fecha de emisión:</span><span class="info-value"><?php echo $fecha_emision; ?></span></div>
        <div class="info-row"><span class="info-label">Fecha vencimiento:</span><span class="info-value"><?php echo $fecha_venc; ?></span></div>
        <div class="info-row"><span class="info-label">Vendedor:</span><span class="info-value"><?php echo htmlspecialchars($venta['vendedor']); ?></span></div>
        <div class="info-row"><span class="info-label">Forma de pago:</span><span class="info-value"><?php echo htmlspecialchars($formaPagoTexto); ?></span></div>
        <?php if ($infoSeparacionAbono): ?>
        <div class="info-row"><span class="info-label">Mercadería:</span><span class="info-value">S/ <?php echo number_format($infoSeparacionAbono['total_mercaderia'], 2); ?></span></div>
        <div class="info-row"><span class="info-label">Pagado hoy:</span><span class="info-value">S/ <?php echo number_format($total, 2); ?></span></div>
        <div class="info-row"><span class="info-label">Saldo:</span><span class="info-value">S/ <?php echo number_format(max(0, $infoSeparacionAbono['total_mercaderia'] - $infoSeparacionAbono['abonado']), 2); ?></span></div>
        <?php endif; ?>
    </div>
</div>

<table class="prod-table">
    <thead>
        <tr>
            <th style="width:7%">COD.</th>
            <th style="width:7%">CANT.</th>
            <th style="width:8%">UNIDAD</th>
            <th>DESCRIPCIÓN</th>
            <th style="width:11%">MARCA</th>
            <th class="r" style="width:10%">P.UNIT</th>
            <th class="r" style="width:8%">DTO.</th>
            <th class="r" style="width:10%">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalles as $d): ?>
        <tr>
            <td><?php echo htmlspecialchars($d['id_producto']); ?></td>
            <td><?php echo number_format($d['piezas'], 2); ?></td>
            <td><?php echo htmlspecialchars($d['abreviatura']); ?></td>
            <td><?php echo htmlspecialchars($d['producto_nombre']); ?></td>
            <td>—</td>
            <td class="r"><?php echo number_format($d['precio_venta'], 2); ?></td>
            <td class="r">0.00</td>
            <td class="r"><?php echo number_format($d['subtotal'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($abonosParaImprimir): ?>
<table class="prod-table" style="margin-bottom: 14px;">
    <thead>
        <tr>
            <th>FECHA</th>
            <th>TIPO</th>
            <th class="r">MONTO</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($abonosParaImprimir as $a): ?>
        <tr>
            <td><?php echo date('Y-m-d', strtotime($a['fecha'])); ?></td>
            <td><?php echo $a['es_anticipo'] == 1 ? 'Anticipo' : 'Abono'; ?></td>
            <td class="r">S/ <?php echo number_format($a['total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="totals-wrap">
    <table class="totals-table">
        <tr>
            <td class="label">OP. EXONERADAS:</td>
            <td class="value">S/ <?php echo number_format($total, 2); ?></td>
        </tr>
        <tr class="grand">
            <td class="label">TOTAL A PAGAR: S/</td>
            <td class="value"><?php echo number_format($total, 2); ?></td>
        </tr>
    </table>
</div>

<?php if ($esComprobanteSunat): ?>
<div style="display:flex; align-items:center; gap:14px; margin-top:16px;">
    <div id="qrSunat"></div>
    <div style="font-size:8pt; color:#444; line-height:1.5;">
        <p>Representación impresa del comprobante electrónico.</p>
        <p>Consulta la validez en <strong>www.sunat.gob.pe</strong>.</p>
        <p style="word-break:break-all;">Hash: <?php echo htmlspecialchars($venta['sunat_hash'] ?? '—'); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="footer">
    <p><strong>CONDICIÓN DE PAGO:</strong> Contado</p>
    <p><strong>PAGOS:</strong></p>
    <?php foreach ($pagosLista as $lineaPago): ?>
    <p class="pago-line"><?php echo htmlspecialchars($lineaPago); ?></p>
    <?php endforeach; ?>
    <p><strong>SALDO:</strong> S/ <?php echo number_format($saldoPendiente, 2); ?></p>
</div>

<p style="margin-top:14px; font-size:8.5pt; color:#444;">
    Para consultar el comprobante ingresar a
    <?php echo htmlspecialchars(SUNAT_CONSULTA_URL); ?>
</p>

<?php else: /* ========= MAQUETADO TICKETERAS TÉRMICAS (estilo tukifac) ========= */ ?>

<div class="biz-info">
    <img class="brand-logo-img" src="<?php echo htmlspecialchars(marcaLogo('LOGO_CLARO', '../assets/logo.svg')); ?>" alt="<?php echo htmlspecialchars(SUNAT_NOMBRE_COMERCIAL ?: 'NISSI'); ?>">
    <p class="biz-name"><?php echo htmlspecialchars(SUNAT_RAZON_SOCIAL ?: 'Confecciones NISSI'); ?></p>
    <p>RUC <?php echo htmlspecialchars(SUNAT_RUC_EMISOR ?: '20000000000'); ?></p>
    <p><?php echo htmlspecialchars(SUNAT_DIRECCION ?: 'Av. Principal S/N'); ?></p>
    <p><?php echo htmlspecialchars(SUNAT_EMAIL ?: ''); ?></p>
    <p><?php echo htmlspecialchars(SUNAT_TELEFONO ?: ''); ?></p>
</div>

<hr class="sep">

<div class="doc-box">
    <div class="doc-type"><?php echo $tipo_doc; ?></div>
    <div class="doc-code"><?php echo $codigoReal; ?></div>
</div>

<?php if ($infoSeparacionAbono): ?>
<hr class="sep">
<p class="tc bold" style="font-size: 1.05em;">ABONO A SEPARACIÓN <?php echo htmlspecialchars($infoSeparacionAbono['codigo']); ?></p>
<?php endif; ?>

<hr class="sep">

<div class="info-block">
    <table>
        <tr><td>F. Emisión:</td><td><?php echo $fecha_emision; ?></td></tr>
        <tr><td>F. Venc.:</td><td><?php echo $fecha_venc; ?></td></tr>
        <tr><td>Cliente:</td><td><?php echo htmlspecialchars($venta['cliente']); ?></td></tr>
        <tr><td>Doc.:</td><td><?php echo htmlspecialchars($venta['numero_documento']); ?></td></tr>
        <tr><td>Vendedor:</td><td><?php echo htmlspecialchars($venta['vendedor']); ?></td></tr>
        <tr><td>F. Pago:</td><td><?php echo htmlspecialchars($formaPagoTexto); ?></td></tr>
        <tr><td>Estado:</td><td><?php echo $estado; ?></td></tr>
        <?php if ($infoSeparacionAbono): ?>
        <tr><td>Mercadería:</td><td>S/ <?php echo number_format($infoSeparacionAbono['total_mercaderia'], 2); ?></td></tr>
        <tr><td>Pagado hoy:</td><td>S/ <?php echo number_format($total, 2); ?></td></tr>
        <tr><td>Saldo:</td><td>S/ <?php echo number_format(max(0, $infoSeparacionAbono['total_mercaderia'] - $infoSeparacionAbono['abonado']), 2); ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<table class="prod-table">
    <thead>
        <tr>
            <?php if ($format !== '58mm'): ?><th>COD</th><?php endif; ?>
            <th>CANT</th>
            <th>UNID</th>
            <th>DESCRIPCIÓN</th>
            <th class="r">P.UNIT</th>
            <th class="r">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalles as $d): ?>
        <tr>
            <?php if ($format !== '58mm'): ?><td><?php echo htmlspecialchars($d['id_producto']); ?></td><?php endif; ?>
            <td><?php echo number_format($d['piezas'], 2); ?></td>
            <td><?php echo htmlspecialchars($d['abreviatura']); ?></td>
            <td><?php echo htmlspecialchars($d['producto_nombre']); ?></td>
            <td class="r"><?php echo number_format($d['precio_venta'], 2); ?></td>
            <td class="r"><?php echo number_format($d['subtotal'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($abonosParaImprimir): ?>
<table class="prod-table">
    <thead>
        <tr><th>FECHA</th><th>TIPO</th><th class="r">MONTO</th></tr>
    </thead>
    <tbody>
        <?php foreach ($abonosParaImprimir as $a): ?>
        <tr>
            <td><?php echo date('Y-m-d', strtotime($a['fecha'])); ?></td>
            <td><?php echo $a['es_anticipo'] == 1 ? 'Anticipo' : 'Abono'; ?></td>
            <td class="r">S/ <?php echo number_format($a['total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="total-row"><span>OP. EXONERADAS:</span><span>S/ <?php echo number_format($total, 2); ?></span></div>
<div class="total-row grand"><span>TOTAL A PAGAR: S/</span><span><?php echo number_format($total, 2); ?></span></div>

<?php if ($esComprobanteSunat): ?>
<hr class="sep">
<div id="qrSunat" class="tc" style="margin: 4px 0;"></div>
<p class="tc" style="font-size: 0.8em;">Representación impresa del comprobante electrónico.</p>
<p class="tc" style="font-size: 0.7em; word-break: break-all;">Hash: <?php echo htmlspecialchars($venta['sunat_hash'] ?? '—'); ?></p>
<?php endif; ?>

<hr class="sep" style="margin-top:8px;">
<p style="text-align:left;"><strong>CONDICIÓN DE PAGO:</strong> Contado</p>
<p><strong>PAGOS:</strong></p>
<?php foreach ($pagosLista as $lineaPago): ?>
<p style="margin-left:12px;"><?php echo htmlspecialchars($lineaPago); ?></p>
<?php endforeach; ?>
<p><strong>SALDO:</strong> S/ <?php echo number_format($saldoPendiente, 2); ?></p>
<hr class="sep">
<p class="footer-msg">Para consultar el comprobante ingresar a:</p>
<p class="tc" style="word-break: break-all;"><?php echo htmlspecialchars(SUNAT_CONSULTA_URL); ?></p>
<hr class="sep">
<p class="footer-msg">¡Gracias por su compra!</p>

<?php endif; ?>

<?php if ($esComprobanteSunat): ?>
<!-- Librería de QR solo cuando hace falta: un ticket que nunca fue a SUNAT
     (Nota de Venta) no necesita cargarla. Mismo patrón que V_reportes.php,
     que ya trae librerías por CDN (jsPDF, xlsx) sin pasar por Composer. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('qrSunat'), {
        text: <?php echo json_encode($qrValor); ?>,
        width: <?php echo $isTicket ? 90 : 110; ?>,
        height: <?php echo $isTicket ? 90 : 110; ?>,
        correctLevel: QRCode.CorrectLevel.M,
    });
</script>
<?php endif; ?>

<!-- Disparar automáticamente la impresión del navegador si se incluye el parámetro print=1 en la URL -->
<script>
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('print') === '1') {
        window.onload = () => window.print();
    }
</script>
</body>
</html>
