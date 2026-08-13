-- migrations/add_intentos_login.sql
--
-- Control de fuerza bruta de login persistido en BD, no en $_SESSION.
--
-- El contador de sesión que había (C_Login.php y C_ClienteAuth.php) no protege de
-- nada: vive en una cookie que el atacante controla, así que basta con descartarla
-- en cada intento para tener reintentos infinitos. Un navegador normal sí la
-- conserva, de ahí que pareciera funcionar.
--
-- Se indexa por (ambito, identificador) — el usuario/correo tecleado — que es lo que
-- hay que proteger. Contrapartida conocida y aceptada: alguien puede provocar el
-- bloqueo de 10 minutos de una cuenta ajena tecleando mal su contraseña a propósito;
-- la ventana es corta y se limpia sola, que es justo el comportamiento que ya tenía.
--
-- El identificador se guarda tal como se tecleó (sin verificar que exista) para no
-- filtrar qué usuarios son reales.

CREATE TABLE IF NOT EXISTS `intentos_login` (
  `id_intento` int(11) NOT NULL AUTO_INCREMENT,
  `ambito` varchar(10) NOT NULL COMMENT 'staff | cliente — las dos sesiones son independientes',
  `identificador` varchar(150) NOT NULL COMMENT 'username (staff) o email (cliente), tal como se tecleó',
  `intentos` int(11) NOT NULL DEFAULT 0,
  `ultimo_intento` datetime NOT NULL,
  PRIMARY KEY (`id_intento`),
  UNIQUE KEY `uk_ambito_identificador` (`ambito`, `identificador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
