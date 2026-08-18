<?php
// Iniciar sesión para el seguimiento de CSRF y control de fuerza bruta
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();

// Cabeceras de seguridad HTTP
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin');
header('Content-Type: application/json');

require_once '../config/conexion.php';
require_once '../entities/Persona.php';
require_once '../entities/Usuario.php';
require_once '../models/M_Usuario.php';
require_once '../models/M_IntentosLogin.php';

$modeloIntentos = M_IntentosLogin::singleton();

// 1. Recibir los datos del fetch (JS)
$json = file_get_contents('php://input');
$datos = json_decode($json);

// Protección contra fuerza bruta: el contador vive en BD indexado por el usuario
// tecleado, NO en $_SESSION — la sesión la controla el cliente, así que bastaba con
// descartar la cookie en cada intento para tener reintentos infinitos.
$usernameIntento = isset($datos->username) ? trim($datos->username) : '';
$minutosRestantes = $modeloIntentos->minutosBloqueoRestantes('staff', $usernameIntento);
if ($minutosRestantes > 0) {
    echo json_encode(["success" => false, "mensaje" => "Cuenta bloqueada temporalmente por demasiados intentos fallidos. Intenta en $minutosRestantes minuto(s)."]);
    exit;
}

// 2. Comprobar que los datos vengan en el JSON
if (isset($datos->username) && isset($datos->password) && isset($datos->csrf_token)) {
    // Verificación de token CSRF
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $datos->csrf_token)) {
        echo json_encode(["success" => false, "mensaje" => "Token de seguridad inválido. Recarga la página."]);
        exit;
    }

    $username = trim($datos->username);
    $password_ingresada = $datos->password;

    // 3. Instanciar el DAO y buscar el usuario en la BD
    $modeloUsuario = M_Usuario::singleton();
    $usuario = $modeloUsuario->verificarLogin($username);

    // 4. Verificación segura de credenciales
    if ($usuario && $usuario['estado'] == 1 && password_verify($password_ingresada, $usuario['password'])) {
        
        // Iniciamos la sesión segura en el servidor
        session_regenerate_id(true); // Regenerar ID de sesión para prevenir Session Fixation
        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nombres'] = $usuario['nombres_razon_social'];
        $_SESSION['rol'] = $usuario['rol'];
        
        // Restablecer el contador de intentos de fuerza bruta
        $modeloIntentos->limpiar('staff', $username);
        unset($_SESSION['csrf_token']); // Consumir el token CSRF una vez usado

        // Enviamos respuesta afirmativa
        echo json_encode([
            "success" => true, 
            "mensaje" => "Bienvenido " . $usuario['nombres_razon_social']
        ]);

    } else {
        // Falló el login
        $modeloIntentos->registrarFallo('staff', $username);
        echo json_encode([
            "success" => false, 
            "mensaje" => "Usuario o contraseña incorrectos, o cuenta inactiva."
        ]);
    }
} else {
    // Faltaron parámetros
    echo json_encode(["success" => false, "mensaje" => "Faltan datos requeridos."]);
}
?>