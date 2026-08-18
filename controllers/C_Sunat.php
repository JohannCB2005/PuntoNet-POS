<?php
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

require_once dirname(__DIR__) . '/config/csrf.php';
csrfRequerir();

require_once dirname(__DIR__) . '/models/M_Sunat.php';
require_once dirname(__DIR__) . '/models/M_Serie.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$mSunat = M_Sunat::singleton();
$mSerie = M_Serie::singleton();

switch ($action) {

    // Reintenta emitir un comprobante que quedó pendiente (estado_sunat=1) o que
    // SUNAT rechazó (3). Emitir consume un correlativo real y declara un monto ante
    // SUNAT, así que se restringe igual que dar_de_baja y nota_credito.
    case 'reenviar':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "Solo un Administrador puede reenviar comprobantes a SUNAT."]);
            exit;
        }
        $id_venta = intval($input['id_venta'] ?? 0);
        if ($id_venta <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de venta inválido."]);
            exit;
        }

        // Solo se reenvía lo que el propio sistema ya intentó emitir. Sin este filtro
        // se podía forzar la emisión de CUALQUIER venta pasando su id a mano — en
        // particular las de separación (origen=4), cuyo total es solo el saldo cobrado
        // ese día y que ni siquiera tienen detalle_ventas propio: se declararía un
        // monto incorrecto y se quemaría un correlativo real.
        require_once dirname(__DIR__) . '/config/conexion.php';
        $stmtEstado = Conexion::singleton()->getConexion()->prepare(
            "SELECT estado_sunat, origen FROM ventas WHERE id_venta = ?"
        );
        $stmtEstado->execute([$id_venta]);
        $ventaEstado = $stmtEstado->fetch(PDO::FETCH_ASSOC);
        if (!$ventaEstado) {
            echo json_encode(["success" => false, "mensaje" => "Venta no encontrada."]);
            exit;
        }
        if (!in_array((int) $ventaEstado['estado_sunat'], [1, 3], true)) {
            echo json_encode(["success" => false, "mensaje" => "Esta venta no está pendiente de envío ante SUNAT."]);
            exit;
        }
        if (in_array((int) $ventaEstado['origen'], [3, 4], true)) {
            echo json_encode(["success" => false, "mensaje" => "Las ventas de cambio de talla y de separación no se emiten como comprobante electrónico por esta vía."]);
            exit;
        }

        $r = $mSunat->emitir($id_venta);
        echo json_encode(["success" => $r['ok'], "mensaje" => $r['mensaje'], "estado_sunat" => $r['estado_sunat']]);
        break;

    // Da de baja un comprobante ya aceptado (estado_sunat=2). Solo Administrador:
    // la anulación ya ocurrió (C_Venta.php?action=anular), esto es reintentar
    // el aviso a SUNAT si el primer intento automático falló.
    case 'dar_de_baja':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "Solo un Administrador puede gestionar bajas ante SUNAT."]);
            exit;
        }
        $id_venta = intval($input['id_venta'] ?? 0);
        $motivo = trim((string) ($input['motivo'] ?? 'Anulación solicitada por el usuario'));
        if ($id_venta <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de venta inválido."]);
            exit;
        }
        $r = $mSunat->darDeBaja($id_venta, $motivo);
        echo json_encode(["success" => $r['ok'], "mensaje" => $r['mensaje']]);
        break;

    // Consulta el ticket de una baja en trámite (estado_sunat=4).
    case 'consultar_baja':
        $id_venta = intval($_GET['id_venta'] ?? 0);
        if ($id_venta <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de venta inválido."]);
            exit;
        }
        $r = $mSunat->consultarBaja($id_venta);
        echo json_encode(["success" => $r['ok'], "mensaje" => $r['mensaje']]);
        break;

    // Emite una Nota de Crédito total sobre un comprobante que ya no se puede
    // anular directamente (>7 días desde su emisión). Solo Administrador.
    case 'nota_credito':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "Solo un Administrador puede emitir Notas de Crédito."]);
            exit;
        }
        require_once dirname(__DIR__) . '/models/M_Caja.php';

        $id_venta = intval($input['id_venta'] ?? 0);
        $motivo = trim((string) ($input['motivo'] ?? ''));
        $pagosInput = isset($input['pagos']) && is_array($input['pagos']) ? $input['pagos'] : [];
        $itemsInput = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

        if ($id_venta <= 0 || $motivo === '') {
            echo json_encode(["success" => false, "mensaje" => "Debes indicar la venta y el motivo de la Nota de Crédito."]);
            exit;
        }
        if (empty($pagosInput)) {
            echo json_encode(["success" => false, "mensaje" => "Debes indicar cómo se devuelve el dinero."]);
            exit;
        }
        foreach ($pagosInput as $p) {
            $mp = intval($p['metodo_pago'] ?? 0);
            $monto = floatval($p['monto'] ?? 0);
            if (!in_array($mp, [1, 2, 3], true) || $monto <= 0) {
                echo json_encode(["success" => false, "mensaje" => "Hay una línea de devolución inválida."]);
                exit;
            }
        }
        foreach ($itemsInput as $it) {
            $idDetalle = intval($it['id_detalle'] ?? 0);
            $cantidad = floatval($it['cantidad'] ?? 0);
            if ($idDetalle <= 0 || $cantidad <= 0) {
                echo json_encode(["success" => false, "mensaje" => "Hay una línea de devolución de mercadería inválida."]);
                exit;
            }
        }

        $modelCaja = M_Caja::singleton();
        $cajaAbierta = $modelCaja->obtenerCajaAbierta($_SESSION['id_usuario']);
        if (!$cajaAbierta) {
            echo json_encode(["success" => false, "mensaje" => "Debes abrir caja antes de emitir una Nota de Crédito (el dinero devuelto sale de tu caja de hoy)."]);
            exit;
        }

        $pagos = array_map(function ($p) {
            return [
                'metodo_pago' => intval($p['metodo_pago']),
                'monto' => floatval($p['monto']),
                'referencia' => trim((string) ($p['referencia'] ?? '')) ?: null,
            ];
        }, $pagosInput);

        $r = $mSunat->emitirNotaCredito($id_venta, $motivo, $_SESSION['id_usuario'], (int) $cajaAbierta['id_caja'], $pagos, $itemsInput);
        echo json_encode(["success" => $r['ok'], "mensaje" => $r['mensaje'], "codigo" => $r['codigo'] ?? null]);
        break;

    // Devuelve el XML firmado y/o el CDR guardados de un documento, para depurar
    // un rechazo o simplemente auditar lo que se envió.
    case 'ver_cdr':
        $tipo_documento = trim((string) ($_GET['tipo_documento'] ?? 'venta'));
        $id_referencia = intval($_GET['id_referencia'] ?? 0);
        if ($id_referencia <= 0) {
            echo json_encode(["success" => false, "mensaje" => "Falta id_referencia."]);
            exit;
        }
        require_once dirname(__DIR__) . '/config/conexion.php';
        $stmt = Conexion::singleton()->getConexion()->prepare(
            "SELECT xml_firmado, cdr_zip_base64, fecha FROM comprobantes_sunat
             WHERE tipo_documento = ? AND id_referencia = ? ORDER BY id_comprobante_sunat DESC LIMIT 1"
        );
        $stmt->execute([$tipo_documento, $id_referencia]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($fila ? ["success" => true, "data" => $fila] : ["success" => false, "mensaje" => "No hay XML/CDR guardado para este documento."]);
        break;

    // Procesa el lote de pendientes (emisiones sin enviar, bajas en trámite,
    // notas de crédito sin confirmar). Acción explícita — ver el porqué en
    // M_Sunat::barrerPendientes().
    case 'procesar_pendientes':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "Solo un Administrador puede procesar el lote de pendientes."]);
            exit;
        }
        $resumen = $mSunat->barrerPendientes();
        echo json_encode(["success" => true, "data" => $resumen]);
        break;

    // --- Gestión de series (módulo de configuración) ---
    case 'listar_series':
        echo json_encode(["success" => true, "data" => $mSerie->listar()]);
        break;

    case 'crear_serie':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "Solo un Administrador puede configurar series."]);
            exit;
        }
        $tipo_comprobante = intval($input['tipo_comprobante'] ?? 0);
        $serie = trim((string) ($input['serie'] ?? ''));
        $correlativo_inicial = intval($input['correlativo_inicial'] ?? 0);
        if (!in_array($tipo_comprobante, [1, 2, 4, 5], true) || $serie === '') {
            echo json_encode(["success" => false, "mensaje" => "Datos de serie inválidos."]);
            exit;
        }
        $r = $mSerie->crear($tipo_comprobante, $serie, $correlativo_inicial);
        echo json_encode($r['ok'] ? ["success" => true] : ["success" => false, "mensaje" => $r['mensaje']]);
        break;

    case 'cambiar_estado_serie':
        if ($_SESSION['rol'] !== 'Administrador') {
            echo json_encode(["success" => false, "mensaje" => "Solo un Administrador puede configurar series."]);
            exit;
        }
        $id_serie = intval($input['id_serie'] ?? 0);
        $activa = !empty($input['activa']);
        if ($id_serie <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de serie inválido."]);
            exit;
        }
        echo json_encode(["success" => $mSerie->cambiarEstado($id_serie, $activa)]);
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
