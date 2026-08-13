<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/models/M_Kardex.php';

/**
 * Cambio de talla sobre una venta ya emitida, sin anularla ni editarla.
 *
 * La venta original queda intacta a propósito: `M_Caja::calcularVentasAcumuladas()`
 * recalcula el arqueo en vivo con `SUM(total) WHERE id_caja = ? AND estado = 1`, así
 * que tocar el total de una venta de ayer descuadraría un cierre de caja ya hecho.
 * En vez de eso, este modelo mueve stock hoy, deja rastro en kardex_movimientos, y si
 * hay diferencia de precio la registra como una venta nueva (origen=3) en la caja
 * abierta de HOY — reutilizando toda la maquinaria de arqueo/reportes sin tocarla.
 */
class M_CambioTalla {
    private static $instancia = null;
    private $conexion;

    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Resuelve la talla VIGENTE de una línea (la del último cambio ya aplicado sobre
     * ese detalle, o el producto original si nunca se cambió) y devuelve sus hermanas
     * con stock disponible, para ofrecer cambios encadenados (8 → 10 → 12) sin que un
     * segundo cambio parta de una talla que el cliente ya no tiene.
     */
    public function tallasDisponiblesPara(int $id_detalle): array {
        try {
            $stmt = $this->conexion->prepare(
                "SELECT dv.id_venta, dv.id_producto, dv.cantidad,
                        v.estado AS estado_venta, v.fecha AS fecha_venta
                 FROM detalle_ventas dv
                 INNER JOIN ventas v ON dv.id_venta = v.id_venta
                 WHERE dv.id_detalle = ?"
            );
            $stmt->execute([$id_detalle]);
            $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$detalle) {
                return ['ok' => false, 'mensaje' => 'Línea de venta no encontrada.'];
            }

            // Talla vigente: el producto_entrante del cambio más reciente sobre esta
            // línea, o el producto original de detalle_ventas si nunca se cambió.
            $stmtUltimo = $this->conexion->prepare(
                "SELECT id_producto_entrante FROM cambios_talla
                 WHERE id_detalle_original = ? ORDER BY fecha DESC, id_cambio DESC LIMIT 1"
            );
            $stmtUltimo->execute([$id_detalle]);
            $idProductoVigente = $stmtUltimo->fetchColumn() ?: $detalle['id_producto'];

            $stmtProd = $this->conexion->prepare(
                "SELECT id_producto, nombre, precio_unitario, stock_piezas, id_talla, id_producto_padre
                 FROM productos WHERE id_producto = ?"
            );
            $stmtProd->execute([$idProductoVigente]);
            $productoVigente = $stmtProd->fetch(PDO::FETCH_ASSOC);
            if (!$productoVigente) {
                return ['ok' => false, 'mensaje' => 'El producto de esta línea ya no existe.'];
            }
            if (empty($productoVigente['id_producto_padre'])) {
                return ['ok' => false, 'mensaje' => 'Este producto no tiene variantes de talla.'];
            }

            $stmtHermanas = $this->conexion->prepare(
                "SELECT i.id_producto, i.nombre, i.precio_unitario, i.stock_piezas,
                        i.id_talla, t.nombre AS talla, t.orden AS talla_orden
                 FROM productos i
                 LEFT JOIN tallas t ON i.id_talla = t.id_talla
                 WHERE i.id_producto_padre = ? AND i.estado = 1 AND i.id_producto != ?
                 ORDER BY t.orden ASC, t.nombre ASC"
            );
            $stmtHermanas->execute([$productoVigente['id_producto_padre'], $idProductoVigente]);
            $hermanas = $stmtHermanas->fetchAll(PDO::FETCH_ASSOC);

            $precioVigente = (float) $productoVigente['precio_unitario'];
            foreach ($hermanas as &$h) {
                $h['diferencia_unitaria'] = round((float) $h['precio_unitario'] - $precioVigente, 2);
            }
            unset($h);

            return [
                'ok'                => true,
                'id_venta'          => (int) $detalle['id_venta'],
                'estado_venta'      => (int) $detalle['estado_venta'],
                'fecha_venta'       => $detalle['fecha_venta'],
                'cantidad'          => (float) $detalle['cantidad'],
                'producto_vigente'  => $productoVigente,
                'hermanas'          => $hermanas,
            ];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al consultar tallas disponibles.'];
        }
    }

    /**
     * Registra el cambio de talla completo en una sola transacción: valida venta,
     * ventana temporal por rol, hermandad de producto y stock; mueve stock; deja
     * rastro en kardex; y si hay diferencia de precio, la cobra o devuelve como una
     * venta nueva (origen=3) en la caja abierta de hoy.
     *
     * @param int    $id_detalle           Línea de detalle_ventas a cambiar
     * @param int    $id_producto_entrante Talla que el cliente se lleva
     * @param int    $id_usuario           Quién registra el cambio ($_SESSION['id_usuario'])
     * @param string $rol                  $_SESSION['rol'] — determina la ventana temporal
     * @param int    $metodo_pago          Solo se usa si hay diferencia de precio
     * @param ?string $motivo
     */
    public function registrarCambio(
        int $id_detalle,
        int $id_producto_entrante,
        int $id_usuario,
        string $rol,
        int $metodo_pago = 1,
        ?string $motivo = null
    ): array {
        try {
            $this->conexion->beginTransaction();

            // 1. Resolver la talla vigente de esta línea (mismo criterio que
            //    tallasDisponiblesPara, pero bajo lock porque aquí sí mutamos).
            $stmt = $this->conexion->prepare(
                "SELECT dv.id_venta, dv.cantidad, v.estado AS estado_venta,
                        v.id_cliente, v.tipo_comprobante, v.id_usuario AS vendedor_original
                 FROM detalle_ventas dv
                 INNER JOIN ventas v ON dv.id_venta = v.id_venta
                 WHERE dv.id_detalle = ? FOR UPDATE"
            );
            $stmt->execute([$id_detalle]);
            $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$detalle) {
                throw new Exception('Línea de venta no encontrada.');
            }
            if ((int) $detalle['estado_venta'] !== 1) {
                throw new Exception('No se puede cambiar la talla de una venta anulada.');
            }

            // 1b. Pertenencia: un Vendedor solo toca sus propias ventas. Mismo criterio
            //     que C_Venta.php para anular y ver detalles — sin esto un vendedor podía
            //     mover stock y cobrar/devolver dinero sobre la venta de un compañero.
            if ($rol !== 'Administrador' && (int) $detalle['vendedor_original'] !== $id_usuario) {
                throw new Exception('No puedes cambiar la talla de una venta registrada por otro vendedor.');
            }

            // 2. Ventana temporal — SIEMPRE en SQL contra NOW(), nunca contra el reloj de
            //    PHP: en este entorno MySQL corre en hora local y PHP en UTC (5h de
            //    diferencia), y esa mezcla ya rompió el checkout una vez.
            $diasMax = ($rol === 'Administrador') ? 7 : 1;
            $stmtVentana = $this->conexion->prepare(
                "SELECT DATEDIFF(NOW(), fecha) AS dias FROM ventas WHERE id_venta = ?"
            );
            $stmtVentana->execute([$detalle['id_venta']]);
            $dias = (int) $stmtVentana->fetchColumn();
            if ($dias > $diasMax) {
                throw new Exception("Esta venta tiene más de {$diasMax} día(s) y ya no admite cambio de talla para tu rol.");
            }

            // 3. Talla vigente de esta línea (encadenamiento: si ya hubo un cambio antes,
            //    partimos de ahí, no del producto original de detalle_ventas).
            $stmtUltimo = $this->conexion->prepare(
                "SELECT id_producto_entrante FROM cambios_talla
                 WHERE id_detalle_original = ? ORDER BY fecha DESC, id_cambio DESC LIMIT 1 FOR UPDATE"
            );
            $stmtUltimo->execute([$id_detalle]);
            $idProductoSaliente = $stmtUltimo->fetchColumn();
            if (!$idProductoSaliente) {
                $stmtOriginal = $this->conexion->prepare("SELECT id_producto FROM detalle_ventas WHERE id_detalle = ?");
                $stmtOriginal->execute([$id_detalle]);
                $idProductoSaliente = $stmtOriginal->fetchColumn();
            }
            $idProductoSaliente = (int) $idProductoSaliente;

            if ($idProductoSaliente === $id_producto_entrante) {
                throw new Exception('Elige una talla distinta a la actual.');
            }

            // 4. Hermandad: mismo padre, ambos activos. Bloqueo pesimista sobre el
            //    entrante porque es al que hay que validarle stock (mismo patrón que
            //    M_Venta::registrar()).
            $stmtProds = $this->conexion->prepare(
                "SELECT id_producto, id_producto_padre, precio_unitario, stock_piezas, estado, comision
                 FROM productos WHERE id_producto IN (?, ?) FOR UPDATE"
            );
            $stmtProds->execute([$idProductoSaliente, $id_producto_entrante]);
            $productos = [];
            foreach ($stmtProds->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $productos[(int) $p['id_producto']] = $p;
            }

            $saliente = $productos[$idProductoSaliente] ?? null;
            $entrante = $productos[$id_producto_entrante] ?? null;
            if (!$saliente || !$entrante) {
                throw new Exception('Producto no encontrado.');
            }
            if ((int) $entrante['estado'] !== 1) {
                throw new Exception('La talla elegida no está disponible.');
            }
            if (empty($saliente['id_producto_padre']) || $saliente['id_producto_padre'] != $entrante['id_producto_padre']) {
                throw new Exception('Solo se puede cambiar por otra talla del mismo producto.');
            }

            $cantidad = (float) $detalle['cantidad'];
            if ((float) $entrante['stock_piezas'] < $cantidad) {
                throw new Exception('No hay stock suficiente de la talla elegida.');
            }

            // 5. Mover stock.
            $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ? AND stock_ilimitado = 0")
                 ->execute([$cantidad, $idProductoSaliente]);
            $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas - ? WHERE id_producto = ? AND stock_ilimitado = 0")
                 ->execute([$cantidad, $id_producto_entrante]);

            // 6. Kardex: dos movimientos explícitos, para que el histórico del producto
            //    no dependa de reescribir detalle_ventas (que nunca se toca).
            $referencia = 'V-' . str_pad((string) $detalle['id_venta'], 6, '0', STR_PAD_LEFT);
            $kardex = M_Kardex::singleton();
            $kardex->registrarMovimiento($idProductoSaliente, 'entrada', $cantidad, (float) $saliente['precio_unitario'], $referencia, 'Cambio de talla — devolución', $id_usuario);
            $kardex->registrarMovimiento($id_producto_entrante, 'salida', $cantidad, (float) $entrante['precio_unitario'], $referencia, 'Cambio de talla — entrega', $id_usuario);

            // 7. Diferencia de precio y de comisión (independientes: dos tallas del mismo
            //    modelo suelen costar igual pero pueden tener comisión distinta).
            $precioSaliente = (float) $saliente['precio_unitario'];
            $precioEntrante = (float) $entrante['precio_unitario'];
            $diferencia = round(($precioEntrante - $precioSaliente) * $cantidad, 2);
            $comisionDelta = round(((float) $entrante['comision'] - (float) $saliente['comision']) * $cantidad, 2);

            $idVentaDiferencia = null;
            if (abs($diferencia) > 0.001) {
                $cajaAbierta = $this->conexion->prepare("SELECT id_caja FROM cajas WHERE id_usuario = ? AND estado = 1 ORDER BY id_caja DESC LIMIT 1");
                $cajaAbierta->execute([$id_usuario]);
                $idCaja = $cajaAbierta->fetchColumn();
                if (!$idCaja) {
                    throw new Exception('Hay una diferencia de precio ' . ($diferencia > 0 ? 'a cobrar' : 'a devolver') . ' y no tienes una caja abierta.');
                }

                // Desglose fiscal derivado de total, igual que las otras 3 rutas de
                // INSERT a ventas (M_Venta, M_Ecommerce, M_Sunat). Sin esto la fila
                // quedaba con subtotal=0 e igv=0 y un total distinto de cero, rompiendo
                // el invariante subtotal + igv == total. $diferencia puede ser negativa
                // (devolución al cliente) y el desglose conserva ese signo.
                $subtotalDif = round($diferencia / 1.18, 2);
                $igvDif = round($diferencia - $subtotalDif, 2);

                $stmtVentaDif = $this->conexion->prepare(
                    "INSERT INTO ventas (id_usuario, id_caja, id_cliente, tipo_comprobante, total, subtotal, igv, metodo_pago, estado, origen)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 3)"
                );
                $stmtVentaDif->execute([
                    $id_usuario, $idCaja, $detalle['id_cliente'], $detalle['tipo_comprobante'], $diferencia, $subtotalDif, $igvDif, $metodo_pago,
                ]);
                $idVentaDiferencia = (int) $this->conexion->lastInsertId();

                // comision_unitaria = 0: esta línea es un movimiento de dinero (la diferencia
                // de precio), no una unidad vendida — el ajuste de comisión real vive en
                // cambios_talla.comision_delta, calculado más abajo, para que M_Comision no
                // cuente esta prenda dos veces (una aquí y otra en la venta original).
                $precioLineaDif = round($diferencia / $cantidad, 2);
                $stmtDetalleDif = $this->conexion->prepare(
                    "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_venta, costo_unitario, comision_unitaria, valor_unitario, igv_linea, subtotal)
                     VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?)"
                );
                $stmtDetalleDif->execute([
                    $idVentaDiferencia, $id_producto_entrante, $cantidad, $precioLineaDif,
                    round($precioLineaDif / 1.18, 4), $igvDif, $diferencia,
                ]);

                // Línea de pago para que M_Caja::calcularDesglosePorMetodo() cuente esta
                // diferencia dentro del balde correcto (efectivo/yape/tarjeta) en vez de
                // desaparecer del arqueo en vivo de la caja de hoy. El monto mantiene el
                // signo de $diferencia (negativo = devolución), igual que ventas.total.
                $this->conexion->prepare(
                    "INSERT INTO pagos_venta (id_venta, metodo_pago, monto) VALUES (?, ?, ?)"
                )->execute([$idVentaDiferencia, $metodo_pago, $diferencia]);
            }

            // 8. Trazabilidad.
            $stmtCambio = $this->conexion->prepare(
                "INSERT INTO cambios_talla
                    (id_venta_original, id_detalle_original, id_producto_saliente, id_producto_entrante,
                     cantidad, precio_saliente, precio_entrante, diferencia, comision_delta, id_venta_diferencia, id_usuario, motivo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmtCambio->execute([
                $detalle['id_venta'], $id_detalle, $idProductoSaliente, $id_producto_entrante,
                $cantidad, $precioSaliente, $precioEntrante, $diferencia, $comisionDelta, $idVentaDiferencia, $id_usuario, $motivo,
            ]);
            $idCambio = (int) $this->conexion->lastInsertId();

            $this->conexion->commit();
            return [
                'ok'                  => true,
                'id_cambio'           => $idCambio,
                'diferencia'          => $diferencia,
                'id_venta_diferencia' => $idVentaDiferencia,
                'mensaje'             => 'Cambio de talla registrado correctamente.',
            ];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }
}
