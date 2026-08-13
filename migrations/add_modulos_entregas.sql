-- =============================================================================
-- PuntoNet POS — Migración: Entregas de Módulos escolares (back-office)
-- Fecha: 2026-08
-- Descripción:
--   Incorpora el back-office del sistema "Módulos IEP Divino Redentor"
--   (sistema hermano en /Proyectos personales/Modulos) dentro del POS:
--   padrón de alumnos, importación de comprobantes de pago (cubicol),
--   conciliación, promociones "Módulo del Bimestre" y registro de entregas
--   físicas de módulos con descuento de stock vinculado al inventario.
--
--   Decisiones:
--   - Un solo colegio por ahora (no hay dimensión multi-colegio).
--   - Se REUTILIZAN niveles_educativos/grados, productos, usuarios,
--     personas e intentos_login de NISSI (misma BD puntonet_pos).
--   - La sección NO participa en la asignación de productos: el detalle de la
--     promoción se define por (nivel, grado).
--   - detalle_promocion_productos: qué productos (y cuánto) lleva cada
--     nivel/grado en una promoción. Sirve de plantilla para el siguiente
--     bimestre y de insumo para descontar stock al entregar.
--   - detalle_entregas: fotografía de los productos entregados a un alumno
--     (permite auditoría aunque la promoción cambie después).
--   - La entrega por promoción solo mueve stock vía kardex_movimientos
--     (salida) — NO crea venta contable (no toca caja/comisiones/SUNAT).
--
--   Solo necesaria si tu base de datos ya existía sin estas tablas.
--   En una instalación nueva, base_datos_nissi.sql ya las incluye.
-- =============================================================================

-- 1. Padrón de alumnos.
CREATE TABLE `alumnos` (
  `id_alumno` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL COMMENT 'Código cubicol, ej. A20230349 — clave natural del upsert',
  `nombre_completo` varchar(200) NOT NULL COMMENT 'Literal del padrón: "APELLIDOS, Nombres"',
  `nombre_normalizado` varchar(200) NOT NULL COMMENT 'Sin comas ni tildes, mayúsculas — llave del cruce',
  `numero_documento` varchar(15) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` char(1) DEFAULT NULL COMMENT 'M | F',
  `id_nivel` int(11) DEFAULT NULL,
  `id_grado` int(11) DEFAULT NULL,
  `seccion` varchar(5) DEFAULT NULL COMMENT 'Solo informativa: la asignación de productos es por nivel/grado',
  `matriculado` tinyint(1) NOT NULL DEFAULT 1,
  `pension_pactada` decimal(10,2) DEFAULT NULL COMMENT 'Monto que este alumno paga habitualmente',
  `pension_origen` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Auto-detectada, 2=Fijada a mano, 3=Ambiguo (revisar)',
  `id_importacion` int(11) DEFAULT NULL,
  `origen` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Importado, 2=Alta manual',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_alumno`),
  UNIQUE KEY `uk_alumno_codigo` (`codigo`),
  KEY `idx_alumno_normalizado` (`nombre_normalizado`),
  KEY `id_nivel` (`id_nivel`),
  KEY `id_grado` (`id_grado`),
  CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_educativos` (`id_nivel`),
  CONSTRAINT `alumnos_ibfk_2` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id_grado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Registro de importaciones (padrón o comprobantes) + resumen.
CREATE TABLE `importaciones` (
  `id_importacion` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` tinyint(1) NOT NULL COMMENT '1=Padrón de alumnos, 2=Comprobantes de pago',
  `nombre_archivo` varchar(255) NOT NULL,
  `hash_archivo` char(64) DEFAULT NULL COMMENT 'sha256 del archivo; avisa "este archivo ya se procesó"',
  `hoja` varchar(100) DEFAULT NULL,
  `mes_filtro` tinyint(2) DEFAULT NULL,
  `anio_filtro` smallint(4) DEFAULT NULL,
  `filas_leidas` int(11) NOT NULL DEFAULT 0,
  `nuevos` int(11) NOT NULL DEFAULT 0,
  `actualizados` int(11) NOT NULL DEFAULT 0,
  `sin_cambios` int(11) NOT NULL DEFAULT 0,
  `omitidos` int(11) NOT NULL DEFAULT 0,
  `sin_cruce` int(11) NOT NULL DEFAULT 0 COMMENT 'Solo tipo 2: pagos que no encontraron alumno',
  `id_usuario` int(11) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_importacion`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `importaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Comprobantes de pago de pensión. id_alumno NULL = bandeja de conciliación.
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_alumno` int(11) DEFAULT NULL COMMENT 'NULL = pago sin cruzar. Esta columna ES la bandeja de conciliación',
  `tipo_comprobante` varchar(20) NOT NULL DEFAULT 'BOLETA',
  `serie` varchar(10) NOT NULL DEFAULT '',
  `numero` varchar(20) NOT NULL COMMENT 'N° de boleta COMO TEXTO: 0008425 conserva los ceros',
  `nombre_comprobante` varchar(200) NOT NULL COMMENT '"APELLIDOS Nombres" tal cual viene, sin coma',
  `nombre_normalizado` varchar(200) NOT NULL,
  `concepto` varchar(150) NOT NULL COMMENT 'Literal: Pensión - Agosto - 2026',
  `concepto_clave` varchar(30) NOT NULL COMMENT 'Derivado: PENSION-08-2026. Parte de la clave única',
  `mes_concepto` tinyint(2) NOT NULL,
  `anio_concepto` smallint(4) NOT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `numero_operacion` varchar(50) DEFAULT NULL,
  `observacion` varchar(100) DEFAULT NULL COMMENT 'Medio de pago: YAPE, BCP, CAJA PIURA…',
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mora` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_cruce` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Sin cruzar, 1=Exacto por nombre, 2=Conciliado a mano, 3=Alta manual',
  `id_importacion` int(11) DEFAULT NULL,
  `origen` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Importado, 2=Alta manual',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_pago`),
  UNIQUE KEY `uk_pago_comprobante` (`tipo_comprobante`,`serie`,`numero`,`concepto_clave`),
  KEY `idx_pago_periodo` (`anio_concepto`,`mes_concepto`,`id_alumno`),
  KEY `idx_pago_normalizado` (`nombre_normalizado`),
  KEY `id_alumno` (`id_alumno`),
  KEY `id_importacion` (`id_importacion`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE SET NULL,
  CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`id_importacion`) REFERENCES `importaciones` (`id_importacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Historial de conciliación manual (asignar/descartar/desasignar).
CREATE TABLE `conciliaciones_pago` (
  `id_conciliacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_pago` int(11) NOT NULL,
  `id_alumno` int(11) DEFAULT NULL COMMENT 'NULL cuando la acción fue descartar o desasignar',
  `accion` tinyint(1) NOT NULL COMMENT '1=Asignado, 2=Descartado, 3=Desasignado',
  `motivo` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_conciliacion`),
  KEY `id_pago` (`id_pago`),
  KEY `id_alumno` (`id_alumno`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `conciliaciones_pago_ibfk_1` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id_pago`) ON DELETE CASCADE,
  CONSTRAINT `conciliaciones_pago_ibfk_2` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`),
  CONSTRAINT `conciliaciones_pago_ibfk_3` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Promociones: criterios de elegibilidad ("Módulo del Bimestre a cambio de pensión").
CREATE TABLE `promociones` (
  `id_promocion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL COMMENT 'Ej. Módulo 3er Bimestre',
  `bimestre` tinyint(1) NOT NULL COMMENT '1..4',
  `mes_requerido` tinyint(2) NOT NULL COMMENT '1=Enero … 12=Diciembre',
  `anio_requerido` smallint(4) NOT NULL,
  `monto_minimo` decimal(10,2) DEFAULT NULL COMMENT 'NULL = cualquier neto positivo de pensión califica',
  `exige_pension_completa` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = exige que el neto del mes cubra la pensión pactada del alumno',
  `fecha_limite_pago` date DEFAULT NULL COMMENT 'NULL = sin límite. Si se fija, solo cuentan pagos con fecha_pago <= valor',
  `exige_matriculado` tinyint(1) NOT NULL DEFAULT 1,
  `exige_neto_positivo` tinyint(1) NOT NULL DEFAULT 1,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `id_usuario` int(11) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_promocion`),
  UNIQUE KEY `uk_promocion_periodo` (`anio_requerido`,`bimestre`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `promociones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Detalle de productos por (nivel, grado) de cada promoción.
--    "Qué lleva cada alumno" — la sección NO se toma en cuenta.
--    Sirve de plantilla para el siguiente bimestre (botón copiar) y de
--    insumo para descontar stock al confirmar una entrega.
CREATE TABLE `detalle_promocion_productos` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_promocion` int(11) NOT NULL,
  `id_nivel` int(11) NOT NULL,
  `id_grado` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id_detalle`),
  UNIQUE KEY `uk_promo_nivel_grado_producto` (`id_promocion`,`id_nivel`,`id_grado`,`id_producto`),
  KEY `id_promocion` (`id_promocion`),
  KEY `id_nivel` (`id_nivel`),
  KEY `id_grado` (`id_grado`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_promocion_productos_ibfk_1` FOREIGN KEY (`id_promocion`) REFERENCES `promociones` (`id_promocion`) ON DELETE CASCADE,
  CONSTRAINT `detalle_promocion_productos_ibfk_2` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_educativos` (`id_nivel`),
  CONSTRAINT `detalle_promocion_productos_ibfk_3` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id_grado`),
  CONSTRAINT `detalle_promocion_productos_ibfk_4` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Entregas: quién recibió el módulo de una promoción.
--    La UK (id_promocion, id_alumno) es la única protección real contra la
--    doble entrega concurrente (ver M_Entrega::registrar).
CREATE TABLE `entregas` (
  `id_entrega` int(11) NOT NULL AUTO_INCREMENT,
  `id_promocion` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `id_pago` int(11) DEFAULT NULL COMMENT 'Boleta que habilitó la entrega, congelada al momento de entregar',
  `dni_receptor` varchar(8) DEFAULT NULL COMMENT 'NULL cuando origen_datos=3 (entregado en colegio al propio alumno)',
  `nombre_receptor` varchar(150) NOT NULL COMMENT 'Apellidos y nombres del que recoge',
  `parentesco` varchar(50) DEFAULT NULL,
  `origen_datos` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=RENIEC vía apiperu, 2=Digitado a mano, 3=Entregado en colegio al propio alumno (sin DNI)',
  `observacion` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Quién atendió la entrega',
  `fecha_entrega` datetime DEFAULT current_timestamp(),
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Entregado, 0=Anulado',
  PRIMARY KEY (`id_entrega`),
  UNIQUE KEY `uk_entrega_promocion_alumno` (`id_promocion`,`id_alumno`),
  KEY `id_alumno` (`id_alumno`),
  KEY `id_pago` (`id_pago`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`id_promocion`) REFERENCES `promociones` (`id_promocion`),
  CONSTRAINT `entregas_ibfk_2` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`),
  CONSTRAINT `entregas_ibfk_3` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id_pago`) ON DELETE SET NULL,
  CONSTRAINT `entregas_ibfk_4` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Detalle de una entrega: los productos realmente entregados (foto).
--    Cada fila genera una salida de kardex (referencia ENT-<id_entrega>).
CREATE TABLE `detalle_entregas` (
  `id_detalle_entrega` int(11) NOT NULL AUTO_INCREMENT,
  `id_entrega` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id_detalle_entrega`),
  KEY `id_entrega` (`id_entrega`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_entregas_ibfk_1` FOREIGN KEY (`id_entrega`) REFERENCES `entregas` (`id_entrega`) ON DELETE CASCADE,
  CONSTRAINT `detalle_entregas_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;