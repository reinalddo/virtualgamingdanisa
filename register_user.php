<?php
header('Content-Type: application/json');

// Detectar el tenant según el dominio
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$tenant = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($host));
$usersFile = __DIR__ . "/tenants/{$tenant}/users.json";

// Recibe los datos del POST
$data = json_decode(file_get_contents('php://input'), true);

// Validación básica
defaultResponse();
if (!isset($data['nombre']) || !isset($data['correo']) || !isset($data['telefono']) || !isset($data['contrasena'])) {
    response(false, 'Faltan datos requeridos.');
}

$nombre = trim($data['nombre']);
$correo = strtolower(trim($data['correo']));
$telefono = substr(trim($data['telefono']), 0, 50);
$contrasena = $data['contrasena'];

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    response(false, 'Correo electrónico inválido.');
}
if (strlen($contrasena) < 6) {
    response(false, 'La contraseña debe tener al menos 6 caracteres.');
}


// Guardar usuario en la base de datos MySQL
require_once __DIR__ . '/includes/db.php';

function register_users_has_phone_column(PDO $connection): bool {
    if (function_exists('users_has_phone_column_pdo')) {
        return users_has_phone_column_pdo($connection);
    }

    try {
        $result = $connection->query("SHOW COLUMNS FROM usuarios LIKE 'telefono'");
        return $result instanceof PDOStatement && (bool) $result->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
}

$hasPhoneColumn = register_users_has_phone_column($pdo);

// Verificar si el correo ya existe en la BD
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
$stmt->execute([$correo]);
if ($stmt->fetch()) {
    response(false, 'El correo ya está registrado.');
}

$hash = password_hash($contrasena, PASSWORD_DEFAULT);
$rol = 'usuario';
$username = $correo;
if ($hasPhoneColumn) {
    $sql = 'INSERT INTO usuarios (username, password, nombre, email, telefono, rol, creado_en) VALUES (?, ?, ?, ?, ?, ?, NOW())';
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([$username, $hash, $nombre, $correo, $telefono, $rol]);
} else {
    $sql = 'INSERT INTO usuarios (username, password, nombre, email, rol, creado_en) VALUES (?, ?, ?, ?, ?, NOW())';
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([$username, $hash, $nombre, $correo, $rol]);
}
if ($ok) {
    response(true, 'Usuario registrado correctamente.');
} else {
    response(false, 'Error al guardar el usuario en la base de datos.');
}

function response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
function defaultResponse() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        response(false, 'Método no permitido.');
    }
}
