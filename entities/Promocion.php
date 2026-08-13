<?php
class Promocion {
    public $id_promocion;
    public $nombre;
    public $bimestre;
    public $mes_requerido;
    public $anio_requerido;
    public $monto_minimo;
    public $fecha_limite_pago;
    public $exige_pension_completa;
    public $exige_matriculado;
    public $exige_neto_positivo;
    public $descripcion;
    public $estado;
    public $id_usuario;

    public function __construct(
        $nombre = '',
        $bimestre = 1,
        $mes_requerido = 1,
        $anio_requerido = 0,
        $monto_minimo = null,
        $fecha_limite_pago = null,
        $exige_pension_completa = 0,
        $exige_matriculado = 1,
        $exige_neto_positivo = 1,
        $descripcion = '',
        $id_usuario = null,
        $id_promocion = null
    ) {
        $this->id_promocion           = $id_promocion;
        $this->nombre                  = $nombre;
        $this->bimestre                = $bimestre;
        $this->mes_requerido           = $mes_requerido;
        $this->anio_requerido          = $anio_requerido;
        $this->monto_minimo            = $monto_minimo;
        $this->fecha_limite_pago       = $fecha_limite_pago;
        $this->exige_pension_completa  = $exige_pension_completa;
        $this->exige_matriculado       = $exige_matriculado;
        $this->exige_neto_positivo     = $exige_neto_positivo;
        $this->descripcion             = $descripcion;
        $this->estado                  = 1;
        $this->id_usuario              = $id_usuario;
    }
}
?>