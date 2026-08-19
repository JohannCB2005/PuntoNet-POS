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
            // Correlativo global: máximo existente + 1 (identificador de caja).
            $numero = (int) $this->conexion->query('SELECT COALESCE(MAX(numero_caja), 0) + 1 FROM cajas')->fetchColumn();
            $sql = "INSERT INTO cajas (numero_caja, id_usuario, monto_apertura, estado) VALUES (?, ?, ?, 1)";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([$numero, $id_usuario, $monto_apertura]);
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
            // 1. Confirmar que la caja existe, está abierta y pertenece al usuario
            $sql_caja = "SELECT fecha_apertura, monto_apertura FROM cajas WHERE id_caja = ? AND id_usuario = ? AND estado = 1";
            $stmt_caja = $this->conexion->prepare($sql_caja);
            $stmt_caja->execute([$id_caja, $id_usuario]);
            $caja = $stmt_caja->fetch();

            if (!$caja) return false;

            $monto_apertura = floatval($caja['monto_apertura']);

            // 2. Calcular desglose de ventas por método de pago — SIEMPRE por id_caja,
            //    nunca por ventana de fecha: una venta solo pertenece a esta caja si el
            //    dinero pasó físicamente por ella (M_Venta::registrar la liga al crearla).
            $desglose = $this->calcularDesglosePorMetodo($id_caja);

            $ventas_efectivo = $desglose['1'];
            $ventas_yape     = $desglose['2'];
            // $desglose ya viene sumado por línea de pago real (pagos_venta), así que
            // una venta mixta reparte correctamente entre 1/2/3 — no hace falta fusionar
            // nada con Tarjeta como antes de pago mixto.
            $ventas_tarjeta  = $desglose['3'];
            $total_ventas    = $ventas_efectivo + $ventas_yape + $ventas_tarjeta;

            // Obtener número de ventas de esta caja
            $sql_num = "SELECT COUNT(id_venta) FROM ventas WHERE id_caja = ? AND estado = 1";
            $stmt_num = $this->conexion->prepare($sql_num);
            $stmt_num->execute([$id_caja]);
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
                        fecha_cierre = NOW(),
                        total_ventas = ?, num_ventas = ?,
                        diferencia = ?, dif_efectivo = ?, dif_yape = ?, dif_tarjeta = ?,
                        observaciones = ?, estado = 0
                        WHERE id_caja = ?";

            $stmt_upd = $this->conexion->prepare($sql_upd);
            return $stmt_upd->execute([
                $monto_cierre, $cierre_efectivo, $cierre_yape, $cierre_tarjeta,
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
                    WHERE DATE(c.fecha_apertura) <= ?
                      AND (c.estado = 1 OR DATE(c.fecha_cierre) >= ?)";

            $params = [$fecha, $fecha];

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
     * Calcula dinámicamente las ventas acumuladas en una caja física específica.
     * Se filtra por `id_caja`, no por usuario+fecha: así una venta de un pedido online
     * (que no pertenece a ninguna caja, id_caja=NULL) nunca se cuenta aquí.
     * @param int $id_caja ID de la sesión de caja
     * @return float Sumatoria total de las ventas
     */
    public function calcularVentasAcumuladas($id_caja) {
        try {
            $sql = "SELECT SUM(total) as total_ventas
                    FROM ventas
                    WHERE id_caja = ? AND estado = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_caja]);
            $res = $stmt->fetchColumn();
            return $res ? floatval($res) : 0.00;
        } catch (PDOException $e) {
            return 0.00;
        }
    }

    /**
     * Calcula el desglose de ventas por método de pago de una caja física específica.
     *
     * Suma por LÍNEA de pago (`pagos_venta`), no por venta: una venta pagada mitad
     * efectivo y mitad Yape aporta su monto real a cada balde, en vez de caer entera
     * en uno solo. Las líneas nunca llevan metodo_pago=4 (Mixto es solo la etiqueta
     * resumen de `ventas.metodo_pago`), así que el resultado ya viene repartido entre
     * 1/2/3 sin necesidad de fusionar nada.
     */
    public function calcularDesglosePorMetodo($id_caja) {
        try {
            $sql = "SELECT pv.metodo_pago, SUM(pv.monto) AS monto
                    FROM pagos_venta pv
                    INNER JOIN ventas v ON pv.id_venta = v.id_venta
                    WHERE v.id_caja = ? AND v.estado = 1
                    GROUP BY pv.metodo_pago";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_caja]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = ['1' => 0.0, '2' => 0.0, '3' => 0.0, '4' => 0.0];
            foreach ($rows as $r) {
                $result[(string) $r['metodo_pago']] = floatval($r['monto']);
            }
            return $result;
        } catch (PDOException $e) {
            return ['1' => 0.0, '2' => 0.0, '3' => 0.0, '4' => 0.0];
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

            // 2. Ventas de esta caja física (nunca incluye pedidos online: id_caja=NULL en esos casos)
            $etiquetasMetodo = ['1' => 'Efectivo', '2' => 'Yape/Plin', '3' => 'Tarjeta', '4' => 'Mixto', '5' => 'Transferencia'];
            $stmtVentas = $this->conexion->prepare("
                SELECT v.id_venta, v.fecha AS fecha_venta, v.total, v.metodo_pago,
                       v.tipo_comprobante, v.estado,
                       IFNULL(p.nombres_razon_social, 'Público General') AS nombre_cliente
                FROM ventas v
                LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
                LEFT JOIN personas p ON c.id_persona = p.id_persona
                WHERE v.id_caja = ?
                ORDER BY v.fecha ASC
            ");
            $stmtVentas->execute([$id_caja]);
            $ventas = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ventas as &$v) {
                $v['metodo_pago'] = $etiquetasMetodo[(string) $v['metodo_pago']] ?? 'Otro';
            }
            unset($v);

            // 3. Resumen: total del sistema (ventas) vs. lo declarado por el cajero al cerrar
            $desglose = $this->calcularDesglosePorMetodo($id_caja);
            $sistemaEfectivo = $desglose['1'];
            $sistemaYape     = $desglose['2'];
            // $desglose ya viene sumado por línea de pago real (pagos_venta): una venta
            // mixta reparte correctamente entre 1/2/3, sin fusionar nada con Tarjeta.
            $sistemaTarjeta  = $desglose['3'];

            $declaradoEfectivo = $caja['cierre_efectivo'] !== null ? (float) $caja['cierre_efectivo'] : $sistemaEfectivo;
            $declaradoYape     = $caja['cierre_yape'] !== null ? (float) $caja['cierre_yape'] : $sistemaYape;
            $declaradoTarjeta  = $caja['cierre_tarjeta'] !== null ? (float) $caja['cierre_tarjeta'] : $sistemaTarjeta;

            $resumen = [
                'efectivo' => [
                    'sistema'    => $sistemaEfectivo,
                    'declarado'  => $declaradoEfectivo,
                    'diferencia' => $caja['dif_efectivo'] !== null ? (float) $caja['dif_efectivo'] : ($declaradoEfectivo - $sistemaEfectivo),
                ],
                'yape' => [
                    'sistema'    => $sistemaYape,
                    'declarado'  => $declaradoYape,
                    'diferencia' => $caja['dif_yape'] !== null ? (float) $caja['dif_yape'] : ($declaradoYape - $sistemaYape),
                ],
                'tarjeta' => [
                    'sistema'    => $sistemaTarjeta,
                    'declarado'  => $declaradoTarjeta,
                    'diferencia' => $caja['dif_tarjeta'] !== null ? (float) $caja['dif_tarjeta'] : ($declaradoTarjeta - $sistemaTarjeta),
                ],
            ];

            return ['caja' => $caja, 'ventas' => $ventas, 'resumen' => $resumen];
        } catch (PDOException $e) {
            return null;
        }
    }
}
?>
