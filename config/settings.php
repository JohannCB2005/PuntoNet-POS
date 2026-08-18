<?php
/**
 * config/settings.php
 * Cargador central de configuración de la tienda.
 *
 * Fuente de verdad: tabla `configuracion` en la BD (editada desde el panel
 * "Configuración de la tienda"). Los valores que el administrador NO ha guardado
 * todavía caen al fallback del .env (config/*.php), así el sistema funciona
 * desde el primer request sin migrar data.
 *
 * La consulta a BD va envuelta en try/catch y se cachea por request: si la tabla
 * no existe o la BD no responde, se devuelve el fallback sin romper nada (mismo
 * criterio que fechaHoyBD() en config/conexion.php).
 */

/**
 * Devuelve el valor de una clave de configuración: BD primero, fallback si no.
 *
 * @param string $clave
 * @param mixed  $fallback Valor a devolver si no hay registro en la BD.
 * @return string
 */
function configuracion(string $clave, $fallback = ''): string {
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            require_once dirname(__DIR__) . '/config/conexion.php';
            require_once dirname(__DIR__) . '/config/cripto.php';
            $stmt = Conexion::singleton()->getConexion()->query(
                'SELECT clave, valor FROM configuracion'
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $cache[$fila['clave']] = (string) $fila['valor'];
            }
        } catch (Throwable $e) {
            // Tabla inexistente o BD no disponible: se queda el cache vacío
            // y todo resuelve al fallback.
        }
    }

    if (!array_key_exists($clave, $cache)) {
        return (string) $fallback;
    }

    // Secretos cifrados: se descifran en el punto de lectura. Los valores no
    // secretos nunca se cifran, así que el prefijo "encv1:" es marca suficiente.
    $valor = $cache[$clave];
    if (str_starts_with($valor, 'encv1:')) {
        $valor = descifrarSecreto($valor);
    }
    return $valor === '' && $fallback !== '' ? (string) $fallback : $valor;
}