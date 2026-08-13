<?php
require_once dirname(__DIR__) . '/config/conexion.php';

/**
 * Control de fuerza bruta de login, persistido en BD.
 *
 * Reemplaza a los contadores en $_SESSION que tenían C_Login.php y C_ClienteAuth.php:
 * esos vivían en una cookie que el atacante controla, así que descartándola en cada
 * intento tenía reintentos infinitos.
 *
 * Los dos ámbitos ('staff' y 'cliente') se cuentan por separado, igual que las dos
 * sesiones del sistema.
 *
 * Todas las fechas se comparan con NOW() dentro de SQL, nunca contra time() de PHP
 * (MySQL va en hora local y PHP en UTC — ver fechaHoyBD() en config/conexion.php).
 */
class M_IntentosLogin {
    /** Intentos fallidos antes de bloquear. */
    const MAX_INTENTOS = 5;
    /** Minutos que dura el bloqueo. Se limpia solo, no hace falta desbloquear a mano. */
    const MINUTOS_BLOQUEO = 10;

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
     * Minutos que faltan para poder reintentar, o 0 si no está bloqueado.
     *
     * Un registro cuyo último intento ya salió de la ventana cuenta como libre: no
     * hace falta borrarlo, el siguiente fallo lo reinicia (ver registrarFallo()).
     */
    public function minutosBloqueoRestantes(string $ambito, string $identificador): int {
        if ($identificador === '') {
            return 0;
        }
        try {
            $stmt = $this->conexion->prepare(
                "SELECT CEIL((? - TIMESTAMPDIFF(SECOND, ultimo_intento, NOW())) / 60) AS restantes
                 FROM intentos_login
                 WHERE ambito = ? AND identificador = ?
                   AND intentos >= ?
                   AND TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) < ?"
            );
            $segundos = self::MINUTOS_BLOQUEO * 60;
            $stmt->execute([$segundos, $ambito, $identificador, self::MAX_INTENTOS, $segundos]);
            $restantes = $stmt->fetchColumn();
            return $restantes === false ? 0 : max(1, (int) $restantes);
        } catch (PDOException $e) {
            // Ante un fallo de BD no se bloquea el login: dejar fuera a todo el mundo
            // es peor que perder temporalmente esta protección.
            return 0;
        }
    }

    /**
     * Suma un intento fallido. Si el último fallo quedó fuera de la ventana, el
     * contador arranca de nuevo en 1 en vez de seguir acumulando para siempre.
     */
    public function registrarFallo(string $ambito, string $identificador): void {
        if ($identificador === '') {
            return;
        }
        try {
            $this->conexion->prepare(
                "INSERT INTO intentos_login (ambito, identificador, intentos, ultimo_intento)
                 VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                     intentos = IF(TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) >= ?, 1, intentos + 1),
                     ultimo_intento = NOW()"
            )->execute([$ambito, $identificador, self::MINUTOS_BLOQUEO * 60]);
        } catch (PDOException $e) {
            // Silencioso: no romper el login por no poder contar un fallo.
        }
    }

    /** Login correcto: se limpia el contador de ese identificador. */
    public function limpiar(string $ambito, string $identificador): void {
        if ($identificador === '') {
            return;
        }
        try {
            $this->conexion->prepare("DELETE FROM intentos_login WHERE ambito = ? AND identificador = ?")
                 ->execute([$ambito, $identificador]);
        } catch (PDOException $e) {
            // Silencioso.
        }
    }
}
?>
