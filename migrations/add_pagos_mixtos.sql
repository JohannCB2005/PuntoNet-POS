-- =============================================================================
-- PuntoNet POS — Migración: Pago mixto en Nueva Venta
-- Fecha: 2026-08
-- Descripción:
--   Hoy una venta admite un solo `metodo_pago`. `M_Caja::calcularDesglosePorMetodo()`
--   agrupa SUM(total) por ese único valor, así que si alguien paga mitad efectivo y
--   mitad Yape, el monto completo cae en un solo balde al cuadrar caja — el arqueo
--   deja de reflejar el dinero físico real.
--
--   Se crea `pagos_venta`, una fila por CADA línea de pago de una venta. Una línea
--   siempre lleva un método real (1/2/3); el valor 4=Mixto sigue existiendo solo
--   como etiqueta resumen en `ventas.metodo_pago` cuando una venta tiene más de una
--   línea con métodos distintos — el desglose de caja ya no depende de esa etiqueta.
--
--   El backfill es obligatorio, no cosmético: si M_Caja pasa a leer solo de
--   `pagos_venta`, cualquier venta existente sin una fila ahí desaparecería del
--   arqueo en vivo de una caja todavía abierta.
--
--   Solo necesaria si tu base de datos ya existía sin esta tabla.
--   En una instalación nueva, base_datos_nissi.sql ya la incluye.
-- =============================================================================

CREATE TABLE `pagos_venta` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `metodo_pago` tinyint(1) NOT NULL COMMENT '1=Efectivo, 2=Yape/Plin, 3=Tarjeta (nunca 4: una línea es siempre un método real)',
  `monto` decimal(10,2) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL COMMENT 'N° de operación/voucher, opcional',
  PRIMARY KEY (`id_pago`),
  KEY `idx_pagos_venta_id_venta` (`id_venta`),
  CONSTRAINT `pagos_venta_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill: una fila por venta existente con método simple (1/2/3), representando
-- exactamente el comportamiento de hoy (1 venta = 1 método = el total completo).
-- Si hubiera alguna venta con metodo_pago=4 (no debería: nunca hubo UI para generarla),
-- queda fuera de este INSERT a propósito y debe revisarse a mano antes de continuar.
INSERT INTO `pagos_venta` (id_venta, metodo_pago, monto)
SELECT id_venta, metodo_pago, total FROM `ventas` WHERE metodo_pago IN (1, 2, 3);
