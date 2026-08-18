<?php
/**
 * controllers/C_ClienteAuth.php
 * Registro/login/logout/verificación/recuperación de contraseña para clientes
 * de la tienda online. Sesión bajo $_SESSION['id_cliente'] — separada de
 * $_SESSION['id_usuario'] (staff) para que ambas convivan sin pisarse.
 */
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin');
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/models/M_ClienteWeb.php';
require_once dirname(__DIR__) . '/models/M_Mailer.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$model = M_ClienteWeb::singleton();

// Regla de fuerza de contraseña — misma que C_Usuario.php (staff)
const REGLA_PASSWORD = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

switch ($action) {

    // ─────────────────────────────────────────────────────────────
    case 'registrar':
        $numero_documento = trim($input['numero_documento'] ?? '');
        $nombres = trim($input['nombres_razon_social'] ?? '');
        $apellidos = trim($input['apellidos'] ?? '');
        $telefono = trim($input['telefono'] ?? '');
        $direccion = trim($input['direccion'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (empty($numero_documento) || empty($nombres) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'mensaje' => 'Completa todos los campos obligatorios.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'mensaje' => 'El correo no es válido.']);
            exit;
        }
        if (!preg_match(REGLA_PASSWORD, $password)) {
            echo json_encode(['success' => false, 'mensaje' => 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un símbolo.']);
            exit;
        }

        $res = $model->registrarCuenta([
            'numero_documento'     => $numero_documento,
            'nombres_razon_social' => $nombres,
            'apellidos'            => $apellidos,
            'telefono'             => $telefono,
            'direccion'            => $direccion,
            'email'                => $email,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if (!$res['ok']) {
            echo json_encode(['success' => false, 'mensaje' => $res['mensaje']]);
            exit;
        }

        $envio = M_Mailer::singleton()->enviarCodigoVerificacion($email, $res['nombre'], $res['codigo']);
        echo json_encode([
            'success' => true,
            'mensaje' => $envio['ok']
                ? 'Cuenta creada. Revisa tu correo para el código de verificación.'
                : 'Cuenta creada, pero no pudimos enviar el correo de verificación. Usa "Reenviar código" en unos minutos.',
        ]);
        break;

    // ─────────────────────────────────────────────────────────────
    case 'verificar_email':
        $email = strtolower(trim($input['email'] ?? ''));
        $codigo = trim($input['codigo'] ?? '');

        if (empty($email) || empty($codigo)) {
            echo json_encode(['success' => false, 'mensaje' => 'Faltan datos.']);
            exit;
        }

        if (!$model->verificarCodigoEmail($email, $codigo)) {
            echo json_encode(['success' => false, 'mensaje' => 'Código inválido o vencido.']);
            exit;
        }

        $cliente = $model->obtenerPorEmail($email);
        session_regenerate_id(true);
        $_SESSION['id_cliente'] = (int) $cliente['id_cliente_web'];
        $_SESSION['cliente_nombre'] = $cliente['nombres_razon_social'];
        $_SESSION['cliente_email'] = $cliente['email'];

        echo json_encode(['success' => true, 'mensaje' => 'Cuenta verificada. Bienvenido, ' . $cliente['nombres_razon_social'] . '.']);
        break;

    // ─────────────────────────────────────────────────────────────
    case 'reenviar_codigo':
        $email = strtolower(trim($input['email'] ?? ''));
        if (empty($email)) {
            echo json_encode(['success' => false, 'mensaje' => 'Falta el correo.']);
            exit;
        }

        $res = $model->reenviarCodigoVerificacion($email);
        if (!$res['ok']) {
            echo json_encode(['success' => false, 'mensaje' => $res['mensaje']]);
            exit;
        }

        M_Mailer::singleton()->enviarCodigoVerificacion($email, $res['nombre'], $res['codigo']);
        echo json_encode(['success' => true, 'mensaje' => 'Código reenviado.']);
        break;

    // ─────────────────────────────────────────────────────────────
    case 'login':
        require_once dirname(__DIR__) . '/models/M_IntentosLogin.php';
        $modeloIntentos = M_IntentosLogin::singleton();

        $email = strtolower(trim($input['email'] ?? ''));

        // Contador en BD, no en $_SESSION: la sesión la controla el cliente, así que
        // bastaba con descartar la cookie en cada intento para reintentar sin límite.
        $min = $modeloIntentos->minutosBloqueoRestantes('cliente', $email);
        if ($min > 0) {
            echo json_encode(['success' => false, 'mensaje' => "Demasiados intentos fallidos. Intenta en $min minuto(s)."]);
            exit;
        }

        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'mensaje' => 'Token de seguridad inválido. Recarga la página.']);
            exit;
        }

        $password = (string) ($input['password'] ?? '');

        $cliente = $model->verificarLogin($email, $password);

        if (!$cliente) {
            $modeloIntentos->registrarFallo('cliente', $email);
            echo json_encode(['success' => false, 'mensaje' => 'Correo o contraseña incorrectos.']);
            exit;
        }

        if ((int) $cliente['email_verificado'] !== 1) {
            echo json_encode(['success' => false, 'mensaje' => 'Debes verificar tu correo antes de iniciar sesión.', 'no_verificado' => true]);
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['id_cliente'] = (int) $cliente['id_cliente_web'];
        $_SESSION['cliente_nombre'] = $cliente['nombres_razon_social'];
        $_SESSION['cliente_email'] = $cliente['email'];
        $modeloIntentos->limpiar('cliente', $email);
        unset($_SESSION['csrf_token']);

        echo json_encode(['success' => true, 'mensaje' => 'Bienvenido, ' . $cliente['nombres_razon_social']]);
        break;

    // ─────────────────────────────────────────────────────────────
    case 'logout':
        // Solo cierra la sesión del cliente — NUNCA session_destroy(), para no
        // afectar una sesión de staff que coexista en el mismo navegador.
        unset($_SESSION['id_cliente'], $_SESSION['cliente_nombre'], $_SESSION['cliente_email']);
        echo json_encode(['success' => true]);
        break;

    // ─────────────────────────────────────────────────────────────
    case 'solicitar_reset':
        $email = strtolower(trim($input['email'] ?? ''));
        if (!empty($email)) {
            $res = $model->generarCodigoReset($email);
            if ($res) {
                M_Mailer::singleton()->enviarCodigoReset($email, $res['nombre'], $res['codigo']);
            }
        }
        // Respuesta genérica siempre — no revela si el correo existe o no.
        echo json_encode(['success' => true, 'mensaje' => 'Si el correo existe en nuestro sistema, recibirás un código para restablecer tu contraseña.']);
        break;

    // ─────────────────────────────────────────────────────────────
    case 'confirmar_reset':
        $email = strtolower(trim($input['email'] ?? ''));
        $codigo = trim($input['codigo'] ?? '');
        $nuevaPassword = (string) ($input['password'] ?? '');

        if (empty($email) || empty($codigo) || empty($nuevaPassword)) {
            echo json_encode(['success' => false, 'mensaje' => 'Faltan datos.']);
            exit;
        }
        if (!preg_match(REGLA_PASSWORD, $nuevaPassword)) {
            echo json_encode(['success' => false, 'mensaje' => 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un símbolo.']);
            exit;
        }

        $ok = $model->resetearPassword($email, $codigo, password_hash($nuevaPassword, PASSWORD_DEFAULT));
        echo json_encode($ok
            ? ['success' => true, 'mensaje' => 'Contraseña actualizada. Ya puedes iniciar sesión.']
            : ['success' => false, 'mensaje' => 'Código inválido o vencido.']
        );
        break;

    // ─────────────────────────────────────────────────────────────
    case 'perfil':
        if (!isset($_SESSION['id_cliente'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autenticado.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'data' => [
                'id_cliente' => $_SESSION['id_cliente'],
                'nombre'     => $_SESSION['cliente_nombre'],
                'email'      => $_SESSION['cliente_email'],
            ],
        ]);
        break;

    // ─────────────────────────────────────────────────────────────
    // "Mi Cuenta": único cambio permitido desde ahí es la contraseña — no se expone
    // edición de nombre, teléfono, dirección, DNI ni correo.
    case 'cambiar_password':
        if (!isset($_SESSION['id_cliente'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autenticado.']);
            exit;
        }

        $passwordActual = (string) ($input['password_actual'] ?? '');
        $passwordNueva  = (string) ($input['password_nueva'] ?? '');

        if ($passwordActual === '' || $passwordNueva === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Completa ambos campos.']);
            exit;
        }
        if (!preg_match(REGLA_PASSWORD, $passwordNueva)) {
            echo json_encode(['success' => false, 'mensaje' => 'La nueva contraseña debe tener al menos 8 caracteres, una mayúscula, un número y un símbolo.']);
            exit;
        }

        $resultado = $model->cambiarPassword((int) $_SESSION['id_cliente'], $passwordActual, password_hash($passwordNueva, PASSWORD_DEFAULT));

        echo json_encode(match ($resultado) {
            'ok' => ['success' => true, 'mensaje' => 'Contraseña actualizada correctamente.'],
            'password_actual_incorrecta' => ['success' => false, 'mensaje' => 'La contraseña actual no es correcta.'],
            default => ['success' => false, 'mensaje' => 'No se pudo actualizar la contraseña.'],
        });
        break;

    // ─────────────────────────────────────────────────────────────
    // Vista previa de RUC para el checkout (elegir Factura): consulta SUNAT y
    // muestra la razón social ANTES de pagar, pero no persiste nada. La resolución
    // que de verdad cuenta ocurre en C_Ecommerce.php?action=crear_pedido, en
    // servidor, sobre el RUC recibido — nunca sobre un id que mande el navegador.
    // Misma validación que ya usa el POS en C_Cliente.php (estado ACTIVO/HABIDO).
    case 'consultar_ruc':
        if (!isset($_SESSION['id_cliente'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Debes iniciar sesión.']);
            exit;
        }

        $ruc = trim((string) ($input['ruc'] ?? ''));
        if (!ctype_digit($ruc) || strlen($ruc) !== 11) {
            echo json_encode(['success' => false, 'mensaje' => 'El RUC debe tener 11 dígitos.']);
            exit;
        }

        $apiConfig = require dirname(__DIR__) . '/config/api.php';
        $token = $apiConfig['apiperu_token'] ?? '';
        if (empty($token)) {
            echo json_encode(['success' => false, 'mensaje' => 'Servicio de consulta no disponible.']);
            exit;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://apiperu.dev/api/ruc',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_POSTFIELDS     => json_encode(['ruc' => $ruc]),
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo json_encode(['success' => false, 'mensaje' => 'Error de conexión con el servicio de consulta.']);
            exit;
        }

        $resData = json_decode($response, true);
        if (!$resData || !isset($resData['success']) || !$resData['success']) {
            $msg = $resData['message'] ?? 'RUC no encontrado en el padrón de SUNAT.';
            echo json_encode(['success' => false, 'mensaje' => $msg]);
            exit;
        }

        $apiData   = $resData['data'];
        $estado    = strtoupper(trim($apiData['estado'] ?? ''));
        $condicion = strtoupper(trim($apiData['condicion'] ?? ''));

        if (!empty($estado) && $estado !== 'ACTIVO') {
            echo json_encode(['success' => false, 'mensaje' => 'El RUC tiene estado no activo: ' . $estado . '.']);
            exit;
        }
        if (!empty($condicion) && $condicion !== 'HABIDO') {
            echo json_encode(['success' => false, 'mensaje' => 'El RUC tiene condición de domicilio: ' . $condicion . '.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data'    => [
                'razon_social' => $apiData['nombre_o_razon_social'] ?? '',
                'direccion'    => $apiData['direccion'] ?? '',
            ],
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida.']);
}
