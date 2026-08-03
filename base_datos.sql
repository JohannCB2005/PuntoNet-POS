-- =============================================================================
-- PUNTONET POS — Script de Base de Datos Completo
-- Compatible con InfinityFree (phpMyAdmin)
-- Reglas: sin CREATE DATABASE, sin USE, sin STORED PROCEDURES, sin DELIMITER
-- Charset: utf8mb4 | Engine: InnoDB
--
-- INSTRUCCIONES:
--   1. En phpMyAdmin, selecciona la base de datos (if0_42381931_puntonet_pos)
--   2. Ve a la pestaña "SQL" y pega este script completo
--   3. Ejecuta — las tablas se crearán en el orden correcto sin conflictos
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Eliminar tablas (en orden inverso a las FK para evitar errores)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `detalle_pedidos_online`;
DROP TABLE IF EXISTS `pedidos_online`;
DROP TABLE IF EXISTS `detalle_ventas`;
DROP TABLE IF EXISTS `vales`;
DROP TABLE IF EXISTS `ventas`;
DROP TABLE IF EXISTS `cajas`;
DROP TABLE IF EXISTS `trabajadores`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `insumos`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `unidades_medida`;
DROP TABLE IF EXISTS `dependencias`;
DROP TABLE IF EXISTS `facultades`;
DROP TABLE IF EXISTS `tipos_trabajador`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `personas`;

-- =============================================================================
-- TABLAS BASE (sin dependencias externas)
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

CREATE TABLE `tipos_trabajador` (
  `id_tipo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `dependencias` (
  `id_dependencia` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_dependencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `facultades` (
  `id_facultad` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_facultad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABLAS DE USUARIO Y ACCESO
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

-- =============================================================================
-- TABLAS DE INVENTARIO
-- =============================================================================

CREATE TABLE `insumos` (
  `id_insumo` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `id_unidad` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `costo_produccion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_piezas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `contenido_estandar` decimal(10,2) DEFAULT NULL COMMENT 'Peso estándar por pieza en Kg. NULL = peso variable (aves vivas).',
  `estado` tinyint(1) DEFAULT 1,
  `imagen` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_insumo`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_unidad` (`id_unidad`),
  CONSTRAINT `insumos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`),
  CONSTRAINT `insumos_ibfk_2` FOREIGN KEY (`id_unidad`) REFERENCES `unidades_medida` (`id_unidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABLAS DE CLIENTES Y TRABAJADORES (independientes)
-- =============================================================================

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `tipo_cliente` tinyint(1) DEFAULT 1 COMMENT '1=Regular, 2=Mayorista',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `id_persona` (`id_persona`),
  CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id_persona`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `trabajadores` (
  `id_trabajador` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `id_tipo_trabajador` int(11) NOT NULL,
  `id_dependencia` int(11) DEFAULT NULL,
  `codigo_planilla` varchar(20) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_trabajador`),
  UNIQUE KEY `id_persona` (`id_persona`),
  KEY `id_tipo_trabajador` (`id_tipo_trabajador`),
  KEY `id_dependencia` (`id_dependencia`),
  CONSTRAINT `trabajadores_ibfk_1` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id_persona`) ON DELETE CASCADE,
  CONSTRAINT `trabajadores_ibfk_2` FOREIGN KEY (`id_tipo_trabajador`) REFERENCES `tipos_trabajador` (`id_tipo`),
  CONSTRAINT `trabajadores_ibfk_3` FOREIGN KEY (`id_dependencia`) REFERENCES `dependencias` (`id_dependencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABLAS DE CAJAS Y VENTAS
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

CREATE TABLE `vales` (
  `id_vale` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `tipo_vale` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Monetario, 2=Especie (Kg de un producto)',
  `id_trabajador` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto en Soles (si tipo_vale=1)',
  `id_insumo_especie` int(11) DEFAULT NULL COMMENT 'Insumo cubierto (si tipo_vale=2)',
  `cantidad_especie` decimal(10,2) DEFAULT NULL COMMENT 'Kg o Unidades cubiertas (si tipo_vale=2)',
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` tinyint(1) DEFAULT 1 COMMENT '1=Vigente, 0=Canjeado/Anulado',
  `id_usuario_emisor` int(11) NOT NULL,
  `campana` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_vale`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `id_usuario_emisor` (`id_usuario_emisor`),
  KEY `fk_vale_trabajador` (`id_trabajador`),
  KEY `fk_vale_insumo` (`id_insumo_especie`),
  CONSTRAINT `fk_vale_insumo` FOREIGN KEY (`id_insumo_especie`) REFERENCES `insumos` (`id_insumo`),
  CONSTRAINT `fk_vale_trabajador` FOREIGN KEY (`id_trabajador`) REFERENCES `trabajadores` (`id_trabajador`),
  CONSTRAINT `vales_ibfk_2` FOREIGN KEY (`id_usuario_emisor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL COMMENT 'NULL si la venta es a un trabajador sin registro de cliente',
  `id_trabajador` int(11) DEFAULT NULL COMMENT 'Poblado si la venta es a un trabajador de la UNP',
  `tipo_comprobante` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Boleta, 2=Factura, 3=Ticket',
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Efectivo, 2=Planilla, 3=Vale, 4=Yape, 5=Mixto',
  `id_vale` int(11) DEFAULT NULL,
  `pago_efectivo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pago_vale` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` tinyint(1) DEFAULT 1 COMMENT '1=Activa, 0=Anulada',
  PRIMARY KEY (`id_venta`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_cliente` (`id_cliente`),
  KEY `fk_venta_vale` (`id_vale`),
  KEY `fk_venta_trabajador` (`id_trabajador`),
  CONSTRAINT `fk_venta_trabajador` FOREIGN KEY (`id_trabajador`) REFERENCES `trabajadores` (`id_trabajador`),
  CONSTRAINT `fk_venta_vale` FOREIGN KEY (`id_vale`) REFERENCES `vales` (`id_vale`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detalle_ventas` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL,
  `piezas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `peso_neto` decimal(10,2) NOT NULL DEFAULT 0.00,
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
-- TABLAS DE E-COMMERCE (Pedidos Online)
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
  `peso_neto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_insumo` (`id_insumo`),
  CONSTRAINT `detalle_pedidos_online_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_online` (`id_pedido`),
  CONSTRAINT `detalle_pedidos_online_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id_insumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- DATOS BÁSICOS INICIALES (seed data)
-- =============================================================================

-- Roles del sistema
INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Vendedor');

-- Tipos de trabajador UNP
INSERT INTO `tipos_trabajador` (`id_tipo`, `nombre`, `estado`) VALUES
(1, 'Docente/Nombrado', 1),
(2, 'Personal CAS', 1),
(3, 'Administrativo', 1),
(4, 'Obrero', 1),
(5, 'Contratado', 1);

-- Unidades de medida
INSERT INTO `unidades_medida` (`id_unidad`, `nombre`, `abreviatura`, `estado`) VALUES
(1, 'Kilogramo', 'Kg', 1),
(2, 'Unidad', 'Und', 1),
(3, 'Litro', 'Lt', 1),
(4, 'Gramo', 'gr', 1),
(5, 'Docena', 'Doc', 1),
(6, 'Caja', 'Cja', 1);

-- Categorías de productos
INSERT INTO `categorias` (`id_categoria`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Aves', 'Pavos, pollos y aves de corral', 1);

-- Usuario administrador por defecto
-- Contraseña: admin123 (hash bcrypt)
INSERT INTO `personas` (`id_persona`, `tipo_documento`, `numero_documento`, `nombres_razon_social`, `apellidos`, `estado`) VALUES
(1, 1, '00000001', 'Administrador', 'Sistema', 1);

INSERT INTO `usuarios` (`id_usuario`, `id_persona`, `id_rol`, `username`, `password`) VALUES
(1, 1, 1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- NOTA: La contraseña por defecto es "password". Cámbiala después del primer login.

-- Cliente "Público General" (para ventas sin cliente registrado)
INSERT INTO `personas` (`id_persona`, `tipo_documento`, `numero_documento`, `nombres_razon_social`, `estado`) VALUES
(2, 1, '00000000', 'Público General', 1);

INSERT INTO `clientes` (`id_cliente`, `id_persona`, `tipo_cliente`) VALUES
(1, 2, 1);

-- Insumo de ejemplo: Pavo Vivo a granel
INSERT INTO `insumos` (`id_insumo`, `id_categoria`, `id_unidad`, `nombre`, `precio_unitario`, `costo_produccion`, `stock_piezas`, `contenido_estandar`, `estado`) VALUES
(1, 1, 1, 'Pavo Vivo a granel', 15.00, 10.00, 0.00, NULL, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN DEL SCRIPT
-- Versión: 2026-07-11
-- Para producción InfinityFree:
--   - BD: if0_42381931_puntonet_pos
--   - Ejecutar desde phpMyAdmin → pestaña SQL
-- =============================================================================
