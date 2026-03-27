<?php
$conn = new mysqli('localhost', 'root', '', 'virtualgaming');
$conn->set_charset('utf8mb4');
$conn->query("UPDATE juego_paquetes SET activo = IF(COALESCE(activo, 1) = 1, 0, 1) WHERE id = 1 AND juego_id = 3");
echo 'affected=' . $conn->affected_rows . PHP_EOL;
$res = $conn->query("SELECT id,juego_id,nombre,activo,orden FROM juego_paquetes WHERE id=1");
echo json_encode($res->fetch_assoc(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
