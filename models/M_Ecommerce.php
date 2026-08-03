<?php
require_once dirname(__DIR__) . '/config/conexion.php';

class M_Ecommerce {
    private $conexion;
    private static $instancia = null;

    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    public static function singleton() {
        if (!isset(self::$instancia)) {
            $miclase = __CLASS__;
            self::$instancia = new $miclase;
        }
        return self::$instancia;
    }

    public function getCatalogo() {
        try {
            $sql = "SELECT i.id_insumo, i.nombre, i.precio_unitario, i.stock_piezas, i.contenido_estandar, i.imagen,
                           um.abreviatura AS unidad, c.nombre AS categoria
                    FROM insumos i
                    INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                    INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                    WHERE i.estado = 1 AND i.stock_piezas > 0
                      AND i.es_agrupador = 0
                    ORDER BY c.nombre, i.nombre";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Retorna el catálogo agrupado para la tienda pública y el POS.
     * - Los productos con variantes de talla se entregan como un solo bloque con su lista de variantes.
     * - Los productos simples (sin padre) se entregan con una sola variante que es el producto mismo.
     * El producto padre (es_agrupador=1) NUNCA aparece como item vendible.
     *
     * @return array Array de grupos: cada grupo tiene 'tiene_tallas', 'nombre', 'imagen', 'categoria',
     *               'precio_desde', 'stock_total', 'variantes' (array de variantes con id_insumo, talla, precio, stock)
     */
    public function getCatalogoAgrupado(): array {
        try {
            $grupos = [];

            // 1. Productos padre con sus hijos (variantes de talla)
            $sqlPadres = "SELECT p.id_insumo AS id_padre, p.nombre, p.imagen,
                                 c.nombre AS categoria, c.id_categoria,
                                 h.id_insumo AS var_id, h.precio_unitario AS var_precio,
                                 h.stock_piezas AS var_stock,
                                 t.nombre AS var_talla, t.id_talla, t.orden AS var_orden
                          FROM insumos p
                          INNER JOIN categorias c ON p.id_categoria = c.id_categoria
                          INNER JOIN insumos h ON h.id_producto_padre = p.id_insumo
                                               AND h.estado = 1
                          LEFT JOIN tallas t ON h.id_talla = t.id_talla
                          WHERE p.es_agrupador = 1 AND p.estado = 1
                          ORDER BY c.nombre ASC, p.nombre ASC, t.orden ASC, t.nombre ASC";
            $stmtP = $this->conexion->prepare($sqlPadres);
            $stmtP->execute();
            $rowsPadres = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rowsPadres as $row) {
                $key = 'p_' . $row['id_padre'];
                if (!isset($grupos[$key])) {
                    $grupos[$key] = [
                        'id'          => $row['id_padre'],
                        'nombre'      => $row['nombre'],
                        'imagen'      => $row['imagen'],
                        'categoria'   => $row['categoria'],
                        'id_categoria'=> $row['id_categoria'],
                        'tiene_tallas'=> true,
                        'precio_desde'=> null,
                        'stock_total' => 0,
                        'variantes'   => [],
                    ];
                }
                // Solo agregar variantes con stock disponible
                if ($row['var_stock'] > 0) {
                    $precio = floatval($row['var_precio']);
                    $grupos[$key]['variantes'][] = [
                        'id_insumo' => (int) $row['var_id'],
                        'talla'     => $row['var_talla'],
                        'id_talla'  => $row['id_talla'],
                        'precio'    => $precio,
                        'stock'     => (int) $row['var_stock'],
                    ];
                    $grupos[$key]['stock_total'] += (int) $row['var_stock'];
                    if ($grupos[$key]['precio_desde'] === null || $precio < $grupos[$key]['precio_desde']) {
                        $grupos[$key]['precio_desde'] = $precio;
                    }
                }
            }

            // 2. Productos simples (sin padre, no agrupador)
            $sqlSimples = "SELECT i.id_insumo, i.nombre, i.imagen, i.precio_unitario, i.stock_piezas,
                                  c.nombre AS categoria, c.id_categoria, um.abreviatura AS unidad
                           FROM insumos i
                           INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                           INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                           WHERE i.estado = 1 AND i.stock_piezas > 0
                             AND i.es_agrupador = 0 AND i.id_producto_padre IS NULL
                           ORDER BY c.nombre ASC, i.nombre ASC";
            $stmtS = $this->conexion->prepare($sqlSimples);
            $stmtS->execute();
            $simples = $stmtS->fetchAll(PDO::FETCH_ASSOC);

            foreach ($simples as $s) {
                $grupos['s_' . $s['id_insumo']] = [
                    'id'          => (int) $s['id_insumo'],
                    'nombre'      => $s['nombre'],
                    'imagen'      => $s['imagen'],
                    'categoria'   => $s['categoria'],
                    'id_categoria'=> $s['id_categoria'],
                    'tiene_tallas'=> false,
                    'precio_desde'=> floatval($s['precio_unitario']),
                    'stock_total' => (int) $s['stock_piezas'],
                    'variantes'   => [[
                        'id_insumo' => (int) $s['id_insumo'],
                        'talla'     => null,
                        'id_talla'  => null,
                        'precio'    => floatval($s['precio_unitario']),
                        'stock'     => (int) $s['stock_piezas'],
                    ]],
                ];
            }

            // Excluir grupos padre que quedaron sin variantes con stock
            return array_values(array_filter($grupos, fn($g) => !empty($g['variantes'])));

        } catch (PDOException $e) {
            return [];
        }
    }

    public function crearPedido($cliente, $nro_operacion, $total, $carrito) {
        try {
            $this->conexion->beginTransaction();

            // 1. Buscar o crear persona
            $stmtPersona = $this->conexion->prepare("SELECT id_persona FROM personas WHERE numero_documento = ? AND tipo_documento = 1 LIMIT 1");
            $stmtPersona->execute([$cliente['dni']]);
            $rowP = $stmtPersona->fetch();

            if ($rowP) {
                $id_persona = $rowP['id_persona'];
                // Update phone/address if empty
                $stmtUpdate = $this->conexion->prepare("UPDATE personas SET telefono = COALESCE(telefono, ?), direccion = COALESCE(direccion, ?) WHERE id_persona = ?");
                $stmtUpdate->execute([$cliente['telefono'], $cliente['direccion'], $id_persona]);
            } else {
                $stmtInsertP = $this->conexion->prepare("INSERT INTO personas (tipo_documento, numero_documento, nombres_razon_social, apellidos, telefono, direccion, estado) VALUES (1, ?, ?, ?, ?, ?, 1)");
                $stmtInsertP->execute([$cliente['dni'], $cliente['nombres'], $cliente['apellidos'], $cliente['telefono'], $cliente['direccion']]);
                $id_persona = $this->conexion->lastInsertId();
            }

            // 2. Buscar o crear cliente
            $stmtCliente = $this->conexion->prepare("SELECT id_cliente FROM clientes WHERE id_persona = ? LIMIT 1");
            $stmtCliente->execute([$id_persona]);
            $rowC = $stmtCliente->fetch();

            if ($rowC) {
                $id_cliente = $rowC['id_cliente'];
            } else {
                $stmtInsertC = $this->conexion->prepare("INSERT INTO clientes (id_persona, tipo_cliente) VALUES (?, 1)");
                $stmtInsertC->execute([$id_persona]);
                $id_cliente = $this->conexion->lastInsertId();
            }

            // 3. Crear pedido
            $stmtPedido = $this->conexion->prepare("INSERT INTO pedidos_online (id_cliente, total, nro_operacion_yape, estado) VALUES (?, ?, ?, 1)");
            $stmtPedido->execute([$id_cliente, $total, $nro_operacion]);
            $id_pedido = $this->conexion->lastInsertId();

            // 4. Detalles del pedido y deducción de stock reservado
            $stmtDetalle = $this->conexion->prepare("INSERT INTO detalle_pedidos_online (id_pedido, id_insumo, cantidad, peso_neto, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtStockCheck = $this->conexion->prepare("SELECT stock_piezas FROM insumos WHERE id_insumo = ? FOR UPDATE");
            $stmtStockUpdate = $this->conexion->prepare("UPDATE insumos SET stock_piezas = stock_piezas - ? WHERE id_insumo = ?");

            foreach ($carrito as $item) {
                $id_insumo = $item['id_insumo'];
                $cantidad = $item['cantidad'];
                $peso_neto = $item['peso_neto'] ?? 0;
                $precio = $item['precio'];
                $subtotal = $item['subtotal'];

                // Check stock
                $stmtStockCheck->execute([$id_insumo]);
                $stock = $stmtStockCheck->fetchColumn();
                if ($stock < $cantidad) {
                    throw new Exception("Stock insuficiente para el insumo ID " . $id_insumo);
                }

                // Insert detail
                $stmtDetalle->execute([$id_pedido, $id_insumo, $cantidad, $peso_neto, $precio, $subtotal]);

                // Deduct stock
                $stmtStockUpdate->execute([$cantidad, $id_insumo]);
            }

            $this->conexion->commit();
            return ['ok' => true, 'id_pedido' => $id_pedido, 'mensaje' => 'Pedido registrado correctamente.'];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public function getPedidoPublico($id_pedido) {
        try {
            $sql = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.estado, p.nro_operacion_yape,
                           per.numero_documento, per.nombres_razon_social, per.apellidos, per.telefono, per.direccion
                    FROM pedidos_online p
                    INNER JOIN clientes c ON p.id_cliente = c.id_cliente
                    INNER JOIN personas per ON c.id_persona = per.id_persona
                    WHERE p.id_pedido = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_pedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                $pedido['detalles'] = $this->getDetallesPedido($id_pedido);
                return $pedido;
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function listarPedidos() {
        try {
            $sql = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.nro_operacion_yape, p.estado,
                           per.numero_documento, per.nombres_razon_social, per.apellidos, per.telefono,
                           v.id_venta
                    FROM pedidos_online p
                    INNER JOIN clientes c ON p.id_cliente = c.id_cliente
                    INNER JOIN personas per ON c.id_persona = per.id_persona
                    LEFT JOIN ventas v ON p.id_venta = v.id_venta
                    ORDER BY p.fecha_pedido DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getDetallesPedido($id_pedido) {
        try {
            $sql = "SELECT d.cantidad, d.peso_neto, d.precio_unitario, d.subtotal,
                           i.nombre, um.abreviatura
                    FROM detalle_pedidos_online d
                    INNER JOIN insumos i ON d.id_insumo = i.id_insumo
                    INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                    WHERE d.id_pedido = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_pedido]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function gestionarPedido($id_pedido, $accion, $id_usuario_cajero) {
        try {
            $this->conexion->beginTransaction();

            $stmtCheck = $this->conexion->prepare("SELECT * FROM pedidos_online WHERE id_pedido = ? AND estado = 1 FOR UPDATE");
            $stmtCheck->execute([$id_pedido]);
            $pedido = $stmtCheck->fetch();

            if (!$pedido) {
                throw new Exception("El pedido no existe o ya ha sido procesado.");
            }

            if ($accion === 'rechazar') {
                // Restore stock
                $detalles = $this->getDetallesPedido($id_pedido);
                $stmtRestore = $this->conexion->prepare("UPDATE insumos SET stock_piezas = stock_piezas + ? WHERE id_insumo = ?");
                $stmtDetalleIds = $this->conexion->prepare("SELECT id_insumo, cantidad FROM detalle_pedidos_online WHERE id_pedido = ?");
                $stmtDetalleIds->execute([$id_pedido]);
                $d_rows = $stmtDetalleIds->fetchAll();
                foreach($d_rows as $dr) {
                    $stmtRestore->execute([$dr['cantidad'], $dr['id_insumo']]);
                }
                
                // Mark as rejected
                $stmtUpdate = $this->conexion->prepare("UPDATE pedidos_online SET estado = 0 WHERE id_pedido = ?");
                $stmtUpdate->execute([$id_pedido]);

                $this->conexion->commit();
                return ['ok' => true, 'mensaje' => 'Pedido rechazado y stock restaurado.'];
            } 
            else if ($accion === 'aprobar') {
                // Generar la venta física (Venta POS)
                // Usamos método de pago = 4 (Yape)
                $stmtInsertVenta = $this->conexion->prepare("INSERT INTO ventas (id_usuario, id_cliente, tipo_comprobante, total, metodo_pago, estado) VALUES (?, ?, 1, ?, 4, 1)");
                $stmtInsertVenta->execute([$id_usuario_cajero, $pedido['id_cliente'], $pedido['total']]);
                $id_venta = $this->conexion->lastInsertId();

                // Migrar detalles
                $stmtDetallesPedido = $this->conexion->prepare("SELECT id_insumo, cantidad, peso_neto, precio_unitario, subtotal FROM detalle_pedidos_online WHERE id_pedido = ?");
                $stmtDetallesPedido->execute([$id_pedido]);
                $detalles = $stmtDetallesPedido->fetchAll();

                $stmtInsertDetalleVenta = $this->conexion->prepare("INSERT INTO detalle_ventas (id_venta, id_insumo, cantidad, precio_venta, costo_unitario, subtotal) VALUES (?, ?, ?, ?, (SELECT costo_produccion FROM insumos WHERE id_insumo = ?), ?)");
                
                foreach ($detalles as $d) {
                    $stmtInsertDetalleVenta->execute([
                        $id_venta, $d['id_insumo'], $d['cantidad'], $d['precio_unitario'], $d['id_insumo'], $d['subtotal']
                    ]);
                }

                // Update pedido state
                $stmtUpdate = $this->conexion->prepare("UPDATE pedidos_online SET estado = 2, id_venta = ? WHERE id_pedido = ?");
                $stmtUpdate->execute([$id_venta, $id_pedido]);

                $this->conexion->commit();
                return ['ok' => true, 'mensaje' => 'Pedido aprobado y venta generada.', 'id_venta' => $id_venta];
            }

        } catch (Exception $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }
}
?>
