<?php
require_once __DIR__ . "/includes/db_connect.php";
require_once __DIR__ . "/includes/app_session.php";

$openLoginModalWithError = static function (string $message, string $emailValue = ''): void {
  app_session_start();

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

$hasPhoneColumn = users_has_phone_column_mysqli($mysqli);
$selectColumns = $hasPhoneColumn
  ? "id, username, password, nombre, email, telefono, rol"
  : "id, username, password, nombre, email, rol";
$stmt = $mysqli->prepare("SELECT $selectColumns FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($user === null || empty($user["password"]) || !password_verify($password, $user["password"])) {
  $openLoginModalWithError("Credenciales inválidas.", $email);
}

app_session_start();
session_regenerate_id(true);
unset($_SESSION["auth_modal_state"]);
$_SESSION["auth_user"] = [
  "id" => $user["id"],
  "email" => $user["email"],
  "telefono" => $user["telefono"] ?? '',
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
if (($user["rol"] ?? "") === "influencer") {
  header("Location: /admin/cupones?tab=influencers");
  exit;
}
header("Location: /");
exit;
