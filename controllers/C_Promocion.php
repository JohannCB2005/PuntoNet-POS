<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/entities/Promocion.php';
require_once dirname(__DIR__) . '/models/M_Promocion.php';
require_once dirname(__DIR__) . '/models/M_Nivel.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$model = M_Promocion::singleton();

function leerPromocionDesdeInput(array $input, int $idUsuario, ?int $idPromocion = null): Promocion {
    $montoMinimo = isset($input['monto_minimo']) && $input['monto_minimo'] !== '' ? (float) $input['monto_minimo'] : null;
    $fechaLimite = isset($input['fecha_limite_pago']) ? trim((string) $input['fecha_limite_pago']) : '';
    $fechaLimite = ($fechaLimite !== '' && DateTime::createFromFormat('Y-m-d', $fechaLimite) !== false) ? $fechaLimite : null;
    return new Promocion(
        trim($input['nombre'] ?? ''),
        (int) ($input['bimestre'] ?? 1),
        (int) ($input['mes_requerido'] ?? 1),
        (int) ($input['anio_requerido'] ?? 0),
        $montoMinimo,
        $fechaLimite,
        isset($input['exige_pension_completa']) ? (int) $input['exige_pension_completa'] : 0,
        isset($input['exige_matriculado']) ? (int) $input['exige_matriculado'] : 1,
        isset($input['exige_neto_positivo']) ? (int) $input['exige_neto_positivo'] : 1,
        trim($input['descripcion'] ?? ''),
        $idUsuario,
        $idPromocion
    );
}

switch ($action) {

    case 'listar':
        echo json_encode($model->listar());
        break;

    case 'previsualizar_beneficiarios':
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : 0;
        $anio = isset($_GET['anio']) ? (int) $_GET['anio'] : 0;
        $exigeMatriculado = isset($_GET['exige_matriculado']) ? (bool) (int) $_GET['exige_matriculado'] : true;
        $exigeNeto = isset($_GET['exige_neto_positivo']) ? (bool) (int) $_GET['exige_neto_positivo'] : true;
        $montoMinimo = isset($_GET['monto_minimo']) && $_GET['monto_minimo'] !== '' ? (float) $_GET['monto_minimo'] : null;
        $fechaLimite = isset($_GET['fecha_limite_pago']) ? trim((string) $_GET['fecha_limite_pago']) : '';
        $fechaLimite = ($fechaLimite !== '' && DateTime::createFromFormat('Y-m-d', $fechaLimite) !== false) ? $fechaLimite : null;
        $exigePensionCompleta = isset($_GET['exige_pension_completa']) ? (bool) (int) $_GET['exige_pension_completa'] : false;
        if ($mes < 1 || $mes > 12 || $anio < 2000) {
            echo json_encode(["success" => false, "mensaje" => "Mes/año inválidos."]);
            exit;
        }
        $total = $model->contarBeneficiarios($mes, $anio, $exigeMatriculado, $exigeNeto, $montoMinimo, $fechaLimite, $exigePensionCompleta);
        echo json_encode(["success" => true, "beneficiarios" => $total]);
        break;

    case 'crear':
        $p = leerPromocionDesdeInput($input, (int) $_SESSION['id_usuario']);
        if ($p->nombre === '' || $p->anio_requerido < 2000) {
            echo json_encode(["success" => false, "mensaje" => "Nombre y año son obligatorios."]);
            exit;
        }
        echo json_encode($model->crear($p));
        break;

    case 'actualizar':
        $id = isset($input['id_promocion']) ? (int) $input['id_promocion'] : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID inválido."]);
            exit;
        }
        $p = leerPromocionDesdeInput($input, (int) $_SESSION['id_usuario'], $id);
        echo json_encode($model->actualizar($p));
        break;

    case 'eliminar':
        $id = isset($input['id_promocion']) ? (int) $input['id_promocion'] : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID inválido."]);
            exit;
        }
        echo json_encode(["success" => $model->eliminar($id), "mensaje" => "Promoción eliminada."]);
        break;

    // ---------- Productos de la promoción (NISSI) ----------

    // Devuelve el catálogo de productos, el detalle ya asignado y los datos
    // necesarios para pintar el modal de asignación (niveles, grados, y si
    // existe una promoción anterior para copiar).
    case 'productos':
        $id = isset($_GET['id_promocion']) ? (int) $_GET['id_promocion'] : 0;
        $promocion = $id > 0 ? $model->obtenerPorId($id) : null;
        if (!$promocion) {
            echo json_encode(["success" => false, "mensaje" => "Promoción no encontrada."]);
            exit;
        }
        $anterior = $model->promocionAnterior($id);
        echo json_encode([
            "success" => true,
            "promocion" => $promocion,
            "productos" => $model->listarProductosCatalogo(),
            "detalle" => $model->productosDePromocion($id),
            "niveles" => M_Nivel::singleton()->listarNiveles(),
            "grados" => M_Nivel::singleton()->listarGrados(),
            "promocion_anterior" => $anterior ? ['id_promocion' => (int) $anterior['id_promocion'], 'nombre' => $anterior['nombre']] : null,
        ]);
        break;

    // Reemplaza el detalle completo de productos de la promoción.
    case 'guardar_productos':
        $id = isset($input['id_promocion']) ? (int) $input['id_promocion'] : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Promoción inválida."]);
            exit;
        }
        $filas = isset($input['filas']) && is_array($input['filas']) ? $input['filas'] : [];
        echo json_encode($model->guardarProductos($id, $filas));
        break;

    // Copia el detalle de productos desde la promoción anterior (o la indicada).
    case 'copiar_productos':
        $id = isset($input['id_promocion']) ? (int) $input['id_promocion'] : 0;
        $origen = isset($input['id_origen']) ? (int) $input['id_origen'] : 0;
        if ($id <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Promoción inválida."]);
            exit;
        }
        if ($origen <= 0 || $origen === $id) {
            $anterior = $model->promocionAnterior($id);
            $origen = $anterior ? (int) $anterior['id_promocion'] : 0;
        }
        if ($origen <= 0) {
            echo json_encode(["success" => false, "mensaje" => "No hay una promoción anterior para copiar."]);
            exit;
        }
        echo json_encode($model->copiarProductos($id, $origen));
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>