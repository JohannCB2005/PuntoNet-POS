<?php
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/config/csrf.php';
csrfRequerir();

require_once dirname(__DIR__) . '/entities/Usuario.php';
require_once dirname(__DIR__) . '/models/M_Usuario.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$model = M_Usuario::singleton();

switch ($action) {
    case 'listar':
        echo json_encode($model->listarUsuarios());
        break;

    case 'crear':
        $tipo_documento = isset($input['tipo_documento']) ? intval($input['tipo_documento']) : 1;
        $numero_documento = isset($input['numero_documento']) ? trim($input['numero_documento']) : '';
        $nombres_razon_social = isset($input['nombres_razon_social']) ? trim($input['nombres_razon_social']) : '';
        $apellidos = isset($input['apellidos']) ? trim($input['apellidos']) : '';
        $direccion = isset($input['direccion']) ? trim($input['direccion']) : '';
        $telefono = isset($input['telefono']) ? trim($input['telefono']) : '';
        $id_rol = isset($input['id_rol']) ? intval($input['id_rol']) : 2; // Por defecto Vendedor
        $username = isset($input['username']) ? trim($input['username']) : '';
        $password = isset($input['password']) ? trim($input['password']) : '';

        if (empty($numero_documento) || empty($nombres_razon_social) || empty($username) || empty($password)) {
            echo json_encode(["success" => false, "mensaje" => "Documento, nombre, usuario y contraseña son obligatorios."]);
            exit;
        }

        // Validar la fortaleza de la contraseña
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            echo json_encode(["success" => false, "mensaje" => "La contraseña debe tener al menos 8 caracteres, incluir 1 mayúscula, 1 número y 1 carácter especial."]);
            exit;
        }

        // Encriptar la contraseña de forma segura
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $usuario = new Usuario($tipo_documento, $numero_documento, $nombres_razon_social, $apellidos, $direccion, $telefono, $id_rol, $username, $hashedPassword);
        
        $resultado = $model->registrarUsuario($usuario);
        if ($resultado['ok']) {
            echo json_encode(["success" => true, "mensaje" => "Usuario registrado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => $resultado['mensaje']]);
        }
        break;

    case 'actualizar':
        $id_usuario = isset($input['id_usuario']) ? intval($input['id_usuario']) : 0;
        $tipo_documento = isset($input['tipo_documento']) ? intval($input['tipo_documento']) : 1;
        $numero_documento = isset($input['numero_documento']) ? trim($input['numero_documento']) : '';
        $nombres_razon_social = isset($input['nombres_razon_social']) ? trim($input['nombres_razon_social']) : '';
        $apellidos = isset($input['apellidos']) ? trim($input['apellidos']) : '';
        $direccion = isset($input['direccion']) ? trim($input['direccion']) : '';
        $telefono = isset($input['telefono']) ? trim($input['telefono']) : '';
        $id_rol = isset($input['id_rol']) ? intval($input['id_rol']) : 2;
        $username = isset($input['username']) ? trim($input['username']) : '';

        if ($id_usuario <= 0 || empty($numero_documento) || empty($nombres_razon_social) || empty($username)) {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos o incompletos."]);
            exit;
        }

        // No dejar al sistema sin Administradores: degradar al último a Vendedor
        // cierra la puerta por fuera, sin forma de recuperarla desde la interfaz.
        // ROL_ADMINISTRADOR = 1 (tabla roles).
        if ($id_rol !== 1 && $model->contarAdministradoresActivos($id_usuario) === 0) {
            echo json_encode(["success" => false, "mensaje" => "No puedes quitarle el rol de Administrador al único administrador activo: el sistema quedaría sin acceso de administración."]);
            exit;
        }

        $usuario = new Usuario($tipo_documento, $numero_documento, $nombres_razon_social, $apellidos, $direccion, $telefono, $id_rol, $username, '');
        $usuario->id_usuario = $id_usuario;

        if ($model->actualizarUsuario($usuario)) {
            // Opcional: Si se proporciona una contraseña, la actualizamos por separado.
            $password = isset($input['password']) ? trim($input['password']) : '';
            if (!empty($password)) {
                if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                    echo json_encode(["success" => false, "mensaje" => "Usuario actualizado correctamente, pero la contraseña no se cambió: debe tener al menos 8 caracteres, 1 mayúscula, 1 número y 1 símbolo."]);
                    exit;
                }
                try {
                    $dbh = Conexion::singleton()->getConexion();
                    $stmt = $dbh->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
                    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id_usuario]);
                } catch (Exception $e) {
                    // Ignorar o registrar error
                }
            }
            echo json_encode(["success" => true, "mensaje" => "Usuario actualizado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al actualizar el usuario."]);
        }
        break;

    case 'eliminar':
        $id_usuario = isset($input['id_usuario']) ? intval($input['id_usuario']) : 0;

        if ($id_usuario <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de usuario inválido."]);
            exit;
        }

        // Autoeliminación: aunque queden otros administradores, borrarse a uno mismo
        // cierra la sesión en curso de forma confusa. Que lo haga otro administrador.
        if ((int) $id_usuario === (int) $_SESSION['id_usuario']) {
            echo json_encode(["success" => false, "mensaje" => "No puedes eliminar tu propio usuario."]);
            exit;
        }

        // Y nunca al último administrador activo (ver contarAdministradoresActivos()).
        if ($model->contarAdministradoresActivos($id_usuario) === 0) {
            echo json_encode(["success" => false, "mensaje" => "No puedes eliminar al único administrador activo: el sistema quedaría sin acceso de administración."]);
            exit;
        }

        // Eliminación lógica (establecer estado a 0 en la tabla personas)
        if ($model->EliminarUsuario($id_usuario, 0)) {
            echo json_encode(["success" => true, "mensaje" => "Usuario eliminado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al eliminar el usuario."]);
        }
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
