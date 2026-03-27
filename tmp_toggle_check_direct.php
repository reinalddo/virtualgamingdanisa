<?php
$_SERVER['HTTP_HOST'] = 'virtualgaming';
require 'c:/wamp64/www/proyectosgemini/virtualgaming/includes/db_connect.php';
$res = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'activo'");
echo json_encode($res->fetch_assoc(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
$res2 = $mysqli->query("SELECT id,juego_id,nombre,activo,orden FROM juego_paquetes WHERE id=6");
echo json_encode($res2->fetch_assoc(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
$mysqli->query("UPDATE juego_paquetes SET activo = IF(COALESCE(activo, 1) = 1, 0, 1) WHERE id = 6 AND juego_id = 3");
echo 'affected=' . $mysqli->affected_rows . PHP_EOL;
$res3 = $mysqli->query("SELECT id,juego_id,nombre,activo,orden FROM juego_paquetes WHERE id=6");
echo json_encode($res3->fetch_assoc(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
