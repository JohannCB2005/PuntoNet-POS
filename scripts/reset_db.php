<?php
$host = 'localhost';
$dbname = 'puntonet_pos';
$user = 'puntonet_user';
$pass = 'puntonet2026';

try {
    $db = new PDO("mysql:host=$host", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("DROP DATABASE IF EXISTS $dbname");
    $db->exec("CREATE DATABASE $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database dropped and recreated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
