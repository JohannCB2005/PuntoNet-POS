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
     *      a. Bloquea la fila del insumo con SELECT ... FOR UPDATE.
     *      b. Valida que el stock en piezas sea suficiente; si no, lanza excepción.
     *      c. Inserta la línea en 'detalle_ventas'.
     *      d. Descuenta stock_piezas en 'insumos'.
     *   4. Confirma la transacción (COMMIT) si todo fue exitoso.
     *   5. Revierte (ROLLBACK) ante cualquier error, propagando el mensaje.
     *
     * @return array ['ok' => bool, 'id_venta' => int|null, 'mensaje' => string]
     */
    public function registrar(Venta $venta) {
        try {
            $this->conexion->beginTransaction();

            // --- PASO 1: Insertar cabecera de venta ---
            $stmtVenta = $this->conexion->prepare(
                "INSERT INTO ventas (id_usuario, id_cliente, tipo_comprobante, total, metodo_pago, estado)
                 VALUES (?, ?, ?, ?, ?, 1)"
            );
            $stmtVenta->execute([
                $venta->id_usuario,
                $venta->id_cliente,
                $venta->tipo_comprobante,
                $venta->total,
                $venta->metodo_pago
            ]);
            $id_venta = (int) $this->conexion->lastInsertId();

            // Preparar sentencias reutilizables para el bucle
            $stmtStock   = $this->conexion->prepare(
                "SELECT stock_piezas, costo_produccion FROM insumos WHERE id_insumo = ? FOR UPDATE"
            );
            $stmtDetalle = $this->conexion->prepare(
                "INSERT INTO detalle_ventas (id_venta, id_insumo, cantidad, precio_venta, costo_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtDescuento = $this->conexion->prepare(
                "UPDATE insumos SET stock_piezas = stock_piezas - ? WHERE id_insumo = ?"
            );

            // --- PASO 2: Procesar cada línea del carrito ---
            foreach ($venta->detalles as $detalle) {
                $id_insumo  = (int)   $detalle->id_insumo;
                $piezas     = (float) $detalle->piezas;
                $precio     = (float) $detalle->precio_venta;
                $subtotal   = (float) $detalle->subtotal;

                // a. Leer stock actual con bloqueo de fila
                $stmtStock->execute([$id_insumo]);
                $row = $stmtStock->fetch();

                if ($row === false) {
                    throw new Exception("Insumo ID {$id_insumo} no encontrado en inventario.");
                }

                $stock_actual = (float) $row['stock_piezas'];
                $costo_actual = (float) $row['costo_produccion'];

                // b. Validar stock suficiente
                if ($stock_actual < $piezas) {
                    throw new Exception("Stock físico (piezas) insuficiente. Transacción cancelada.");
                }

                // c. Insertar línea de detalle
                $stmtDetalle->execute([$id_venta, $id_insumo, $piezas, $precio, $costo_actual, $subtotal]);

                // d. Descontar stock
                $stmtDescuento->execute([$piezas, $id_insumo]);
            }


            $this->conexion->commit();
            return ['ok' => true, 'id_venta' => $id_venta, 'mensaje' => ''];

        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'id_venta' => null, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Anula una venta y revierte el stock de todos sus ítems.
     * Equivalente a: sp_anular_venta
     *
     * Flujo:
     *   1. Inicia una transacción.
     *   2. Marca la venta como inactiva (estado = 0).
     *   3. Recupera todas las líneas de detalle_ventas de esa venta.
     *   4. Por cada línea, devuelve las piezas al stock del insumo.
     *   5. COMMIT si todo es correcto, ROLLBACK en caso de error.
     *
     * @param int $id_venta ID de la venta a anular
     * @return array ['ok' => bool, 'mensaje' => string]
     */
    public function anular($id_venta) {
        try {
            $this->conexion->beginTransaction();

            // --- PASO 1: Marcar venta como anulada ---
            $stmtAnular = $this->conexion->prepare(
                "UPDATE ventas SET estado = 0 WHERE id_venta = ?"
            );
            $stmtAnular->execute([$id_venta]);

            // --- PASO 2: Obtener ítems de la venta ---
            $stmtDetalles = $this->conexion->prepare(
                "SELECT id_insumo, cantidad FROM detalle_ventas WHERE id_venta = ?"
            );
            $stmtDetalles->execute([$id_venta]);
            $detalles = $stmtDetalles->fetchAll();

            // --- PASO 3: Revertir stock por cada ítem ---
            $stmtRevertir = $this->conexion->prepare(
                "UPDATE insumos SET stock_piezas = stock_piezas + ? WHERE id_insumo = ?"
            );
            foreach ($detalles as $detalle) {
                $stmtRevertir->execute([$detalle['cantidad'], $detalle['id_insumo']]);
            }

            $this->conexion->commit();
            return ['ok' => true, 'mensaje' => 'Venta anulada correctamente.'];

        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
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
            $sql = "SELECT v.id_venta, v.tipo_comprobante, v.fecha, v.total, v.estado, v.metodo_pago,
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
            $sql = "SELECT dv.cantidad AS piezas, dv.precio_venta, dv.costo_unitario, dv.subtotal,
                    CASE
                        WHEN i.id_talla IS NOT NULL AND t.nombre IS NOT NULL
                        THEN CONCAT(i.nombre, ' - T.', t.nombre)
                        ELSE i.nombre
                    END AS insumo_nombre,
                    t.nombre AS talla_nombre,
                    i.id_talla,
                    um.abreviatura
                    FROM detalle_ventas dv
                    INNER JOIN insumos i ON dv.id_insumo = i.id_insumo
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