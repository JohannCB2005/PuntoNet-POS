<?php
// Iniciar la sesión PHP para verificar la identidad del usuario
session_start();

// Definir cabecera de respuesta JSON
header('Content-Type: application/json');

// Validar que el usuario esté autenticado
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado.']);
    exit;
}

// Cargar el modelo del Kardex
require_once dirname(__DIR__) . '/models/M_Kardex.php';

// Obtener la acción del parámetro GET
$action = isset($_GET['action']) ? $_GET['action'] : '';
$model  = M_Kardex::singleton();

// Enrutar según la acción solicitada
switch ($action) {
    
    // Obtiene una lista simplificada de insumos (para selectores de opciones en el módulo de Kardex)
    case 'listar_insumos':
        $insumos = $model->listarInsumos();
        echo json_encode(['success' => true, 'data' => $insumos]);
        break;

    // Obtiene los movimientos de inventario de un insumo bajo filtros específicos
    case 'movimientos':
        $id_insumo = isset($_GET['id_insumo']) ? intval($_GET['id_insumo']) : 0;
        $desde     = isset($_GET['desde'])     ? trim($_GET['desde'])       : null;
        $hasta     = isset($_GET['hasta'])     ? trim($_GET['hasta'])       : null;
        $tipo      = isset($_GET['tipo'])      ? trim($_GET['tipo'])        : 'todos'; // entrada, salida, todos
        $busqueda  = isset($_GET['busqueda'])  ? trim($_GET['busqueda'])    : '';      // término de búsqueda manual

        // Validación de insumo
        if ($id_insumo <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'Insumo inválido.']);
            exit;
        }

        // Consultar movimientos históricos en el modelo
        $result = $model->obtenerMovimientos($id_insumo, $desde ?: null, $hasta ?: null, $tipo, $busqueda);

        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'mensaje' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'data' => $result]);
        }
        break;

    // Registra un movimiento manual (entrada o salida) en el kardex
    case 'registrar_movimiento':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(['success' => false, 'mensaje' => 'Sin permiso.']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id_insumo'], $data['tipo'], $data['cantidad'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos.']);
            exit;
        }
        $id_insumo = intval($data['id_insumo']);
        $tipo      = in_array($data['tipo'], ['entrada', 'salida']) ? $data['tipo'] : null;
        $cantidad  = floatval($data['cantidad']);
        $referencia = trim($data['referencia'] ?? '');
        $concepto  = trim($data['concepto']   ?? '');
        $id_usuario = $_SESSION['id_usuario'];

        if (!$tipo || $cantidad <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'Tipo o cantidad inválidos.']);
            exit;
        }

        require_once dirname(__DIR__) . '/models/M_Insumo.php';

        // Verificar stock suficiente para salidas
        if ($tipo === 'salida') {
            $modelIns = M_Insumo::singleton();
            $ins = $modelIns->obtenerPorId($id_insumo);
            if (!$ins || $ins['stock_piezas'] < $cantidad) {
                echo json_encode(['success' => false, 'mensaje' => 'Stock insuficiente para la salida.']);
                exit;
            }
        }

        $variacion = $tipo === 'entrada' ? $cantidad : -$cantidad;
        M_Insumo::singleton()->actualizarStockRapido($id_insumo, $variacion);

        $precioUnit = floatval($data['precio_unitario'] ?? 0);

        if ($model->registrarMovimiento($id_insumo, $tipo, $cantidad, $precioUnit, $referencia, $concepto, $id_usuario)) {
            echo json_encode(['success' => true, 'mensaje' => 'Movimiento registrado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al registrar el movimiento.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>
