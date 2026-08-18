<?php
require_once dirname(__DIR__) . '/config/sesion_segura.php';
session_start();
session_unset();
session_destroy();
header('Location: /login');
exit;
?>
