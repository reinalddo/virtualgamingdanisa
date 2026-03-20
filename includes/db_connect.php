<?php
// Conexión a la base de datos para todos los módulos
$mysqli = new mysqli('localhost', 'u680460687_vgaming', 'LnGxQW:b0Y', 'u680460687_vgaming');
if ($mysqli->connect_errno) {
    die('Error de conexión: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
