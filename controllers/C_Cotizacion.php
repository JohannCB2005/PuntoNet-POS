<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit;
}

require_once dirname(__DIR__) . '/models/M_Cotizacion.php';
require_once dirname(__DIR__) . '/entities/Cotizacion.php';
require_once dirname(__DIR__) . '/entities/DetalleCotizacion.php';
require_once dirname(__DIR__) . '/models/M_Venta.php';
require_once dirname(__DIR__) . '/entities/Venta.php';
require_once dirname(__DIR__) . '/entities/DetalleVenta.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$modelo = M_Cotizacion::singleton();

switch ($action) {
    case 'listar':
        // Vendedor solo ve las suyas (opcional, o todos ven todas. Dejaremos Vendedor vea las suyas)
        $id_usuario = ($_SESSION['rol'] !== 'Administrador') ? $_SESSION['id_usuario'] : null;
        $cotizaciones = $modelo->listar($id_usuario);
        
        $data = [];
        foreach ($cotizaciones as $c) {
            // Verificar si venció y está pendiente
            $estado = $c['estado'];
            if ($estado == 1 && strtotime($c['fecha_vencimiento']) < strtotime(date('Y-m-d'))) {
                $estado = 3; // Vencida visualmente
            }
            
            $cliente_text = !empty($c['apellidos_cliente']) 
                            ? htmlspecialchars($c['nombre_cliente'] . ' ' . $c['apellidos_cliente'])
                            : htmlspecialchars($c['nombre_cliente']);

            $data[] = [
                'id_cotizacion' => $c['id_cotizacion'],
                'codigo' => htmlspecialchars($c['codigo']),
                'fecha_emision' => date('d/m/Y', strtotime($c['fecha_emision'])),
                'fecha_vencimiento' => date('d/m/Y', strtotime($c['fecha_vencimiento'])),
                'cliente' => $cliente_text,
                'total' => $c['total'],
                'estado' => $estado,
                'vendedor' => htmlspecialchars($c['nombre_usuario'])
            ];
        }
        echo json_encode(['data' => $data]);
        break;

    case 'crear':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            exit;
        }

        $id_cliente = !empty($input['id_cliente']) ? intval($input['id_cliente']) : null;
        $cliente_manual = !empty($input['cliente_nombre_manual']) ? trim($input['cliente_nombre_manual']) : '';
        
        if (empty($id_cliente) && empty($cliente_manual)) {
            echo json_encode(['success' => false, 'mensaje' => 'Debe seleccionar un cliente o escribir un nombre.']);
            exit;
        }

        $dias_validez = isset($input['validez']) ? intval($input['validez']) : 7;
        $fecha_vencimiento = date('Y-m-d', strtotime("+$dias_validez days"));

        $cotizacion = new Cotizacion(
            null, 
            '', 
            $_SESSION['id_usuario'], 
            $id_cliente, 
            $cliente_manual, 
            '', 
            $fecha_vencimiento, 
            $input['subtotal'], 
            $input['igv'], 
            $input['total'], 
            $input['observaciones'] ?? ''
        );

        foreach ($input['detalles'] as $d) {
            $detalle = new DetalleCotizacion(
                null, 
                null, 
                $d['id_insumo'], 
                $d['cantidad'], 
                $d['precio'], 
                $d['subtotal']
            );
            $cotizacion->agregarDetalle($detalle);
        }

        $res = $modelo->registrar($cotizacion);
        if ($res['ok']) {
            echo json_encode(['success' => true, 'id_cotizacion' => $res['id_cotizacion'], 'codigo' => $res['codigo'], 'mensaje' => 'Cotización creada con éxito.']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $res['mensaje']]);
        }
        break;

    case 'obtener':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = $modelo->obtenerPorId($id);
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'No encontrada']);
        }
        break;

    case 'anular':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($modelo->anular($id)) {
            echo json_encode(['success' => true, 'mensaje' => 'Cotización anulada']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al anular']);
        }
        break;

    case 'convertir':
        $input = json_decode(file_get_contents('php://input'), true);
        $id_cot = isset($input['id_cotizacion']) ? intval($input['id_cotizacion']) : 0;
        $tipo_comprobante = isset($input['tipo_comprobante']) ? intval($input['tipo_comprobante']) : 1; // 1=Boleta, 2=Factura, 3=Nota
        $metodo_pago = isset($input['metodo_pago']) ? intval($input['metodo_pago']) : 1; // 1=Efectivo, etc
        
        $cotData = $modelo->obtenerPorId($id_cot);
        if (!$cotData || $cotData['estado'] != 1) {
            echo json_encode(['success' => false, 'mensaje' => 'Cotización no válida o ya convertida/anulada.']);
            exit;
        }

        // Crear objeto Venta
        $venta = new Venta(
            null,
            $_SESSION['id_usuario'],
            $cotData['id_cliente'],
            '',
            $tipo_comprobante,
            $cotData['total'],
            $metodo_pago,
            1
        );

        foreach ($cotData['detalles'] as $d) {
            $detVenta = new DetalleVenta(
                null,
                $d['id_insumo'],
                $d['piezas'],
                $d['precio_unitario'],
                0, // costo_unitario: el M_Venta lo recalcula o lo ignora
                $d['subtotal']
            );
            $venta->agregarDetalle($detVenta);
        }

        $mVenta = M_Venta::singleton();
        $resVenta = $mVenta->registrar($venta);
        
        if ($resVenta['ok']) {
            $modelo->marcarConvertida($id_cot, $resVenta['id_venta']);
            echo json_encode(['success' => true, 'id_venta' => $resVenta['id_venta'], 'mensaje' => 'Cotización convertida a Venta exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al registrar venta: ' . $resVenta['mensaje']]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>
