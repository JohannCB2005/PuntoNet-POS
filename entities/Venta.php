<?php
class Venta {
    public $id_venta;
    public $id_usuario;
    public $id_cliente;
    public $fecha;
    public $tipo_comprobante;
    public $total;
    public $metodo_pago;
    public $estado;
    public $detalles = [];

    public function __construct($id_venta=null, $id_usuario=null, $id_cliente=null, $fecha='', $tipo_comprobante=1, $total=0, $metodo_pago=1, $estado=1) {
        $this->id_venta = $id_venta;
        $this->id_usuario = $id_usuario;
        $this->id_cliente = $id_cliente;
        $this->fecha = $fecha;
        $this->tipo_comprobante = $tipo_comprobante;
        $this->total = $total;
        $this->metodo_pago = $metodo_pago;
        $this->estado = $estado;
        $this->detalles = [];
    }

    public function agregarDetalle($detalle) {
        $this->detalles[] = $detalle;
    }
}
?>
