-- =============================================================================
-- PuntoNet POS — Migración: Boleta/Factura en pedidos online
-- Fecha: 2026-08
-- Descripción:
--   Permite al cliente elegir Boleta o Factura al comprar online, reutilizando
--   el mismo modelo de facturación que ya usa el POS presencial: ni `ventas` ni
--   el ticket impreso tienen columnas propias de RUC/razón social, siempre
--   resuelven esos datos vía id_cliente -> clientes -> personas.
--
--   `id_cliente_facturacion` es la entidad a facturar (una empresa, resuelta por
--   RUC en el momento de la compra) y es DISTINTA de `id_cliente` (la cuenta
--   logueada, siempre persona natural). Así "Mis Pedidos" y la sesión del
--   cliente no se ven afectadas por a nombre de quién factura una compra puntual.
--
--   Solo necesaria si tu base de datos ya existía sin estas columnas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

ALTER TABLE `pedidos_online`
  ADD COLUMN `tipo_comprobante` TINYINT(1) NOT NULL DEFAULT 1
  COMMENT '1=Boleta, 2=Factura (mismo mapeo que ventas.tipo_comprobante)'
  AFTER `total`;

ALTER TABLE `pedidos_online`
  ADD COLUMN `id_cliente_facturacion` INT(11) DEFAULT NULL
  COMMENT 'Entidad a facturar cuando tipo_comprobante=2 (empresa con RUC), distinta del id_cliente dueño del pedido'
  AFTER `id_cliente`;

ALTER TABLE `pedidos_online`
  ADD CONSTRAINT `pedidos_online_ibfk_5`
  FOREIGN KEY (`id_cliente_facturacion`) REFERENCES `clientes` (`id_cliente`);
