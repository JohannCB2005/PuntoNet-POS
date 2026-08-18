<?php
if (session_status() === PHP_SESSION_NONE) { require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start(); }
require_once dirname(__DIR__) . '/models/M_Ecommerce.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

/**
 * Resuelve la entidad a facturar (empresa con RUC) a partir del número de RUC.
 *
 * NUNCA se confía en un id_cliente que mande el navegador — solo en el número de
 * RUC, que se vuelve a validar aquí en servidor (mismo criterio que ya usa el POS
 * en controllers/C_Cliente.php: estado ACTIVO/HABIDO en SUNAT). Si el RUC ya
 * existe localmente se reutiliza esa fila; si no, se consulta apiperu.dev y se
 * da de alta con M_Cliente::registrarCliente(), igual que hace el POS al vuelo.
 *
 * @return array{ok:bool, id_cliente:?int, mensaje:string}
 */
function resolverClienteFacturacionPorRuc(string $ruc): array {
    require_once dirname(__DIR__) . '/entities/Cliente.php';
    require_once dirname(__DIR__) . '/models/M_Cliente.php';

    if (!ctype_digit($ruc) || strlen($ruc) !== 11) {
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => 'Para Factura, indica un RUC válido de 11 dígitos.'];
    }

    $modelCliente = M_Cliente::singleton();

    // 1. Reutilizar si ya existe localmente (ej. el POS ya lo dio de alta antes).
    $existente = $modelCliente->obtenerClientePorDocumento($ruc);
    if ($existente) {
        return ['ok' => true, 'id_cliente' => (int) $existente['id_cliente'], 'mensaje' => ''];
    }

    // 2. Consultar SUNAT (misma validación que controllers/C_Cliente.php:132-158).
    $apiConfig = require dirname(__DIR__) . '/config/api.php';
    $token = $apiConfig['apiperu_token'] ?? '';
    if (empty($token)) {
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => 'Servicio de consulta de RUC no disponible.'];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => 'https://apiperu.dev/api/ruc',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POSTFIELDS     => json_encode(['ruc' => $ruc]),
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => 'Error de conexión al validar el RUC.'];
    }

    $resData = json_decode($response, true);
    if (!$resData || !isset($resData['success']) || !$resData['success']) {
        $msg = $resData['message'] ?? 'RUC no encontrado en el padrón de SUNAT.';
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => $msg];
    }

    $apiData   = $resData['data'];
    $estado    = strtoupper(trim($apiData['estado'] ?? ''));
    $condicion = strtoupper(trim($apiData['condicion'] ?? ''));

    if (!empty($estado) && $estado !== 'ACTIVO') {
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => 'El RUC tiene estado no activo: ' . $estado . '.'];
    }
    if (!empty($condicion) && $condicion !== 'HABIDO') {
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => 'El RUC tiene condición de domicilio: ' . $condicion . '.'];
    }

    // 3. Dar de alta la empresa como cliente (tipo_documento=2=RUC), igual que el POS.
    $razonSocial = $apiData['nombre_o_razon_social'] ?? '';
    $direccion   = $apiData['direccion'] ?? '';
    $tipoCliente = strpos($ruc, '20') === 0 ? 2 : 1; // 2=Jurídica

    $cliente = new Cliente(2, $ruc, $razonSocial, null, $direccion, '', $tipoCliente);
    $resultado = $modelCliente->registrarCliente($cliente);
    if ($resultado !== true) {
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => 'No se pudo registrar la entidad de facturación.'];
    }

    $nuevo = $modelCliente->obtenerClientePorDocumento($ruc);
    if (!$nuevo) {
        return ['ok' => false, 'id_cliente' => null, 'mensaje' => 'No se pudo recuperar la entidad de facturación recién creada.'];
    }

    return ['ok' => true, 'id_cliente' => (int) $nuevo['id_cliente'], 'mensaje' => ''];
}

switch ($action) {
    case 'catalogo':
        $items = M_Ecommerce::singleton()->getCatalogo();
        echo json_encode(['success' => true, 'data' => $items]);
        break;

    case 'catalogo_agrupado':
        $grupos = M_Ecommerce::singleton()->getCatalogoAgrupado();
        echo json_encode(['success' => true, 'data' => $grupos]);
        break;

    // Consulta pública: qué pasarelas de pago están habilitadas y configuradas en
    // el panel. La usa el checkout para mostrar/ocultar los métodos de pago.
    case 'pasarelas':
        header('Cache-Control: no-store, no-cache, must-revalidate');
        require_once dirname(__DIR__) . '/models/M_Taypi.php';
        require_once dirname(__DIR__) . '/models/M_Izipay.php';
        echo json_encode([
            'success' => true,
            'taypi'   => M_Taypi::singleton()->estaConfigurado(),
            'izipay'  => M_Izipay::singleton()->estaConfigurado(),
        ]);
        break;

    // Requiere el token público del pedido: evita enumerar pedidos ajenos (IDOR).
    case 'get_pedido':
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $id = intval($_GET['id'] ?? 0);
        $token = trim($_GET['t'] ?? '');
        if ($id <= 0 || $token === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Solicitud inválida.']);
            exit;
        }
        $pedido = M_Ecommerce::singleton()->getPedidoPublico($id, $token);
        echo json_encode(['success' => (bool) $pedido, 'data' => $pedido]);
        break;

    // Cancelación por abandono del checkout. Se llama desde la página de pago con
    // navigator.sendBeacon cuando el cliente cierra/abandona la ventana mientras su
    // pedido está pendiente. El servidor verifica antes contra la pasarela que el
    // cobro no se haya completado (si sí, confirma en vez de liberar stock) — así
    // cerrar la pestaña justo después de pagar no pierde ni pedido ni stock.
    case 'cancelar_pedido':
        header('Cache-Control: no-store');
        $id = intval($_GET['id'] ?? 0);
        $token = trim($_GET['t'] ?? '');
        if ($id <= 0 || $token === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Solicitud inválida.']);
            exit;
        }
        $r = M_Ecommerce::singleton()->cancelarPedidoPendiente($id, $token);
        echo json_encode(['success' => (bool) ($r['ok'] ?? false), 'accion' => $r['accion'] ?? '', 'mensaje' => $r['mensaje'] ?? '']);
        break;

    // Crea el pedido en estado "pendiente de pago" y reserva stock. El precio y el total
    // se recalculan siempre en el servidor; el cliente solo envía {id_producto, cantidad}.
    // Requiere cuenta de cliente autenticada — ya no existe compra como invitado.
    case 'crear_pedido':
        if (!isset($_SESSION['id_cliente'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Debes iniciar sesión para completar tu compra.', 'requiere_login' => true]);
            exit;
        }

        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);
        if (!$data) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos.']);
            exit;
        }

        $carrito = $data['carrito'] ?? [];
        $tipo_entrega = intval($data['tipo_entrega'] ?? 1);

        if (empty($carrito) || !in_array($tipo_entrega, [1, 2], true)) {
            echo json_encode(['success' => false, 'mensaje' => 'Faltan datos obligatorios o el carrito está vacío.']);
            exit;
        }

        $entrega = ['tipo_entrega' => $tipo_entrega, 'observaciones' => trim($data['observaciones'] ?? '') ?: null];

        if ($tipo_entrega === 2) {
            $estudiante_nombre = trim($data['estudiante_nombre'] ?? '');
            $id_nivel = intval($data['id_nivel'] ?? 0);
            $id_grado = intval($data['id_grado'] ?? 0);
            if ($estudiante_nombre === '' || $id_nivel <= 0 || $id_grado <= 0) {
                echo json_encode(['success' => false, 'mensaje' => 'Para entrega en colegio indica nombre del estudiante, nivel y grado.']);
                exit;
            }
            $entrega['estudiante_nombre'] = $estudiante_nombre;
            $entrega['id_nivel'] = $id_nivel;
            $entrega['id_grado'] = $id_grado;
        } elseif ($tipo_entrega === 1) {
            // Recojo en tienda: quién recoge. 'yo' = el comprador; 'otra' = otra persona
            // de la que se pide DNI + nombres. El DNI/nombres del comprador se resuelven
            // SIEMPRE en servidor desde su cuenta, nunca de lo que mande el navegador.
            $quien_recoge = trim($data['quien_recoge'] ?? 'yo');
            if ($quien_recoge === 'otra') {
                $recoge_dni = trim($data['recoge_dni'] ?? '');
                $recoge_nombre = trim($data['recoge_nombre'] ?? '');
                if (!preg_match('/^\d{8}$/', $recoge_dni) || $recoge_nombre === '') {
                    echo json_encode(['success' => false, 'mensaje' => 'Para que otra persona recoja, indica su DNI (8 dígitos) y sus nombres completos.']);
                    exit;
                }
                $entrega['quien_recoge'] = 'otra';
                $entrega['recoge_dni'] = $recoge_dni;
                $entrega['recoge_nombre'] = $recoge_nombre;
            } else {
                require_once dirname(__DIR__) . '/models/M_ClienteWeb.php';
                $cuenta = M_ClienteWeb::singleton()->obtenerPorId((int) $_SESSION['id_cliente']);
                $entrega['quien_recoge'] = 'yo';
                $entrega['recoge_dni'] = trim($cuenta['numero_documento'] ?? '') ?: null;
                $entrega['recoge_nombre'] = trim(($cuenta['nombres_razon_social'] ?? '') . ' ' . ($cuenta['apellidos'] ?? '')) ?: null;
            }
        }

        // Boleta/Factura: el tipo lo elige el cliente, pero la entidad a facturar
        // (id_cliente_facturacion) SIEMPRE se resuelve aquí a partir del número de
        // RUC, nunca de un id que mande el navegador.
        $tipo_comprobante = intval($data['tipo_comprobante'] ?? 1);
        if (!in_array($tipo_comprobante, [1, 2], true)) {
            echo json_encode(['success' => false, 'mensaje' => 'Tipo de comprobante inválido.']);
            exit;
        }

        $id_cliente_facturacion = null;
        if ($tipo_comprobante === 2) {
            $ruc_facturacion = trim((string) ($data['ruc_facturacion'] ?? ''));
            $resolucion = resolverClienteFacturacionPorRuc($ruc_facturacion);
            if (!$resolucion['ok']) {
                echo json_encode(['success' => false, 'mensaje' => $resolucion['mensaje']]);
                exit;
            }
            $id_cliente_facturacion = $resolucion['id_cliente'];
        }

        // Ya no hay identificador de pago externo que validar: el pedido se crea primero,
        // con los totales recalculados en servidor, y su propio id genera la referencia
        // que después se envía a Izipay al pedir el FormToken (ver C_Izipay.php).
        $res = M_Ecommerce::singleton()->crearPedidoPendiente(
            (int) $_SESSION['id_cliente'], $carrito, $entrega, $tipo_comprobante, $id_cliente_facturacion
        );
        echo json_encode([
            'success'   => $res['ok'],
            'mensaje'   => $res['mensaje'] ?? null,
            'id_pedido' => $res['id_pedido'] ?? null,
            'token'     => $res['token'] ?? null,
            'total'     => $res['total'] ?? null,
        ]);
        break;

    // Historial del cliente autenticado ("Mis Pedidos").
    case 'mis_pedidos':
        if (!isset($_SESSION['id_cliente'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Debes iniciar sesión.']);
            exit;
        }
        $pedidos = M_Ecommerce::singleton()->listarPedidosCliente((int) $_SESSION['id_cliente']);
        echo json_encode(['success' => true, 'data' => $pedidos]);
        break;

    case 'listar_pedidos':
        if (!isset($_SESSION['id_usuario'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
            exit;
        }
        $incluirNoCompletados = isset($_GET['incluir_no_completados']) && $_GET['incluir_no_completados'] === '1';
        $pedidos = M_Ecommerce::singleton()->listarPedidos($incluirNoCompletados);
        echo json_encode(['success' => true, 'data' => $pedidos]);
        break;

    case 'detalles_pedido':
        if (!isset($_SESSION['id_usuario'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
            exit;
        }
        $id = intval($_GET['id']);
        $detalles = M_Ecommerce::singleton()->getDetallesPedido($id);
        echo json_encode(['success' => true, 'data' => $detalles]);
        break;

    // Transiciones de estado de un pedido pagado: preparar (1→5), entregar (1|5→2), rechazar (1|5→0).
    // No exige caja abierta: un pedido online se paga por pasarela, nunca con dinero físico.
    case 'gestionar_pedido':
        if (!isset($_SESSION['id_usuario'])) {
            echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
            exit;
        }
        require_once dirname(__DIR__) . '/config/csrf.php';
        csrfRequerir();

        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);
        $id_pedido = intval($data['id_pedido'] ?? 0);
        $accion = $data['accion'] ?? ''; // 'preparar', 'entregar' o 'rechazar'
        $motivo = trim($data['motivo'] ?? '');

        if ($id_pedido <= 0 || !in_array($accion, ['preparar', 'entregar', 'rechazar'], true)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos.']);
            exit;
        }

        // Rechazar (implica devolver dinero al cliente por fuera del sistema) queda reservado al Administrador.
        if ($accion === 'rechazar' && $_SESSION['rol'] !== 'Administrador') {
            echo json_encode(['success' => false, 'mensaje' => 'Solo un Administrador puede rechazar un pedido.']);
            exit;
        }

        $res = M_Ecommerce::singleton()->gestionarPedido($id_pedido, $accion, $_SESSION['id_usuario'], $motivo ?: null);

        // Al entregar, la venta ya quedó confirmada arriba; el envío a SUNAT es
        // best-effort y nunca debe revertir la entrega ya hecha (ver M_Sunat::emitir(),
        // que deja estado_sunat=1 pendiente si SUNAT está caído, para el barrido).
        if ($res['ok'] && $accion === 'entregar' && !empty($res['id_venta'])) {
            require_once dirname(__DIR__) . '/models/M_Sunat.php';
            try {
                M_Sunat::singleton()->emitir((int) $res['id_venta']);
            } catch (Exception $e) {
                // Silencioso a propósito: el barrido de pendientes lo reintentará.
            }
        }

        echo json_encode(['success' => $res['ok'], 'mensaje' => $res['mensaje']]);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
