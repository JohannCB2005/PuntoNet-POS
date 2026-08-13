<?php
class Entrega {
    public $id_entrega;
    public $id_promocion;
    public $id_alumno;
    public $id_pago;
    public $dni_receptor;
    public $nombre_receptor;
    public $parentesco;
    public $origen_datos;
    public $observacion;
    public $id_usuario;
    public $estado;

    public function __construct(
        $id_promocion = null,
        $id_alumno = null,
        $id_pago = null,
        $dni_receptor = '',
        $nombre_receptor = '',
        $parentesco = null,
        $origen_datos = 1,
        $id_usuario = null,
        $id_entrega = null
    ) {
        $this->id_entrega       = $id_entrega;
        $this->id_promocion     = $id_promocion;
        $this->id_alumno        = $id_alumno;
        $this->id_pago          = $id_pago;
        $this->dni_receptor     = $dni_receptor;
        $this->nombre_receptor  = $nombre_receptor;
        $this->parentesco       = $parentesco;
        $this->origen_datos     = $origen_datos;
        $this->observacion      = null;
        $this->id_usuario       = $id_usuario;
        $this->estado           = 1;
    }
}
?>