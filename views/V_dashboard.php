<?php
// Restricción de acceso: El Dashboard analítico es exclusivo para Administradores
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

require_once dirname(__DIR__) . '/config/conexion.php';
$dbh = Conexion::singleton()->getConexion();

// 1. Calcular Ventas Totales acumuladas en el sistema (ventas completas activas)
$stmt = $dbh->query("SELECT COALESCE(SUM(total), 0) as total FROM ventas WHERE estado = 1");
$totalSales = floatval($stmt->fetch()['total']);

// 2. Calcular número total de comprobantes emitidos válidos
$stmt = $dbh->query("SELECT COUNT(*) as count FROM ventas WHERE estado = 1");
$completedCount = intval($stmt->fetch()['count']);

// 3. Obtener el número de usuarios y personal activos en el sistema
$stmt = $dbh->query("SELECT COUNT(*) as count FROM usuarios u INNER JOIN personas p ON u.id_persona = p.id_persona WHERE p.estado = 1");
$activeUsers = intval($stmt->fetch()['count']);

// 4. Contar la cantidad de productos que tienen stock crítico igual o inferior a 20 unidades
$stmt = $dbh->query("SELECT COUNT(*) as count FROM productos WHERE stock_piezas <= 20 AND estado = 1");
$lowStockCount = intval($stmt->fetch()['count']);

// 5. Consultar las últimas 5 ventas para mostrar en el historial reciente
$stmt = $dbh->query("SELECT v.id_venta, v.tipo_comprobante, v.fecha, v.total, v.estado,
                            p.nombres_razon_social AS cliente_nombre 
                     FROM ventas v
                     INNER JOIN clientes c ON v.id_cliente = c.id_cliente
                     INNER JOIN personas p ON c.id_persona = p.id_persona
                     ORDER BY v.fecha DESC LIMIT 5");
$recentSales = $stmt->fetchAll();

// 6. Configurar analítica de ventas para el gráfico de barras/línea (Últimos 7 días)
$ventasPorDia = [];
$diasSemanaMap = [
    'Sunday' => 'Dom',
    'Monday' => 'Lun',
    'Tuesday' => 'Mar',
    'Wednesday' => 'Mié',
    'Thursday' => 'Jue',
    'Friday' => 'Vie',
    'Saturday' => 'Sáb'
];

// Inicializar el arreglo de los últimos 7 días con ventas en 0
for ($i = 6; $i >= 0; $i--) {
    $timestamp = strtotime("-$i days");
    $fechaKey = date('Y-m-d', $timestamp);
    $diaIngles = date('l', $timestamp);
    $diaSemana = isset($diasSemanaMap[$diaIngles]) ? $diasSemanaMap[$diaIngles] : substr($diaIngles, 0, 3);
    
    $ventasPorDia[$fechaKey] = [
        "dia" => $diaSemana,
        "fecha" => $fechaKey,
        "ventas" => 0.0
    ];
}

// Consultar las ventas agrupadas por día para los últimos 7 días en la DB
$stmt = $dbh->query("SELECT DATE(fecha) as fecha_dia, SUM(total) as total_dia 
                     FROM ventas 
                     WHERE estado = 1 AND fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                     GROUP BY DATE(fecha)");
$dbVentas = $stmt->fetchAll();

// Sobrescribir los montos reales vendidos en los días correspondientes
foreach ($dbVentas as $row) {
    $f = $row['fecha_dia'];
    if (isset($ventasPorDia[$f])) {
        $ventasPorDia[$f]['ventas'] = floatval($row['total_dia']);
    }
}
$chartData = array_values($ventasPorDia);
?>

<div class="container-fluid px-0">
    <!-- Encabezado de Página -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Dashboard</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Resumen general de la operación de NISSI POS.</p>
        </div>
    </div>

    <!-- Cuadrícula de Métricas Principales -->
    <div class="row g-4 mb-4">
        <!-- Métrica 1: Ventas Totales -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="gp-card d-flex flex-column h-full">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-medium" style="font-size: 13px;">Ventas Totales</span>
                    <span class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width: 36px; height: 36px;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </span>
                </div>
                <h3 class="mb-1 fw-bold text-dark">S/ <?php echo number_format($totalSales, 2); ?></h3>
                <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                    <span class="text-primary fw-bold d-inline-flex align-items-center gap-1"><i class="bi bi-arrow-up-right"></i> +12.4%</span>
                    <span class="text-muted">vs. semana anterior</span>
                </div>
            </div>
        </div>

        <!-- Métrica 2: Cantidad de Comprobantes emitidos -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="gp-card d-flex flex-column h-full">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-medium" style="font-size: 13px;">Comprobantes</span>
                    <span class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width: 36px; height: 36px;">
                        <i class="bi bi-bag-check-fill"></i>
                    </span>
                </div>
                <h3 class="mb-1 fw-bold text-dark"><?php echo $completedCount; ?></h3>
                <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                    <span class="text-primary fw-bold d-inline-flex align-items-center gap-1"><i class="bi bi-plus"></i> Reciente</span>
                    <span class="text-muted">emitidos hoy</span>
                </div>
            </div>
        </div>

        <!-- Métrica 3: Usuarios Activos del sistema -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="gp-card d-flex flex-column h-full">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-medium" style="font-size: 13px;">Usuarios Activos</span>
                    <span class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width: 36px; height: 36px;">
                        <i class="bi bi-people-fill"></i>
                    </span>
                </div>
                <h3 class="mb-1 fw-bold text-dark"><?php echo $activeUsers; ?></h3>
                <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                    <span class="text-muted fw-bold d-inline-flex align-items-center">Activo</span>
                    <span class="text-muted">vendedores y admins</span>
                </div>
            </div>
        </div>

        <!-- Métrica 4: Alertas de Stock Bajo -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="gp-card d-flex flex-column h-full">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-medium" style="font-size: 13px;">Stock Bajo</span>
                    <span class="d-flex align-items-center justify-content-center <?php echo $lowStockCount > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-primary bg-opacity-10 text-primary'; ?> rounded-3" style="width: 36px; height: 36px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </span>
                </div>
                <h3 class="mb-1 fw-bold text-dark"><?php echo $lowStockCount; ?></h3>
                <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px;">
                    <?php if ($lowStockCount > 0): ?>
                        <span class="text-danger fw-bold d-inline-flex align-items-center">Atención</span>
                        <span class="text-muted">productos por reponer</span>
                    <?php else: ?>
                        <span class="text-primary fw-bold d-inline-flex align-items-center">Al día</span>
                        <span class="text-muted">sin alertas de stock</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Gráficos y Tablas Detalladas -->
    <div class="row g-4">
        <!-- Gráfico Analítico de Ventas (Chart.js) -->
        <div class="col-12 col-lg-8">
            <div class="gp-card">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h6 class="mb-1 fw-bold text-dark">Analítica de Ventas</h6>
                        <p class="text-muted mb-0" style="font-size: 12px;">Ingresos de los últimos 7 días</p>
                    </div>
                    <div class="btn-group border rounded-3 p-0.5 bg-light" role="group">
                        <button type="button" class="btn btn-sm btn-white active shadow-sm py-1 px-3 fw-semibold border-0" id="chart-area-btn">Línea</button>
                        <button type="button" class="btn btn-sm btn-light py-1 px-3 fw-semibold border-0" id="chart-bar-btn">Barras</button>
                    </div>
                </div>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Listado de Ventas Recientes -->
        <div class="col-12 col-lg-4">
            <div class="gp-card h-100 d-flex flex-column">
                <h6 class="mb-4 fw-bold text-dark">Ventas Recientes</h6>
                <div class="flex-grow-1 overflow-auto">
                    <?php if (empty($recentSales)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-receipt-cutoff fs-2 mb-2 d-block"></i>
                            <p style="font-size: 13px;">No hay transacciones registradas.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentSales as $sale): ?>
                                <div class="list-group-item px-0 py-3 border-bottom d-flex align-items-center justify-content-between bg-transparent">
                                    <div>
                                        <p class="mb-0 fw-semibold text-dark" style="font-size: 13.5px;">
                                            V-<?php echo str_pad($sale['id_venta'], 6, '0', STR_PAD_LEFT); ?>
                                        </p>
                                        <small class="text-muted" style="font-size: 11px;">
                                            <?php echo htmlspecialchars($sale['cliente_nombre']); ?> &bull; <?php echo date('d/m H:i', strtotime($sale['fecha'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <p class="mb-1 fw-bold text-dark" style="font-size: 14px;">
                                            S/ <?php echo number_format($sale['total'], 2); ?>
                                        </p>
                                        <span class="<?php echo $sale['estado'] == 1 ? 'gp-badge-primary' : 'gp-badge-danger'; ?>">
                                            <?php echo $sale['estado'] == 1 ? 'Completada' : 'Anulada'; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Inicializar datos para el gráfico
        const rawChartData = <?php echo json_encode($chartData); ?>;
        const labels = rawChartData.map(d => d.dia);
        const dataValues = rawChartData.map(d => d.ventas);
        
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Relleno de degradado lineal azul para el gráfico de línea/área
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(2, 132, 199, 0.3)');
        gradient.addColorStop(1, 'rgba(2, 132, 199, 0.01)');

        let chartType = 'line';
        let salesChart;

        // Función para renderizar o actualizar dinámicamente el gráfico de Chart.js
        function buildChart(type) {
            if (salesChart) {
                salesChart.destroy();
            }

            const config = {
                type: type === 'line' ? 'line' : 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Ventas (S/)',
                        data: dataValues,
                        borderColor: '#0284c7',
                        borderWidth: 2.5,
                        backgroundColor: type === 'line' ? gradient : '#0284c7',
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
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' S/ ' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: 'Plus Jakarta Sans',
                                    size: 11
                                }
                            }
                        },
                        y: {
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                font: {
                                    family: 'Plus Jakarta Sans',
                                    size: 11
                                },
                                callback: function(value) {
                                    return 'S/ ' + value;
                                }
                            }
                        }
                    }
                }
            };
            
            salesChart = new Chart(ctx, config);
        }

        // Generar gráfico lineal inicial
        buildChart('line');

        // Botones de alternancia para cambiar el tipo de visualización (Línea / Barras)
        const areaBtn = document.getElementById('chart-area-btn');
        const barBtn = document.getElementById('chart-bar-btn');

        if (areaBtn && barBtn) {
            areaBtn.addEventListener('click', () => {
                areaBtn.classList.add('active', 'shadow-sm');
                areaBtn.classList.remove('btn-light');
                areaBtn.classList.add('btn-white');
                barBtn.classList.remove('active', 'shadow-sm');
                barBtn.classList.remove('btn-white');
                barBtn.classList.add('btn-light');
                buildChart('line');
            });

            barBtn.addEventListener('click', () => {
                barBtn.classList.add('active', 'shadow-sm');
                barBtn.classList.remove('btn-light');
                barBtn.classList.add('btn-white');
                areaBtn.classList.remove('active', 'shadow-sm');
                areaBtn.classList.remove('btn-white');
                areaBtn.classList.add('btn-light');
                buildChart('bar');
            });
        }
    });
</script>
