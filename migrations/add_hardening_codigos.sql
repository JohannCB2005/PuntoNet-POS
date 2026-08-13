-- migrations/add_hardening_codigos.sql
--
-- Contador de intentos para los códigos de 6 dígitos de la tienda (verificación
-- de correo y reset de contraseña).
--
-- Por qué en BD y no en $_SESSION: el atacante controla su propia cookie, así que
-- un contador de sesión (como el de login) se evade borrándola. El límite tiene
-- que vivir del lado del dato que protege, no del cliente.
--
-- Sin esto, un código de 6 dígitos con ventana de 15/30 minutos y reintentos
-- ilimitados se agota por fuerza bruta y entrega la cuenta.

ALTER TABLE `clientes`
  ADD COLUMN `codigo_verificacion_intentos` tinyint(4) NOT NULL DEFAULT 0
      COMMENT 'Intentos fallidos del código de verificación; a los 5 el código se invalida',
  ADD COLUMN `codigo_reset_intentos` tinyint(4) NOT NULL DEFAULT 0
      COMMENT 'Intentos fallidos del código de reset; a los 5 el código se invalida';
