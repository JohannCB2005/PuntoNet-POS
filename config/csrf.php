<?php
/**
 * config/csrf.php
 * Protección CSRF para el panel (staff). Mecanismo:
 *
 *   - csrfToken(): genera/retorna el token guardado en $_SESSION['csrf_token'].
 *   - csrfValidar(): compara el token enviado (header X-CSRF-Token, $_POST['csrf_token']
 *     o campo json 'csrf_token') contra el de sesión, con hash_equals.
 *   - csrfRequerir(): valida y aborta con 403 si falla.
 *
 * Las peticiones GET/HEAD/OPTIONS no se validan (no tienen efectos colaterales).
 * El token viaja automáticamente en todas las peticiones del panel gracias al
 * wrapper de fetch que inyecta views/layouts/header.php.
 */

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfValidar(): bool {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return true;
    }

    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ($_POST['csrf_token'] ?? '');

    if ($enviado === '' && !empty($_SERVER['CONTENT_TYPE'])
        && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
        $cuerpo = json_decode(file_get_contents('php://input'), true);
        $enviado = $cuerpo['csrf_token'] ?? '';
    }

    return !empty($_SESSION['csrf_token'])
        && is_string($enviado)
        && hash_equals($_SESSION['csrf_token'], $enviado);
}

function csrfRequerir(): void {
    if (!csrfValidar()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'mensaje' => 'Token de seguridad inválido. Recarga la página.']);
        exit;
    }
}