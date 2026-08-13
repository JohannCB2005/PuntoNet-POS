<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Venta.php';
require_once dirname(__DIR__) . '/entities/DetalleVenta.php';
require_once dirname(__DIR__) . '/models/M_Venta.php';
require_once dirname(__DIR__) . '/models/M_Kardex.php';

/**
 * Apartar mercadería con un anticipo (mínimo 50%) y cobrar el saldo en uno o
 * varios abonos posteriores, sin romper el arqueo de caja.
 *
 * Cada pago (el anticipo y cada abono, incluido el cobro final del despacho) es
 * su propia venta con origen=4, ligada a la caja abierta del día en que se pagó
 * — mismo patrón que la venta-diferencia origen=3 del cambio de talla. Así una
 * caja ya cerrada nunca cambia hacia atrás.
 *
 * La mercadería no tiene tabla propia: vive en el detalle_ventas de la venta del
 * anticipo (a precio completo), delegado en M_Venta::registrar() para heredar el
 * descuento de stock, el bloqueo FOR UPDATE y las líneas de pago_venta sin
 * reimplementar nada. El saldo nunca se guarda, siempre se calcula.
 */
class M_Separacion {
    const ANTICIPO_MINIMO = 0.50;
    const DIAS_VENCIMIENTO = 30;

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
     * Registra una separación nueva: valida el anticipo mínimo, registra la venta
     * del anticipo (mercadería completa vía M_Venta::registrar()) y crea la
     * cabecera de la separación ligada a esa venta.
     *
     * @param array $carrito [{id_producto, piezas, precio, subtotal}, ...] — la mercadería completa
     * @param array $pagos   [{metodo_pago, monto, referencia}, ...] — el anticipo, puede ser mixto
     */
    public function registrar(int $id_cliente, int $id_usuario, int $id_caja, array $carrito, array $pagos): array {
        try {
            if ($id_cliente <= 1) {
                throw new Exception('Debes elegir un cliente registrado (no Público General) para separar un pedido.');
            }
            if (empty($carrito)) {
                throw new Exception('El carrito está vacío.');
            }

            // El precio del carrito llega del navegador y aquí manda dos cosas: el valor
            // de la mercadería reservada y el anticipo mínimo exigido (30% de ese valor).
            // Con precios manipulados se podría reservar mercadería cara pagando un
            // anticipo irrisorio, así que se relee el catálogo y se recalcula todo.
            require_once dirname(__DIR__) . '/models/M_Producto.php';
            $preciosVigentes = M_Producto::singleton()->obtenerPreciosVigentes(
                array_map(fn($i) => intval($i['id_producto'] ?? 0), $carrito)
            );

            $totalMercaderia = 0.0;
            foreach ($carrito as $i => $item) {
                $idProducto = (int) ($item['id_producto'] ?? 0);
                if (!isset($preciosVigentes[$idProducto])) {
                    throw new Exception('Uno de los productos del carrito ya no existe en el catálogo.');
                }
                $piezas = (float) ($item['piezas'] ?? 0);
                $carrito[$i]['precio']   = $preciosVigentes[$idProducto];
                $carrito[$i]['subtotal'] = round($preciosVigentes[$idProducto] * $piezas, 2);
                $totalMercaderia += $carrito[$i]['subtotal'];
            }
            $totalMercaderia = round($totalMercaderia, 2);

            $anticipo = round(array_sum(array_column($pagos, 'monto')), 2);
            $minimoRequerido = round($totalMercaderia * self::ANTICIPO_MINIMO, 2);
            if ($anticipo < $minimoRequerido) {
                throw new Exception("El anticipo mínimo es " . (self::ANTICIPO_MINIMO * 100) . "% del total (S/ " . number_format($minimoRequerido, 2) . "). Recibido: S/ " . number_format($anticipo, 2) . ".");
            }
            if ($anticipo > $totalMercaderia) {
                throw new Exception('El anticipo no puede superar el valor de la mercadería.');
            }

            $this->conexion->beginTransaction();

            // --- Venta del anticipo: mercadería a precio completo, total = lo pagado hoy ---
            $venta = new Venta(null, $id_usuario, $id_cliente, '', 3, $anticipo, 1, 1, $id_caja, 4);
            foreach ($pagos as $p) {
                $venta->agregarPago((int) $p['metodo_pago'], (float) $p['monto'], $p['referencia'] ?? null);
            }
            foreach ($carrito as $item) {
                $detalle = new DetalleVenta(null, (int) $item['id_producto'], (float) $item['piezas'], (float) $item['precio'], 0.0, (float) $item['subtotal']);
                $venta->agregarDetalle($detalle);
            }

            $resultadoVenta = M_Venta::singleton()->registrarEnTransaccion($this->conexion, $venta);
            $idVentaAnticipo = $resultadoVenta['id_venta'];

            // --- Cabecera de la separación ---
            $stmt = $this->conexion->prepare(
                "INSERT INTO separaciones (codigo, id_cliente, id_usuario, id_venta_anticipo, fecha_vencimiento, total, estado)
                 VALUES ('SEP-TEMP', ?, ?, ?, DATE_ADD(NOW(), INTERVAL " . self::DIAS_VENCIMIENTO . " DAY), ?, 1)"
            );
            $stmt->execute([$id_cliente, $id_usuario, $idVentaAnticipo, $totalMercaderia]);
            $idSeparacion = (int) $this->conexion->lastInsertId();

            $codigo = 'SEP-' . str_pad((string) $idSeparacion, 6, '0', STR_PAD_LEFT);
            $this->conexion->prepare("UPDATE separaciones SET codigo = ? WHERE id_separacion = ?")
                ->execute([$codigo, $idSeparacion]);

            $this->conexion->prepare("UPDATE ventas SET id_separacion = ? WHERE id_venta = ?")
                ->execute([$idSeparacion, $idVentaAnticipo]);

            $this->conexion->commit();
            return ['ok' => true, 'id_separacion' => $idSeparacion, 'codigo' => $codigo, 'id_venta_anticipo' => $idVentaAnticipo];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Registra un abono parcial: una venta origen=4 sin líneas de detalle, ligada
     * a la caja abierta de hoy. Rechaza montos que excedan el saldo pendiente.
     */
    public function abonar(int $id_separacion, int $id_usuario, int $id_caja, array $pagos): array {
        try {
            $this->conexion->beginTransaction();

            $sep = $this->obtenerConLock($id_separacion);
            if (!$sep) {
                throw new Exception('Separación no encontrada.');
            }
            if ((int) $sep['estado'] !== 1) {
                throw new Exception('Esta separación ya no está pendiente.');
            }

            $saldo = $this->calcularSaldo($id_separacion);
            $monto = round(array_sum(array_column($pagos, 'monto')), 2);
            if ($monto <= 0) {
                throw new Exception('El monto del abono debe ser mayor a cero.');
            }
            if ($monto > $saldo + 0.01) {
                throw new Exception("El abono (S/ " . number_format($monto, 2) . ") supera el saldo pendiente (S/ " . number_format($saldo, 2) . ").");
            }

            $venta = new Venta(null, $id_usuario, (int) $sep['id_cliente'], '', 3, $monto, 1, 1, $id_caja, 4);
            foreach ($pagos as $p) {
                $venta->agregarPago((int) $p['metodo_pago'], (float) $p['monto'], $p['referencia'] ?? null);
            }
            $resultadoVenta = M_Venta::singleton()->registrarEnTransaccion($this->conexion, $venta);
            $this->conexion->prepare("UPDATE ventas SET id_separacion = ? WHERE id_venta = ?")
                ->execute([$id_separacion, $resultadoVenta['id_venta']]);

            $this->conexion->commit();
            return ['ok' => true, 'id_venta' => $resultadoVenta['id_venta'], 'saldo_restante' => round($saldo - $monto, 2)];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Cobra el saldo restante (si lo hay) con el comprobante elegido y marca la
     * separación como despachada. No toca stock: ya se reservó al separar.
     */
    public function despachar(int $id_separacion, int $id_usuario, int $id_caja, array $pagos, int $tipo_comprobante = 3): array {
        try {
            $this->conexion->beginTransaction();

            $sep = $this->obtenerConLock($id_separacion);
            if (!$sep) {
                throw new Exception('Separación no encontrada.');
            }
            if ((int) $sep['estado'] !== 1) {
                throw new Exception('Esta separación ya no está pendiente.');
            }

            $saldo = $this->calcularSaldo($id_separacion);
            $idVentaSaldo = null;

            if ($saldo > 0.01) {
                $monto = round(array_sum(array_column($pagos, 'monto')), 2);
                if (abs($monto - $saldo) > 0.01) {
                    throw new Exception("El pago (S/ " . number_format($monto, 2) . ") no coincide con el saldo pendiente (S/ " . number_format($saldo, 2) . ").");
                }
                $venta = new Venta(null, $id_usuario, (int) $sep['id_cliente'], '', $tipo_comprobante, $monto, 1, 1, $id_caja, 4);
                foreach ($pagos as $p) {
                    $venta->agregarPago((int) $p['metodo_pago'], (float) $p['monto'], $p['referencia'] ?? null);
                }
                $resultadoVenta = M_Venta::singleton()->registrarEnTransaccion($this->conexion, $venta);
                $idVentaSaldo = $resultadoVenta['id_venta'];
                $this->conexion->prepare("UPDATE ventas SET id_separacion = ? WHERE id_venta = ?")
                    ->execute([$id_separacion, $idVentaSaldo]);
            }

            $this->conexion->prepare(
                "UPDATE separaciones SET estado = 2, tipo_comprobante_final = ?, id_usuario_despacho = ?, fecha_despacho = NOW() WHERE id_separacion = ?"
            )->execute([$tipo_comprobante, $id_usuario, $id_separacion]);

            $this->conexion->commit();
            return ['ok' => true, 'id_venta_saldo' => $idVentaSaldo];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Anula una separación pendiente: devuelve el stock reservado y deja un
     * movimiento de entrada explícito en kardex_movimientos. Las ventas de abono
     * ya cobradas NO se anulan — el dinero cobrado se queda registrado (decisión
     * tomada); si hay que devolverlo, es un movimiento de caja aparte.
     */
    public function anular(int $id_separacion, string $motivo, int $id_usuario): array {
        try {
            $this->conexion->beginTransaction();

            $sep = $this->obtenerConLock($id_separacion);
            if (!$sep) {
                throw new Exception('Separación no encontrada.');
            }
            if ((int) $sep['estado'] !== 1) {
                throw new Exception('Solo se puede anular una separación pendiente.');
            }

            $stmtDetalles = $this->conexion->prepare(
                "SELECT d.id_producto, d.cantidad, d.precio_venta, p.stock_ilimitado
                 FROM detalle_ventas d
                 INNER JOIN productos p ON p.id_producto = d.id_producto
                 WHERE d.id_venta = ?"
            );
            $stmtDetalles->execute([$sep['id_venta_anticipo']]);
            $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

            $referencia = $sep['codigo'];
            $kardex = M_Kardex::singleton();
            foreach ($detalles as $d) {
                if ((int) ($d['stock_ilimitado'] ?? 0) === 1) {
                    continue;
                }
                $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ?")
                    ->execute([$d['cantidad'], $d['id_producto']]);
                $kardex->registrarMovimiento($d['id_producto'], 'entrada', $d['cantidad'], $d['precio_venta'], $referencia, 'Anulación de separación', $id_usuario);
            }

            $this->conexion->prepare("UPDATE separaciones SET estado = 0, motivo_anulacion = ? WHERE id_separacion = ?")
                ->execute([$motivo, $id_separacion]);

            $this->conexion->commit();
            return ['ok' => true, 'mensaje' => 'Separación anulada. El stock fue devuelto al inventario.'];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public function listar(?int $estado = null): array {
        try {
            $sql = "SELECT s.id_separacion, s.codigo, s.fecha, s.fecha_vencimiento, s.total, s.estado,
                           s.id_venta_anticipo, s.tipo_comprobante_final, s.fecha_despacho,
                           DATEDIFF(s.fecha_vencimiento, CURDATE()) < 0 AS vencida,
                           IF(p.apellidos IS NOT NULL AND p.apellidos != '',
                              CONCAT(p.apellidos, ', ', p.nombres_razon_social),
                              p.nombres_razon_social) AS cliente,
                           p.numero_documento,
                           u.username AS vendedor,
                           IFNULL((SELECT SUM(v.total) FROM ventas v WHERE v.id_separacion = s.id_separacion AND v.estado = 1), 0) AS abonado
                    FROM separaciones s
                    INNER JOIN clientes c ON s.id_cliente = c.id_cliente
                    INNER JOIN personas p ON c.id_persona = p.id_persona
                    INNER JOIN usuarios u ON s.id_usuario = u.id_usuario";
            $params = [];
            if ($estado !== null) {
                $sql .= " WHERE s.estado = ?";
                $params[] = $estado;
            }
            $sql .= " ORDER BY s.fecha DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$f) {
                $f['saldo'] = round((float) $f['total'] - (float) $f['abonado'], 2);
                $f['vencida'] = (bool) $f['vencida'];
            }
            unset($f);
            return $filas;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function obtenerPorId(int $id_separacion): ?array {
        $filas = $this->listar(null);
        foreach ($filas as $f) {
            if ((int) $f['id_separacion'] === $id_separacion) {
                $f['abonos'] = $this->obtenerAbonos($id_separacion);
                return $f;
            }
        }
        return null;
    }

    public function obtenerAbonos(int $id_separacion): array {
        try {
            $stmt = $this->conexion->prepare(
                "SELECT v.id_venta, v.fecha, v.total, v.metodo_pago, v.origen,
                        v.id_venta = s.id_venta_anticipo AS es_anticipo
                 FROM ventas v
                 INNER JOIN separaciones s ON s.id_separacion = ?
                 WHERE v.id_separacion = ? AND v.estado = 1
                 ORDER BY v.fecha ASC"
            );
            $stmt->execute([$id_separacion, $id_separacion]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    private function calcularSaldo(int $id_separacion): float {
        $stmt = $this->conexion->prepare(
            "SELECT s.total - IFNULL((SELECT SUM(v.total) FROM ventas v WHERE v.id_separacion = s.id_separacion AND v.estado = 1), 0) AS saldo
             FROM separaciones s WHERE s.id_separacion = ?"
        );
        $stmt->execute([$id_separacion]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    private function obtenerConLock(int $id_separacion): ?array {
        $stmt = $this->conexion->prepare("SELECT * FROM separaciones WHERE id_separacion = ? FOR UPDATE");
        $stmt->execute([$id_separacion]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }
}
?>
