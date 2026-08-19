<?php
// Restricción de acceso: El Dashboard analítico es exclusivo para Administradores
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

require_once dirname(__DIR__) . '/config/conexion.php';
$dbh = Conexion::singleton()->getConexion();

$hoyKey      = date('Y-m-d');
$ayerKey     = date('Y-m-d', strtotime('-1 day'));
$rangoInicio = date('Y-m-d', strtotime('-13 day'));

// ---- Fila 1: KPIs principales (valores reales, sin hardcode) ----

// 1. Ventas de hoy y de ayer (monto + nº de tickets) para comparación real
$stmt = $dbh->prepare(
    "SELECT
        SUM(CASE WHEN DATE(fecha) = :hoy THEN total ELSE 0 END) AS total_hoy,
        SUM(CASE WHEN DATE(fecha) = :hoy THEN 1 ELSE 0 END)     AS nro_hoy,
        SUM(CASE WHEN DATE(fecha) = :ayer THEN total ELSE 0 END) AS total_ayer,
        SUM(CASE WHEN DATE(fecha) = :ayer THEN 1 ELSE 0 END)     AS nro_ayer
     FROM ventas
     WHERE estado = 1 AND fecha >= :rango"
);
$stmt->execute([':hoy' => $hoyKey, ':ayer' => $ayerKey, ':rango' => $ayerKey . ' 00:00:00']);
$ventasHoyAyer = $stmt->fetch();
$totalHoy  = (float) $ventasHoyAyer['total_hoy'];
$nroHoy    = (int) $ventasHoyAyer['nro_hoy'];
$totalAyer = (float) $ventasHoyAyer['total_ayer'];
$nroAyer   = (int) $ventasHoyAyer['nro_ayer'];

// Variación real vs ayer (solo cuando hay base de comparación)
if ($totalAyer > 0) {
    $variacionHoy = (($totalHoy - $totalAyer) / $totalAyer) * 100;
    $variacionHoy = round($variacionHoy, 1);
} else {
    $variacionHoy = null;
}

// 2. Pedidos online pendientes de atender (1=Pagado, 5=Preparado)
$stmt = $dbh->query(
    "SELECT COUNT(*) FROM pedidos_online WHERE estado IN (1, 5)"
);
$pedidosPendientes = (int) $stmt->fetchColumn();

// 3. Comprobantes emitidos hoy (corrige el KPI viejo que mostraba el total histórico)
$stmt = $dbh->prepare("SELECT COUNT(*) FROM ventas WHERE estado = 1 AND DATE(fecha) = ?");
$stmt->execute([$hoyKey]);
$comprobantesHoy = (int) $stmt->fetchColumn();

// 4. Caja: última caja abierta (estado=1)
$stmt = $dbh->query(
    "SELECT id_caja, id_usuario, monto_apertura, fecha_apertura
     FROM cajas WHERE estado = 1 ORDER BY id_caja DESC LIMIT 1"
);
$cajaAbierta = $stmt->fetch();

// ---- Fila 2: KPIs operativos ----

// 5. Productos agotados (stock 0) y stock bajo (1-20), excluyendo ilimitados
$stmt = $dbh->query(
    "SELECT
        SUM(CASE WHEN stock_piezas <= 0 THEN 1 ELSE 0 END) AS agotados,
        SUM(CASE WHEN stock_piezas BETWEEN 1 AND 20 THEN 1 ELSE 0 END) AS bajos
     FROM productos WHERE estado = 1 AND stock_ilimitado = 0"
);
$stockKpis = $stmt->fetch();
$productosAgotados = (int) $stockKpis['agotados'];
$productosBajos    = (int) $stockKpis['bajos'];

// 6. Separaciones vigentes
$stmt = $dbh->query("SELECT COUNT(*) FROM separaciones WHERE estado = 1");
$separacionesVigentes = (int) $stmt->fetchColumn();

// 7. Usuarios activos (personal habilitado)
$stmt = $dbh->query(
    "SELECT COUNT(*) FROM usuarios u
     INNER JOIN personas p ON u.id_persona = p.id_persona
     WHERE p.estado = 1"
);
$activeUsers = (int) $stmt->fetchColumn();

// ---- Fila 3: Analítica ----

// 8. Ventas por día (últimos 14 días), rellena 0 en días sin ventas
$ventasPorDia = [];
for ($i = 13; $i >= 0; $i--) {
    $ts    = strtotime("-$i days");
    $fecha = date('Y-m-d', $ts);
    $ventasPorDia[$fecha] = [
        'fecha'  => $fecha,
        'dia'    => date('d/m', $ts),
        'ventas' => 0.0
    ];
}
$stmt = $dbh->prepare(
    "SELECT DATE(fecha) AS fecha_dia, SUM(total) AS total_dia
     FROM ventas
     WHERE estado = 1 AND fecha >= :desde
     GROUP BY DATE(fecha)"
);
$stmt->execute([':desde' => $rangoInicio . ' 00:00:00']);
foreach ($stmt->fetchAll() as $row) {
    $f = $row['fecha_dia'];
    if (isset($ventasPorDia[$f])) {
        $ventasPorDia[$f]['ventas'] = (float) $row['total_dia'];
    }
}
$chartVentas = array_values($ventasPorDia);

// 9. Método de pago real (desglose efectivo / Yape / tarjeta) — últimos 14 días
$stmt = $dbh->prepare(
    "SELECT pv.metodo_pago, COALESCE(SUM(pv.monto), 0) AS monto
     FROM pagos_venta pv
     INNER JOIN ventas v ON pv.id_venta = v.id_venta
     WHERE v.estado = 1 AND v.fecha >= :desde
     GROUP BY pv.metodo_pago"
);
$stmt->execute([':desde' => $rangoInicio . ' 00:00:00']);
$metodosMap = [1 => 'Efectivo', 2 => 'Yape', 3 => 'Tarjeta', 5 => 'Transferencia'];
$chartMetodos = [];
foreach ($stmt->fetchAll() as $row) {
    $chartMetodos[] = [
        'metodo' => $metodosMap[(int) $row['metodo_pago']] ?? 'Otro',
        'monto'  => (float) $row['monto']
    ];
}

// 10. Top 5 productos más vendidos (por monto, últimos 14 días)
$stmt = $dbh->prepare(
    "SELECT p.nombre, COALESCE(SUM(dv.subtotal), 0) AS monto, COALESCE(SUM(dv.cantidad), 0) AS cantidad
     FROM detalle_ventas dv
     INNER JOIN ventas v ON dv.id_venta = v.id_venta
     INNER JOIN productos p ON dv.id_producto = p.id_producto
     WHERE v.estado = 1 AND v.fecha >= :desde
     GROUP BY p.id_producto, p.nombre
     ORDER BY monto DESC
     LIMIT 5"
);
$stmt->execute([':desde' => $rangoInicio . ' 00:00:00']);
$topProductos = $stmt->fetchAll();

// 11. Pedidos online por estado (para la dona) — todo el histórico
$stmt = $dbh->query(
    "SELECT estado, COUNT(*) AS total FROM pedidos_online GROUP BY estado"
);
$estadoMap = [
    0 => 'Rechazado',
    1 => 'Pagado — en proceso',
    2 => 'Entregado',
    3 => 'Esperando pago',
    4 => 'Expirado / fallido',
    5 => 'Preparado'
];
$pedidosPorEstado = [];
foreach ($stmt->fetchAll() as $row) {
    $estadoKey = (int) $row['estado'];
    if (isset($estadoMap[$estadoKey])) {
        $pedidosPorEstado[] = [
            'estado' => $estadoMap[$estadoKey],
            'total'  => (int) $row['total']
        ];
    }
}

// ---- Fila 4: Listas accionables ----

// 12. Pedidos pendientes de atender (con cliente), para la lista con enlace
$stmt = $dbh->query(
    "SELECT p.id_pedido, p.estado, p.total, p.tipo_entrega, p.estudiante_nombre,
            p.fecha_pedido, cw.nombres_razon_social, cw.apellidos
     FROM pedidos_online p
     INNER JOIN clientes_web cw ON p.id_cliente_web = cw.id_cliente_web
     WHERE p.estado IN (1, 5)
     ORDER BY p.fecha_pedido DESC"
);
$pedidosPendientesLista = $stmt->fetchAll();

// 13. Últimas 5 ventas para el historial reciente
$stmt = $dbh->query(
    "SELECT v.id_venta, v.tipo_comprobante, v.fecha, v.total, v.estado,
            p.nombres_razon_social AS cliente_nombre
     FROM ventas v
     INNER JOIN clientes c ON v.id_cliente = c.id_cliente
     INNER JOIN personas p ON c.id_persona = p.id_persona
     ORDER BY v.fecha DESC LIMIT 5"
);
$recentSales = $stmt->fetchAll();

$tipoComprobanteLabel = function (int $tipo): string {
    return $tipo == 1 ? 'Boleta' : ($tipo == 2 ? 'Factura' : 'Nota de Venta');
};
?>

<div class="container-fluid px-0">
    <!-- Encabezado de Página -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Dashboard</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Resumen general de la operación de NISSI POS.</p>
        </div>
        <div class="d-none d-md-block">
            <span class="badge bg-light text-dark border px-3 py-2" style="font-size: 13px;">
                <i class="bi bi-calendar3 me-1"></i><?php echo date('d/m/Y'); ?>
            </span>
        </div>
    </div>

    <!-- Fila 1: KPIs principales -->
    <div class="row g-4 mb-4">
        <!-- Ventas de hoy -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="gp-card d-flex flex-column h-full">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-medium" style="font-size: 13px;">Ventas de Hoy</span>
                    <span class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width: 36px; height: 36px;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </span>
                </div>
                <h3 class="mb-1 fw-bold text-dark">S/ <?php echo number_format($totalHoy, 2); ?></h3>
                <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                    <?php if ($variacionHoy === null): ?>
                        <span class="text-muted fw-bold d-inline-flex align-items-center"><?php echo $nroHoy; ?> tickets hoy</span>
                        <span class="text-muted">sin base vs ayer</span>
                    <?php else: ?>
                        <span class="<?php echo $variacionHoy >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold d-inline-flex align-items-center gap-1">
                            <i class="bi <?php echo $variacionHoy >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>"></i> <?php echo $variacionHoy >= 0 ? '+' : ''; ?><?php echo $variacionHoy; ?>%
                        </span>
                        <span class="text-muted">vs ayer (<?php echo $nroAyer; ?> tickets)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pedidos online pendientes -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/pedidos-online" class="text-decoration-none">
                <div class="gp-card d-flex flex-column h-full">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-medium" style="font-size: 13px;">Pedidos Online Pendientes</span>
                        <span class="d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-3" style="width: 36px; height: 36px;">
                            <i class="bi bi-cart3"></i>
                        </span>
                    </div>
                    <h3 class="mb-1 fw-bold text-dark"><?php echo $pedidosPendientes; ?></h3>
                    <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                        <span class="<?php echo $pedidosPendientes > 0 ? 'text-warning fw-bold' : 'text-success fw-bold'; ?> d-inline-flex align-items-center">
                            <?php echo $pedidosPendientes > 0 ? 'Requieren atención' : 'Al día'; ?>
                        </span>
                        <span class="text-muted">pagados y en preparación</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Comprobantes hoy -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="gp-card d-flex flex-column h-full">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-medium" style="font-size: 13px;">Comprobantes Hoy</span>
                    <span class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width: 36px; height: 36px;">
                        <i class="bi bi-bag-check-fill"></i>
                    </span>
                </div>
                <h3 class="mb-1 fw-bold text-dark"><?php echo $comprobantesHoy; ?></h3>
                <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                    <span class="text-primary fw-bold d-inline-flex align-items-center"><i class="bi bi-plus"></i> Emitidos</span>
                    <span class="text-muted">boletas, facturas y notas</span>
                </div>
            </div>
        </div>

        <!-- Caja -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/caja" class="text-decoration-none">
                <div class="gp-card d-flex flex-column h-full">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-medium" style="font-size: 13px;">Caja</span>
                        <span class="d-flex align-items-center justify-content-center <?php echo $cajaAbierta ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary'; ?> rounded-3" style="width: 36px; height: 36px;">
                            <i class="bi <?php echo $cajaAbierta ? 'bi-safe2' : 'bi-safe2-fill'; ?>"></i>
                        </span>
                    </div>
                    <h3 class="mb-1 fw-bold text-dark" style="font-size: 22px;">
                        <?php echo $cajaAbierta ? 'S/ ' . number_format((float) $cajaAbierta['monto_apertura'], 2) : 'Cerrada'; ?>
                    </h3>
                    <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                        <?php if ($cajaAbierta): ?>
                            <span class="text-success fw-bold d-inline-flex align-items-center">
                                <span class="me-1 d-inline-block rounded-circle bg-success" style="width:7px;height:7px;"></span> Abierta
                            </span>
                            <span class="text-muted">desde <?php echo date('d/m H:i', strtotime($cajaAbierta['fecha_apertura'])); ?></span>
                        <?php else: ?>
                            <span class="text-muted fw-bold d-inline-flex align-items-center">Sin caja abierta</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Fila 2: KPIs operativos -->
    <div class="row g-4 mb-4">
        <!-- Productos agotados -->
        <div class="col-6 col-xl-3">
            <div class="gp-card d-flex align-items-center justify-content-between p-3">
                <div>
                    <div class="text-muted fw-medium" style="font-size: 12px;">Agotados</div>
                    <div class="fw-bold text-dark" style="font-size: 22px;"><?php echo $productosAgotados; ?></div>
                </div>
                <span class="d-flex align-items-center justify-content-center <?php echo $productosAgotados > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success'; ?> rounded-3" style="width: 38px; height: 38px;">
                    <i class="bi bi-x-octagon-fill"></i>
                </span>
            </div>
        </div>
        <!-- Stock bajo -->
        <div class="col-6 col-xl-3">
            <div class="gp-card d-flex align-items-center justify-content-between p-3">
                <div>
                    <div class="text-muted fw-medium" style="font-size: 12px;">Stock Bajo</div>
                    <div class="fw-bold text-dark" style="font-size: 22px;"><?php echo $productosBajos; ?></div>
                </div>
                <span class="d-flex align-items-center justify-content-center <?php echo $productosBajos > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-success bg-opacity-10 text-success'; ?> rounded-3" style="width: 38px; height: 38px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </span>
            </div>
        </div>
        <!-- Separaciones vigentes -->
        <div class="col-6 col-xl-3">
            <div class="gp-card d-flex align-items-center justify-content-between p-3">
                <div>
                    <div class="text-muted fw-medium" style="font-size: 12px;">Separaciones Vigentes</div>
                    <div class="fw-bold text-dark" style="font-size: 22px;"><?php echo $separacionesVigentes; ?></div>
                </div>
                <span class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width: 38px; height: 38px;">
                    <i class="bi bi-box-seam-fill"></i>
                </span>
            </div>
        </div>
        <!-- Usuarios activos -->
        <div class="col-6 col-xl-3">
            <div class="gp-card d-flex align-items-center justify-content-between p-3">
                <div>
                    <div class="text-muted fw-medium" style="font-size: 12px;">Usuarios Activos</div>
                    <div class="fw-bold text-dark" style="font-size: 22px;"><?php echo $activeUsers; ?></div>
                </div>
                <span class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width: 38px; height: 38px;">
                    <i class="bi bi-people-fill"></i>
                </span>
            </div>
        </div>
    </div>

    <!-- Fila 3: Gráficos analíticos -->
    <div class="row g-4 mb-4">
        <!-- Ventas 14 días -->
        <div class="col-12 col-lg-8">
            <div class="gp-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h6 class="mb-1 fw-bold text-dark">Analítica de Ventas</h6>
                        <p class="text-muted mb-0" style="font-size: 12px;">Ingresos de los últimos 14 días</p>
                    </div>
                    <div class="btn-group border rounded-3 bg-light" role="group">
                        <button type="button" class="btn btn-sm btn-white active shadow-sm py-1 px-3 fw-semibold border-0" id="chart-area-btn">Línea</button>
                        <button type="button" class="btn btn-sm btn-light py-1 px-3 fw-semibold border-0" id="chart-bar-btn">Barras</button>
                    </div>
                </div>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Método de pago -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="gp-card h-100">
                <h6 class="mb-4 fw-bold text-dark">Método de Pago</h6>
                <div style="position: relative; height: 220px;">
                    <canvas id="metodosChart"></canvas>
                </div>
                <p class="text-muted mt-3 mb-0" style="font-size: 11px;">Desglose de los últimos 14 días</p>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Top productos -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="gp-card h-100">
                <h6 class="mb-4 fw-bold text-dark">Top Productos</h6>
                <div style="position: relative; height: 260px;">
                    <canvas id="topChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Pedidos por estado -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="gp-card h-100">
                <h6 class="mb-4 fw-bold text-dark">Pedidos Online por Estado</h6>
                <div style="position: relative; height: 260px;">
                    <canvas id="pedidosChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Pedidos pendientes: lista accionable -->
        <div class="col-12 col-lg-4">
            <div class="gp-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold text-dark">Pedidos por Atender</h6>
                    <a href="/pedidos-online" class="btn btn-sm btn-outline-primary fw-semibold border-0">Ver todos</a>
                </div>
                <div class="flex-grow-1 overflow-auto">
                    <?php if (empty($pedidosPendientesLista)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle fs-3 mb-2 d-block"></i>
                            <p style="font-size: 13px;">No hay pedidos pendientes.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($pedidosPendientesLista as $ped): ?>
                                <div class="list-group-item px-0 py-2 border-bottom d-flex align-items-center justify-content-between bg-transparent">
                                    <div>
                                        <p class="mb-0 fw-semibold text-dark" style="font-size: 13px;">
                                            Pedido #<?php echo $ped['id_pedido']; ?>
                                        </p>
                                        <small class="text-muted" style="font-size: 11px;">
                                            <?php echo htmlspecialchars(trim(($ped['apellidos'] ?? '') . ' ' . ($ped['nombres_razon_social'] ?? ''))); ?>
                                            &bull; S/ <?php echo number_format((float) $ped['total'], 2); ?>
                                        </small>
                                    </div>
                                    <span class="badge <?php echo $ped['estado'] == 1 ? 'bg-warning text-dark' : 'bg-info text-dark'; ?>" style="font-size: 11px;">
                                        <?php echo $ped['estado'] == 1 ? 'Pagado' : 'Preparado'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Ventas recientes -->
    <div class="row g-4">
        <div class="col-12">
            <div class="gp-card">
                <h6 class="mb-3 fw-bold text-dark">Ventas Recientes</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead>
                            <tr class="text-muted" style="font-size: 12px;">
                                <th>Comprobante</th><th>Cliente</th><th>Fecha</th><th class="text-end">Total</th><th class="text-end">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No hay transacciones registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold text-dark"><?php echo $tipoComprobanteLabel((int) $sale['tipo_comprobante']); ?></span>
                                            <span class="text-muted">V-<?php echo str_pad($sale['id_venta'], 6, '0', STR_PAD_LEFT); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($sale['cliente_nombre']); ?></td>
                                        <td class="text-muted"><?php echo date('d/m/Y H:i', strtotime($sale['fecha'])); ?></td>
                                        <td class="text-end fw-bold text-dark">S/ <?php echo number_format((float) $sale['total'], 2); ?></td>
                                        <td class="text-end">
                                            <span class="<?php echo $sale['estado'] == 1 ? 'gp-badge-primary' : 'gp-badge-danger'; ?>">
                                                <?php echo $sale['estado'] == 1 ? 'Completada' : 'Anulada'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chartColors = {
            primary: '#23284E',
            green: '#16a34a',
            yellow: '#f59e0b',
            red: '#dc2626',
            gray: '#9ca3af'
        };

        // ---- Gráfico: Ventas 14 días (línea / barras) ----
        const ventasData = <?php echo json_encode($chartVentas); ?>;
        const labels14 = ventasData.map(d => d.dia);
        const values14 = ventasData.map(d => d.ventas);
        const ctx = document.getElementById('salesChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(2, 132, 199, 0.3)');
        gradient.addColorStop(1, 'rgba(2, 132, 199, 0.01)');

        let salesChart;
        function buildVentasChart(type) {
            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctx, {
                type: type,
                data: {
                    labels: labels14,
                    datasets: [{
                        label: 'Ventas (S/)',
                        data: values14,
                        borderColor: chartColors.primary,
                        borderWidth: 2.5,
                        backgroundColor: type === 'line' ? gradient : chartColors.primary,
                        fill: type === 'line',
                        tension: 0.3,
                        borderRadius: type === 'bar' ? 6 : 0,
                        maxBarThickness: type === 'bar' ? 40 : null
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (c) => ' S/ ' + c.parsed.y.toFixed(2)
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: {
                            grid: { color: '#f3f4f6' },
                            ticks: {
                                font: { size: 11 },
                                callback: (v) => 'S/ ' + v
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        buildVentasChart('line');

        const areaBtn = document.getElementById('chart-area-btn');
        const barBtn = document.getElementById('chart-bar-btn');
        if (areaBtn && barBtn) {
            const reset = () => {
                areaBtn.classList.toggle('btn-white', true);
                areaBtn.classList.toggle('btn-light', false);
                areaBtn.classList.toggle('active', true);
                barBtn.classList.toggle('btn-white', false);
                barBtn.classList.toggle('btn-light', true);
                barBtn.classList.toggle('active', false);
            };
            areaBtn.addEventListener('click', () => { reset(); buildVentasChart('line'); });
            barBtn.addEventListener('click', () => { reset(); buildVentasChart('bar'); });
        }

        // ---- Gráfico: Método de pago (dona) ----
        const metodosData = <?php echo json_encode($chartMetodos); ?>;
        if (document.getElementById('metodosChart') && metodosData.length > 0) {
            new Chart(document.getElementById('metodosChart'), {
                type: 'doughnut',
                data: {
                    labels: metodosData.map(m => m.metodo),
                    datasets: [{
                        data: metodosData.map(m => m.monto),
                        backgroundColor: [chartColors.green, chartColors.primary, chartColors.yellow],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: (c) => ' ' + c.label + ': S/ ' + c.parsed.toFixed(2)
                            }
                        }
                    }
                }
            });
        } else {
            const el = document.getElementById('metodosChart');
            if (el) el.parentElement.innerHTML = '<div class="text-center text-muted py-4" style="font-size:13px;">Sin pagos en el período.</div>';
        }

        // ---- Gráfico: Top productos (barras horizontales) ----
        const topData = <?php echo json_encode($topProductos); ?>;
        if (document.getElementById('topChart') && topData.length > 0) {
            new Chart(document.getElementById('topChart'), {
                type: 'bar',
                data: {
                    labels: topData.map(p => p.nombre),
                    datasets: [{
                        label: 'Monto (S/)',
                        data: topData.map(p => parseFloat(p.monto)),
                        backgroundColor: 'rgba(2, 132, 199, 0.75)',
                        borderRadius: 4,
                        maxBarThickness: 18
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (c) => ' S/ ' + c.parsed.x.toFixed(2)
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: '#f3f4f6' },
                            ticks: { font: { size: 11 }, callback: (v) => 'S/ ' + v },
                            beginAtZero: true
                        },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        } else {
            const el = document.getElementById('topChart');
            if (el) el.parentElement.innerHTML = '<div class="text-center text-muted py-4" style="font-size:13px;">Sin ventas en el período.</div>';
        }

        // ---- Gráfico: Pedidos por estado (dona) ----
        const pedidosData = <?php echo json_encode($pedidosPorEstado); ?>;
        if (document.getElementById('pedidosChart') && pedidosData.length > 0) {
            new Chart(document.getElementById('pedidosChart'), {
                type: 'doughnut',
                data: {
                    labels: pedidosData.map(p => p.estado),
                    datasets: [{
                        data: pedidosData.map(p => p.total),
                        backgroundColor: [chartColors.red, chartColors.yellow, chartColors.green, chartColors.gray, '#6b7280', chartColors.primary],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: (c) => ' ' + c.label + ': ' + c.parsed + ' pedido(s)'
                            }
                        }
                    }
                }
            });
        } else {
            const el = document.getElementById('pedidosChart');
            if (el) el.parentElement.innerHTML = '<div class="text-center text-muted py-4" style="font-size:13px;">Sin pedidos online.</div>';
        }
    });
</script>