<?php
require 'config/conexion.php';
$conexion = Conexion::singleton()->getConexion();
$stmt = $conexion->query("SELECT id_insumo, i.nombre, c.nombre as categoria, id_talla FROM insumos i JOIN categorias c ON i.id_categoria = c.id_categoria LIMIT 30;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
