-- =============================================================================
-- PuntoNet POS — Migración: Cambio de talla + tabla de Kardex faltante
-- Fecha: 2026-08
-- Descripción:
--   1. Crea `kardex_movimientos`, que models/M_Kardex.php ya consulta pero que
--      NUNCA existió en el esquema versionado ni en la base real — el módulo
--      Kardex está roto hoy para cualquier producto
--      (SQLSTATE 42S02: Table 'kardex_movimientos' doesn't exist). El DDL se
--      deriva exactamente de las columnas que el código ya espera
--      (M_Kardex.php:117-128 y :251-252), no se inventa nada nuevo.
--   2. Crea `cambios_talla`, la trazabilidad de un cambio de talla sobre una
--      venta ya emitida: qué línea, qué producto salió, cuál entró, y si hubo
--      diferencia de dinero, a qué venta (origen=3) quedó ligada.
--   3. Actualiza el COMMENT de ventas.origen para documentar el valor 3.
--   Solo necesaria si tu base de datos ya existía sin estas tablas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

-- 1. Kardex de movimientos manuales (entrada/salida) — desbloquea M_Kardex.php.
CREATE TABLE `kardex_movimientos` (
  `id_movimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `referencia` varchar(100) DEFAULT NULL COMMENT 'Documento o venta de referencia (ej. V-000123 en un cambio de talla)',
  `concepto` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_movimiento`),
  KEY `idx_kardex_producto` (`id_producto`),
  KEY `idx_kardex_usuario` (`id_usuario`),
  CONSTRAINT `kardex_movimientos_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `kardex_movimientos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Trazabilidad de cambios de talla sobre una venta ya emitida.
--    La venta original NUNCA se edita: esto es un registro nuevo que apunta a ella.
CREATE TABLE `cambios_talla` (
  `id_cambio` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta_original` int(11) NOT NULL,
  `id_detalle_original` int(11) NOT NULL COMMENT 'Línea exacta de detalle_ventas que se cambió',
  `id_producto_saliente` int(11) NOT NULL COMMENT 'Talla que el cliente devuelve (vuelve al stock)',
  `id_producto_entrante` int(11) NOT NULL COMMENT 'Talla que el cliente se lleva (sale del stock)',
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `precio_saliente` decimal(10,2) NOT NULL,
  `precio_entrante` decimal(10,2) NOT NULL,
  `diferencia` decimal(10,2) NOT NULL COMMENT 'precio_entrante - precio_saliente, por la cantidad. Negativo = se devolvió dinero al cliente.',
  `id_venta_diferencia` int(11) DEFAULT NULL COMMENT 'Venta origen=3 que registró el cobro/devolución. NULL si la diferencia fue 0.',
  `id_usuario` int(11) NOT NULL COMMENT 'Quién registró el cambio',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `motivo` text DEFAULT NULL,
  PRIMARY KEY (`id_cambio`),
  KEY `idx_cambio_venta_original` (`id_venta_original`),
  KEY `idx_cambio_detalle_original` (`id_detalle_original`),
  CONSTRAINT `cambios_talla_ibfk_1` FOREIGN KEY (`id_venta_original`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `cambios_talla_ibfk_2` FOREIGN KEY (`id_detalle_original`) REFERENCES `detalle_ventas` (`id_detalle`),
  CONSTRAINT `cambios_talla_ibfk_3` FOREIGN KEY (`id_producto_saliente`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `cambios_talla_ibfk_4` FOREIGN KEY (`id_producto_entrante`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `cambios_talla_ibfk_5` FOREIGN KEY (`id_venta_diferencia`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `cambios_talla_ibfk_6` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Documentar el nuevo valor de origen.
ALTER TABLE `ventas`
  MODIFY `origen` tinyint(1) NOT NULL DEFAULT 1
  COMMENT '1=POS presencial, 2=Pedido online, 3=Cambio de talla (diferencia de precio)';
