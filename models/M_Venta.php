<?php
// Cargar dependencias para la transacción de ventas
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Venta.php';

/**
 * Modelo para el procesamiento de Ventas
 * Toda la lógica transaccional (antes en sp_registrar_venta y sp_anular_venta)
 * ha sido migrada a PHP nativo con PDO para compatibilidad con hosting sin STORED PROCEDURES.
 */
class M_Venta {
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
            $miclase = __CLASS__;
            self::$instancia = new $miclase;
        }
        return self::$instancia;
    }

    /**
     * Registra una venta completa con sus líneas de detalle y descuenta el stock.
     * Equivalente a: sp_registrar_venta
     *
     * Flujo:
     *   1. Inicia una transacción.
     *   2. Inserta la cabecera en la tabla 'ventas'.
     *   3. Por cada ítem del carrito:
     *      a. Bloquea la fila del producto con SELECT ... FOR UPDATE.
     *      b. Valida que el stock en piezas sea suficiente; si no, lanza excepción.
     *      c. Inserta la línea en 'detalle_ventas'.
     *      d. Descuenta stock_piezas en 'productos'.
     *   4. Confirma la transacción (COMMIT) si todo fue exitoso.
     *   5. Revierte (ROLLBACK) ante cualquier error, propagando el mensaje.
     *
     * @return array ['ok' => bool, 'id_venta' => int|null, 'mensaje' => string]
     */
    public function registrar(Venta $venta) {
        try {
            $this->conexion->beginTransaction();
            $resultado = $this->registrarEnTransaccion($this->conexion, $venta);
            $this->conexion->commit();
            return ['ok' => true, 'id_venta' => $resultado['id_venta'], 'mensaje' => ''];

        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'id_venta' => null, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Cuerpo transaccional de registrar(): inserta cabecera + líneas de pago +
     * detalle + descuenta stock, SIN abrir ni cerrar transacción propia. Existe
     * para que otros modelos (M_Separacion) puedan registrar una venta como parte
     * de una transacción más grande (venta del anticipo + cabecera de separación)
     * sin duplicar esta lógica. Lanza Exception en el mismo punto que registrar();
     * el llamador es responsable del rollback.
     *
     * @return array ['id_venta' => int]
     */
    public function registrarEnTransaccion(PDO $conexion, Venta $venta): array {
        // Pago mixto: si no vienen líneas explícitas, se asume una sola con el
        // metodo_pago/total de la venta — así los llamadores que aún no arman
        // $pagos (M_CambioTalla, conversión de cotización) siguen funcionando.
        $pagos = $venta->pagos;
        if (empty($pagos)) {
            $pagos = [['metodo_pago' => $venta->metodo_pago, 'monto' => $venta->total, 'referencia' => null]];
        }

        // La suma de las líneas debe calzar con el total ANTES de tocar nada:
        // valga lo mismo, ni de más ni de menos (tolerancia de 1 céntimo por redondeo).
        $sumaPagos = array_sum(array_column($pagos, 'monto'));
        if (abs($sumaPagos - (float) $venta->total) > 0.01) {
            throw new Exception("La suma de los pagos (S/ " . number_format($sumaPagos, 2) . ") no coincide con el total (S/ " . number_format((float) $venta->total, 2) . ").");
        }

        // metodo_pago resumen: el único método si todas las líneas coinciden, o
        // 4 (Mixto) si hay más de uno distinto. Es solo una etiqueta para mostrar
        // — el desglose real de caja se calcula sobre las líneas de pago_venta.
        $metodosUnicos = array_unique(array_column($pagos, 'metodo_pago'));
        $metodoPagoResumen = count($metodosUnicos) === 1 ? (int) $metodosUnicos[0] : 4;

        // Desglose fiscal de la cabecera: se deriva SIEMPRE de total, nunca de
        // sumar detalle_ventas. Para una venta normal (POS, pedido online,
        // despacho de separación) total == mercadería y ambos caminos coinciden;
        // pero en el anticipo/abono de una separación total es solo lo pagado
        // HOY mientras detalle_ventas guarda la mercadería completa a precio de
        // lista (por diseño — ver CODEMAP). Como esos comprobantes son siempre
        // Nota de Venta y nunca se envían a SUNAT, no hace falta que cuadren
        // entre sí; lo que sí debe cuadrar siempre es subtotal + igv == total,
        // y derivándolo así se cumple por construcción.
        $ventaSubtotal = round((float) $venta->total / 1.18, 2);
        $ventaIgv = round((float) $venta->total - $ventaSubtotal, 2);

        // --- PASO 1: Insertar cabecera de venta ---
        $stmtVenta = $conexion->prepare(
            "INSERT INTO ventas (id_usuario, id_caja, id_cliente, tipo_comprobante, total, subtotal, igv, metodo_pago, estado, origen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)"
        );
        $stmtVenta->execute([
            $venta->id_usuario,
            $venta->id_caja,
            $venta->id_cliente,
            $venta->tipo_comprobante,
            $venta->total,
            $ventaSubtotal,
            $ventaIgv,
            $metodoPagoResumen,
            $venta->origen ?? 1
        ]);
        $id_venta = (int) $conexion->lastInsertId();

        // --- PASO 1b: Insertar cada línea de pago ---
        $stmtPago = $conexion->prepare(
            "INSERT INTO pagos_venta (id_venta, metodo_pago, monto, referencia) VALUES (?, ?, ?, ?)"
        );
        foreach ($pagos as $pago) {
            $stmtPago->execute([
                $id_venta,
                (int) $pago['metodo_pago'],
                (float) $pago['monto'],
                $pago['referencia'] ?? null,
            ]);
        }

        // Preparar sentencias reutilizables para el bucle
        $stmtStock   = $conexion->prepare(
            "SELECT stock_piezas, costo_produccion, comision, stock_ilimitado FROM productos WHERE id_producto = ? FOR UPDATE"
        );
        $stmtDetalle = $conexion->prepare(
            "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_venta, costo_unitario, comision_unitaria, valor_unitario, igv_linea, subtotal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtDescuento = $conexion->prepare(
            "UPDATE productos SET stock_piezas = stock_piezas - ? WHERE id_producto = ? AND stock_ilimitado = 0"
        );

        // --- PASO 2: Procesar cada línea del carrito ---
        foreach ($venta->detalles as $detalle) {
            $id_producto  = (int)   $detalle->id_producto;
            $piezas     = (float) $detalle->piezas;
            $precio     = (float) $detalle->precio_venta;
            $subtotal   = (float) $detalle->subtotal;

            // a. Leer stock actual con bloqueo de fila
            $stmtStock->execute([$id_producto]);
            $row = $stmtStock->fetch();

            if ($row === false) {
                throw new Exception("Producto ID {$id_producto} no encontrado en inventario.");
            }

            $stock_actual = (float) $row['stock_piezas'];
            $costo_actual = (float) $row['costo_produccion'];
            $comision_actual = (float) $row['comision'];
            $es_ilimitado = (int) ($row['stock_ilimitado'] ?? 0) === 1;

            // b. Validar stock suficiente (se omite para productos de stock ilimitado)
            if (!$es_ilimitado && $stock_actual < $piezas) {
                throw new Exception("Stock físico (piezas) insuficiente. Transacción cancelada.");
            }

            // c. Desglose fiscal de la línea, a partir de SU PROPIO precio (nunca
            //    de la cabecera): precio_venta ya incluye IGV, se separa a 18%.
            $valorUnitarioLinea = round($precio / 1.18, 4);
            $igvLinea = round($subtotal - round($subtotal / 1.18, 2), 2);

            // d. Insertar línea de detalle (comisión congelada: un cambio de tarifa
            //    después de la venta nunca reescribe lo ya devengado)
            $stmtDetalle->execute([$id_venta, $id_producto, $piezas, $precio, $costo_actual, $comision_actual, $valorUnitarioLinea, $igvLinea, $subtotal]);

            // e. Descontar stock
            $stmtDescuento->execute([$piezas, $id_producto]);
        }

        return ['id_venta' => $id_venta];
    }

    /**
     * Anula una venta y revierte el stock de todos sus ítems. La anulación
     * LOCAL (estado=0 + stock devuelto) siempre es inmediata — el cajero no
     * puede esperar a que SUNAT responda para volver a vender la prenda.
     *
     * Lo que SÍ depende de estado_sunat es si además hace falta avisarle a
     * SUNAT y cómo (ver DATEDIFF en SQL, nunca contra el reloj de PHP — mismo
     * criterio que el resto del sistema por el desfase de zona horaria):
     *   - estado_sunat=0 (Nota de Venta) o 1 (nunca se llegó a enviar):
     *     anulación puramente local, no hay nada que comunicarle a SUNAT.
     *   - estado_sunat=3 (SUNAT ya lo rechazó): el correlativo quedó quemado,
     *     tampoco hay nada que dar de baja.
     *   - estado_sunat=2 (aceptado) y ≤7 días desde la emisión: anulación local
     *     ahora + el llamador (C_Venta.php) debe encolar la baja ante SUNAT
     *     con M_Sunat::darDeBaja() — Comunicación de Baja si es factura,
     *     Resumen Diario de baja (estado 3) si es boleta; VoidedDocuments
     *     excluye boletas explícitamente, no es simétrico.
     *   - estado_sunat=2 y >7 días: NO se puede anular. Hay que emitir una
     *     Nota de Crédito (M_Sunat::emitirNotaCredito()).
     *
     * @param int $id_venta ID de la venta a anular
     * @return array ['ok'=>bool, 'mensaje'=>string, 'requiere_baja_sunat'=>bool]
     */
    public function anular($id_venta) {
        try {
            $this->conexion->beginTransaction();

            $stmt = $this->conexion->prepare(
                "SELECT estado, estado_sunat, DATEDIFF(NOW(), fecha) AS dias_transcurridos
                 FROM ventas WHERE id_venta = ? FOR UPDATE"
            );
            $stmt->execute([$id_venta]);
            $venta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$venta) {
                throw new Exception('Venta no encontrada.');
            }
            if ((int) $venta['estado'] === 0) {
                throw new Exception('Esta venta ya está anulada.');
            }

            $estadoSunat = (int) $venta['estado_sunat'];
            if ($estadoSunat === 2 && (int) $venta['dias_transcurridos'] > 7) {
                throw new Exception('Esta venta ya fue aceptada por SUNAT y tiene más de 7 días: ya no se puede anular. Emite una Nota de Crédito desde el Historial.');
            }

            // --- PASO 1: Marcar venta como anulada ---
            $stmtAnular = $this->conexion->prepare(
                "UPDATE ventas SET estado = 0 WHERE id_venta = ?"
            );
            $stmtAnular->execute([$id_venta]);

            // --- PASO 2: Obtener ítems de la venta ---
            $stmtDetalles = $this->conexion->prepare(
                "SELECT id_producto, cantidad FROM detalle_ventas WHERE id_venta = ?"
            );
            $stmtDetalles->execute([$id_venta]);
            $detalles = $stmtDetalles->fetchAll();

            // --- PASO 3: Revertir stock por cada ítem ---
            $stmtRevertir = $this->conexion->prepare(
                "UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ? AND stock_ilimitado = 0"
            );
            foreach ($detalles as $detalle) {
                $stmtRevertir->execute([$detalle['cantidad'], $detalle['id_producto']]);
            }

            $this->conexion->commit();

            // Solo un comprobante que SUNAT ya aceptó necesita que se le avise de
            // la baja; el llamador decide cuándo/cómo (llamada de red, fuera de
            // esta transacción).
            $requiereBajaSunat = ($estadoSunat === 2);
            return ['ok' => true, 'mensaje' => 'Venta anulada correctamente.', 'requiere_baja_sunat' => $requiereBajaSunat];

        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * True si la venta es un abono/anticipo/despacho de una separación (id_separacion
     * no nulo). Usado por C_Venta.php?action=anular para rechazar la anulación directa
     * de esas ventas: deben anularse desde el módulo de Separaciones, que además
     * revierte el stock correctamente (la venta del anticipo es la única con detalle).
     */
    public function perteneceASeparacion($id_venta): bool {
        try {
            $stmt = $this->conexion->prepare("SELECT id_separacion FROM ventas WHERE id_venta = ?");
            $stmt->execute([$id_venta]);
            $valor = $stmt->fetchColumn();
            return $valor !== null && $valor !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtiene el listado histórico de ventas del sistema.
     * Si se pasa el ID de un vendedor, filtra únicamente sus ventas (Restricción del rol vendedor).
     * @param int|null $id_usuario ID opcional del vendedor
     * @return array Listado asociativo de ventas
     */
    public function listar($id_usuario = null) {
        try {
            $sql = "SELECT v.id_venta, v.tipo_comprobante, v.fecha, v.total, v.subtotal, v.igv, v.estado, v.metodo_pago, v.origen,
                           v.serie, v.correlativo, v.estado_sunat, v.sunat_codigo, v.sunat_mensaje, v.sunat_hash,
                           DATEDIFF(NOW(), v.fecha) AS dias_transcurridos,
                           u.username AS vendedor,
                           IF(p.apellidos IS NOT NULL AND p.apellidos != '',
                              CONCAT(p.apellidos, ', ', p.nombres_razon_social),
                              p.nombres_razon_social) AS cliente,                            p.numero_documento
                     FROM ventas v
                     INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                     LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
                     LEFT JOIN personas p ON c.id_persona = p.id_persona";
            
            if ($id_usuario !== null) {
                $sql .= " WHERE v.id_usuario = ?";
            }
            
            $sql .= " ORDER BY v.fecha DESC";
            
            $stmt = $this->conexion->prepare($sql);
            if ($id_usuario !== null) {
                $stmt->execute([$id_usuario]);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtiene todas las líneas de detalle de una venta específica.
     * @param int $id_venta ID de la venta a consultar
     * @return array Listado de productos e importes de la venta
     */
    public function obtenerDetallesPorVenta($id_venta) {
        try {
            $sql = "SELECT dv.id_detalle, dv.id_producto, dv.cantidad AS piezas, dv.precio_venta, dv.costo_unitario, dv.subtotal,
                    CASE
                        WHEN i.id_talla IS NOT NULL AND t.nombre IS NOT NULL
                        THEN CONCAT(i.nombre, ' - T.', t.nombre)
                        ELSE i.nombre
                    END AS producto_nombre,
                    t.nombre AS talla_nombre,
                    i.id_talla,
                    i.id_producto_padre,
                    um.abreviatura
                    FROM detalle_ventas dv
                    INNER JOIN productos i ON dv.id_producto = i.id_producto
                    INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                    LEFT JOIN tallas t ON i.id_talla = t.id_talla
                    WHERE dv.id_venta = ?";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_venta]);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }


}
?>