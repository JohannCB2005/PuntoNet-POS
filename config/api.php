<?php
// Configuración de API externa
// El token real vive en .env (nunca versionado); aquí solo queda un placeholder de respaldo.
$_envFile = dirname(__DIR__) . '/.env';
$_env     = file_exists($_envFile) ? parse_ini_file($_envFile) : [];

return [
    'apiperu_token' => $_env['APIPERU_TOKEN'] ?? ''
];
