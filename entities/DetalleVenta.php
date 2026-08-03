<?php
/**
 * Entidad DetalleVenta
 * Representa una línea de producto dentro de una venta.
 *
 * Regla de negocio:
 *   - piezas    → Cuántas unidades físicas salieron (Ej: 2 pavos, 3 sacos).
 *                 Es el campo que se descuenta del stock de inventario.
 *   - peso_neto → Peso total registrado en balanza (Ej: 19.50 Kg).
 *                 Para productos de peso fijo se calcula: piezas × contenido_estandar.
 *                 Para pavos/aves, es el valor real pesado al momento de la venta.
 *   - subtotal  → Para productos normales: piezas × precio_venta.
 *                 Para pavos: peso_neto × precio_venta.
 */
class DetalleVenta {
    public $id_detalle;
    public $id_producto;
    public $piezas;       // Unidades físicas que salen del inventario
    public $precio_venta;
    public $costo_unitario; // Snapshot del costo de producción al momento de la venta
    public $subtotal;

    public function __construct(
        $id_detalle  = null,
        $id_producto   = null,
        $piezas      = 0,
        $precio_venta= 0.0,
        $costo_unitario = 0.0,
        $subtotal    = 0.0
    ) {
        $this->id_detalle  = $id_detalle;
        $this->id_producto   = $id_producto;
        $this->piezas      = $piezas;
        $this->precio_venta= $precio_venta;
        $this->costo_unitario = $costo_unitario;
        $this->subtotal    = $subtotal;
    }
}
?>
