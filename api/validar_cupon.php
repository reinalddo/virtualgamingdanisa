<?php
header('Content-Type: application/json');
// Manejo global de errores de conexión
try {
    require_once __DIR__ . '/../includes/db_connect.php';
    require_once __DIR__ . '/../includes/currency.php';
    currency_ensure_schema();
    if (!isset($mysqli) || $mysqli->connect_errno) {
        throw new Exception('Error de conexión a la base de datos: ' . ($mysqli->connect_error ?? 'Desconocido'));
    }
} catch (Exception $e) {
    $errorMsg = date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/log_cupon.txt', $errorMsg, FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

function normalize_coupon_code(string $value): string {
    return strtoupper(trim($value));
}

function is_valid_coupon_code(string $value): bool {
    return $value !== '' && preg_match('/^[A-Za-z0-9]+$/', $value) === 1;
}

$codeInput = isset($_POST['code']) ? trim($_POST['code']) : '';
$code = normalize_coupon_code($codeInput);
$pack_price = isset($_POST['pack_price']) ? floatval($_POST['pack_price']) : 0;
$currencyCode = isset($_POST['currency']) ? trim((string) $_POST['currency']) : '';
$currency = currency_find_by_code($currencyCode);
$pack_price = currency_apply_amount_rule($pack_price, $currency);

if ($code === '') {
    $errorMsg = date('Y-m-d H:i:s') . " | ERROR: Cupón vacío.\n";
    file_put_contents(__DIR__ . '/log_cupon.txt', $errorMsg, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Cupón vacío.']);
    exit;
}

if (!is_valid_coupon_code($codeInput)) {
    $errorMsg = date('Y-m-d H:i:s') . " | ERROR: Cupón con formato inválido.\n";
    file_put_contents(__DIR__ . '/log_cupon.txt', $errorMsg, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'El cupón solo puede contener letras y números, sin espacios ni caracteres especiales.']);
    exit;
}

// LOG TEMPORAL PARA DEPURAR
file_put_contents(__DIR__ . '/log_cupon.txt', date('Y-m-d H:i:s') . " | code: $code | pack_price: $pack_price\n", FILE_APPEND);

$stmt = $mysqli->prepare('SELECT * FROM cupones WHERE codigo = ? LIMIT 1');
$stmt->bind_param('s', $code);
$stmt->execute();
$res = $stmt->get_result();
$cupon = $res->fetch_assoc();
$stmt->close();

if (!$cupon) {
    $errorMsg = date('Y-m-d H:i:s') . " | ERROR: Cupón inexistente.\n";
    file_put_contents(__DIR__ . '/log_cupon.txt', $errorMsg, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Cupón inexistente.']);
    exit;
}
if ($cupon['activo'] != 1) {
    $errorMsg = date('Y-m-d H:i:s') . " | ERROR: Cupón inactivo.\n";
    file_put_contents(__DIR__ . '/log_cupon.txt', $errorMsg, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Cupón inactivo.']);
    exit;
}
if (!is_null($cupon['fecha_expiracion']) && strtotime($cupon['fecha_expiracion']) < time()) {
    $errorMsg = date('Y-m-d H:i:s') . " | ERROR: Cupón expirado.\n";
    file_put_contents(__DIR__ . '/log_cupon.txt', $errorMsg, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Cupón expirado.']);
    exit;
}
if (!is_null($cupon['limite_usos']) && $cupon['limite_usos'] > 0 && $cupon['usos_actuales'] >= $cupon['limite_usos']) {
    $errorMsg = date('Y-m-d H:i:s') . " | ERROR: Cupón agotado.\n";
    file_put_contents(__DIR__ . '/log_cupon.txt', $errorMsg, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Cupón agotado.']);
    exit;
}

$descuento = 0;
if ($cupon['tipo_descuento'] === 'porcentaje') {
    $descuento = $pack_price * ($cupon['valor_descuento'] / 100);
} else {
    $descuento = floatval($cupon['valor_descuento']);
}

$nuevo_total = currency_apply_amount_rule(max(0, $pack_price - $descuento), $currency);
$descuento = currency_apply_amount_rule(max(0, $pack_price - $nuevo_total), $currency);

echo json_encode([
    'success' => true,
    'message' => 'Cupón aplicado correctamente.',
    'descuento' => $descuento,
    'nuevo_total' => $nuevo_total,
    'tipo_descuento' => $cupon['tipo_descuento'],
    'valor_descuento' => $cupon['valor_descuento']
]);
exit;
