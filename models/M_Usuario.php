<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Usuario.php';

/**
 * Modelo para la gestión de Usuarios y Login.
 * La lógica de sp_registrar_usuario ha sido migrada a PHP/PDO nativo.
 */
class M_Usuario {
    private static $instancia = null;
    private $conexion;

    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    public static function singleton() {
        if (!isset(self::$instancia)) {
            $miclase = __CLASS__;
            self::$instancia = new $miclase;
        }
        return self::$instancia;
    }

    /**
     * Registra un nuevo usuario con su persona asociada de forma transaccional.
     * Equivalente a: sp_registrar_usuario
     *
     * Flujo:
     *   1. Busca persona por número de documento.
     *   2a. Si existe y ya tiene usuario → lanza excepción (documento duplicado).
     *   2b. Si existe pero sin usuario → actualiza datos de la persona.
     *   2c. Si no existe → inserta nueva persona.
     *   3. Inserta fila en 'usuarios' vinculada a la persona.
     *
     * @param Usuario $usuario Entidad con datos personales, credenciales y rol
     * @return array ['ok' => bool, 'mensaje' => string]
     */
    public function registrarUsuario(Usuario $usuario) {
        try {
            $this->conexion->beginTransaction();

            // PASO 1: Buscar persona por documento
            $stmtBuscar = $this->conexion->prepare(
                "SELECT id_persona FROM personas WHERE numero_documento = ? LIMIT 1"
            );
            $stmtBuscar->execute([$usuario->numero_documento]);
            $persona = $stmtBuscar->fetch();

            if ($persona !== false) {
                $id_persona = $persona['id_persona'];

                // PASO 2a: ¿Ya tiene usuario asignado?
                $stmtExiste = $this->conexion->prepare(
                    "SELECT COUNT(*) AS total FROM usuarios WHERE id_persona = ?"
                );
                $stmtExiste->execute([$id_persona]);
                if ((int) $stmtExiste->fetch()['total'] > 0) {
                    throw new Exception('Error: Este documento ya pertenece a un usuario del sistema.');
                }

                // PASO 2b: Actualizar datos de persona existente
                $stmtUpd = $this->conexion->prepare(
                    "UPDATE personas SET nombres_razon_social=?, apellidos=?, direccion=?, telefono=?, estado=1
                     WHERE id_persona=?"
                );
                $stmtUpd->execute([
                    $usuario->nombres_razon_social, $usuario->apellidos,
                    $usuario->direccion, $usuario->telefono, $id_persona
                ]);
            } else {
                // PASO 2c: Insertar nueva persona
                $stmtIns = $this->conexion->prepare(
                    "INSERT INTO personas (tipo_documento, numero_documento, nombres_razon_social, apellidos, direccion, telefono, estado)
                     VALUES (?, ?, ?, ?, ?, ?, 1)"
                );
                $stmtIns->execute([
                    $usuario->tipo_documento, $usuario->numero_documento,
                    $usuario->nombres_razon_social, $usuario->apellidos,
                    $usuario->direccion, $usuario->telefono
                ]);
                $id_persona = (int) $this->conexion->lastInsertId();
            }

            // PASO 3: Insertar usuario
            $stmtUser = $this->conexion->prepare(
                "INSERT INTO usuarios (id_persona, id_rol, username, password) VALUES (?, ?, ?, ?)"
            );
            $stmtUser->execute([$id_persona, $usuario->id_rol, $usuario->username, $usuario->password]);

            $this->conexion->commit();
            return ['ok' => true, 'mensaje' => 'Usuario registrado correctamente.'];

        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Obtiene el listado de todos los usuarios activos.
     * @return array
     */
    public function listarUsuarios() {
        try {
            $sql = "SELECT p.tipo_documento, p.nombres_razon_social, p.apellidos, p.numero_documento, p.telefono,
                        u.id_usuario, u.id_rol, u.username, r.nombre AS rol, u.estado
                    FROM usuarios u
                    INNER JOIN personas p ON u.id_persona = p.id_persona
                    INNER JOIN roles r ON u.id_rol = r.id_rol
                    WHERE u.estado = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Busca los datos completos de un usuario por ID.
     * @param int $id_usuario
     * @return array|false
     */
    public function obtenerUsuarioPorId($id_usuario) {
        try {
            $sql = "SELECT p.*, u.*, r.nombre AS rol
                    FROM usuarios u
                    INNER JOIN personas p ON u.id_persona = p.id_persona
                    INNER JOIN roles r ON u.id_rol = r.id_rol
                    WHERE u.id_usuario = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(1, $id_usuario);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Actualiza información personal y datos de usuario (rol/username) en transacción.
     * @param Usuario $usuario
     * @return bool
     */
    public function actualizarUsuario(Usuario $usuario) {
        try {
            $this->conexion->beginTransaction();

            $stmt = $this->conexion->prepare(
                "UPDATE personas SET tipo_documento=?, numero_documento=?, nombres_razon_social=?, apellidos=?, telefono=?, direccion=?
                 WHERE id_persona = (SELECT id_persona FROM usuarios WHERE id_usuario=?)"
            );
            $stmt->execute([
                $usuario->tipo_documento, $usuario->numero_documento,
                $usuario->nombres_razon_social, $usuario->apellidos,
                $usuario->telefono, $usuario->direccion, $usuario->id_usuario
            ]);

            $stmt = $this->conexion->prepare(
                "UPDATE usuarios SET id_rol=?, username=? WHERE id_usuario=?"
            );
            $stmt->execute([$usuario->id_rol, $usuario->username, $usuario->id_usuario]);

            $this->conexion->commit();
            return true;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return false;
        }
    }

    /**
     * Desactivación lógica de un usuario.
     *
     * Apaga usuarios.estado, NO personas.estado: la misma fila de personas puede
     * tener además una cuenta de cliente en la tienda (M_Cliente filtra por
     * p.estado = 1), y dar de baja a un trabajador no debe borrarle su cuenta de
     * comprador ni sus pedidos. Ver migrations/add_usuarios_estado.sql.
     *
     * @param int $id_usuario
     * @param int $nuevoEstado
     * @return bool
     */
    public function EliminarUsuario($id_usuario, $nuevoEstado) {
        try {
            $stmt = $this->conexion->prepare("UPDATE usuarios SET estado=? WHERE id_usuario=?");
            $stmt->execute([$nuevoEstado, $id_usuario]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Cuántos Administradores activos quedan, excluyendo opcionalmente a uno.
     *
     * Existe para que no se pueda dejar al sistema sin ningún administrador: ni
     * borrando al último, ni degradándolo a Vendedor. Es una puerta sin llave por
     * fuera — no hay forma de recuperar el acceso desde la interfaz, solo tocando
     * la base de datos a mano.
     *
     * "Activo" se mide sobre usuarios.estado, que es lo que apaga EliminarUsuario().
     */
    public function contarAdministradoresActivos(?int $excluyendoIdUsuario = null): int {
        try {
            $sql = "SELECT COUNT(*) FROM usuarios u
                    INNER JOIN roles r ON u.id_rol = r.id_rol
                    WHERE r.nombre = 'Administrador' AND u.estado = 1";
            $params = [];
            if ($excluyendoIdUsuario !== null) {
                $sql .= " AND u.id_usuario <> ?";
                $params[] = $excluyendoIdUsuario;
            }
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Ante la duda, decir que no queda ninguno: bloquea la operación
            // peligrosa en vez de permitirla a ciegas.
            return 0;
        }
    }

    /**
     * Recupera hash de contraseña y datos del usuario para el Login.
     * Solo autentifica cuentas activas (p.estado = 1).
     * @param string $username
     * @return array|false
     */
    public function verificarLogin($username) {
        try {
            // El gate es usuarios.estado, no personas.estado: este último lo pone a 1
            // M_Cliente::registrarCuenta(), así que un ex-trabajador podía recuperar el
            // acceso al panel con solo registrarse como cliente en la tienda.
            $sql = "SELECT u.id_usuario, p.nombres_razon_social, p.apellidos, u.username, u.password, r.nombre AS rol, u.estado
                    FROM usuarios u
                    INNER JOIN personas p ON u.id_persona = p.id_persona
                    INNER JOIN roles r ON u.id_rol = r.id_rol
                    WHERE u.username = ? AND u.estado = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(1, $username);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>