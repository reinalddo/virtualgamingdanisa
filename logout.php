<?php
require_once __DIR__ . '/includes/app_session.php';
app_session_start();
// Destruir la sesión y redirigir al index
$_SESSION = [];
app_session_forget_cookie();
session_destroy();
header('Location: /');
exit();
