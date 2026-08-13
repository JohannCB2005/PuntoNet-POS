-- =============================================================================
-- NISSI POS — Script de Base de Datos Completo
-- Tienda de Uniformes y Módulos Escolares
-- Compatible con InfinityFree (phpMyAdmin)
-- Reglas: sin CREATE DATABASE, sin USE, sin STORED PROCEDURES, sin DELIMITER
-- Charset: utf8mb4 | Engine: InnoDB
--
-- INSTRUCCIONES:
--   1. En phpMyAdmin, selecciona la base de datos
--   2. Ve a la pestaña "SQL" y pega este script completo
--   3. Ejecuta — las tablas se crearán en el orden correcto
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Eliminar tablas (orden inverso a las FK)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `detalle_pedidos_online`;
DROP TABLE IF EXISTS `pedidos_online`;
DROP TABLE IF EXISTS `detalle_ventas`;
DROP TABLE IF EXISTS `ventas`;
DROP TABLE IF EXISTS `cajas`;
DROP TABLE IF EXISTS `vales`;
DROP TABLE IF EXISTS `trabajadores`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `unidades_medida`;
DROP TABLE IF EXISTS `tallas`;
DROP TABLE IF EXISTS `tipos_corbata`;
DROP TABLE IF EXISTS `grados`;
DROP TABLE IF EXISTS `niveles_educativos`;
DROP TABLE IF EXISTS `areas_cursos`;
DROP TABLE IF EXISTS `bimestres`;
DROP TABLE IF EXISTS `dependencias`;
DROP TABLE IF EXISTS `facultades`;
DROP TABLE IF EXISTS `tipos_trabajador`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `personas`;

-- =============================================================================
-- TABLAS BASE
-- =============================================================================

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `personas` (
  `id_persona` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=DNI, 2=RUC, 3=Pasaporte',
  `numero_documento` varchar(15) NOT NULL,
  `nombres_razon_social` varchar(150) NOT NULL,
  `apellidos` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `ubigeo` varchar(6) DEFAULT NULL COMMENT 'Código INEI departamento+provincia+distrito, exigido en el XML de factura SUNAT',
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_persona`),
  UNIQUE KEY `numero_documento` (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `unidades_medida` (
  `id_unidad` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `abreviatura` varchar(10) NOT NULL,
  `codigo_sunat` varchar(5) NOT NULL DEFAULT 'NIU' COMMENT 'Catálogo 03 SUNAT: NIU=Unidad, DZN=Docena',
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_unidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABLAS DE UNIFORMES: TALLAS Y TIPOS DE CORBATA
-- =============================================================================

CREATE TABLE `tallas` (
  `id_talla` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL COMMENT 'Ej: XS, S, M, L, 2, 4, 6, 8, etc.',
  `orden` int(11) DEFAULT 0 COMMENT 'Para ordenar en selector',
  PRIMARY KEY (`id_talla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `tipos_corbata` (
  `id_tipo_corbata` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL COMMENT 'Elástica Pequeña, Elástica Grande, Nudo',
  PRIMARY KEY (`id_tipo_corbata`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABLAS DE MÓDULOS ESCOLARES
-- =============================================================================

CREATE TABLE `niveles_educativos` (
  `id_nivel` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL COMMENT 'Inicial, Primaria, Secundaria',
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_nivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `grados` (
  `id_grado` int(11) NOT NULL AUTO_INCREMENT,
  `id_nivel` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL COMMENT '1°, 2°, 3°, etc.',
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_grado`),
  KEY `id_nivel` (`id_nivel`),
  CONSTRAINT `grados_ibfk_1` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_educativos` (`id_nivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `areas_cursos` (
  `id_area` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL COMMENT 'Matemáticas, Comunicación, CTA, etc.',
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `bimestres` (
  `id_bimestre` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL COMMENT 'I Bimestre, II Bimestre, etc.',
  PRIMARY KEY (`id_bimestre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- INVENTARIO: PRODUCTOS NISSI
-- =============================================================================

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `id_unidad` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `costo_produccion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comision` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Comisión fija en S/ que gana el vendedor por cada unidad vendida',
  `stock_piezas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_ilimitado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Servicio con stock ilimitado: no valida ni descuenta stock, se muestra siempre',
  `estado` tinyint(1) DEFAULT 1,
  `imagen` varchar(255) DEFAULT NULL,
  -- Atributos de Uniformes
  `id_talla` int(11) DEFAULT NULL,
  `id_tipo_corbata` int(11) DEFAULT NULL COMMENT 'Solo para corbatas',
  -- Atributos de Módulos Escolares
  `id_nivel` int(11) DEFAULT NULL,
  `id_grado` int(11) DEFAULT NULL,
  `id_area` int(11) DEFAULT NULL,
  `id_bimestre` int(11) DEFAULT NULL,
  -- Variantes de talla
  `es_agrupador` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Producto padre con variantes de talla',
  `id_producto_padre` int(11) DEFAULT NULL COMMENT 'FK al producto padre si es variante',
  PRIMARY KEY (`id_producto`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_unidad` (`id_unidad`),
  KEY `id_talla` (`id_talla`),
  KEY `id_tipo_corbata` (`id_tipo_corbata`),
  KEY `id_nivel` (`id_nivel`),
  KEY `id_grado` (`id_grado`),
  KEY `id_area` (`id_area`),
  KEY `id_bimestre` (`id_bimestre`),
  KEY `idx_producto_padre` (`id_producto_padre`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`),
  CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_unidad`) REFERENCES `unidades_medida` (`id_unidad`),
  CONSTRAINT `fk_producto_talla` FOREIGN KEY (`id_talla`) REFERENCES `tallas` (`id_talla`),
  CONSTRAINT `fk_producto_tipo_corbata` FOREIGN KEY (`id_tipo_corbata`) REFERENCES `tipos_corbata` (`id_tipo_corbata`),
  CONSTRAINT `fk_producto_nivel` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_educativos` (`id_nivel`),
  CONSTRAINT `fk_producto_grado` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id_grado`),
  CONSTRAINT `fk_producto_area` FOREIGN KEY (`id_area`) REFERENCES `areas_cursos` (`id_area`),
  CONSTRAINT `fk_producto_bimestre` FOREIGN KEY (`id_bimestre`) REFERENCES `bimestres` (`id_bimestre`),
  CONSTRAINT `fk_producto_padre` FOREIGN KEY (`id_producto_padre`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- USUARIOS Y CLIENTES
-- =============================================================================

-- Control de fuerza bruta de login, persistido aquí y no en $_SESSION: la sesión la
-- controla el cliente, así que un contador en cookie se evade descartándola.
CREATE TABLE `intentos_login` (
  `id_intento` int(11) NOT NULL AUTO_INCREMENT,
  `ambito` varchar(10) NOT NULL COMMENT 'staff | cliente — las dos sesiones son independientes',
  `identificador` varchar(150) NOT NULL COMMENT 'username (staff) o email (cliente), tal como se tecleó',
  `intentos` int(11) NOT NULL DEFAULT 0,
  `ultimo_intento` datetime NOT NULL,
  PRIMARY KEY (`id_intento`),
  UNIQUE KEY `uk_ambito_identificador` (`ambito`, `identificador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Activo, 0=Dado de baja. Independiente de personas.estado (cuenta de cliente)',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `id_persona` (`id_persona`),
  UNIQUE KEY `username` (`username`),
  KEY `id_rol` (`id_rol`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id_persona`) ON DELETE CASCADE,
  CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `tipo_cliente` tinyint(1) DEFAULT 1 COMMENT '1=Regular, 2=Mayorista',
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `codigo_verificacion` varchar(10) DEFAULT NULL,
  `codigo_verificacion_expira` datetime DEFAULT NULL,
  `codigo_reset` varchar(10) DEFAULT NULL,
  `codigo_reset_expira` datetime DEFAULT NULL,
  `codigo_verificacion_intentos` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Intentos fallidos del código de verificación; a los 5 el código se invalida',
  `codigo_reset_intentos` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Intentos fallidos del código de reset; a los 5 el código se invalida',
  `fecha_registro` datetime DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `id_persona` (`id_persona`),
  UNIQUE KEY `uq_cliente_email` (`email`),
  CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id_persona`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- CAJAS Y VENTAS
-- =============================================================================

CREATE TABLE `cajas` (
  `id_caja` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `monto_apertura` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_apertura` datetime NOT NULL DEFAULT current_timestamp(),
  `monto_cierre` decimal(10,2) DEFAULT NULL,
  `cierre_efectivo` decimal(10,2) DEFAULT NULL,
  `cierre_yape` decimal(10,2) DEFAULT NULL,
  `cierre_tarjeta` decimal(10,2) DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `total_ventas` decimal(10,2) DEFAULT NULL,
  `num_ventas` int(11) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `dif_efectivo` decimal(10,2) DEFAULT NULL,
  `dif_yape` decimal(10,2) DEFAULT NULL,
  `dif_tarjeta` decimal(10,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Abierta, 0=Cerrada',
  PRIMARY KEY (`id_caja`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `cajas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_caja` int(11) DEFAULT NULL COMMENT 'Caja física que recibió el dinero. NULL = sin caja física (ej. pedido online pagado por pasarela).',
  `id_cliente` int(11) DEFAULT NULL COMMENT 'NULL = Público General',
  `tipo_comprobante` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Boleta, 2=Factura, 3=Ticket',
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Efectivo, 2=Yape/Plin, 3=Tarjeta, 4=Mixto',
  `pago_efectivo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` tinyint(1) DEFAULT 1 COMMENT '1=Activa, 0=Anulada',
  `origen` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=POS presencial, 2=Pedido online, 3=Cambio de talla (diferencia de precio), 4=Separación (anticipo/abono/despacho), 5=Nota de Crédito (efecto en stock/caja)',
  `id_separacion` int(11) DEFAULT NULL COMMENT 'Abono/despacho de una separación. NULL = venta normal.',
  `serie` varchar(4) DEFAULT NULL COMMENT 'NULL = Nota de Venta, nunca se envía a SUNAT',
  `correlativo` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de venta sin IGV, persistido al registrar',
  `igv` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado_sunat` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '0=No aplica (nota de venta), 1=Pendiente de envío, 2=Aceptado, 3=Rechazado, 4=Baja en trámite, 5=Dado de baja',
  `sunat_codigo` varchar(10) DEFAULT NULL COMMENT 'Código del CDR (0=aceptado, 2000-3999=rechazado)',
  `sunat_mensaje` varchar(255) DEFAULT NULL,
  `sunat_ticket` varchar(40) DEFAULT NULL COMMENT 'Ticket de sendSummary (baja), se consulta con getStatus',
  `sunat_hash` varchar(100) DEFAULT NULL COMMENT 'digestValue del XML firmado, va en el QR del ticket impreso',
  `sunat_intentos` int(11) NOT NULL DEFAULT 0,
  `sunat_fecha_envio` datetime DEFAULT NULL,
  PRIMARY KEY (`id_venta`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_cliente` (`id_cliente`),
  KEY `idx_venta_caja` (`id_caja`),
  KEY `idx_venta_separacion` (`id_separacion`),
  UNIQUE KEY `uk_serie_correlativo` (`serie`, `correlativo`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `ventas_ibfk_3` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detalle_ventas` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Número de piezas/unidades',
  `precio_venta` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comision_unitaria` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Foto de productos.comision al momento de la venta (mismo criterio que costo_unitario)',
  `valor_unitario` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'precio_venta sin IGV',
  `igv_linea` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tipo_afectacion_igv` varchar(2) NOT NULL DEFAULT '10' COMMENT 'Catálogo 07 SUNAT: 10=Gravado-Operación Onerosa',
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_venta` (`id_venta`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE,
  CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `comision_delta` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ajuste de comisión del cambio: (comision_entrante - comision_saliente) * cantidad',
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

-- =============================================================================
-- SEPARACIONES (apartar mercadería con anticipo)
-- =============================================================================
-- La mercadería no tiene tabla propia: vive en el detalle_ventas de la venta del
-- anticipo (id_venta_anticipo). Cada pago posterior es otra venta origen=4 con
-- id_separacion apuntando aquí. El saldo nunca se almacena, se calcula:
--   total - SUM(ventas.total WHERE id_separacion=? AND estado=1)

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

-- La FK de ventas.id_separacion se añade aquí y no en el CREATE TABLE de ventas
-- porque la referencia es circular (separaciones también apunta a ventas).
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_4` FOREIGN KEY (`id_separacion`) REFERENCES `separaciones` (`id_separacion`);

-- =============================================================================
-- SUNAT: series, notas de crédito y almacén de XML/CDR
-- =============================================================================
-- Series y correlativos, independientes de los que use un tercero (p.ej. Tukifac).
-- El siguiente correlativo se reserva con SELECT ... FOR UPDATE (M_Serie.php)
-- dentro de la misma transacción que registra la venta.

CREATE TABLE `series_comprobante` (
  `id_serie` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_comprobante` tinyint(1) NOT NULL COMMENT '1=Boleta, 2=Factura, 4=Nota de Crédito ligada a Boleta, 5=Nota de Crédito ligada a Factura',
  `serie` varchar(4) NOT NULL COMMENT 'B002 / F002 / BC02 / FC02',
  `correlativo_actual` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Activa, 0=Cerrada',
  PRIMARY KEY (`id_serie`),
  UNIQUE KEY `uk_tipo_serie` (`tipo_comprobante`, `serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Única salida legal cuando ya no se puede anular (>7 días) o el comprobante fue
-- rechazado tras haber sido aceptado. Motivo 01 (anulación de la operación) —
-- sin soporte de NC parcial.
CREATE TABLE `notas_credito` (
  `id_nota_credito` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta_original` int(11) NOT NULL COMMENT 'Comprobante que se corrige',
  `id_venta_nc` int(11) DEFAULT NULL COMMENT 'Venta origen=5 que registra el efecto en stock/caja, ligada a la caja de HOY',
  `serie` varchar(4) NOT NULL,
  `correlativo` int(11) NOT NULL,
  `tipo_motivo` varchar(2) NOT NULL DEFAULT '01' COMMENT 'Catálogo 09 SUNAT: 01=Anulación de la operación',
  `total` decimal(10,2) NOT NULL,
  `estado_sunat` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Pendiente, 2=Aceptado, 3=Rechazado',
  `sunat_codigo` varchar(10) DEFAULT NULL,
  `sunat_mensaje` varchar(255) DEFAULT NULL,
  `sunat_hash` varchar(100) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) NOT NULL,
  PRIMARY KEY (`id_nota_credito`),
  UNIQUE KEY `uk_nc_serie_correlativo` (`serie`, `correlativo`),
  KEY `idx_nc_venta_original` (`id_venta_original`),
  CONSTRAINT `notas_credito_ibfk_1` FOREIGN KEY (`id_venta_original`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `notas_credito_ibfk_2` FOREIGN KEY (`id_venta_nc`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `notas_credito_ibfk_3` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- XML firmado + CDR de cada documento enviado (boleta/factura/baja/NC). En BD,
-- no en disco: a bajo volumen entra en el mysqldump y evita problemas de
-- permisos en hosting compartido.
CREATE TABLE `comprobantes_sunat` (
  `id_comprobante_sunat` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(20) NOT NULL COMMENT 'venta, baja, nota_credito',
  `id_referencia` int(11) NOT NULL COMMENT 'id_venta o id_nota_credito, según tipo_documento',
  `xml_firmado` longtext DEFAULT NULL,
  `cdr_zip_base64` longtext DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_comprobante_sunat`),
  KEY `idx_comprobante_referencia` (`tipo_documento`, `id_referencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- COTIZACIONES
-- =============================================================================

CREATE TABLE `cotizaciones` (
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

CREATE TABLE `detalle_cotizaciones` (
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

-- =============================================================================
-- PEDIDOS ONLINE (E-Commerce NISSI)
-- =============================================================================

CREATE TABLE `pedidos_online` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_cliente_facturacion` int(11) DEFAULT NULL COMMENT 'Entidad a facturar cuando tipo_comprobante=2 (empresa con RUC), distinta del id_cliente dueño del pedido',
  `fecha_pedido` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `tipo_comprobante` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Boleta, 2=Factura (mismo mapeo que ventas.tipo_comprobante)',
  `tipo_entrega` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Recojo en tienda, 2=Entrega en colegio',
  `estudiante_nombre` varchar(150) DEFAULT NULL,
  `id_nivel` int(11) DEFAULT NULL,
  `id_grado` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `motivo_rechazo` text DEFAULT NULL COMMENT 'Motivo escrito por quien despacha al rechazar; se envía al cliente por correo.',
  `nro_operacion_yape` varchar(50) NOT NULL DEFAULT '',
  `referencia_pago` varchar(64) DEFAULT NULL COMMENT 'orderId enviado a Izipay (PN-{id_pedido}). En pedidos antiguos, el PaymentIntent de Stripe.',
  `transaccion_uuid` varchar(40) DEFAULT NULL COMMENT 'UUID de la transaccion Izipay que quedo PAID, para cuadrar con el Back Office',
  `fecha_pago` datetime DEFAULT NULL COMMENT 'Se llena solo tras verificar en Izipay que existe una transaccion PAID',
  `fecha_preparado` datetime DEFAULT NULL,
  `fecha_entregado` datetime DEFAULT NULL,
  `fecha_expira` datetime DEFAULT NULL COMMENT 'Si estado=3 y fecha_expira < NOW(), el barrido libera el stock',
  `token_publico` char(32) NOT NULL DEFAULT '' COMMENT 'Token aleatorio exigido para ver la boleta pública',
  `estado` tinyint(1) NOT NULL DEFAULT 3 COMMENT '3=Pendiente de pago, 1=Pagado/Pendiente entrega, 5=Preparado, 2=Entregado, 0=Rechazado, 4=Expirado',
  `id_venta` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_venta` (`id_venta`),
  KEY `id_nivel` (`id_nivel`),
  KEY `id_grado` (`id_grado`),
  KEY `idx_referencia_pago` (`referencia_pago`),
  KEY `idx_estado_expira` (`estado`, `fecha_expira`),
  CONSTRAINT `pedidos_online_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `pedidos_online_ibfk_2` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `pedidos_online_ibfk_3` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_educativos` (`id_nivel`),
  CONSTRAINT `pedidos_online_ibfk_4` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id_grado`),
  CONSTRAINT `pedidos_online_ibfk_5` FOREIGN KEY (`id_cliente_facturacion`) REFERENCES `clientes` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detalle_pedidos_online` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_pedidos_online_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_online` (`id_pedido`),
  CONSTRAINT `detalle_pedidos_online_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- DATOS SEMILLA (SEED DATA)
-- =============================================================================

-- Roles
INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Vendedor');

-- Unidades de medida
INSERT INTO `unidades_medida` (`id_unidad`, `nombre`, `abreviatura`, `codigo_sunat`, `estado`) VALUES
(1, 'Unidad', 'Und', 'NIU', 1),
(2, 'Docena', 'Doc', 'DZN', 1);

-- Categorías NISSI
INSERT INTO `categorias` (`id_categoria`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Buzo (casaca + pantalón)', NULL, 1),
(2, 'General', NULL, 1),
(3, 'Camipolos', NULL, 1),
(4, 'Camisas', NULL, 1),
(5, 'Casacas educ.física', NULL, 1),
(6, 'Corbata', NULL, 1),
(9, 'Faldas', NULL, 1),
(10, 'Pantalonetas educ.física', NULL, 1),
(11, 'Pantalones de vestir', NULL, 1),
(12, 'Polo educ.física', NULL, 1),
(13, 'Shorts educ.física', NULL, 1),
(14, 'Short inicial', NULL, 1),
(15, 'MÓDULOS PRIMARIA BAJA', NULL, 1),
(16, 'MÓDULOS PRIMARIA ALTA', NULL, 1),
(17, 'MÓDULOS SECUNDARIA', NULL, 1);

-- Tallas (infantiles + adulto)
INSERT INTO `tallas` (`nombre`, `orden`) VALUES
('2',   1), ('4',   2), ('6',   3), ('8',   4),
('10',  5), ('12',  6), ('14',  7), ('16',  8),
('XS',  9), ('S',  10), ('M',  11), ('L',  12),
('XL', 13), ('XXL',14);

-- Tipos de corbata
INSERT INTO `tipos_corbata` (`nombre`) VALUES
('Elástica Pequeña'),
('Elástica Grande'),
('Nudo');

-- Niveles educativos
INSERT INTO `niveles_educativos` (`id_nivel`, `nombre`, `estado`) VALUES
(1, 'Inicial',     1),
(2, 'Primaria',    1),
(3, 'Secundaria',  1);

-- Grados
INSERT INTO `grados` (`id_nivel`, `nombre`, `estado`) VALUES
-- Inicial
(1, '3 años', 1), (1, '4 años', 1), (1, '5 años', 1),
-- Primaria
(2, '1°',     1), (2, '2°',     1), (2, '3°',     1),
(2, '4°',     1), (2, '5°',     1), (2, '6°',     1),
-- Secundaria
(3, '1°',     1), (3, '2°',     1), (3, '3°',     1),
(3, '4°',     1), (3, '5°',     1);

-- Áreas / Cursos
INSERT INTO `areas_cursos` (`nombre`, `estado`) VALUES
('Matemáticas',                1),
('Comunicación',               1),
('Ciencia y Tecnología (CTA)', 1),
('Historia y Geografía',       1),
('Personal Social',            1),
('Inglés',                     1),
('Educación Física',           1),
('Arte y Cultura',             1),
('Religión',                   1),
('Tutoría',                    1);

-- Bimestres
INSERT INTO `bimestres` (`nombre`) VALUES
('I Bimestre'),
('II Bimestre'),
('III Bimestre'),
('IV Bimestre');

-- Usuario administrador por defecto
-- Contraseña: password (hash bcrypt)
INSERT INTO `personas` (`id_persona`, `tipo_documento`, `numero_documento`, `nombres_razon_social`, `apellidos`, `estado`) VALUES
(1, 1, '00000001', 'Administrador', 'Sistema', 1);

INSERT INTO `usuarios` (`id_usuario`, `id_persona`, `id_rol`, `username`, `password`) VALUES
(1, 1, 1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- NOTA: Cambiar la contraseña tras el primer login.

-- Cliente "Público General"
INSERT INTO `personas` (`id_persona`, `tipo_documento`, `numero_documento`, `nombres_razon_social`, `estado`) VALUES
(2, 1, '00000000', 'Público General', 1);

INSERT INTO `clientes` (`id_cliente`, `id_persona`, `tipo_cliente`) VALUES
(1, 2, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN DEL SCRIPT
-- NISSI POS — Base de Datos v1.0
-- Tienda de Uniformes y Módulos Escolares
-- =============================================================================
