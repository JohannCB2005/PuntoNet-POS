<?php
require 'config/conexion.php';
$conexion = Conexion::singleton()->getConexion();
$stmt = $conexion->query("SELECT * FROM tallas;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
