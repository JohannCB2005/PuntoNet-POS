<?php
/**
 * Entidad Producto
 * Representa un artículo del catálogo de inventario.
 * Atributos extendidos para uniformes y módulos escolares.
 */
class Producto {
    public $id_producto;
    public $id_categoria;
    public $id_unidad;
    public $nombre;
    public $precio_unitario;
    public $costo_produccion;
    public $comision;
    public $stock_piezas;
    public $stock_ilimitado;  // 1 = Servicio con stock ilimitado (no valida ni descuenta stock)
    public $estado;
    public $imagen;

    // Uniformes
    public $id_talla;
    public $id_tipo_corbata;
    // Módulos Escolares
    public $id_nivel;
    public $id_grado;
    public $id_area;
    public $id_bimestre;
    // Sistema de variantes de talla
    public $es_agrupador;       // 1 = Producto padre (NO vendible), 0 = simple/variante
    public $id_producto_padre;  // FK al padre, NULL si es padre o producto simple
    public $tipo_variante;      // talla|corbata|bimestre|libre (NULL si es simple)
    public $nombre_variante;    // Etiqueta libre de la variante (solo tipo 'libre')

    public function __construct(
        $id_categoria = null,
        $id_unidad = null,
        $nombre = '',
        $precio_unitario = 0.0,
        $costo_produccion = 0.0,
        $stock_piezas = 0.0,
        $id_producto = null,
        $imagen = null,
        $id_talla = null,
        $id_tipo_corbata = null,
        $id_nivel = null,
        $id_grado = null,
        $id_area = null,
        $id_bimestre = null,
        $es_agrupador = 0,
        $id_producto_padre = null,
        $comision = 0.0,
        $stock_ilimitado = 0,
        $tipo_variante = null,
        $nombre_variante = null
    ) {
        $this->id_producto          = $id_producto;
        $this->id_categoria       = $id_categoria;
        $this->id_unidad          = $id_unidad;
        $this->nombre             = $nombre;
        $this->precio_unitario    = $precio_unitario;
        $this->costo_produccion   = $costo_produccion;
        $this->comision           = $comision;
        $this->stock_piezas       = $stock_piezas;
        $this->stock_ilimitado    = $stock_ilimitado ? 1 : 0;
        $this->estado             = 1;
        $this->imagen             = $imagen;
        $this->id_talla           = $id_talla;
        $this->id_tipo_corbata    = $id_tipo_corbata;
        $this->id_nivel           = $id_nivel;
        $this->id_grado           = $id_grado;
        $this->id_area            = $id_area;
        $this->id_bimestre        = $id_bimestre;
        $this->es_agrupador       = $es_agrupador ? 1 : 0;
        $this->id_producto_padre  = $id_producto_padre;
        $this->tipo_variante      = $tipo_variante;
        $this->nombre_variante    = $nombre_variante;
    }
}
?>
