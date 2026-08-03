-- =============================================================================
-- PuntoNet POS — Migración: Tablas de Cotizaciones
-- Fecha: 2026-08
-- Descripción:
--   El módulo de Cotizaciones (controllers/C_Cotizacion.php, models/M_Cotizacion.php)
--   está ruteado y activo en index.php, pero nunca tuvo tablas en el esquema base.
--   Solo necesaria si tu base de datos ya existía sin estas tablas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `cotizaciones` (
  `id_cotizacion` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `cliente_nombre_manual` varchar(150) DEFAULT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `igv` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Pendiente, 2=Convertida a venta, 0=Anulada',
  `id_venta` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_cotizacion`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_venta` (`id_venta`),
  CONSTRAINT `cotizaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `cotizaciones_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `cotizaciones_ibfk_3` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `detalle_cotizaciones` (
  `id_detalle_cotizacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_cotizacion` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `piezas` decimal(10,2) NOT NULL DEFAULT 1.00,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle_cotizacion`),
  KEY `id_cotizacion` (`id_cotizacion`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_cotizaciones_ibfk_1` FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE CASCADE,
  CONSTRAINT `detalle_cotizaciones_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
