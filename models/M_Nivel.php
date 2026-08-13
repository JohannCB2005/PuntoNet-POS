<?php
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/models/M_Cruce.php';

/**
 * Catálogo de niveles y grados de NISSI.
 *
 * A diferencia del sistema hermano (Modulos usa tablas `niveles`/`grados`
 * propias con obtenerOCrear*()), aquí se REUTILIZAN las tablas existentes
 * `niveles_educativos` (Inicial/Primaria/Secundaria) y `grados`, que ya
 * comparten productos y uniformes. El import de cubicol NO crea niveles/grados
 * nuevos: resuelve el texto del archivo ("Nivel Inicial", "1°", "Primer Grado",
 * "3 Años"... ) contra lo que ya existe, aceptando variantes. Si no consigue
 * resolver, devuelve null (el alumno queda sin nivel/grado asignado, nunca se
 * pisan datos).
 */
class M_Nivel {
    private static $instancia = null;
    private $conexion;
    private $cacheNiveles = [];
    private $cacheGrados = [];

    // Palabras ordinales -> número, para interpretar "Primer Grado" o "Tercer Año".
    private const ORDINALES = [
        'PRIMER' => 1, 'PRIMERO' => 1, 'PRIMERA' => 1,
        'SEGUNDO' => 2, 'SEGUNDA' => 2,
        'TERCER' => 3, 'TERCERO' => 3, 'TERCERA' => 3,
        'CUARTO' => 4, 'CUARTA' => 4,
        'QUINTO' => 5, 'QUINTA' => 5,
        'SEXTO' => 6, 'SEXTA' => 6,
    ];

    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    public function listarNiveles(): array {
        try {
            return $this->conexion->query("SELECT * FROM niveles_educativos WHERE estado = 1 ORDER BY id_nivel")->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function listarGrados(?int $idNivel = null): array {
        try {
            if ($idNivel !== null) {
                $stmt = $this->conexion->prepare("SELECT * FROM grados WHERE estado = 1 AND id_nivel = ? ORDER BY id_grado");
                $stmt->execute([$idNivel]);
                return $stmt->fetchAll();
            }
            return $this->conexion->query(
                "SELECT g.*, n.nombre AS nivel_nombre FROM grados g
                 INNER JOIN niveles_educativos n ON g.id_nivel = n.id_nivel
                 WHERE g.estado = 1 ORDER BY n.id_nivel, g.id_grado"
            )->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Resuelve el texto de nivel de cubicol ("NIVEL INICIAL", "INICIAL",
     * "Inicial - 3 Años"... ) al id_nivel existente de NISSI.
     */
    public function resolverNivel(string $texto): ?int {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        if (isset($this->cacheNiveles[$texto])) {
            return $this->cacheNiveles[$texto];
        }

        $norm = M_Cruce::normalizarNombre($texto);
        $id = null;
        $keywords = ['INICIAL' => 'INICIAL', 'PRIMARIA' => 'PRIMARIA', 'SECUNDARIA' => 'SECUNDARIA'];
        $elegida = null;
        foreach ($keywords as $clave => $palabra) {
            if (strpos($norm, $palabra) !== false) {
                $elegida = $clave;
                break;
            }
        }
        if ($elegida !== null) {
            $stmt = $this->conexion->prepare("SELECT id_nivel FROM niveles_educativos WHERE estado = 1 AND nombre LIKE ? LIMIT 1");
            $stmt->execute(['%' . $elegida . '%']);
            $id = $stmt->fetchColumn();
        }

        $id = $id === false || $id === null ? null : (int) $id;
        $this->cacheNiveles[$texto] = $id;
        return $id;
    }

    /**
     * Resuelve el texto de grado de cubicol al id_grado existente DENTRO de un
     * nivel. Acepta "1°", "1", "Primer Grado", "Tercer Año", "3 Años", etc.
     * Nunca crea grados: si no encuentra coincidencia, devuelve null.
     */
    public function resolverGrado(int $idNivel, string $texto): ?int {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        $clave = $idNivel . '|' . $texto;
        if (isset($this->cacheGrados[$clave])) {
            return $this->cacheGrados[$clave];
        }

        $stmt = $this->conexion->prepare("SELECT id_grado, nombre FROM grados WHERE estado = 1 AND id_nivel = ?");
        $stmt->execute([$idNivel]);
        $grados = $stmt->fetchAll();

        $id = null;
        // 1) Coincidencia exacta de nombre normalizado (el caso típico: "1°").
        $normTexto = M_Cruce::normalizarNombre($texto);
        foreach ($grados as $g) {
            if (M_Cruce::normalizarNombre($g['nombre']) === $normTexto) {
                $id = (int) $g['id_grado'];
                break;
            }
        }

        // 2) El texto y el grado comparten el mismo número (dígito u ordinal).
        if ($id === null) {
            $numeroTexto = self::_numeroDeTexto($normTexto);
            if ($numeroTexto !== null) {
                foreach ($grados as $g) {
                    if (self::_numeroDeTexto(M_Cruce::normalizarNombre($g['nombre'])) === $numeroTexto) {
                        $id = (int) $g['id_grado'];
                        break;
                    }
                }
            }
        }

        $id = (int) $id;
        $id = $id > 0 ? $id : null;
        $this->cacheGrados[$clave] = $id;
        return $id;
    }

    /** Número de grado (1..6) contenido en un texto normalizado, o null. */
    private static function _numeroDeTexto(string $norm): ?int {
        if (preg_match('/([1-9])/', $norm, $m)) {
            return (int) $m[1];
        }
        foreach (self::ORDINALES as $palabra => $numero) {
            if (strpos($norm, $palabra) !== false) {
                return $numero;
            }
        }
        return null;
    }
}
?>