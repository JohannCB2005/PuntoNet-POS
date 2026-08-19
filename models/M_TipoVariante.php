<?php
// Requerir archivo de conexión
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Modelo para la gestión de Tipos de Variante.
 *
 * Los tipos de sistema (Talla, Corbata, Bimestre, Libre) no se pueden eliminar;
 * sus opciones viven en sus propias tablas (tallas, tipos_corbata, bimestres).
 * Los tipos personalizados guardan sus opciones en `opciones_variante` y su
 * codigo es 'v{id}'. En productos, los hijos de un tipo personalizado guardan
 * el nombre de la opción en `nombre_variante`.
 */
class M_TipoVariante {
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

    /**
     * Lista todos los tipos (activos e inactivos) con el conteo de opciones.
     */
    public function listar() {
        try {
            $sql = "SELECT tv.id_tipo_variante, tv.nombre, tv.codigo, tv.modo, tv.tipo, tv.estado, tv.orden,
                    (SELECT COUNT(*) FROM opciones_variante ov WHERE ov.id_tipo_variante = tv.id_tipo_variante) AS num_opciones
                    FROM tipos_variante tv
                    ORDER BY tv.tipo ASC, tv.orden ASC, tv.id_tipo_variante ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Lista los tipos activos para el combobox de la modal de producto,
     * incluyendo las opciones de los tipos personalizados.
     */
    public function listarActivos() {
        try {
            $sql = "SELECT id_tipo_variante, nombre, codigo, modo, tipo FROM tipos_variante
                    WHERE estado = 1 ORDER BY tipo ASC, orden ASC, id_tipo_variante ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tipos as &$t) {
                if ($t['tipo'] === 'personalizado') {
                    $t['opciones'] = $this->obtenerOpciones((int) $t['id_tipo_variante']);
                }
            }
            return $tipos;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function obtenerOpciones(int $id_tipo_variante): array {
        try {
            $sql = "SELECT id_opcion, nombre FROM opciones_variante
                    WHERE id_tipo_variante = ? ORDER BY orden ASC, id_opcion ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_tipo_variante]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Crea un tipo personalizado (codigo 'v{id}') y sus opciones si es 'lista'.
     */
    public function registrar(string $nombre, string $modo, array $opciones): array {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'El nombre es obligatorio.'];
        }
        try {
            $this->conexion->beginTransaction();
            $stmt = $this->conexion->prepare(
                "INSERT INTO tipos_variante (nombre, codigo, modo, tipo, estado, orden) VALUES (?, '', ?, 'personalizado', 1, 0)"
            );
            $stmt->execute([$nombre, $modo === 'libre' ? 'libre' : 'lista']);
            $id = (int) $this->conexion->lastInsertId();
            $codigo = 'v' . $id;
            $this->conexion->prepare("UPDATE tipos_variante SET codigo = ? WHERE id_tipo_variante = ?")
                ->execute([$codigo, $id]);
            if ($modo === 'lista') {
                $this->reemplazarOpciones($id, $opciones);
            }
            $this->conexion->commit();
            return ['ok' => true, 'mensaje' => 'Tipo de variante creado correctamente.'];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => 'Error al crear el tipo de variante.'];
        }
    }

    /**
     * Actualiza nombre/modo de un tipo personalizado y reemplaza sus opciones.
     */
    public function actualizar(int $id, string $nombre, string $modo, array $opciones): array {
        $nombre = trim($nombre);
        if ($id <= 0 || $nombre === '') {
            return ['ok' => false, 'mensaje' => 'Datos inválidos.'];
        }
        try {
            $this->conexion->beginTransaction();
            $this->conexion->prepare("UPDATE tipos_variante SET nombre = ?, modo = ? WHERE id_tipo_variante = ?")
                ->execute([$nombre, $modo === 'libre' ? 'libre' : 'lista', $id]);
            if ($modo === 'lista') {
                $this->reemplazarOpciones($id, $opciones);
            } else {
                $this->conexion->prepare("DELETE FROM opciones_variante WHERE id_tipo_variante = ?")->execute([$id]);
            }
            $this->conexion->commit();
            return ['ok' => true, 'mensaje' => 'Tipo de variante actualizado correctamente.'];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => 'Error al actualizar el tipo de variante.'];
        }
    }

    /**
     * Elimina (lógicamente) un tipo personalizado que no esté en uso.
     */
    public function eliminar(int $id): array {
        $tipo = $this->obtenerPorId($id);
        if (!$tipo) {
            return ['ok' => false, 'mensaje' => 'Tipo de variante no encontrado.'];
        }
        if ($tipo['tipo'] === 'sistema') {
            return ['ok' => false, 'mensaje' => 'Los tipos de sistema (Talla, Corbata, Bimestre, Libre) no se pueden eliminar.'];
        }
        $usado = $this->usadoEnProductos($id);
        if ($usado > 0) {
            return ['ok' => false, 'mensaje' => "No se puede eliminar: $usado producto(s) lo usan."];
        }
        try {
            $this->conexion->prepare("UPDATE tipos_variante SET estado = 0 WHERE id_tipo_variante = ?")->execute([$id]);
            return ['ok' => true, 'mensaje' => 'Tipo de variante eliminado.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al eliminar el tipo de variante.'];
        }
    }

    public function toggleEstado(int $id): array {
        try {
            $this->conexion->prepare("UPDATE tipos_variante SET estado = IF(estado = 1, 0, 1) WHERE id_tipo_variante = ?")
                ->execute([$id]);
            return ['ok' => true, 'mensaje' => 'Estado actualizado.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al actualizar el estado.'];
        }
    }

    /**
     * Renombra una opción individual de un tipo personalizado.
     */
    public function actualizarOpcion(int $id_opcion, string $nombre): array {
        $nombre = trim($nombre);
        if ($id_opcion <= 0 || $nombre === '') {
            return ['ok' => false, 'mensaje' => 'Datos inválidos.'];
        }
        try {
            $stmt = $this->conexion->prepare("UPDATE opciones_variante SET nombre = ? WHERE id_opcion = ?");
            $stmt->execute([$nombre, $id_opcion]);
            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'mensaje' => 'Opción no encontrada.'];
            }
            return ['ok' => true, 'mensaje' => 'Opción actualizada correctamente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al actualizar la opción.'];
        }
    }

    /**
     * Elimina una opción individual de un tipo personalizado.
     * Los productos que ya la usan conservan el valor guardado en nombre_variante.
     */
    public function eliminarOpcion(int $id_opcion): array {
        if ($id_opcion <= 0) {
            return ['ok' => false, 'mensaje' => 'Datos inválidos.'];
        }
        try {
            $stmt = $this->conexion->prepare("DELETE FROM opciones_variante WHERE id_opcion = ?");
            $stmt->execute([$id_opcion]);
            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'mensaje' => 'Opción no encontrada.'];
            }
            return ['ok' => true, 'mensaje' => 'Opción eliminada correctamente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al eliminar la opción.'];
        }
    }

    /**
     * Tablas de origen de las opciones de los tipos de sistema.
     */
    private const TABLAS_SISTEMA = [
        'talla'    => ['tabla' => 'tallas',        'col_id' => 'id_talla',        'col_nombre' => 'nombre'],
        'corbata'  => ['tabla' => 'tipos_corbata', 'col_id' => 'id_tipo_corbata', 'col_nombre' => 'nombre'],
        'bimestre' => ['tabla' => 'bimestres',     'col_id' => 'id_bimestre',     'col_nombre' => 'nombre'],
    ];

    /**
     * Lista las opciones de un tipo de sistema (tallas, corbatas, bimestres).
     */
    public function obtenerOpcionesSistema(string $codigo): array {
        if (!isset(self::TABLAS_SISTEMA[$codigo])) {
            return [];
        }
        $t = self::TABLAS_SISTEMA[$codigo];
        try {
            $stmt = $this->conexion->prepare(
                "SELECT {$t['col_id']} AS id_opcion, {$t['col_nombre']} AS nombre FROM {$t['tabla']} ORDER BY {$t['col_id']} ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Renombra una opción de un tipo de sistema (tallas, corbatas, bimestres).
     */
    public function actualizarOpcionSistema(string $codigo, int $id, string $nombre): array {
        if (!isset(self::TABLAS_SISTEMA[$codigo])) {
            return ['ok' => false, 'mensaje' => 'Tipo de variante no válido.'];
        }
        $t = self::TABLAS_SISTEMA[$codigo];
        $nombre = trim($nombre);
        if ($id <= 0 || $nombre === '') {
            return ['ok' => false, 'mensaje' => 'Datos inválidos.'];
        }
        try {
            $stmt = $this->conexion->prepare("UPDATE {$t['tabla']} SET {$t['col_nombre']} = ? WHERE {$t['col_id']} = ?");
            $stmt->execute([$nombre, $id]);
            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'mensaje' => 'Opción no encontrada.'];
            }
            return ['ok' => true, 'mensaje' => 'Opción actualizada correctamente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al actualizar la opción.'];
        }
    }

    /**
     * Elimina una opción de un tipo de sistema si ningún producto la usa.
     */
    public function eliminarOpcionSistema(string $codigo, int $id): array {
        if (!isset(self::TABLAS_SISTEMA[$codigo])) {
            return ['ok' => false, 'mensaje' => 'Tipo de variante no válido.'];
        }
        $t = self::TABLAS_SISTEMA[$codigo];
        $usados = $this->usadoEnProductosPorColumna($t['col_id'], $id);
        if ($usados > 0) {
            return ['ok' => false, 'mensaje' => "No se puede eliminar: $usados producto(s) lo usan."];
        }
        try {
            $stmt = $this->conexion->prepare("DELETE FROM {$t['tabla']} WHERE {$t['col_id']} = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'mensaje' => 'Opción no encontrada.'];
            }
            return ['ok' => true, 'mensaje' => 'Opción eliminada correctamente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al eliminar la opción.'];
        }
    }

    /**
     * Cuenta productos que referencian una opción de sistema por columna.
     */
    public function usadoEnProductosPorColumna(string $columna, int $id): int {
        $permitidas = ['id_talla', 'id_tipo_corbata', 'id_bimestre'];
        if (!in_array($columna, $permitidas, true)) {
            return 0;
        }
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM productos WHERE $columna = ?");
            $stmt->execute([$id]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function obtenerPorId(int $id) {
        try {
            $stmt = $this->conexion->prepare("SELECT * FROM tipos_variante WHERE id_tipo_variante = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function usadoEnProductos(int $id): int {
        try {
            $codigo = 'v' . $id;
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM productos WHERE tipo_variante = ?");
            $stmt->execute([$codigo]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    private function reemplazarOpciones(int $id, array $opciones): void {
        $this->conexion->prepare("DELETE FROM opciones_variante WHERE id_tipo_variante = ?")->execute([$id]);
        $stmt = $this->conexion->prepare(
            "INSERT INTO opciones_variante (id_tipo_variante, nombre, orden) VALUES (?, ?, ?)"
        );
        $orden = 0;
        foreach ($opciones as $opc) {
            $nombre = trim((string) ($opc['nombre'] ?? $opc ?? ''));
            if ($nombre === '') continue;
            $stmt->execute([$id, $nombre, $orden++]);
        }
    }
}