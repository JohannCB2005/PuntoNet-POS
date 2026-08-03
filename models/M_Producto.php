<?php
// Cargar la conexión y la entidad del Producto
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Producto.php';

/**
 * Modelo para la gestión del catálogo de Productos
 * Permite registrar, listar, editar y deshabilitar artículos del catálogo.
 */
class M_Producto {
    // Instancia única Singleton
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
     * Registra un nuevo producto/artículo en el inventario con estado activo (1)
     * @param Producto $producto Entidad producto con datos de categoría, unidad, precio y stock inicial
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function registrar(Producto $producto) {
        try {
            $sql = "INSERT INTO productos (id_categoria, id_unidad, nombre, precio_unitario, costo_produccion,
                    stock_piezas, estado, imagen, id_talla, id_tipo_corbata, id_nivel, id_grado,
                    id_area, id_bimestre, es_agrupador, id_producto_padre)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                $producto->id_categoria,
                $producto->id_unidad,
                $producto->nombre,
                $producto->precio_unitario,
                $producto->costo_produccion,
                $producto->stock_piezas,
                $producto->imagen,
                $producto->id_talla,
                $producto->id_tipo_corbata,
                $producto->id_nivel,
                $producto->id_grado,
                $producto->id_area,
                $producto->id_bimestre,
                $producto->es_agrupador ?? 0,
                $producto->id_producto_padre ?? null,
            ]);

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Lista todos los productos activos en el inventario
     * Utiliza un doble INNER JOIN para obtener los nombres textuales de la categoría y la unidad de medida.
     * @return array Listado asociativo con detalles del producto
     */
    public function listar() {
        try {
            $sql = "SELECT i.id_producto, i.id_categoria, i.id_unidad, c.nombre AS categoria,
                    u.nombre AS unidad, u.abreviatura, i.nombre, i.precio_unitario, i.costo_produccion,
                    i.stock_piezas, i.estado, i.imagen,
                    i.id_talla, i.id_tipo_corbata, i.id_nivel, i.id_grado, i.id_area, i.id_bimestre,
                    i.es_agrupador, i.id_producto_padre,
                    t.nombre AS talla, t.orden AS talla_orden,
                    tc.nombre AS tipo_corbata, n.nombre AS nivel, g.nombre AS grado,
                    a.nombre AS area, b.nombre AS bimestre
                    FROM productos i
                    INNER JOIN categorias c ON i.id_categoria = c.id_categoria
                    INNER JOIN unidades_medida u ON i.id_unidad = u.id_unidad
                    LEFT JOIN tallas t ON i.id_talla = t.id_talla
                    LEFT JOIN tipos_corbata tc ON i.id_tipo_corbata = tc.id_tipo_corbata
                    LEFT JOIN niveles_educativos n ON i.id_nivel = n.id_nivel
                    LEFT JOIN grados g ON i.id_grado = g.id_grado
                    LEFT JOIN areas_cursos a ON i.id_area = a.id_area
                    LEFT JOIN bimestres b ON i.id_bimestre = b.id_bimestre
                    WHERE i.estado = 1
                    ORDER BY COALESCE(i.id_producto_padre, i.id_producto) DESC, i.es_agrupador DESC, t.orden ASC, i.nombre ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtiene la fila de base de datos de un producto específico según su ID
     * @param int $id_producto ID del producto a consultar
     * @return array|false Fila de base de datos o False si ocurre un error
     */
    public function obtenerPorId($id_producto) {
        try {
            $sql = "SELECT * FROM productos WHERE id_producto = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_producto]);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Actualiza la información de un producto (categoría, unidad de medida, nombre, precio y stock)
     * @param Producto $producto Entidad Producto con los nuevos datos cargados
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function actualizar(Producto $producto) {
        try {
            $sql = "UPDATE productos SET id_categoria = ?, id_unidad = ?, nombre = ?,
                    precio_unitario = ?, costo_produccion = ?, stock_piezas = ?,
                    imagen = COALESCE(?, imagen), id_talla = ?, id_tipo_corbata = ?,
                    id_nivel = ?, id_grado = ?, id_area = ?, id_bimestre = ?
                    WHERE id_producto = ?";
            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                $producto->id_categoria,
                $producto->id_unidad,
                $producto->nombre,
                $producto->precio_unitario,
                $producto->costo_produccion,
                $producto->stock_piezas,
                $producto->imagen,
                $producto->id_talla,
                $producto->id_tipo_corbata,
                $producto->id_nivel,
                $producto->id_grado,
                $producto->id_area,
                $producto->id_bimestre,
                $producto->id_producto
            ]);

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Crea un producto padre con sus variantes de talla en una transacción atómica.
     * @param array $datosPadre  [id_categoria, id_unidad, nombre, imagen]
     * @param array $variantes   Array de [id_talla, precio_unitario, costo_produccion, stock_piezas]
     * @return array ['ok' => bool, 'id_padre' => int|null, 'mensaje' => string]
     */
    public function registrarConVariantes(array $datosPadre, array $variantes): array {
        try {
            $this->conexion->beginTransaction();

            // 1. Crear el producto padre (es_agrupador=1, stock=0, precio=0)
            $sqlPadre = "INSERT INTO productos
                         (id_categoria, id_unidad, nombre, precio_unitario, costo_produccion,
                          stock_piezas, estado, imagen, es_agrupador)
                         VALUES (?, ?, ?, 0, 0, 0, 1, ?, 1)";
            $stmtP = $this->conexion->prepare($sqlPadre);
            $stmtP->execute([
                $datosPadre['id_categoria'],
                $datosPadre['id_unidad'],
                $datosPadre['nombre'],
                $datosPadre['imagen'] ?? null,
            ]);
            $id_padre = (int) $this->conexion->lastInsertId();

            // 2. Crear cada variante como hijo del padre
            $sqlHijo = "INSERT INTO productos
                        (id_categoria, id_unidad, nombre, precio_unitario, costo_produccion,
                         stock_piezas, estado, imagen, id_talla, es_agrupador, id_producto_padre)
                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 0, ?)";
            $stmtH = $this->conexion->prepare($sqlHijo);

            foreach ($variantes as $v) {
                $stmtH->execute([
                    $datosPadre['id_categoria'],
                    $datosPadre['id_unidad'],
                    $datosPadre['nombre'],
                    floatval($v['precio_unitario']),
                    floatval($v['costo_produccion']),
                    floatval($v['stock_piezas']),
                    $datosPadre['imagen'] ?? null,
                    intval($v['id_talla']),
                    $id_padre,
                ]);
            }

            $this->conexion->commit();
            return ['ok' => true, 'id_padre' => $id_padre, 'mensaje' => 'Producto con variantes creado correctamente.'];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'id_padre' => null, 'mensaje' => 'Error al crear el producto: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene las variantes (hijos) de un producto padre, ordenadas por talla.
     * @param int $id_padre ID del producto padre (es_agrupador=1)
     * @return array
     */
    public function obtenerVariantes(int $id_padre): array {
        try {
            $sql = "SELECT i.id_producto, i.nombre, i.precio_unitario, i.costo_produccion,
                           i.stock_piezas, i.imagen, i.id_talla,
                           t.nombre AS talla, t.orden AS talla_orden
                    FROM productos i
                    LEFT JOIN tallas t ON i.id_talla = t.id_talla
                    WHERE i.id_producto_padre = ? AND i.estado = 1
                    ORDER BY t.orden ASC, t.nombre ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_padre]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Actualiza el stock de un producto (suma algebraica)
     * @param int $id_producto ID del producto
     * @param float $variacion Cantidad a sumar (positiva o negativa)
     * @return bool True en caso de éxito
     */
    public function actualizarStockRapido($id_producto, $variacion) {
        try {
            $sql = "UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$variacion, $id_producto]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Actualiza un producto padre y sus variantes de talla en una transacción atómica.
     */
    public function actualizarConVariantes(int $id_padre, array $datosPadre, array $variantes): array {
        try {
            $this->conexion->beginTransaction();

            // 1. Actualizar el producto padre
            $sqlPadre = "UPDATE productos SET id_categoria = ?, id_unidad = ?, nombre = ?";
            $paramsPadre = [$datosPadre['id_categoria'], $datosPadre['id_unidad'], $datosPadre['nombre']];
            if (isset($datosPadre['imagen']) && $datosPadre['imagen'] !== null) {
                $sqlPadre .= ", imagen = ?";
                $paramsPadre[] = $datosPadre['imagen'];
            }
            $sqlPadre .= " WHERE id_producto = ? AND es_agrupador = 1";
            $paramsPadre[] = $id_padre;
            
            $stmtP = $this->conexion->prepare($sqlPadre);
            $stmtP->execute($paramsPadre);

            // 2. Obtener los hijos actuales
            $stmtActuales = $this->conexion->prepare("SELECT id_producto FROM productos WHERE id_producto_padre = ?");
            $stmtActuales->execute([$id_padre]);
            $hijosActuales = $stmtActuales->fetchAll(PDO::FETCH_COLUMN);

            $hijosMantenidos = [];

            // 3. Procesar variantes enviadas
            $sqlInsertHijo = "INSERT INTO productos (id_categoria, id_unidad, nombre, precio_unitario, costo_produccion, stock_piezas, estado, id_talla, es_agrupador, id_producto_padre) VALUES (?, ?, ?, ?, ?, ?, 1, ?, 0, ?)";
            $stmtInsertHijo = $this->conexion->prepare($sqlInsertHijo);
            
            $sqlUpdateHijo = "UPDATE productos SET id_categoria = ?, id_unidad = ?, nombre = ?, precio_unitario = ?, costo_produccion = ?, id_talla = ? WHERE id_producto = ? AND id_producto_padre = ?";
            $stmtUpdateHijo = $this->conexion->prepare($sqlUpdateHijo);

            foreach ($variantes as $v) {
                if (!empty($v['id_producto']) && $v['id_producto'] > 0) {
                    // Update existing
                    $stmtUpdateHijo->execute([
                        $datosPadre['id_categoria'],
                        $datosPadre['id_unidad'],
                        $datosPadre['nombre'],
                        $v['precio_unitario'],
                        $v['costo_produccion'],
                        $v['id_talla'],
                        $v['id_producto'],
                        $id_padre
                    ]);
                    $hijosMantenidos[] = $v['id_producto'];
                } else {
                    // Insert new
                    $stmtInsertHijo->execute([
                        $datosPadre['id_categoria'],
                        $datosPadre['id_unidad'],
                        $datosPadre['nombre'],
                        $v['precio_unitario'],
                        $v['costo_produccion'],
                        $v['stock_piezas'] ?? 0,
                        $v['id_talla'],
                        $id_padre
                    ]);
                }
            }

            // 4. Eliminar variantes que ya no están
            $hijosEliminar = array_diff($hijosActuales, $hijosMantenidos);
            if (!empty($hijosEliminar)) {
                $inQuery = implode(',', array_fill(0, count($hijosEliminar), '?'));
                try {
                    $stmtDel = $this->conexion->prepare("DELETE FROM productos WHERE id_producto IN ($inQuery)");
                    $stmtDel->execute(array_values($hijosEliminar));
                } catch (PDOException $ex) {
                    // Si falla por FK constraints, simplemente desactivamos el producto.
                    $stmtInactivar = $this->conexion->prepare("UPDATE productos SET estado = 0 WHERE id_producto IN ($inQuery)");
                    $stmtInactivar->execute(array_values($hijosEliminar));
                }
            }

            $this->conexion->commit();
            return ['ok' => true, 'mensaje' => 'Familia de productos actualizada correctamente.'];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => 'Error al actualizar familia: ' . $e->getMessage()];
        }
    }

    /**
     * "Elimina" lógicamente un producto (Cambia su estado a inactivo = 0)
     * @param int $id_producto ID del producto a deshabilitar
     * @return bool True si tuvo éxito
     */
    public function eliminar($id_producto) {
        try {
            $sql = "UPDATE productos SET estado = 0 WHERE id_producto = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_producto]);
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Métodos para cargar opciones de formulario NISSI
    public function obtenerTallas() {
        return $this->conexion->query("SELECT * FROM tallas ORDER BY orden")->fetchAll();
    }
    public function obtenerTiposCorbata() {
        return $this->conexion->query("SELECT * FROM tipos_corbata")->fetchAll();
    }
    public function obtenerNiveles() {
        return $this->conexion->query("SELECT * FROM niveles_educativos WHERE estado = 1")->fetchAll();
    }
    public function obtenerGrados() {
        return $this->conexion->query("SELECT * FROM grados WHERE estado = 1")->fetchAll();
    }
    public function obtenerAreas() {
        return $this->conexion->query("SELECT * FROM areas_cursos WHERE estado = 1")->fetchAll();
    }
    public function obtenerBimestres() {
        return $this->conexion->query("SELECT * FROM bimestres")->fetchAll();
    }
}
?>