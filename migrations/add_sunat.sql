-- =============================================================================
-- PuntoNet POS — Migración: Emisión y anulación de comprobantes ante SUNAT
-- Fecha: 2026-08
-- Descripción:
--   Permite que el propio sistema emita boletas/facturas ante SUNAT (SEE del
--   Contribuyente) en vez de depender de un tercero (Tukifac), y las anule
--   correctamente. La anulación NO es simétrica entre factura y boleta:
--     - Factura: Comunicación de Baja (RA), documento tipo 01/07/08.
--     - Boleta:  Resumen Diario de baja (RC, estado 3) — VoidedDocuments
--       excluye explícitamente las boletas.
--   Ambas vías son asíncronas (sendSummary -> ticket -> getStatus).
--
--   El envío a SUNAT es asíncrono respecto al registro de la venta: la venta
--   se cobra e imprime al instante (estado_sunat=1 "pendiente") y un barrido
--   posterior la envía dentro del plazo legal (factura 3 días, boleta 5 días).
--   Así SUNAT caído nunca bloquea una venta de mostrador.
--
--   El IGV deja de calcularse al vuelo (total/1.18) porque SUNAT valida al
--   céntimo: se persiste subtotal/igv por línea y por venta en el momento del
--   registro (M_Venta::registrarEnTransaccion()).
--
--   Solo necesaria si tu base de datos ya existía sin estas tablas/columnas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

-- 1. Series y correlativos, independientes de los que ya usa Tukifac (B001/F001).
--    El siguiente correlativo se reserva con SELECT ... FOR UPDATE (M_Serie.php)
--    dentro de la misma transacción que registra la venta.
CREATE TABLE `series_comprobante` (
  `id_serie` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_comprobante` tinyint(1) NOT NULL COMMENT '1=Boleta, 2=Factura, 4=Nota de Crédito ligada a Boleta, 5=Nota de Crédito ligada a Factura',
  `serie` varchar(4) NOT NULL COMMENT 'B002 / F002 / BC02 / FC02 — nunca las que ya usa Tukifac',
  `correlativo_actual` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Activa, 0=Cerrada',
  PRIMARY KEY (`id_serie`),
  UNIQUE KEY `uk_tipo_serie` (`tipo_comprobante`, `serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Campos fiscales y de estado SUNAT en la venta.
ALTER TABLE `ventas`
  ADD COLUMN `serie` varchar(4) DEFAULT NULL COMMENT 'NULL = Nota de Venta, nunca se envía a SUNAT',
  ADD COLUMN `correlativo` int(11) DEFAULT NULL,
  ADD COLUMN `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de venta sin IGV, persistido al registrar',
  ADD COLUMN `igv` decimal(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `estado_sunat` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '0=No aplica (nota de venta), 1=Pendiente de envío, 2=Aceptado, 3=Rechazado, 4=Baja en trámite, 5=Dado de baja',
  ADD COLUMN `sunat_codigo` varchar(10) DEFAULT NULL COMMENT 'Código del CDR (0=aceptado, 2000-3999=rechazado)',
  ADD COLUMN `sunat_mensaje` varchar(255) DEFAULT NULL,
  ADD COLUMN `sunat_ticket` varchar(40) DEFAULT NULL COMMENT 'Ticket de sendSummary (baja), se consulta con getStatus',
  ADD COLUMN `sunat_hash` varchar(100) DEFAULT NULL COMMENT 'digestValue del XML firmado, va en el QR del ticket impreso',
  ADD COLUMN `sunat_intentos` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN `sunat_fecha_envio` datetime DEFAULT NULL,
  ADD UNIQUE KEY `uk_serie_correlativo` (`serie`, `correlativo`);

-- 3. Desglose fiscal por línea (catálogo 07 SUNAT: tipo de afectación IGV).
ALTER TABLE `detalle_ventas`
  ADD COLUMN `valor_unitario` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'precio_venta sin IGV',
  ADD COLUMN `igv_linea` decimal(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `tipo_afectacion_igv` varchar(2) NOT NULL DEFAULT '10' COMMENT 'Catálogo 07 SUNAT: 10=Gravado-Operación Onerosa';

-- 4. Código de unidad de medida SUNAT (catálogo 03). Und->NIU, Doc->DZN.
ALTER TABLE `unidades_medida`
  ADD COLUMN `codigo_sunat` varchar(5) NOT NULL DEFAULT 'NIU';
UPDATE `unidades_medida` SET `codigo_sunat` = 'DZN' WHERE `abreviatura` = 'Doc';

-- 5. Ubigeo del cliente, exigido en el XML de la factura.
ALTER TABLE `personas`
  ADD COLUMN `ubigeo` varchar(6) DEFAULT NULL COMMENT 'Código INEI departamento+provincia+distrito';

-- 6. Notas de Crédito: única salida legal cuando ya no se puede anular (>7 días)
--    o cuando el comprobante fue rechazado y hay que corregirlo tras haber sido
--    aceptado. Motivo 01 (anulación de la operación) — sin soporte de NC parcial.
CREATE TABLE `notas_credito` (
  `id_nota_credito` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta_original` int(11) NOT NULL COMMENT 'Comprobante que se corrige',
  `id_venta_nc` int(11) DEFAULT NULL COMMENT 'Venta origen=5 que registra el efecto en stock/caja, ligada a la caja de HOY',
  `serie` varchar(4) NOT NULL,
  `correlativo` int(11) NOT NULL,
  `tipo_motivo` varchar(2) NOT NULL DEFAULT '01' COMMENT 'Catálogo 09 SUNAT: 01=Anulación de la operación',
  `total` decimal(10,2) NOT NULL,
  `estado_sunat` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Pendiente, 2=Aceptado, 3=Rechazado',
  `sunat_codigo` varchar(10) DEFAULT NULL,
  `sunat_mensaje` varchar(255) DEFAULT NULL,
  `sunat_hash` varchar(100) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) NOT NULL,
  PRIMARY KEY (`id_nota_credito`),
  UNIQUE KEY `uk_nc_serie_correlativo` (`serie`, `correlativo`),
  KEY `idx_nc_venta_original` (`id_venta_original`),
  CONSTRAINT `notas_credito_ibfk_1` FOREIGN KEY (`id_venta_original`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `notas_credito_ibfk_2` FOREIGN KEY (`id_venta_nc`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `notas_credito_ibfk_3` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. XML firmado + CDR de cada documento enviado (boleta/factura/baja/NC), para
--    conservación y para poder reimprimir/reenviar sin volver a construir el XML.
--    En BD, no en disco: a bajo volumen entra en el mysqldump y evita problemas
--    de permisos en hosting compartido.
CREATE TABLE `comprobantes_sunat` (
  `id_comprobante_sunat` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(20) NOT NULL COMMENT 'venta, baja, nota_credito',
  `id_referencia` int(11) NOT NULL COMMENT 'id_venta o id_nota_credito, según tipo_documento',
  `xml_firmado` longtext DEFAULT NULL,
  `cdr_zip_base64` longtext DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_comprobante_sunat`),
  KEY `idx_comprobante_referencia` (`tipo_documento`, `id_referencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Actualizar el comentario de origen para incluir la Nota de Crédito.
ALTER TABLE `ventas`
  MODIFY COLUMN `origen` tinyint(1) NOT NULL DEFAULT 1
  COMMENT '1=POS presencial, 2=Pedido online, 3=Cambio de talla (diferencia de precio), 4=Separación (anticipo/abono/despacho), 5=Nota de Crédito (efecto en stock/caja)';

-- 9. Backfill: las ventas históricas nunca pasaron por este sistema hacia SUNAT.
--    estado_sunat=0 (no aplica) es el valor correcto, no un hueco. subtotal/igv
--    se derivan de total para no dejarlas en cero.
UPDATE `ventas`
SET `subtotal` = ROUND(`total` / 1.18, 2),
    `igv` = `total` - ROUND(`total` / 1.18, 2)
WHERE `subtotal` = 0.00 AND `total` > 0;

UPDATE `detalle_ventas` dv
INNER JOIN `ventas` v ON dv.id_venta = v.id_venta
SET dv.valor_unitario = ROUND(dv.precio_venta / 1.18, 4),
    dv.igv_linea = ROUND(dv.subtotal - ROUND(dv.subtotal / 1.18, 2), 2)
WHERE dv.valor_unitario = 0.0000 AND dv.subtotal > 0;
