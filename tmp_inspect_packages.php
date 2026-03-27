<?php
$conn = new mysqli('localhost', 'root', '', 'virtualgaming');
$conn->set_charset('utf8mb4');
$res = $conn->query("SELECT id,juego_id,nombre,activo,orden FROM juego_paquetes WHERE juego_id=3 ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}
