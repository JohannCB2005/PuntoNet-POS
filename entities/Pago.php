<?php
class Pago {
    public $id_pago;
    public $id_alumno;
    public $tipo_comprobante;
    public $serie;
    public $numero;
    public $nombre_comprobante;
    public $nombre_normalizado;
    public $concepto;
    public $concepto_clave;
    public $mes_concepto;
    public $anio_concepto;
    public $fecha_emision;
    public $fecha_pago;
    public $numero_operacion;
    public $observacion;
    public $monto;
    public $mora;
    public $descuento;
    public $total;
    public $metodo_cruce;
    public $id_importacion;
    public $origen;
    public $estado;

    public function __construct(
        $numero = '',
        $nombre_comprobante = '',
        $nombre_normalizado = '',
        $concepto = '',
        $mes_concepto = 0,
        $anio_concepto = 0,
        $total = 0.0,
        $id_alumno = null,
        $origen = 2,
        $id_pago = null
    ) {
        $this->id_pago             = $id_pago;
        $this->id_alumno           = $id_alumno;
        $this->tipo_comprobante    = 'BOLETA';
        $this->serie               = '';
        $this->numero              = $numero;
        $this->nombre_comprobante  = $nombre_comprobante;
        $this->nombre_normalizado  = $nombre_normalizado;
        $this->concepto            = $concepto;
        $this->concepto_clave      = '';
        $this->mes_concepto        = $mes_concepto;
        $this->anio_concepto       = $anio_concepto;
        $this->fecha_emision       = null;
        $this->fecha_pago          = null;
        $this->numero_operacion    = null;
        $this->observacion         = null;
        $this->monto               = $total;
        $this->mora                = 0.0;
        $this->descuento           = 0.0;
        $this->total               = $total;
        $this->metodo_cruce        = $id_alumno ? 1 : 0;
        $this->id_importacion      = null;
        $this->origen              = $origen;
        $this->estado              = 1;
    }
}
?>