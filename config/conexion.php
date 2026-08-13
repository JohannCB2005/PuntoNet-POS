<?php
class Conexion {
    private static $instancia = null;
    private $dbh;

    private function __construct() {
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';
        $isLocalhost = (strpos($httpHost, 'localhost') !== false || strpos($httpHost, '127.0.0.1') !== false || php_sapi_name() === 'cli');

        $opciones = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        );

        // Las credenciales de producción viven en .env (nunca versionado) porque cambian
        // según el hosting/cuenta que se esté usando en cada momento.
        $_envFile = dirname(__DIR__) . '/.env';
        $_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

        // Definir credenciales según el entorno
        if ($isLocalhost) {
            $configs = [
                [
                    'host' => 'localhost',
                    'dbname' => 'puntonet_pos',
                    'user' => 'puntonet_user',
                    'pass' => 'puntonet2026'
                ],
                [
                    'host' => 'localhost',
                    'dbname' => 'puntonet_pos',
                    'user' => 'root',
                    'pass' => ''
                ]
            ];
        } else {
            $_prodConfig = [
                'host'   => $_env['DB_PROD_HOST'] ?? '',
                'dbname' => $_env['DB_PROD_NAME'] ?? '',
                'user'   => $_env['DB_PROD_USER'] ?? '',
                'pass'   => $_env['DB_PROD_PASS'] ?? ''
            ];

            if (!empty($_prodConfig['host']) && !empty($_prodConfig['dbname'])) {
                $configs = [$_prodConfig];
            } else {
                // Sin credenciales de producción configuradas (por ejemplo en un túnel
                // local como Cloudflare Tunnel) se usan las credenciales locales de desarrollo.
                $configs = [
                    [
                        'host' => 'localhost',
                        'dbname' => 'puntonet_pos',
                        'user' => 'puntonet_user',
                        'pass' => 'puntonet2026'
                    ]
                ];
            }
            unset($_prodConfig);
        }
        unset($_envFile, $_env);

        $conexionExitosa = false;
        $ultimoError = null;

        foreach ($configs as $config) {
            $host = $config['host'];
            $dbname = $config['dbname'];
            $user = $config['user'];
            $pass = $config['pass'];

            try {
                // Intentar conectar directamente a la base de datos
                $this->dbh = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass, $opciones);
                
                // Verificar si existen tablas. Si la base de datos está vacía, la inicializamos.
                $stmt = $this->dbh->query("SHOW TABLES");
                $tablas = $stmt->fetchAll();
                if (empty($tablas)) {
                    $this->inicializarBaseDatos($this->dbh);
                }
                
                $conexionExitosa = true;
                break; // Conexión exitosa, salir del bucle
            } catch (PDOException $e) {
                $ultimoError = $e;

                // Si la base de datos no existe (código 1049 o SQLSTATE HY000 / 1049)
                if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                    try {
                        // Conectarse temporalmente a MySQL sin seleccionar base de datos
                        $tempDbh = new PDO("mysql:host=$host", $user, $pass, $opciones);
                        
                        // Crear la base de datos
                        $tempDbh->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
                        
                        // Intentar la conexión real a la base de datos recién creada
                        $this->dbh = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass, $opciones);
                        
                        // Importar el esquema y data semilla del archivo base_datos_nissi.sql
                        $this->inicializarBaseDatos($this->dbh);
                        
                        $conexionExitosa = true;
                        break; // Conexión exitosa tras crear la BD, salir del bucle
                    } catch (PDOException $ex) {
                        $ultimoError = $ex;
                        // Si falla aquí, continúa el bucle con la siguiente configuración
                    }
                }
                // Si es un error de credenciales incorrectas (1045 - Access denied),
                // el bucle simplemente continuará con el siguiente intento de credenciales.
            }
        }

        if (!$conexionExitosa) {
            die("Error de conexión a la base de datos: " . ($ultimoError ? $ultimoError->getMessage() : "No se pudo establecer conexión."));
        }
    }

    /**
     * Lee y ejecuta el archivo base_datos_nissi.sql para crear el esquema y la data semilla.
     * NOTA: Los STORED PROCEDURES han sido eliminados del SQL y migrados a PHP/PDO
     * para compatibilidad con hosting compartido (InfinityFree) sin privilegios de CREATE PROCEDURE.
     */
    private function inicializarBaseDatos($conexion) {
        $sqlPath = dirname(__DIR__) . '/base_datos_nissi.sql';
        if (!file_exists($sqlPath)) {
            die("Error de inicialización: No se encontró el archivo base_datos_nissi.sql en " . $sqlPath);
        }

        try {
            $sqlContent = file_get_contents($sqlPath);
            $lines = explode("\n", $sqlContent);
            $query = '';
            $inProcedure = false;

            foreach ($lines as $line) {
                $lineTrimmed = trim($line);

                // Ignorar líneas vacías y comentarios SQL de una línea
                if ($lineTrimmed === '' || strpos($lineTrimmed, '--') === 0 || strpos($lineTrimmed, '#') === 0) {
                    continue;
                }

                // Detectar comandos DELIMITER del cliente MySQL
                if (stripos($lineTrimmed, 'DELIMITER') === 0) {
                    if (strpos($lineTrimmed, '$$') !== false) {
                        $inProcedure = true;
                    } else {
                        $inProcedure = false;
                    }
                    continue;
                }

                $query .= $line . "\n";

                // Si estamos procesando un procedimiento almacenado
                if ($inProcedure) {
                    if (strpos($lineTrimmed, '$$') !== false) {
                        // Reemplazar el delimitador final $$ por ; y ejecutar
                        $queryToExecute = str_replace('$$', ';', $query);
                        $conexion->exec($queryToExecute);
                        $query = '';
                    }
                } else {
                    // Consultas estándar que terminan con ;
                    if (substr($lineTrimmed, -1) === ';') {
                        $conexion->exec($query);
                        $query = '';
                    }
                }
            }
        } catch (Exception $e) {
            die("Error al importar el esquema de base de datos de manera automática: " . $e->getMessage());
        }
    }

    public static function singleton() {
        if (!isset(self::$instancia)) {
            $miclase = __CLASS__;
            self::$instancia = new $miclase;
        }
        return self::$instancia;
    }

    public function getConexion() {
        return $this->dbh;
    }
}

/**
 * Fecha de "hoy" según el reloj de MySQL, que es el único que importa para filtrar
 * columnas DATETIME de la base.
 *
 * En este entorno MySQL corre en hora local (time_zone = SYSTEM) y PHP en UTC: 5
 * horas de diferencia. Usar date('Y-m-d') como valor por defecto de un filtro que
 * después se compara contra DATE(fecha_apertura) hace que, desde las 19:00 hora de
 * Perú, la consulta pida el día SIGUIENTE — "Control de Cajas" y "Reportes" salen
 * vacíos con la tienda todavía abierta.
 *
 * Regla del proyecto: las fechas de BD se comparan siempre contra el reloj de la BD.
 * Esto es la versión "valor por defecto" de esa misma regla.
 */
function fechaHoyBD(): string {
    try {
        return (string) Conexion::singleton()->getConexion()->query('SELECT CURDATE()')->fetchColumn();
    } catch (Exception $e) {
        // Si la BD no responde, el reloj de PHP es mejor que nada.
        return date('Y-m-d');
    }
}
?>