-- =============================================================================
-- PuntoNet POS — Migración: Seguimiento de pedidos + reparación del cierre de caja
-- Fecha: 2026-08
-- Descripción:
--   1. Repara `cerrarCaja()` (models/M_Caja.php), que hoy falla siempre porque
--      escribe en columnas de `cajas` que nunca existieron en el esquema
--      (SQLSTATE 42S22 Unknown column 'cierre_efectivo').
--   2. Ata `ventas` a la caja física que efectivamente recibió el dinero
--      (`id_caja`), para que un pedido online pagado con pasarela deje de
--      sumarse al arqueo de quien lo despache. `id_caja = NULL` = venta sin
--      caja física asociada (pedidos online).
--   3. Agrega el estado intermedio "Preparado" y su seguimiento de fechas a
--      `pedidos_online`, más el motivo de rechazo (se le envía al cliente).
--   Solo necesaria si tu base de datos ya existía sin estas columnas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

-- 1. Columnas de cierre físico y diferencia por método, usadas por cerrarCaja()
--    pero ausentes del esquema original — de ahí que el cierre de caja fallara siempre.
ALTER TABLE `cajas`
    ADD COLUMN `cierre_efectivo` DECIMAL(10,2) DEFAULT NULL AFTER `monto_cierre`,
    ADD COLUMN `cierre_yape` DECIMAL(10,2) DEFAULT NULL AFTER `cierre_efectivo`,
    ADD COLUMN `cierre_tarjeta` DECIMAL(10,2) DEFAULT NULL AFTER `cierre_yape`,
    ADD COLUMN `dif_efectivo` DECIMAL(10,2) DEFAULT NULL AFTER `diferencia`,
    ADD COLUMN `dif_yape` DECIMAL(10,2) DEFAULT NULL AFTER `dif_efectivo`,
    ADD COLUMN `dif_tarjeta` DECIMAL(10,2) DEFAULT NULL AFTER `dif_yape`;

-- 2. Vínculo real ventas → caja física, y origen de la venta
ALTER TABLE `ventas`
    ADD COLUMN `id_caja` INT(11) DEFAULT NULL
        COMMENT 'Caja física que recibió el dinero. NULL = sin caja física (ej. pedido online pagado por pasarela).'
    AFTER `id_usuario`,
    ADD COLUMN `origen` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1=POS presencial, 2=Pedido online'
    AFTER `estado`;

ALTER TABLE `ventas`
    ADD INDEX `idx_venta_caja` (`id_caja`),
    ADD CONSTRAINT `ventas_ibfk_3` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`);

-- 3. Seguimiento de estados de pedidos_online: preparado / entregado / motivo de rechazo
ALTER TABLE `pedidos_online`
    ADD COLUMN `fecha_preparado` DATETIME DEFAULT NULL AFTER `fecha_pago`,
    ADD COLUMN `fecha_entregado` DATETIME DEFAULT NULL AFTER `fecha_preparado`,
    ADD COLUMN `motivo_rechazo` TEXT DEFAULT NULL
        COMMENT 'Motivo escrito por quien despacha al rechazar; se envía al cliente por correo.'
    AFTER `observaciones`;

ALTER TABLE `pedidos_online`
    MODIFY COLUMN `estado` TINYINT(1) NOT NULL DEFAULT 3
        COMMENT '3=Pendiente de pago, 1=Pagado/Pendiente entrega, 5=Preparado, 2=Entregado, 0=Rechazado, 4=Expirado';
