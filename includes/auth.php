<?php
function auth_normalize_email($email) {
  return strtolower(trim((string) $email));
}

function auth_set_flash($type, $message) {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
  $_SESSION["auth_flash"] = ["type" => $type, "message" => $message];
}

function auth_redirect_back($fallback = "/") {
  $target = $_SERVER["HTTP_REFERER"] ?? $fallback;
  header("Location: " . $target);
  exit;
}
