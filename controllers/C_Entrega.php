<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Administrador', 'Vendedor'], true)) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/entities/Entrega.php';
require_once dirname(__DIR__) . '/models/M_Entrega.php';
require_once dirname(__DIR__) . '/models/M_Promocion.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

switch ($action) {

    case 'listar':
        $idPromocion = isset($_GET['id_promocion']) ? (int) $_GET['id_promocion'] : 0;
        $promocion = $idPromocion > 0 ? M_Promocion::singleton()->obtenerPorId($idPromocion) : M_Promocion::singleton()->obtenerUltima();
        if (!$promocion) {
            echo json_encode(["success" => false, "mensaje" => "No hay ninguna promoción configurada todavía."]);
            exit;
        }
        $beneficiarios = M_Entrega::singleton()->listarBeneficiarios($promocion);
        echo json_encode(["success" => true, "promocion" => $promocion, "beneficiarios" => $beneficiarios]);
        break;

    // Recorte de la rama DNI de Tienda NISSI (controllers/C_Cliente.php, acción
    // buscar_api_only): consulta apiperu.dev/api/dni con el token compartido.
    case 'buscar_dni':
        $dni = isset($input['dni']) ? trim((string) $input['dni']) : '';
        if (strlen($dni) !== 8 || !ctype_digit($dni)) {
            echo json_encode(["success" => false, "mensaje" => "El DNI debe tener exactamente 8 dígitos."]);
            exit;
        }

        $apiConfig = require dirname(__DIR__) . '/config/api.php';
        $token = $apiConfig['apiperu_token'] ?? '';
        if (empty($token)) {
            echo json_encode(["success" => false, "mensaje" => "Token de RENIEC no configurado en el servidor."]);
            exit;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://apiperu.dev/api/dni',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode(['dni' => $dni]),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo json_encode(["success" => false, "mensaje" => "Error de conexión con RENIEC: " . $err]);
            exit;
        }

        $resData = json_decode($response, true);
        if (!$resData || !isset($resData['success']) || !$resData['success']) {
            $msg = $resData['message'] ?? 'DNI no encontrado.';
            echo json_encode(["success" => false, "mensaje" => $msg]);
            exit;
        }

        $apiData = $resData['data'];
        $nombres = trim($apiData['nombres'] ?? '');
        $apellidos = trim(($apiData['apellido_paterno'] ?? '') . ' ' . ($apiData['apellido_materno'] ?? ''));
        $nombreCompleto = $apellidos !== '' ? ($apellidos . ', ' . $nombres) : $nombres;

        echo json_encode(["success" => true, "nombre_completo" => $nombreCompleto]);
        break;

    case 'registrar':
        // CSRF propio (excepción deliberada: NISSI solo lo aplica al login, pero
        // esta acción mueve mercadería física).
        $token = isset($input['csrf_entrega']) ? (string) $input['csrf_entrega'] : '';
        if (!isset($_SESSION['csrf_entrega']) || !hash_equals($_SESSION['csrf_entrega'], $token)) {
            echo json_encode(["success" => false, "mensaje" => "Token de seguridad inválido. Recarga la página."]);
            exit;
        }

        $idPromocion = isset($input['id_promocion']) ? (int) $input['id_promocion'] : 0;
        $idAlumno = isset($input['id_alumno']) ? (int) $input['id_alumno'] : 0;
        $idPago = isset($input['id_pago']) && $input['id_pago'] !== '' ? (int) $input['id_pago'] : null;
        $dni = isset($input['dni_receptor']) ? trim((string) $input['dni_receptor']) : '';
        $nombreReceptor = isset($input['nombre_receptor']) ? trim((string) $input['nombre_receptor']) : '';
        $parentesco = isset($input['parentesco']) && trim((string) $input['parentesco']) !== '' ? trim((string) $input['parentesco']) : null;
        $origenDatos = isset($input['origen_datos']) ? (int) $input['origen_datos'] : 2;

        if ($idPromocion <= 0 || $idAlumno <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Promoción o alumno inválidos."]);
            exit;
        }

        // origen_datos=3: entregado en el colegio directamente al alumno, sin
        // verificar DNI de un tercero (el "receptor" es el propio alumno).
        if ($origenDatos === 3) {
            $dni = null;
        } elseif (strlen($dni) !== 8 || !ctype_digit($dni)) {
            echo json_encode(["success" => false, "mensaje" => "El DNI de quien recoge debe tener 8 dígitos."]);
            exit;
        }
        if ($nombreReceptor === '') {
            echo json_encode(["success" => false, "mensaje" => "Falta el nombre de quien recoge."]);
            exit;
        }

        $e = new Entrega($idPromocion, $idAlumno, $idPago, $dni, $nombreReceptor, $parentesco, $origenDatos, (int) $_SESSION['id_usuario']);
        echo json_encode(M_Entrega::singleton()->registrar($e));
        break;

    case 'obtener':
        $id = isset($_GET['id_entrega']) ? (int) $_GET['id_entrega'] : 0;
        $detalle = $id > 0 ? M_Entrega::singleton()->obtener($id) : null;
        if ($detalle === null) {
            echo json_encode(["success" => false, "mensaje" => "Entrega no encontrada."]);
            exit;
        }
        echo json_encode(["success" => true, "entrega" => $detalle]);
        break;

    // Disponible para Administrador y Vendedor por igual (mismo guard de arriba).
    // Mismo CSRF propio que 'registrar': anular también mueve mercadería.
    case 'anular':
        $token = isset($input['csrf_entrega']) ? (string) $input['csrf_entrega'] : '';
        if (!isset($_SESSION['csrf_entrega']) || !hash_equals($_SESSION['csrf_entrega'], $token)) {
            echo json_encode(["success" => false, "mensaje" => "Token de seguridad inválido. Recarga la página."]);
            exit;
        }
        $id = isset($input['id_entrega']) ? (int) $input['id_entrega'] : 0;
        $motivo = isset($input['motivo']) ? trim((string) $input['motivo']) : '';
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID inválido."]);
            exit;
        }
        if ($motivo === '') {
            echo json_encode(["success" => false, "mensaje" => "Indica el motivo de la anulación."]);
            exit;
        }
        echo json_encode(["success" => M_Entrega::singleton()->anular($id, $motivo, (int) $_SESSION['id_usuario']), "mensaje" => "Entrega anulada."]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>