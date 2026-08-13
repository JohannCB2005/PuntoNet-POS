<?php
require_once __DIR__ . '/Persona.php';

class Cliente extends Persona {
    public $id_cliente;
    public $tipo_cliente;
    // Cuenta de acceso a la tienda online (opcional: clientes de mostrador no la tienen)
    public $email;
    public $password;
    public $email_verificado;

    public function __construct(
        $tipo_documento = 1,
        $numero_documento = '',
        $nombres_razon_social = '',
        $apellidos = null,
        $direccion = null,
        $telefono = null,
        $tipo_cliente = 1,
        $id_cliente = null,
        $email = null,
        $password = null,
        $email_verificado = 0
    ) {
        parent::__construct(
            $tipo_documento,
            $numero_documento,
            $nombres_razon_social,
            $apellidos,
            $direccion,
            $telefono
        );
        $this->tipo_cliente = $tipo_cliente;
        $this->id_cliente = $id_cliente;
        $this->email = $email;
        $this->password = $password;
        $this->email_verificado = $email_verificado;
    }
}
?>
