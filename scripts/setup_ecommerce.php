<?php
require_once dirname(__DIR__) . '/config/conexion.php';
try {
    $db = Conexion::singleton()->getConexion();
    $db->exec("
    CREATE TABLE IF NOT EXISTS pedidos_online (
        id_pedido INT AUTO_INCREMENT PRIMARY KEY,
        id_cliente INT NOT NULL,
        fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        total DECIMAL(10,2) NOT NULL,
        nro_operacion_yape VARCHAR(50) NOT NULL,
        estado TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Pendiente, 2=Entregado, 0=Rechazado',
        id_venta INT NULL,
        FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente),
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS detalle_pedidos_online (
        id_detalle INT AUTO_INCREMENT PRIMARY KEY,
        id_pedido INT NOT NULL,
        id_producto INT NOT NULL,
        cantidad DECIMAL(10,2) NOT NULL,
        peso_neto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        precio_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (id_pedido) REFERENCES pedidos_online(id_pedido),
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tables pedidos_online and detalle_pedidos_online created successfully.\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
