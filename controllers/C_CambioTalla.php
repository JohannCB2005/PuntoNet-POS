<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado.']);
    exit;
}

require_once dirname(__DIR__) . '/models/M_CambioTalla.php';

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$model = M_CambioTalla::singleton();

switch ($action) {

    // Tallas hermanas disponibles para una línea de venta, con la diferencia de
    // precio que implicaría cada una. Solo lectura.
    case 'tallas_disponibles':
        $id_detalle = intval($_GET['id_detalle'] ?? 0);
        if ($id_detalle <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'Falta id_detalle.']);
            exit;
        }
        $r = $model->tallasDisponiblesPara($id_detalle);
        echo json_encode($r['ok'] ? ['success' => true, 'data' => $r] : ['success' => false, 'mensaje' => $r['mensaje']]);
        break;

    // Registra el cambio: mueve stock, deja rastro en kardex y cobra/devuelve la
    // diferencia si la hay. Muta datos — exige POST explícito.
    case 'registrar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'mensaje' => 'Método no permitido.']);
            exit;
        }

        $id_detalle           = intval($input['id_detalle'] ?? 0);
        $id_producto_entrante = intval($input['id_producto_entrante'] ?? 0);
        $metodo_pago          = intval($input['metodo_pago'] ?? 1);
        $motivo               = isset($input['motivo']) ? trim((string) $input['motivo']) : null;

        if ($id_detalle <= 0 || $id_producto_entrante <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos.']);
            exit;
        }
        if (!in_array($metodo_pago, [1, 2, 3, 4], true)) {
            $metodo_pago = 1;
        }

        $r = $model->registrarCambio(
            $id_detalle,
            $id_producto_entrante,
            (int) $_SESSION['id_usuario'],
            (string) $_SESSION['rol'],
            $metodo_pago,
            $motivo ?: null
        );
        echo json_encode($r);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida.']);
}
