<?php
// Generación del Comprobante para Impresión - Standalone
// Valida sesión activa; inicia si es que no se ha arrancado previamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado.');
}

// Cargar la conexión y el modelo de Ventas
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/models/M_Venta.php';

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format   = isset($_GET['format']) ? $_GET['format'] : '80mm'; // Formatos válidos: 80mm | 58mm | a4

if ($id_venta <= 0) { die('ID de venta inválido.'); }

$model  = M_Venta::singleton();

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
$tipo_doc = $venta['tipo_comprobante'] == 1 ? 'BOLETA DE VENTA'
          : ($venta['tipo_comprobante'] == 2 ? 'FACTURA ELECTRÓNICA' : 'NOTA DE VENTA');
$fecha_emision  = date('Y-m-d / H:i:s', strtotime($venta['fecha']));
$fecha_venc     = date('Y-m-d', strtotime($venta['fecha']));
$subtotal = $venta['total'] / 1.18;
$igv      = $venta['total'] - $subtotal;
$total    = $venta['total'];
$estado   = $venta['estado'] == 1 ? 'COMPLETADA' : 'ANULADA';

// Ajustes del ancho y tipografía para formatos de ticketeras térmicas
$isTicket = ($format === '80mm' || $format === '58mm');
$ticketW  = $format === '58mm' ? '56mm' : '76mm';
$ticketFs = $format === '58mm' ? '9px'  : '11px';
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
    font-size: 11pt;
    color: #000;
    background: #fff;
}

/* Encabezado */
.header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 20px;
}
.header-brand {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.brand-logo {
    font-family: Georgia, serif;
    font-size: 48pt;
    font-weight: 900;
    letter-spacing: -2px;
    line-height: 1;
    color: #000;
}
.brand-info {
    padding-top: 6px;
}
.brand-info .biz-name {
    font-size: 13pt;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 2px;
}
.brand-info p {
    font-size: 8.5pt;
    color: #333;
    line-height: 1.5;
}
.header-doc {
    border: 1.5px dotted #000;
    padding: 12px 18px;
    text-align: center;
    min-width: 170px;
}
.header-doc .doc-type {
    font-size: 12pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.header-doc .doc-code {
    font-size: 14pt;
    font-weight: bold;
}

/* Separadores */
.sep { border: none; border-top: 1px dotted #555; margin: 16px 0; }
.sep-solid { border: none; border-top: 1.5px solid #000; margin: 6px 0; }

/* Grid de Información del Cliente */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 30px;
    margin-bottom: 18px;
}
.info-row {
    display: flex;
    gap: 6px;
    padding: 3px 0;
    font-size: 9.5pt;
    border-bottom: 1px dotted #ccc;
}
.info-label { color: #444; white-space: nowrap; }
.info-value { font-weight: 600; color: #000; }

/* Tabla de Productos */
.prod-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
    font-size: 9.5pt;
}
.prod-table thead tr {
    border-top: 1.5px dotted #000;
    border-bottom: 1.5px dotted #000;
}
.prod-table th {
    padding: 6px 5px;
    text-align: left;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 8pt;
}
.prod-table th.r { text-align: right; }
.prod-table tbody tr {
    border-bottom: 1px dotted #bbb;
}
.prod-table td {
    padding: 6px 5px;
    vertical-align: middle;
}
.prod-table td.r { text-align: right; }

/* Tabla de Importes */
.totals-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
}
.totals-table {
    width: 260px;
    border-collapse: collapse;
    font-size: 9.5pt;
}
.totals-table td {
    padding: 4px 6px;
    border-bottom: 1px dotted #ccc;
}
.totals-table td.label { color: #444; text-align: right; padding-right: 12px; }
.totals-table td.value { text-align: right; font-weight: 600; }
.totals-table .grand td {
    border-top: 2px solid #000;
    border-bottom: none;
    font-weight: bold;
    font-size: 11pt;
    padding-top: 8px;
}
.totals-table .split td {
    color: #444;
    font-size: 9pt;
    padding-top: 2px;
}

/* Pie de Página */
.footer {
    margin-top: 22px;
    font-size: 8.5pt;
    color: #444;
}
.footer p { margin-bottom: 4px; }
.footer .thanks {
    text-align: center;
    font-size: 10pt;
    font-weight: bold;
    color: #000;
    margin-top: 14px;
}

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
    font-size: <?php echo $format === '58mm' ? '26pt' : '32pt'; ?>;
    font-weight: 900;
    letter-spacing: -1px;
    text-align: center;
    line-height: 1;
    margin-bottom: 4px;
}
.biz-info { text-align: center; line-height: 1.55; margin-bottom: 4px; }
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

<?php if (!$isTicket): /* ========= MAQUETADO A4 ========= */ ?>
<div class="header">
    <div class="header-brand">
        <div class="brand-logo"><img src="../assets/Logo Login PuntoNet.png" style="height: 60px; filter: grayscale(100%);" alt="NISSI"></div>
        <div class="brand-info">
            <p class="biz-name">Confecciones NISSI</p>
            <p>Av. Principal S/N</p>
            <p>RUC: 20000000000</p>
            <p>Tel: 999 999 999 | nissi@uniformes.com</p>
        </div>
    </div>
    <div class="header-doc">
        <div class="doc-type"><?php echo $tipo_doc; ?></div>
        <div class="doc-code"><?php echo $codigo; ?></div>
    </div>
</div>

<hr class="sep">

<div class="info-grid">
    <div>
        <div class="info-row"><span class="info-label">Cliente:</span><span class="info-value"><?php echo htmlspecialchars($venta['cliente']); ?></span></div>
        <div class="info-row"><span class="info-label">Doc. / RUC:</span><span class="info-value"><?php echo htmlspecialchars($venta['numero_documento']); ?></span></div>
        <div class="info-row"><span class="info-label">Vendedor:</span><span class="info-value"><?php echo htmlspecialchars($venta['vendedor']); ?></span></div>
        <div class="info-row"><span class="info-label">Forma de Pago:</span><span class="info-value">EFECTIVO / TRANSFERENCIA</span></div>
        <div class="info-row"><span class="info-label">Estado:</span><span class="info-value"><?php echo $estado; ?></span></div>
    </div>
    <div>
        <div class="info-row"><span class="info-label">Fecha de emisión:</span><span class="info-value"><?php echo $fecha_emision; ?></span></div>
        <div class="info-row"><span class="info-label">Fecha vencimiento:</span><span class="info-value"><?php echo $fecha_venc; ?></span></div>
    </div>
</div>

<table class="prod-table">
    <thead>
        <tr>
            <th style="width:8%">COD.</th>
            <th style="width:10%">CANT.</th>
            <th style="width:10%">UNIDAD</th>
            <th>DESCRIPCIÓN</th>
            <th class="r" style="width:12%">P.UNIT</th>
            <th class="r" style="width:12%">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalles as $i => $d): ?>
        <tr>
            <td><?php echo str_pad($i+1, 2, '0', STR_PAD_LEFT); ?></td>
            <td>
                <?php echo number_format($d['piezas'], 2); ?>
            </td>
            <td><?php echo htmlspecialchars($d['abreviatura']); ?></td>
            <td><?php echo htmlspecialchars($d['insumo_nombre']); ?></td>
            <td class="r"><?php echo number_format($d['precio_venta'], 2); ?></td>
            <td class="r"><?php echo number_format($d['subtotal'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="totals-wrap">
    <table class="totals-table">
        <tr>
            <td class="label">OP. GRAVADA:</td>
            <td class="value">S/ <?php echo number_format($subtotal, 2); ?></td>
        </tr>
        <tr>
            <td class="label">IGV (18%):</td>
            <td class="value">S/ <?php echo number_format($igv, 2); ?></td>
        </tr>
        <tr class="grand">
            <td class="label">TOTAL A PAGAR: S/</td>
            <td class="value"><?php echo number_format($total, 2); ?></td>
        </tr>
    </table>
</div>

<div class="footer">
    <p><strong>CONDICIÓN DE PAGO:</strong> Al contado</p>
    <p class="thanks">¡Gracias por su compra!</p>
</div>

<?php else: /* ========= MAQUETADO TICKETERAS TÉRMICAS ========= */ ?>

<div class="brand-logo"><img src="../assets/Logo Login PuntoNet.png" style="height: 40px; filter: grayscale(100%);" alt="NISSI"></div>
<div class="biz-info">
    <p class="biz-name">Confecciones NISSI</p>
    <p>RUC: 20000000000</p>
    <p>Av. Principal S/N</p>
    <p>Tel: 999 999 999</p>
</div>

<hr class="sep">

<div class="doc-box">
    <div class="doc-type"><?php echo $tipo_doc; ?></div>
    <div class="doc-code"><?php echo $codigo; ?></div>
</div>

<hr class="sep">

<div class="info-block">
    <table>
        <tr><td>F. Emisión:</td><td><?php echo $fecha_emision; ?></td></tr>
        <tr><td>F. Venc.:</td><td><?php echo $fecha_venc; ?></td></tr>
        <tr><td>Cliente:</td><td><?php echo htmlspecialchars($venta['cliente']); ?></td></tr>
        <tr><td>Doc.:</td><td><?php echo htmlspecialchars($venta['numero_documento']); ?></td></tr>
        <tr><td>Vendedor:</td><td><?php echo htmlspecialchars($venta['vendedor']); ?></td></tr>
        <tr><td>F. Pago:</td><td>EFECTIVO / TRANSF</td></tr>
        <tr><td>Estado:</td><td><?php echo $estado; ?></td></tr>
    </table>
</div>

<table class="prod-table">
    <thead>
        <tr>
            <th>COD</th>
            <th>CANT</th>
            <th>UNID</th>
            <th>DESCRIPCIÓN</th>
            <th class="r">P.UNIT</th>
            <th class="r">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalles as $i => $d): ?>
        <tr>
            <td><?php echo str_pad($i+1, 2, '0', STR_PAD_LEFT); ?></td>
            <td>
                <?php echo number_format($d['piezas'], 2); ?>
            </td>
            <td><?php echo htmlspecialchars($d['abreviatura']); ?></td>
            <td><?php echo htmlspecialchars($d['insumo_nombre']); ?></td>
            <td class="r"><?php echo number_format($d['precio_venta'], 2); ?></td>
            <td class="r"><?php echo number_format($d['subtotal'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="total-row"><span>OP. GRAVADA:</span><span>S/ <?php echo number_format($subtotal, 2); ?></span></div>
<div class="total-row"><span>IGV (18%):</span><span>S/ <?php echo number_format($igv, 2); ?></span></div>
<div class="total-row grand"><span>TOTAL A PAGAR: S/</span><span><?php echo number_format($total, 2); ?></span></div>

<hr class="sep" style="margin-top:8px;">
<p class="footer-msg">¡Gracias por su compra!</p>

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
