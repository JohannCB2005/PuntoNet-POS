<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/config/sunat.php';
require_once dirname(__DIR__) . '/models/M_Serie.php';
require_once dirname(__DIR__) . '/models/M_SunatWs.php';

use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Client\Client;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Xml\Builder\InvoiceBuilder;
use Greenter\Xml\Builder\NoteBuilder;
use Greenter\Xml\Builder\VoidedBuilder;
use Greenter\Xml\Builder\SummaryBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;

/**
 * Orquesta la emisión de boletas/facturas ante SUNAT: arma el documento con
 * Greenter, lo firma, lo envía y persiste XML + CDR. Deliberadamente detrás de
 * una interfaz sencilla (emitir($id_venta)) para poder enchufar un proveedor
 * externo (APIsPERU, etc.) como plan B sin tocar los llamadores.
 *
 * Boletas se envían con sendBill INDIVIDUAL (no por resumen diario): mismo
 * flujo que factura, CDR inmediato en vez de ticket. La única asimetría real
 * entre ambas está en la ANULACIÓN (ver M_Venta::anular()), no en la emisión.
 */
class M_Sunat {
    private static $instancia = null;
    private $conexion;

    // Catálogo 06 SUNAT (tipo de documento de identidad). Los códigos locales
    // de `personas.tipo_documento` (1=DNI, 2=RUC, 3=Pasaporte) no coinciden con
    // los de SUNAT y hay que traducirlos siempre en este límite del sistema.
    const CATALOGO_06 = [1 => '1', 2 => '6', 3 => '7'];

    // "Tipos de comprobante" técnicos usados solo para reservar el correlativo
    // de las comunicaciones de baja/resumen vía M_Serie — no son documentos de
    // venta reales, no tienen serie elegible por el usuario ni aparecen en
    // series_comprobante hasta la primera vez que se necesitan.
    const TIPO_SERIE_RA = 6; // Comunicación de Baja (facturas)
    const TIPO_SERIE_RC = 7; // Resumen Diario de baja (boletas)

    private function __construct() {
        $this->conexion = Conexion::singleton()->getConexion();
    }

    public static function singleton() {
        if (!isset(self::$instancia)) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Emite una venta ante SUNAT: reserva serie (si aún no la tiene), construye
     * y firma el XML, lo envía y persiste el resultado. Nunca lanza excepción
     * hacia el llamador — un fallo de red dentro de un barrido no debe
     * interrumpir el resto del lote, así que todo error queda en el propio
     * estado_sunat de la venta para reintentar después.
     *
     * @return array ['ok'=>bool, 'estado_sunat'=>int, 'mensaje'=>string]
     */
    public function emitir(int $id_venta): array {
        try {
            $venta = $this->obtenerVentaParaEmitir($id_venta);
            if (!$venta) {
                return ['ok' => false, 'estado_sunat' => 0, 'mensaje' => 'Venta no encontrada o no es un comprobante SUNAT.'];
            }

            // 1. Reservar serie/correlativo la primera vez (no hay red de por medio,
            //    así que esto siempre se completa aunque SUNAT esté caído).
            if (empty($venta['serie'])) {
                $tipoComprobanteSunat = (int) $venta['tipo_comprobante']; // 1=Boleta, 2=Factura
                $this->conexion->beginTransaction();
                try {
                    $reserva = M_Serie::singleton()->reservarSiguiente($this->conexion, $tipoComprobanteSunat);
                    $this->conexion->prepare(
                        "UPDATE ventas SET serie = ?, correlativo = ?, estado_sunat = 1 WHERE id_venta = ?"
                    )->execute([$reserva['serie'], $reserva['correlativo'], $id_venta]);
                    $this->conexion->commit();
                } catch (Exception $e) {
                    $this->conexion->rollBack();
                    return ['ok' => false, 'estado_sunat' => 0, 'mensaje' => 'No se pudo asignar serie: ' . $e->getMessage()];
                }
                $venta['serie'] = $reserva['serie'];
                $venta['correlativo'] = $reserva['correlativo'];
            }

            // 2. Construir, firmar y enviar. Si algo de esto falla (red, SUNAT
            //    caído), la venta queda en estado_sunat=1 (pendiente) — ya tiene
            //    serie asignada, el barrido la reintentará sin pedir una nueva.
            $tipoDocSunat = (int) $venta['tipo_comprobante'] === 2 ? '01' : '03'; // 01=Factura, 03=Boleta
            $invoice = $this->construirInvoice($venta, $tipoDocSunat);

            $builder = new InvoiceBuilder();
            $xml = $builder->build($invoice);

            $signer = new SignedXml();
            $signer->setCertificateFromFile(SUNAT_CERT_PATH);
            $signedXml = $signer->signXml($xml);
            $hash = $this->extraerHashFirma($signedXml);

            $fileName = SUNAT_RUC . "-{$tipoDocSunat}-{$venta['serie']}-{$venta['correlativo']}";
            $zipBase64 = $this->zipearXml($fileName, $signedXml);

            $ws = M_SunatWs::singleton();
            $respuesta = $ws->sendBill($fileName, $zipBase64);

            $this->conexion->prepare("UPDATE ventas SET sunat_intentos = sunat_intentos + 1, sunat_fecha_envio = NOW() WHERE id_venta = ?")
                ->execute([$id_venta]);

            if (!$respuesta['ok']) {
                // Fallo de transporte/credenciales: se queda pendiente para reintentar,
                // no se marca como rechazado (rechazado es solo cuando SUNAT sí
                // procesó el documento y lo objetó).
                $this->guardarComprobante('venta', $id_venta, $signedXml, null);
                $this->conexion->prepare("UPDATE ventas SET sunat_mensaje = ? WHERE id_venta = ?")
                    ->execute([substr((string) $respuesta['mensaje'], 0, 255), $id_venta]);
                return ['ok' => false, 'estado_sunat' => 1, 'mensaje' => $respuesta['mensaje']];
            }

            $cdrInfo = $this->parsearCdr($respuesta['cdr_base64']);
            $this->guardarComprobante('venta', $id_venta, $signedXml, $respuesta['cdr_base64']);

            // Código 0 = aceptado (puede traer observaciones 4000+ en el mismo
            // código 0); cualquier otro código es rechazo.
            $estadoFinal = $cdrInfo['codigo'] === '0' ? 2 : 3;
            $this->conexion->prepare(
                "UPDATE ventas SET estado_sunat = ?, sunat_codigo = ?, sunat_mensaje = ?, sunat_hash = ? WHERE id_venta = ?"
            )->execute([$estadoFinal, $cdrInfo['codigo'], substr($cdrInfo['mensaje'], 0, 255), $hash, $id_venta]);

            return ['ok' => $estadoFinal === 2, 'estado_sunat' => $estadoFinal, 'mensaje' => $cdrInfo['mensaje']];
        } catch (Exception $e) {
            // Cualquier error inesperado (certificado inválido, XML mal formado,
            // etc.) deja la venta pendiente en vez de propagar la excepción.
            return ['ok' => false, 'estado_sunat' => 1, 'mensaje' => 'Error al emitir: ' . $e->getMessage()];
        }
    }

    /**
     * Encola la baja ante SUNAT de un comprobante YA ACEPTADO (estado_sunat=2).
     * Asimétrico por diseño: factura usa Comunicación de Baja (RA), boleta usa
     * Resumen Diario de baja con SummaryDetail.estado='3' (RC) — VoidedDocuments
     * excluye boletas explícitamente. Ambas vías son asíncronas: esto solo
     * ENVÍA y guarda el ticket; consultarBaja() resuelve el resultado después.
     *
     * La anulación LOCAL (stock, ventas.estado=0) ya ocurrió en
     * M_Venta::anular() antes de llamar aquí — esto es exclusivamente el aviso
     * a SUNAT, exigido dentro de los 7 días siguientes a la emisión.
     */
    public function darDeBaja(int $id_venta, string $motivo): array {
        try {
            $venta = $this->obtenerVentaParaBaja($id_venta);
            if (!$venta) {
                return ['ok' => false, 'mensaje' => 'Venta no encontrada o no está aceptada por SUNAT.'];
            }

            $esFactura = (int) $venta['tipo_comprobante'] === 2;
            $tipoDocSunat = $esFactura ? '01' : '03';

            $address = (new Address())->setUbigueo(SUNAT_UBIGEO)->setDepartamento(SUNAT_DEPARTAMENTO)
                ->setProvincia(SUNAT_PROVINCIA)->setDistrito(SUNAT_DISTRITO)->setDireccion(SUNAT_DIRECCION);
            $company = (new Company())->setRuc(SUNAT_RUC)->setRazonSocial(SUNAT_RAZON_SOCIAL)
                ->setNombreComercial(SUNAT_NOMBRE_COMERCIAL ?: SUNAT_RAZON_SOCIAL)->setAddress($address);

            // Correlativo propio de la comunicación (RA-YYYYMMDD-N / RC-YYYYMMDD-N),
            // reservado con el mismo M_Serie que las series de comprobantes reales
            // — no es un comprobante de venta, pero la reserva atómica es la misma
            // necesidad, así que se reutiliza el mecanismo en vez de duplicarlo.
            $tipoInterno = $esFactura ? self::TIPO_SERIE_RA : self::TIPO_SERIE_RC;
            $this->asegurarSerieInterna($tipoInterno);
            $this->conexion->beginTransaction();
            $reserva = M_Serie::singleton()->reservarSiguiente($this->conexion, $tipoInterno);
            $this->conexion->commit();

            $signer = new SignedXml();
            $signer->setCertificateFromFile(SUNAT_CERT_PATH);

            if ($esFactura) {
                $detalle = (new VoidedDetail())
                    ->setTipoDoc($tipoDocSunat)
                    ->setSerie($venta['serie'])
                    ->setCorrelativo((string) $venta['correlativo'])
                    ->setDesMotivoBaja($motivo);
                $documento = (new Voided())
                    ->setCorrelativo((string) $reserva['correlativo'])
                    ->setFecGeneracion(new DateTime())
                    ->setFecComunicacion(new DateTime())
                    ->setCompany($company)
                    ->setDetails([$detalle]);
                $xml = (new VoidedBuilder())->build($documento);
            } else {
                $tipoDocCliente = empty($venta['numero_documento']) ? '0' : (self::CATALOGO_06[(int) $venta['tipo_documento']] ?? '1');
                $numDocCliente = $venta['numero_documento'] ?: '00000000';
                $subtotalVenta = round((float) $venta['total'] - (float) $venta['igv'], 2);
                $detalle = (new SummaryDetail())
                    ->setTipoDoc($tipoDocSunat)
                    ->setSerieNro($venta['serie'] . '-' . $venta['correlativo'])
                    ->setClienteTipo($tipoDocCliente)
                    ->setClienteNro($numDocCliente)
                    ->setEstado('3') // 3 = anulación (dar de baja) dentro del resumen diario
                    ->setTotal((float) $venta['total'])
                    ->setMtoOperGravadas($subtotalVenta)
                    ->setPorcentajeIgv(18)
                    ->setMtoIGV((float) $venta['igv']);
                $documento = (new Summary())
                    ->setCorrelativo((string) $reserva['correlativo'])
                    ->setFecGeneracion(new DateTime())
                    ->setFecResumen(new DateTime())
                    ->setMoneda('PEN')
                    ->setCompany($company)
                    ->setDetails([$detalle]);
                $xml = (new SummaryBuilder())->build($documento);
            }

            $signedXml = $signer->signXml($xml);
            $fileName = $documento->getName();
            $zipBase64 = $this->zipearXml($fileName, $signedXml);

            $ws = M_SunatWs::singleton();
            $respuesta = $ws->sendSummary($fileName, $zipBase64);

            if (!$respuesta['ok']) {
                $this->guardarComprobante('baja', $id_venta, $signedXml, null);
                return ['ok' => false, 'mensaje' => $respuesta['mensaje']];
            }

            $this->guardarComprobante('baja', $id_venta, $signedXml, null);
            $this->conexion->prepare(
                "UPDATE ventas SET estado_sunat = 4, sunat_ticket = ?, sunat_mensaje = 'Baja en trámite ante SUNAT.' WHERE id_venta = ?"
            )->execute([$respuesta['ticket'], $id_venta]);

            return ['ok' => true, 'mensaje' => 'Baja encolada ante SUNAT (ticket ' . $respuesta['ticket'] . ').'];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => 'Error al dar de baja: ' . $e->getMessage()];
        }
    }

    /**
     * Consulta el resultado de una baja en trámite (estado_sunat=4). Código 0
     * del CDR = SUNAT aceptó la baja → estado_sunat=5 (dado de baja). Cualquier
     * rechazo vuelve la venta a estado_sunat=2 con el motivo, para que se
     * pueda reintentar la baja desde Historial — la anulación LOCAL ya está
     * hecha de todos modos, esto solo afecta el estado de cara a SUNAT.
     */
    public function consultarBaja(int $id_venta): array {
        try {
            $stmt = $this->conexion->prepare("SELECT sunat_ticket FROM ventas WHERE id_venta = ? AND estado_sunat = 4");
            $stmt->execute([$id_venta]);
            $ticket = $stmt->fetchColumn();
            if (!$ticket) {
                return ['ok' => false, 'mensaje' => 'Esta venta no tiene una baja en trámite.'];
            }

            $ws = M_SunatWs::singleton();
            $respuesta = $ws->getStatus($ticket);
            if (!$respuesta['ok']) {
                return ['ok' => false, 'mensaje' => $respuesta['mensaje']];
            }

            if ($respuesta['status_code'] === '98') {
                return ['ok' => true, 'mensaje' => 'SUNAT todavía está procesando la baja. Vuelve a consultar en unos minutos.'];
            }

            if (empty($respuesta['cdr_base64'])) {
                return ['ok' => false, 'mensaje' => 'SUNAT no devolvió un CDR (código ' . $respuesta['status_code'] . ').'];
            }

            $cdrInfo = $this->parsearCdr($respuesta['cdr_base64']);
            $this->guardarComprobante('baja', $id_venta, '', $respuesta['cdr_base64']);

            if ($cdrInfo['codigo'] === '0') {
                $this->conexion->prepare(
                    "UPDATE ventas SET estado_sunat = 5, sunat_codigo = ?, sunat_mensaje = ? WHERE id_venta = ?"
                )->execute([$cdrInfo['codigo'], substr($cdrInfo['mensaje'], 0, 255), $id_venta]);
                return ['ok' => true, 'mensaje' => 'SUNAT confirmó la baja: ' . $cdrInfo['mensaje']];
            }

            $this->conexion->prepare(
                "UPDATE ventas SET estado_sunat = 2, sunat_codigo = ?, sunat_mensaje = ?, sunat_ticket = NULL WHERE id_venta = ?"
            )->execute([$cdrInfo['codigo'], substr($cdrInfo['mensaje'], 0, 255), $id_venta]);
            return ['ok' => false, 'mensaje' => 'SUNAT rechazó la baja: ' . $cdrInfo['mensaje']];
        } catch (Exception $e) {
            return ['ok' => false, 'mensaje' => 'Error al consultar la baja: ' . $e->getMessage()];
        }
    }

    /**
     * Emite una Nota de Crédito total (motivo 01, sin soporte de NC parcial)
     * para un comprobante que ya no se puede anular por la vía normal (>7 días
     * desde la emisión, o cualquier corrección tras aceptación). Es la única
     * salida legal en ese caso.
     *
     * Efecto en stock/caja, mismo patrón que M_CambioTalla::registrarCambio():
     * la venta original NUNCA se edita; el stock se revierte hoy con un
     * movimiento explícito en kardex_movimientos (referencia = código de la
     * NC), y el dinero devuelto se registra como una venta NUEVA (origen=5)
     * ligada a la caja abierta de HOY, con líneas de pagos_venta en NEGATIVO
     * (mismo signo que usa cambio de talla para una devolución) — así una caja
     * ya cerrada nunca cambia hacia atrás y el arqueo de hoy sí refleja la
     * salida real de dinero.
     *
     * @param array $pagos [{metodo_pago, monto, referencia}, ...] — cómo se
     *   devuelve el dinero (debe sumar exactamente el total del comprobante).
     */
    public function emitirNotaCredito(int $idVentaOriginal, string $motivo, int $idUsuario, int $idCaja, array $pagos): array {
        try {
            $stmt = $this->conexion->prepare(
                "SELECT v.id_venta, v.tipo_comprobante, v.serie, v.correlativo, v.estado, v.id_cliente,
                        v.total, v.subtotal, v.igv,
                        p.tipo_documento, p.numero_documento, p.nombres_razon_social, p.apellidos
                 FROM ventas v
                 LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
                 LEFT JOIN personas p ON c.id_persona = p.id_persona
                 WHERE v.id_venta = ? AND v.tipo_comprobante IN (1, 2) AND v.serie IS NOT NULL"
            );
            $stmt->execute([$idVentaOriginal]);
            $venta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$venta) {
                return ['ok' => false, 'mensaje' => 'Venta no encontrada o nunca fue emitida ante SUNAT.'];
            }

            $sumaPagos = round(array_sum(array_column($pagos, 'monto')), 2);
            if (abs($sumaPagos - (float) $venta['total']) > 0.01) {
                return ['ok' => false, 'mensaje' => "El monto a devolver (S/ " . number_format($sumaPagos, 2) . ") no coincide con el total del comprobante (S/ " . number_format((float) $venta['total'], 2) . ")."];
            }

            require_once dirname(__DIR__) . '/models/M_Kardex.php';

            $stmtDetalles = $this->conexion->prepare(
                "SELECT dv.id_producto, dv.cantidad, dv.precio_venta, dv.valor_unitario, dv.igv_linea, dv.tipo_afectacion_igv, dv.subtotal,
                        i.nombre AS producto_nombre, um.codigo_sunat, i.stock_ilimitado
                 FROM detalle_ventas dv
                 INNER JOIN productos i ON dv.id_producto = i.id_producto
                 INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                 WHERE dv.id_venta = ?"
            );
            $stmtDetalles->execute([$idVentaOriginal]);
            $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
            if (empty($detalles)) {
                return ['ok' => false, 'mensaje' => 'La venta original no tiene mercadería que revertir.'];
            }

            $this->conexion->beginTransaction();

            // 1. Reservar serie de la NC (4=NC de Boleta, 5=NC de Factura). A
            //    diferencia de RA/RC (internas, invisibles al usuario), la serie
            //    de una NC sí es un comprobante real — debe existir configurada
            //    de antemano (módulo de Series), reservarSiguiente() avisa si falta.
            $tipoComprobanteNC = (int) $venta['tipo_comprobante'] === 2 ? 5 : 4;
            $reserva = M_Serie::singleton()->reservarSiguiente($this->conexion, $tipoComprobanteNC);
            $codigoNC = $reserva['serie'] . '-' . str_pad((string) $reserva['correlativo'], 6, '0', STR_PAD_LEFT);

            // 2. Revertir stock + kardex explícito (solo si la venta seguía activa;
            //    si ya se había anulado localmente antes de vencer los 7 días, el
            //    stock ya volvió entonces y no hay que devolverlo dos veces).
            if ((int) $venta['estado'] === 1) {
                $kardex = M_Kardex::singleton();
                foreach ($detalles as $d) {
                    if ((int) ($d['stock_ilimitado'] ?? 0) === 1) {
                        continue;
                    }
                    $this->conexion->prepare("UPDATE productos SET stock_piezas = stock_piezas + ? WHERE id_producto = ?")
                        ->execute([$d['cantidad'], $d['id_producto']]);
                    $kardex->registrarMovimiento($d['id_producto'], 'entrada', $d['cantidad'], $d['precio_venta'], $codigoNC, 'Nota de crédito — devolución', $idUsuario);
                }
                $this->conexion->prepare("UPDATE ventas SET estado = 0 WHERE id_venta = ?")->execute([$idVentaOriginal]);
            }

            // 3. Venta-efecto: el dinero que sale de caja HOY. tipo_comprobante=3
            //    (Nota de Venta) porque el documento fiscal real es la propia NC,
            //    registrada aparte en `notas_credito` — esta fila es solo el
            //    movimiento de caja. Sin detalle_ventas: así M_Kardex no la ve (ya
            //    quedó el movimiento explícito arriba), mismo patrón que un abono
            //    de separación.
            $totalNC = -round((float) $venta['total'], 2);
            $subtotalNC = -round((float) $venta['subtotal'], 2);
            $igvNC = -round((float) $venta['igv'], 2);
            $this->conexion->prepare(
                "INSERT INTO ventas (id_usuario, id_caja, id_cliente, tipo_comprobante, total, subtotal, igv, metodo_pago, estado, origen)
                 VALUES (?, ?, ?, 3, ?, ?, ?, ?, 1, 5)"
            )->execute([$idUsuario, $idCaja, $venta['id_cliente'], $totalNC, $subtotalNC, $igvNC, (int) $pagos[0]['metodo_pago']]);
            $idVentaNC = (int) $this->conexion->lastInsertId();

            foreach ($pagos as $p) {
                $this->conexion->prepare("INSERT INTO pagos_venta (id_venta, metodo_pago, monto, referencia) VALUES (?, ?, ?, ?)")
                    ->execute([$idVentaNC, (int) $p['metodo_pago'], -abs((float) $p['monto']), $p['referencia'] ?? null]);
            }

            // 4. Cabecera de la Nota de Crédito (el documento SUNAT en sí).
            $this->conexion->prepare(
                "INSERT INTO notas_credito (id_venta_original, id_venta_nc, serie, correlativo, tipo_motivo, total, estado_sunat, id_usuario)
                 VALUES (?, ?, ?, ?, '01', ?, 1, ?)"
            )->execute([$idVentaOriginal, $idVentaNC, $reserva['serie'], $reserva['correlativo'], $venta['total'], $idUsuario]);
            $idNotaCredito = (int) $this->conexion->lastInsertId();

            $this->conexion->commit();

            // 5. Construir, firmar y enviar. Igual que emitir(): un fallo de red
            //    aquí no revierte lo anterior — la NC queda registrada como
            //    pendiente (estado_sunat=1) y se reintenta desde el barrido.
            $tipoDocSunat = '07'; // 07 = Nota de Crédito (mismo código de documento para ambos casos)
            $note = $this->construirNotaCredito($venta, $detalles, $reserva, $motivo);

            $builder = new NoteBuilder();
            $xml = $builder->build($note);

            $signer = new SignedXml();
            $signer->setCertificateFromFile(SUNAT_CERT_PATH);
            $signedXml = $signer->signXml($xml);
            $hash = $this->extraerHashFirma($signedXml);

            $fileName = SUNAT_RUC . "-{$tipoDocSunat}-{$reserva['serie']}-{$reserva['correlativo']}";
            $zipBase64 = $this->zipearXml($fileName, $signedXml);

            $ws = M_SunatWs::singleton();
            $respuesta = $ws->sendBill($fileName, $zipBase64);

            if (!$respuesta['ok']) {
                $this->guardarComprobante('nota_credito', $idNotaCredito, $signedXml, null);
                $this->conexion->prepare("UPDATE notas_credito SET sunat_mensaje = ? WHERE id_nota_credito = ?")
                    ->execute([substr((string) $respuesta['mensaje'], 0, 255), $idNotaCredito]);
                return ['ok' => true, 'mensaje' => 'Nota de Crédito ' . $codigoNC . ' registrada; no se pudo notificar a SUNAT todavía (' . $respuesta['mensaje'] . '), se reintentará.', 'codigo' => $codigoNC];
            }

            $cdrInfo = $this->parsearCdr($respuesta['cdr_base64']);
            $this->guardarComprobante('nota_credito', $idNotaCredito, $signedXml, $respuesta['cdr_base64']);
            $estadoFinal = $cdrInfo['codigo'] === '0' ? 2 : 3;
            $this->conexion->prepare(
                "UPDATE notas_credito SET estado_sunat = ?, sunat_codigo = ?, sunat_mensaje = ?, sunat_hash = ? WHERE id_nota_credito = ?"
            )->execute([$estadoFinal, $cdrInfo['codigo'], substr($cdrInfo['mensaje'], 0, 255), $hash, $idNotaCredito]);

            return ['ok' => $estadoFinal === 2, 'mensaje' => $cdrInfo['mensaje'], 'codigo' => $codigoNC];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            return ['ok' => false, 'mensaje' => 'Error al emitir la Nota de Crédito: ' . $e->getMessage()];
        }
    }

    private function construirNotaCredito(array $venta, array $detalles, array $reserva, string $motivo): Note {
        $address = (new Address())->setUbigueo(SUNAT_UBIGEO)->setDepartamento(SUNAT_DEPARTAMENTO)
            ->setProvincia(SUNAT_PROVINCIA)->setDistrito(SUNAT_DISTRITO)->setDireccion(SUNAT_DIRECCION);
        $company = (new Company())->setRuc(SUNAT_RUC)->setRazonSocial(SUNAT_RAZON_SOCIAL)
            ->setNombreComercial(SUNAT_NOMBRE_COMERCIAL ?: SUNAT_RAZON_SOCIAL)->setAddress($address);

        if (empty($venta['numero_documento'])) {
            $client = (new Client())->setTipoDoc('0')->setNumDoc('00000000')->setRznSocial('CLIENTES VARIOS');
        } else {
            $nombreCompleto = trim(($venta['apellidos'] ?? '') . ' ' . $venta['nombres_razon_social']);
            $client = (new Client())
                ->setTipoDoc(self::CATALOGO_06[(int) $venta['tipo_documento']] ?? '1')
                ->setNumDoc($venta['numero_documento'])
                ->setRznSocial($nombreCompleto ?: $venta['nombres_razon_social']);
        }

        $items = [];
        foreach ($detalles as $d) {
            $items[] = (new SaleDetail())
                ->setCodProducto((string) $d['id_producto'])
                ->setUnidad($d['codigo_sunat'])
                ->setDescripcion($d['producto_nombre'])
                ->setCantidad((float) $d['cantidad'])
                ->setMtoValorUnitario((float) $d['valor_unitario'])
                ->setMtoValorVenta(round((float) $d['subtotal'] - (float) $d['igv_linea'], 2))
                ->setMtoBaseIgv(round((float) $d['subtotal'] - (float) $d['igv_linea'], 2))
                ->setPorcentajeIgv(18)
                ->setIgv((float) $d['igv_linea'])
                ->setTipAfeIgv($d['tipo_afectacion_igv'])
                ->setTotalImpuestos((float) $d['igv_linea'])
                ->setMtoPrecioUnitario((float) $d['precio_venta']);
        }

        $tipoDocAfectado = (int) $venta['tipo_comprobante'] === 2 ? '01' : '03';

        return (new Note())
            ->setUblVersion('2.1')
            ->setTipoDoc('07')
            ->setSerie($reserva['serie'])
            ->setCorrelativo((string) $reserva['correlativo'])
            ->setFechaEmision(new DateTime())
            ->setTipoMoneda('PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setCodMotivo('01') // Catálogo 09: 01 = Anulación de la operación
            ->setDesMotivo($motivo ?: 'Anulación de la operación')
            ->setTipDocAfectado($tipoDocAfectado)
            ->setNumDocfectado($venta['serie'] . '-' . $venta['correlativo'])
            ->setMtoOperGravadas((float) $venta['subtotal'])
            ->setMtoIGV((float) $venta['igv'])
            ->setTotalImpuestos((float) $venta['igv'])
            ->setValorVenta((float) $venta['subtotal'])
            ->setSubTotal((float) $venta['total'])
            ->setMtoImpVenta((float) $venta['total'])
            ->setDetails($items)
            ->setLegends([
                (new Legend())->setCode('1000')->setValue($this->montoEnLetras((float) $venta['total'])),
            ]);
    }

    private function obtenerVentaParaBaja(int $id_venta): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT v.id_venta, v.tipo_comprobante, v.serie, v.correlativo, v.total, v.igv,
                    p.tipo_documento, p.numero_documento
             FROM ventas v
             LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
             LEFT JOIN personas p ON c.id_persona = p.id_persona
             WHERE v.id_venta = ? AND v.estado_sunat = 2 AND v.serie IS NOT NULL"
        );
        $stmt->execute([$id_venta]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
        return $venta ?: null;
    }

    /**
     * Las comunicaciones de baja/resumen no son un "tipo de comprobante" real
     * (no tienen serie propia elegida por el usuario, solo un correlativo
     * técnico), pero reutilizan M_Serie::reservarSiguiente() para la reserva
     * atómica. Se autocrean la primera vez que hacen falta.
     */
    private function asegurarSerieInterna(int $tipoInterno): void {
        $serieInterna = $tipoInterno === self::TIPO_SERIE_RA ? 'RA' : 'RC';
        $stmt = $this->conexion->prepare("SELECT 1 FROM series_comprobante WHERE tipo_comprobante = ? AND serie = ?");
        $stmt->execute([$tipoInterno, $serieInterna]);
        if (!$stmt->fetchColumn()) {
            $this->conexion->prepare(
                "INSERT INTO series_comprobante (tipo_comprobante, serie, correlativo_actual, estado) VALUES (?, ?, 0, 1)"
            )->execute([$tipoInterno, $serieInterna]);
        }
    }

    /**
     * Carga la venta con todo lo necesario para construir el XML: cabecera,
     * cliente/persona y líneas de detalle con su código SUNAT de unidad.
     * Devuelve null si la venta no es un comprobante fiscal (Nota de Venta,
     * o una venta de abono/anticipo de separación sin detalle propio).
     */
    private function obtenerVentaParaEmitir(int $id_venta): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT v.id_venta, v.tipo_comprobante, v.fecha, v.total, v.subtotal, v.igv,
                    v.serie, v.correlativo, v.estado_sunat,
                    p.tipo_documento, p.numero_documento, p.nombres_razon_social, p.apellidos,
                    p.direccion, p.ubigeo
             FROM ventas v
             LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
             LEFT JOIN personas p ON c.id_persona = p.id_persona
             WHERE v.id_venta = ? AND v.tipo_comprobante IN (1, 2) AND v.estado = 1"
        );
        $stmt->execute([$id_venta]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$venta) {
            return null;
        }

        $stmtDetalle = $this->conexion->prepare(
            "SELECT dv.cantidad, dv.precio_venta, dv.valor_unitario, dv.igv_linea, dv.tipo_afectacion_igv, dv.subtotal,
                    i.nombre AS producto_nombre, i.id_producto, um.codigo_sunat
             FROM detalle_ventas dv
             INNER JOIN productos i ON dv.id_producto = i.id_producto
             INNER JOIN unidades_medida um ON i.id_unidad = um.id_unidad
             WHERE dv.id_venta = ?"
        );
        $stmtDetalle->execute([$id_venta]);
        $venta['detalle'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

        if (empty($venta['detalle'])) {
            // Anticipo/abono de separación (Nota de Venta, nunca llega aquí porque
            // tipo_comprobante no es 1/2), o un caso inesperado sin mercadería.
            return null;
        }

        return $venta;
    }

    private function construirInvoice(array $venta, string $tipoDocSunat): Invoice {
        $address = (new Address())
            ->setUbigueo(SUNAT_UBIGEO)
            ->setDepartamento(SUNAT_DEPARTAMENTO)
            ->setProvincia(SUNAT_PROVINCIA)
            ->setDistrito(SUNAT_DISTRITO)
            ->setDireccion(SUNAT_DIRECCION);

        $company = (new Company())
            ->setRuc(SUNAT_RUC)
            ->setRazonSocial(SUNAT_RAZON_SOCIAL)
            ->setNombreComercial(SUNAT_NOMBRE_COMERCIAL ?: SUNAT_RAZON_SOCIAL)
            ->setAddress($address);

        // Cliente sin documento (Público General, solo posible bajo el umbral de
        // boleta): SUNAT admite tipoDoc '0' + numDoc '00000000' para este caso.
        if (empty($venta['numero_documento'])) {
            $client = (new Client())->setTipoDoc('0')->setNumDoc('00000000')->setRznSocial('CLIENTES VARIOS');
        } else {
            $nombreCompleto = trim(($venta['apellidos'] ?? '') . ' ' . $venta['nombres_razon_social']);
            $client = (new Client())
                ->setTipoDoc(self::CATALOGO_06[(int) $venta['tipo_documento']] ?? '1')
                ->setNumDoc($venta['numero_documento'])
                ->setRznSocial($nombreCompleto ?: $venta['nombres_razon_social']);
        }

        $items = [];
        foreach ($venta['detalle'] as $d) {
            $items[] = (new SaleDetail())
                ->setCodProducto((string) $d['id_producto'])
                ->setUnidad($d['codigo_sunat'])
                ->setDescripcion($d['producto_nombre'])
                ->setCantidad((float) $d['cantidad'])
                ->setMtoValorUnitario((float) $d['valor_unitario'])
                ->setMtoValorVenta(round((float) $d['subtotal'] - (float) $d['igv_linea'], 2))
                ->setMtoBaseIgv(round((float) $d['subtotal'] - (float) $d['igv_linea'], 2))
                ->setPorcentajeIgv(18)
                ->setIgv((float) $d['igv_linea'])
                ->setTipAfeIgv($d['tipo_afectacion_igv'])
                ->setTotalImpuestos((float) $d['igv_linea'])
                ->setMtoPrecioUnitario((float) $d['precio_venta']);
        }

        return (new Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101') // Catálogo 51: Venta interna
            ->setTipoDoc($tipoDocSunat)
            ->setSerie($venta['serie'])
            ->setCorrelativo((string) $venta['correlativo'])
            ->setFechaEmision(new DateTime($venta['fecha']))
            ->setFormaPago(new FormaPagoContado())
            ->setTipoMoneda('PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas((float) $venta['subtotal'])
            ->setMtoIGV((float) $venta['igv'])
            ->setTotalImpuestos((float) $venta['igv'])
            ->setValorVenta((float) $venta['subtotal'])
            ->setSubTotal((float) $venta['total'])
            ->setMtoImpVenta((float) $venta['total'])
            ->setDetails($items)
            ->setLegends([
                (new Legend())->setCode('1000')->setValue($this->montoEnLetras((float) $venta['total'])),
            ]);
    }

    /**
     * Conversión número→letras para la leyenda obligatoria del comprobante.
     * Implementación mínima (0–999,999.99), suficiente para el ticket de una
     * tienda de uniformes; no pretende cubrir montos arbitrariamente grandes.
     */
    private function montoEnLetras(float $monto): string {
        $entero = (int) floor($monto);
        $centimos = (int) round(($monto - $entero) * 100);
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $especiales = [10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE',
            16 => 'DIECISEIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE'];
        $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
            'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $convertirCentena = function (int $n) use ($unidades, $especiales, $decenas, $centenas): string {
            if ($n === 0) return '';
            if ($n === 100) return 'CIEN';
            $partes = [];
            if ($n >= 100) { $partes[] = $centenas[intdiv($n, 100)]; $n %= 100; }
            if ($n >= 10 && $n <= 19) {
                $partes[] = $especiales[$n];
            } elseif ($n >= 20) {
                $d = $decenas[intdiv($n, 10)];
                $u = $n % 10;
                $partes[] = $u > 0 ? "{$d} Y {$unidades[$u]}" : $d;
            } elseif ($n > 0) {
                $partes[] = $unidades[$n];
            }
            return implode(' ', $partes);
        };

        if ($entero === 0) {
            $texto = 'CERO';
        } elseif ($entero < 1000) {
            $texto = $convertirCentena($entero);
        } elseif ($entero < 1000000) {
            $miles = intdiv($entero, 1000);
            $resto = $entero % 1000;
            $texto = ($miles === 1 ? 'MIL' : $convertirCentena($miles) . ' MIL') . ($resto > 0 ? ' ' . $convertirCentena($resto) : '');
        } else {
            $texto = number_format($entero, 0, '', ''); // fuera del alcance previsto, degrada a numérico
        }

        return "SON {$texto} CON " . str_pad((string) $centimos, 2, '0', STR_PAD_LEFT) . '/100 SOLES';
    }

    private function extraerHashFirma(string $signedXml): ?string {
        $doc = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $doc->loadXML($signedXml);
        libxml_use_internal_errors($anterior);
        $nodos = $doc->getElementsByTagName('DigestValue');
        return $nodos->length > 0 ? $nodos->item(0)->textContent : null;
    }

    private function zipearXml(string $fileName, string $xmlContent): string {
        $tmpPath = sys_get_temp_dir() . '/' . $fileName . '_' . uniqid() . '.zip';
        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString("{$fileName}.xml", $xmlContent);
        $zip->close();
        $contenido = file_get_contents($tmpPath);
        unlink($tmpPath);
        return base64_encode($contenido);
    }

    /**
     * Extrae ResponseCode/Description del CDR (un .zip que contiene un XML
     * ApplicationResponse UBL). Código '0' = aceptado (con o sin observaciones);
     * cualquier otro = rechazado.
     */
    private function parsearCdr(string $cdrZipBase64): array {
        $tmpPath = sys_get_temp_dir() . '/cdr_' . uniqid() . '.zip';
        file_put_contents($tmpPath, base64_decode($cdrZipBase64));

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            unlink($tmpPath);
            return ['codigo' => '', 'mensaje' => 'No se pudo leer el CDR (zip corrupto).'];
        }

        $xmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if (str_ends_with($nombre, '.xml')) {
                $xmlContent = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();
        unlink($tmpPath);

        if ($xmlContent === null) {
            return ['codigo' => '', 'mensaje' => 'El CDR no contiene un XML.'];
        }

        $doc = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $doc->loadXML($xmlContent);
        libxml_use_internal_errors($anterior);

        $codigoNodo = $doc->getElementsByTagName('ResponseCode');
        $descNodo = $doc->getElementsByTagName('Description');

        return [
            'codigo' => $codigoNodo->length > 0 ? trim($codigoNodo->item(0)->textContent) : '',
            'mensaje' => $descNodo->length > 0 ? trim($descNodo->item(0)->textContent) : 'Sin descripción en el CDR.',
        ];
    }

    private function guardarComprobante(string $tipoDocumento, int $idReferencia, string $xmlFirmado, ?string $cdrBase64): void {
        $this->conexion->prepare(
            "INSERT INTO comprobantes_sunat (tipo_documento, id_referencia, xml_firmado, cdr_zip_base64) VALUES (?, ?, ?, ?)"
        )->execute([$tipoDocumento, $idReferencia, $xmlFirmado, $cdrBase64]);
    }

    /**
     * Barrido periódico: procesa lo que quedó pendiente porque SUNAT estaba
     * caída, la red falló, o simplemente nadie volvió a la pantalla a
     * reintentar a mano.
     *
     * A propósito NO está colgado de un page-load normal como
     * M_Ecommerce::barrerPedidosVencidos() — ese barrido solo revisa fechas en
     * BD (barato); este hace llamadas HTTP reales a SUNAT (lento, y a veces
     * SUNAT tarda varios segundos), así que colgarlo de cargar Historial
     * podría hacer esa pantalla lenta o colgarse si SUNAT está caída. Se
     * dispara por acción explícita del Administrador (botón "Enviar
     * pendientes", C_Sunat.php?action=procesar_pendientes) o por cron
     * (scripts/sunat_worker.php) para quien sí tenga cron en su hosting.
     *
     * Cada ítem se procesa en su propio try/catch (implícito: emitir(),
     * consultarBaja() y reenviarNotaCredito() nunca lanzan excepción hacia
     * afuera) para que un fallo puntual no interrumpa el resto del lote.
     */
    public function barrerPendientes(int $limite = 20): array {
        $limite = max(1, min($limite, 100));
        $resumen = ['emitidos' => 0, 'bajas_consultadas' => 0, 'notas_credito' => 0];

        // 1. Comprobantes que nunca se llegaron a enviar (o el intento anterior falló).
        $stmt = $this->conexion->prepare(
            // origen 3 (cambio de talla) y 4 (separación) quedan fuera: su total es un
            // movimiento de dinero parcial, no el valor del comprobante, así que no
            // deben emitirse por esta vía aunque alguien les deje estado_sunat=1.
            "SELECT id_venta FROM ventas
             WHERE estado_sunat = 1 AND estado = 1 AND tipo_comprobante IN (1, 2)
               AND origen NOT IN (3, 4)
             ORDER BY fecha ASC LIMIT " . intval($limite)
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idVenta) {
            $this->emitir((int) $idVenta);
            $resumen['emitidos']++;
        }

        // 2. Bajas en trámite: consultar si SUNAT ya resolvió el ticket.
        $stmt = $this->conexion->prepare(
            "SELECT id_venta FROM ventas WHERE estado_sunat = 4 ORDER BY sunat_fecha_envio ASC LIMIT " . intval($limite)
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idVenta) {
            $this->consultarBaja((int) $idVenta);
            $resumen['bajas_consultadas']++;
        }

        // 3. Notas de Crédito cuyo envío falló (ya reservaron serie y revirtieron
        //    stock — solo falta reintentar el envío del XML ya firmado, nunca
        //    reconstruirlas: eso repetiría la reserva de correlativo y la
        //    reversión de stock).
        $stmt = $this->conexion->prepare(
            "SELECT id_nota_credito FROM notas_credito WHERE estado_sunat = 1 ORDER BY fecha ASC LIMIT " . intval($limite)
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idNC) {
            $this->reenviarNotaCredito((int) $idNC);
            $resumen['notas_credito']++;
        }

        return $resumen;
    }

    /**
     * Reintenta el envío de una Nota de Crédito ya creada (serie reservada,
     * stock ya revertido) cuyo primer intento de envío falló. Reutiliza el
     * XML firmado guardado en su momento — no se reconstruye ni se vuelve a
     * firmar, para no correr el riesgo de que un cambio de datos entre medias
     * (p.ej. la dirección del emisor) produzca un XML distinto al que
     * corresponde a ese correlativo ya reservado.
     */
    public function reenviarNotaCredito(int $idNotaCredito): array {
        try {
            $stmt = $this->conexion->prepare("SELECT serie, correlativo FROM notas_credito WHERE id_nota_credito = ? AND estado_sunat = 1");
            $stmt->execute([$idNotaCredito]);
            $nc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$nc) {
                return ['ok' => false, 'mensaje' => 'Nota de Crédito no encontrada o ya no está pendiente.'];
            }

            $stmtXml = $this->conexion->prepare(
                "SELECT xml_firmado FROM comprobantes_sunat WHERE tipo_documento = 'nota_credito' AND id_referencia = ? ORDER BY id_comprobante_sunat DESC LIMIT 1"
            );
            $stmtXml->execute([$idNotaCredito]);
            $signedXml = $stmtXml->fetchColumn();
            if (!$signedXml) {
                return ['ok' => false, 'mensaje' => 'No se encontró el XML firmado de esta Nota de Crédito.'];
            }

            $fileName = SUNAT_RUC . "-07-{$nc['serie']}-{$nc['correlativo']}";
            $zipBase64 = $this->zipearXml($fileName, $signedXml);

            $ws = M_SunatWs::singleton();
            $respuesta = $ws->sendBill($fileName, $zipBase64);
            if (!$respuesta['ok']) {
                $this->conexion->prepare("UPDATE notas_credito SET sunat_mensaje = ? WHERE id_nota_credito = ?")
                    ->execute([substr((string) $respuesta['mensaje'], 0, 255), $idNotaCredito]);
                return ['ok' => false, 'mensaje' => $respuesta['mensaje']];
            }

            $cdrInfo = $this->parsearCdr($respuesta['cdr_base64']);
            $this->guardarComprobante('nota_credito', $idNotaCredito, $signedXml, $respuesta['cdr_base64']);
            $estadoFinal = $cdrInfo['codigo'] === '0' ? 2 : 3;
            $this->conexion->prepare(
                "UPDATE notas_credito SET estado_sunat = ?, sunat_codigo = ?, sunat_mensaje = ? WHERE id_nota_credito = ?"
            )->execute([$estadoFinal, $cdrInfo['codigo'], substr($cdrInfo['mensaje'], 0, 255), $idNotaCredito]);

            return ['ok' => $estadoFinal === 2, 'mensaje' => $cdrInfo['mensaje']];
        } catch (Exception $e) {
            return ['ok' => false, 'mensaje' => 'Error al reenviar la Nota de Crédito: ' . $e->getMessage()];
        }
    }
}
?>
