<?php
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Series y correlativos de comprobantes electrónicos.
 *
 * El correlativo se reserva con SELECT ... FOR UPDATE dentro de la MISMA
 * transacción que registra la venta — mismo patrón de bloqueo pesimista que ya
 * usa M_Venta::registrarEnTransaccion() para el stock. El UNIQUE(serie,
 * correlativo) en `ventas` es la red de seguridad final: si algo se escapara
 * del lock, la BD rechaza el duplicado en vez de dejarlo pasar.
 */
class M_Serie {
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
     * Reserva y devuelve el siguiente correlativo de una serie, dentro de la
     * transacción del llamador. Debe ejecutarse con una transacción ya abierta.
     *
     * @param PDO $conexion Conexión con transacción activa (la del llamador)
     * @param int $tipoComprobante 1=Boleta, 2=Factura, 4=NC de Boleta, 5=NC de Factura
     * @param string|null $serieEspecifica Si se indica, reserva el correlativo de
     *        ESA serie concreta (la elegida en el POS) en vez de la primera activa.
     * @return array ['serie' => string, 'correlativo' => int]
     * @throws Exception si no hay una serie activa para ese tipo de comprobante
     */
    public function reservarSiguiente(PDO $conexion, int $tipoComprobante, ?string $serieEspecifica = null): array {
        $sql = "SELECT id_serie, serie, correlativo_actual FROM series_comprobante
                WHERE tipo_comprobante = ? AND estado = 1";
        $params = [$tipoComprobante];
        if ($serieEspecifica !== null && $serieEspecifica !== '') {
            $sql .= " AND serie = ?";
            $params[] = strtoupper(trim($serieEspecifica));
        }
        $sql .= " ORDER BY id_serie ASC LIMIT 1 FOR UPDATE";
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new Exception('No hay una serie activa configurada para este tipo de comprobante. Configúrala en el módulo de Series.');
        }

        $siguiente = (int) $fila['correlativo_actual'] + 1;
        $conexion->prepare("UPDATE series_comprobante SET correlativo_actual = ? WHERE id_serie = ?")
            ->execute([$siguiente, $fila['id_serie']]);

        return ['serie' => $fila['serie'], 'correlativo' => $siguiente];
    }

    /**
     * Da de alta una serie nueva. `serie` debe ser distinta a las que use
     * cualquier otro emisor del mismo RUC (p.ej. un proveedor externo ya en
     * uso) — SUNAT rechaza un correlativo repetido para la misma serie.
     */
    public function crear(int $tipoComprobante, string $serie, int $correlativoInicial = 0): array {
        try {
            $stmt = $this->conexion->prepare(
                "INSERT INTO series_comprobante (tipo_comprobante, serie, correlativo_actual, estado) VALUES (?, ?, ?, 1)"
            );
            $stmt->execute([$tipoComprobante, strtoupper(trim($serie)), $correlativoInicial]);
            return ['ok' => true, 'id_serie' => (int) $this->conexion->lastInsertId()];
        } catch (PDOException $e) {
            $duplicada = strpos($e->getMessage(), 'uk_tipo_serie') !== false;
            return ['ok' => false, 'mensaje' => $duplicada ? 'Ya existe una serie con ese tipo y nombre.' : $e->getMessage()];
        }
    }

    public function listar(): array {
        try {
            $stmt = $this->conexion->query(
                "SELECT id_serie, tipo_comprobante, serie, correlativo_actual, estado FROM series_comprobante ORDER BY tipo_comprobante, serie"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function cambiarEstado(int $idSerie, bool $activa): bool {
        try {
            $this->conexion->prepare("UPDATE series_comprobante SET estado = ? WHERE id_serie = ?")
                ->execute([$activa ? 1 : 0, $idSerie]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
