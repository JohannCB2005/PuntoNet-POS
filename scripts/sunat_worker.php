<?php
/**
 * scripts/sunat_worker.php
 * Procesa el lote de pendientes ante SUNAT: comprobantes sin enviar, bajas en
 * trámite y notas de crédito sin confirmar (ver M_Sunat::barrerPendientes()).
 *
 * Pensado para cron, en el hosting que sí lo permita — cada 5 minutos:
 *   5 minutos: php /ruta/al/proyecto/scripts/sunat_worker.php >> /ruta/logs/sunat_worker.log 2>&1
 *
 * Si el hosting no permite cron, el mismo barrido se dispara a mano desde el
 * botón "Enviar pendientes" del panel (C_Sunat.php?action=procesar_pendientes).
 * Ejecutar por CLI únicamente — no expone datos, pero no tiene sentido por web.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script solo se ejecuta por línea de comandos.');
}

require_once dirname(__DIR__) . '/models/M_Sunat.php';

$resumen = M_Sunat::singleton()->barrerPendientes(20);

echo date('Y-m-d H:i:s') . " — SUNAT worker: "
    . "{$resumen['emitidos']} emitidos, "
    . "{$resumen['bajas_consultadas']} bajas consultadas, "
    . "{$resumen['notas_credito']} notas de crédito reintentadas.\n";
