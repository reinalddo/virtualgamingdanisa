<?php
$_SERVER['HTTP_HOST'] = 'virtualgaming';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/paquetes/3';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_ACCEPT'] = 'application/json, text/plain, */*';
$_POST = ['ajax' => '1', 'update_orden_paquete' => '1', 'paquete_id' => '6', 'orden' => '7'];
$_REQUEST = $_POST;
chdir('c:/wamp64/www/proyectosgemini/virtualgaming/admin');
require 'c:/wamp64/www/proyectosgemini/virtualgaming/admin/paquetes.php';
