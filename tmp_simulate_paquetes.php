<?php
$_SERVER['REQUEST_URI'] = '/admin/paquetes/3?ajax=1&toggle_activo=1';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_ACCEPT'] = 'application/json, text/plain, */*';
$_GET = ['ajax' => '1', 'toggle_activo' => '1'];
$_REQUEST = $_GET;
chdir('c:/wamp64/www/proyectosgemini/virtualgaming/admin');
require 'c:/wamp64/www/proyectosgemini/virtualgaming/admin/paquetes.php';
