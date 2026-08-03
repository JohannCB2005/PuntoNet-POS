<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Cotizacion.php';
require_once dirname(__DIR__) . '/entities/DetalleCotizacion.php';

class M_Cotizacion {
    private static $instancia = null;
    private $conexion;

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

    public function registrar(Cotizacion $cotizacion) {
        try {
            $this->conexion->beginTransaction();

            // Insertar cabecera de cotización
            $stmt = $this->conexion->prepare(
                "INSERT INTO cotizaciones (codigo, id_usuario, id_cliente, cliente_nombre_manual, fecha_vencimiento, subtotal, igv, total, observaciones, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            
            // Generar código temporal que luego actualizaremos o pre-generaremos
            $codigo_temp = 'COT-TEMP';
            
            $stmt->execute([
                $codigo_temp,
                $cotizacion->id_usuario,
                $cotizacion->id_cliente,
                $cotizacion->cliente_nombre_manual,
                $cotizacion->fecha_vencimiento,
                $cotizacion->subtotal,
                $cotizacion->igv,
                $cotizacion->total,
                $cotizacion->observaciones
            ]);
            $id_cotizacion = (int) $this->conexion->lastInsertId();

            // Actualizar código con el ID real
            $codigo_real = 'COT-' . str_pad($id_cotizacion, 6, '0', STR_PAD_LEFT);
            $stmtUpd = $this->conexion->prepare("UPDATE cotizaciones SET codigo = ? WHERE id_cotizacion = ?");
            $stmtUpd->execute([$codigo_real, $id_cotizacion]);

            // Insertar detalles
            $stmtDetalle = $this->conexion->prepare(
                "INSERT INTO detalle_cotizaciones (id_cotizacion, id_producto, piezas, precio_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($cotizacion->detalles as $detalle) {
                $stmtDetalle->execute([
                    $id_cotizacion,
                    $detalle->id_producto,
                    $detalle->piezas,
                    $detalle->precio_unitario,
                    $detalle->subtotal
                ]);
            }

            $this->conexion->commit();
            return ['ok' => true, 'id_cotizacion' => $id_cotizacion, 'codigo' => $codigo_real];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public function listar($id_usuario = null) {
        try {
            $sql = "SELECT c.*, 
                           IFNULL(pcli.nombres_razon_social, c.cliente_nombre_manual) as nombre_cliente,
                           pcli.apellidos as apellidos_cliente,
                           pcli.numero_documento as documento_cliente,
                           CONCAT(pusr.nombres_razon_social, ' ', IFNULL(pusr.apellidos, '')) as nombre_usuario
                    FROM cotizaciones c
                    LEFT JOIN clientes cl ON c.id_cliente = cl.id_cliente
                    LEFT JOIN personas pcli ON cl.id_persona = pcli.id_persona
                    LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
                    LEFT JOIN personas pusr ON u.id_persona = pusr.id_persona ";
            
            if ($id_usuario !== null) {
                $sql .= " WHERE c.id_usuario = ? ";
                $sql .= " ORDER BY c.id_cotizacion DESC";
                $stmt = $this->conexion->prepare($sql);
                $stmt->execute([$id_usuario]);
            } else {
                $sql .= " ORDER BY c.id_cotizacion DESC";
                $stmt = $this->conexion->query($sql);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function obtenerPorId($id_cotizacion) {
        try {
            // Cabecera
            $sql = "SELECT c.*, 
                           IFNULL(pcli.nombres_razon_social, c.cliente_nombre_manual) as nombre_cliente,
                           pcli.apellidos as apellidos_cliente,
                           pcli.numero_documento as documento_cliente,
                           pcli.direccion as direccion_cliente,
                           pcli.telefono as telefono_cliente,
                           CONCAT(pusr.nombres_razon_social, ' ', IFNULL(pusr.apellidos, '')) as nombre_usuario
                    FROM cotizaciones c
                    LEFT JOIN clientes cl ON c.id_cliente = cl.id_cliente
                    LEFT JOIN personas pcli ON cl.id_persona = pcli.id_persona
                    LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
                    LEFT JOIN personas pusr ON u.id_persona = pusr.id_persona
                    WHERE c.id_cotizacion = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_cotizacion]);
            $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cotizacion) return null;

            // Detalles
            $sqlDet = "SELECT dc.*,
                        CASE
                            WHEN i.id_talla IS NOT NULL AND t.nombre IS NOT NULL
                            THEN CONCAT(i.nombre, ' - T.', t.nombre)
                            ELSE i.nombre
                        END AS nombre_producto,
                        NULL AS codigo_producto
                       FROM detalle_cotizaciones dc
                       INNER JOIN productos i ON dc.id_producto = i.id_producto
                       LEFT JOIN tallas t ON i.id_talla = t.id_talla
                       WHERE dc.id_cotizacion = ?";
            $stmtDet = $this->conexion->prepare($sqlDet);
            $stmtDet->execute([$id_cotizacion]);
            $cotizacion['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            return $cotizacion;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function anular($id_cotizacion) {
        try {
            $stmt = $this->conexion->prepare("UPDATE cotizaciones SET estado = 0 WHERE id_cotizacion = ?");
            $stmt->execute([$id_cotizacion]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function marcarConvertida($id_cotizacion, $id_venta) {
        try {
            $stmt = $this->conexion->prepare("UPDATE cotizaciones SET estado = 2, id_venta = ? WHERE id_cotizacion = ?");
            $stmt->execute([$id_venta, $id_cotizacion]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
