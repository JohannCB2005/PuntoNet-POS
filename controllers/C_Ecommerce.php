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

    // Consulta pública: qué métodos de pago están habilitados y configurados en el
    // panel. La usa el checkout para mostrar/ocultar métodos. Incluye el cobro por
    // verificación manual (QR + bancos), que no depende de ninguna pasarela.
    case 'pasarelas':
        header('Cache-Control: no-store, no-cache, must-revalidate');
        require_once dirname(__DIR__) . '/models/M_Taypi.php';
        require_once dirname(__DIR__) . '/models/M_Izipay.php';

        require_once dirname(__DIR__) . '/config/settings.php';
        $manualHabilitado = configuracion('PAGO_MANUAL_HABILITADO') === 'SI';

        // Los QR de Yape y Plin se muestran REDIBUJADOS en el checkout (más nítidos que
        // una captura). El contenido EMVCo se guarda en Configuración → Pagos; estos
        // valores son el fallback por defecto decodificado de los QR del comercio.
        $qrYapeContenido = configuracion('PAGO_MANUAL_QR_YAPE_CONTENIDO', '00020101021139324c6cec2d66db5cc7bcb5463fb2490aca5204561153036045802PE5906YAPERO6004Lima63049501');
        $qrPlinContenido = configuracion('PAGO_MANUAL_QR_PLIN_CONTENIDO', '0002015802PE0102115204482953036045912P2P Transfer6004Lima26560032876acd9027f84481b6073a9a67e4103b0116Plin Network P2P63041109');
        $qrIzipayRuta    = configuracion('PAGO_MANUAL_BILLETERA_QR', '');
        // Cada billetera se habilita con su propio interruptor (default SI para no romper
        // configuraciones existentes); se muestra solo si además tiene contenido.
        $yapeOk  = configuracion('PAGO_MANUAL_BILLETERA_YAPE_HABILITADO', 'SI') === 'SI' && $qrYapeContenido !== '';
        $plinOk  = configuracion('PAGO_MANUAL_BILLETERA_PLIN_HABILITADO', 'SI') === 'SI' && $qrPlinContenido !== '';
        $izipayOk = configuracion('PAGO_MANUAL_BILLETERA_IZIPAY_HABILITADO', 'SI') === 'SI' && $qrIzipayRuta !== '';
        $manualBilletera = $manualHabilitado
            && configuracion('PAGO_MANUAL_BILLETERA_HABILITADO') === 'SI'
            && ($yapeOk || $plinOk || $izipayOk);

        $logosBanco = [
            'bcp'        => 'assets/pagos/bcp.webp',
            'bbva'       => 'assets/pagos/bbva.webp',
            'interbank'  => 'assets/pagos/interbank.webp',
            'scotiabank' => 'assets/pagos/scotiabank.webp',
        ];
        $bancos = [];
        foreach (['BCP', 'BBVA', 'INTERBANK', 'SCOTIABANK'] as $banco) {
            // Cada banco se habilita con su propio interruptor (default SI) y además
            // debe tener número de cuenta cargado para mostrarse.
            if (configuracion('PAGO_MANUAL_' . $banco . '_HABILITADO', 'SI') !== 'SI') continue;
            $cuenta = configuracion('PAGO_MANUAL_' . $banco . '_CUENTA', '');
            if ($cuenta === '') continue;
            $codigo = strtolower($banco);
            $bancos[] = [
                'codigo'  => $codigo,
                'nombre'  => $banco === 'INTERBANK' ? 'Interbank' : $banco,
                'titular' => configuracion('PAGO_MANUAL_' . $banco . '_TITULAR', ''),
                'cuenta'  => $cuenta,
                'cci'     => configuracion('PAGO_MANUAL_' . $banco . '_CCI', ''),
                'logo'    => $logosBanco[$codigo] ?? null,
            ];
        }
        $manualTransferencia = $manualHabilitado
            && configuracion('PAGO_MANUAL_TRANSFERENCIA_HABILITADO') === 'SI'
            && !empty($bancos);

        $manual = [
            'habilitado'    => $manualHabilitado,
            'billetera'     => $manualBilletera,
            'transferencia' => $manualTransferencia,
            'datos'         => [
                'minutos'       => max(5, (int) (configuracion('PAGO_MANUAL_MINUTOS', '15') ?: 15)),
                'instrucciones' => configuracion('PAGO_MANUAL_INSTRUCCIONES', ''),
                'billetera'     => $manualBilletera ? [
                    'titular'           => configuracion('PAGO_MANUAL_BILLETERA_TITULAR', ''),
                    'logo_yape'         => 'assets/pagos/yape-logo.png',
                    'logo_plin'         => 'assets/pagos/plin-logo.webp',
                    'qr_yape_contenido' => $yapeOk ? $qrYapeContenido : '',
                    'qr_plin_contenido' => $plinOk ? $qrPlinContenido : '',
                    'qr_izipay'         => $izipayOk ? $qrIzipayRuta : '',
                ] : null,
                'bancos'        => $bancos,
            ],
        ];

        echo json_encode([
            'success' => true,
            'taypi'   => M_Taypi::singleton()->estaConfigurado(),
            'izipay'  => M_Izipay::singleton()->estaConfigurado(),
            'manual'  => $manual,
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

        // Método elegido en el checkout: tarjeta/qr (pasarelas) o billetera/transferencia
        // (verificación manual). El pedido lo registra para el flujo y la reserva de stock.
        $metodo = (string) ($data['metodo'] ?? '');
        if (!in_array($metodo, ['tarjeta', 'qr', 'billetera', 'transferencia'], true)) {
            $metodo = '';
        }
        require_once dirname(__DIR__) . '/config/settings.php';
        $minutosManual = null;
        if (in_array($metodo, ['billetera', 'transferencia'], true)) {
            $minutosManual = max(5, (int) (configuracion('PAGO_MANUAL_MINUTOS', '15') ?: 15));
        }

        // Ventana de reserva de las pasarelas (tarjeta/QR): configurable, default 15 min.
        $minutosPasarela = max(5, (int) (configuracion('RESERVA_PASARELA_MINUTOS', '15') ?: 15));

        // Ya no hay identificador de pago externo que validar: el pedido se crea primero,
        // con los totales recalculados en servidor, y su propio id genera la referencia
        // que después se envía a Izipay al pedir el FormToken (ver C_Izipay.php).
        $res = M_Ecommerce::singleton()->crearPedidoPendiente(
            (int) $_SESSION['id_cliente'], $carrito, $entrega, $tipo_comprobante, $id_cliente_facturacion,
            $metodo === '' ? null : $metodo,
            $metodo === 'tarjeta' || $metodo === 'qr' ? $minutosPasarela : $minutosManual
        );
        echo json_encode([
            'success'      => $res['ok'],
            'mensaje'      => $res['mensaje'] ?? null,
            'id_pedido'    => $res['id_pedido'] ?? null,
            'token'        => $res['token'] ?? null,
            'total'        => $res['total'] ?? null,
            'fecha_expira' => $res['fecha_expira'] ?? null,
            'minutos'      => $res['minutos'] ?? null,
        ]);
        break;

    // El cliente decide su método de pago en el paso 4 (después de reservar).
    // Solo se persiste el método: la ventana de reserva ya se fijó al crear el
    // pedido y el contador no debe reiniciarse al cambiar de opción.
    case 'actualizar_metodo':
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $idPedido = (int) ($data['id_pedido'] ?? 0);
        $token = (string) ($data['token'] ?? '');
        $metodo = (string) ($data['metodo'] ?? '');
        $modelo = M_Ecommerce::singleton();
        $pedido = $modelo->getPedidoPublico($idPedido, $token);
        if (!$pedido) {
            echo json_encode(['success' => false, 'mensaje' => 'No encontramos tu pedido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ((int) $pedido['estado'] !== 3) {
            echo json_encode(['success' => false, 'mensaje' => 'Tu pedido ya no está en reserva.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $res = $modelo->actualizarMetodoPago($idPedido, $metodo);
        echo json_encode([
            'success'      => $res['ok'],
            'mensaje'      => $res['mensaje'] ?? null,
            'fecha_expira' => $res['fecha_expira'] ?? null,
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

    // Transiciones de estado de un pedido: preparar (1→5), entregar (1|5→2),
    // aprobar pago manual (6→1) y rechazar (1|5|6→0).
    // No exige caja abierta: un pedido online se paga por pasarela o verificación manual,
    // nunca con dinero físico en caja.
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
        $accion = $data['accion'] ?? ''; // 'preparar', 'entregar', 'rechazar' o 'aprobar'
        $motivo = trim($data['motivo'] ?? '');
        $codigoConfirmacion = trim((string) ($data['codigo_confirmacion'] ?? ''));
        $saltarCodigo = !empty($data['saltar_codigo']);

        if ($id_pedido <= 0 || !in_array($accion, ['preparar', 'entregar', 'rechazar', 'aprobar'], true)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos.']);
            exit;
        }

        // Rechazar (implica devolver dinero al cliente por fuera del sistema) y aprobar
        // (confirma un cobro manual) quedan reservados al Administrador.
        if (in_array($accion, ['rechazar', 'aprobar'], true) && $_SESSION['rol'] !== 'Administrador') {
            echo json_encode(['success' => false, 'mensaje' => 'Solo un Administrador puede ' . ($accion === 'aprobar' ? 'aprobar' : 'rechazar') . ' un pedido.']);
            exit;
        }

        $res = M_Ecommerce::singleton()->gestionarPedido(
            $id_pedido,
            $accion,
            $_SESSION['id_usuario'],
            $motivo ?: null,
            $codigoConfirmacion ?: null,
            $saltarCodigo
        );

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
