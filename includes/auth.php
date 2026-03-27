<?php
require_once __DIR__ . '/app_timezone.php';
require_once __DIR__ . '/app_session.php';
require_once __DIR__ . '/db_connect.php';

function auth_normalize_email($email) {
  return strtolower(trim((string) $email));
}

function auth_users_has_phone_column(mysqli $connection): bool {
  if (function_exists('users_has_phone_column_mysqli')) {
    return users_has_phone_column_mysqli($connection);
  }

  try {
    $result = $connection->query("SHOW COLUMNS FROM usuarios LIKE 'telefono'");
    $hasColumn = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
    if ($result instanceof mysqli_result) {
      $result->free();
    }

    return $hasColumn;
  } catch (Throwable $exception) {
    return false;
  }
}

function auth_sync_session_user(): ?array {
  app_session_start();
  $sessionUser = $_SESSION['auth_user'] ?? null;
  if (!is_array($sessionUser) || empty($sessionUser['id'])) {
    return null;
  }

  global $mysqli;
  if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    return $sessionUser;
  }

  $userId = (int) $sessionUser['id'];
  $hasPhoneColumn = auth_users_has_phone_column($mysqli);
  $selectColumns = $hasPhoneColumn
    ? 'id, username, nombre, email, telefono, rol'
    : 'id, username, nombre, email, rol';
  $stmt = $mysqli->prepare('SELECT ' . $selectColumns . ' FROM usuarios WHERE id = ? LIMIT 1');
  if (!$stmt) {
    return $sessionUser;
  }

  $stmt->bind_param('i', $userId);
  if (!$stmt->execute()) {
    $stmt->close();
    return $sessionUser;
  }

  $result = $stmt->get_result();
  $freshUser = $result ? $result->fetch_assoc() : null;
  $stmt->close();

  if (!is_array($freshUser)) {
    unset($_SESSION['auth_user']);
    return null;
  }

  $_SESSION['auth_user'] = [
    'id' => (int) ($freshUser['id'] ?? $userId),
    'email' => (string) ($freshUser['email'] ?? ''),
    'telefono' => (string) ($freshUser['telefono'] ?? ''),
    'full_name' => (string) ($freshUser['nombre'] ?? ''),
    'username' => (string) ($freshUser['username'] ?? ''),
    'rol' => strtolower(trim((string) ($freshUser['rol'] ?? 'usuario'))),
  ];

  return $_SESSION['auth_user'];
}

function auth_set_flash($type, $message) {
  app_session_start();
  $_SESSION["auth_flash"] = ["type" => $type, "message" => $message];
}

function auth_redirect_back($fallback = "/") {
  $target = $_SERVER["HTTP_REFERER"] ?? $fallback;
  header("Location: " . $target);
  exit;
}
