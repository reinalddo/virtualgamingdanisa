<?php
require_once __DIR__ . "/includes/db_connect.php";

$openLoginModalWithError = static function (string $message, string $emailValue = ''): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  $_SESSION["auth_modal_state"] = [
    "mode" => "login",
    "message" => $message,
    "email" => $emailValue,
  ];

  header("Location: /");
  exit;
};

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: /");
  exit;
}

$email = strtolower(trim($_POST["email"] ?? ""));
$password = (string) ($_POST["password"] ?? "");

if ($email === "" || $password === "") {
  $openLoginModalWithError("Completa el correo y la contraseña.", $email);
}

$stmt = $mysqli->prepare("SELECT id, username, password, nombre, email, rol FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($user === null || empty($user["password"]) || !password_verify($password, $user["password"])) {
  $openLoginModalWithError("Credenciales inválidas.", $email);
}

session_start();
unset($_SESSION["auth_modal_state"]);
$_SESSION["auth_user"] = [
  "id" => $user["id"],
  "email" => $user["email"],
  "full_name" => $user["nombre"],
  "username" => $user["username"],
  "rol" => $user["rol"]
];
$_SESSION["auth_flash"] = ["type" => "success", "message" => "Inicio de sesión exitoso."];

if (($user["rol"] ?? "") === "admin") {
  header("Location: /admin/dashboard");
  exit;
}
if (($user["rol"] ?? "") === "empleado") {
  header("Location: /admin/pedidos");
  exit;
}
header("Location: /");
exit;
