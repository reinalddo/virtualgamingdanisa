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
$telefono = trim($data['telefono']);
$contrasena = $data['contrasena'];

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    response(false, 'Correo electrónico inválido.');
}
if (strlen($contrasena) < 6) {
    response(false, 'La contraseña debe tener al menos 6 caracteres.');
}


// Guardar usuario en la base de datos MySQL
require_once __DIR__ . '/includes/db.php';

// Verificar si el correo ya existe en la BD
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
$stmt->execute([$correo]);
if ($stmt->fetch()) {
    response(false, 'El correo ya está registrado.');
}

$hash = password_hash($contrasena, PASSWORD_DEFAULT);
$rol = 'usuario';
$sql = 'INSERT INTO usuarios (username, password, nombre, email, rol, creado_en) VALUES (?, ?, ?, ?, ?, NOW())';
$username = $correo;
$stmt = $pdo->prepare($sql);
$ok = $stmt->execute([$username, $hash, $nombre, $correo, $rol]);
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
