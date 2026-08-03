<?php
// Cargar archivo de conexión centralizada
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Modelo para el control de sesiones de Caja (Apertura y Cierre)
 * Gestiona el balance de dinero en caja por usuario y calcula diferencias al cerrar.
 */
class M_Caja {
    // Instancia estática del patrón Singleton
    private static $instancia;
    // Conexión PDO
    private $conexion;

    // Constructor privado
    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    // Obtener instancia única del modelo
    public static function singleton() {
        if (!isset(self::$instancia)) {
            $miclase = __CLASS__;
            self::$instancia = new $miclase;
        }
        return self::$instancia;
    }

    /**
     * Obtiene la sesión de caja que se encuentre abierta (estado = 1) para un usuario específico
     * @param int $id_usuario ID del usuario a consultar
     * @return array|null Fila de la base de datos o null si no tiene caja abierta
     */
    public function obtenerCajaAbierta($id_usuario) {
        try {
            $sql = "SELECT * FROM cajas WHERE id_usuario = ? AND estado = 1 ORDER BY id_caja DESC LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_usuario]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Registra la apertura de caja de un usuario con un monto inicial
     * Valida que no exista ya otra caja abierta para el mismo usuario.
     * @param int $id_usuario ID del usuario
     * @param float $monto_apertura Saldo inicial con el que abre la caja
     * @return bool True si abre con éxito, False si ya tiene caja abierta o falla
     */
    public function abrirCaja($id_usuario, $monto_apertura) {
        try {
            // Verificar que no tenga ya una caja abierta
            if ($this->obtenerCajaAbierta($id_usuario)) {
                return false;
            }
            $sql = "INSERT INTO cajas (id_usuario, monto_apertura, estado) VALUES (?, ?, 1)";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([$id_usuario, $monto_apertura]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Cierra la sesión de caja del usuario, calculando automáticamente las ventas acumuladas por método de pago,
     * el total esperado y la diferencia (sobrante o faltante) por cada método.
     * @param int $id_caja ID de la sesión de caja a cerrar
     * @param int $id_usuario ID del usuario dueño de la caja
     * @param float $cierre_efectivo Dinero físico real reportado por el usuario
     * @param float $cierre_yape Monto de capturas de Yape reportado
     * @param float $cierre_tarjeta Monto de vouchers reportado
     * @param string $observaciones Comentarios u observaciones sobre el cuadre de caja
     * @return bool True en caso de éxito, False si no existe la caja o falla la DB
     */
    public function cerrarCaja($id_caja, $id_usuario, $cierre_efectivo, $cierre_yape, $cierre_tarjeta, $observaciones) {
        try {
            // 1. Obtener la fecha de apertura para delimitar el cálculo de ventas
            $sql_caja = "SELECT fecha_apertura, monto_apertura FROM cajas WHERE id_caja = ? AND id_usuario = ? AND estado = 1";
            $stmt_caja = $this->conexion->prepare($sql_caja);
            $stmt_caja->execute([$id_caja, $id_usuario]);
            $caja = $stmt_caja->fetch();

            if (!$caja) return false;

            $fecha_apertura = $caja['fecha_apertura'];
            $fecha_cierre = date('Y-m-d H:i:s');
            $monto_apertura = floatval($caja['monto_apertura']);

            // 2. Calcular desglose de ventas por método de pago
            $desglose = $this->calcularDesglosePorMetodo($id_usuario, $fecha_apertura);
            
            $ventas_efectivo = $desglose['1'];
            $ventas_yape     = $desglose['2'];
            $ventas_tarjeta  = $desglose['3'];
            $total_ventas    = $ventas_efectivo + $ventas_yape + $ventas_tarjeta;

            // Obtener número de ventas
            $sql_num = "SELECT COUNT(id_venta) FROM ventas WHERE id_usuario = ? AND estado = 1 AND fecha BETWEEN ? AND ?";
            $stmt_num = $this->conexion->prepare($sql_num);
            $stmt_num->execute([$id_usuario, $fecha_apertura, $fecha_cierre]);
            $num_ventas = intval($stmt_num->fetchColumn());

            // 4. Calcular diferencias
            $dif_efectivo = $cierre_efectivo - ($monto_apertura + $ventas_efectivo);
            $dif_yape     = $cierre_yape - $ventas_yape;
            $dif_tarjeta  = $cierre_tarjeta - $ventas_tarjeta;

            $monto_cierre = $cierre_efectivo + $cierre_yape + $cierre_tarjeta;
            $diferencia   = $dif_efectivo + $dif_yape + $dif_tarjeta;

            // 5. Actualizar la fila en cajas cerrando el estado (estado = 0)
            $sql_upd = "UPDATE cajas SET 
                        monto_cierre = ?, cierre_efectivo = ?, cierre_yape = ?, cierre_tarjeta = ?,
                        fecha_cierre = ?, 
                        total_ventas = ?, num_ventas = ?, 
                        diferencia = ?, dif_efectivo = ?, dif_yape = ?, dif_tarjeta = ?,
                        observaciones = ?, estado = 0 
                        WHERE id_caja = ?";
            
            $stmt_upd = $this->conexion->prepare($sql_upd);
            return $stmt_upd->execute([
                $monto_cierre, $cierre_efectivo, $cierre_yape, $cierre_tarjeta,
                $fecha_cierre,
                $total_ventas, $num_ventas,
                $diferencia, $dif_efectivo, $dif_yape, $dif_tarjeta,
                $observaciones, $id_caja
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Lista todas las sesiones de cajas abiertas y cerradas para una fecha específica (Filtro del Administrador)
     * Permite también opcionalmente filtrar por un usuario específico.
     * @param string $fecha Fecha a consultar (Formato YYYY-MM-DD)
     * @param int|null $id_usuario ID del usuario opcional para el filtro
     * @return array Listado de sesiones de caja con datos de la persona
     */
    public function listarPorFecha($fecha, $id_usuario = null) {
        try {
            $sql = "SELECT c.*, u.username, CONCAT(p.nombres_razon_social, ' ', IFNULL(p.apellidos, '')) as nombre_completo
                    FROM cajas c
                    INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
                    INNER JOIN personas p ON u.id_persona = p.id_persona
                    WHERE DATE(c.fecha_apertura) = ?";
            
            $params = [$fecha];

            if ($id_usuario) {
                $sql .= " AND c.id_usuario = ?";
                $params[] = $id_usuario;
            }

            $sql .= " ORDER BY c.fecha_apertura DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Calcula dinámicamente las ventas acumuladas hechas por un usuario desde su hora de apertura de caja
     * @param int $id_usuario ID del usuario
     * @param string $fecha_apertura Fecha y hora de apertura
     * @return float Sumatoria total de las ventas
     */
    public function calcularVentasAcumuladas($id_usuario, $fecha_apertura) {
        try {
            $sql = "SELECT SUM(total) as total_ventas 
                    FROM ventas 
                    WHERE id_usuario = ? AND estado = 1 AND fecha >= ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_usuario, $fecha_apertura]);
            $res = $stmt->fetchColumn();
            return $res ? floatval($res) : 0.00;
        } catch (PDOException $e) {
            return 0.00;
        }
    }

    /**
     * Calcula el desglose de ventas por método de pago para un usuario desde su apertura de caja
     */
    public function calcularDesglosePorMetodo($id_usuario, $fecha_apertura) {
        try {
            $sql = "SELECT metodo_pago, SUM(total) AS monto
                    FROM ventas
                    WHERE id_usuario = ? AND estado = 1 AND fecha >= ?
                    GROUP BY metodo_pago";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_usuario, $fecha_apertura]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = ['1' => 0.0, '2' => 0.0, '3' => 0.0];
            foreach ($rows as $r) {
                $result[$r['metodo_pago']] = floatval($r['monto']);
            }
            return $result;
        } catch (PDOException $e) {
            return ['1' => 0.0, '2' => 0.0, '3' => 0.0];
        }
    }

    /**
     * Obtiene el detalle completo de una caja para mostrar en el modal
     */
    public function obtenerDetalleCaja($id_caja) {
        try {
            // 1. Obtener datos de la caja
            $stmtCaja = $this->conexion->prepare("SELECT * FROM cajas WHERE id_caja = ?");
            $stmtCaja->execute([$id_caja]);
            $caja = $stmtCaja->fetch(PDO::FETCH_ASSOC);
            if (!$caja) return null;

            $fecha_fin = $caja['fecha_cierre'] ?? date('Y-m-d H:i:s');

            // 2. Ventas del período con desglose
            $stmtVentas = $this->conexion->prepare("
                SELECT v.id_venta, v.fecha, v.total, v.metodo_pago,
                       v.tipo_comprobante, v.estado,
                       IFNULL(p.nombres_razon_social, 'Público General') AS cliente
                FROM ventas v
                LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
                LEFT JOIN personas p ON c.id_persona = p.id_persona
                WHERE v.id_usuario = ? AND v.fecha BETWEEN ? AND ?
                ORDER BY v.fecha ASC
            ");
            $stmtVentas->execute([$caja['id_usuario'], $caja['fecha_apertura'], $fecha_fin]);
            $ventas = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

            // 3. Resumen por método (de las ventas recuperadas, si se desea verificar)
            $resumen = ['1' => 0.0, '2' => 0.0, '3' => 0.0];
            foreach ($ventas as $v) {
                if ($v['estado'] == 1) {
                    $m = (string)$v['metodo_pago'];
                    if (isset($resumen[$m])) $resumen[$m] += floatval($v['total']);
                }
            }

            return ['caja' => $caja, 'ventas' => $ventas, 'resumen' => $resumen];
        } catch (PDOException $e) {
            return null;
        }
    }
}
?>
