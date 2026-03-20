<?php
session_start();
// Destruir la sesión y redirigir al index
session_destroy();
header('Location: /');
exit();
