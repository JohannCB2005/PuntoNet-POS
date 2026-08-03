<?php
class DetalleCotizacion {
    public $id_detalle_cotizacion;
    public $id_cotizacion;
    public $id_insumo;
    public $piezas;
    public $precio_unitario;
    public $subtotal;

    public function __construct(
        $id_detalle_cotizacion = null,
        $id_cotizacion = null,
        $id_insumo = null,
        $piezas = 0,
        $precio_unitario = 0.0,
        $subtotal = 0.0
    ) {
        $this->id_detalle_cotizacion = $id_detalle_cotizacion;
        $this->id_cotizacion = $id_cotizacion;
        $this->id_insumo = $id_insumo;
        $this->piezas = $piezas;
        $this->precio_unitario = $precio_unitario;
        $this->subtotal = $subtotal;
    }
}
?>
