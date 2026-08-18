<?php
/**
 * scripts/migrar_secretos.php
 * Cifra cualquier secreto del catálogo que esté en texto plano en la tabla
 * `configuracion` (migración única, idempotente). Ejecutar una vez con PHP CLI:
 *
 *   php scripts/migrar_secretos.php
 *
 * Los valores ya cifrados (prefijo "encv1:") se saltan; los vacíos también.
 */

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/cripto.php';
require_once __DIR__ . '/../models/M_Configuracion.php';

$pdo = Conexion::singleton()->getConexion();
$catalogo = M_Configuracion::CAMPOS;

$actualizados = 0;
$omitidos = 0;

foreach ($catalogo as $clave => $meta) {
    if (empty($meta['secreto'])) continue;

    $stmt = $pdo->prepare('SELECT valor FROM configuracion WHERE clave = ?');
    $stmt->execute([$clave]);
    $valor = $stmt->fetchColumn();

    if ($valor === false || (string) $valor === '') {
        $omitidos++;
        continue;
    }
    if (str_starts_with((string) $valor, 'encv1:')) {
        $omitidos++;
        continue;
    }

    $cifrado = cifrarSecreto((string) $valor);
    if ($cifrado === '') {
        echo "ERROR: no se pudo cifrar $clave (¿falta APP_ENCRYPTION_KEY en .env?)\n";
        continue;
    }

    $pdo->prepare('UPDATE configuracion SET valor = ? WHERE clave = ?')
        ->execute([$cifrado, $clave]);
    echo "Cifrado: $clave\n";
    $actualizados++;
}

echo "Listo: $actualizados cifrados, $omitidos omitidos (vacíos o ya cifrados).\n";