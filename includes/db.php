<?php
// Conexión PDO para VirtualGaming
/*
$host = 'localhost';
$db   = 'u680460687_vgaming';
$user = 'u680460687_vgaming';
$pass = 'LnGxQW:b0Y';
$charset = 'utf8mb4';
*/
$host = 'localhost';
$db   = 'virtualgaming';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
