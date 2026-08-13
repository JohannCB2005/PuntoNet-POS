-- migrations/add_usuarios_estado.sql
--
-- Estado propio de la cuenta de staff, independiente de personas.estado.
--
-- Hasta ahora "dar de baja a un usuario" apagaba personas.estado, que es la misma
-- fila que usa la tienda para las cuentas de cliente (M_Cliente filtra por
-- p.estado = 1). Consecuencias, ambas reales:
--
--   1. Dar de baja a un trabajador que además compra en la tienda le borraba de
--      hecho su cuenta de cliente y sus pedidos dejaban de ser visibles.
--   2. Al revés: M_Cliente::registrarCuenta() hace UPDATE personas SET estado=1,
--      así que un ex-trabajador podía REACTIVAR su acceso al panel simplemente
--      registrándose como cliente en la tienda.
--
-- Una persona puede ser trabajador y cliente a la vez; son dos vínculos distintos
-- con la misma persona y necesitan estados distintos.
--
-- Backfill: se copia el estado actual de la persona para no resucitar usuarios que
-- hoy están dados de baja.

ALTER TABLE `usuarios`
  ADD COLUMN `estado` tinyint(1) NOT NULL DEFAULT 1
      COMMENT '1=Activo, 0=Dado de baja. Independiente de personas.estado (cuenta de cliente)';

UPDATE `usuarios` u
  INNER JOIN `personas` p ON u.id_persona = p.id_persona
  SET u.estado = COALESCE(p.estado, 1);
