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
     * @param array $categorias  IDs de categorías (etiquetas). La primaria (id_categoria) se incluye siempre.
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function registrar(Producto $producto, array $categorias = []) {
        try {
            $sql = "INSERT INTO productos (id_categoria, id_unidad, nombre, precio_unitario, costo_produccion, comision,
                    stock_piezas, stock_ilimitado, estado, imagen, id_talla, id_tipo_corbata, id_nivel, id_grado,
                    id_area, id_bimestre, es_agrupador, id_producto_padre, tipo_variante, nombre_variante)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                $producto->id_categoria,
                $producto->id_unidad,
                $producto->nombre,
                $producto->precio_unitario,
                $producto->costo_produccion,
                $producto->comision,
                $producto->stock_piezas,
                $producto->stock_ilimitado ?? 0,
                $producto->imagen,
                $producto->id_talla,
                $producto->id_tipo_corbata,
                $producto->id_nivel,
                $producto->id_grado,
                $producto->id_area,
                $producto->id_bimestre,
                $producto->es_agrupador ?? 0,
                $producto->id_producto_padre ?? null,
                $producto->tipo_variante ?? null,
                $producto->nombre_variante ?? null,
            ]);

            $this->sincronizarCategorias((int) $this->conexion->lastInsertId(), $categorias, (int) $producto->id_categoria);

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Precio de catálogo vigente de un conjunto de productos, indexado por id.
     *
     * El POS y las separaciones reciben el carrito desde el navegador, precio
     * incluido. Ese precio NO puede ser la fuente de verdad: quien controle la
     * petición puede registrar una prenda de S/200 a S/1 y el kardex, los reportes
     * y el comprobante electrónico lo darían por bueno, sin rastro contra el precio
     * de lista. Los controladores reescriben el precio con esto antes de construir
     * la venta — mismo criterio que ya usa el checkout público en
     * M_Ecommerce::calcularCarrito().
     *
     * No aplica a la conversión de cotizaciones: ahí el precio viene congelado de
     * detalle_cotizaciones (la cotización respeta el precio que se cotizó), y ese
     * dato ya está dentro del sistema, no lo manda el navegador.
     *
     * @param int[] $ids
     * @return array<int,float> id_producto => precio_unitario
     */
    public function obtenerPreciosVigentes(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (empty($ids)) {
            return [];
        }
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->conexion->prepare(
                "SELECT id_producto, precio_unitario FROM productos WHERE id_producto IN ($placeholders)"
            );
            $stmt->execute($ids);
            $precios = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $precios[(int) $fila['id_producto']] = (float) $fila['precio_unitario'];
            }
            return $precios;
        } catch (PDOException $e) {
            return [];
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
                    u.nombre AS unidad, u.abreviatura, i.nombre, i.precio_unitario, i.costo_produccion, i.comision,
                    i.stock_piezas, i.stock_ilimitado, i.estado, i.imagen,
                    i.id_talla, i.id_tipo_corbata, i.id_nivel, i.id_grado, i.id_area, i.id_bimestre,
                    i.es_agrupador, i.id_producto_padre, i.tipo_variante, i.nombre_variante,
                    COALESCE(t.nombre, tc.nombre, b.nombre, i.nombre_variante) AS etiqueta_variante,
                    (SELECT GROUP_CONCAT(pc.id_categoria ORDER BY pc.id_categoria)
                       FROM producto_categorias pc WHERE pc.id_producto = i.id_producto) AS categorias_ids,
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
                    ORDER BY COALESCE(i.id_producto_padre, i.id_producto) DESC, i.es_agrupador DESC, i.tipo_variante ASC, i.nombre ASC";

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
     * @param array $categorias  IDs de categorías (etiquetas). La primaria (id_categoria) se incluye siempre.
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function actualizar(Producto $producto, array $categorias = []) {
        try {
            $sql = "UPDATE productos SET id_categoria = ?, id_unidad = ?, nombre = ?,
                    precio_unitario = ?, costo_produccion = ?, comision = ?, stock_piezas = ?,
                    stock_ilimitado = ?, imagen = COALESCE(?, imagen), id_talla = ?, id_tipo_corbata = ?,
                    id_nivel = COALESCE(NULLIF(?, ''), id_nivel), id_grado = COALESCE(NULLIF(?, ''), id_grado), id_area = COALESCE(NULLIF(?, ''), id_area), id_bimestre = COALESCE(NULLIF(?, ''), id_bimestre),
                    tipo_variante = ?, nombre_variante = ?
                    WHERE id_producto = ?";
            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                $producto->id_categoria,
                $producto->id_unidad,
                $producto->nombre,
                $producto->precio_unitario,
                $producto->costo_produccion,
                $producto->comision,
                $producto->stock_piezas,
                $producto->stock_ilimitado ?? 0,
                $producto->imagen,
                $producto->id_talla,
                $producto->id_tipo_corbata,
                $producto->id_nivel,
                $producto->id_grado,
                $producto->id_area,
                $producto->id_bimestre,
                $producto->tipo_variante ?? null,
                $producto->nombre_variante ?? null,
                $producto->id_producto
            ]);

            $this->sincronizarCategorias((int) $producto->id_producto, $categorias, (int) $producto->id_categoria);

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Deja en la tabla pivote exactamente las categorías indicadas (siempre más la primaria).
     * @param int $id_producto
     * @param array $ids IDs extra de categorías
     * @param int $id_primaria
     */
    private function sincronizarCategorias(int $id_producto, array $ids, int $id_primaria) {
        try {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
            $ids[] = $id_primaria;
            $ids = array_values(array_unique($ids));

            $stmtDel = $this->conexion->prepare("DELETE FROM producto_categorias WHERE id_producto = ?");
            $stmtDel->execute([$id_producto]);

            $stmtIns = $this->conexion->prepare("INSERT IGNORE INTO producto_categorias (id_producto, id_categoria) VALUES (?, ?)");
            foreach ($ids as $idc) {
                $stmtIns->execute([$id_producto, $idc]);
            }
        } catch (PDOException $e) {
            // No romper la operación principal si el pivote falla.
        }
    }

    /**
     * Categorías (ids) de un producto.
     * @param int $id_producto
     * @return array
     */
    public function obtenerCategoriasProducto(int $id_producto): array {
        try {
            $stmt = $this->conexion->prepare("SELECT id_categoria FROM producto_categorias WHERE id_producto = ?");
            $stmt->execute([$id_producto]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Crea un producto padre con sus variantes en una transacción atómica.
     *
     * Soporta los 4 tipos de sistema ('talla', 'corbata', 'bimestre', 'libre')
     * y los tipos personalizados (código 'v{id}'). Para estos últimos y para
     * 'libre', la etiqueta/opción se guarda en nombre_variante.
     *
     * @param array $datosPadre  [id_categoria, id_unidad, nombre, imagen, id_nivel, id_grado, id_area]
     * @param array $variantes   Array de [id_dimension, nombre_variante, precio_unitario, costo_produccion, comision, stock_piezas]
     * @param string $tipo_variante talla|corbata|bimestre|libre
     * @param array $categorias  IDs de categorías (etiquetas) del padre y sus hijos
     * @return array ['ok' => bool, 'id_padre' => int|null, 'mensaje' => string]
     */
    public function registrarConVariantes(array $datosPadre, array $variantes, string $tipo_variante = 'talla', array $categorias = []): array {
        $dimensionCol = $this->columnaDimension($tipo_variante);
        try {
            $this->conexion->beginTransaction();

            // 1. Crear el producto padre (es_agrupador=1, stock=0, precio=0)
            $sqlPadre = "INSERT INTO productos
                         (id_categoria, id_unidad, nombre, precio_unitario, costo_produccion,
                          stock_piezas, estado, imagen, es_agrupador, tipo_variante,
                          id_nivel, id_grado, id_area)
                         VALUES (?, ?, ?, 0, 0, 0, 1, ?, 1, ?, ?, ?, ?)";
            $stmtP = $this->conexion->prepare($sqlPadre);
            $stmtP->execute([
                $datosPadre['id_categoria'],
                $datosPadre['id_unidad'],
                $datosPadre['nombre'],
                $datosPadre['imagen'] ?? null,
                $tipo_variante,
                $datosPadre['id_nivel'] ?? null,
                $datosPadre['id_grado'] ?? null,
                $datosPadre['id_area'] ?? null,
            ]);
            $id_padre = (int) $this->conexion->lastInsertId();

            // 2. Crear cada variante como hijo del padre (columnas dinámicas según tipo)
            $colsHijo = "id_categoria, id_unidad, nombre, precio_unitario, costo_produccion, comision,
                         stock_piezas, estado, imagen";
            $phsHijo  = "?, ?, ?, ?, ?, ?, ?, 1, ?";
            $argsCol  = [];
            if ($dimensionCol && $dimensionCol !== 'nombre_variante') {
                $colsHijo .= ", $dimensionCol";
                $phsHijo  .= ", ?";
                $argsCol[] = 'dimension';
            }
            if ($dimensionCol === 'nombre_variante') {
                $colsHijo .= ", nombre_variante";
                $phsHijo  .= ", ?";
                $argsCol[] = 'libre';
            }
            $colsHijo .= ", id_nivel, id_grado, id_area, es_agrupador, id_producto_padre, tipo_variante";
            $phsHijo  .= ", ?, ?, ?, 0, ?, ?";

            $sqlHijo = "INSERT INTO productos ($colsHijo) VALUES ($phsHijo)";
            $stmtH = $this->conexion->prepare($sqlHijo);

            foreach ($variantes as $v) {
                $params = [
                    $datosPadre['id_categoria'],
                    $datosPadre['id_unidad'],
                    $datosPadre['nombre'],
                    floatval($v['precio_unitario']),
                    floatval($v['costo_produccion']),
                    floatval($v['comision'] ?? 0),
                    floatval($v['stock_piezas']),
                    $datosPadre['imagen'] ?? null,
                ];
                foreach ($argsCol as $col) {
                    $params[] = $col === 'dimension'
                        ? (intval($v['id_dimension'] ?? 0) ?: null)
                        : trim($v['nombre_variante'] ?? '');
                }
                $params[] = $datosPadre['id_nivel'] ?? null;
                $params[] = $datosPadre['id_grado'] ?? null;
                $params[] = $datosPadre['id_area'] ?? null;
                $params[] = $id_padre;
                $params[] = $tipo_variante;
                $stmtH->execute($params);
            }

            // 3. Pivote de categorías (padre + hijos)
            $idsCats = $this->categoriasCompletas($datosPadre['id_categoria'], $categorias);
            foreach (array_merge([$id_padre], $this->hijosDe($id_padre)) as $idProd) {
                $this->sincronizarCategorias($idProd, $idsCats, (int) $datosPadre['id_categoria']);
            }

            $this->conexion->commit();
            return ['ok' => true, 'id_padre' => $id_padre, 'mensaje' => 'Producto con variantes creado correctamente.'];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return ['ok' => false, 'id_padre' => null, 'mensaje' => 'Error al crear el producto: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene las variantes (hijos) de un producto padre, ordenadas por tipo de variante.
     * @param int $id_padre ID del producto padre (es_agrupador=1)
     * @return array
     */
    public function obtenerVariantes(int $id_padre): array {
        try {
            $sql = "SELECT i.id_producto, i.nombre, i.precio_unitario, i.costo_produccion, i.comision,
                           i.stock_piezas, i.imagen, i.id_talla, i.id_tipo_corbata, i.id_bimestre,
                           i.tipo_variante, i.nombre_variante,
                           COALESCE(t.nombre, tc.nombre, b.nombre, i.nombre_variante) AS etiqueta_variante,
                           t.orden AS talla_orden
                    FROM productos i
                    LEFT JOIN tallas t ON i.id_talla = t.id_talla
                    LEFT JOIN tipos_corbata tc ON i.id_tipo_corbata = tc.id_tipo_corbata
                    LEFT JOIN bimestres b ON i.id_bimestre = b.id_bimestre
                    WHERE i.id_producto_padre = ? AND i.estado = 1
                    ORDER BY COALESCE(t.orden, tc.id_tipo_corbata, b.id_bimestre, i.id_producto) ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_padre]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Columna donde se guarda la dimensión de una variante según su tipo.
     * 'libre' no usa FK (la etiqueta vive en nombre_variante).
     */
    private function columnaDimension(string $tipo_variante): string {
        switch ($tipo_variante) {
            case 'talla':   return 'id_talla';
            case 'corbata': return 'id_tipo_corbata';
            case 'bimestre': return 'id_bimestre';
            // 'libre' y los tipos personalizados (v{id}) guardan la etiqueta/opción en nombre_variante
            default:        return 'nombre_variante';
        }
    }

    /**
     * Une las categorías extra con la primaria, sin duplicados.
     */
    private function categoriasCompletas(int $id_primaria, array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        $ids[] = $id_primaria;
        return array_values(array_unique($ids));
    }

    /**
     * IDs de los hijos de un padre.
     */
    private function hijosDe(int $id_padre): array {
        try {
            $stmt = $this->conexion->prepare("SELECT id_producto FROM productos WHERE id_producto_padre = ?");
            $stmt->execute([$id_padre]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
     * Actualiza un producto padre y sus variantes en una transacción atómica.
     */
    public function actualizarConVariantes(int $id_padre, array $datosPadre, array $variantes, string $tipo_variante = 'talla', array $categorias = []): array {
        $dimensionCol = $this->columnaDimension($tipo_variante);
        try {
            $this->conexion->beginTransaction();

            // 1. Actualizar el producto padre
            $sqlPadre = "UPDATE productos SET id_categoria = ?, id_unidad = ?, nombre = ?,
                         tipo_variante = ?, id_nivel = COALESCE(NULLIF(?, ''), id_nivel), id_grado = COALESCE(NULLIF(?, ''), id_grado), id_area = COALESCE(NULLIF(?, ''), id_area)";
            $paramsPadre = [
                $datosPadre['id_categoria'],
                $datosPadre['id_unidad'],
                $datosPadre['nombre'],
                $tipo_variante,
                $datosPadre['id_nivel'] ?? null,
                $datosPadre['id_grado'] ?? null,
                $datosPadre['id_area'] ?? null,
            ];
            if (isset($datosPadre['imagen']) && $datosPadre['imagen'] !== null) {
                $sqlPadre .= ", imagen = ?";
                $paramsPadre[] = $datosPadre['imagen'];
            }
            $sqlPadre .= " WHERE id_producto = ? AND es_agrupador = 1";
            $paramsPadre[] = $id_padre;

            $stmtP = $this->conexion->prepare($sqlPadre);
            $stmtP->execute($paramsPadre);

            // 2. Obtener los hijos actuales
            $hijosActuales = $this->hijosDe($id_padre);
            $hijosMantenidos = [];

            // 3. Procesar variantes enviadas
            $colsIns = "id_categoria, id_unidad, nombre, precio_unitario, costo_produccion, comision,
                        stock_piezas, estado";
            $phsIns  = "?, ?, ?, ?, ?, ?, ?, 1";
            $colsUpd = "id_categoria = ?, id_unidad = ?, nombre = ?, precio_unitario = ?,
                        costo_produccion = ?, comision = ?";
            $argsCol = [];
            if ($dimensionCol && $dimensionCol !== 'nombre_variante') {
                $colsIns .= ", $dimensionCol";
                $phsIns  .= ", ?";
                $colsUpd .= ", $dimensionCol = ?";
                $argsCol[] = 'dimension';
            }
            if ($dimensionCol === 'nombre_variante') {
                $colsIns .= ", nombre_variante";
                $phsIns  .= ", ?";
                $colsUpd .= ", nombre_variante = ?";
                $argsCol[] = 'libre';
            }
            $colsIns .= ", id_nivel, id_grado, id_area, es_agrupador, id_producto_padre, tipo_variante";
            $phsIns  .= ", ?, ?, ?, 0, ?, ?";
            $colsUpd .= ", id_nivel = ?, id_grado = ?, id_area = ?, tipo_variante = ?";

            $stmtInsertHijo = $this->conexion->prepare("INSERT INTO productos ($colsIns) VALUES ($phsIns)");
            $stmtUpdateHijo = $this->conexion->prepare("UPDATE productos SET $colsUpd WHERE id_producto = ? AND id_producto_padre = ?");

            foreach ($variantes as $v) {
                $dim = $dimensionCol && $dimensionCol !== 'nombre_variante' ? (intval($v['id_dimension'] ?? 0) ?: null) : null;
                $libre = $dimensionCol === 'nombre_variante' ? trim($v['nombre_variante'] ?? '') : null;
                if (!empty($v['id_producto']) && $v['id_producto'] > 0) {
                    $paramsUpd = [
                        $datosPadre['id_categoria'],
                        $datosPadre['id_unidad'],
                        $datosPadre['nombre'],
                        floatval($v['precio_unitario']),
                        floatval($v['costo_produccion']),
                        floatval($v['comision'] ?? 0),
                    ];
                    foreach ($argsCol as $col) {
                        $paramsUpd[] = $col === 'dimension' ? $dim : $libre;
                    }
                    $paramsUpd[] = $datosPadre['id_nivel'] ?? null;
                    $paramsUpd[] = $datosPadre['id_grado'] ?? null;
                    $paramsUpd[] = $datosPadre['id_area'] ?? null;
                    $paramsUpd[] = $tipo_variante;
                    $paramsUpd[] = intval($v['id_producto']);
                    $paramsUpd[] = $id_padre;
                    $stmtUpdateHijo->execute($paramsUpd);
                    $hijosMantenidos[] = intval($v['id_producto']);
                } else {
                    $paramsIns = [
                        $datosPadre['id_categoria'],
                        $datosPadre['id_unidad'],
                        $datosPadre['nombre'],
                        floatval($v['precio_unitario']),
                        floatval($v['costo_produccion']),
                        floatval($v['comision'] ?? 0),
                        floatval($v['stock_piezas'] ?? 0),
                    ];
                    foreach ($argsCol as $col) {
                        $paramsIns[] = $col === 'dimension' ? $dim : $libre;
                    }
                    $paramsIns[] = $datosPadre['id_nivel'] ?? null;
                    $paramsIns[] = $datosPadre['id_grado'] ?? null;
                    $paramsIns[] = $datosPadre['id_area'] ?? null;
                    $paramsIns[] = $id_padre;
                    $paramsIns[] = $tipo_variante;
                    $stmtInsertHijo->execute($paramsIns);
                }
            }

            // 4. Eliminar variantes que ya no están
            $hijosEliminar = array_values(array_diff($hijosActuales, $hijosMantenidos));
            if (!empty($hijosEliminar)) {
                $inQuery = implode(',', array_fill(0, count($hijosEliminar), '?'));
                try {
                    $stmtDel = $this->conexion->prepare("DELETE FROM productos WHERE id_producto IN ($inQuery)");
                    $stmtDel->execute($hijosEliminar);
                } catch (PDOException $ex) {
                    $stmtInactivar = $this->conexion->prepare("UPDATE productos SET estado = 0 WHERE id_producto IN ($inQuery)");
                    $stmtInactivar->execute($hijosEliminar);
                }
            }

            // 5. Pivote de categorías (padre + hijos)
            $idsCats = $this->categoriasCompletas((int) $datosPadre['id_categoria'], $categorias);
            foreach (array_merge([$id_padre], $this->hijosDe($id_padre)) as $idProd) {
                $this->sincronizarCategorias($idProd, $idsCats, (int) $datosPadre['id_categoria']);
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