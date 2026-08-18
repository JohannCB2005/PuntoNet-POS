<?php
/**
 * controllers/C_Configuracion.php
 * API de configuración de la tienda. Solo Administrador.
 *
 * - obtener: devuelve el estado completo del panel (valores efectivos: BD→.env).
 * - guardar: persiste claves de texto + archivos subidos.
 */
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/config/csrf.php';
csrfRequerir();

require_once dirname(__DIR__) . '/models/M_Configuracion.php';

$action = $_GET['action'] ?? '';
$model  = M_Configuracion::singleton();

switch ($action) {

    // Estado completo del panel (para precargar los formularios).
    case 'obtener':
        echo json_encode(["success" => true, "data" => $model->obtenerTodas()]);
        break;

    // Guardar valores de texto (los archivos viajan en el mismo POST multipart).
    case 'guardar':
        $campos = [];
        if (isset($_POST['campos']) && is_array($_POST['campos'])) {
            foreach ($_POST['campos'] as $clave => $valor) {
                // Solo interesan las claves del catálogo; el modelo las vuelve a validar.
                if (is_string($clave)) {
                    $campos[$clave] = is_array($valor) ? '' : (string) $valor;
                }
            }
        }

        $resultado = $model->guardarCampos($campos);

        // Procesar archivos subidos (claves del catálogo con tipo 'archivo').
        $archivosOk = 0;
        foreach ($_FILES as $clave => $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue; // el campo no trae archivo nuevo: conservar el actual
            }
            $res = $model->subirArchivo($clave, $file);
            if ($res['ok']) {
                $archivosOk++;
            } else {
                $resultado['errores'][] = $res['mensaje'];
            }
        }

        if (!$resultado['ok']) {
            echo json_encode(["success" => false, "mensaje" => $resultado['mensaje'] ?? "No se pudo guardar."]);
            exit;
        }
        echo json_encode([
            "success" => true,
            "mensaje" => "Configuración guardada correctamente.",
            "data"    => [
                "campos"    => $resultado['guardadas'],
                "archivos"  => $archivosOk,
                "errores"   => $resultado['errores'] ?? [],
            ],
        ]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
}
