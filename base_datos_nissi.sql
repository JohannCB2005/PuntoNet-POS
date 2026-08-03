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
DROP TABLE IF EXISTS `insumos`;
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
-- INVENTARIO: INSUMOS / PRODUCTOS NISSI
-- =============================================================================

CREATE TABLE `insumos` (
  `id_insumo` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `id_unidad` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `costo_produccion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_piezas` decimal(10,2) NOT NULL DEFAULT 0.00,
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
  PRIMARY KEY (`id_insumo`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_unidad` (`id_unidad`),
  KEY `id_talla` (`id_talla`),
  KEY `id_tipo_corbata` (`id_tipo_corbata`),
  KEY `id_nivel` (`id_nivel`),
  KEY `id_grado` (`id_grado`),
  KEY `id_area` (`id_area`),
  KEY `id_bimestre` (`id_bimestre`),
  KEY `idx_producto_padre` (`id_producto_padre`),
  CONSTRAINT `insumos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`),
  CONSTRAINT `insumos_ibfk_2` FOREIGN KEY (`id_unidad`) REFERENCES `unidades_medida` (`id_unidad`),
  CONSTRAINT `fk_insumo_talla` FOREIGN KEY (`id_talla`) REFERENCES `tallas` (`id_talla`),
  CONSTRAINT `fk_insumo_tipo_corbata` FOREIGN KEY (`id_tipo_corbata`) REFERENCES `tipos_corbata` (`id_tipo_corbata`),
  CONSTRAINT `fk_insumo_nivel` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_educativos` (`id_nivel`),
  CONSTRAINT `fk_insumo_grado` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id_grado`),
  CONSTRAINT `fk_insumo_area` FOREIGN KEY (`id_area`) REFERENCES `areas_cursos` (`id_area`),
  CONSTRAINT `fk_insumo_bimestre` FOREIGN KEY (`id_bimestre`) REFERENCES `bimestres` (`id_bimestre`),
  CONSTRAINT `fk_producto_padre` FOREIGN KEY (`id_producto_padre`) REFERENCES `insumos` (`id_insumo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- USUARIOS Y CLIENTES
-- =============================================================================

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
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
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `id_persona` (`id_persona`),
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
  `fecha_cierre` datetime DEFAULT NULL,
  `total_ventas` decimal(10,2) DEFAULT NULL,
  `num_ventas` int(11) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Abierta, 0=Cerrada',
  PRIMARY KEY (`id_caja`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `cajas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL COMMENT 'NULL = Público General',
  `tipo_comprobante` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Boleta, 2=Factura, 3=Ticket',
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Efectivo, 2=Yape/Plin, 3=Tarjeta, 4=Mixto',
  `pago_efectivo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` tinyint(1) DEFAULT 1 COMMENT '1=Activa, 0=Anulada',
  PRIMARY KEY (`id_venta`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_cliente` (`id_cliente`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detalle_ventas` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Número de piezas/unidades',
  `precio_venta` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_venta` (`id_venta`),
  KEY `id_insumo` (`id_insumo`),
  CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE,
  CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id_insumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- PEDIDOS ONLINE (E-Commerce NISSI)
-- =============================================================================

CREATE TABLE `pedidos_online` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `fecha_pedido` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `nro_operacion_yape` varchar(50) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Pendiente, 2=Entregado, 0=Rechazado',
  `id_venta` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_venta` (`id_venta`),
  CONSTRAINT `pedidos_online_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `pedidos_online_ibfk_2` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detalle_pedidos_online` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_insumo` (`id_insumo`),
  CONSTRAINT `detalle_pedidos_online_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_online` (`id_pedido`),
  CONSTRAINT `detalle_pedidos_online_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id_insumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- DATOS SEMILLA (SEED DATA)
-- =============================================================================

-- Roles
INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Vendedor');

-- Unidades de medida
INSERT INTO `unidades_medida` (`id_unidad`, `nombre`, `abreviatura`, `estado`) VALUES
(1, 'Unidad', 'Und', 1),
(2, 'Docena', 'Doc', 1);

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
