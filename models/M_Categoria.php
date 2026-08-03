<?php
// Requerir archivo de conexión y la clase entidad correspondiente
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Categoria.php';

/**
 * Modelo para la gestión de Categorías de Productos
 * Permite registrar, actualizar, listar y suspender categorías.
 */
class M_Categoria {
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
     * Registra una nueva categoría en la base de datos
     * @param Categoria $categoria Entidad que contiene el nombre y descripción
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function registrar(Categoria $categoria) {
        try {
            $sql = "INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, 1)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$categoria->nombre, $categoria->descripcion]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtiene el listado de todas las categorías activas
     * @return array Listado asociativo de categorías
     */
    public function listar() {
        try {
            $sql = "SELECT * FROM categorias WHERE estado = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtiene la información de una categoría específica por su ID
     * @param int $id_categoria ID único de la categoría a consultar
     * @return array|false Fila de base de datos en caso de éxito, False en caso de error
     */
    public function obtenerPorId($id_categoria) {
        try {
            $sql = "SELECT * FROM categorias WHERE id_categoria = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_categoria]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Actualiza el nombre y la descripción de una categoría
     * @param Categoria $categoria Entidad Categoria con los nuevos datos
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function actualizar(Categoria $categoria) {
        try {
            $sql = "UPDATE categorias SET nombre = ?, descripcion = ? WHERE id_categoria = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                $categoria->nombre, 
                $categoria->descripcion, 
                $categoria->id_categoria
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Realiza un borrado lógico de una categoría estableciendo su estado en 0
     * @param int $id_categoria ID de la categoría a suspender
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function eliminar($id_categoria) {
        try {
            $sql = "UPDATE categorias SET estado = 0 WHERE id_categoria = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_categoria]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>