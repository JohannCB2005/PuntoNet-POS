-- =============================================================================
-- NISSI POS — Migración: Sistema de Variantes de Talla
-- Fecha: 2026-08
-- Descripción:
--   Agrega soporte para productos padre/hijo con variantes de talla.
--   Los productos padre (es_agrupador=1) NO son vendibles directamente.
--   Solo sus hijos (variantes con id_producto_padre) se venden.
-- =============================================================================

-- 1. Columna agrupadora: marca si este producto es un "producto padre"
ALTER TABLE `productos`
    ADD COLUMN `es_agrupador` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1=Producto padre NO vendible que agrupa variantes de talla, 0=Variante individual o producto simple'
    AFTER `id_bimestre`;

-- 2. Columna de referencia al padre (NULL para padres y productos simples)
ALTER TABLE `productos`
    ADD COLUMN `id_producto_padre` INT(11) DEFAULT NULL
        COMMENT 'FK al id_producto del producto padre agrupador. NULL si es padre o producto simple.'
    AFTER `es_agrupador`;

-- 3. Clave foránea de auto-referencia (padre → hijos)
ALTER TABLE `productos`
    ADD CONSTRAINT `fk_producto_padre`
        FOREIGN KEY (`id_producto_padre`) REFERENCES `productos` (`id_producto`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. Índice para acelerar consultas de variantes por padre
ALTER TABLE `productos`
    ADD INDEX `idx_producto_padre` (`id_producto_padre`);

-- =============================================================================
-- Verificación post-migración:
--   SELECT id_producto, nombre, es_agrupador, id_producto_padre FROM productos LIMIT 5;
-- =============================================================================
