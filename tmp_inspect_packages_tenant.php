<?php
$_SERVER['HTTP_HOST'] = 'virtualgaming';
require 'c:/wamp64/www/proyectosgemini/virtualgaming/includes/db_connect.php';
$res = $mysqli->query("SELECT id,juego_id,nombre,activo,orden FROM juego_paquetes WHERE juego_id=3 ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}
