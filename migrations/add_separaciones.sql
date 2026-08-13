-- =============================================================================
-- PuntoNet POS — Migración: Separación de pedidos con anticipo
-- Fecha: 2026-08
-- Descripción:
--   Permite apartar mercadería con un adelanto (mínimo 50%) y cobrar el saldo
--   en uno o varios abonos posteriores, sin romper el arqueo de caja.
--
--   Diseño: cada pago (el anticipo y cada abono posterior) se registra como su
--   propia venta con `origen=4`, cuyo `total` es lo cobrado ESE día y que queda
--   ligada a la caja abierta de ese día — mismo patrón que la venta-diferencia
--   `origen=3` del cambio de talla. Así una caja ya cerrada nunca cambia hacia
--   atrás y `M_Caja` no necesita ningún cambio.
--
--   La mercadería NO tiene tabla propia: vive en el `detalle_ventas` de la venta
--   del anticipo (`separaciones.id_venta_anticipo`), a precio completo. Eso hace
--   que M_Venta::registrar() descuente el stock, que M_Kardex derive la salida
--   en la fecha correcta y que M_Reporte cuente los ingresos por producto, todo
--   sin tocar esos modelos. Consecuencia esperada: en esa venta concreta
--   SUM(detalle_ventas.subtotal) != ventas.total (mercadería vs. anticipo).
--
--   El saldo NUNCA se almacena, siempre se calcula:
--     saldo = separaciones.total - SUM(ventas.total WHERE id_separacion=? AND estado=1)
--
--   Solo necesaria si tu base de datos ya existía sin estas tablas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

-- 1. Cabecera de la separación.
CREATE TABLE `separaciones` (
  `id_separacion` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL COMMENT 'SEP-000001',
  `id_cliente` int(11) NOT NULL COMMENT 'Obligatorio: hay que saber a quién pertenece lo apartado',
  `id_usuario` int(11) NOT NULL COMMENT 'Quién registró la separación',
  `id_venta_anticipo` int(11) NOT NULL COMMENT 'Venta origen=4 dueña del detalle_ventas (la mercadería)',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` date NOT NULL,
  `total` decimal(10,2) NOT NULL COMMENT 'Valor de la mercadería, congelado al separar',
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Pendiente, 2=Despachada, 0=Anulada',
  `tipo_comprobante_final` tinyint(1) DEFAULT NULL COMMENT 'Elegido al despachar: 1=Boleta, 2=Factura, 3=Nota de Venta',
  `id_usuario_despacho` int(11) DEFAULT NULL,
  `fecha_despacho` datetime DEFAULT NULL,
  `motivo_anulacion` text DEFAULT NULL,
  PRIMARY KEY (`id_separacion`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_separacion_cliente` (`id_cliente`),
  KEY `idx_separacion_estado` (`estado`),
  KEY `idx_separacion_venta_anticipo` (`id_venta_anticipo`),
  CONSTRAINT `separaciones_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `separaciones_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `separaciones_ibfk_3` FOREIGN KEY (`id_venta_anticipo`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `separaciones_ibfk_4` FOREIGN KEY (`id_usuario_despacho`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Marca en `ventas` qué separación paga cada abono (incluido el anticipo y el
--    cobro final del despacho). NULL = venta normal, sin relación con separaciones.
ALTER TABLE `ventas`
  ADD COLUMN `id_separacion` int(11) DEFAULT NULL COMMENT 'Abono/despacho de una separación. NULL = venta normal.',
  ADD KEY `idx_venta_separacion` (`id_separacion`),
  ADD CONSTRAINT `ventas_ibfk_4` FOREIGN KEY (`id_separacion`) REFERENCES `separaciones` (`id_separacion`);

-- 3. Documentar el nuevo valor de origen.
ALTER TABLE `ventas`
  MODIFY COLUMN `origen` tinyint(1) NOT NULL DEFAULT 1
  COMMENT '1=POS presencial, 2=Pedido online, 3=Cambio de talla (diferencia de precio), 4=Separación (anticipo/abono/despacho)';
