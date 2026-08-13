<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/models/M_LectorHoja.php';
require_once dirname(__DIR__) . '/models/M_Importacion.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {

    // Sube un archivo, lo lee (sin tocar la BD) y devuelve sus hojas + una
    // vista previa de filas para que el usuario confirme antes de importar.
    case 'preflight':
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["success" => false, "mensaje" => "No se recibió ningún archivo válido."]);
            exit;
        }

        $tmp = $_FILES['archivo']['tmp_name'];
        $nombreOriginal = $_FILES['archivo']['name'];
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            echo json_encode(["success" => false, "mensaje" => "Solo se aceptan archivos .xlsx o .csv. Si tienes un .xls, ábrelo en Excel y usa \"Guardar como\" → .xlsx."]);
            exit;
        }

        // ZipArchive necesita un archivo con la extensión correcta en su ruta real
        // (algunos entornos la infieren del nombre); tmp_name de PHP no la trae.
        $rutaTrabajo = $tmp . '.' . $extension;
        if (!copy($tmp, $rutaTrabajo)) {
            echo json_encode(["success" => false, "mensaje" => "No se pudo procesar el archivo subido."]);
            exit;
        }

        try {
            $hojaSolicitada = isset($_POST['hoja']) && $_POST['hoja'] !== '' ? $_POST['hoja'] : null;
            $hojas = M_LectorHoja::hojas($rutaTrabajo);
            $hojaActiva = ($hojaSolicitada !== null && in_array($hojaSolicitada, $hojas, true)) ? $hojaSolicitada : ($hojas[0] ?? null);
            $filas = $hojaActiva !== null ? M_LectorHoja::leer($rutaTrabajo, $hojaActiva, 40) : [];

            echo json_encode([
                "success" => true,
                "hojas" => $hojas,
                "hoja_activa" => $hojaActiva,
                "filas" => $filas,
                "nombre_archivo" => $nombreOriginal,
            ]);
        } catch (Exception $e) {
            echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
        } finally {
            @unlink($rutaTrabajo);
        }
        break;

    // Importa el padrón de alumnos (upsert por código, idempotente).
    case 'importar_padron':
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["success" => false, "mensaje" => "No se recibió ningún archivo válido."]);
            exit;
        }
        $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            echo json_encode(["success" => false, "mensaje" => "Solo se aceptan archivos .xlsx o .csv."]);
            exit;
        }
        $rutaTrabajo = $_FILES['archivo']['tmp_name'] . '.' . $extension;
        if (!copy($_FILES['archivo']['tmp_name'], $rutaTrabajo)) {
            echo json_encode(["success" => false, "mensaje" => "No se pudo procesar el archivo subido."]);
            exit;
        }
        try {
            $hoja = isset($_POST['hoja']) && $_POST['hoja'] !== '' ? $_POST['hoja'] : null;
            $resumen = M_Importacion::singleton()->importarPadron(
                $rutaTrabajo, $hoja, $_FILES['archivo']['name'], (int) $_SESSION['id_usuario']
            );
            echo json_encode(["success" => true, "resumen" => $resumen]);
        } catch (Exception $e) {
            echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
        } finally {
            @unlink($rutaTrabajo);
        }
        break;

    // Importa comprobantes de pago (upsert por comprobante). Por defecto trae
    // TODO el histórico de pensiones del archivo; mes/año son un filtro opcional
    // para restringir la carga a un solo periodo si el usuario lo pide.
    case 'importar_comprobantes':
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["success" => false, "mensaje" => "No se recibió ningún archivo válido."]);
            exit;
        }
        $mesRaw = isset($_POST['mes']) ? trim((string) $_POST['mes']) : '';
        $anioRaw = isset($_POST['anio']) ? trim((string) $_POST['anio']) : '';
        $mes = $mesRaw !== '' ? (int) $mesRaw : null;
        $anio = $anioRaw !== '' ? (int) $anioRaw : null;
        if (($mes !== null && ($mes < 1 || $mes > 12)) || ($anio !== null && $anio < 2000)) {
            echo json_encode(["success" => false, "mensaje" => "Mes/año inválidos."]);
            exit;
        }
        $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            echo json_encode(["success" => false, "mensaje" => "Solo se aceptan archivos .xlsx o .csv."]);
            exit;
        }
        $rutaTrabajo = $_FILES['archivo']['tmp_name'] . '.' . $extension;
        if (!copy($_FILES['archivo']['tmp_name'], $rutaTrabajo)) {
            echo json_encode(["success" => false, "mensaje" => "No se pudo procesar el archivo subido."]);
            exit;
        }
        try {
            $hoja = isset($_POST['hoja']) && $_POST['hoja'] !== '' ? $_POST['hoja'] : null;
            $resumen = M_Importacion::singleton()->importarComprobantes(
                $rutaTrabajo, $hoja, $_FILES['archivo']['name'], $mes, $anio, (int) $_SESSION['id_usuario']
            );
            echo json_encode(["success" => true, "resumen" => $resumen]);
        } catch (Exception $e) {
            echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
        } finally {
            @unlink($rutaTrabajo);
        }
        break;

    case 'historial':
        echo json_encode(["success" => true, "historial" => M_Importacion::singleton()->historial()]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>