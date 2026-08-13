<?php
class Alumno {
    public $id_alumno;
    public $codigo;
    public $nombre_completo;
    public $nombre_normalizado;
    public $numero_documento;
    public $fecha_nacimiento;
    public $sexo;
    public $id_nivel;
    public $id_grado;
    public $seccion;
    public $matriculado;
    public $id_importacion;
    public $origen;
    public $estado;

    public function __construct(
        $codigo = '',
        $nombre_completo = '',
        $nombre_normalizado = '',
        $id_nivel = null,
        $id_grado = null,
        $seccion = '',
        $matriculado = 1,
        $origen = 2,
        $id_alumno = null
    ) {
        $this->id_alumno          = $id_alumno;
        $this->codigo              = $codigo;
        $this->nombre_completo     = $nombre_completo;
        $this->nombre_normalizado  = $nombre_normalizado;
        $this->numero_documento    = null;
        $this->fecha_nacimiento    = null;
        $this->sexo                = null;
        $this->id_nivel            = $id_nivel;
        $this->id_grado            = $id_grado;
        $this->seccion             = $seccion;
        $this->matriculado         = $matriculado;
        $this->id_importacion      = null;
        $this->origen              = $origen;
        $this->estado              = 1;
    }
}
?>