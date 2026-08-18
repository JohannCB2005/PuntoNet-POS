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
    public $id_caja;  // Caja física que recibió el dinero. NULL = sin caja (ej. pedido online).
    public $origen;   // 1=POS presencial, 2=Pedido online
    public $serie = '';               // Serie del comprobante (opcional en POS; vacía = automática)
    public $fecha_vencimiento = '';   // Fecha de vencimiento Y-m-d (opcional)
    public $detalles = [];
    // Líneas de pago (pago mixto): [{metodo_pago, monto, referencia}, ...]. Si viene
    // vacío, M_Venta::registrar() asume una sola línea con $metodo_pago y $total,
    // así que los llamadores existentes (M_CambioTalla, conversión de cotización)
    // siguen funcionando sin cambios.
    public $pagos = [];

    public function __construct($id_venta=null, $id_usuario=null, $id_cliente=null, $fecha='', $tipo_comprobante=1, $total=0, $metodo_pago=1, $estado=1, $id_caja=null, $origen=1) {
        $this->id_venta = $id_venta;
        $this->id_usuario = $id_usuario;
        $this->id_cliente = $id_cliente;
        $this->fecha = $fecha;
        $this->tipo_comprobante = $tipo_comprobante;
        $this->total = $total;
        $this->metodo_pago = $metodo_pago;
        $this->estado = $estado;
        $this->id_caja = $id_caja;
        $this->origen = $origen;
        $this->detalles = [];
        $this->pagos = [];
    }

    public function agregarDetalle($detalle) {
        $this->detalles[] = $detalle;
    }

    public function agregarPago(int $metodo_pago, float $monto, ?string $referencia = null) {
        $this->pagos[] = ['metodo_pago' => $metodo_pago, 'monto' => $monto, 'referencia' => $referencia];
    }
}
?>
