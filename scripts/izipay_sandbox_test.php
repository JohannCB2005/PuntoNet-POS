<?php
/**
 * scripts/izipay_sandbox_test.php
 *
 * Prueba aislada de la conexión con el sandbox de Izipay. NO toca la BD, ni el
 * carrito, ni pedidos_online: solo comprueba que las credenciales de test sirven
 * para pedir un FormToken, pintar el formulario y validar la firma del retorno.
 *
 * Uso:  php -S localhost:8000  →  http://localhost:8000/scripts/izipay_sandbox_test.php
 *
 * Cuando esto funcione de punta a punta, recién ahí se integra al checkout real.
 * Borrar este archivo antes de desplegar a producción.
 */

// Solo local: este script expone detalles de la integración, no debe correr en el hosting.
$_host = $_SERVER['HTTP_HOST'] ?? '';
if (!preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_host)) {
    http_response_code(403);
    exit('Este script de prueba solo puede ejecutarse en local.');
}

require_once dirname(__DIR__) . '/models/M_Izipay.php';

$izipay = M_Izipay::singleton();

// ── Paso 3: el navegador vuelve con el resultado del pago ────────────────────
if (!empty($_POST['kr-answer'])) {
    $krAnswer = $_POST['kr-answer'];
    $krHash   = $_POST['kr-hash'] ?? '';
    $firmaOk  = $izipay->verificarFirmaNavegador($krAnswer, $krHash);
    $r        = $izipay->parsearRespuesta($krAnswer);

    echo '<meta charset="utf-8"><body style="font-family:system-ui;max-width:760px;margin:40px auto;">';
    echo '<h2>Resultado del pago (sandbox)</h2>';
    echo '<p><strong>Firma HMAC-SHA256:</strong> '
       . ($firmaOk ? '<span style="color:#16a34a">VÁLIDA</span>' : '<span style="color:#dc2626">INVÁLIDA — revisar IZIPAY_HMAC_SHA256</span>')
       . '</p>';
    if ($firmaOk) {
        $pagado = $r['orderStatus'] === 'PAID';
        echo '<p><strong>orderStatus:</strong> <span style="color:' . ($pagado ? '#16a34a' : '#dc2626') . '">'
           . htmlspecialchars($r['orderStatus']) . '</span></p>';
        echo '<p><strong>orderId:</strong> ' . htmlspecialchars($r['orderId']) . '</p>';
        echo '<p><strong>uuid transacción:</strong> ' . htmlspecialchars($r['uuid']) . '</p>';
        if ($r['errorCode']) {
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($r['errorCode'] . ' — ' . $r['errorMsg']) . '</p>';
        }
        // Lo que explica por qué las tarjetas de fallo 3DS2 pueden salir PAID:
        // si la autenticación no se ejecuta, el escenario que simulan nunca ocurre.
        $auth = $r['autenticacion'] ?: '(no informado)';
        $ls   = $r['liabilityShift'] ?: '(no informado)';
        echo '<p style="background:#f8fafc;border:1px solid #e2e8f0;padding:12px;border-radius:8px">'
           . '<strong>Autenticación 3DS2:</strong> ' . htmlspecialchars($auth) . '<br>'
           . '<strong>Traslado de responsabilidad (liabilityShift):</strong> ' . htmlspecialchars($ls)
           . ($ls === 'NO' ? ' — el comercio asume los contracargos por fraude' : '')
           . '</p>';
        echo '<details><summary>Respuesta completa</summary><pre>'
           . htmlspecialchars(json_encode($r['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
           . '</pre></details>';
    }
    echo '<p><a href="izipay_sandbox_test.php">← Probar de nuevo</a> · '
       . '<a href="izipay_sandbox_test.php?3ds=1">Probar forzando challenge 3DS2</a></p></body>';
    exit;
}

// ── Paso 1: pedir el FormToken ──────────────────────────────────────────────
$error = '';
$formToken = '';

if (!$izipay->estaConfigurado()) {
    $error = 'Faltan credenciales en .env. Completa IZIPAY_PASSWORD, IZIPAY_PUBLIC_KEY '
           . 'e IZIPAY_HMAC_SHA256 con los valores de la pestaña "Claves de API REST" (columna Test).';
} else {
    // ?3ds=1 pide explícitamente el challenge 3DS2. Sin esto la tienda de test
    // resuelve en modo frictionless y las tarjetas que simulan fallos de 3DS2
    // (challenge rechazado / timeout) salen PAID, porque la autenticación que
    // deberían hacer fallar nunca llega a ejecutarse.
    $forzar3ds = isset($_GET['3ds']) && $_GET['3ds'] === '1';

    $r = $izipay->crearFormToken(
        10.50,
        'TEST-' . date('YmdHis'),
        ['email' => 'prueba@puntonet.pe', 'nombres' => 'Cliente', 'apellidos' => 'De Prueba'],
        'PEN',
        $forzar3ds ? 'CHALLENGE_REQUESTED' : ''
    );
    if ($r['ok']) {
        $formToken = $r['formToken'];
    } else {
        $error = $r['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<!-- Krypton exige viewport definido; sin esto avisa con CLIENT_705 y el
     formulario no se adapta bien en móvil. -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Izipay — prueba de sandbox</title>
<?php if ($formToken): ?>
    <script
        src="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js"
        kr-public-key="<?php echo htmlspecialchars(IZIPAY_PUBLIC_KEY); ?>"
        kr-post-url-success="izipay_sandbox_test.php"
        kr-language="es-ES"></script>
    <link rel="stylesheet" href="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.css">
    <script src="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.js"></script>
<?php endif; ?>
<style>body{font-family:system-ui;max-width:760px;margin:40px auto;padding:0 16px}</style>
</head>
<body>
    <h2>Izipay — prueba de conexión al sandbox</h2>
    <p>Modo: <strong><?php echo htmlspecialchars(IZIPAY_MODO); ?></strong> ·
       Tienda: <strong><?php echo htmlspecialchars(IZIPAY_SHOP_ID); ?></strong> ·
       Host: <strong><?php echo htmlspecialchars(IZIPAY_API_HOST); ?></strong></p>

<?php if ($error): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;padding:16px;border-radius:8px">
        <strong>No se pudo obtener el FormToken.</strong>
        <p style="margin:8px 0 0"><?php echo htmlspecialchars($error); ?></p>
    </div>
<?php else: ?>
    <p style="color:#16a34a"><strong>FormToken obtenido correctamente.</strong> Monto de prueba: S/ 10.50</p>
    <p>Autenticación 3DS2:
        <strong><?php echo $forzar3ds ? 'CHALLENGE_REQUESTED (forzada)' : 'según config de la tienda'; ?></strong>
        —
        <?php if ($forzar3ds): ?>
            <a href="izipay_sandbox_test.php">volver al modo normal</a>
        <?php else: ?>
            <a href="izipay_sandbox_test.php?3ds=1">forzar challenge 3DS2</a>
            (necesario para que las tarjetas de fallo 3DS2 se comporten como su descripción)
        <?php endif; ?>
    </p>
    <p>Usa las tarjetas de prueba de la barra de depuración que aparece al pie del formulario.</p>
    <div class="kr-embedded" kr-form-token="<?php echo htmlspecialchars($formToken); ?>"></div>
<?php endif; ?>
</body>
</html>
