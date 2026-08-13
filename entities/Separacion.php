<?php
class Separacion {
    public $id_separacion;
    public $codigo;
    public $id_cliente;
    public $id_usuario;
    public $id_venta_anticipo;
    public $fecha;
    public $fecha_vencimiento;
    public $total;
    public $estado;
    public $tipo_comprobante_final;
    public $id_usuario_despacho;
    public $fecha_despacho;
    public $motivo_anulacion;

    public function __construct(
        $id_separacion = null,
        $codigo = '',
        $id_cliente = null,
        $id_usuario = null,
        $id_venta_anticipo = null,
        $fecha = '',
        $fecha_vencimiento = '',
        $total = 0,
        $estado = 1,
        $tipo_comprobante_final = null,
        $id_usuario_despacho = null,
        $fecha_despacho = null,
        $motivo_anulacion = null
    ) {
        $this->id_separacion = $id_separacion;
        $this->codigo = $codigo;
        $this->id_cliente = $id_cliente;
        $this->id_usuario = $id_usuario;
        $this->id_venta_anticipo = $id_venta_anticipo;
        $this->fecha = $fecha;
        $this->fecha_vencimiento = $fecha_vencimiento;
        $this->total = $total;
        $this->estado = $estado;
        $this->tipo_comprobante_final = $tipo_comprobante_final;
        $this->id_usuario_despacho = $id_usuario_despacho;
        $this->fecha_despacho = $fecha_despacho;
        $this->motivo_anulacion = $motivo_anulacion;
    }
}
?>
