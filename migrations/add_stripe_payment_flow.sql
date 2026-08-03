-- =============================================================================
-- PuntoNet POS — Migración: Flujo de pago Stripe verificado en servidor
-- Fecha: 2026-08
-- Descripción:
--   El monto y los precios pasan a calcularse SIEMPRE en el servidor
--   (M_Ecommerce::calcularCarrito). Se añade el ciclo de vida del pago:
--     3 = Pendiente de pago (stock reservado, esperando confirmación de Stripe)
--     1 = Pagado / pendiente de entrega
--     2 = Entregado (venta generada)
--     0 = Rechazado por el administrador
--     4 = Expirado / pago fallido (stock devuelto)
--   Solo necesaria si tu base de datos ya existía con el esquema anterior.
--   En una instalación nueva, base_datos_nissi.sql ya incluye estas columnas.
-- =============================================================================

ALTER TABLE `pedidos_online`
    ADD COLUMN `payment_intent_id` VARCHAR(64) DEFAULT NULL
        COMMENT 'ID del PaymentIntent de Stripe (pi_...). NULL en pedidos anteriores.'
    AFTER `nro_operacion_yape`;

ALTER TABLE `pedidos_online`
    ADD COLUMN `fecha_pago` DATETIME DEFAULT NULL
        COMMENT 'Se llena sólo tras verificar status=succeeded en la API de Stripe.'
    AFTER `payment_intent_id`;

ALTER TABLE `pedidos_online`
    ADD COLUMN `fecha_expira` DATETIME DEFAULT NULL
        COMMENT 'Si estado=3 y fecha_expira < NOW(), el barrido libera el stock.'
    AFTER `fecha_pago`;

ALTER TABLE `pedidos_online`
    ADD COLUMN `token_publico` CHAR(32) NOT NULL DEFAULT ''
        COMMENT 'Token aleatorio exigido por get_pedido para ver la boleta pública.'
    AFTER `fecha_expira`;

ALTER TABLE `pedidos_online`
    ADD UNIQUE INDEX `uq_payment_intent` (`payment_intent_id`);

ALTER TABLE `pedidos_online`
    ADD INDEX `idx_estado_expira` (`estado`, `fecha_expira`);

ALTER TABLE `pedidos_online`
    MODIFY COLUMN `nro_operacion_yape` VARCHAR(50) NOT NULL DEFAULT ''
        COMMENT 'Referencia manual (Yape) — vacía en pedidos pagados con Stripe.';

ALTER TABLE `pedidos_online`
    MODIFY COLUMN `estado` TINYINT(1) NOT NULL DEFAULT 3
        COMMENT '3=Pendiente de pago, 1=Pagado/Pendiente entrega, 2=Entregado, 0=Rechazado, 4=Expirado';
