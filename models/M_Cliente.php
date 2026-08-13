<?php
// Cargar conexión de base de datos y la entidad correspondiente
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/entities/Cliente.php';

/**
 * Modelo para la gestión de Clientes
 * La lógica de sp_registrar_cliente ha sido migrada a PHP/PDO nativo.
 * Interactúa con las tablas 'clientes' y 'personas' para registrar y actualizar clientes.
 */
class M_Cliente {
    /**
     * Intentos fallidos permitidos por código de 6 dígitos (verificación de correo
     * y reset de contraseña). Al alcanzarlo el código queda quemado y hay que pedir
     * uno nuevo por correo — eso es lo que hace inviable la fuerza bruta, no la
     * ventana de expiración por sí sola.
     */
    const MAX_INTENTOS_CODIGO = 5;

    // Instancia estática para el patrón Singleton
    private static $instancia = null;
    // Conexión PDO
    private $conexion;

    // Constructor privado
    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    // Obtener la instancia única del modelo
    public static function singleton() {
        if (!isset(self::$instancia)) {
            $miclase = __CLASS__;
            self::$instancia = new $miclase;
        }
        return self::$instancia;
    }

    /**
     * Registra o reactiva un cliente de forma transaccional.
     * Equivalente a: sp_registrar_cliente
     *
     * Flujo:
     *   1. Busca persona por número de documento.
     *   2a. Si NO existe → inserta persona + cliente nuevos.
     *   2b. Si existe:
     *       - Actualiza los datos de la persona y la reactiva (estado=1).
     *       - Si ya tiene registro de cliente → actualiza tipo_cliente.
     *       - Si no tiene registro de cliente → inserta en 'clientes'.
     *
     * @param Cliente $cliente Entidad cliente con los datos personales y de cliente
     * @return bool|string Retorna true si fue exitoso o el mensaje del error en caso contrario
     */
    public function registrarCliente(Cliente $cliente) {
        try {
            $this->conexion->beginTransaction();

            // PASO 1: Buscar persona por documento
            $stmtBuscar = $this->conexion->prepare(
                "SELECT id_persona FROM personas WHERE numero_documento = ? LIMIT 1"
            );
            $stmtBuscar->execute([$cliente->numero_documento]);
            $persona = $stmtBuscar->fetch();

            if ($persona === false) {
                // PASO 2a: Persona nueva → insertar persona y luego cliente
                $stmtPer = $this->conexion->prepare(
                    "INSERT INTO personas (tipo_documento, numero_documento, nombres_razon_social, apellidos, direccion, telefono, estado)
                     VALUES (?, ?, ?, ?, ?, ?, 1)"
                );
                $stmtPer->execute([
                    $cliente->tipo_documento, $cliente->numero_documento,
                    $cliente->nombres_razon_social, $cliente->apellidos,
                    $cliente->direccion, $cliente->telefono
                ]);
                $id_persona = (int) $this->conexion->lastInsertId();

                $stmtCli = $this->conexion->prepare(
                    "INSERT INTO clientes (id_persona, tipo_cliente) VALUES (?, ?)"
                );
                $stmtCli->execute([$id_persona, $cliente->tipo_cliente]);

            } else {
                // PASO 2b: Persona existente → actualizar y reactivar
                $id_persona = $persona['id_persona'];

                $stmtUpd = $this->conexion->prepare(
                    "UPDATE personas SET tipo_documento=?, nombres_razon_social=?, apellidos=?, direccion=?, telefono=?, estado=1
                     WHERE id_persona=?"
                );
                $stmtUpd->execute([
                    $cliente->tipo_documento, $cliente->nombres_razon_social,
                    $cliente->apellidos, $cliente->direccion,
                    $cliente->telefono, $id_persona
                ]);

                // ¿Ya existe como cliente?
                $stmtExiste = $this->conexion->prepare(
                    "SELECT id_cliente FROM clientes WHERE id_persona = ? LIMIT 1"
                );
                $stmtExiste->execute([$id_persona]);
                $clienteRow = $stmtExiste->fetch();

                if ($clienteRow === false) {
                    // No existía como cliente: insertar
                    $stmtCli = $this->conexion->prepare(
                        "INSERT INTO clientes (id_persona, tipo_cliente) VALUES (?, ?)"
                    );
                    $stmtCli->execute([$id_persona, $cliente->tipo_cliente]);
                } else {
                    // Ya existe: solo actualizar tipo y campos planilla
                    $stmtUpdCli = $this->conexion->prepare(
                        "UPDATE clientes SET tipo_cliente=? WHERE id_cliente=?"
                    );
                    $stmtUpdCli->execute([$cliente->tipo_cliente, $clienteRow['id_cliente']]);
                }
            }

            $this->conexion->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            // Retornar el string del error para que el controlador lo detalle al frontend
            return $e->getMessage();
        }
    }

    /**
     * Lista todos los clientes activos relacionando las tablas clientes y personas
     * @return array Listado asociativo con nombres, apellidos, documento y tipo de cliente
     */
    public function listarClientes() {
        try {
            // INNER JOIN para traer los datos humanos desde la tabla unificada de personas
            $sql = "SELECT p.*, c.id_cliente, c.tipo_cliente
                    FROM clientes c
                    INNER JOIN personas p ON c.id_persona = p.id_persona
                    WHERE p.estado = 1 AND c.tipo_cliente != 3";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtiene los datos completos de un cliente según su ID
     * @param int $id_cliente ID del cliente a buscar
     * @return array|false Fila de la base de datos o False si ocurre un error
     */
    public function obtenerClientePorId($id_cliente) {
        try {
            $sql = "SELECT p.*, c.* FROM clientes c
                    INNER JOIN personas p ON c.id_persona = p.id_persona
                    WHERE c.id_cliente = ?";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_cliente]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Busca y obtiene los datos de un cliente activo por su número de documento (DNI/RUC)
     * @param string $numero_documento Número de documento a consultar
     * @return array|false Fila de la base de datos o False si no existe o está inactivo
     */
    public function obtenerClientePorDocumento($numero_documento) {
        try {
            $sql = "SELECT p.*, c.id_cliente, c.tipo_cliente FROM clientes c
                    INNER JOIN personas p ON c.id_persona = p.id_persona
                    WHERE p.numero_documento = ? AND p.estado = 1";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$numero_documento]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Actualiza la información personal del cliente (tabla personas) y su tipo de cliente (tabla clientes)
     * Realiza una transacción de base de datos para garantizar la consistencia en ambas tablas.
     * @param Cliente $cliente Entidad cliente con los datos modificados
     * @return bool True en caso de éxito, False en caso de fallo
     */
    public function actualizarCliente(Cliente $cliente) {
        try {
            $this->conexion->beginTransaction();

            // 1. Actualizar la tabla general de personas relacionada al cliente
            $sql = "UPDATE personas SET nombres_razon_social = ?, apellidos = ?, direccion = ?, telefono = ? 
                     WHERE id_persona = (SELECT id_persona FROM clientes WHERE id_cliente = ?)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                $cliente->nombres_razon_social, $cliente->apellidos, 
                $cliente->direccion, $cliente->telefono, $cliente->id_cliente
            ]);

            // 2. Actualizar el tipo de cliente en la tabla clientes
            $sql = "UPDATE clientes SET tipo_cliente = ? WHERE id_cliente = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$cliente->tipo_cliente, $cliente->id_cliente]);

            $this->conexion->commit();
            return true;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return false;
        }
    }

    /**
     * Realiza la desactivación/borrado lógico de un cliente estableciendo el estado de su persona a 0
     * @param int $id_cliente ID del cliente a desactivar
     * @return bool True en caso de éxito, False si ocurre algún error
     */
    public function eliminarCliente($id_cliente) {
        try {
            $sql = "UPDATE personas SET estado = 0
                    WHERE id_persona = (SELECT id_persona FROM clientes WHERE id_cliente = ?)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$id_cliente]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // =========================================================================
    // Cuenta de cliente para la tienda online (email + contraseña)
    // =========================================================================

    /**
     * Registra (o reutiliza, si ya existía por DNI de una compra de invitado) la cuenta
     * de un cliente para la tienda online. Deja la cuenta sin verificar y genera el
     * código de verificación; el controlador es quien dispara el correo.
     *
     * @param array $datos ['numero_documento','nombres_razon_social','apellidos','telefono','direccion','email','password_hash']
     * @return array ['ok'=>bool, 'mensaje'=>string, 'codigo'=>string, 'id_cliente'=>int, 'nombre'=>string]
     */
    public function registrarCuenta(array $datos) {
        try {
            $stmtEmail = $this->conexion->prepare("SELECT id_cliente, email_verificado FROM clientes WHERE email = ? LIMIT 1");
            $stmtEmail->execute([$datos['email']]);
            $existenteEmail = $stmtEmail->fetch(PDO::FETCH_ASSOC);
            if ($existenteEmail && (int) $existenteEmail['email_verificado'] === 1) {
                return ['ok' => false, 'mensaje' => 'Ya existe una cuenta verificada con este correo. Inicia sesión.'];
            }

            $this->conexion->beginTransaction();

            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // Buscar persona por DNI: puede existir de una compra de invitado previa a este feature
            $stmtPersona = $this->conexion->prepare("SELECT id_persona FROM personas WHERE numero_documento = ? LIMIT 1");
            $stmtPersona->execute([$datos['numero_documento']]);
            $persona = $stmtPersona->fetch(PDO::FETCH_ASSOC);

            if ($persona) {
                $id_persona = $persona['id_persona'];
                $stmtUpd = $this->conexion->prepare(
                    "UPDATE personas SET nombres_razon_social=?, apellidos=?, telefono=COALESCE(telefono,?), direccion=COALESCE(direccion,?), estado=1
                     WHERE id_persona=?"
                );
                $stmtUpd->execute([$datos['nombres_razon_social'], $datos['apellidos'], $datos['telefono'], $datos['direccion'], $id_persona]);
            } else {
                $stmtIns = $this->conexion->prepare(
                    "INSERT INTO personas (tipo_documento, numero_documento, nombres_razon_social, apellidos, telefono, direccion, estado)
                     VALUES (1, ?, ?, ?, ?, ?, 1)"
                );
                $stmtIns->execute([$datos['numero_documento'], $datos['nombres_razon_social'], $datos['apellidos'], $datos['telefono'], $datos['direccion']]);
                $id_persona = (int) $this->conexion->lastInsertId();
            }

            // ¿Ya existe fila en `clientes` (invitado anterior)?
            $stmtCli = $this->conexion->prepare("SELECT id_cliente FROM clientes WHERE id_persona = ? LIMIT 1");
            $stmtCli->execute([$id_persona]);
            $clienteRow = $stmtCli->fetch(PDO::FETCH_ASSOC);

            if ($clienteRow) {
                $id_cliente = (int) $clienteRow['id_cliente'];
                $stmtUpdCli = $this->conexion->prepare(
                    "UPDATE clientes SET email=?, password=?, email_verificado=0, codigo_verificacion=?, codigo_verificacion_expira=?,
                            fecha_registro=COALESCE(fecha_registro, NOW())
                     WHERE id_cliente=?"
                );
                $stmtUpdCli->execute([$datos['email'], $datos['password_hash'], $codigo, $expira, $id_cliente]);
            } else {
                $stmtInsCli = $this->conexion->prepare(
                    "INSERT INTO clientes (id_persona, tipo_cliente, email, password, email_verificado, codigo_verificacion, codigo_verificacion_expira, fecha_registro)
                     VALUES (?, 1, ?, ?, 0, ?, ?, NOW())"
                );
                $stmtInsCli->execute([$id_persona, $datos['email'], $datos['password_hash'], $codigo, $expira]);
                $id_cliente = (int) $this->conexion->lastInsertId();
            }

            $this->conexion->commit();
            return ['ok' => true, 'codigo' => $codigo, 'id_cliente' => $id_cliente, 'nombre' => $datos['nombres_razon_social']];
        } catch (PDOException $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            if (stripos($e->getMessage(), 'uq_cliente_email') !== false) {
                return ['ok' => false, 'mensaje' => 'Ese correo ya está en uso.'];
            }
            return ['ok' => false, 'mensaje' => 'Error al registrar la cuenta.'];
        }
    }

    public function obtenerPorEmail(string $email) {
        try {
            $sql = "SELECT c.id_cliente, c.email, c.password, c.email_verificado,
                           c.codigo_verificacion, c.codigo_verificacion_expira, c.codigo_verificacion_intentos,
                           c.codigo_reset, c.codigo_reset_expira, c.codigo_reset_intentos,
                           p.id_persona, p.nombres_razon_social, p.apellidos, p.numero_documento, p.telefono, p.estado
                    FROM clientes c
                    INNER JOIN personas p ON c.id_persona = p.id_persona
                    WHERE c.email = ? LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /** @return array|false Fila del cliente si las credenciales son válidas y la persona está activa */
    public function verificarLogin(string $email, string $password) {
        $cliente = $this->obtenerPorEmail($email);
        if (!$cliente || (int) $cliente['estado'] !== 1) return false;
        if (empty($cliente['password']) || !password_verify($password, $cliente['password'])) return false;
        return $cliente;
    }

    public function verificarCodigoEmail(string $email, string $codigo): bool {
        try {
            $cliente = $this->obtenerPorEmail($email);
            if (!$cliente) return false;
            if ((int) $cliente['email_verificado'] === 1) return true;
            if (empty($cliente['codigo_verificacion'])) return false;

            // Un código de 6 dígitos con reintentos ilimitados se agota por fuerza
            // bruta dentro de su propia ventana de validez. El contador vive en BD y
            // no en $_SESSION a propósito: el atacante controla su cookie, así que un
            // contador de sesión (como el de login) se evade borrándola.
            if ((int) $cliente['codigo_verificacion_intentos'] >= self::MAX_INTENTOS_CODIGO) {
                return false;
            }
            if (!hash_equals($cliente['codigo_verificacion'], $codigo)
                || empty($cliente['codigo_verificacion_expira'])
                || strtotime($cliente['codigo_verificacion_expira']) < time()) {
                $this->conexion->prepare(
                    "UPDATE clientes SET codigo_verificacion_intentos = codigo_verificacion_intentos + 1 WHERE id_cliente = ?"
                )->execute([$cliente['id_cliente']]);
                return false;
            }

            $stmt = $this->conexion->prepare(
                "UPDATE clientes SET email_verificado=1, codigo_verificacion=NULL, codigo_verificacion_expira=NULL, codigo_verificacion_intentos=0 WHERE id_cliente=?"
            );
            $stmt->execute([$cliente['id_cliente']]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** @return array ['ok'=>bool, 'mensaje'=>string, 'codigo'=>string, 'nombre'=>string] */
    public function reenviarCodigoVerificacion(string $email): array {
        $cliente = $this->obtenerPorEmail($email);
        if (!$cliente) return ['ok' => false, 'mensaje' => 'No existe una cuenta con ese correo.'];
        if ((int) $cliente['email_verificado'] === 1) return ['ok' => false, 'mensaje' => 'Esta cuenta ya está verificada.'];

        if (!empty($cliente['codigo_verificacion_expira'])) {
            $emitidoHaceSegundos = time() - (strtotime($cliente['codigo_verificacion_expira']) - 1800);
            if ($emitidoHaceSegundos < 60) {
                return ['ok' => false, 'mensaje' => 'Espera unos segundos antes de pedir otro código.'];
            }
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        try {
            // Reiniciar el contador junto con el código nuevo: si no, un usuario
            // legítimo que agotó los intentos quedaría bloqueado para siempre.
            $stmt = $this->conexion->prepare("UPDATE clientes SET codigo_verificacion=?, codigo_verificacion_expira=?, codigo_verificacion_intentos=0 WHERE id_cliente=?");
            $stmt->execute([$codigo, $expira, $cliente['id_cliente']]);
            return ['ok' => true, 'codigo' => $codigo, 'nombre' => $cliente['nombres_razon_social']];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al generar el código.'];
        }
    }

    /** @return array|null ['codigo'=>string,'nombre'=>string] o null si el email no existe (el controller responde genérico igual) */
    public function generarCodigoReset(string $email): ?array {
        $cliente = $this->obtenerPorEmail($email);
        if (!$cliente || empty($cliente['password'])) return null;

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        try {
            // Igual que en la verificación: el código nuevo trae contador limpio.
            $stmt = $this->conexion->prepare("UPDATE clientes SET codigo_reset=?, codigo_reset_expira=?, codigo_reset_intentos=0 WHERE id_cliente=?");
            $stmt->execute([$codigo, $expira, $cliente['id_cliente']]);
            return ['codigo' => $codigo, 'nombre' => $cliente['nombres_razon_social']];
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Cambio de contraseña desde "Mi Cuenta" (cliente ya logueado): exige conocer la
     * contraseña actual, a diferencia del reset por correo. Devuelve el motivo del
     * rechazo para que el controlador lo traduzca al mensaje del usuario.
     *
     * @return string 'ok' | 'no_encontrado' | 'password_actual_incorrecta'
     */
    public function cambiarPassword(int $id_cliente, string $passwordActual, string $passwordNuevaHash): string {
        try {
            $stmt = $this->conexion->prepare("SELECT password FROM clientes WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente || empty($cliente['password'])) {
                return 'no_encontrado';
            }
            if (!password_verify($passwordActual, $cliente['password'])) {
                return 'password_actual_incorrecta';
            }

            $upd = $this->conexion->prepare("UPDATE clientes SET password = ? WHERE id_cliente = ?");
            $upd->execute([$passwordNuevaHash, $id_cliente]);
            return 'ok';
        } catch (PDOException $e) {
            return 'no_encontrado';
        }
    }

    public function resetearPassword(string $email, string $codigo, string $nuevaPasswordHash): bool {
        try {
            $cliente = $this->obtenerPorEmail($email);
            if (!$cliente || empty($cliente['codigo_reset'])) return false;

            // Mismo razonamiento que verificarCodigoEmail(), y aquí importa más: acertar
            // este código entrega la cuenta directamente. Al llegar al tope el código
            // queda quemado — hay que pedir uno nuevo por correo, que es justo lo que
            // vuelve inviable la fuerza bruta.
            if ((int) $cliente['codigo_reset_intentos'] >= self::MAX_INTENTOS_CODIGO) {
                return false;
            }
            if (!hash_equals($cliente['codigo_reset'], $codigo)
                || empty($cliente['codigo_reset_expira'])
                || strtotime($cliente['codigo_reset_expira']) < time()) {
                $this->conexion->prepare(
                    "UPDATE clientes SET codigo_reset_intentos = codigo_reset_intentos + 1 WHERE id_cliente = ?"
                )->execute([$cliente['id_cliente']]);
                return false;
            }

            $stmt = $this->conexion->prepare("UPDATE clientes SET password=?, codigo_reset=NULL, codigo_reset_expira=NULL, codigo_reset_intentos=0 WHERE id_cliente=?");
            $stmt->execute([$nuevaPasswordHash, $cliente['id_cliente']]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>