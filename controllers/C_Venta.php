<?php
// Iniciar sesión PHP para el control de identidad y roles de usuario
session_start();

// Configurar cabecera para responder en formato JSON
header('Content-Type: application/json');

// Validar que el usuario esté autenticado
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

// Cargar entidades y modelos requeridos para procesar ventas
require_once dirname(__DIR__) . '/entities/Venta.php';
require_once dirname(__DIR__) . '/entities/DetalleVenta.php';
require_once dirname(__DIR__) . '/models/M_Venta.php';
require_once dirname(__DIR__) . '/models/M_Caja.php';
require_once dirname(__DIR__) . '/config/sunat.php';

// Obtener la acción a realizar
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Capturar los parámetros de entrada en formato JSON o POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el modelo centralizado de Ventas usando Singleton
$model = M_Venta::singleton();

// Enrutar según la acción
switch ($action) {
    
    // Lista el historial de ventas
    case 'listar':
        // Restricción: Si el rol no es Administrador, solo listar las ventas hechas por el usuario actual
        $id_vendedor = ($_SESSION['rol'] !== 'Administrador') ? $_SESSION['id_usuario'] : null;
        echo json_encode($model->listar($id_vendedor));
        break;

    // Registra una nueva venta con sus respectivas líneas de detalle
    case 'crear':
        $id_usuario = $_SESSION['id_usuario'];
        $id_cliente = isset($input['id_cliente']) && $input['id_cliente'] ? intval($input['id_cliente']) : 1;

        $tipo_comprobante = isset($input['tipo_comprobante']) ? intval($input['tipo_comprobante']) : 1; // 1 = Boleta, 2 = Factura, 3 = Nota de venta
        $total = isset($input['total']) ? floatval($input['total']) : 0.0;
        $cart = isset($input['cart']) ? $input['cart'] : []; // Elementos del carrito: {id_producto, cantidad, precio, subtotal}

        // Pago mixto: el front manda una o más líneas de pago (método + monto + referencia
        // opcional). M_Venta::registrar() valida que la suma calce con el total y calcula
        // el metodo_pago resumen (único, o 4=Mixto si hay más de uno distinto).
        $pagosInput = isset($input['pagos']) && is_array($input['pagos']) ? $input['pagos'] : [];

        // Validación inicial
        if (empty($cart) || $total <= 0) {
            echo json_encode(["success" => false, "mensaje" => "El carrito está vacío o el total es cero."]);
            exit;
        }

        // Boleta sobre el umbral SUNAT exige identificar al comprador con documento
        // — "Público General" (id_cliente=1) deja de ser una opción válida. Se
        // valida aquí, en servidor, para que no baste con saltarse el aviso de la UI.
        if ($tipo_comprobante === 1 && $total > SUNAT_BOLETA_UMBRAL_DNI && $id_cliente <= 1) {
            echo json_encode(["success" => false, "mensaje" => "Ventas de Boleta mayores a S/ " . number_format(SUNAT_BOLETA_UMBRAL_DNI, 2) . " requieren identificar al cliente con su documento (no se puede vender a Público General)."]);
            exit;
        }

        if (empty($pagosInput)) {
            echo json_encode(["success" => false, "mensaje" => "Debes indicar al menos una forma de pago."]);
            exit;
        }
        foreach ($pagosInput as $p) {
            $mp = intval($p['metodo_pago'] ?? 0);
            $monto = floatval($p['monto'] ?? 0);
            if (!in_array($mp, [1, 2, 3], true) || $monto <= 0) {
                echo json_encode(["success" => false, "mensaje" => "Hay una línea de pago inválida."]);
                exit;
            }
        }

        // CONTROL DE CAJA: Validar obligatoriamente que el usuario tenga una sesión de caja abierta
        $modelCaja = M_Caja::singleton();
        $cajaAbierta = $modelCaja->obtenerCajaAbierta($id_usuario);
        if (!$cajaAbierta) {
            echo json_encode(["success" => false, "mensaje" => "Debes abrir caja antes de registrar ventas."]);
            exit;
        }

        // Crear la entidad principal de Venta, ligada a la caja física que recibe el dinero.
        // metodo_pago aquí es solo un valor de arranque: M_Venta::registrar() lo recalcula
        // a partir de $venta->pagos antes de insertar la cabecera.
        $venta = new Venta(null, $id_usuario, $id_cliente, '', $tipo_comprobante, $total, 1, 1, $cajaAbierta['id_caja'], 1);
        foreach ($pagosInput as $p) {
            $venta->agregarPago(intval($p['metodo_pago']), floatval($p['monto']), trim((string) ($p['referencia'] ?? '')) ?: null);
        }

        // El precio NUNCA se toma del navegador: se relee del catálogo y se recalcula
        // el subtotal. Sin esto se puede registrar una prenda de S/200 a S/1 y el
        // kardex, los reportes y el comprobante SUNAT lo dan por bueno. Mismo criterio
        // que el checkout público (M_Ecommerce::calcularCarrito()).
        require_once dirname(__DIR__) . '/models/M_Producto.php';
        $preciosVigentes = M_Producto::singleton()->obtenerPreciosVigentes(
            array_map(fn($i) => intval($i['id_producto'] ?? 0), $cart)
        );

        // Cargar los items del carrito dentro de la entidad de venta
        $totalCalculado = 0.0;
        foreach ($cart as $item) {
            $id_producto  = intval($item['id_producto']);
            // piezas: unidades físicas vendidas (descuenta stock)
            $piezas     = floatval($item['piezas'] ?? $item['cantidad'] ?? 0);

            if (!isset($preciosVigentes[$id_producto])) {
                echo json_encode(["success" => false, "mensaje" => "Uno de los productos del carrito ya no existe en el catálogo."]);
                exit;
            }
            $precio   = $preciosVigentes[$id_producto];
            $subtotal = round($precio * $piezas, 2);
            $totalCalculado += $subtotal;

            $detalle = new DetalleVenta(null, $id_producto, $piezas, $precio, 0.0, $subtotal);
            $venta->agregarDetalle($detalle);
        }

        // El total tampoco se cree: debe coincidir con lo recalculado. Si no calza,
        // el carrito que vio el usuario ya no refleja el catálogo (cambio de precio
        // a mitad de la venta) o la petición viene manipulada.
        $totalCalculado = round($totalCalculado, 2);
        if (abs($totalCalculado - $total) > 0.01) {
            echo json_encode(["success" => false, "mensaje" => "El total no coincide con los precios vigentes (S/ " . number_format($totalCalculado, 2) . "). Vuelve a cargar el carrito."]);
            exit;
        }
        $venta->total = $totalCalculado;

        // Intentar registrar la venta de manera transaccional en la DB (afectará stock e inventario)
        $resultado = $model->registrar($venta);
        if ($resultado['ok']) {
            // La venta ya quedó confirmada e impresa; el envío a SUNAT es best-effort
            // y nunca debe revertirla (M_Sunat::emitir() ignora tipo_comprobante=3 y
            // deja estado_sunat=1 pendiente si SUNAT está caído, para el barrido).
            require_once dirname(__DIR__) . '/models/M_Sunat.php';
            try {
                M_Sunat::singleton()->emitir((int) $resultado['id_venta']);
            } catch (Exception $e) {
                // Silencioso a propósito: el barrido de pendientes lo reintentará.
            }
            echo json_encode(["success" => true, "mensaje" => "Venta registrada con éxito.", "id_venta" => $resultado['id_venta']]);
        } else {
            echo json_encode(["success" => false, "mensaje" => $resultado['mensaje']]);
        }
        break;

    // Anula una venta previamente registrada y devuelve la mercadería al inventario
    case 'anular':
        $id_venta = isset($input['id_venta']) ? intval($input['id_venta']) : 0;
        $motivo   = trim((string) ($input['motivo'] ?? 'Anulación solicitada por el usuario'));

        if ($id_venta <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de venta inválido."]);
            exit;
        }

        // Una venta de separación (anticipo/abono/despacho) no se anula por aquí: solo
        // la venta del anticipo tiene detalle_ventas real, así que anularla desde
        // Historial dejaría la separación con un saldo/stock inconsistente. Debe
        // anularse desde el módulo de Separaciones (C_Separacion.php?action=anular).
        if ($model->perteneceASeparacion($id_venta)) {
            echo json_encode(["success" => false, "mensaje" => "Esta venta pertenece a una separación. Anúlala desde el módulo de Separaciones."]);
            exit;
        }

        // Restricción de seguridad: Si no es Admin, verificar que la venta pertenezca al vendedor logueado
        if ($_SESSION['rol'] !== 'Administrador') {
            $ventas_vendedor = $model->listar($_SESSION['id_usuario']);
            $pertence_a_usuario = false;
            foreach ($ventas_vendedor as $v) {
                if ($v['id_venta'] == $id_venta) {
                    $pertence_a_usuario = true;
                    break;
                }
            }
            if (!$pertence_a_usuario) {
                echo json_encode(["success" => false, "mensaje" => "No autorizado para anular esta venta."]);
                exit;
            }
        }

        // Ejecutar proceso de anulación en base de datos. La anulación LOCAL
        // (estado + stock) es inmediata siempre; si el comprobante ya fue
        // aceptado por SUNAT, además hay que encolarle la baja (best-effort,
        // fuera de la transacción — un fallo de red aquí no debe deshacer la
        // anulación local, que ya está confirmada).
        $resultadoAnular = $model->anular($id_venta);
        if (!$resultadoAnular['ok']) {
            echo json_encode(["success" => false, "mensaje" => $resultadoAnular['mensaje']]);
            exit;
        }

        $mensaje = "Venta anulada con éxito. El stock ha sido retornado.";
        if (!empty($resultadoAnular['requiere_baja_sunat'])) {
            require_once dirname(__DIR__) . '/models/M_Sunat.php';
            $resultadoBaja = M_Sunat::singleton()->darDeBaja($id_venta, $motivo);
            $mensaje .= $resultadoBaja['ok']
                ? ' Se notificó la baja a SUNAT.'
                : ' Aviso: no se pudo notificar la baja a SUNAT automáticamente (' . $resultadoBaja['mensaje'] . '); se reintentará.';
        }
        echo json_encode(["success" => true, "mensaje" => $mensaje]);
        break;

    // Devuelve los detalles (líneas de venta) de una venta específica
    case 'detalles':
        $id_venta = isset($_GET['id_venta']) ? intval($_GET['id_venta']) : (isset($input['id_venta']) ? intval($input['id_venta']) : 0);

        if ($id_venta <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de venta inválido."]);
            exit;
        }

        // Restricción: Si no es Admin, solo puede consultar detalles de sus propias ventas
        if ($_SESSION['rol'] !== 'Administrador') {
            $ventas_vendedor = $model->listar($_SESSION['id_usuario']);
            $pertence_a_usuario = false;
            foreach ($ventas_vendedor as $v) {
                if ($v['id_venta'] == $id_venta) {
                    $pertence_a_usuario = true;
                    break;
                }
            }
            if (!$pertence_a_usuario) {
                echo json_encode([]);
                exit;
            }
        }

        // Obtener detalles desde el modelo de base de datos
        echo json_encode($model->obtenerDetallesPorVenta($id_venta));
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
