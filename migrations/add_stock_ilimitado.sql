-- =============================================================================
-- NISSI POS — Migración: Productos de Stock Ilimitado (servicios)
-- Fecha: 2026-08
-- Descripción:
--   Permite marcar un producto como servicio con stock ilimitado (ej. envío S/10).
--   Con stock_ilimitado=1 el producto:
--     - se muestra siempre en catálogo (tienda y POS) aunque stock_piezas sea 0,
--     - NO se valida contra el stock ni se descuenta al vender,
--     - tampoco se le devuelve stock al anular/rechazar la venta o pedido.
--   Sólo aplica a productos simples (lás Envío no tienen tallas).
-- =============================================================================

ALTER TABLE `productos`
    ADD COLUMN `stock_ilimitado` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1=Servicio con stock ilimitado: no valida ni descuenta stock, se muestra siempre'
    AFTER `stock_piezas`;

-- =============================================================================
-- Verificación post-migración:
--   SELECT id_producto, nombre, stock_piezas, stock_ilimitado FROM productos LIMIT 5;
-- =============================================================================