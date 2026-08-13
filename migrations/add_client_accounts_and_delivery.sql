-- =============================================================================
-- PuntoNet POS — Migración: Cuentas de cliente + entrega en colegio
-- Fecha: 2026-08
-- Descripción:
--   Agrega autenticación de clientes (email + contraseña, verificación por
--   código, recuperación de contraseña) y el método de entrega del pedido
--   online (recojo en tienda vs. entrega a un estudiante en el colegio).
--   Solo necesaria si tu base de datos ya existía sin estas columnas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

-- 1. Cuenta de cliente sobre la tabla `clientes` ya existente
ALTER TABLE `clientes`
    ADD COLUMN `email` VARCHAR(150) DEFAULT NULL AFTER `tipo_cliente`,
    ADD COLUMN `password` VARCHAR(255) DEFAULT NULL AFTER `email`,
    ADD COLUMN `email_verificado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`,
    ADD COLUMN `codigo_verificacion` VARCHAR(10) DEFAULT NULL AFTER `email_verificado`,
    ADD COLUMN `codigo_verificacion_expira` DATETIME DEFAULT NULL AFTER `codigo_verificacion`,
    ADD COLUMN `codigo_reset` VARCHAR(10) DEFAULT NULL AFTER `codigo_verificacion_expira`,
    ADD COLUMN `codigo_reset_expira` DATETIME DEFAULT NULL AFTER `codigo_reset`,
    ADD COLUMN `fecha_registro` DATETIME DEFAULT NULL AFTER `codigo_reset_expira`;

ALTER TABLE `clientes`
    ADD UNIQUE INDEX `uq_cliente_email` (`email`);

-- 2. Método de entrega sobre `pedidos_online` ya existente
ALTER TABLE `pedidos_online`
    ADD COLUMN `tipo_entrega` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1=Recojo en tienda, 2=Entrega en colegio'
    AFTER `total`,
    ADD COLUMN `estudiante_nombre` VARCHAR(150) DEFAULT NULL AFTER `tipo_entrega`,
    ADD COLUMN `id_nivel` INT(11) DEFAULT NULL AFTER `estudiante_nombre`,
    ADD COLUMN `id_grado` INT(11) DEFAULT NULL AFTER `id_nivel`,
    ADD COLUMN `observaciones` TEXT DEFAULT NULL AFTER `id_grado`;

ALTER TABLE `pedidos_online`
    ADD INDEX `idx_pedido_nivel` (`id_nivel`),
    ADD INDEX `idx_pedido_grado` (`id_grado`);

ALTER TABLE `pedidos_online`
    ADD CONSTRAINT `pedidos_online_ibfk_3` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_educativos` (`id_nivel`),
    ADD CONSTRAINT `pedidos_online_ibfk_4` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id_grado`);
