<?php
// Iniciar la sesión PHP para acceder a los datos de autenticación del usuario
session_start();

// Establecer cabecera para indicar que la respuesta será en formato JSON
header('Content-Type: application/json');

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

// Requerir el modelo centralizado de Caja
require_once dirname(__DIR__) . '/models/M_Caja.php';

// Obtener la acción a realizar mediante la URL (GET)
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Leer los datos del cuerpo de la petición (JSON) si están disponibles, o usar $_POST en su defecto
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el Modelo de Caja usando el patrón Singleton
$model = M_Caja::singleton();
$id_usuario = $_SESSION['id_usuario'];

// Enrutar la petición según la acción solicitada
switch ($action) {
    
    // Obtiene el estado actual de la caja del usuario autenticado (si está abierta o no)
    case 'estado':
        $caja = $model->obtenerCajaAbierta($id_usuario);
        if ($caja) {
            // Si la caja está abierta, calcular en vivo el total de ventas acumuladas desde la hora de apertura
            $ventas_acumuladas = $model->calcularVentasAcumuladas($id_usuario, $caja['fecha_apertura']);
            $desglose = $model->calcularDesglosePorMetodo($id_usuario, $caja['fecha_apertura']);
            
            echo json_encode([
                "success" => true, 
                "caja_abierta" => true, 
                "caja" => $caja, 
                "ventas_acumuladas" => $ventas_acumuladas,
                "desglose" => $desglose
            ]);
        } else {
            echo json_encode(["success" => true, "caja_abierta" => false]);
        }
        break;

    // Registra la apertura de una nueva sesión de caja
    case 'abrir':
        $monto_apertura = isset($input['monto_apertura']) ? floatval($input['monto_apertura']) : 0.00;
        
        // Validación del monto inicial
        if ($monto_apertura < 0) {
            echo json_encode(["success" => false, "mensaje" => "Monto inválido."]);
            exit;
        }

        // Registrar apertura en el modelo
        if ($model->abrirCaja($id_usuario, $monto_apertura)) {
            echo json_encode(["success" => true, "mensaje" => "Caja aperturada con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al abrir la caja. Es posible que ya tengas una caja abierta."]);
        }
        break;

    // Cierra la sesión activa de caja, calculando el balance final y diferencias
    case 'cerrar':
        $id_caja = isset($input['id_caja']) ? intval($input['id_caja']) : 0;
        $cierre_efectivo = isset($input['cierre_efectivo']) ? floatval($input['cierre_efectivo']) : 0.00;
        $cierre_yape = isset($input['cierre_yape']) ? floatval($input['cierre_yape']) : 0.00;
        $cierre_tarjeta = isset($input['cierre_tarjeta']) ? floatval($input['cierre_tarjeta']) : 0.00;
        $observaciones = isset($input['observaciones']) ? trim($input['observaciones']) : '';

        // Validaciones básicas de parámetros
        if ($id_caja <= 0 || $cierre_efectivo < 0 || $cierre_yape < 0 || $cierre_tarjeta < 0) {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos."]);
            exit;
        }

        // Procesar el cierre de la caja
        if ($model->cerrarCaja($id_caja, $id_usuario, $cierre_efectivo, $cierre_yape, $cierre_tarjeta, $observaciones)) {
            echo json_encode(["success" => true, "mensaje" => "Caja cerrada con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al cerrar la caja."]);
        }
        break;

    // Lista todas las sesiones de cajas para una fecha en particular (Solo para el Administrador)
    case 'listar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos."]);
            exit;
        }
        // Si no se envía fecha por GET, se asume la fecha actual del servidor
        $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
        echo json_encode($model->listarPorFecha($fecha));
        break;

    // Obtiene el detalle completo de una caja
    case 'detalle':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "No tienes permisos."]);
            exit;
        }
        $id_caja = isset($input['id_caja']) ? intval($input['id_caja']) : (isset($_GET['id_caja']) ? intval($_GET['id_caja']) : 0);
        $data = $model->obtenerDetalleCaja($id_caja);
        if ($data) {
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se pudo obtener el detalle."]);
        }
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
