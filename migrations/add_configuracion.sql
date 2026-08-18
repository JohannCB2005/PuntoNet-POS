-- =============================================================================
-- PuntoNet POS — Migración: Configuración de la Tienda (panel administrable)
-- Fecha: 2026-08
-- Descripción:
--   Tabla clave→valor que centraliza la configuración del negocio (datos de la
--   empresa, RUC, logos, entorno SUNAT, credenciales SOAP, Consulta CPE, guías
--   electrónicas, SIRE, Qz Tray, PSE, pagos) para poder editarla desde la UI
--   en vez de tocar .env a mano.
--
--   La tabla empieza vacía a propósito: el cargador config/settings.php devuelve
--   como fallback los valores del .env, así el panel muestra los datos actuales
--   sin migrar data y se sobrescriben solo cuando el administrador guarda.
--
--   Solo necesaria si tu base de datos ya existía sin esta tabla. En una
--   instalación nueva, base_datos_nissi.sql ya la incluye.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave` varchar(60) NOT NULL,
  `valor` text DEFAULT NULL,
  `grupo` varchar(40) NOT NULL DEFAULT 'empresa',
  `tipo` varchar(20) NOT NULL DEFAULT 'texto',
  `etiqueta` varchar(120) DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;