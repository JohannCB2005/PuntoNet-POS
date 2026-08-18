<?php
require dirname(__DIR__) . '/models/M_Mailer.php';

$m = M_Mailer::singleton();

$pedido = [
    'id_pedido'        => 1042,
    'tipo_entrega'     => 1,
    'estudiante_nombre' => '',
    'total'            => 129.90,
    'detalles'         => [
        [
            'nombre'   => 'Polo manga corta NISSI (T-14)',
            'cantidad' => 2,
            'subtotal' => 99.90,
        ],
        [
            'nombre'   => 'Corbata escolar NISSI',
            'cantidad' => 1,
            'subtotal' => 30.00,
        ],
    ],
];

$r = $m->enviarConfirmacionCompra('johanndaniel88@gmail.com', 'Johann Daniel', $pedido);

echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
