<?php
class Cotizacion {
    public $id_cotizacion;
    public $codigo;
    public $id_usuario;
    public $id_cliente;
    public $cliente_nombre_manual;
    public $fecha_emision;
    public $fecha_vencimiento;
    public $subtotal;
    public $igv;
    public $total;
    public $observaciones;
    public $estado;
    public $id_venta;
    public $detalles = [];

    public function __construct(
        $id_cotizacion = null, 
        $codigo = '', 
        $id_usuario = null, 
        $id_cliente = null, 
        $cliente_nombre_manual = '', 
        $fecha_emision = '', 
        $fecha_vencimiento = '', 
        $subtotal = 0, 
        $igv = 0, 
        $total = 0, 
        $observaciones = '', 
        $estado = 1, 
        $id_venta = null
    ) {
        $this->id_cotizacion = $id_cotizacion;
        $this->codigo = $codigo;
        $this->id_usuario = $id_usuario;
        $this->id_cliente = $id_cliente;
        $this->cliente_nombre_manual = $cliente_nombre_manual;
        $this->fecha_emision = $fecha_emision;
        $this->fecha_vencimiento = $fecha_vencimiento;
        $this->subtotal = $subtotal;
        $this->igv = $igv;
        $this->total = $total;
        $this->observaciones = $observaciones;
        $this->estado = $estado;
        $this->id_venta = $id_venta;
        $this->detalles = [];
    }

    public function agregarDetalle($detalle) {
        $this->detalles[] = $detalle;
    }
}
?>
