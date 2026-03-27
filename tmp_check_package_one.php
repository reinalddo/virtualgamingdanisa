<?php
$_SERVER['HTTP_HOST'] = 'virtualgaming';
require 'c:/wamp64/www/proyectosgemini/virtualgaming/includes/db_connect.php';
$res = $mysqli->query("SELECT id,juego_id,nombre,activo,orden FROM juego_paquetes WHERE id=6");
echo json_encode($res->fetch_assoc(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
