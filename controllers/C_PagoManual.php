<?php
/**
 * controllers/C_PagoManual.php
 * Reporte del cliente de un pago por verificación manual (billetera o transferencia).
 *
 * Solo hay una acción cliente: `reportar`. Autentica por token público del pedido
 * (mismo patrón que cancelar_pedido): quien conoce el token es quien hizo la compra.
 *
 * La aprobación/rechazo por parte del admin NO vive aquí: se hace con la acción
 * `gestionar_pedido` de C_Ecommerce.php (acciones 'aprobar'/'rechazar', con CSRF).
 */
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/models/M_Ecommerce.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // El cliente reporta que ya pagó: medio usado + número de operación (+ captura opcional).
    case 'reportar':
        $id_pedido = intval($_POST['id_pedido'] ?? 0);
        $token     = trim((string) ($_POST['token'] ?? ''));
        if ($id_pedido <= 0 || $token === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Solicitud inválida.']);
            exit;
        }

        $medio     = strtolower(trim((string) ($_POST['medio_pago'] ?? '')));
        $referencia = trim((string) ($_POST['referencia'] ?? ''));

        // Captura opcional: imagen del comprobante (se guarda solo si todo lo demás pasa).
        $captura = null;
        if (isset($_FILES['captura']) && $_FILES['captura']['error'] !== UPLOAD_ERR_NO_FILE) {
            $res = guardarCapturaPago($_FILES['captura']);
            if (!$res['ok']) {
                echo json_encode(['success' => false, 'mensaje' => $res['mensaje']]);
                exit;
            }
            $captura = $res['ruta'];
        }

        $r = M_Ecommerce::singleton()->reportarPagoManual($id_pedido, $token, [
            'medio_pago_usado' => $medio,
            'referencia'       => $referencia,
            'captura_pago'     => $captura,
        ]);

        // Si el reporte no procede, no dejar la imagen subida como basura.
        if (!$r['ok'] && $captura) {
            @unlink(dirname(__DIR__) . '/' . $captura);
        }

        echo json_encode(['success' => $r['ok'], 'mensaje' => $r['mensaje'] ?? '']);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida.']);
}

/**
 * Valida y guarda una imagen de comprobante en assets/comprobantes/ (servida por web).
 *
 * @return array{ok:bool, mensaje:string, ruta:?string}
 */
function guardarCapturaPago(array $file): array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'mensaje' => 'No se pudo recibir la imagen.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'mensaje' => 'La imagen supera el tamaño máximo (5 MB).'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'mensaje' => 'Formato no permitido. Usa JPG, PNG o WebP.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'mensaje' => 'El archivo no es una imagen válida.'];
    }

    $dir = dirname(__DIR__) . '/assets/comprobantes/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $nombre = 'comp_' . bin2hex(random_bytes(8)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
        return ['ok' => false, 'mensaje' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return ['ok' => true, 'mensaje' => '', 'ruta' => 'assets/comprobantes/' . $nombre];
}