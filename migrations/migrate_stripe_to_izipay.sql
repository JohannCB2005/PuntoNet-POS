-- =============================================================================
-- PuntoNet POS — Migración: Stripe → Izipay
-- Fecha: 2026-08
-- Descripción:
--   Sustituye Stripe por Izipay como única pasarela de pago. El cambio de fondo
--   en el esquema es que **un pedido puede tener varias transacciones de pago**.
--
--   Con Stripe la relación era 1 pedido = 1 PaymentIntent inmutable, y eso se
--   forzaba con un índice UNIQUE. Izipay, en cambio, acumula los reintentos bajo
--   un mismo `orderId`: si el cliente paga con una tarjeta que rechaza y vuelve a
--   intentarlo, el pedido termina con N transacciones y solo la última queda en
--   estado PAID. Mantener el UNIQUE impediría ese reintento.
--
--   1. Elimina el índice UNIQUE `uq_payment_intent`.
--   2. Renombra `payment_intent_id` → `referencia_pago`: ahora guarda el
--      `orderId` que enviamos a Izipay (formato `PN-{id_pedido}`). Los pedidos
--      antiguos conservan su `pi_...` de Stripe como dato histórico.
--   3. Añade `transaccion_uuid`, el UUID de la transacción que quedó PAID, que
--      es lo que permite cuadrar un pedido con una fila concreta del Back Office
--      de Izipay.
--
--   Solo necesaria si tu base de datos ya existía con el flujo de Stripe.
--   En una instalación nueva, base_datos_nissi.sql ya refleja este estado.
-- =============================================================================

-- 1. Fuera el UNIQUE: es incompatible con los reintentos de Izipay.
ALTER TABLE `pedidos_online`
  DROP INDEX `uq_payment_intent`;

-- 2. Renombrar la columna (CHANGE, no RENAME COLUMN, por compatibilidad con
--    versiones antiguas de MySQL/MariaDB en hosting compartido).
ALTER TABLE `pedidos_online`
  CHANGE `payment_intent_id` `referencia_pago` VARCHAR(64) DEFAULT NULL
  COMMENT 'orderId enviado a Izipay (PN-{id_pedido}). En pedidos antiguos, el PaymentIntent de Stripe.';

-- 3. Índice NO único: lo usan el barrido de vencidos y el IPN para localizar
--    el pedido a partir del orderId que devuelve Izipay.
ALTER TABLE `pedidos_online`
  ADD INDEX `idx_referencia_pago` (`referencia_pago`);

-- 4. UUID de la transacción efectivamente pagada (conciliación con el Back Office).
ALTER TABLE `pedidos_online`
  ADD COLUMN `transaccion_uuid` VARCHAR(40) DEFAULT NULL
  COMMENT 'UUID de la transaccion Izipay que quedo PAID, para cuadrar con el Back Office'
  AFTER `referencia_pago`;

-- 5. El comentario de fecha_pago mencionaba la API de Stripe.
ALTER TABLE `pedidos_online`
  MODIFY `fecha_pago` DATETIME DEFAULT NULL
  COMMENT 'Se llena solo tras verificar en Izipay que existe una transaccion PAID';
