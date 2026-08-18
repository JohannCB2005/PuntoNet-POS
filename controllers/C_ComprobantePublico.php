<?php
/**
 * controllers/C_ComprobantePublico.php
 * Comprobante público por enlace (estilo tukifac).
 *
 * Permite compartir el comprobante de una venta por URL sin necesidad de sesión:
 *   /comprobante?id=<id_venta>&t=<token_publico>
 *
 * Seguridad: el acceso se valida con hash_equals contra el token_publico de la
 * venta (mismo patrón que getPedidoPublico de pedidos_online). Sin el token
 * exacto, 404 — no se filtra ni siquiera si la venta existe.
 *
 * No inicia sesión ni toca estado: es una página de solo lectura, pensada para
 * clientes anónimos. La entrada principal es index.php (ruta /comprobante).
 */

$idVenta = intval($_GET['id'] ?? 0);
$token   = trim((string) ($_GET['t'] ?? ''));

require_once dirname(__DIR__) . '/config/sunat.php';
require_once dirname(__DIR__) . '/config/marca.php';
require_once dirname(__DIR__) . '/models/M_Venta.php';

$comprobante = null;
if ($idVenta > 0 && $token !== '') {
    $comprobante = M_Venta::singleton()->obtenerComprobantePublico($idVenta, $token);
}

if (!$comprobante) {
    http_response_code(404);
}

require __DIR__ . '/../views/public/V_comprobante_publico.php';