-- =============================================================================
-- PuntoNet POS — Migración: Cobro por verificación manual (Configuración → Pagos)
-- Fecha: 2026-08
-- Descripción:
--   Permite cobrar sin pasarela: el cliente paga con QR (Yape/Plin/Izipay QR) o
--   por transferencia bancaria (BCP/BBVA/Interbank/Scotiabank) y el vendedor
--   verifica el pago manualmente desde "Pedidos Online".
--
--   Columnas nuevas en `pedidos_online`:
--     metodo_pago_online : método presentado en el checkout
--                          (tarjeta | taypi | billetera | transferencia)
--     medio_pago_usado   : medio que el cliente reporta que usó
--                          (yape | plin | izipay_qr | bcp | bbva | interbank | scotiabank)
--     referencia_cliente : número de operación/boucher (único, no repetible)
--     captura_pago       : ruta de la imagen comprobante subida por el cliente
--     id_verificado_por  : usuario admin que aprobó la verificación manual
--     fecha_verificacion : cuándo se aprobó manualmente
--
--   Nuevo estado 6 en pedidos_online.estado: 'Pago enviado — en verificación manual'.
--   El estado 6 NO expira (el stock queda reservado hasta que el admin apruebe o
--   rechace); el barrido de vencidos y el abandono solo aplican al estado 3.
-- =============================================================================

ALTER TABLE `pedidos_online`
  ADD COLUMN `metodo_pago_online` varchar(20) DEFAULT NULL COMMENT 'Método presentado en checkout: tarjeta|taypi|billetera|transferencia' AFTER `payment_id_taypi`,
  ADD COLUMN `medio_pago_usado` varchar(30) DEFAULT NULL COMMENT 'Medio reportado por el cliente: yape|plin|izipay_qr|bcp|bbva|interbank|scotiabank' AFTER `metodo_pago_online`,
  ADD COLUMN `referencia_cliente` varchar(255) DEFAULT NULL COMMENT 'Número de operación/boucher reportado (no repetible)' AFTER `medio_pago_usado`,
  ADD COLUMN `captura_pago` varchar(255) DEFAULT NULL COMMENT 'Ruta de la imagen comprobante subida por el cliente' AFTER `referencia_cliente`,
  ADD COLUMN `id_verificado_por` int(11) DEFAULT NULL COMMENT 'Usuario admin que aprobó la verificación manual' AFTER `fecha_pago`,
  ADD COLUMN `fecha_verificacion` datetime DEFAULT NULL COMMENT 'Cuándo se aprobó manualmente' AFTER `id_verificado_por`,
  ADD KEY `idx_referencia_cliente` (`referencia_cliente`),
  ADD KEY `idx_metodo_online` (`metodo_pago_online`);