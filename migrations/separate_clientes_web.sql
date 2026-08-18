-- Migración: separar cuentas de tienda online (clientes_web) de los clientes POS
-- (personas + clientes, validados con RENIEC desde el módulo de ventas).
--
-- Antes: el registro web escribía en personas/clientes (sobrescribía nombres y
-- permitía cambiar email/password de una cuenta ajena). Ahora la cuenta web vive
-- en clientes_web (autocontenida) y NUNCA toca personas/clientes.
--
-- La FK id_cliente de pedidos_online pasa a apuntar a clientes_web (renombrada a
-- id_cliente_web). id_cliente_facturacion SIGUE en clientes: son entidades RUC
-- resueltas con SUNAT, no cuentas web.

CREATE TABLE IF NOT EXISTS `clientes_web` (
  `id_cliente_web` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `codigo_verificacion` varchar(10) DEFAULT NULL,
  `codigo_verificacion_expira` datetime DEFAULT NULL,
  `codigo_verificacion_intentos` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Intentos fallidos del código de verificación; a los 5 el código se invalida',
  `codigo_reset` varchar(10) DEFAULT NULL,
  `codigo_reset_expira` datetime DEFAULT NULL,
  `codigo_reset_intentos` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Intentos fallidos del código de reset; a los 5 el código se invalida',
  `numero_documento` varchar(15) DEFAULT NULL,
  `nombres_razon_social` varchar(150) DEFAULT NULL,
  `apellidos` varchar(100) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_cliente_web`),
  UNIQUE KEY `uq_cliente_web_email` (`email`),
  KEY `idx_cliente_web_dni` (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Migrar las cuentas web existentes (id_cliente 4, 5, 15, 16) ────────────────
-- Se mantiene el MISMO id numérico para no tocar los 21 pedidos existentes:
-- pedidos_online.id_cliente 4 y 16 seguirán apuntando a los mismos números,
-- solo que ahora en clientes_web.
INSERT INTO `clientes_web`
    (id_cliente_web, email, password, email_verificado,
     codigo_verificacion, codigo_verificacion_expira, codigo_verificacion_intentos,
     codigo_reset, codigo_reset_expira, codigo_reset_intentos,
     numero_documento, nombres_razon_social, apellidos, telefono, direccion, fecha_registro, estado)
SELECT c.id_cliente,
       c.email,
       c.password,
       c.email_verificado,
       c.codigo_verificacion,
       c.codigo_verificacion_expira,
       c.codigo_verificacion_intentos,
       c.codigo_reset,
       c.codigo_reset_expira,
       c.codigo_reset_intentos,
       p.numero_documento,
       p.nombres_razon_social,
       p.apellidos,
       p.telefono,
       p.direccion,
       c.fecha_registro,
       p.estado
FROM clientes c
INNER JOIN personas p ON c.id_persona = p.id_persona
WHERE c.id_cliente IN (4, 5, 15, 16)
  AND c.email IS NOT NULL;

-- ── Reapuntar pedidos_online.id_cliente a clientes_web ────────────────────────
ALTER TABLE `pedidos_online` DROP FOREIGN KEY `pedidos_online_ibfk_1`;
ALTER TABLE `pedidos_online` CHANGE COLUMN `id_cliente` `id_cliente_web` int(11) NOT NULL;
ALTER TABLE `pedidos_online` ADD CONSTRAINT `pedidos_online_ibfk_1`
    FOREIGN KEY (`id_cliente_web`) REFERENCES `clientes_web` (`id_cliente_web`);