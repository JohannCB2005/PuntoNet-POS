<?php
// Requerir archivo de conexión centralizada
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Modelo para la gestión y control del Kardex (Movimientos de Inventario)
 * Calcula y genera el historial de entradas, salidas y saldos en vivo de cada producto del inventario.
 */
class M_Kardex {
    // Instancia Singleton
    private static $instancia = null;
    // Conexión PDO
    private $conexion;

    // Constructor privado
    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    // Obtener la instancia única del modelo
    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Retorna todos los productos activos con sus categorías y unidades para llenar el selector de productos en la UI del Kardex
     * @return array Listado asociativo de productos activos
     */
    public function listarProductos() {
        try {
            $sql = "SELECT i.id_producto, i.nombre, c.nombre AS categoria, u.abreviatura,
                           i.precio_unitario, i.stock_piezas,
                           t.nombre AS talla,
                           p.nombre AS nombre_padre
                    FROM productos i
                    INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                    INNER JOIN unidades_medida u ON i.id_unidad = u.id_unidad
                    LEFT JOIN tallas t ON i.id_talla = t.id_talla
                    LEFT JOIN productos p ON i.id_producto_padre = p.id_producto
                    WHERE i.estado = 1 AND i.es_agrupador = 0
                    ORDER BY c.nombre, nombre_padre, t.orden, i.nombre";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtiene el historial de movimientos de inventario (Kardex) para un producto específico.
     * Calcula la sumatoria histórica para reconstruir el saldo inicial del producto hacia atrás.
     * Cada fila resultante contiene: fecha, tipo_doc, numero_doc, concepto,
     *   entrada_cant, entrada_cu, entrada_ct,
     *   salida_cant, salida_cu, salida_ct,
     *   saldo_cant, saldo_cu, saldo_ct
     * 
     * Los movimientos de salida se extraen de la tabla detalle_ventas.
     * 
     * @param int $id_producto ID del producto a analizar
     * @param string|null $desde Fecha de inicio del filtro
     * @param string|null $hasta Fecha de fin del filtro
     * @param string $tipo Tipo de movimiento a filtrar ('entrada', 'salida', 'todos')
     * @param string $busqueda Palabra clave para buscar por vendedor, cliente o código
     * @return array Historial detallado del Kardex y estadísticas de stock
     */
    public function obtenerMovimientos($id_producto, $desde = null, $hasta = null, $tipo = 'todos', $busqueda = '') {
        try {
            $params = [$id_producto];
            $fechaWhere = '';
            if ($desde) { $fechaWhere .= ' AND v.fecha >= ?'; $params[] = $desde . ' 00:00:00'; }
            if ($hasta)  { $fechaWhere .= ' AND v.fecha <= ?'; $params[] = $hasta  . ' 23:59:59'; }
            $busquedaWhere = '';
            if ($busqueda) {
                $busquedaWhere = ' AND (v.id_venta LIKE ? OR p.nombres_razon_social LIKE ?)';
                $params[] = '%' . $busqueda . '%';
                $params[] = '%' . $busqueda . '%';
            }

            // 1a. Salidas por ventas
            $sql = "SELECT
                        v.fecha,
                        CASE v.tipo_comprobante
                            WHEN 1 THEN 'Boleta'
                            WHEN 2 THEN 'Factura'
                            WHEN 3 THEN 'Nota de Venta'
                            ELSE 'Comprobante'
                        END AS tipo_doc,
                        LPAD(v.id_venta, 6, '0') AS numero_doc,
                        CONCAT('Venta a ', p.nombres_razon_social, IFNULL(CONCAT(' ', p.apellidos), '')) AS concepto,
                        dv.cantidad AS salida_cant,
                        NULL AS salida_peso,
                        dv.precio_venta AS costo_unit,
                        dv.subtotal AS salida_ct,
                        'salida' AS tipo_movimiento
                    FROM detalle_ventas dv
                    INNER JOIN ventas v ON dv.id_venta = v.id_venta
                    INNER JOIN clientes cl ON v.id_cliente = cl.id_cliente
                    INNER JOIN personas p ON cl.id_persona = p.id_persona
                    WHERE dv.id_producto = ?
                      AND v.estado = 1
                      {$fechaWhere}
                      {$busquedaWhere}
                    ORDER BY v.fecha ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            $salidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 1b. Movimientos manuales del kardex
            $paramsMov = [$id_producto];
            $fechaWhereMov = '';
            if ($desde) { $fechaWhereMov .= ' AND km.fecha >= ?'; $paramsMov[] = $desde . ' 00:00:00'; }
            if ($hasta)  { $fechaWhereMov .= ' AND km.fecha <= ?'; $paramsMov[] = $hasta  . ' 23:59:59'; }
            $sqlMov = "SELECT km.fecha, km.tipo AS tipo_movimiento, km.cantidad,
                              km.precio_unitario AS costo_unit, km.concepto,
                              km.referencia,
                              CONCAT(per.nombres_razon_social, IFNULL(CONCAT(' ', per.apellidos), '')) AS usuario_nombre
                       FROM kardex_movimientos km
                       INNER JOIN usuarios u ON km.id_usuario = u.id_usuario
                       INNER JOIN personas per ON u.id_persona = per.id_persona
                       WHERE km.id_producto = ? {$fechaWhereMov}
                       ORDER BY km.fecha ASC";
            $stmtMov = $this->conexion->prepare($sqlMov);
            $stmtMov->execute($paramsMov);
            $movManuales = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

            // 2. Obtener los datos básicos de stock y unidad del producto
            $infoStmt = $this->conexion->prepare(
                "SELECT i.nombre, c.nombre AS categoria, u.abreviatura, i.precio_unitario, i.stock_piezas
                 FROM productos i
                 INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                 INNER JOIN unidades_medida u ON i.id_unidad = u.id_unidad
                 WHERE i.id_producto = ?"
            );
            $infoStmt->execute([$id_producto]);
            $producto = $infoStmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) return ['error' => 'Producto no encontrado'];

            // 3. Reconstruir stock desde cero
            $totalVendido    = array_sum(array_column($salidas, 'salida_cant'));
            $totalSalidasMan = 0; $totalEntradasMan = 0;
            foreach ($movManuales as $mm) {
                if ($mm['tipo_movimiento'] === 'salida') $totalSalidasMan += floatval($mm['cantidad']);
                else $totalEntradasMan += floatval($mm['cantidad']);
            }
            $stockActual  = floatval($producto['stock_piezas']);
            $stockInicial = $stockActual + $totalVendido + $totalSalidasMan - $totalEntradasMan;
            $precioUnit   = floatval($producto['precio_unitario']);

            // 4. Construir filas (ventas + manuales fusionadas y ordenadas)
            $rows     = [];
            $saldoCant = 0;

            if ($tipo !== 'salida' && !$desde && !$busqueda) {
                $saldoCant = $stockInicial;
                $rows[] = [
                    'fecha' => null, 'tipo_doc' => 'Saldo Inicial', 'numero_doc' => '—',
                    'concepto' => 'Stock de apertura',
                    'entrada_cant' => $stockInicial, 'entrada_cu' => $precioUnit, 'entrada_ct' => $stockInicial * $precioUnit,
                    'salida_cant'  => null, 'salida_cu' => null, 'salida_ct' => null,
                    'saldo_cant' => $saldoCant, 'saldo_cu' => $precioUnit, 'saldo_ct' => $saldoCant * $precioUnit,
                    'tipo_movimiento' => 'entrada',
                ];
            } else {
                $saldoCant = $stockInicial;
            }

            // 5. Unir ventas + movimientos manuales y ordenar por fecha
            $allMovs = [];
            foreach ($salidas as $mov) {
                if ($tipo === 'entrada') continue;
                $allMovs[] = ['fecha' => $mov['fecha'], '_src' => 'venta', '_data' => $mov];
            }
            foreach ($movManuales as $mov) {
                if ($tipo === 'entrada' && $mov['tipo_movimiento'] === 'salida') continue;
                if ($tipo === 'salida' && $mov['tipo_movimiento'] === 'entrada') continue;
                $allMovs[] = ['fecha' => $mov['fecha'], '_src' => 'manual', '_data' => $mov];
            }
            usort($allMovs, fn($a, $b) => strtotime($a['fecha']) - strtotime($b['fecha']));

            foreach ($allMovs as $item) {
                if ($item['_src'] === 'venta') {
                    $mov  = $item['_data'];
                    $cant = floatval($mov['salida_cant']);
                    $cu   = floatval($mov['costo_unit']);
                    $saldoCant -= $cant;
                    $rows[] = [
                        'fecha' => $mov['fecha'], 'tipo_doc' => $mov['tipo_doc'],
                        'numero_doc' => 'V-' . $mov['numero_doc'], 'concepto' => $mov['concepto'],
                        'entrada_cant' => null, 'entrada_cu' => null, 'entrada_ct' => null,
                        'salida_cant' => $cant, 'salida_peso' => floatval($mov['salida_peso']),
                        'salida_cu' => $cu, 'salida_ct' => $mov['salida_ct'],
                        'saldo_cant' => $saldoCant, 'saldo_cu' => $cu, 'saldo_ct' => $saldoCant * $cu,
                        'tipo_movimiento' => 'salida',
                    ];
                } else {
                    $mov  = $item['_data'];
                    $cant = floatval($mov['cantidad']);
                    $cu   = floatval($mov['costo_unit']) ?: $precioUnit;
                    if ($mov['tipo_movimiento'] === 'entrada') {
                        $saldoCant += $cant;
                        $rows[] = [
                            'fecha' => $mov['fecha'], 'tipo_doc' => 'Entrada Manual',
                            'numero_doc' => $mov['referencia'] ?? '—', 'concepto' => $mov['concepto'],
                            'entrada_cant' => $cant, 'entrada_cu' => $cu, 'entrada_ct' => $cant * $cu,
                            'salida_cant' => null, 'salida_cu' => null, 'salida_ct' => null,
                            'saldo_cant' => $saldoCant, 'saldo_cu' => $cu, 'saldo_ct' => $saldoCant * $cu,
                            'tipo_movimiento' => 'entrada',
                        ];
                    } else {
                        $saldoCant -= $cant;
                        $rows[] = [
                            'fecha' => $mov['fecha'], 'tipo_doc' => 'Salida Manual',
                            'numero_doc' => '—', 'concepto' => $mov['concepto'] . ($mov['referencia'] ? ' — ' . $mov['referencia'] : ''),
                            'entrada_cant' => null, 'entrada_cu' => null, 'entrada_ct' => null,
                            'salida_cant' => $cant, 'salida_cu' => $cu, 'salida_ct' => $cant * $cu,
                            'saldo_cant' => $saldoCant, 'saldo_cu' => $cu, 'saldo_ct' => $saldoCant * $cu,
                            'tipo_movimiento' => 'salida',
                        ];
                    }
                }
            }

            // Estadísticas
            $totalEntradaCant = $stockInicial + $totalEntradasMan;
            $totalSalidaCant  = $totalVendido  + $totalSalidasMan;

            return [
                'producto'           => $producto,
                'rows'             => $rows,
                'total_entrada_cant' => $totalEntradaCant,
                'total_salida_cant'  => $totalSalidaCant,
                'stock_actual'       => $stockActual,
                'precio_unitario'    => $precioUnit,
            ];

        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Registra un movimiento manual de entrada o salida en el kardex
     */
    public function registrarMovimiento($id_producto, $tipo, $cantidad, $precio_unitario, $referencia, $concepto, $id_usuario) {
        try {
            $sql = "INSERT INTO kardex_movimientos (id_producto, tipo, cantidad, precio_unitario, referencia, concepto, id_usuario, fecha)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_producto, $tipo, $cantidad, $precio_unitario, $referencia, $concepto, $id_usuario]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
