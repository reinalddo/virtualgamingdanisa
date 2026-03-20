<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/currency.php';

currency_ensure_schema();

function account_json_error(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function account_json_ok(array $payload = []): void {
    echo json_encode(array_merge(['ok' => true], $payload));
    exit;
}

function account_ensure_user_session(): array {
    $user = $_SESSION['auth_user'] ?? null;
    if (!is_array($user) || empty($user['id'])) {
        account_json_error('Debes iniciar sesión para continuar.', 401);
    }
    return $user;
}

function account_pedidos_has_owner_column(mysqli $mysqli): bool {
    $result = $mysqli->query("SHOW COLUMNS FROM pedidos LIKE 'cliente_usuario_id'");
    return $result && $result->num_rows > 0;
}

function account_pedidos_table_exists(mysqli $mysqli): bool {
    $result = $mysqli->query("SHOW TABLES LIKE 'pedidos'");
    return $result && $result->num_rows > 0;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === '') {
    account_json_error('Acción no especificada.', 422);
}

$authUser = account_ensure_user_session();
$authUserId = (int) ($authUser['id'] ?? 0);
$authUserEmail = trim((string) ($authUser['email'] ?? ''));

if ($action === 'orders') {
    $orders = [];
    if (!account_pedidos_table_exists($mysqli)) {
        account_json_ok(['orders' => []]);
    }
    $hasOwnerColumn = account_pedidos_has_owner_column($mysqli);

    if ($hasOwnerColumn) {
        $stmt = $mysqli->prepare(
            "SELECT id, juego_nombre, paquete_nombre, paquete_cantidad, moneda, precio, email, estado, creado_en
             FROM pedidos
             WHERE cliente_usuario_id = ?
                OR (cliente_usuario_id IS NULL AND email = ?)
             ORDER BY creado_en DESC, id DESC"
        );
        if (!$stmt) {
            account_json_error('No se pudo consultar el historial de pedidos.', 500);
        }
        $stmt->bind_param('is', $authUserId, $authUserEmail);
    } else {
        $stmt = $mysqli->prepare(
            "SELECT id, juego_nombre, paquete_nombre, paquete_cantidad, moneda, precio, email, estado, creado_en
             FROM pedidos
             WHERE email = ?
             ORDER BY creado_en DESC, id DESC"
        );
        if (!$stmt) {
            account_json_error('No se pudo consultar el historial de pedidos.', 500);
        }
        $stmt->bind_param('s', $authUserEmail);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = [
                'id' => (int) ($row['id'] ?? 0),
                'juego_nombre' => (string) ($row['juego_nombre'] ?? ''),
                'paquete_nombre' => (string) ($row['paquete_nombre'] ?? ''),
                'paquete_cantidad' => (string) ($row['paquete_cantidad'] ?? ''),
                'moneda' => (string) ($row['moneda'] ?? ''),
                'precio' => currency_format_amount_by_code((float) ($row['precio'] ?? 0), (string) ($row['moneda'] ?? '')),
                'email' => (string) ($row['email'] ?? ''),
                'estado' => (string) ($row['estado'] ?? 'pendiente'),
                'creado_en' => (string) ($row['creado_en'] ?? ''),
            ];
        }
    }
    $stmt->close();

    account_json_ok(['orders' => $orders]);
}

if ($action === 'update_profile') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($name === '' || $email === '') {
        account_json_error('Nombre y correo son obligatorios.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        account_json_error('El correo electrónico no es válido.');
    }
    if ($password !== '' && strlen($password) < 6) {
        account_json_error('La nueva contraseña debe tener al menos 6 caracteres.');
    }
    if ($password !== $passwordConfirm) {
        account_json_error('La confirmación de contraseña no coincide.');
    }

    $dupStmt = $mysqli->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1');
    if (!$dupStmt) {
        account_json_error('No se pudo validar el correo.', 500);
    }
    $dupStmt->bind_param('si', $email, $authUserId);
    $dupStmt->execute();
    $duplicate = $dupStmt->get_result();
    if ($duplicate && $duplicate->fetch_assoc()) {
        $dupStmt->close();
        account_json_error('Ese correo ya está registrado por otro usuario.');
    }
    $dupStmt->close();

    if ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('UPDATE usuarios SET nombre = ?, email = ?, username = ?, password = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            account_json_error('No se pudo actualizar el usuario.', 500);
        }
        $stmt->bind_param('ssssi', $name, $email, $email, $passwordHash, $authUserId);
    } else {
        $stmt = $mysqli->prepare('UPDATE usuarios SET nombre = ?, email = ?, username = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            account_json_error('No se pudo actualizar el usuario.', 500);
        }
        $stmt->bind_param('sssi', $name, $email, $email, $authUserId);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        account_json_error('No se pudieron guardar los datos del usuario.', 500);
    }
    $stmt->close();

    $_SESSION['auth_user']['email'] = $email;
    $_SESSION['auth_user']['full_name'] = $name;
    $_SESSION['auth_user']['username'] = $email;

    account_json_ok([
        'message' => 'Datos de usuario actualizados correctamente.',
        'user' => [
            'id' => $authUserId,
            'email' => $email,
            'full_name' => $name,
            'rol' => (string) ($_SESSION['auth_user']['rol'] ?? 'usuario'),
        ],
    ]);
}

account_json_error('Acción no soportada.', 422);
?>