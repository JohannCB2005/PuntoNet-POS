<?php
// Iniciar sesión PHP para el control de identidad y roles de usuario
session_start();

// Configurar cabecera para responder en formato JSON
header('Content-Type: application/json');

// Validar que el usuario esté autenticado
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/models/M_Separacion.php';
require_once dirname(__DIR__) . '/models/M_Caja.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$model = M_Separacion::singleton();
$modelCaja = M_Caja::singleton();

/**
 * Valida y normaliza el array de líneas de pago recibido del cliente.
 * Mismo criterio que C_Venta.php?action=crear: método en [1,2,3], monto > 0.
 * Devuelve el array normalizado o termina la ejecución con el error JSON.
 */
function validarPagos($pagosInput) {
    if (!is_array($pagosInput) || empty($pagosInput)) {
        echo json_encode(["success" => false, "mensaje" => "Debes indicar al menos una forma de pago."]);
        exit;
    }
    $pagos = [];
    foreach ($pagosInput as $p) {
        $mp = intval($p['metodo_pago'] ?? 0);
        $monto = floatval($p['monto'] ?? 0);
        if (!in_array($mp, [1, 2, 3], true) || $monto <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Hay una línea de pago inválida."]);
            exit;
        }
        $pagos[] = ['metodo_pago' => $mp, 'monto' => $monto, 'referencia' => trim((string) ($p['referencia'] ?? '')) ?: null];
    }
    return $pagos;
}

switch ($action) {

    // Registra una separación nueva: mercadería completa + anticipo (>= 50%).
    case 'crear':
        $id_usuario = $_SESSION['id_usuario'];
        $id_cliente = intval($input['id_cliente'] ?? 0);
        $carrito    = isset($input['cart']) && is_array($input['cart']) ? $input['cart'] : [];
        $pagosInput = isset($input['pagos']) ? $input['pagos'] : [];

        if ($id_cliente <= 0 || empty($carrito)) {
            echo json_encode(["success" => false, "mensaje" => "Faltan el cliente o los productos a separar."]);
            exit;
        }
        $pagos = validarPagos($pagosInput);

        $cajaAbierta = $modelCaja->obtenerCajaAbierta($id_usuario);
        if (!$cajaAbierta) {
            echo json_encode(["success" => false, "mensaje" => "Debes abrir caja antes de registrar una separación."]);
            exit;
        }

        $r = $model->registrar($id_cliente, $id_usuario, (int) $cajaAbierta['id_caja'], $carrito, $pagos);
        echo json_encode($r['ok']
            ? ["success" => true, "mensaje" => "Separación registrada con éxito.", "id_separacion" => $r['id_separacion'], "codigo" => $r['codigo'], "id_venta_anticipo" => $r['id_venta_anticipo']]
            : ["success" => false, "mensaje" => $r['mensaje']]);
        break;

    // Registra un abono parcial sobre una separación pendiente.
    case 'abonar':
        $id_usuario     = $_SESSION['id_usuario'];
        $id_separacion  = intval($input['id_separacion'] ?? 0);
        $pagosInput     = isset($input['pagos']) ? $input['pagos'] : [];

        if ($id_separacion <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Separación inválida."]);
            exit;
        }
        $pagos = validarPagos($pagosInput);

        $cajaAbierta = $modelCaja->obtenerCajaAbierta($id_usuario);
        if (!$cajaAbierta) {
            echo json_encode(["success" => false, "mensaje" => "Debes abrir caja antes de registrar un abono."]);
            exit;
        }

        $r = $model->abonar($id_separacion, $id_usuario, (int) $cajaAbierta['id_caja'], $pagos);
        echo json_encode($r['ok']
            ? ["success" => true, "mensaje" => "Abono registrado con éxito.", "saldo_restante" => $r['saldo_restante']]
            : ["success" => false, "mensaje" => $r['mensaje']]);
        break;

    // Cobra el saldo pendiente (si lo hay) y marca la separación como despachada.
    case 'despachar':
        $id_usuario        = $_SESSION['id_usuario'];
        $id_separacion     = intval($input['id_separacion'] ?? 0);
        $pagosInput        = isset($input['pagos']) ? $input['pagos'] : [];
        $tipo_comprobante  = isset($input['tipo_comprobante']) ? intval($input['tipo_comprobante']) : 3; // Nota de Venta por defecto

        if ($id_separacion <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Separación inválida."]);
            exit;
        }
        if (!in_array($tipo_comprobante, [1, 2, 3], true)) {
            $tipo_comprobante = 3;
        }
        // El saldo puede ya estar en 0 (separación pagada por completo en abonos previos);
        // en ese caso no hace falta ninguna línea de pago.
        $pagos = empty($pagosInput) ? [] : validarPagos($pagosInput);

        $cajaAbierta = $modelCaja->obtenerCajaAbierta($id_usuario);
        if (!$cajaAbierta) {
            echo json_encode(["success" => false, "mensaje" => "Debes abrir caja antes de despachar."]);
            exit;
        }

        $r = $model->despachar($id_separacion, $id_usuario, (int) $cajaAbierta['id_caja'], $pagos, $tipo_comprobante);
        echo json_encode($r['ok']
            ? ["success" => true, "mensaje" => "Separación despachada con éxito.", "id_venta_saldo" => $r['id_venta_saldo']]
            : ["success" => false, "mensaje" => $r['mensaje']]);
        break;

    // Anula una separación pendiente y devuelve el stock. Solo Administrador.
    case 'anular':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "Solo un Administrador puede anular una separación."]);
            exit;
        }
        $id_separacion = intval($input['id_separacion'] ?? 0);
        $motivo        = trim((string) ($input['motivo'] ?? ''));

        if ($id_separacion <= 0 || $motivo === '') {
            echo json_encode(["success" => false, "mensaje" => "Debes indicar la separación y el motivo de anulación."]);
            exit;
        }

        $r = $model->anular($id_separacion, $motivo, $_SESSION['id_usuario']);
        echo json_encode($r['ok']
            ? ["success" => true, "mensaje" => $r['mensaje']]
            : ["success" => false, "mensaje" => $r['mensaje']]);
        break;

    // Lista las separaciones, opcionalmente filtradas por estado.
    case 'listar':
        $estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? intval($_GET['estado']) : null;
        echo json_encode(["success" => true, "data" => $model->listar($estado)]);
        break;

    // Detalle de una separación puntual, con su historial de abonos.
    case 'detalle':
        $id_separacion = intval($_GET['id_separacion'] ?? 0);
        if ($id_separacion <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Separación inválida."]);
            exit;
        }
        $detalle = $model->obtenerPorId($id_separacion);
        echo json_encode($detalle
            ? ["success" => true, "data" => $detalle]
            : ["success" => false, "mensaje" => "Separación no encontrada."]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
