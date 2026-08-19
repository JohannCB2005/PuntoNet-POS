<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/config/settings.php';

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
            $sql = "SELECT i.id_producto, i.nombre, i.precio_unitario, i.stock_piezas, i.imagen,
                           um.abreviatura AS unidad, c.nombre AS categoria
                    FROM productos i
                    INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                    INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                    WHERE i.estado = 1 AND i.stock_ilimitado = 0 AND i.stock_piezas > 0
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
     * - Los productos con variantes (talla/corbata/bimestre/libre) se entregan como
     *   un solo bloque con su lista de variantes.
     * - Los productos simples (sin padre) se entregan con una sola variante que es el producto mismo.
     * El producto padre (es_agrupador=1) NUNCA aparece como item vendible.
     *
     * Cada grupo expone: 'tiene_tallas', 'tipo_variante', 'nombre', 'imagen', 'categoria',
     * 'id_categoria', 'categorias' (array de ids para filtros multi-etiqueta),
     * 'precio_desde', 'stock_total', 'variantes' (cada una con id_producto, etiqueta, precio, stock).
     *
     * @return array
     */
    public function getCatalogoAgrupado(): array {
        try {
            $grupos = [];

            // Mapa id_producto => categorías (pivote), para filtros multi-etiqueta
            $catMap = [];
            $stmtC = $this->conexion->query(
                "SELECT id_producto, GROUP_CONCAT(id_categoria) AS ids FROM producto_categorias GROUP BY id_producto"
            );
            foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $catMap[(int) $r['id_producto']] = array_map('intval', explode(',', $r['ids']));
            }

            // Mapa id_categoria => nombre, para exponer los nombres en el catálogo
            $catNombreMap = [];
            $stmtN = $this->conexion->query("SELECT id_categoria, nombre FROM categorias WHERE estado = 1");
            foreach ($stmtN->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $catNombreMap[(int) $r['id_categoria']] = $r['nombre'];
            }
            $idsANombres = function (array $ids) use ($catNombreMap): array {
                $nombres = [];
                foreach ($ids as $id) {
                    if (isset($catNombreMap[$id])) $nombres[] = $catNombreMap[$id];
                }
                return $nombres;
            };

            // 1. Productos padre con sus hijos (variantes)
            $sqlPadres = "SELECT p.id_producto AS id_padre, p.nombre, p.imagen,
                                 c.nombre AS categoria, c.id_categoria, p.tipo_variante,
                                 tv.nombre AS tipo_nombre,
                                 h.id_producto AS var_id, h.precio_unitario AS var_precio,
                                 h.stock_piezas AS var_stock,
                                 COALESCE(t.nombre, tc.nombre, b.nombre, h.nombre_variante) AS var_etiqueta,
                                 COALESCE(t.orden, tc.id_tipo_corbata, b.id_bimestre, 9999) AS var_orden
                          FROM productos p
                          INNER JOIN categorias c ON p.id_categoria = c.id_categoria
                          INNER JOIN productos h ON h.id_producto_padre = p.id_producto
                                               AND h.estado = 1 AND h.stock_ilimitado = 0
                          LEFT JOIN tipos_variante tv ON tv.codigo = p.tipo_variante
                          LEFT JOIN tallas t ON h.id_talla = t.id_talla
                          LEFT JOIN tipos_corbata tc ON h.id_tipo_corbata = tc.id_tipo_corbata
                          LEFT JOIN bimestres b ON h.id_bimestre = b.id_bimestre
                          WHERE p.es_agrupador = 1 AND p.estado = 1
                          ORDER BY c.nombre ASC, p.nombre ASC";
            $stmtP = $this->conexion->prepare($sqlPadres);
            $stmtP->execute();
            $rowsPadres = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rowsPadres as $row) {
                $key = 'p_' . $row['id_padre'];
                if (!isset($grupos[$key])) {
                    $idPadre = (int) $row['id_padre'];
                    $grupos[$key] = [
                        'id'          => $idPadre,
                        'nombre'      => $row['nombre'],
                        'imagen'      => $row['imagen'],
                        'categoria'   => $row['categoria'],
                        'id_categoria'=> $row['id_categoria'],
                        'categorias'  => $catMap[$idPadre] ?? [$row['id_categoria']],
                        'categorias_nombre' => $idsANombres($catMap[$idPadre] ?? [$row['id_categoria']]),
                        'tipo_variante'=> $row['tipo_variante'],
                        'tipo_nombre' => $row['tipo_nombre'],
                        'tiene_tallas'=> true,
                        'disponible'  => false,
                        'precio_desde'=> null,
                        'stock_total' => 0,
                        'variantes'   => [],
                    ];
                }
                // Se listan TODAS las variantes (incluidas las de stock 0) para que el
                // producto aparezca como "Agotado" en la tienda en vez de desaparecer.
                $precio = floatval($row['var_precio']);
                $stock  = (int) $row['var_stock'];
                $grupos[$key]['variantes'][] = [
                    'id_producto' => (int) $row['var_id'],
                    'talla'     => $row['var_etiqueta'],
                    'etiqueta'  => $row['var_etiqueta'],
                    'tipo_variante' => $row['tipo_variante'],
                    'precio'    => $precio,
                    'stock'     => $stock,
                ];
                if ($stock > 0) {
                    $grupos[$key]['disponible'] = true;
                    $grupos[$key]['stock_total'] += $stock;
                }
                if ($grupos[$key]['precio_desde'] === null || $precio < $grupos[$key]['precio_desde']) {
                    $grupos[$key]['precio_desde'] = $precio;
                }
            }

            // 2. Productos simples (sin padre, no agrupador)
            $sqlSimples = "SELECT i.id_producto, i.nombre, i.imagen, i.precio_unitario, i.stock_piezas,
                                  i.stock_ilimitado,
                                  c.nombre AS categoria, c.id_categoria, um.abreviatura AS unidad
                           FROM productos i
                           INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                           INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                    WHERE i.estado = 1 AND i.stock_ilimitado = 0
                      AND i.es_agrupador = 0 AND i.id_producto_padre IS NULL
                           ORDER BY c.nombre ASC, i.nombre ASC";
            $stmtS = $this->conexion->prepare($sqlSimples);
            $stmtS->execute();
            $simples = $stmtS->fetchAll(PDO::FETCH_ASSOC);

            foreach ($simples as $s) {
                $ilimitado   = (int) $s['stock_ilimitado'] === 1;
                $stockMostrar = $ilimitado ? 9999 : (int) $s['stock_piezas'];
                $idSimple    = (int) $s['id_producto'];
                $grupos['s_' . $idSimple] = [
                    'id'          => $idSimple,
                    'nombre'      => $s['nombre'],
                    'imagen'      => $s['imagen'],
                    'categoria'   => $s['categoria'],
                    'id_categoria'=> $s['id_categoria'],
                    'categorias'  => $catMap[$idSimple] ?? [$s['id_categoria']],
                    'categorias_nombre' => $idsANombres($catMap[$idSimple] ?? [$s['id_categoria']]),
                    'tipo_variante'=> null,
                    'tiene_tallas'=> false,
                    'disponible'  => $stockMostrar > 0,
                    'precio_desde'=> floatval($s['precio_unitario']),
                    'stock_total' => $stockMostrar,
                    'stock_ilimitado' => $ilimitado,
                    'variantes'   => [[
                        'id_producto' => $idSimple,
                        'talla'     => null,
                        'etiqueta'  => null,
                        'tipo_variante' => null,
                        'precio'    => floatval($s['precio_unitario']),
                        'stock'     => $stockMostrar,
                        'stock_ilimitado' => $ilimitado,
                    ]],
                ];
            }

            // Excluir grupos padre que quedaron sin variantes
            $grupos = array_values(array_filter($grupos, fn($g) => !empty($g['variantes'])));

            // Siempre primero los productos con stock disponible; después por categoría y nombre.
            usort($grupos, function ($a, $b) {
                if (($a['disponible'] ?? false) !== ($b['disponible'] ?? false)) {
                    return ($b['disponible'] ?? false) ? 1 : -1;
                }
                $cmp = strcasecmp($a['categoria'], $b['categoria']);
                if ($cmp !== 0) return $cmp;
                return strcasecmp($a['nombre'], $b['nombre']);
            });

            return $grupos;

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
        $sql = "SELECT id_producto, nombre, precio_unitario, stock_piezas, stock_ilimitado, estado, es_agrupador
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
            if ((int) $p['stock_ilimitado'] === 1) {
                return ['ok' => false, 'mensaje' => "\"{$p['nombre']}\" no está disponible para compra online."];
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
     * Identificador que se envía a Izipay como `orderId`. Determinista a partir del
     * id del pedido, para que no exista una segunda fuente de verdad y para que la
     * referencia sea legible en el Back Office del comercio.
     */
    public static function referenciaPago(int $id_pedido): string {
        return 'PN-' . $id_pedido;
    }

    /**
     * Crea un pedido en estado "pendiente de pago" (3) y reserva el stock de inmediato.
     * El monto cobrado en la pasarela y el registrado aquí provienen SIEMPRE de
     * calcularCarrito(). El cliente ya viene autenticado (sesión).
     *
     * Aquí NO se recibe un identificador de pago externo: el pedido nace primero y su
     * propio id genera la referencia
     * (`PN-{id_pedido}`) que después se envía a Izipay al pedir el FormToken. Por eso
     * tampoco hay chequeo anti-duplicado: la unicidad la da el id del pedido.
     *
     * @param int   $id_cliente_web  id_cliente_web de la cuenta del comprador (clientes_web)
     * @param array $carritoCliente [['id_producto'=>int,'cantidad'=>int], ...]
     * @param array $entrega ['tipo_entrega'=>int, 'estudiante_nombre'=>?string, 'id_nivel'=>?int, 'id_grado'=>?int, 'observaciones'=>?string, 'quien_recoge'=>?string, 'recoge_dni'=>?string, 'recoge_nombre'=>?string]
     * @param int   $tipoComprobante        1=Boleta, 2=Factura
     * @param ?int  $idClienteFacturacion   Entidad a facturar (empresa con RUC) si $tipoComprobante=2.
     *              Ya viene resuelto por C_Ecommerce.php contra el RUC — nunca confiar en un id
     *              recibido tal cual del navegador en niveles superiores a este.
     * @param ?string $metodoPagoOnline      Método presentado en el checkout:
     *              tarjeta|taypi|billetera|transferencia (los manuales reservan más minutos).
     * @param ?int  $minutosReserva          Ventana de reserva (default 10 min; los métodos
     *              manuales usan ~60 min para dar tiempo a transferir).
     */
    public function crearPedidoPendiente(
        int $id_cliente_web,
        array $carritoCliente,
        array $entrega,
        int $tipoComprobante = 1,
        ?int $idClienteFacturacion = null,
        ?string $metodoPagoOnline = null,
        ?int $minutosReserva = null
    ): array {
        try {
            $this->conexion->beginTransaction();

            $calc = $this->calcularCarrito($carritoCliente, true);
            if (!$calc['ok']) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => $calc['mensaje']];
            }

            // Código de confirmación de 4 dígitos (configurable): se genera solo
            // cuando el switch de la tienda está activo. Puede repetirse entre
            // pedidos; la referencia única del pedido sigue siendo el id_pedido.
            $codigoConfirmacion = null;
            if (configuracion('CODIGO_CONFIRMACION_HABILITADO', 'SI') === 'SI') {
                $codigoConfirmacion = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }

            // Crear pedido en estado 3 (pendiente de pago), con ventana de reserva
            // configurable (10 min pasarelas, ~60 min métodos manuales).
            $token = bin2hex(random_bytes(16));
            $tipoEntrega = (int) ($entrega['tipo_entrega'] ?? 1);
            $minutos = $minutosReserva ? max(5, min((int) $minutosReserva, 720)) : 10;
            $stmtPedido = $this->conexion->prepare(
                "INSERT INTO pedidos_online
                    (id_cliente_web, id_cliente_facturacion, total, tipo_comprobante, tipo_entrega,
                     estudiante_nombre, id_nivel, id_grado, observaciones,
                     quien_recoge, recoge_dni, recoge_nombre,
                     fecha_expira, token_publico, estado, metodo_pago_online, codigo_confirmacion)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, 3, ?, ?)"
            );
            $stmtPedido->execute([
                $id_cliente_web,
                $idClienteFacturacion,
                $calc['total'],
                $tipoComprobante,
                $tipoEntrega,
                $entrega['estudiante_nombre'] ?? null,
                $entrega['id_nivel'] ?? null,
                $entrega['id_grado'] ?? null,
                $entrega['observaciones'] ?? null,
                $entrega['quien_recoge'] ?? null,
                $entrega['recoge_dni'] ?? null,
                $entrega['recoge_nombre'] ?? null,
                $minutos,
                $token,
                $metodoPagoOnline,
                $codigoConfirmacion,
            ]);
            $id_pedido = (int) $this->conexion->lastInsertId();

            // La referencia depende del id autogenerado, así que se fija justo después
            // del INSERT y dentro de la misma transacción.
            $referencia = self::referenciaPago($id_pedido);
            $this->conexion->prepare("UPDATE pedidos_online SET referencia_pago = ? WHERE id_pedido = ?")
                 ->execute([$referencia, $id_pedido]);

            // Detalles + descuento de stock, siempre con los precios de calcularCarrito()
            $stmtDetalle = $this->conexion->prepare("INSERT INTO detalle_pedidos_online (id_pedido, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmtStockUpdate = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas - ? WHERE id_producto = ? AND stock_ilimitado = 0");
            foreach ($calc['items'] as $item) {
                $stmtDetalle->execute([$id_pedido, $item['id_producto'], $item['cantidad'], $item['precio_unitario'], $item['subtotal']]);
                $stmtStockUpdate->execute([$item['cantidad'], $item['id_producto']]);
            }

            $this->conexion->commit();
            $stmtFe = $this->conexion->prepare("SELECT fecha_expira FROM pedidos_online WHERE id_pedido = ?");
            $stmtFe->execute([$id_pedido]);
            return [
                'ok'          => true,
                'id_pedido'   => $id_pedido,
                'token'       => $token,
                'total'       => $calc['total'],
                'referencia'  => $referencia,
                'fecha_expira'=> $stmtFe->fetchColumn(),
                'minutos'     => $minutos,
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Cambia el método de pago de un pedido pendiente (estado 3) SIN tocar la
     * ventana de reserva: al elegir el método en el paso de pago, la reserva ya
     * se creó con sus minutos y el contador no debe reiniciarse. Devuelve la
     * fecha de expiración vigente para que el frontend mantenga su contador.
     */
    public function actualizarMetodoPago(int $id_pedido, string $metodo): array {
        try {
            $metodo = in_array($metodo, ['tarjeta', 'qr', 'billetera', 'transferencia'], true) ? $metodo : 'tarjeta';
            $upd = $this->conexion->prepare(
                "UPDATE pedidos_online SET metodo_pago_online = ? WHERE id_pedido = ? AND estado = 3"
            );
            $upd->execute([$metodo, $id_pedido]);
            $fe = $this->conexion->prepare("SELECT fecha_expira FROM pedidos_online WHERE id_pedido = ?");
            $fe->execute([$id_pedido]);
            return ['ok' => true, 'fecha_expira' => $fe->fetchColumn()];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /** Pedidos del cliente autenticado, para "Mis Pedidos". */
    public function listarPedidosCliente(int $id_cliente_web): array {
        try {
            $sql = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.estado, p.tipo_entrega,
                           p.estudiante_nombre, p.observaciones, p.motivo_rechazo,
                           p.quien_recoge, p.recoge_dni, p.recoge_nombre,
                           p.fecha_preparado, p.fecha_entregado,
                           p.metodo_pago_online, p.medio_pago_usado, p.referencia_cliente, p.captura_pago,
                           p.codigo_confirmacion,
                           p.token_publico,
                           n.nombre AS nivel_nombre, g.nombre AS grado_nombre
                    FROM pedidos_online p
                    LEFT JOIN niveles_educativos n ON p.id_nivel = n.id_nivel
                    LEFT JOIN grados g ON p.id_grado = g.id_grado
                    WHERE p.id_cliente_web = ?
                    ORDER BY p.fecha_pedido DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_cliente_web]);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // El código de confirmación solo se expone al cliente cuando el switch
            // de la tienda está activo (aunque el pedido lo tenga guardado).
            $codigoVisible = configuracion('CODIGO_CONFIRMACION_HABILITADO', 'SI') === 'SI';
            if (!$codigoVisible) {
                foreach ($pedidos as &$pedido) {
                    $pedido['codigo_confirmacion'] = null;
                }
                unset($pedido);
            }

            foreach ($pedidos as &$pedido) {
                $pedido['detalles'] = $this->getDetallesPedido($pedido['id_pedido']);
            }
            return $pedidos;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Único camino para promover un pedido de estado 3 (pendiente de pago) a 1 (pagado).
     * Idempotente: si ya estaba confirmado, no vuelve a mutar nada.
     *
     * NUNCA se fía de quien llama: aunque el retorno del navegador y la IPN vengan con
     * firma válida, aquí se reconsulta a Izipay para comprobar que existe realmente una
     * transacción PAID: la palabra del navegador nunca basta para mover dinero.
     *
     * @param string $orderId Si viene, debe coincidir con la referencia del pedido.
     *        Sirve para que la IPN no pueda confirmar un pedido distinto al notificado.
     * @return array 'indeterminado' => true cuando la pasarela no respondió: quien llama
     *         debe reintentar más tarde, no dar el pago por perdido.
     */
    public function confirmarPagoPedido(int $id_pedido, string $orderId = ''): array {
        require_once dirname(__DIR__) . '/models/M_Izipay.php';

        try {
            // 1. Lectura rápida SIN bloqueo: descarta los casos triviales antes de
            //    gastar una llamada de red.
            $stmt = $this->conexion->prepare("SELECT estado, referencia_pago FROM pedidos_online WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                return ['ok' => false, 'mensaje' => 'Pedido no encontrado.'];
            }
            if (in_array((int) $pedido['estado'], [1, 2, 5], true)) {
                return ['ok' => true, 'ya_confirmado' => true];
            }
            if ((int) $pedido['estado'] !== 3) {
                return ['ok' => false, 'mensaje' => 'El pedido no está pendiente de pago.'];
            }

            $referencia = (string) ($pedido['referencia_pago'] ?: self::referenciaPago($id_pedido));
            if ($orderId !== '' && !hash_equals($referencia, $orderId)) {
                return ['ok' => false, 'mensaje' => 'El pago no corresponde a este pedido.'];
            }

            // 2. Verificación contra Izipay FUERA de la transacción: es una llamada HTTP
            //    de hasta 20s y no se puede retener un lock de fila durante ese tiempo.
            $verif = M_Izipay::singleton()->ordenEstaPagada($referencia);
            if (!$verif['ok']) {
                return ['ok' => false, 'indeterminado' => true, 'mensaje' => 'No pudimos verificar el pago con la pasarela.'];
            }
            if (!$verif['pagada']) {
                return ['ok' => false, 'mensaje' => 'El pago aún no está confirmado.'];
            }
            $uuid = $verif['transaccion']['uuid'] ?? null;

            // 3. Transacción corta para mutar. Se revalida el estado bajo bloqueo porque
            //    entre el paso 1 y aquí la IPN pudo haber confirmado el mismo pedido.
            $this->conexion->beginTransaction();

            $stmtLock = $this->conexion->prepare("SELECT estado FROM pedidos_online WHERE id_pedido = ? FOR UPDATE");
            $stmtLock->execute([$id_pedido]);
            $estadoActual = (int) $stmtLock->fetchColumn();

            if (in_array($estadoActual, [1, 2, 5], true)) {
                $this->conexion->commit();
                return ['ok' => true, 'ya_confirmado' => true];
            }
            if ($estadoActual !== 3) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'El pedido no está pendiente de pago.'];
            }

            $upd = $this->conexion->prepare(
                "UPDATE pedidos_online SET estado = 1, fecha_pago = NOW(), transaccion_uuid = ?
                 WHERE id_pedido = ? AND estado = 3"
            );
            $upd->execute([$uuid, $id_pedido]);

            $this->conexion->commit();

            // Correo de confirmación — best-effort, fuera de la transacción; nunca debe
            // hacer que la confirmación del pago falle si Brevo no responde.
            $this->enviarCorreoConfirmacion($id_pedido);

            return ['ok' => true, 'ya_confirmado' => false, 'uuid' => $uuid];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Promueve un pedido de estado 3 (pendiente de pago) a 1 (pagado) tras un webhook
     * de TAYPI. Idempotente: si ya estaba confirmado, no vuelve a mutar nada.
     *
     * A diferencia de confirmarPagoPedido() (Izipay), aquí NO se reconsulta a la
     * pasarela por HTTP: la firma HMAC del webhook ya se validó en C_TaypiWebhook.php
     * y el body viene firmado por TAYPI. Lo que sí se revalida es el MONTO contra la
     * BD, para no confirmar un pago de importe distinto al del pedido.
     *
     * @param string $paymentId ID del pago en TAYPI (se guarda como transaccion_uuid).
     * @param string $montoWebhook Monto que TAYPI reporta como pagado ("50.00").
     */
    /**
     * Guarda el payment_id que TAYPI asigna al crear un pago. Sirve para poder
     * verificar_pago (confirmar sin esperar el webhook) y para correlacionar.
     */
    public function guardarPaymentIdTaypi(int $id_pedido, string $paymentId): bool {
        try {
            $stmt = $this->conexion->prepare(
                "UPDATE pedidos_online SET payment_id_taypi = ? WHERE id_pedido = ?"
            );
            return $stmt->execute([$paymentId, $id_pedido]);
        } catch (Exception $e) {
            error_log('TAYPI guardarPaymentIdTaypi pedido ' . $id_pedido . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancelación por abandono del checkout (el cliente cierra la pestaña/ventana
     * mientras su pedido está pendiente de pago).
     *
     * Red de seguridad ANTES de liberar stock: consulta la pasarela que se usó. Si
     * el cobro realmente se completó (el cliente cerró justo después de pagar), se
     * confirma el pedido en vez de liberarlo. Si la pasarela no responde, NO se
     * libera nada (se deja que la reserva siga y expire sola con barrerPedidosVencidos).
     *
     * @return array{ok:bool, accion:'confirmado'|'liberado'|'indeterminado', mensaje:string}
     */
    public function cancelarPedidoPendiente(int $id_pedido, string $token): array {
        $pedido = $this->getPedidoPublico($id_pedido, $token);
        if (!$pedido) {
            return ['ok' => false, 'accion' => '', 'mensaje' => 'Pedido no encontrado.'];
        }

        $estado = (int) $pedido['estado'];
        // Ya confirmado o en progreso: nada que liberar.
        if (in_array($estado, [1, 2, 5], true)) {
            return ['ok' => true, 'accion' => 'confirmado', 'mensaje' => 'El pedido ya está pagado.'];
        }
        // Rechazado/expirado: el stock ya se liberó antes.
        if ($estado !== 3) {
            return ['ok' => true, 'accion' => 'liberado', 'mensaje' => 'El pedido ya no está pendiente.'];
        }

        $id = (int) $pedido['id_pedido'];

        // Pago manual (billetera/transferencia): no hay pasarela que consultar. Si el
        // cliente abandona el checkout sin reportar su pago, se libera la reserva
        // directo (estado 3). Un reporte ya enviado (estado 6) nunca se cancela aquí.
        if (in_array((string) ($pedido['metodo_pago_online'] ?? ''), ['billetera', 'transferencia'], true)) {
            $this->expirarPedido($id);
            return ['ok' => true, 'accion' => 'liberado', 'mensaje' => 'Pedido cancelado y stock liberado.'];
        }

        // ¿El pago fue por TAYPI (QR)? Verificar contra su API.
        $paymentIdTaypi = (string) ($pedido['payment_id_taypi'] ?? '');
        if ($paymentIdTaypi !== '') {
            require_once dirname(__DIR__) . '/models/M_Taypi.php';
            $v = M_Taypi::singleton()->verificarPago($paymentIdTaypi);
            if (!$v['ok']) {
                return ['ok' => false, 'accion' => 'indeterminado', 'mensaje' => 'No pudimos verificar el pago con la pasarela.'];
            }
            if ($v['pagado']) {
                $this->confirmarPagoTaypi($id, $paymentIdTaypi, (string) ($v['data']['amount'] ?? 0));
                return ['ok' => true, 'accion' => 'confirmado', 'mensaje' => 'El pago ya se había completado.'];
            }
            $this->expirarPedido($id);
            return ['ok' => true, 'accion' => 'liberado', 'mensaje' => 'Pedido cancelado y stock liberado.'];
        }

        // Flujo Izipay (tarjeta): consultar la orden por su referencia.
        require_once dirname(__DIR__) . '/models/M_Izipay.php';
        $referencia = (string) ($pedido['referencia_pago'] ?: self::referenciaPago($id));
        $verif = M_Izipay::singleton()->ordenEstaPagada($referencia);
        if (!$verif['ok']) {
            return ['ok' => false, 'accion' => 'indeterminado', 'mensaje' => 'No pudimos verificar el pago con la pasarela.'];
        }
        if ($verif['pagada']) {
            $this->confirmarPagoPedido($id, $referencia);
            return ['ok' => true, 'accion' => 'confirmado', 'mensaje' => 'El pago ya se había completado.'];
        }
        $this->expirarPedido($id);
        return ['ok' => true, 'accion' => 'liberado', 'mensaje' => 'Pedido cancelado y stock liberado.'];
    }

    public function confirmarPagoTaypi(int $id_pedido, string $paymentId, string $montoWebhook): array {        try {
            // 1. Lectura rápida SIN bloqueo.
            $stmt = $this->conexion->prepare("SELECT estado, total FROM pedidos_online WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                return ['ok' => false, 'mensaje' => 'Pedido no encontrado.'];
            }
            if (in_array((int) $pedido['estado'], [1, 2, 5], true)) {
                return ['ok' => true, 'ya_confirmado' => true];
            }
            if ((int) $pedido['estado'] !== 3) {
                return ['ok' => false, 'mensaje' => 'El pedido no está pendiente de pago.'];
            }

            // 2. Monto: el webhook ya viene firmado por TAYPI, pero por defensa en
            //    profundidad se exige que coincida con el total del pedido.
            $totalBD = round((float) $pedido['total'], 2);
            $cobrado = round((float) $montoWebhook, 2);
            if (abs($totalBD - $cobrado) > 0.01) {
                error_log('TAYPI webhook: monto no coincide para pedido ' . $id_pedido . ' (BD ' . $totalBD . ' vs webhook ' . $cobrado . ')');
                return ['ok' => false, 'mensaje' => 'El monto del pago no coincide con el pedido.'];
            }

            // 3. Transacción corta para mutar, revalidando bajo bloqueo.
            $this->conexion->beginTransaction();

            $stmtLock = $this->conexion->prepare("SELECT estado FROM pedidos_online WHERE id_pedido = ? FOR UPDATE");
            $stmtLock->execute([$id_pedido]);
            $estadoActual = (int) $stmtLock->fetchColumn();

            if (in_array($estadoActual, [1, 2, 5], true)) {
                $this->conexion->commit();
                return ['ok' => true, 'ya_confirmado' => true];
            }
            if ($estadoActual !== 3) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'El pedido no está pendiente de pago.'];
            }

            $upd = $this->conexion->prepare(
                "UPDATE pedidos_online SET estado = 1, fecha_pago = NOW(), transaccion_uuid = ?
                 WHERE id_pedido = ? AND estado = 3"
            );
            $upd->execute([$paymentId, $id_pedido]);

            $this->conexion->commit();

            $this->enviarCorreoConfirmacion($id_pedido);

            return ['ok' => true, 'ya_confirmado' => false, 'uuid' => $paymentId];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /** Medios de billetera que el cliente puede reportar al pagar con QR manual. */
    public const MEDIOS_BILLETERA = ['yape', 'plin', 'izipay_qr'];

    /** Bancos que el cliente puede reportar al pagar por transferencia. */
    public const MEDIOS_TRANSFERENCIA = ['bcp', 'bbva', 'interbank', 'scotiabank'];

    /**
     * El cliente reporta un pago manual (billetera o transferencia) de un pedido
     * en estado 3 (pendiente). Valida el token, que el pedido siga vigente, el
     * medio reportado y que el número de operación NO se repita.
     *
     * Al aprobarse el reporte el pedido pasa a estado 6 ("Pago enviado — en
     * verificación manual") y deja de expirar: el stock queda reservado hasta
     * que el admin apruebe o rechace desde Pedidos Online.
     *
     * @param array $datos ['medio_pago_usado'=>string, 'referencia'=>string, 'captura_pago'=>?string]
     */
    public function reportarPagoManual(int $id_pedido, string $token, array $datos): array {
        $pedido = $this->getPedidoPublico($id_pedido, $token);
        if (!$pedido) {
            return ['ok' => false, 'mensaje' => 'Pedido no encontrado.'];
        }
        if ((int) $pedido['estado'] !== 3) {
            return ['ok' => false, 'mensaje' => 'Este pedido ya no está pendiente de pago.'];
        }

        $metodo = (string) ($pedido['metodo_pago_online'] ?? '');
        $medio  = strtolower(trim((string) ($datos['medio_pago_usado'] ?? '')));
        if ($metodo === 'billetera' && !in_array($medio, self::MEDIOS_BILLETERA, true)) {
            return ['ok' => false, 'mensaje' => 'Indica con qué app pagaste (Yape, Plin o Izipay QR).'];
        }
        if ($metodo === 'transferencia' && !in_array($medio, self::MEDIOS_TRANSFERENCIA, true)) {
            return ['ok' => false, 'mensaje' => 'Indica el banco al que transferiste.'];
        }
        if (!in_array($metodo, ['billetera', 'transferencia'], true)) {
            return ['ok' => false, 'mensaje' => 'Este pedido no usa pago por verificación manual.'];
        }

        $referencia = trim((string) ($datos['referencia'] ?? ''));
        if ($referencia === '' || mb_strlen($referencia) > 255) {
            return ['ok' => false, 'mensaje' => 'Ingresa el número de operación de tu pago.'];
        }

        try {
            // Unicidad del número de operación: no puede repetirse en ningún pedido.
            // La FK/índice único (uq_referencia_cliente) garantiza la unicidad incluso
            // ante dos reportes concurrentes; el chequeo previo da el mensaje amigable.
            $this->conexion->beginTransaction();
            $stmtDup = $this->conexion->prepare(
                "SELECT id_pedido FROM pedidos_online
                 WHERE referencia_cliente = ? AND id_pedido <> ? LIMIT 1 FOR UPDATE"
            );
            $stmtDup->execute([$referencia, $id_pedido]);
            if ($stmtDup->fetch()) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'Ese número de operación ya fue registrado para otro pedido. Verifícalo e inténtalo de nuevo.'];
            }

            $upd = $this->conexion->prepare(
                "UPDATE pedidos_online
                 SET estado = 6, fecha_expira = NULL,
                     medio_pago_usado = ?, referencia_cliente = ?, captura_pago = ?
                 WHERE id_pedido = ? AND estado = 3"
            );
            $upd->execute([$medio, $referencia, $datos['captura_pago'] ?? null, $id_pedido]);
            if ($upd->rowCount() === 0) {
                $this->conexion->rollBack();
                return ['ok' => false, 'mensaje' => 'El pedido venció y se liberó el stock. Vuelve a intentar el pago desde tu carrito.'];
            }
            $this->conexion->commit();
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => 'No pudimos registrar tu pago. Intenta de nuevo.'];
        }

        return ['ok' => true, 'mensaje' => 'Recibimos tu pago. El vendedor lo verificará y te avisaremos por correo.'];
    }

    private function enviarCorreoConfirmacion(int $id_pedido): void {
        try {
            require_once dirname(__DIR__) . '/models/M_Mailer.php';
            $pedido = $this->obtenerDatosCorreoPedido($id_pedido);
            if (!$pedido) return;
            M_Mailer::singleton()->enviarConfirmacionCompra($pedido['email'], $pedido['nombres_razon_social'], $pedido);
        } catch (Exception $e) {
            // Silencioso a propósito: un fallo de correo no debe afectar el flujo de pago.
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
            $stmtRestore = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ? AND stock_ilimitado = 0");
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
                "SELECT id_pedido, referencia_pago, metodo_pago_online FROM pedidos_online
                 WHERE estado = 3 AND fecha_expira < NOW()
                 ORDER BY fecha_expira ASC LIMIT " . intval($limite)
            );
            $stmt->execute();
            $vencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return;
        }
        if (empty($vencidos)) return;

        require_once dirname(__DIR__) . '/models/M_Izipay.php';
        $izipay = M_Izipay::singleton();

        foreach ($vencidos as $v) {
            $id_pedido  = (int) $v['id_pedido'];
            $referencia = (string) ($v['referencia_pago'] ?: self::referenciaPago($id_pedido));

            // Pago manual: no hay pasarela que consultar; la reserva venció sin que el
            // cliente reportara su pago → se libera el stock directo.
            if (in_array((string) ($v['metodo_pago_online'] ?? ''), ['billetera', 'transferencia'], true)) {
                $this->expirarPedido($id_pedido);
                continue;
            }

            $r = $izipay->ordenEstaPagada($referencia);

            if (!$r['ok']) {
                // Izipay no respondió: sin certeza no se libera el stock ni se da el
                // pago por perdido. Se prorroga 15 min y se reintenta en el próximo barrido.
                $upd = $this->conexion->prepare("UPDATE pedidos_online SET fecha_expira = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id_pedido = ? AND estado = 3");
                $upd->execute([$id_pedido]);
                continue;
            }

            if ($r['pagada']) {
                // El cobro sí se completó (típicamente el cliente cerró el navegador
                // antes de volver a la tienda): se rescata el pedido.
                $this->confirmarPagoPedido($id_pedido, $referencia);
            } else {
                // Respuesta definitiva de "no pagado" — incluye el caso de que nunca se
                // intentara el pago (PSP_010). Se libera el stock.
                $this->expirarPedido($id_pedido);
            }
        }
    }

    /**
     * ¿El pedido sigue siendo cobrable (estado 3 y dentro de su ventana de reserva)?
     *
     * La comparación de fechas se hace ENTERAMENTE en SQL a propósito. PHP y MySQL
     * pueden estar en zonas horarias distintas — en este entorno difieren 5 horas
     * (MySQL en SYSTEM/hora local, PHP en UTC) — y comparar un DATETIME devuelto por
     * MySQL contra `time()` de PHP daba "expirado" para pedidos recién creados.
     * Regla general del proyecto: las fechas de BD se comparan contra NOW(), nunca
     * contra el reloj de PHP.
     *
     * @return string 'ok' | 'no_encontrado' | 'no_pendiente' | 'expirado'
     */
    public function estadoCobrabilidad(int $id_pedido): string {
        try {
            $stmt = $this->conexion->prepare(
                "SELECT estado, (fecha_expira IS NOT NULL AND fecha_expira < NOW()) AS expirado
                 FROM pedidos_online WHERE id_pedido = ?"
            );
            $stmt->execute([$id_pedido]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row)                          return 'no_encontrado';
            if ((int) $row['estado'] !== 3)     return 'no_pendiente';
            if ((int) $row['expirado'] === 1)   return 'expirado';
            return 'ok';
        } catch (PDOException $e) {
            return 'no_encontrado';
        }
    }

    /**
     * Token público de un pedido, para que la página de retorno pueda mostrar la boleta
     * después de validar la firma del pago. No se expone por ningún endpoint: solo lo
     * usa código de servidor que ya verificó la autenticidad de la respuesta de Izipay.
     */
    public function tokenPublicoDe(int $id_pedido): string {
        try {
            $stmt = $this->conexion->prepare("SELECT token_publico FROM pedidos_online WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            return (string) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return '';
        }
    }

    /** Localiza un pedido a partir del orderId que devuelve Izipay. La usa la IPN. */
    public function buscarPorReferencia(string $referencia): ?array {
        try {
            $stmt = $this->conexion->prepare("SELECT id_pedido FROM pedidos_online WHERE referencia_pago = ?");
            $stmt->execute([$referencia]);
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
                           p.fecha_expira, p.referencia_pago, p.payment_id_taypi,
                           p.metodo_pago_online, p.medio_pago_usado, p.referencia_cliente, p.captura_pago,
                           p.tipo_entrega, p.estudiante_nombre, p.observaciones, p.motivo_rechazo,
                           p.quien_recoge, p.recoge_dni, p.recoge_nombre,
                           p.codigo_confirmacion,
                           n.nombre AS nivel_nombre, g.nombre AS grado_nombre,
                           cw.numero_documento, cw.nombres_razon_social, cw.apellidos, cw.telefono
                    FROM pedidos_online p
                    INNER JOIN clientes_web cw ON p.id_cliente_web = cw.id_cliente_web
                    LEFT JOIN niveles_educativos n ON p.id_nivel = n.id_nivel
                    LEFT JOIN grados g ON p.id_grado = g.id_grado
                    WHERE p.id_pedido = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_pedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido || !hash_equals((string) $pedido['token_publico'], (string) $token)) {
                return null;
            }
            unset($pedido['token_publico']);

            // El código de confirmación solo se muestra cuando el switch de la
            // tienda está activo (aunque el pedido lo tenga guardado).
            if (configuracion('CODIGO_CONFIRMACION_HABILITADO', 'SI') !== 'SI') {
                $pedido['codigo_confirmacion'] = null;
            }

            $pedido['detalles'] = $this->getDetallesPedido($id_pedido);
            return $pedido;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * @param bool $incluirNoCompletados si es false (por defecto), oculta los intentos de pago
     *        que nunca se concretaron (estado 3=pendiente de pago, 4=expirado) para que la cola
     *        de trabajo diaria del panel solo muestre pedidos reales.
     */
    public function listarPedidos(bool $incluirNoCompletados = false) {
        $this->barrerPedidosVencidos();
        try {
            $sql = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.nro_operacion_yape,
                           p.referencia_pago, p.transaccion_uuid,
                           p.metodo_pago_online, p.medio_pago_usado, p.referencia_cliente, p.captura_pago,
                           p.fecha_pago, p.id_verificado_por, p.fecha_verificacion,
                           p.fecha_preparado, p.fecha_entregado, p.motivo_rechazo, p.estado,
                           p.tipo_entrega, p.estudiante_nombre, p.observaciones,
                           p.quien_recoge, p.recoge_dni, p.recoge_nombre,
                           p.tipo_comprobante, p.codigo_confirmacion,
                           perFact.numero_documento AS ruc_facturacion, perFact.nombres_razon_social AS razon_social_facturacion,
                           n.nombre AS nivel_nombre, g.nombre AS grado_nombre,
                           cw.numero_documento, cw.nombres_razon_social, cw.apellidos, cw.telefono,
                           v.id_venta
                    FROM pedidos_online p
                    INNER JOIN clientes_web cw ON p.id_cliente_web = cw.id_cliente_web
                    LEFT JOIN clientes cFact ON p.id_cliente_facturacion = cFact.id_cliente
                    LEFT JOIN personas perFact ON cFact.id_persona = perFact.id_persona
                    LEFT JOIN ventas v ON p.id_venta = v.id_venta
                    LEFT JOIN niveles_educativos n ON p.id_nivel = n.id_nivel
                    LEFT JOIN grados g ON p.id_grado = g.id_grado";
            if (!$incluirNoCompletados) {
                $sql .= " WHERE p.estado NOT IN (3, 4)";
            }
            $sql .= " ORDER BY p.fecha_pedido DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $pedidos = $stmt->fetchAll();

            // El panel indica si el pedido exige el código de confirmación en la
            // entrega (switch activo + pedido con código), SIN exponer el código
            // al cajero: el código lo dicta el cliente y lo verifica el servidor.
            $codigoActivo = configuracion('CODIGO_CONFIRMACION_HABILITADO', 'SI') === 'SI';
            foreach ($pedidos as &$pedido) {
                $pedido['requiere_codigo'] = ($codigoActivo && !empty($pedido['codigo_confirmacion'])) ? 1 : 0;
                unset($pedido['codigo_confirmacion']);
            }
            unset($pedido);
            return $pedidos;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getDetallesPedido($id_pedido) {
        try {
            // Mismo criterio que M_Venta::obtenerDetallesPorVenta(): los productos con
            // talla comparten el nombre del padre en su propia columna `nombre` (así se
            // crean en M_Producto::registrarConVariantes()), así que sin el CONCAT dos
            // tallas distintas del mismo pedido aparecerían con la misma línea "Polo".
            $sql = "SELECT d.cantidad, d.precio_unitario, d.subtotal,
                           CASE
                               WHEN i.id_talla IS NOT NULL AND t.nombre IS NOT NULL
                               THEN CONCAT(i.nombre, ' - T.', t.nombre)
                               ELSE i.nombre
                           END AS nombre,
                           um.abreviatura
                    FROM detalle_pedidos_online d
                    INNER JOIN productos i ON d.id_producto = i.id_producto
                    INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                    LEFT JOIN tallas t ON i.id_talla = t.id_talla
                    WHERE d.id_pedido = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_pedido]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Máquina de estados de un pedido online, ya pagado:
     *   preparar : 1        → 5 (sella fecha_preparado, no toca stock/dinero/correo)
     *   entregar : 1 ó 5    → 2 (sella fecha_entregado, genera la venta SIN caja física, correo de entrega)
     *   rechazar : 1 ó 5    → 0 (exige motivo, devuelve stock, correo de rechazo con el motivo)
     */
    public function gestionarPedido($id_pedido, $accion, $id_usuario_cajero, ?string $motivoRechazo = null, ?string $codigoConfirmacion = null, bool $saltarCodigo = false) {
        try {
            $this->conexion->beginTransaction();

            $estadosOrigen = match ($accion) {
                'preparar' => [1],
                'aprobar'  => [6],
                'entregar' => [1, 5],
                'rechazar' => [1, 5, 6],
                default => [],
            };
            if (empty($estadosOrigen)) {
                throw new Exception("Acción inválida.");
            }

            $placeholders = implode(',', array_fill(0, count($estadosOrigen), '?'));
            $stmtCheck = $this->conexion->prepare("SELECT * FROM pedidos_online WHERE id_pedido = ? AND estado IN ($placeholders) FOR UPDATE");
            $stmtCheck->execute(array_merge([$id_pedido], $estadosOrigen));
            $pedido = $stmtCheck->fetch();

            if (!$pedido) {
                throw new Exception("El pedido no existe o no está en un estado válido para esta acción.");
            }

            if ($accion === 'preparar') {
                $stmtUpdate = $this->conexion->prepare("UPDATE pedidos_online SET estado = 5, fecha_preparado = NOW() WHERE id_pedido = ?");
                $stmtUpdate->execute([$id_pedido]);

                $this->conexion->commit();
                return ['ok' => true, 'mensaje' => 'Pedido marcado como preparado.'];
            }

            // Aprobar un pago por verificación manual (estado 6 → 1): el admin verificó
            // el comprobante y confirma el cobro. El correo de confirmación avisa al cliente.
            if ($accion === 'aprobar') {
                $stmtUpdate = $this->conexion->prepare(
                    "UPDATE pedidos_online
                     SET estado = 1, fecha_pago = NOW(), id_verificado_por = ?, fecha_verificacion = NOW()
                     WHERE id_pedido = ?"
                );
                $stmtUpdate->execute([$id_usuario_cajero, $id_pedido]);

                $this->conexion->commit();
                $this->enviarCorreoConfirmacion($id_pedido);
                return ['ok' => true, 'mensaje' => 'Pago aprobado. El pedido pasó a "Pagado — en proceso".'];
            }

            if ($accion === 'rechazar') {
                $motivoRechazo = trim((string) $motivoRechazo);
                if ($motivoRechazo === '') {
                    throw new Exception("Debes indicar un motivo de rechazo.");
                }

            $stmtRestore = $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ? AND stock_ilimitado = 0");
                $stmtDetalleIds = $this->conexion->prepare("SELECT id_producto, cantidad FROM detalle_pedidos_online WHERE id_pedido = ?");
                $stmtDetalleIds->execute([$id_pedido]);
                foreach ($stmtDetalleIds->fetchAll() as $dr) {
                    $stmtRestore->execute([$dr['cantidad'], $dr['id_producto']]);
                }

                $stmtUpdate = $this->conexion->prepare("UPDATE pedidos_online SET estado = 0, motivo_rechazo = ? WHERE id_pedido = ?");
                $stmtUpdate->execute([$motivoRechazo, $id_pedido]);

                $this->conexion->commit();
                $this->enviarCorreoRechazo($id_pedido, $motivoRechazo);
                return ['ok' => true, 'mensaje' => 'Pedido rechazado y stock restaurado.'];
            }

            if ($accion === 'entregar') {
                // Verificación del código de confirmación (configurable en la tienda):
                // si el switch está activo y el pedido tiene código, se exige que el
                // código que dicta el cliente coincida. El salto manual es la última
                // opción (cliente que perdió el correo), nunca el camino normal.
                if (
                    configuracion('CODIGO_CONFIRMACION_HABILITADO', 'SI') === 'SI'
                    && !empty($pedido['codigo_confirmacion'])
                ) {
                    if (!$saltarCodigo && !hash_equals((string) $pedido['codigo_confirmacion'], (string) $codigoConfirmacion)) {
                        throw new Exception('El código de confirmación no coincide. Verifica con el cliente o usa "Saltar verificación" solo como última opción.');
                    }
                }

                // Método de pago = 3 (Tarjeta), pagado vía pasarela. Sin id_caja: no hay dinero
                // físico involucrado, por eso nunca debe sumarse al arqueo de quien despacha.
                //
                // El cliente de la venta depende del tipo de comprobante: con Factura, se factura
                // a la entidad con RUC resuelta en el checkout (id_cliente_facturacion), no a la
                // cuenta logueada — así el comprobante impreso trae el RUC/razón social correctos.
                // Con Boleta, la cuenta web (clientes_web) se materializa como cliente POS
                // (personas+clientes) en el momento de la entrega: se reutiliza si el DNI ya
                // existe, si no se crea con los datos web — NUNCA se sobrescriben datos del POS.
                $tipoComprobante = (int) $pedido['tipo_comprobante'];
                $idClienteVenta = ($tipoComprobante === 2 && $pedido['id_cliente_facturacion'])
                    ? (int) $pedido['id_cliente_facturacion']
                    : $this->resolverClientePosEntrega((int) $pedido['id_cliente_web']);

                // Método de pago según cómo se cobró el pedido: la billetera manual
                // (Yape/Plin/Izipay QR) se registra como Yape/Plin (2), la transferencia
                // bancaria como 5 (Transferencia) y las pasarelas como Tarjeta (3).
                $metodoVenta = match ((string) ($pedido['metodo_pago_online'] ?? '')) {
                    'billetera'     => 2,
                    'transferencia' => 5,
                    default         => 3,
                };

                // Desglose fiscal: se deriva de total (aquí total SÍ es el valor íntegro de
                // la mercadería, a diferencia del anticipo de una separación), igual criterio
                // que M_Venta::registrarEnTransaccion() — SUNAT valida al céntimo.
                $ventaSubtotal = round((float) $pedido['total'] / 1.18, 2);
                $ventaIgv = round((float) $pedido['total'] - $ventaSubtotal, 2);

                $stmtInsertVenta = $this->conexion->prepare(
                    "INSERT INTO ventas (id_usuario, id_caja, id_cliente, tipo_comprobante, total, subtotal, igv, metodo_pago, estado, origen)
                     VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 1, 2)"
                );
                $stmtInsertVenta->execute([$id_usuario_cajero, $idClienteVenta, $tipoComprobante, $pedido['total'], $ventaSubtotal, $ventaIgv, $metodoVenta]);
                $id_venta = $this->conexion->lastInsertId();

                $stmtDetallesPedido = $this->conexion->prepare("SELECT id_producto, cantidad, precio_unitario, subtotal FROM detalle_pedidos_online WHERE id_pedido = ?");
                $stmtDetallesPedido->execute([$id_pedido]);
                $detalles = $stmtDetallesPedido->fetchAll();

                // Comisión: se atribuye a quien despacha (id_usuario_cajero), congelada al momento
                // de la entrega igual que el costo — un pedido online no tiene vendedor propio.
                $stmtInsertDetalleVenta = $this->conexion->prepare("INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_venta, costo_unitario, comision_unitaria, valor_unitario, igv_linea, subtotal) VALUES (?, ?, ?, ?, (SELECT costo_produccion FROM productos WHERE id_producto = ?), (SELECT comision FROM productos WHERE id_producto = ?), ?, ?, ?)");

                foreach ($detalles as $d) {
                    $precioLinea = (float) $d['precio_unitario'];
                    $subtotalLinea = (float) $d['subtotal'];
                    $valorUnitarioLinea = round($precioLinea / 1.18, 4);
                    $igvLinea = round($subtotalLinea - round($subtotalLinea / 1.18, 2), 2);
                    $stmtInsertDetalleVenta->execute([
                        $id_venta, $d['id_producto'], $d['cantidad'], $precioLinea, $d['id_producto'], $d['id_producto'],
                        $valorUnitarioLinea, $igvLinea, $subtotalLinea
                    ]);
                }

                $stmtUpdate = $this->conexion->prepare("UPDATE pedidos_online SET estado = 2, fecha_entregado = NOW(), id_venta = ? WHERE id_pedido = ?");
                $stmtUpdate->execute([$id_venta, $id_pedido]);

                $this->conexion->commit();
                $this->enviarCorreoEntregado($id_pedido);
                return ['ok' => true, 'mensaje' => 'Pedido entregado y venta generada.', 'id_venta' => $id_venta];
            }

            throw new Exception("Acción inválida.");
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /** Best-effort, fuera de transacción: un fallo de Brevo nunca revierte la entrega. */
    private function enviarCorreoEntregado(int $id_pedido): void {
        try {
            require_once dirname(__DIR__) . '/models/M_Mailer.php';
            $pedido = $this->obtenerDatosCorreoPedido($id_pedido);
            if (!$pedido) return;
            M_Mailer::singleton()->enviarPedidoEntregado($pedido['email'], $pedido['nombres_razon_social'], $pedido);
        } catch (Exception $e) {
            // Silencioso a propósito.
        }
    }

    /** Best-effort, fuera de transacción: un fallo de Brevo nunca revierte el rechazo. */
    private function enviarCorreoRechazo(int $id_pedido, string $motivo): void {
        try {
            require_once dirname(__DIR__) . '/models/M_Mailer.php';
            $pedido = $this->obtenerDatosCorreoPedido($id_pedido);
            if (!$pedido) return;
            M_Mailer::singleton()->enviarPedidoRechazado($pedido['email'], $pedido['nombres_razon_social'], $pedido, $motivo);
        } catch (Exception $e) {
            // Silencioso a propósito.
        }
    }

    private function obtenerDatosCorreoPedido(int $id_pedido): ?array {
        $sql = "SELECT p.id_pedido, p.total, p.tipo_entrega, p.estudiante_nombre,
                       p.quien_recoge, p.recoge_dni, p.recoge_nombre,
                       p.codigo_confirmacion,
                       cw.nombres_razon_social, cw.email
                FROM pedidos_online p
                INNER JOIN clientes_web cw ON p.id_cliente_web = cw.id_cliente_web
                WHERE p.id_pedido = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$id_pedido]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedido || empty($pedido['email'])) return null;

        $pedido['detalles'] = $this->getDetallesPedido($id_pedido);
        return $pedido;
    }

    /**
     * Materializa la cuenta web del comprador como cliente POS (personas + clientes)
     * al ENTREGAR un pedido con Boleta. Reglas (Parte A, sin API RENIEC):
     *   - Si el DNI ya existe en personas → se reutiliza TAL CUAL (nunca se sobrescriben
     *     nombres/apellidos del POS con los de la web).
     *   - Si no existe → se crea con los datos de la cuenta web.
     *   - Si la cuenta no tiene DNI (legado) → cae a "Público General" (id_cliente 1).
     *
     * @return int id_cliente (tabla clientes) a usar en ventas.id_cliente
     */
    private function resolverClientePosEntrega(int $id_cliente_web): int {
        try {
            $stmt = $this->conexion->prepare("SELECT numero_documento, nombres_razon_social, apellidos, telefono, direccion FROM clientes_web WHERE id_cliente_web = ?");
            $stmt->execute([$id_cliente_web]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cuenta || empty($cuenta['numero_documento'])) {
                return 1; // Público General
            }

            // Reutilizar si el DNI ya está en el POS (sin sobrescribir nada).
            $stmtPos = $this->conexion->prepare(
                "SELECT c.id_cliente FROM clientes c
                 INNER JOIN personas p ON c.id_persona = p.id_persona
                 WHERE p.numero_documento = ? AND p.estado = 1 LIMIT 1"
            );
            $stmtPos->execute([$cuenta['numero_documento']]);
            $pos = $stmtPos->fetch(PDO::FETCH_ASSOC);
            if ($pos) {
                return (int) $pos['id_cliente'];
            }

            // Crear persona + cliente con los datos de la cuenta web.
            $stmtPer = $this->conexion->prepare(
                "INSERT INTO personas (tipo_documento, numero_documento, nombres_razon_social, apellidos, telefono, direccion, estado)
                 VALUES (1, ?, ?, ?, ?, ?, 1)"
            );
            $stmtPer->execute([$cuenta['numero_documento'], $cuenta['nombres_razon_social'] ?? '', $cuenta['apellidos'] ?? null, $cuenta['telefono'] ?? null, $cuenta['direccion'] ?? null]);
            $id_persona = (int) $this->conexion->lastInsertId();

            $stmtCli = $this->conexion->prepare("INSERT INTO clientes (id_persona, tipo_cliente) VALUES (?, 1)");
            $stmtCli->execute([$id_persona]);
            return (int) $this->conexion->lastInsertId();
        } catch (Exception $e) {
            return 1; // Público General si algo falla: la venta no puede quedar sin cliente.
        }
    }
}
?>
