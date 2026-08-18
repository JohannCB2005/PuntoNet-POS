<?php
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Modelo de cuentas de la tienda online (tabla clientes_web).
 *
 * A diferencia de M_Cliente (clientes POS, validados con RENIEC desde el módulo
 * de ventas), la cuenta web vive en su PROPIA tabla, autocontenida y SIN FK a
 * personas. El registro/login de la tienda NUNCA toca personas/clientes: ese fue
 * el bug original — registrar un DNI ya existente sobrescribía los nombres y
 * cambiaba el email/password de la cuenta ajena (robo de cuenta).
 *
 * Fix Parte A (sin consumo de API RENIEC):
 *   - DNI de 8 dígitos.
 *   - Email duplicado con cuenta verificada → rechazar.
 *   - DNI ya registrado en clientes_web → rechazar (una persona = una cuenta web).
 *   - Nunca se escriben personas/clientes.
 */
class M_ClienteWeb {
    /**
     * Intentos fallidos permitidos por código de 6 dígitos (verificación de correo
     * y reset de contraseña). Al alcanzarlo el código queda quemado y hay que pedir
     * uno nuevo por correo — eso es lo que hace inviable la fuerza bruta, no la
     * ventana de expiración por sí sola.
     */
    const MAX_INTENTOS_CODIGO = 5;

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
     * Registra una cuenta nueva de tienda online. Escribe SOLO en clientes_web.
     *
     * @param array $datos ['numero_documento','nombres_razon_social','apellidos','telefono','direccion','email','password_hash']
     * @return array ['ok'=>bool, 'mensaje'=>string, 'codigo'=>string, 'id_cliente_web'=>int, 'nombre'=>string]
     */
    public function registrarCuenta(array $datos) {
        try {
            // DNI de 8 dígitos (DNI de persona natural).
            $dni = trim((string) ($datos['numero_documento'] ?? ''));
            if (!preg_match('/^\d{8}$/', $dni)) {
                return ['ok' => false, 'mensaje' => 'El DNI debe tener exactamente 8 dígitos.'];
            }

            // Email duplicado con cuenta ya verificada → rechazar.
            $stmtEmail = $this->conexion->prepare("SELECT id_cliente_web, email_verificado FROM clientes_web WHERE email = ? LIMIT 1");
            $stmtEmail->execute([$datos['email']]);
            $existenteEmail = $stmtEmail->fetch(PDO::FETCH_ASSOC);
            if ($existenteEmail && (int) $existenteEmail['email_verificado'] === 1) {
                return ['ok' => false, 'mensaje' => 'Ya existe una cuenta verificada con este correo. Inicia sesión.'];
            }

            // DNI ya registrado en clientes_web → rechazar (una persona = una cuenta web),
            // esté o no verificado: dos cuentas para el mismo DNI son la puerta a que la
            // segunda haga pasar la cuenta del titular por suya.
            $stmtDni = $this->conexion->prepare("SELECT id_cliente_web FROM clientes_web WHERE numero_documento = ? LIMIT 1");
            $stmtDni->execute([$dni]);
            if ($stmtDni->fetch(PDO::FETCH_ASSOC)) {
                return ['ok' => false, 'mensaje' => 'Este DNI ya tiene una cuenta registrada. Inicia sesión o usa "recuperar contraseña".'];
            }

            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $stmt = $this->conexion->prepare(
                "INSERT INTO clientes_web
                    (email, password, email_verificado, codigo_verificacion, codigo_verificacion_expira,
                     numero_documento, nombres_razon_social, apellidos, telefono, direccion, fecha_registro, estado)
                 VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)"
            );
            $stmt->execute([
                $datos['email'],
                $datos['password_hash'],
                $codigo,
                $expira,
                $dni,
                $datos['nombres_razon_social'],
                $datos['apellidos'] ?? null,
                $datos['telefono'] ?? null,
                $datos['direccion'] ?? null,
            ]);
            $id_cliente_web = (int) $this->conexion->lastInsertId();

            return ['ok' => true, 'codigo' => $codigo, 'id_cliente_web' => $id_cliente_web, 'nombre' => $datos['nombres_razon_social']];
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'uq_cliente_web_email') !== false) {
                return ['ok' => false, 'mensaje' => 'Ese correo ya está en uso.'];
            }
            return ['ok' => false, 'mensaje' => 'Error al registrar la cuenta.'];
        }
    }

    /**
     * @return array|false Fila de la cuenta web o false si no existe
     */
    public function obtenerPorEmail(string $email) {
        try {
            $sql = "SELECT id_cliente_web, email, password, email_verificado,
                           codigo_verificacion, codigo_verificacion_expira, codigo_verificacion_intentos,
                           codigo_reset, codigo_reset_expira, codigo_reset_intentos,
                           numero_documento, nombres_razon_social, apellidos, telefono, direccion, estado
                    FROM clientes_web
                    WHERE email = ? LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /** Datos completos de la cuenta web según su id (para checkout / quién recoge). */
    public function obtenerPorId(int $id_cliente_web) {
        try {
            $sql = "SELECT id_cliente_web, email, email_verificado,
                           numero_documento, nombres_razon_social, apellidos, telefono, direccion, estado
                    FROM clientes_web
                    WHERE id_cliente_web = ? LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_cliente_web]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /** @return array|false Fila de la cuenta si las credenciales son válidas y está activa */
    public function verificarLogin(string $email, string $password) {
        $cuenta = $this->obtenerPorEmail($email);
        if (!$cuenta || (int) $cuenta['estado'] !== 1) return false;
        if (empty($cuenta['password']) || !password_verify($password, $cuenta['password'])) return false;
        return $cuenta;
    }

    public function verificarCodigoEmail(string $email, string $codigo): bool {
        try {
            $cuenta = $this->obtenerPorEmail($email);
            if (!$cuenta) return false;
            if ((int) $cuenta['email_verificado'] === 1) return true;
            if (empty($cuenta['codigo_verificacion'])) return false;

            // Un código de 6 dígitos con reintentos ilimitados se agota por fuerza
            // bruta dentro de su propia ventana de validez. El contador vive en BD y
            // no en $_SESSION a propósito: el atacante controla su cookie, así que un
            // contador de sesión (como el de login) se evade borrándola.
            if ((int) $cuenta['codigo_verificacion_intentos'] >= self::MAX_INTENTOS_CODIGO) {
                return false;
            }
            if (!hash_equals($cuenta['codigo_verificacion'], $codigo)
                || empty($cuenta['codigo_verificacion_expira'])
                || strtotime($cuenta['codigo_verificacion_expira']) < time()) {
                $this->conexion->prepare(
                    "UPDATE clientes_web SET codigo_verificacion_intentos = codigo_verificacion_intentos + 1 WHERE id_cliente_web = ?"
                )->execute([$cuenta['id_cliente_web']]);
                return false;
            }

            $stmt = $this->conexion->prepare(
                "UPDATE clientes_web SET email_verificado=1, codigo_verificacion=NULL, codigo_verificacion_expira=NULL, codigo_verificacion_intentos=0 WHERE id_cliente_web=?"
            );
            $stmt->execute([$cuenta['id_cliente_web']]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** @return array ['ok'=>bool, 'mensaje'=>string, 'codigo'=>string, 'nombre'=>string] */
    public function reenviarCodigoVerificacion(string $email): array {
        $cuenta = $this->obtenerPorEmail($email);
        if (!$cuenta) return ['ok' => false, 'mensaje' => 'No existe una cuenta con ese correo.'];
        if ((int) $cuenta['email_verificado'] === 1) return ['ok' => false, 'mensaje' => 'Esta cuenta ya está verificada.'];

        if (!empty($cuenta['codigo_verificacion_expira'])) {
            $emitidoHaceSegundos = time() - (strtotime($cuenta['codigo_verificacion_expira']) - 1800);
            if ($emitidoHaceSegundos < 60) {
                return ['ok' => false, 'mensaje' => 'Espera unos segundos antes de pedir otro código.'];
            }
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        try {
            // Reiniciar el contador junto con el código nuevo: si no, un usuario
            // legítimo que agotó los intentos quedaría bloqueado para siempre.
            $stmt = $this->conexion->prepare("UPDATE clientes_web SET codigo_verificacion=?, codigo_verificacion_expira=?, codigo_verificacion_intentos=0 WHERE id_cliente_web=?");
            $stmt->execute([$codigo, $expira, $cuenta['id_cliente_web']]);
            return ['ok' => true, 'codigo' => $codigo, 'nombre' => $cuenta['nombres_razon_social']];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al generar el código.'];
        }
    }

    /** @return array|null ['codigo'=>string,'nombre'=>string] o null si el email no existe (el controller responde genérico igual) */
    public function generarCodigoReset(string $email): ?array {
        $cuenta = $this->obtenerPorEmail($email);
        if (!$cuenta || empty($cuenta['password'])) return null;

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        try {
            // Igual que en la verificación: el código nuevo trae contador limpio.
            $stmt = $this->conexion->prepare("UPDATE clientes_web SET codigo_reset=?, codigo_reset_expira=?, codigo_reset_intentos=0 WHERE id_cliente_web=?");
            $stmt->execute([$codigo, $expira, $cuenta['id_cliente_web']]);
            return ['codigo' => $codigo, 'nombre' => $cuenta['nombres_razon_social']];
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Cambio de contraseña desde "Mi Cuenta" (cliente ya logueado): exige conocer la
     * contraseña actual, a diferencia del reset por correo.
     *
     * @return string 'ok' | 'no_encontrado' | 'password_actual_incorrecta'
     */
    public function cambiarPassword(int $id_cliente_web, string $passwordActual, string $passwordNuevaHash): string {
        try {
            $stmt = $this->conexion->prepare("SELECT password FROM clientes_web WHERE id_cliente_web = ?");
            $stmt->execute([$id_cliente_web]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cuenta || empty($cuenta['password'])) {
                return 'no_encontrado';
            }
            if (!password_verify($passwordActual, $cuenta['password'])) {
                return 'password_actual_incorrecta';
            }

            $upd = $this->conexion->prepare("UPDATE clientes_web SET password = ? WHERE id_cliente_web = ?");
            $upd->execute([$passwordNuevaHash, $id_cliente_web]);
            return 'ok';
        } catch (PDOException $e) {
            return 'no_encontrado';
        }
    }

    public function resetearPassword(string $email, string $codigo, string $nuevaPasswordHash): bool {
        try {
            $cuenta = $this->obtenerPorEmail($email);
            if (!$cuenta || empty($cuenta['codigo_reset'])) return false;

            // Mismo razonamiento que verificarCodigoEmail(), y aquí importa más: acertar
            // este código entrega la cuenta directamente. Al llegar al tope el código
            // queda quemado — hay que pedir uno nuevo por correo, que es justo lo que
            // vuelve inviable la fuerza bruta.
            if ((int) $cuenta['codigo_reset_intentos'] >= self::MAX_INTENTOS_CODIGO) {
                return false;
            }
            if (!hash_equals($cuenta['codigo_reset'], $codigo)
                || empty($cuenta['codigo_reset_expira'])
                || strtotime($cuenta['codigo_reset_expira']) < time()) {
                $this->conexion->prepare(
                    "UPDATE clientes_web SET codigo_reset_intentos = codigo_reset_intentos + 1 WHERE id_cliente_web = ?"
                )->execute([$cuenta['id_cliente_web']]);
                return false;
            }

            $stmt = $this->conexion->prepare("UPDATE clientes_web SET password=?, codigo_reset=NULL, codigo_reset_expira=NULL, codigo_reset_intentos=0 WHERE id_cliente_web=?");
            $stmt->execute([$nuevaPasswordHash, $cuenta['id_cliente_web']]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>