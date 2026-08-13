<?php
/**
 * run_controller.php
 * Punto de entrada para ejecutar controladores.
 *
 * El hosting bloquea la ejecución de PHP dentro de public_html/controllers/
 * (mod_security). Este archivo vive en la raíz (donde PHP sí corre) y se
 * encarga de incluir al controlador real, preservando las rutas relativas
 * que esperan vivir en controllers/.
 *
 * Cómo se invoca (desde .htaccess):
 *   /controllers/C_Venta.php  →  run_controller.php?ctrl=C_Venta
 */
$default = isset($_GET['ctrl']) ? $_GET['ctrl'] : '';
$default = basename($default);            // evitar traversal
$default = preg_replace('/[^A-Za-z0-9_]/', '', $default);

$archivo = __DIR__ . '/controllers/' . $default . '.php';

if ($default !== '' && file_exists($archivo)) {
    // Cambiamos al directorio de controllers para que dirname(__DIR__)
    // y las rutas relativas de los controladores resuelvan igual.
    chdir(__DIR__ . '/controllers');
    require $archivo;
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(["success" => false, "mensaje" => "Controlador no encontrado"]);
