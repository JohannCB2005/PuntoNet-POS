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
            $sql = "SELECT i.id_producto, i.nombre, i.precio_unitario, i.stock_piezas, i.contenido_estandar, i.imagen,
                           um.abreviatura AS unidad, c.nombre AS categoria
                    FROM productos i
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
     *               'precio_desde', 'stock_total', 'variantes' (array de variantes con id_producto, talla, precio, stock)
     */
    public function getCatalogoAgrupado(): array {
        try {
            $grupos = [];

            // 1. Productos padre con sus hijos (variantes de talla)
            $sqlPadres = "SELECT p.id_producto AS id_padre, p.nombre, p.imagen,
                                 c.nombre AS categoria, c.id_categoria,
                                 h.id_producto AS var_id, h.precio_unitario AS var_precio,
                                 h.stock_piezas AS var_stock,
                                 t.nombre AS var_talla, t.id_talla, t.orden AS var_orden
                          FROM productos p
                          INNER JOIN categorias c ON p.id_categoria = c.id_categoria
                          INNER JOIN productos h ON h.id_producto_padre = p.id_producto
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
                        'id_producto' => (int) $row['var_id'],
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
            $sqlSimples = "SELECT i.id_producto, i.nombre, i.imagen, i.precio_unitario, i.stock_piezas,
                                  c.nombre AS categoria, c.id_categoria, um.abreviatura AS unidad
                           FROM productos i
                           INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                           INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                           WHERE i.estado = 1 AND i.stock_piezas > 0
                             AND i.es_agrupador = 0 AND i.id_producto_padre IS NULL
                           ORDER BY c.nombre ASC, i.nombre ASC";
            $stmtS = $this->conexion->prepare($sqlSimples);
            $stmtS->execute();
            $simples = $stmtS->fetchAll(PDO::FETCH_ASSOC);

            foreach ($simples as $s) {
                $grupos['s_' . $s['id_producto']] = [
                    'id'          => (int) $s['id_producto'],
                    'nombre'      => $s['nombre'],
                    'imagen'      => $s['imagen'],
                    'categoria'   => $s['categoria'],
                    'id_categoria'=> $s['id_categoria'],
                    'tiene_tallas'=> false,
                    'precio_desde'=> floatval($s['precio_unitario']),
                    'stock_total' => (int) $s['stock_piezas'],
                    'variantes'   => [[
                        'id_producto' => (int) $s['id_producto'],
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

    /**
     * Única fuente de verdad de precios del carrito público. Ignora cualquier
     * precio/subtotal/total enviado por el cliente: sólo confía en id_producto + cantidad,
     * y siempre relee precio_unitario y stock desde la base de datos.
     *
     * @param array $carritoCliente [['id_producto'=>int, 'cantidad'=>int], ...]
     * @param bool  $bloquear       true = usa SELECT ... FOR UPDATE (requiere transacción activa)
     */
    public function calcularCarrito(array $carritoCliente, bool $bloquear = false): array {
        $items = [];
        foreach ($carritoCliente as $it) {
            $id = intval($it['id_producto'] ?? 0);
            $cant = intval($it['cantidad'] ?? 0);
            if ($id <= 0 || $cant <= 0) continue;
            $cant = min($cant, 999);
            $items[$id] = ($items[$id] ?? 0) + $cant;
        }
        if (empty($items)) {
            return ['ok' => false, 'mensaje' => 'El carrito está vacío o es inválido.'];
        }

        $ids = array_keys($items);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $forUpdate = $bloquear ? ' FOR UPDATE' : '';
        $sql = "SELECT id_producto, nombre, precio_unitario, stock_piezas, estado, es_agrupador
                FROM productos WHERE id_producto IN ($placeholders)" . $forUpdate;
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($ids);
        $porId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $porId[(int) $r['id_producto']] = $r;
        }

        $resultado = [];
        $total = 0.0;
        $partesHash = [];
        foreach ($items as $id => $cantidad) {
            if (!isset($porId[$id])) {
                return ['ok' => false, 'mensaje' => "Uno de los productos ya no está disponible."];
            }
            $p = $porId[$id];
            if ((int) $p['estado'] !== 1) {
                return ['ok' => false, 'mensaje' => "\"{$p['nombre']}\" ya no está disponible."];
            }
            if ((int) $p['es_agrupador'] === 1) {
                return ['ok' => false, 'mensaje' => "\"{$p['nombre']}\" requiere elegir una talla/variante."];
            }
            if ((float) $p['stock_piezas'] < $cantidad) {
                return ['ok' => false, 'mensaje' => "Stock insuficiente para \"{$p['nombre']}\"."];
            }
            $precio = round((float) $p['precio_unitario'], 2);
            $subtotal = round($precio * $cantidad, 2);
            $total += $subtotal;
            $resultado[] = [
                'id_producto'     => $id,
                'nombre'          => $p['nombre'],
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio,
                'subtotal'        => $subtotal,
            ];
            $partesHash[] = $id . ':' . $cantidad;
        }
        sort($partesHash);

        return [
            'ok'    => true,
            'total' => round($total, 2),
            'items' => $resultado,
            'hash'  => substr(hash('sha256', implode(';', $partesHash)), 0, 32),
        ];
    }

    /**
     * Crea un pedido en estado "pendiente de pago" (3) y reserva el stock de inmediato.
     * El monto cobrado en Stripe y el registrado aquí provienen SIEMPRE de calcularCarrito().
     */
    public function crearPedidoPendiente(array $cliente, array $carritoCliente, string $paymentIntentId): array {
        try {
            $this->conexion->beginTransaction();

            $stmtDup = $this->conexion->prepare("SELECT id_pedido FROM pedidos_online WHERE payment_intent_id = ?");
            $stmtDup->execute([$paymentIntentId]);
            if ($stmtDup->fetch()) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'Este pago ya fue utilizado para otro pedido.'];
            }

            $calc = $this->calcularCarrito($carritoCliente, true);
            if (!$calc['ok']) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => $calc['mensaje']];
            }

            // 1. Buscar o crear persona
            $stmtPersona = $this->conexion->prepare("SELECT id_persona FROM personas WHERE numero_documento = ? AND tipo_documento = 1 LIMIT 1");
            $stmtPersona->execute([$cliente['dni']]);
            $rowP = $stmtPersona->fetch();

            if ($rowP) {
                $id_persona = $rowP['id_persona'];
                $stmtUpdate = $this->conexion->prepare("UPDATE personas SET telefono = COALESCE(telefono, ?), direccion = COALESCE(direccion, ?) WHERE id_persona = ?");
                $stmtUpdate->execute([$cliente['telefono'] ?? null, $cliente['direccion'] ?? null, $id_persona]);
            } else {
                $stmtInsertP = $this->conexion->prepare("INSERT INTO personas (tipo_documento, numero_documento, nombres_razon_social, apellidos, telefono, direccion, estado) VALUES (1, ?, ?, ?, ?, ?, 1)");
                $stmtInsertP->execute([$cliente['dni'], $cliente['nombres'], $cliente['apellidos'] ?? '', $cliente['telefono'] ?? null, $cliente['direccion'] ?? null]);
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

            // 3. Crear pedido en estado 3 (pendiente de pago), reserva de 30 minutos
            $token = bin2hex(random_bytes(16));
            $stmtPedido = $this->conexion->prepare(
                "INSERT INTO pedidos_online (id_cliente, total, payment_intent_id, fecha_expira, token_publico, estado)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?, 3)"
            );
            $stmtPedido->execute([$id_cliente, $calc['total'], $paymentIntentId, $token]);
            $id_pedido = $this->conexion->lastInsertId();

            // 4. Detalles + descuento de stock, siempre con los precios de calcularCarrito()
            $stmtDetalle = $this->conexion->prepare("INSERT INTO detalle_pedidos_online (id_pedido, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmtStockUpdate = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas - ? WHERE id_producto = ?");
            foreach ($calc['items'] as $item) {
                $stmtDetalle->execute([$id_pedido, $item['id_producto'], $item['cantidad'], $item['precio_unitario'], $item['subtotal']]);
                $stmtStockUpdate->execute([$item['cantidad'], $item['id_producto']]);
            }

            $this->conexion->commit();
            return ['ok' => true, 'id_pedido' => $id_pedido, 'token' => $token, 'total' => $calc['total']];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Único camino para promover un pedido de estado 3 (pendiente de pago) a 1 (pagado).
     * Idempotente: si ya estaba confirmado, no vuelve a mutar nada.
     */
    public function confirmarPagoPedido(int $id_pedido, string $paymentIntentId): array {
        try {
            $this->conexion->beginTransaction();

            $stmt = $this->conexion->prepare("SELECT estado, payment_intent_id FROM pedidos_online WHERE id_pedido = ? FOR UPDATE");
            $stmt->execute([$id_pedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'Pedido no encontrado.'];
            }
            if (in_array((int) $pedido['estado'], [1, 2], true)) {
                $this->conexion->commit();
                return ['ok' => true, 'ya_confirmado' => true];
            }
            if ((int) $pedido['estado'] !== 3) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'El pedido no está pendiente de pago.'];
            }
            if (!hash_equals((string) $pedido['payment_intent_id'], $paymentIntentId)) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'El pago no corresponde a este pedido.'];
            }

            $upd = $this->conexion->prepare("UPDATE pedidos_online SET estado = 1, fecha_pago = NOW() WHERE id_pedido = ? AND estado = 3");
            $upd->execute([$id_pedido]);

            $this->conexion->commit();
            return ['ok' => true, 'ya_confirmado' => false];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /** Libera la reserva de un pedido no pagado: devuelve stock y marca estado 4 (expirado). */
    private function expirarPedido(int $id_pedido): void {
        try {
            $this->conexion->beginTransaction();

            $stmt = $this->conexion->prepare("SELECT id_pedido FROM pedidos_online WHERE id_pedido = ? AND estado = 3 FOR UPDATE");
            $stmt->execute([$id_pedido]);
            if (!$stmt->fetch()) {
                $this->conexion->rollBack();
                return;
            }

            $stmtDet = $this->conexion->prepare("SELECT id_producto, cantidad FROM detalle_pedidos_online WHERE id_pedido = ?");
            $stmtDet->execute([$id_pedido]);
            $stmtRestore = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ?");
            foreach ($stmtDet->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $stmtRestore->execute([$d['cantidad'], $d['id_producto']]);
            }

            $upd = $this->conexion->prepare("UPDATE pedidos_online SET estado = 4 WHERE id_pedido = ? AND estado = 3");
            $upd->execute([$id_pedido]);

            $this->conexion->commit();
        } catch (Exception $e) {
            $this->conexion->rollBack();
        }
    }

    /**
     * Red de seguridad ante la falta de cron/webhook garantizado en InfinityFree:
     * rescata pedidos cuyo pago sí llegó a completarse y libera stock de los que no.
     */
    public function barrerPedidosVencidos(int $limite = 20): void {
        try {
            $limite = max(1, min($limite, 100));
            $stmt = $this->conexion->prepare(
                "SELECT id_pedido, payment_intent_id FROM pedidos_online
                 WHERE estado = 3 AND fecha_expira < NOW()
                 ORDER BY fecha_expira ASC LIMIT " . intval($limite)
            );
            $stmt->execute();
            $vencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return;
        }
        if (empty($vencidos)) return;

        require_once dirname(__DIR__) . '/models/M_Stripe.php';
        $stripe = M_Stripe::singleton();

        foreach ($vencidos as $v) {
            $id_pedido = (int) $v['id_pedido'];
            if (empty($v['payment_intent_id'])) {
                $this->expirarPedido($id_pedido);
                continue;
            }
            $r = $stripe->obtenerPaymentIntent($v['payment_intent_id']);
            $status = $r['data']['status'] ?? '';
            if ($r['ok'] && $status === 'succeeded') {
                $this->confirmarPagoPedido($id_pedido, $v['payment_intent_id']);
            } elseif ($r['ok'] && in_array($status, ['canceled', 'requires_payment_method'], true)) {
                $this->expirarPedido($id_pedido);
            } else {
                // Seguimos sin certeza (o error de red con Stripe): damos 15 min más
                $upd = $this->conexion->prepare("UPDATE pedidos_online SET fecha_expira = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id_pedido = ? AND estado = 3");
                $upd->execute([$id_pedido]);
            }
        }
    }

    public function buscarPorPaymentIntent(string $paymentIntentId): ?array {
        try {
            $stmt = $this->conexion->prepare("SELECT id_pedido FROM pedidos_online WHERE payment_intent_id = ?");
            $stmt->execute([$paymentIntentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /** Requiere el token público exacto del pedido; nunca expone la dirección del cliente. */
    public function getPedidoPublico($id_pedido, $token) {
        try {
            $sql = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.estado, p.token_publico,
                           per.numero_documento, per.nombres_razon_social, per.apellidos, per.telefono
                    FROM pedidos_online p
                    INNER JOIN clientes c ON p.id_cliente = c.id_cliente
                    INNER JOIN personas per ON c.id_persona = per.id_persona
                    WHERE p.id_pedido = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_pedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido || !hash_equals((string) $pedido['token_publico'], (string) $token)) {
                return null;
            }
            unset($pedido['token_publico']);
            $pedido['detalles'] = $this->getDetallesPedido($id_pedido);
            return $pedido;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function listarPedidos() {
        $this->barrerPedidosVencidos();
        try {
            $sql = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.nro_operacion_yape, p.payment_intent_id, p.fecha_pago, p.estado,
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
            $sql = "SELECT d.cantidad, d.precio_unitario, d.subtotal,
                           i.nombre, um.abreviatura
                    FROM detalle_pedidos_online d
                    INNER JOIN productos i ON d.id_producto = i.id_producto
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
                throw new Exception("El pedido no existe, no está pagado, o ya ha sido procesado.");
            }

            if ($accion === 'rechazar') {
                $stmtRestore = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ?");
                $stmtDetalleIds = $this->conexion->prepare("SELECT id_producto, cantidad FROM detalle_pedidos_online WHERE id_pedido = ?");
                $stmtDetalleIds->execute([$id_pedido]);
                foreach ($stmtDetalleIds->fetchAll() as $dr) {
                    $stmtRestore->execute([$dr['cantidad'], $dr['id_producto']]);
                }

                $stmtUpdate = $this->conexion->prepare("UPDATE pedidos_online SET estado = 0 WHERE id_pedido = ?");
                $stmtUpdate->execute([$id_pedido]);

                $this->conexion->commit();
                return ['ok' => true, 'mensaje' => 'Pedido rechazado y stock restaurado.'];
            } elseif ($accion === 'aprobar') {
                // Método de pago = 3 (Tarjeta), pagado vía Stripe
                $stmtInsertVenta = $this->conexion->prepare("INSERT INTO ventas (id_usuario, id_cliente, tipo_comprobante, total, metodo_pago, estado) VALUES (?, ?, 1, ?, 3, 1)");
                $stmtInsertVenta->execute([$id_usuario_cajero, $pedido['id_cliente'], $pedido['total']]);
                $id_venta = $this->conexion->lastInsertId();

                $stmtDetallesPedido = $this->conexion->prepare("SELECT id_producto, cantidad, precio_unitario, subtotal FROM detalle_pedidos_online WHERE id_pedido = ?");
                $stmtDetallesPedido->execute([$id_pedido]);
                $detalles = $stmtDetallesPedido->fetchAll();

                $stmtInsertDetalleVenta = $this->conexion->prepare("INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_venta, costo_unitario, subtotal) VALUES (?, ?, ?, ?, (SELECT costo_produccion FROM productos WHERE id_producto = ?), ?)");

                foreach ($detalles as $d) {
                    $stmtInsertDetalleVenta->execute([
                        $id_venta, $d['id_producto'], $d['cantidad'], $d['precio_unitario'], $d['id_producto'], $d['subtotal']
                    ]);
                }

                $stmtUpdate = $this->conexion->prepare("UPDATE pedidos_online SET estado = 2, id_venta = ? WHERE id_pedido = ?");
                $stmtUpdate->execute([$id_venta, $id_pedido]);

                $this->conexion->commit();
                return ['ok' => true, 'mensaje' => 'Pedido aprobado y venta generada.', 'id_venta' => $id_venta];
            }

            throw new Exception("Acción inválida.");
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }
}
?>
