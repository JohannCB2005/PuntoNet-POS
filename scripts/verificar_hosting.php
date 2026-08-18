<?php
/**
 * scripts/verificar_hosting.php
 *
 * Diagnóstico de un hosting nuevo ANTES de subir el proyecto. Comprueba las dos
 * cosas que ningún proveedor publica en su web y que, si fallan, no tienen arreglo
 * por configuración — solo cambiando de hosting:
 *
 *   1. Versión de PHP y extensiones que el código realmente usa
 *      (curl, openssl, dom, zip, pdo_mysql — ver config/conexion.php, M_Sunat.php,
 *      M_SunatWs.php, M_Izipay.php, M_Mailer.php).
 *   2. Conexiones salientes HTTPS a los 6 servicios externos de los que depende
 *      el sistema: SUNAT (beta y producción), Izipay, Brevo y apiperu.dev.
 *      Muchos hostings baratos restringen esto sin avisar.
 *
 * No usa la base de datos, no lee .env, no expone ninguna credencial: es seguro
 * subirlo a una cuenta de hosting vacía, sin el resto del proyecto, para probar
 * antes de comprometerse a nada.
 *
 * Uso: sube este único archivo a la raíz del hosting y ábrelo en el navegador.
 * BÓRRALO cuando termines de probar — no debe quedar en producción.
 */

header('Content-Type: text/html; charset=utf-8');

function verificarExtension(string $ext): bool {
    return extension_loaded($ext);
}

/**
 * Solo comprueba que se puede establecer la conexión HTTPS y que el servidor
 * responde — no importa el código HTTP (puede ser 404/405 si se pega a la URL
 * base sin los parámetros que el servicio real espera). Lo que se busca es
 * descartar bloqueos de red, no probar la integración completa.
 */
function probarConexion(string $url, int $timeoutSeg = 8): array {
    $inicio = microtime(true);
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeoutSeg,
        CURLOPT_CONNECTTIMEOUT => $timeoutSeg,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_NOBODY         => true, // HEAD: no hace falta el cuerpo, solo confirmar respuesta
        CURLOPT_HTTPHEADER     => ['User-Agent: verificar_hosting.php'],
    ]);
    curl_exec($curl);
    $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error  = curl_error($curl);
    curl_close($curl);
    $ms = round((microtime(true) - $inicio) * 1000);

    // Cualquier código HTTP (incluso 403/404/405) prueba que la conexión llegó.
    // Solo cuenta como bloqueada si cURL nunca obtuvo respuesta.
    $conectado = $codigo > 0;
    return ['ok' => $conectado, 'codigo' => $codigo, 'error' => $error, 'ms' => $ms];
}

$extensiones = ['curl', 'openssl', 'dom', 'zip', 'pdo_mysql', 'mbstring'];

$servicios = [
    'SUNAT (beta — pruebas)'        => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
    'SUNAT (producción)'            => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
    'Izipay — API REST'             => 'https://api.micuentaweb.pe',
    'Izipay — formulario Krypton'   => 'https://secure.micuentaweb.pe',
    'Brevo — correo transaccional'  => 'https://api.brevo.com',
    'apiperu.dev — consulta RUC/DNI' => 'https://apiperu.dev/api/ruc',
];

$phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Verificación de hosting</title>
<style>
    body { font-family: -apple-system, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 16px; color: #1f2937; }
    h1 { font-size: 20px; }
    h2 { font-size: 15px; margin-top: 32px; color: #4b5563; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    td, th { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 14px; }
    .ok { color: #15803d; font-weight: 600; }
    .fail { color: #b91c1c; font-weight: 600; }
    .aviso { background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin-top: 24px; font-size: 13.5px; }
    .detalle { color: #6b7280; font-size: 12px; }
</style>
</head>
<body>
<h1>Verificación de hosting — Tienda NISSI</h1>
<p class="detalle">Generado el <?php echo date('Y-m-d H:i:s'); ?> — PHP <?php echo PHP_VERSION; ?> en <?php echo php_uname('s'); ?></p>

<h2>1. Versión de PHP</h2>
<table>
    <tr>
        <td>Versión actual</td>
        <td><?php echo PHP_VERSION; ?></td>
        <td class="<?php echo $phpOk ? 'ok' : 'fail'; ?>"><?php echo $phpOk ? '✓ OK (≥ 8.1 requerido)' : '✗ INSUFICIENTE — el proyecto necesita PHP 8.1 o superior'; ?></td>
    </tr>
</table>

<h2>2. Extensiones de PHP requeridas</h2>
<table>
    <?php foreach ($extensiones as $ext): $ok = verificarExtension($ext); ?>
    <tr>
        <td>ext-<?php echo $ext; ?></td>
        <td colspan="2" class="<?php echo $ok ? 'ok' : 'fail'; ?>"><?php echo $ok ? '✓ disponible' : '✗ FALTA'; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>3. Conexiones salientes a servicios externos</h2>
<p class="detalle">Esto es lo que casi ningún hosting barato deja claro por adelantado. Si algo aparece bloqueado aquí, no tiene arreglo por configuración.</p>
<table>
    <tr><th>Servicio</th><th>Resultado</th><th>Detalle</th></tr>
    <?php foreach ($servicios as $nombre => $url): $r = probarConexion($url); ?>
    <tr>
        <td><?php echo htmlspecialchars($nombre); ?></td>
        <td class="<?php echo $r['ok'] ? 'ok' : 'fail'; ?>">
            <?php echo $r['ok'] ? "✓ conectado ({$r['ms']} ms)" : '✗ BLOQUEADO'; ?>
        </td>
        <td class="detalle">
            <?php echo $r['ok'] ? 'HTTP ' . $r['codigo'] : htmlspecialchars($r['error'] ?: 'sin respuesta'); ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="aviso">
    <strong>Recuerda borrar este archivo</strong> del hosting cuando termines de probar.
    No expone credenciales, pero no debe quedar público de forma permanente.
</div>

</body>
</html>
