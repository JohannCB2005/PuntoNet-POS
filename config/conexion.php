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
            $configs = [
                [
                    'host' => 'sql210.infinityfree.com',
                    'dbname' => 'if0_42381931_puntonet_pos',
                    'user' => 'if0_42381931',
                    'pass' => 'For52638'
                ]
            ];
        }

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
                        
                        // Importar el esquema y data semilla del archivo base_datos.sql
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
     * Lee y ejecuta el archivo base_datos.sql para crear el esquema y la data semilla.
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
?>