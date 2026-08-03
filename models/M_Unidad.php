<?php
// Requerir archivo de conexión centralizada
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Modelo para la gestión de Unidades de Medida
 * Gestiona el listado de unidades disponibles para los artículos e productos.
 */
class M_Unidad {
    // Instancia estática para el patrón Singleton
    private static $instancia = null;
    // Manejador de la conexión PDO
    private $conexion;

    // Constructor privado para evitar instanciación externa
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
     * Lista todas las unidades de medida activas en la base de datos
     * @return array Listado de unidades (ID, Nombre, Abreviatura)
     */
    public function listar() {
        try {
            $sql = "SELECT * FROM unidades_medida WHERE estado = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>
