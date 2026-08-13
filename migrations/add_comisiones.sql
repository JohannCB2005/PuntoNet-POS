-- =============================================================================
-- PuntoNet POS — Migración: Comisiones por venta
-- Fecha: 2026-08
-- Descripción:
--   Comisión fija en S/ por unidad, asignada por producto. Se congela en
--   detalle_ventas.comision_unitaria al momento de la venta (mismo criterio que
--   costo_unitario), para que un cambio de tarifa nunca reescriba comisiones ya
--   devengadas. cambios_talla.comision_delta guarda el ajuste (comisión entrante
--   - comisión saliente) de un cambio de talla, exista o no diferencia de precio.
--
--   Sin backfill: antes de esta migración ningún producto tenía comisión, así
--   que 0.00 en las filas históricas es el valor real, no un hueco.
--
--   Solo necesaria si tu base de datos ya existía sin estas columnas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

ALTER TABLE `productos`
  ADD COLUMN `comision` decimal(10,2) NOT NULL DEFAULT 0.00
  COMMENT 'Comisión fija en S/ que gana el vendedor por cada unidad vendida';

ALTER TABLE `detalle_ventas`
  ADD COLUMN `comision_unitaria` decimal(10,2) NOT NULL DEFAULT 0.00
  COMMENT 'Foto de productos.comision al momento de la venta (mismo criterio que costo_unitario)';

ALTER TABLE `cambios_talla`
  ADD COLUMN `comision_delta` decimal(10,2) NOT NULL DEFAULT 0.00
  COMMENT 'Ajuste de comisión del cambio: (comision_entrante - comision_saliente) * cantidad';
