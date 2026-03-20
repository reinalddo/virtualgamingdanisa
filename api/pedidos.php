<?php
session_start();
if (ob_get_level() === 0) {
    ob_start();
}
header('Content-Type: application/json');
@ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/currency.php';
require_once __DIR__ . '/../includes/influencer_coupons.php';
require_once __DIR__ . '/../includes/payment_methods.php';
require_once __DIR__ . '/../includes/store_config.php';
currency_ensure_schema();
payment_methods_ensure_table();

function ensure_pedidos_table(mysqli $mysqli): void {
    $create = "CREATE TABLE IF NOT EXISTS pedidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_slug VARCHAR(80) DEFAULT NULL,
        juego_id INT DEFAULT NULL,
        paquete_id INT DEFAULT NULL,
        juego_nombre VARCHAR(180) DEFAULT NULL,
        paquete_nombre VARCHAR(180) DEFAULT NULL,
        paquete_cantidad VARCHAR(80) DEFAULT NULL,
        monto_ff VARCHAR(20) DEFAULT NULL,
        moneda VARCHAR(20) DEFAULT NULL,
        precio DECIMAL(12,2) NOT NULL DEFAULT 0,
        user_identifier VARCHAR(150) DEFAULT NULL,
        email VARCHAR(180) DEFAULT NULL,
        cliente_usuario_id INT DEFAULT NULL,
        numero_referencia VARCHAR(120) DEFAULT NULL,
        telefono_contacto VARCHAR(40) DEFAULT NULL,
        cupon VARCHAR(60) DEFAULT NULL,
        ff_api_referencia VARCHAR(120) DEFAULT NULL,
        ff_api_mensaje VARCHAR(255) DEFAULT NULL,
        ff_api_payload LONGTEXT DEFAULT NULL,
        estado ENUM('pendiente','pagado','enviado','cancelado') NOT NULL DEFAULT 'pendiente',
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_estado (estado),
        INDEX idx_email (email),
        INDEX idx_cliente_usuario_id (cliente_usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($create);

    // Migración defensiva: asegurar columnas si la tabla ya existía con otro esquema
    $neededCols = [
        'tenant_slug' => "ALTER TABLE pedidos ADD COLUMN tenant_slug VARCHAR(80) NULL AFTER id",
        'juego_id' => "ALTER TABLE pedidos ADD COLUMN juego_id INT NULL AFTER tenant_slug",
        'paquete_id' => "ALTER TABLE pedidos ADD COLUMN paquete_id INT NULL AFTER juego_id",
        'juego_nombre' => "ALTER TABLE pedidos ADD COLUMN juego_nombre VARCHAR(180) NULL AFTER juego_id",
        'paquete_nombre' => "ALTER TABLE pedidos ADD COLUMN paquete_nombre VARCHAR(180) NULL AFTER juego_nombre",
        'paquete_cantidad' => "ALTER TABLE pedidos ADD COLUMN paquete_cantidad VARCHAR(80) NULL AFTER paquete_nombre",
        'monto_ff' => "ALTER TABLE pedidos ADD COLUMN monto_ff VARCHAR(20) NULL AFTER paquete_cantidad",
        'moneda' => "ALTER TABLE pedidos ADD COLUMN moneda VARCHAR(20) NULL AFTER paquete_cantidad",
        'precio' => "ALTER TABLE pedidos ADD COLUMN precio DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER moneda",
        'user_identifier' => "ALTER TABLE pedidos ADD COLUMN user_identifier VARCHAR(150) NULL AFTER precio",
        'email' => "ALTER TABLE pedidos ADD COLUMN email VARCHAR(180) NULL AFTER user_identifier",
        'cantidad' => "ALTER TABLE pedidos ADD COLUMN cantidad INT NOT NULL DEFAULT 1 AFTER cupon",
        'cliente_usuario_id' => "ALTER TABLE pedidos ADD COLUMN cliente_usuario_id INT NULL AFTER email",
        'numero_referencia' => "ALTER TABLE pedidos ADD COLUMN numero_referencia VARCHAR(120) NULL AFTER cliente_usuario_id",
        'telefono_contacto' => "ALTER TABLE pedidos ADD COLUMN telefono_contacto VARCHAR(40) NULL AFTER numero_referencia",
        'cupon' => "ALTER TABLE pedidos ADD COLUMN cupon VARCHAR(60) NULL AFTER telefono_contacto",
        'ff_api_referencia' => "ALTER TABLE pedidos ADD COLUMN ff_api_referencia VARCHAR(120) NULL AFTER cupon",
        'ff_api_mensaje' => "ALTER TABLE pedidos ADD COLUMN ff_api_mensaje VARCHAR(255) NULL AFTER ff_api_referencia",
        'ff_api_payload' => "ALTER TABLE pedidos ADD COLUMN ff_api_payload LONGTEXT NULL AFTER ff_api_mensaje",
        'estado_pago_influencer' => "ALTER TABLE pedidos ADD COLUMN estado_pago_influencer ENUM('pendiente','pagado') NOT NULL DEFAULT 'pendiente' AFTER cupon",
        'estado' => "ALTER TABLE pedidos ADD COLUMN estado ENUM('pendiente','pagado','enviado','cancelado') NOT NULL DEFAULT 'pendiente' AFTER cupon",
        'creado_en' => "ALTER TABLE pedidos ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER estado",
        'actualizado_en' => "ALTER TABLE pedidos ADD COLUMN actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en"
    ];
    $colResult = $mysqli->query("SHOW COLUMNS FROM pedidos");
    $existing = [];
    if ($colResult) {
        while ($row = $colResult->fetch_assoc()) {
            $existing[$row['Field']] = true;
        }
    }
    foreach ($neededCols as $col => $alterSql) {
        if (!isset($existing[$col])) {
            $mysqli->query($alterSql);
        }
    }
}

function ensure_juego_paquetes_monto_ff_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'monto_ff'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN monto_ff VARCHAR(20) NULL AFTER clave");
    }
}

function ensure_movimientos_table(mysqli $mysqli): void {
    $create = "CREATE TABLE IF NOT EXISTS movimientos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(120) NOT NULL,
        descripcion VARCHAR(255) DEFAULT NULL,
        fecha_raw VARCHAR(120) DEFAULT NULL,
        fecha_movimiento DATETIME DEFAULT NULL,
        tipo VARCHAR(80) DEFAULT NULL,
        monto DECIMAL(14,2) NOT NULL DEFAULT 0,
        moneda VARCHAR(20) NOT NULL DEFAULT 'VES',
        pedido_id INT DEFAULT NULL,
        payload_json LONGTEXT DEFAULT NULL,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_movimientos_referencia (referencia),
        INDEX idx_movimientos_pedido_id (pedido_id),
        INDEX idx_movimientos_monto (monto),
        INDEX idx_movimientos_fecha (fecha_movimiento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($create);

    $neededCols = [
        'referencia' => "ALTER TABLE movimientos ADD COLUMN referencia VARCHAR(120) NOT NULL AFTER id",
        'descripcion' => "ALTER TABLE movimientos ADD COLUMN descripcion VARCHAR(255) NULL AFTER referencia",
        'fecha_raw' => "ALTER TABLE movimientos ADD COLUMN fecha_raw VARCHAR(120) NULL AFTER descripcion",
        'fecha_movimiento' => "ALTER TABLE movimientos ADD COLUMN fecha_movimiento DATETIME NULL AFTER fecha_raw",
        'tipo' => "ALTER TABLE movimientos ADD COLUMN tipo VARCHAR(80) NULL AFTER fecha_movimiento",
        'monto' => "ALTER TABLE movimientos ADD COLUMN monto DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER tipo",
        'moneda' => "ALTER TABLE movimientos ADD COLUMN moneda VARCHAR(20) NOT NULL DEFAULT 'VES' AFTER monto",
        'pedido_id' => "ALTER TABLE movimientos ADD COLUMN pedido_id INT NULL AFTER moneda",
        'payload_json' => "ALTER TABLE movimientos ADD COLUMN payload_json LONGTEXT NULL AFTER pedido_id",
        'creado_en' => "ALTER TABLE movimientos ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payload_json",
        'actualizado_en' => "ALTER TABLE movimientos ADD COLUMN actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en",
    ];

    $colResult = $mysqli->query("SHOW COLUMNS FROM movimientos");
    $existing = [];
    if ($colResult instanceof mysqli_result) {
        while ($row = $colResult->fetch_assoc()) {
            $existing[$row['Field']] = true;
        }
    }

    foreach ($neededCols as $col => $alterSql) {
        if (!isset($existing[$col])) {
            $mysqli->query($alterSql);
        }
    }

    $indexes = [
        'uniq_movimientos_referencia' => 'ALTER TABLE movimientos ADD UNIQUE KEY uniq_movimientos_referencia (referencia)',
        'idx_movimientos_pedido_id' => 'ALTER TABLE movimientos ADD INDEX idx_movimientos_pedido_id (pedido_id)',
        'idx_movimientos_monto' => 'ALTER TABLE movimientos ADD INDEX idx_movimientos_monto (monto)',
        'idx_movimientos_fecha' => 'ALTER TABLE movimientos ADD INDEX idx_movimientos_fecha (fecha_movimiento)',
    ];
    foreach ($indexes as $indexName => $sql) {
        $indexResult = $mysqli->query("SHOW INDEX FROM movimientos WHERE Key_name = '" . $mysqli->real_escape_string($indexName) . "'");
        if (!($indexResult instanceof mysqli_result) || $indexResult->num_rows === 0) {
            $mysqli->query($sql);
        }
    }
}

function ensure_juegos_api_free_fire_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juegos LIKE 'api_free_fire'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juegos ADD COLUMN api_free_fire TINYINT(1) NOT NULL DEFAULT 0 AFTER popular");
    }
}

function coupon_table_exists(mysqli $mysqli): bool {
    $res = $mysqli->query("SHOW TABLES LIKE 'cupones'");
    return $res && $res->num_rows > 0;
}

function table_exists(mysqli $mysqli, string $tableName): bool {
    $safeName = $mysqli->real_escape_string($tableName);
    $res = $mysqli->query("SHOW TABLES LIKE '{$safeName}'");
    return $res && $res->num_rows > 0;
}

function load_mail_settings(mysqli $mysqli): array {
    $settings = [
        'correo_corporativo' => '',
        'smtp_host' => '',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
    ];

    if (table_exists($mysqli, 'configuracion_general')) {
        $res = $mysqli->query("SELECT clave, valor FROM configuracion_general");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = $row['clave'] ?? '';
                if ($key !== '' && array_key_exists($key, $settings)) {
                    $settings[$key] = $row['valor'];
                }
            }
        }
    } elseif (table_exists($mysqli, 'configuracion')) {
        $res = $mysqli->query("SELECT * FROM configuracion ORDER BY id DESC LIMIT 1");
        if ($res && ($row = $res->fetch_assoc())) {
            foreach ($settings as $key => $defaultValue) {
                if (isset($row[$key]) && $row[$key] !== '') {
                    $settings[$key] = $row[$key];
                }
            }
        }
    }

    $settings['smtp_port'] = (int) ($settings['smtp_port'] ?: 587);
    $settings['smtp_secure'] = strtolower(trim((string) $settings['smtp_secure']));
    if (!in_array($settings['smtp_secure'], ['ssl', 'tls'], true)) {
        $settings['smtp_secure'] = 'tls';
    }

    return $settings;
}

function resolve_admin_email(mysqli $mysqli): ?string {
    $envEmail = trim((string) getenv('TVG_ADMIN_EMAIL'));
    if ($envEmail !== '' && filter_var($envEmail, FILTER_VALIDATE_EMAIL)) {
        return $envEmail;
    }

    if (table_exists($mysqli, 'usuarios')) {
        $resAdmin = $mysqli->query("SELECT email FROM usuarios WHERE rol='admin' AND email IS NOT NULL AND email != '' ORDER BY id ASC LIMIT 1");
        if ($resAdmin && ($rowAdmin = $resAdmin->fetch_assoc())) {
            $adminEmail = trim((string) ($rowAdmin['email'] ?? ''));
            if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                return $adminEmail;
            }
        }
    }

    $settings = load_mail_settings($mysqli);
    foreach (['correo_corporativo', 'smtp_user'] as $key) {
        $candidate = trim((string) ($settings[$key] ?? ''));
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }
    }

    return null;
}

function fetch_valid_coupon(mysqli $mysqli, string $code): ?array {
    if ($code === '' || !coupon_table_exists($mysqli)) {
        return null;
    }
    $stmt = $mysqli->prepare("SELECT * FROM cupones WHERE codigo = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $coupon = $res ? $res->fetch_assoc() : null;
    if (!$coupon) return null;
    if (empty($coupon['activo'])) return null;
    if (!empty($coupon['fecha_expiracion']) && strtotime($coupon['fecha_expiracion']) < time()) return null;
    if (!empty($coupon['limite_usos']) && isset($coupon['usos_actuales']) && $coupon['usos_actuales'] >= $coupon['limite_usos']) return null;
    return $coupon;
}

function fetch_coupon_by_code(mysqli $mysqli, string $code): ?array {
    if ($code === '') {
        return null;
    }

    $stmt = $mysqli->prepare('SELECT * FROM cupones WHERE codigo = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $coupon = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $coupon ?: null;
}

function register_influencer_coupon_sale(mysqli $mysqli, array $order): void {
    $orderId = (int) ($order['id'] ?? 0);
    $couponCode = normalize_coupon_code((string) ($order['cupon'] ?? ''));
    if ($orderId <= 0 || $couponCode === '') {
        return;
    }

    $coupon = fetch_coupon_by_code($mysqli, $couponCode);
    if (!$coupon || !influencer_coupon_has_owner($coupon)) {
        return;
    }

    influencer_coupon_ensure_sales_table_mysqli($mysqli);

    $existsStmt = $mysqli->prepare('SELECT id FROM cupones_influencer_ventas WHERE pedido_id = ? LIMIT 1');
    if (!$existsStmt) {
        return;
    }
    $existsStmt->bind_param('i', $orderId);
    $existsStmt->execute();
    $existing = $existsStmt->get_result();
    $alreadyExists = $existing && $existing->fetch_assoc();
    $existsStmt->close();
    if ($alreadyExists) {
        return;
    }

    $couponId = (int) ($coupon['id'] ?? 0);
    if ($couponId <= 0) {
        return;
    }

    $influencerName = influencer_coupon_clean_text($coupon['nombre_influencer'] ?? null, 100);
    $phone = influencer_coupon_clean_text($coupon['telefono_influencer'] ?? null, 50);
    $email = influencer_coupon_clean_text($coupon['email_influencer'] ?? null, 100);
    $commissionPercent = influencer_coupon_commission_percent($coupon['comision_influencer'] ?? 0);
    $packageName = influencer_coupon_clean_text($order['paquete_nombre'] ?? null, 180);
    $currency = influencer_coupon_clean_text($order['moneda'] ?? null, 20);
    $totalSale = round((float) ($order['precio'] ?? 0), 2);
    $commissionTotal = influencer_coupon_commission_total($totalSale, $commissionPercent);
    $paymentState = 'pendiente';

    $insert = $mysqli->prepare('INSERT INTO cupones_influencer_ventas (cupon_id, pedido_id, nombre_influencer, codigo_cupon, telefono_influencer, email_influencer, comision_porcentaje, paquete_vendido, moneda, total_pedido, total_comision, estado_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$insert) {
        return;
    }

    $insert->bind_param(
        'iissssdssdds',
        $couponId,
        $orderId,
        $influencerName,
        $couponCode,
        $phone,
        $email,
        $commissionPercent,
        $packageName,
        $currency,
        $totalSale,
        $commissionTotal,
        $paymentState
    );
    $insert->execute();
    $insert->close();

    $updateOrder = $mysqli->prepare("UPDATE pedidos SET estado_pago_influencer = CASE WHEN estado_pago_influencer = 'pagado' THEN 'pagado' ELSE 'pendiente' END WHERE id = ?");
    if ($updateOrder) {
        $updateOrder->bind_param('i', $orderId);
        $updateOrder->execute();
        $updateOrder->close();
    }
}

function apply_coupon_to_price(float $price, array $coupon): float {
    $discounted = $price;
    $value = floatval($coupon['valor_descuento'] ?? 0);
    $type = $coupon['tipo_descuento'] ?? 'porcentaje';
    if ($value <= 0) return $price;
    if ($type === 'fijo') {
        $discounted = max(0, $price - $value);
    } else {
        $discounted = max(0, $price - ($price * ($value / 100)));
    }
    return $discounted;
}

function format_order_price_value(float $price, string $currencyCode): string {
    return currency_format_amount($price, currency_find_by_code($currencyCode));
}

function json_error(string $message, int $code = 400): void {
    json_response(['ok' => false, 'message' => $message], $code);
}

function json_response(array $payload, int $code = 200, ?callable $afterSend = null): void {
    http_response_code($code);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        $json = json_encode([
            'ok' => false,
            'message' => 'No se pudo generar una respuesta JSON válida.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false,"message":"No se pudo generar una respuesta JSON válida."}';
    }

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    ignore_user_abort(true);
    echo $json;

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        flush();
    }

    if ($afterSend !== null) {
        try {
            $afterSend();
        } catch (Throwable $e) {
            error_log('TVG background task error: ' . $e->getMessage());
        }
    }

    exit;
}

set_exception_handler(static function (Throwable $e): void {
    error_log('TVG pedidos uncaught exception: ' . $e->getMessage());
    json_response([
        'ok' => false,
        'message' => 'Ocurrió un error interno al procesar la solicitud.',
    ], 500);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    error_log('TVG pedidos fatal shutdown: ' . ($error['message'] ?? 'Fatal error'));

    if (!headers_sent()) {
        http_response_code(500);
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode([
            'ok' => false,
            'message' => 'Ocurrió un error fatal al procesar la solicitud.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
});

function inline_embedded_images_for_html(string $html, array $embeddedImages): string {
    foreach ($embeddedImages as $image) {
        $cid = trim((string) ($image['cid'] ?? ''));
        $path = (string) ($image['path'] ?? '');
        $mime = trim((string) ($image['mime'] ?? ''));
        if ($cid === '' || $path === '' || !is_file($path) || !is_readable($path)) {
            continue;
        }

        $binary = @file_get_contents($path);
        if ($binary === false) {
            continue;
        }

        $detectedMime = $mime !== '' ? $mime : detect_local_file_mime_type($path);
        $html = str_replace('cid:' . $cid, 'data:' . $detectedMime . ';base64,' . base64_encode($binary), $html);
    }

    return $html;
}

function send_app_mail(string $to, string $subject, string $html, ?string $from = null, array $embeddedImages = []): void {
    global $mysqli;
    $settings = isset($mysqli) && $mysqli instanceof mysqli
        ? load_mail_settings($mysqli)
        : [
            'correo_corporativo' => '',
            'smtp_host' => '',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_port' => 587,
            'smtp_secure' => 'tls',
        ];
    $fromAddr = $from ?: ($settings['correo_corporativo'] ?: $settings['smtp_user']);

    try {
        require_once __DIR__ . '/../includes/PHPMailerAutoload.php';
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new RuntimeException('PHPMailer no disponible');
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $smtp_host = $settings['smtp_host'];
        $smtp_user = $settings['smtp_user'];
        $smtp_pass = $settings['smtp_pass'];
        $smtp_port = $settings['smtp_port'];
        $smtp_secure = $settings['smtp_secure'];
        $branding = email_branding();
        $senderName = trim((string) ($branding['name'] ?? 'TVirtualGaming')) ?: 'TVirtualGaming';

        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = $smtp_secure;
        $mail->Port = $smtp_port;
        $mail->setFrom($fromAddr, $senderName);
        $mail->addAddress($to);
        foreach ($embeddedImages as $image) {
            $path = (string) ($image['path'] ?? '');
            $cid = trim((string) ($image['cid'] ?? ''));
            if ($path === '' || $cid === '' || !is_file($path)) {
                continue;
            }
            $mail->addEmbeddedImage($path, $cid, basename($path), 'base64', detect_local_file_mime_type($path));
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->send();
    } catch (Throwable $e) {
        error_log('TVG mail error: ' . $e->getMessage());
        try {
            $branding = email_branding();
            $senderName = trim((string) ($branding['name'] ?? 'TVirtualGaming')) ?: 'TVirtualGaming';
            send_app_mail_via_smtp_socket($to, $subject, inline_embedded_images_for_html($html, $embeddedImages), $fromAddr, $settings, $senderName);
        } catch (Throwable $smtpError) {
            error_log('TVG SMTP fallback error: ' . $smtpError->getMessage());
        }
    }
}

function smtp_read_response($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }
    return $response;
}

function smtp_expect_ok($socket, array $allowedCodes, string $context): string {
    $response = smtp_read_response($socket);
    $code = (int) substr(trim($response), 0, 3);
    if (!in_array($code, $allowedCodes, true)) {
        throw new RuntimeException($context . ': ' . trim($response));
    }
    return $response;
}

function smtp_send_command($socket, string $command, array $allowedCodes, string $context): string {
    fwrite($socket, $command . "\r\n");
    return smtp_expect_ok($socket, $allowedCodes, $context);
}

function send_app_mail_via_smtp_socket(string $to, string $subject, string $html, string $fromAddr, array $settings, string $senderName = 'TVirtualGaming'): void {
    $host = (string) ($settings['smtp_host'] ?? '');
    $port = (int) ($settings['smtp_port'] ?? 587);
    $secure = strtolower((string) ($settings['smtp_secure'] ?? 'tls'));
    $username = (string) ($settings['smtp_user'] ?? '');
    $password = (string) ($settings['smtp_pass'] ?? '');

    if ($host === '' || $username === '' || $password === '') {
        throw new RuntimeException('Configuración SMTP incompleta');
    }

    $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $errstr . ' (' . $errno . ')');
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect_ok($socket, [220], 'Conexión SMTP');
        smtp_send_command($socket, 'EHLO localhost', [250], 'EHLO inicial');

        if ($secure === 'tls') {
            smtp_send_command($socket, 'STARTTLS', [220], 'STARTTLS');
            $cryptoEnabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new RuntimeException('No se pudo habilitar TLS');
            }
            smtp_send_command($socket, 'EHLO localhost', [250], 'EHLO tras TLS');
        }

        smtp_send_command($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN');
        smtp_send_command($socket, base64_encode($username), [334], 'SMTP usuario');
        smtp_send_command($socket, base64_encode($password), [235], 'SMTP contraseña');
        smtp_send_command($socket, 'MAIL FROM:<' . $fromAddr . '>', [250], 'MAIL FROM');
        smtp_send_command($socket, 'RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
        smtp_send_command($socket, 'DATA', [354], 'DATA');

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . str_replace(["\r", "\n"], '', $senderName) . ' <' . $fromAddr . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n" . $html;
        $body = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $body);
        fwrite($socket, $body . "\r\n.\r\n");
        smtp_expect_ok($socket, [250], 'Envío de mensaje');
        smtp_send_command($socket, 'QUIT', [221], 'QUIT');
    } finally {
        fclose($socket);
    }
}

function sanitize_str(?string $value, int $max = 255): ?string {
    if ($value === null) return null;
    $clean = trim($value);
    if ($clean === '') return null;
    return substr($clean, 0, $max);
}

function email_escape(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function order_visual_status_label(?string $status): string {
    $normalized = strtolower(trim((string) $status));
    return match ($normalized) {
        'pendiente' => 'No Verificado',
        'pagado' => 'Verificado',
        default => trim((string) $status),
    };
}

function app_base_url(): string {
    $https = $_SERVER['HTTPS'] ?? '';
    $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . $host;
}

function detect_local_file_mime_type(string $filePath): string {
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($filePath);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    return match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        default => 'image/png',
    };
}

function resolve_store_logo_file_path(string $brandLogo): ?string {
    $brandLogo = trim($brandLogo);
    if ($brandLogo === '' || preg_match('#^https?://#i', $brandLogo) === 1) {
        return null;
    }

    $logoPath = $brandLogo;
    $urlPath = parse_url($brandLogo, PHP_URL_PATH);
    if (is_string($urlPath) && $urlPath !== '') {
        $logoPath = $urlPath;
    }

    $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoPath), DIRECTORY_SEPARATOR);
    if ($relativePath === '') {
        return null;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relativePath;
    return is_file($absolutePath) ? $absolutePath : null;
}

function email_branding(): array {
    $brandPrefix = trim(store_config_get('nombre_prefijo', 'TIENDA'));
    $brandName = trim(store_config_get('nombre_tienda', 'TVirtualGaming'));
    $brandLogo = trim(store_config_get('logo_tienda', ''));
    $logoUrl = '';
    $logoPath = resolve_store_logo_file_path($brandLogo);

    if ($brandLogo !== '') {
        if (preg_match('#^https?://#i', $brandLogo) === 1) {
            $logoUrl = $brandLogo;
        } elseif (str_starts_with($brandLogo, '/')) {
            $logoUrl = app_base_url() . $brandLogo;
        }
    }

    return [
        'prefix' => $brandPrefix !== '' ? $brandPrefix : 'TIENDA',
        'name' => $brandName !== '' ? $brandName : 'TVirtualGaming',
        'logo_url' => $logoUrl,
        'logo_path' => $logoPath,
        'logo_mime' => $logoPath !== null ? detect_local_file_mime_type($logoPath) : '',
    ];
}

function email_branding_embedded_images(): array {
    $branding = email_branding();
    $logoPath = $branding['logo_path'] ?? null;
    if (!is_string($logoPath) || $logoPath === '' || !is_file($logoPath)) {
        return [];
    }

    return [[
        'path' => $logoPath,
        'cid' => 'store-logo',
        'mime' => $branding['logo_mime'] ?? '',
    ]];
}

function default_payment_method_for_currency(string $currencyCode): ?array {
    $currencyCode = strtoupper(trim($currencyCode));
    if ($currencyCode === '') {
        return null;
    }

    $methodsByCurrency = payment_methods_active_by_currency();
    $methods = $methodsByCurrency[$currencyCode] ?? [];
    if (!is_array($methods) || empty($methods)) {
        return null;
    }

    $method = $methods[0];
    return is_array($method) ? $method : null;
}

function payment_method_details_html(?array $method): string {
    if (!$method) {
        return '<p style="margin:14px 0 0;color:#fca5a5;">Aún no hay un método de pago activo configurado para esta moneda. Nuestro equipo revisará tu pedido para indicarte cómo completar el pago.</p>';
    }

    $name = email_escape($method['nombre'] ?? 'Método de pago');
    $details = trim((string) ($method['datos'] ?? ''));
    $formattedDetails = $details !== ''
        ? nl2br(email_escape($details), false)
        : 'Sin detalles adicionales.';

    return '<div style="margin-top:16px;padding:18px 20px;background:#0f172a;border:1px solid #1e293b;border-radius:16px;">'
        . '<div style="color:#67e8f9;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px;">Método de pago disponible</div>'
        . '<div style="color:#f8fafc;font-size:18px;font-weight:700;margin-bottom:10px;">' . $name . '</div>'
        . '<div style="color:#cbd5e1;font-size:14px;line-height:1.7;">' . $formattedDetails . '</div>'
        . '</div>';
}

function render_order_email(string $title, string $eyebrow, string $messageHtml, array $orderData, string $accent = '#22d3ee'): string {
    $branding = email_branding();
    $orderId = email_escape((string) ($orderData['order_id'] ?? ''));
    $gameName = email_escape($orderData['game_name'] ?? '');
    $packName = email_escape($orderData['pack_name'] ?? '');
    $packAmount = email_escape($orderData['pack_amount'] ?? '');
    $currency = email_escape($orderData['currency'] ?? '');
    $price = email_escape($orderData['price'] ?? '');
    $userIdentifier = email_escape($orderData['user_identifier'] ?? '');
    $email = email_escape($orderData['email'] ?? '');
    $paymentMethod = email_escape($orderData['payment_method'] ?? '');
    $referenceNumber = email_escape($orderData['reference_number'] ?? '');
    $phoneNumber = email_escape($orderData['phone'] ?? '');
    $coupon = trim((string) ($orderData['coupon'] ?? ''));
    $status = email_escape(order_visual_status_label($orderData['status'] ?? ''));
    $couponRow = $coupon !== ''
        ? '<tr><td style="padding:10px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Cupón</td><td style="padding:10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . email_escape($coupon) . '</td></tr>'
        : '';
    $statusRow = $status !== ''
        ? '<tr><td style="padding:10px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Estado</td><td style="padding:10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $status . '</td></tr>'
        : '';
    $paymentMethodRow = $paymentMethod !== ''
        ? '<tr><td style="padding:10px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Método de pago</td><td style="padding:10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $paymentMethod . '</td></tr>'
        : '';
    $referenceRow = $referenceNumber !== ''
        ? '<tr><td style="padding:10px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Referencia</td><td style="padding:10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $referenceNumber . '</td></tr>'
        : '';
    $phoneRow = $phoneNumber !== ''
        ? '<tr><td style="padding:10px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Teléfono</td><td style="padding:10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $phoneNumber . '</td></tr>'
        : '';
    $brandingLogo = trim((string) (($branding['logo_path'] ?? '') !== '' ? 'cid:store-logo' : ($branding['logo_url'] ?? '')));
    $brandingLogoHtml = $brandingLogo !== ''
        ? '<div style="margin:0 auto 16px;width:72px;height:72px;border-radius:18px;overflow:hidden;border:1px solid rgba(103,232,249,0.65);box-shadow:0 0 18px rgba(34,211,238,0.18);background:rgba(8,15,24,0.65);">'
            . '<img src="' . email_escape($brandingLogo) . '" alt="Logo de la tienda" style="display:block;width:100%;height:100%;object-fit:cover;">'
            . '</div>'
        : '';
    $brandingPrefix = email_escape($branding['prefix'] ?? 'TIENDA');
    $brandingName = email_escape($branding['name'] ?? 'TVirtualGaming');

    return '<!doctype html>'
        . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . email_escape($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#0a0f14;font-family:Arial,Helvetica,sans-serif;color:#e2e8f0;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0a0f14;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#111827;border:1px solid #164e63;border-radius:20px;overflow:hidden;box-shadow:0 0 0 1px rgba(34,211,238,0.08),0 20px 40px rgba(0,0,0,0.35);">'
        . '<tr><td style="padding:28px 32px;background:linear-gradient(135deg,#0b1220 0%,#102133 55%,#0f3b46 100%);text-align:center;">'
        . $brandingLogoHtml
        . '<div style="color:#67e8f9;font-size:12px;letter-spacing:4px;text-transform:uppercase;margin-bottom:10px;">' . $brandingPrefix . '</div>'
        . '<div style="color:#ffffff;font-size:32px;line-height:1.2;font-weight:700;margin-bottom:8px;">' . $brandingName . '</div>'
        . '<div style="display:inline-block;padding:6px 14px;border:1px solid ' . $accent . ';border-radius:999px;color:' . $accent . ';font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Notificación de pedido</div>'
        . '<div style="color:#cbd5e1;font-size:12px;letter-spacing:4px;text-transform:uppercase;margin-top:12px;">' . email_escape($eyebrow) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:32px;">'
        . '<h1 style="margin:0 0 14px;color:#f8fafc;font-size:28px;line-height:1.2;">' . email_escape($title) . '</h1>'
        . '<div style="color:#cbd5e1;font-size:15px;line-height:1.7;margin-bottom:24px;">' . $messageHtml . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;">'
        . '<tr><td colspan="2" style="padding:16px 20px;background:#0b1220;color:#67e8f9;font-size:16px;font-weight:700;">Pedido #' . $orderId . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Juego</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $gameName . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Paquete</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $packName . ($packAmount !== '' ? ' (' . $packAmount . ')' : '') . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Total</td><td style="padding:10px 20px 10px 0;color:' . $accent . ';font-size:18px;font-weight:700;text-align:right;border-bottom:1px solid #1e293b;">' . $currency . ' ' . $price . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Cliente</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $userIdentifier . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Correo</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $email . '</td></tr>'
        . $paymentMethodRow
        . $referenceRow
        . $phoneRow
        . $couponRow
        . $statusRow
        . '</table>'
        . '<div style="margin-top:24px;padding:16px 18px;background:#0b1220;border:1px solid #1e293b;border-radius:14px;color:#94a3b8;font-size:13px;line-height:1.6;">'
        . 'Este correo fue generado automáticamente por ' . $brandingName . '. Si necesitas revisar el pedido, ingresa al panel o responde desde los canales de soporte configurados.'
        . '</div>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
}

function normalize_coupon_code(string $value): string {
    return strtoupper(trim($value));
}

function is_valid_coupon_code(string $value): bool {
    return $value !== '' && preg_match('/^[A-Za-z0-9]+$/', $value) === 1;
}

function order_expiration_seconds(): int {
    return 1800;
}

function order_expiration_timestamp(array $order): int {
    $createdAt = isset($order['creado_en_ts']) ? (int) $order['creado_en_ts'] : 0;
    if ($createdAt <= 0) {
        $createdAt = strtotime((string) ($order['creado_en'] ?? ''));
        if ($createdAt === false) {
            $createdAt = time();
        }
    }
    return $createdAt + order_expiration_seconds();
}

function order_is_expired(array $order): bool {
    return time() >= order_expiration_timestamp($order);
}

function order_expiration_iso(array $order): string {
    return date(DATE_ATOM, order_expiration_timestamp($order));
}

function fetch_order_by_id(mysqli $mysqli, int $orderId): ?array {
    $stmt = $mysqli->prepare('SELECT pedidos.*, UNIX_TIMESTAMP(creado_en) AS creado_en_ts FROM pedidos WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $order ?: null;
}

function fetch_active_payment_method(mysqli $mysqli, int $methodId): ?array {
    $stmt = $mysqli->prepare("SELECT pm.*, m.nombre AS moneda_nombre, m.clave AS moneda_clave
        FROM payment_methods pm
        INNER JOIN monedas m ON m.id = pm.moneda_id
        WHERE pm.id = ? AND pm.activo = 1
        LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $methodId);
    $stmt->execute();
    $res = $stmt->get_result();
    $method = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $method ?: null;
}

function normalize_currency_code(?string $currencyCode): string {
    $normalized = strtoupper(trim((string) $currencyCode));
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/[^A-Z0-9]+/u', '', $normalized) ?? '';
    $normalized = str_replace(['Á', 'À', 'Ä', 'Â'], 'A', $normalized);
    $normalized = str_replace(['É', 'È', 'Ë', 'Ê'], 'E', $normalized);
    $normalized = str_replace(['Í', 'Ì', 'Ï', 'Î'], 'I', $normalized);
    $normalized = str_replace(['Ó', 'Ò', 'Ö', 'Ô'], 'O', $normalized);
    $normalized = str_replace(['Ú', 'Ù', 'Ü', 'Û'], 'U', $normalized);

    if (
        $normalized === 'BS'
        || $normalized === 'BSS'
        || str_contains($normalized, 'VES')
        || str_contains($normalized, 'VEF')
        || str_contains($normalized, 'BOLIVAR')
        || str_contains($normalized, 'BOLIVARES')
        || str_ends_with($normalized, 'BS')
    ) {
        return 'VES';
    }

    return $normalized;
}

function order_currency_uses_bank_api(?string $currencyCode): bool {
    $normalized = normalize_currency_code($currencyCode);
    return $normalized === 'VES';
}

function game_uses_free_fire_api(mysqli $mysqli, int $gameId): bool {
    if ($gameId <= 0) {
        return false;
    }

    $stmt = $mysqli->prepare('SELECT api_free_fire FROM juegos WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $gameId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return !empty($row['api_free_fire']);
}

function fetch_game_package(mysqli $mysqli, int $packageId, int $gameId): ?array {
    if ($packageId <= 0 || $gameId <= 0) {
        return null;
    }

    $stmt = $mysqli->prepare('SELECT * FROM juego_paquetes WHERE id = ? AND juego_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $packageId, $gameId);
    $stmt->execute();
    $res = $stmt->get_result();
    $package = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $package ?: null;
}

function free_fire_api_config(): array {
    return [
        'usuario' => trim(store_config_get('ff_api_usuario', '')),
        'clave' => trim(store_config_get('ff_api_clave', '')),
        'tipo' => trim(store_config_get('ff_api_tipo', 'recargaFreefire')),
    ];
}

function free_fire_api_config_is_complete(array $config): bool {
    return trim((string) ($config['usuario'] ?? '')) !== ''
        && trim((string) ($config['clave'] ?? '')) !== ''
        && trim((string) ($config['tipo'] ?? '')) !== '';
}

function free_fire_api_alert_is_success(?string $alert): bool {
    $normalized = strtolower(trim((string) $alert));
    return in_array($normalized, ['green', 'success', 'ok'], true);
}

function execute_free_fire_recharge(array $config, string $monto, string $numero): array {
    if (!free_fire_api_config_is_complete($config)) {
        throw new RuntimeException('La configuración de la API de Free Fire está incompleta.');
    }

    if ($monto === '') {
        throw new RuntimeException('El paquete seleccionado no tiene monto_ff configurado.');
    }

    if ($numero === '') {
        throw new RuntimeException('El ID del jugador es obligatorio para la recarga de Free Fire.');
    }

    $url = 'https://www.tiendagiftven.net/conexion_api/api.php?' . http_build_query([
        'action' => 'recarga',
        'usuario' => $config['usuario'],
        'clave' => $config['clave'],
        'tipo' => $config['tipo'],
        'monto' => $monto,
        'numero' => $numero,
    ]);

    $response = http_get_json($url, 25, true);
    $message = trim((string) ($response['mensaje'] ?? ''));

    return [
        'success' => free_fire_api_alert_is_success($response['alerta'] ?? null),
        'message' => $message !== '' ? $message : 'Respuesta recibida desde la API de Free Fire.',
        'reference' => sanitize_str((string) ($response['referencia'] ?? ''), 120),
        'payload' => $response,
    ];
}

function parse_bank_movement_datetime(?string $value): ?string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $normalized = str_ireplace([' a. m.', ' p. m.', ' a.m.', ' p.m.', ' am', ' pm'], [' AM', ' PM', ' AM', ' PM', ' AM', ' PM'], $raw);
    $date = DateTime::createFromFormat('d/m/Y h:i:s A', $normalized)
        ?: DateTime::createFromFormat('d/m/Y h:i A', $normalized)
        ?: DateTime::createFromFormat('d/m/Y H:i:s', $normalized)
        ?: DateTime::createFromFormat('d/m/Y H:i', $normalized);

    return $date ? $date->format('Y-m-d H:i:s') : null;
}

function normalize_bank_amount($value): float {
    if (is_numeric($value)) {
        return round((float) $value, 2);
    }
    $clean = str_replace([',', ' '], '', (string) $value);
    return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
}

function http_get_json(string $url, int $timeout = 20, bool $verifySsl = true): array {
    $body = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException('No se pudo consultar la API bancaria: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('La API bancaria respondió con código HTTP ' . $status . '.');
        }
        $body = $response;
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('No se pudo consultar la API bancaria.');
        }
        $body = $response;
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        throw new RuntimeException('La API bancaria no devolvió un JSON válido.');
    }

    return $data;
}

function fetch_bank_movements(array $config): array {
    $position = trim((string) ($config['ff_bank_posicion'] ?? ''));
    $token = trim((string) ($config['ff_bank_token'] ?? ''));
    $password = trim((string) ($config['ff_bank_clave'] ?? ''));

    if ($position === '' || $token === '' || $password === '') {
        throw new RuntimeException('La conexión automática para pagos en Bs/VES no está configurada completamente.');
    }

    $url = 'https://pagonorte.net/recargas/movimientos.jsp?' . http_build_query([
        'posicion' => $position,
        'token' => $token,
        'password' => $password,
    ]);

    $data = http_get_json($url, 20, false);
    $movements = $data['movimientos'] ?? null;
    if (!is_array($movements)) {
        throw new RuntimeException('La API bancaria no devolvió la lista de movimientos esperada.');
    }

    $normalized = [];
    foreach ($movements as $movement) {
        if (!is_array($movement)) {
            continue;
        }
        $reference = trim((string) ($movement['referencia'] ?? ''));
        if ($reference === '') {
            continue;
        }
        $normalized[] = [
            'referencia' => substr($reference, 0, 120),
            'descripcion' => sanitize_str((string) ($movement['descripcion'] ?? ''), 255),
            'fecha_raw' => sanitize_str((string) ($movement['fecha'] ?? ''), 120),
            'fecha_movimiento' => parse_bank_movement_datetime((string) ($movement['fecha'] ?? '')),
            'tipo' => sanitize_str((string) ($movement['tipo'] ?? ''), 80),
            'monto' => normalize_bank_amount($movement['monto'] ?? 0),
            'moneda' => 'VES',
            'payload_json' => json_encode($movement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    return $normalized;
}

function fetch_and_sync_bank_movements(mysqli $mysqli, array $config): array {
    $movements = fetch_bank_movements($config);
    sync_bank_movements($mysqli, $movements);
    return $movements;
}

function sync_bank_movements(mysqli $mysqli, array $movements): void {
    if (empty($movements)) {
        return;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO movimientos (referencia, descripcion, fecha_raw, fecha_movimiento, tipo, monto, moneda, payload_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), fecha_raw = VALUES(fecha_raw), fecha_movimiento = COALESCE(VALUES(fecha_movimiento), fecha_movimiento), tipo = VALUES(tipo), monto = VALUES(monto), moneda = VALUES(moneda), payload_json = VALUES(payload_json)'
    );
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar el registro de movimientos bancarios.');
    }

    foreach ($movements as $movement) {
        $reference = (string) ($movement['referencia'] ?? '');
        $description = (string) ($movement['descripcion'] ?? '');
        $rawDate = (string) ($movement['fecha_raw'] ?? '');
        $movementDate = $movement['fecha_movimiento'] !== null ? (string) $movement['fecha_movimiento'] : null;
        $type = (string) ($movement['tipo'] ?? '');
        $amount = (float) ($movement['monto'] ?? 0);
        $currency = (string) ($movement['moneda'] ?? 'VES');
        $payloadJson = (string) ($movement['payload_json'] ?? '');

        if (!$stmt->bind_param(
            'sssssdss',
            $reference,
            $description,
            $rawDate,
            $movementDate,
            $type,
            $amount,
            $currency,
            $payloadJson
        )) {
            throw new RuntimeException('No se pudieron enlazar los datos del movimiento bancario: ' . $stmt->error);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('No se pudo registrar el movimiento bancario ' . $reference . ': ' . $stmt->error);
        }
    }

    $stmt->close();
}

function movement_reference_matches(string $fullReference, string $reportedReference, int $requiredDigits): bool {
    if ($reportedReference === '') {
        return false;
    }

    if ($requiredDigits > 0) {
        return substr($fullReference, -$requiredDigits) === $reportedReference;
    }

    return $fullReference === $reportedReference;
}

function movement_is_available_for_order(mysqli $mysqli, string $reference, int $orderId): bool {
    $stmt = $mysqli->prepare('SELECT pedido_id FROM movimientos WHERE referencia = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $reference);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return false;
    }

    $linkedOrderId = isset($row['pedido_id']) ? (int) $row['pedido_id'] : 0;
    return $linkedOrderId === 0 || $linkedOrderId === $orderId;
}

function bank_amount_matches_order_total(float $movementAmount, float $orderAmount): bool {
    return (int) $movementAmount === (int) $orderAmount;
}

function bank_mismatch_failure_type(bool $referenceMatch, bool $amountMatch): string {
    if (!$referenceMatch && $amountMatch) {
        return 'reference_mismatch';
    }
    if ($referenceMatch && !$amountMatch) {
        return 'amount_mismatch';
    }
    if ($referenceMatch && $amountMatch) {
        return 'server_partial_response';
    }
    return 'server_or_data_mismatch';
}

function find_matching_bank_movement(mysqli $mysqli, array $movements, string $reportedReference, float $orderAmount, int $requiredDigits, int $orderId): ?array {
    foreach ($movements as $movement) {
        $reference = (string) ($movement['referencia'] ?? '');
        if ($reference === '') {
            continue;
        }
        if (!movement_reference_matches($reference, $reportedReference, $requiredDigits)) {
            continue;
        }
        if (!bank_amount_matches_order_total((float) ($movement['monto'] ?? 0), $orderAmount)) {
            continue;
        }
        if (!movement_is_available_for_order($mysqli, $reference, $orderId)) {
            continue;
        }
        return $movement;
    }

    return null;
}

function find_matching_bank_movement_with_retry(
    mysqli $mysqli,
    array $bankConfig,
    string $reportedReference,
    float $orderAmount,
    int $requiredDigits,
    int $orderId,
    int $attempts = 2,
    int $delaySeconds = 8,
    ?array $initialMovements = null
): array {
    $attempts = max(1, $attempts);
    $delaySeconds = max(0, $delaySeconds);
    $latestMovements = is_array($initialMovements) ? $initialMovements : [];
    $match = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        if ($attempt > 1 || empty($latestMovements)) {
            $latestMovements = fetch_and_sync_bank_movements($mysqli, $bankConfig);
        }

        $match = find_matching_bank_movement(
            $mysqli,
            $latestMovements,
            $reportedReference,
            $orderAmount,
            $requiredDigits,
            $orderId
        );

        if ($match !== null) {
            return [
                'match' => $match,
                'movements' => $latestMovements,
                'attempts' => $attempt,
            ];
        }

        if ($attempt < $attempts && $delaySeconds > 0) {
            sleep($delaySeconds);
        }
    }

    return [
        'match' => null,
        'movements' => $latestMovements,
        'attempts' => $attempts,
    ];
}

function explain_bank_movement_mismatch(array $movements, string $reportedReference, float $orderAmount, int $requiredDigits): array {
    $referenceMatch = false;
    $amountMatch = false;

    foreach ($movements as $movement) {
        $reference = (string) ($movement['referencia'] ?? '');
        $amount = (float) ($movement['monto'] ?? 0);
        if ($reference !== '' && movement_reference_matches($reference, $reportedReference, $requiredDigits)) {
            $referenceMatch = true;
        }
        if (bank_amount_matches_order_total($amount, $orderAmount)) {
            $amountMatch = true;
        }
    }

    $reasons = [];
    if (!$referenceMatch) {
        $reasons[] = 'La referencia ingresada no coincide con ningún movimiento encontrado en la API bancaria.';
    }
    if (!$amountMatch) {
        $reasons[] = 'El monto del pago no coincide con el total esperado del pedido.';
    }
    if ($referenceMatch && $amountMatch) {
        $reasons[] = 'Existe coincidencia parcial en referencia y monto, pero no en un mismo movimiento bancario.';
    }

    return [
        'reference_match' => $referenceMatch,
        'amount_match' => $amountMatch,
        'failure_type' => bank_mismatch_failure_type($referenceMatch, $amountMatch),
        'reasons' => $reasons,
    ];
}

function link_movement_to_order(mysqli $mysqli, string $reference, int $orderId): void {
    $stmt = $mysqli->prepare('UPDATE movimientos SET pedido_id = ? WHERE referencia = ? AND (pedido_id IS NULL OR pedido_id = 0 OR pedido_id = ?)');
    if (!$stmt) {
        throw new RuntimeException('No se pudo asociar el movimiento al pedido.');
    }
    $stmt->bind_param('isi', $orderId, $reference, $orderId);
    $stmt->execute();
    $stmt->close();
}

function cancel_expired_order(mysqli $mysqli, array $order): array {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return ['changed' => false, 'message' => 'Pedido inválido.'];
    }
    if (($order['estado'] ?? '') !== 'pendiente') {
        return ['changed' => false, 'message' => 'El pedido ya no está pendiente.'];
    }
    if (!order_is_expired($order)) {
        return ['changed' => false, 'message' => 'El pedido aún no ha expirado.'];
    }

    $stmt = $mysqli->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = ? AND estado = 'pendiente'");
    if (!$stmt) {
        return ['changed' => false, 'message' => 'No se pudo actualizar el pedido.'];
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$changed) {
        return ['changed' => false, 'message' => 'El pedido ya fue actualizado.'];
    }

    $adminEmail = resolve_admin_email($mysqli);
    $customerMessage = '<p style="margin:0 0 10px;">La orden superó el tiempo límite de 30 minutos sin confirmación de pago.</p>'
        . '<p style="margin:0;">El pedido fue cancelado automáticamente y deberás generar uno nuevo si deseas continuar con la compra.</p>';
    $adminMessage = '<p style="margin:0 0 10px;">Una orden no verificada superó el tiempo límite de 30 minutos sin confirmación de pago.</p>'
        . '<p style="margin:0;">El pedido fue cancelado automáticamente por vencimiento.</p>';
    $customerHtml = render_order_email('Orden vencida', 'Cliente', $customerMessage, [
        'order_id' => $orderId,
        'game_name' => $order['juego_nombre'] ?? '',
        'pack_name' => $order['paquete_nombre'] ?? '',
        'pack_amount' => $order['paquete_cantidad'] ?? '',
        'currency' => $order['moneda'] ?? '',
        'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
        'user_identifier' => $order['user_identifier'] ?? '',
        'email' => $order['email'] ?? '',
        'coupon' => $order['cupon'] ?? null,
        'status' => 'Cancelado por tiempo',
    ], '#f87171');
    $adminHtml = render_order_email('Orden vencida', 'Administrador', $adminMessage, [
        'order_id' => $orderId,
        'game_name' => $order['juego_nombre'] ?? '',
        'pack_name' => $order['paquete_nombre'] ?? '',
        'pack_amount' => $order['paquete_cantidad'] ?? '',
        'currency' => $order['moneda'] ?? '',
        'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
        'user_identifier' => $order['user_identifier'] ?? '',
        'email' => $order['email'] ?? '',
        'coupon' => $order['cupon'] ?? null,
        'status' => 'Cancelado por tiempo',
    ], '#f87171');

    $brandingImages = email_branding_embedded_images();
    if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
            send_app_mail((string) $order['email'], "Orden vencida #{$orderId}", $customerHtml, null, $brandingImages);
    }
    if ($adminEmail !== null) {
            send_app_mail($adminEmail, "Orden vencida #{$orderId}", $adminHtml, null, $brandingImages);
    }

    return ['changed' => true, 'message' => 'La orden expiró y fue cancelada automáticamente.'];
}

function cancel_pending_order_by_customer(mysqli $mysqli, array $order): array {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return ['changed' => false, 'message' => 'Pedido inválido.'];
    }
    if (($order['estado'] ?? '') !== 'pendiente') {
        return ['changed' => false, 'message' => 'La orden ya no se puede cancelar.'];
    }

    $stmt = $mysqli->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = ? AND estado = 'pendiente'");
    if (!$stmt) {
        return ['changed' => false, 'message' => 'No se pudo cancelar la orden.'];
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$changed) {
        return ['changed' => false, 'message' => 'La orden ya fue actualizada previamente.'];
    }

    $adminEmail = resolve_admin_email($mysqli);
    $brandingImages = email_branding_embedded_images();

    $customerHtml = render_order_email('Orden cancelada', 'Cliente',
        '<p style="margin:0 0 10px;">Cancelaste la orden desde la ventana de pago.</p><p style="margin:0;">Si deseas continuar, deberás generar una nueva orden.</p>', [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'status' => 'Cancelado',
        ], '#f87171');
    $adminHtml = render_order_email('Orden cancelada por cliente', 'Administrador',
        '<p style="margin:0 0 10px;">El cliente canceló la orden desde la ventana de pago.</p><p style="margin:0;">No se requiere validación adicional para este pedido.</p>', [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'status' => 'Cancelado',
        ], '#f87171');

    if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        send_app_mail((string) $order['email'], "Orden cancelada #{$orderId}", $customerHtml, null, $brandingImages);
    }
    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Orden cancelada por cliente #{$orderId}", $adminHtml, null, $brandingImages);
    }

    return ['changed' => true, 'message' => 'La orden fue cancelada correctamente.'];
}

function notify_payment_validation_failed_cancellation(
    mysqli $mysqli,
    array $order,
    string $paymentMethodName,
    string $referenceNumber,
    string $phone
): void {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return;
    }

    $adminEmail = resolve_admin_email($mysqli);
    $brandingImages = email_branding_embedded_images();
    $customerMessage = '<p style="margin:0 0 10px;">No pudimos validar automáticamente tu pago con la referencia y el monto enviados.</p>'
        . '<p style="margin:0;">La orden fue cancelada. Debes generar una nueva orden si deseas volver a intentarlo.</p>';
    $adminMessage = '<p style="margin:0 0 10px;">La verificación automática del pago no encontró coincidencia entre monto y referencia.</p>'
        . '<p style="margin:0;">El pedido fue cancelado automáticamente para evitar una validación errónea.</p>';

    $customerHtml = render_order_email('Pago no verificado', 'Cliente', $customerMessage, [
        'order_id' => $orderId,
        'game_name' => $order['juego_nombre'] ?? '',
        'pack_name' => $order['paquete_nombre'] ?? '',
        'pack_amount' => $order['paquete_cantidad'] ?? '',
        'currency' => $order['moneda'] ?? '',
        'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
        'user_identifier' => $order['user_identifier'] ?? '',
        'email' => $order['email'] ?? '',
        'coupon' => $order['cupon'] ?? null,
        'payment_method' => $paymentMethodName,
        'reference_number' => $referenceNumber,
        'phone' => $phone,
        'status' => 'Cancelado',
    ], '#f87171');
    $adminHtml = render_order_email('Pago no verificado', 'Administrador', $adminMessage, [
        'order_id' => $orderId,
        'game_name' => $order['juego_nombre'] ?? '',
        'pack_name' => $order['paquete_nombre'] ?? '',
        'pack_amount' => $order['paquete_cantidad'] ?? '',
        'currency' => $order['moneda'] ?? '',
        'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
        'user_identifier' => $order['user_identifier'] ?? '',
        'email' => $order['email'] ?? '',
        'coupon' => $order['cupon'] ?? null,
        'payment_method' => $paymentMethodName,
        'reference_number' => $referenceNumber,
        'phone' => $phone,
        'status' => 'Cancelado',
    ], '#f87171');

    $customerEmail = trim((string) ($order['email'] ?? ''));
    if ($customerEmail !== '' && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        send_app_mail($customerEmail, "Pago no verificado #{$orderId}", $customerHtml, null, $brandingImages);
    } else {
        error_log("TVG payment validation cancel mail skipped for order #{$orderId}: invalid customer email");
    }

    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Pedido cancelado por validación #{$orderId}", $adminHtml, null, $brandingImages);
    } else {
        error_log("TVG payment validation cancel mail skipped for order #{$orderId}: admin email not configured");
    }
}

function notify_free_fire_recharge_success(
    mysqli $mysqli,
    array $order,
    string $paymentMethodName,
    string $referenceNumber,
    string $phone,
    string $providerReference,
    string $providerMessage
): void {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return;
    }

    $adminEmail = resolve_admin_email($mysqli);
    $brandingImages = email_branding_embedded_images();
    $providerReferenceText = $providerReference !== '' ? '<p style="margin:0 0 10px;">Referencia del proveedor: <strong>' . email_escape($providerReference) . '</strong></p>' : '';
    $providerMessageText = '<p style="margin:0;">Respuesta del proveedor: <strong>' . email_escape($providerMessage) . '</strong></p>';

    $customerHtml = render_order_email('Pago verificado y recarga enviada', 'Cliente',
        '<p style="margin:0 0 10px;">Tu pago fue validado automáticamente y la recarga de Free Fire fue procesada con éxito.</p>'
        . $providerReferenceText
        . $providerMessageText,
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'Enviado',
        ],
        '#34d399'
    );
    $adminHtml = render_order_email('Recarga Free Fire enviada', 'Administrador',
        '<p style="margin:0 0 10px;">El pago fue validado automáticamente y la API de Free Fire respondió exitosamente.</p>'
        . $providerReferenceText
        . $providerMessageText,
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'Enviado',
        ],
        '#34d399'
    );

    if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        send_app_mail((string) $order['email'], "Recarga enviada #{$orderId}", $customerHtml, null, $brandingImages);
    }
    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Recarga Free Fire enviada #{$orderId}", $adminHtml, null, $brandingImages);
    }
}

function notify_free_fire_recharge_failure(
    mysqli $mysqli,
    array $order,
    string $paymentMethodName,
    string $referenceNumber,
    string $phone,
    string $providerMessage
): void {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return;
    }

    $adminEmail = resolve_admin_email($mysqli);
    $brandingImages = email_branding_embedded_images();
    $providerMessageText = '<p style="margin:0;">Respuesta del proveedor: <strong>' . email_escape($providerMessage) . '</strong></p>';

    $customerHtml = render_order_email('Pago verificado, recarga en revisión', 'Cliente',
        '<p style="margin:0 0 10px;">Tu pago sí fue validado automáticamente, pero la recarga no pudo completarse de forma inmediata.</p>'
        . '<p style="margin:0 0 10px;">Nuestro equipo ya fue notificado para revisar el caso manualmente.</p>'
        . $providerMessageText,
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'Verificado',
        ],
        '#f59e0b'
    );
    $adminHtml = render_order_email('Pago verificado, recarga Free Fire fallida', 'Administrador',
        '<p style="margin:0 0 10px;">El pago fue validado automáticamente, pero la API de Free Fire no completó la recarga.</p>'
        . '<p style="margin:0 0 10px;">El pedido quedó en estado verificado para revisión manual.</p>'
        . $providerMessageText,
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'Verificado',
        ],
        '#f59e0b'
    );

    if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        send_app_mail((string) $order['email'], "Pago verificado, recarga en revisión #{$orderId}", $customerHtml, null, $brandingImages);
    }
    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Recarga Free Fire en revisión #{$orderId}", $adminHtml, null, $brandingImages);
    }
}

function notify_bank_payment_verified_paid(
    mysqli $mysqli,
    array $order,
    string $paymentMethodName,
    string $referenceNumber,
    string $phone
): void {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return;
    }

    $adminEmail = resolve_admin_email($mysqli);
    $brandingImages = email_branding_embedded_images();

    $customerHtml = render_order_email('Pago verificado', 'Cliente',
        '<p style="margin:0 0 10px;">Tu pago fue verificado automáticamente contra los movimientos bancarios.</p>'
        . '<p style="margin:0;">La orden quedó en estado <strong style="color:#f59e0b;">Verificado</strong> para continuar con la gestión manual del producto.</p>',
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'Verificado',
        ],
        '#f59e0b'
    );
    $adminHtml = render_order_email('Pago verificado automáticamente', 'Administrador',
        '<p style="margin:0 0 10px;">El pago del cliente fue validado automáticamente con la API bancaria.</p>'
        . '<p style="margin:0;">La orden quedó en estado <strong style="color:#f59e0b;">Verificado</strong> para gestión manual.</p>',
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'Verificado',
        ],
        '#f59e0b'
    );

    if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        send_app_mail((string) $order['email'], "Pago verificado #{$orderId}", $customerHtml, null, $brandingImages);
    }
    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Pago verificado automáticamente #{$orderId}", $adminHtml, null, $brandingImages);
    }
}

function notify_bank_payment_pending_mismatch(
    mysqli $mysqli,
    array $order,
    string $paymentMethodName,
    string $referenceNumber,
    string $phone,
    array $reasons
): void {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return;
    }

    $adminEmail = resolve_admin_email($mysqli);
    $brandingImages = email_branding_embedded_images();
    $reasonHtml = '';
    if (!empty($reasons)) {
        $items = array_map(static fn ($reason) => '<li>' . email_escape((string) $reason) . '</li>', $reasons);
        $reasonHtml = '<ul style="margin:10px 0 0 18px;padding:0;color:#fecaca;">' . implode('', $items) . '</ul>';
    }

    $customerHtml = render_order_email('Pago no verificado', 'Cliente',
        '<p style="margin:0 0 10px;">No pudimos confirmar automáticamente tu pago con los datos enviados.</p>'
        . '<p style="margin:0 0 10px;">La orden se mantiene en estado <strong style="color:#22d3ee;">No Verificado</strong> para que puedas verificar la referencia e intentarlo nuevamente.</p>'
        . $reasonHtml,
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'No Verificado',
        ],
        '#22d3ee'
    );
    $adminHtml = render_order_email('Pago no verificado automáticamente', 'Administrador',
        '<p style="margin:0 0 10px;">La API bancaria no encontró coincidencia para este pago reportado.</p>'
        . '<p style="margin:0 0 10px;">La orden se mantiene en estado <strong style="color:#22d3ee;">No Verificado</strong>.</p>'
        . $reasonHtml,
        [
            'order_id' => $orderId,
            'game_name' => $order['juego_nombre'] ?? '',
            'pack_name' => $order['paquete_nombre'] ?? '',
            'pack_amount' => $order['paquete_cantidad'] ?? '',
            'currency' => $order['moneda'] ?? '',
            'price' => number_format((float) ($order['precio'] ?? 0), 2, '.', ','),
            'user_identifier' => $order['user_identifier'] ?? '',
            'email' => $order['email'] ?? '',
            'coupon' => $order['cupon'] ?? null,
            'payment_method' => $paymentMethodName,
            'reference_number' => $referenceNumber,
            'phone' => $phone,
            'status' => 'No Verificado',
        ],
        '#22d3ee'
    );

    if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        send_app_mail((string) $order['email'], "Pago no verificado #{$orderId}", $customerHtml, null, $brandingImages);
    }
    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Pago no verificado automáticamente #{$orderId}", $adminHtml, null, $brandingImages);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
if (!$action) {
    json_error('Acción no especificada', 422);
}

ensure_pedidos_table($mysqli);
ensure_movimientos_table($mysqli);
ensure_juegos_api_free_fire_column($mysqli);
ensure_juego_paquetes_monto_ff_column($mysqli);
influencer_coupon_ensure_sales_table_mysqli($mysqli);
sync_coupon_usage_counts_mysqli($mysqli);

if ($action === 'create') {
    $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : null;
    $package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    // Si no viene game_name, intentar obtenerlo por ID
    $game_name = sanitize_str($_POST['game_name'] ?? null, 180);
    $pack_name = sanitize_str($_POST['pack_name'] ?? null, 180);
    $pack_amount_text = sanitize_str($_POST['pack_amount'] ?? null, 80); // texto descriptivo
    $monto_ff = null;
    $pack_amount_num = 1;
    if ($pack_amount_text !== null && is_numeric($pack_amount_text)) {
        $pack_amount_num = intval($pack_amount_text);
    }
    $currency = sanitize_str($_POST['currency'] ?? null, 20);
    $price_raw = str_replace([',', ' '], '', $_POST['price'] ?? '0');
    $price = is_numeric($price_raw) ? floatval($price_raw) : 0;
    $user_identifier = sanitize_str($_POST['user_identifier'] ?? null, 150);
    $email = sanitize_str($_POST['email'] ?? null, 180);
    $cuponInput = sanitize_str($_POST['coupon'] ?? null, 60);
    $cupon = null;
    $cliente_usuario_id = isset($_SESSION['auth_user']['id']) ? intval($_SESSION['auth_user']['id']) : null;
    if ($cuponInput !== null) {
        if (!is_valid_coupon_code($cuponInput)) {
            json_error('El cupón solo puede contener letras y números, sin espacios ni caracteres especiales.');
        }
        $cupon = normalize_coupon_code($cuponInput);
    }
    $tenant_slug = sanitize_str($_POST['tenant_slug'] ?? null, 80);

    $missing = [];
    if (!$game_name && $game_id) {
        $stmtG = $mysqli->prepare('SELECT nombre FROM juegos WHERE id=? LIMIT 1');
        if ($stmtG) {
            $stmtG->bind_param('i', $game_id);
            $stmtG->execute();
            $resG = $stmtG->get_result();
            $rowG = $resG ? $resG->fetch_assoc() : null;
            if ($rowG && !empty($rowG['nombre'])) {
                $game_name = $rowG['nombre'];
            }
        }
    }

    $selectedPackage = null;
    if ($package_id > 0 && $game_id) {
        $selectedPackage = fetch_game_package($mysqli, $package_id, (int) $game_id);
        if ($selectedPackage) {
            $pack_name = sanitize_str((string) ($selectedPackage['nombre'] ?? $pack_name), 180);
            $pack_amount_text = sanitize_str((string) ($selectedPackage['cantidad'] ?? $pack_amount_text), 80);
            $monto_ff = sanitize_str((string) ($selectedPackage['monto_ff'] ?? ''), 20);
            if ($pack_amount_text !== null && is_numeric($pack_amount_text)) {
                $pack_amount_num = intval($pack_amount_text);
            }
        }
    }

    if (!$game_name) $missing[] = 'game_name';
    if ($package_id <= 0) $missing[] = 'package_id';
    if (!$pack_name) $missing[] = 'pack_name';
    if (!$currency) $missing[] = 'currency';
    if (!$user_identifier) $missing[] = 'user_identifier';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $missing[] = 'email';
    if (!empty($missing)) {
        json_error('Faltan datos obligatorios del pedido: ' . implode(', ', $missing));
    }

    if (!$selectedPackage) {
        json_error('El paquete seleccionado no existe para este juego.');
    }

    $selectedCurrency = currency_find_by_code((string) $currency);
    if (!$selectedCurrency) {
        json_error('La moneda seleccionada no es válida.');
    }
    $currency = currency_normalize_code((string) ($selectedCurrency['clave'] ?? $currency));
    $price = currency_convert_from_base((float) ($selectedPackage['precio'] ?? 0), $selectedCurrency);
    if ($price <= 0) {
        json_error('El paquete seleccionado no tiene un precio válido para la moneda elegida.');
    }

    if (game_uses_free_fire_api($mysqli, (int) $game_id) && $monto_ff === null) {
        json_error('Este paquete no tiene un monto API configurado para Free Fire.');
    }

    // Validar y aplicar cupón si existe
    if ($cupon) {
        $couponData = fetch_valid_coupon($mysqli, $cupon);
        if (!$couponData) {
            json_error('Cupón inválido o vencido');
        }
        $price = currency_apply_amount_rule(apply_coupon_to_price($price, $couponData), $selectedCurrency);
        // Registrar uso del cupón (best effort)
        if (isset($couponData['id'])) {
            $upd = $mysqli->prepare("UPDATE cupones SET usos_actuales = COALESCE(usos_actuales,0) + 1 WHERE id = ?");
            if ($upd) {
                $upd->bind_param('i', $couponData['id']);
                $upd->execute();
            }
        }
        // Aseguramos que el cupón se inserte como string, no como null
        $cupon = $couponData['codigo'];
    } else {
        $cupon = null;
    }

    $stmt = $mysqli->prepare("INSERT INTO pedidos (tenant_slug, juego_id, paquete_id, juego_nombre, paquete_nombre, paquete_cantidad, monto_ff, moneda, precio, user_identifier, email, cliente_usuario_id, cupon, cantidad, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pendiente')");
    if (!$stmt) {
        json_error('No se pudo preparar el pedido');
    }
    $stmt->bind_param('siisssssdssisi', $tenant_slug, $game_id, $package_id, $game_name, $pack_name, $pack_amount_text, $monto_ff, $currency, $price, $user_identifier, $email, $cliente_usuario_id, $cupon, $pack_amount_num);
    if (!$stmt->execute()) {
        json_error('No se pudo guardar el pedido');
    }
    $order_id = $mysqli->insert_id;
    $stmt->close();
    sync_coupon_usage_counts_mysqli($mysqli);
    $storedOrder = fetch_order_by_id($mysqli, $order_id);
    if ($storedOrder === null) {
        json_error('No se pudo recuperar el pedido recién creado.', 500);
    }
    $adminEmail = resolve_admin_email($mysqli);
    $defaultPaymentMethod = default_payment_method_for_currency($currency ?? '');

    $customerMessage = '<p style="margin:0 0 10px;">Tu pedido fue creado correctamente y quedó no verificado hasta confirmar el pago.</p>'
        . '<p style="margin:0;">Debes realizar el pago usando el método disponible para la moneda seleccionada y luego enviar tu referencia desde la pantalla de pago para que el administrador pueda revisarla.</p>'
        . payment_method_details_html($defaultPaymentMethod);
    $adminMessage = '<p style="margin:0 0 10px;">Se generó un nuevo pedido y ya está disponible para revisión en el panel administrativo.</p>'
        . '<p style="margin:0;">Valida los datos del cliente y procede con la gestión correspondiente.</p>';
    $customerHtml = render_order_email('Pedido creado, no verificado', 'Cliente', $customerMessage, [
        'order_id' => $order_id,
        'game_name' => $game_name,
        'pack_name' => $pack_name,
        'pack_amount' => $pack_amount_text,
        'currency' => $currency,
        'price' => format_order_price_value((float) $price, $currency),
        'user_identifier' => $user_identifier,
        'email' => $email,
        'coupon' => $cupon,
        'payment_method' => $defaultPaymentMethod['nombre'] ?? '',
        'status' => 'No Verificado',
    ]);
    $adminHtml = render_order_email('Nuevo pedido', 'Administrador', $adminMessage, [
        'order_id' => $order_id,
        'game_name' => $game_name,
        'pack_name' => $pack_name,
        'pack_amount' => $pack_amount_text,
        'currency' => $currency,
        'price' => format_order_price_value((float) $price, $currency),
        'user_identifier' => $user_identifier,
        'email' => $email,
        'coupon' => $cupon,
        'status' => 'No Verificado',
    ], '#34d399');
    $brandingImages = email_branding_embedded_images();
    send_app_mail($email, "Pedido creado #{$order_id} - no verificado", $customerHtml, null, $brandingImages);
    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Nuevo pedido #{$order_id}", $adminHtml, null, $brandingImages);
    }

    json_response([
        'ok' => true,
        'message' => 'Pedido registrado',
        'order_id' => $order_id,
        'estado' => 'pendiente',
        'created_at' => date(DATE_ATOM, isset($storedOrder['creado_en_ts']) ? (int) $storedOrder['creado_en_ts'] : time()),
        'expires_at' => order_expiration_iso($storedOrder),
        'remaining_seconds' => max(0, order_expiration_timestamp($storedOrder) - time())
    ]);
}

if ($action === 'submit_payment') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $paymentMethodId = intval($_POST['payment_method_id'] ?? 0);
    $referenceNumberRaw = trim((string) ($_POST['reference_number'] ?? ''));
    $phoneRaw = trim((string) ($_POST['phone'] ?? ''));

    if ($orderId <= 0) {
        json_error('Pedido inválido.');
    }
    if ($paymentMethodId <= 0) {
        json_error('Debes seleccionar un método de pago.');
    }
    if ($referenceNumberRaw === '') {
        json_error('Debes ingresar el número de referencia.');
    }
    if ($phoneRaw === '') {
        json_error('Debes ingresar un número de teléfono.');
    }
    if (preg_match('/^\d+$/', $referenceNumberRaw) !== 1) {
        json_error('El número de referencia solo puede contener dígitos.');
    }

    $order = fetch_order_by_id($mysqli, $orderId);
    if (!$order) {
        json_error('Pedido no encontrado.', 404);
    }
    if (($order['estado'] ?? '') !== 'pendiente') {
        json_error('El pedido ya no admite confirmación de pago.', 409);
    }
    if (order_is_expired($order)) {
        $expiration = cancel_expired_order($mysqli, $order);
        json_error($expiration['message'] ?: 'La orden ya expiró.', 409);
    }

    $method = fetch_active_payment_method($mysqli, $paymentMethodId);
    if (!$method) {
        json_error('El método de pago seleccionado no está disponible.');
    }

    $orderCurrencyCode = normalize_currency_code((string) ($order['moneda'] ?? ''));
    $methodCurrencyCode = normalize_currency_code((string) ($method['moneda_clave'] ?? ''));
    $orderSupportsBankApi = order_currency_uses_bank_api($orderCurrencyCode);
    $methodSupportsBankApi = order_currency_uses_bank_api($methodCurrencyCode);
    $currencyMatchesOrder = strcasecmp($methodCurrencyCode, $orderCurrencyCode) === 0;

    if ($orderSupportsBankApi && !$currencyMatchesOrder) {
        json_error('El método de pago no corresponde a la moneda del pedido.');
    }

    $referenceDigitsLimit = max(0, (int) ($method['referencia_digitos'] ?? 0));
    if ($referenceDigitsLimit > 0 && strlen($referenceNumberRaw) !== $referenceDigitsLimit) {
        json_error('La referencia debe contener exactamente ' . $referenceDigitsLimit . ' dígitos.');
    }

    $phone = substr($phoneRaw, 0, 40);
    $referenceNumber = substr($referenceNumberRaw, 0, 120);

    $stmt = $mysqli->prepare('UPDATE pedidos SET numero_referencia = ?, telefono_contacto = ? WHERE id = ? AND estado = ?');
    if (!$stmt) {
        json_error('No se pudo actualizar el pedido.', 500);
    }
    $expectedStatus = 'pendiente';
    $stmt->bind_param('ssis', $referenceNumber, $phone, $orderId, $expectedStatus);
    if (!$stmt->execute()) {
        $stmt->close();
        json_error('No se pudieron guardar los datos del pago.', 500);
    }
    $stmt->close();

    $updatedOrder = fetch_order_by_id($mysqli, $orderId) ?: $order;
    $adminEmail = resolve_admin_email($mysqli);
    $paymentMethodName = (string) ($method['nombre'] ?? 'Método de pago');
    $brandingImages = email_branding_embedded_images();
    $usesFreeFireApi = game_uses_free_fire_api($mysqli, (int) ($updatedOrder['juego_id'] ?? 0));
    $bankFlowRequested = $orderSupportsBankApi || $methodSupportsBankApi;
    $usesBankValidation = $orderSupportsBankApi && $methodSupportsBankApi && $currencyMatchesOrder;

    $bankConfig = [
        'ff_bank_posicion' => store_config_get('ff_bank_posicion', '0'),
        'ff_bank_token' => store_config_get('ff_bank_token', ''),
        'ff_bank_clave' => store_config_get('ff_bank_clave', ''),
    ];
    $bankMovements = [];

    if ($bankFlowRequested) {
        try {
            $bankMovements = fetch_and_sync_bank_movements($mysqli, $bankConfig);
        } catch (Throwable $e) {
            json_error('No pudimos validar el pago por respuesta del servidor bancario. Espera un momento y vuelve a intentarlo, o contacta al administrador si ya te debitaron el pago.', 502);
        }

        if (!$usesBankValidation) {
            error_log('TVG bank validation skipped for order #' . $orderId
                . ' order_currency=' . ($order['moneda'] ?? '')
                . ' normalized_order_currency=' . $orderCurrencyCode
                . ' method_currency=' . (($method['moneda_clave'] ?? ''))
                . ' normalized_method_currency=' . $methodCurrencyCode
                . ' bank_flow_requested=' . ($bankFlowRequested ? '1' : '0'));
        }
    }

    if ($usesBankValidation) {
        $matchingMovement = find_matching_bank_movement(
            $mysqli,
            $bankMovements,
            $referenceNumber,
            (float) ($updatedOrder['precio'] ?? 0),
            $referenceDigitsLimit,
            $orderId
        );

        if ($matchingMovement === null) {
            try {
                $retryResult = find_matching_bank_movement_with_retry(
                    $mysqli,
                    $bankConfig,
                    $referenceNumber,
                    (float) ($updatedOrder['precio'] ?? 0),
                    $referenceDigitsLimit,
                    $orderId,
                    3,
                    5,
                    $bankMovements
                );
                $matchingMovement = $retryResult['match'];
                $bankMovements = $retryResult['movements'];
                error_log('TVG bank validation attempts for order #' . $orderId . ': ' . (int) ($retryResult['attempts'] ?? 1));
            } catch (Throwable $e) {
                json_error('No pudimos validar el pago por respuesta del servidor bancario. Espera un momento y vuelve a intentarlo, o contacta al administrador si ya te debitaron el pago.', 502);
            }
        }

        if ($matchingMovement !== null) {
            $verifiedReference = (string) ($matchingMovement['referencia'] ?? $referenceNumber);
            link_movement_to_order($mysqli, $verifiedReference, $orderId);

            if (!$usesFreeFireApi) {
                $paidStatus = 'pagado';
                $paidStmt = $mysqli->prepare("UPDATE pedidos SET numero_referencia = ?, telefono_contacto = ?, estado = ? WHERE id = ? AND estado = 'pendiente'");
                if (!$paidStmt) {
                    json_error('No se pudo confirmar el pago automáticamente.', 500);
                }
                $paidStmt->bind_param('sssi', $verifiedReference, $phone, $paidStatus, $orderId);
                if (!$paidStmt->execute()) {
                    $paidStmt->close();
                    json_error('No se pudo actualizar el pedido tras validar el pago.', 500);
                }
                $paidStmt->close();

                $paidOrder = fetch_order_by_id($mysqli, $orderId) ?: $updatedOrder;
                json_response([
                    'ok' => true,
                    'message' => 'Pago verificado automáticamente. Tu pedido quedó en estado verificado.',
                    'order_id' => $orderId,
                    'estado' => 'pagado',
                    'verified' => true,
                ], 200, static function () use ($mysqli, $paidOrder, $paymentMethodName, $verifiedReference, $phone): void {
                    register_influencer_coupon_sale($mysqli, $paidOrder);
                    notify_bank_payment_verified_paid($mysqli, $paidOrder, $paymentMethodName, $verifiedReference, $phone);
                });
            }

            $montoFf = trim((string) ($updatedOrder['monto_ff'] ?? ''));
            $freeFireConfig = free_fire_api_config();

            try {
                $freeFireResult = execute_free_fire_recharge($freeFireConfig, $montoFf, (string) ($updatedOrder['user_identifier'] ?? ''));
            } catch (Throwable $e) {
                $freeFireResult = [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'reference' => '',
                    'payload' => ['exception' => $e->getMessage()],
                ];
            }

            $providerReference = (string) ($freeFireResult['reference'] ?? '');
            $providerMessage = (string) ($freeFireResult['message'] ?? 'No se recibió mensaje del proveedor.');
            $providerPayload = json_encode($freeFireResult['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!empty($freeFireResult['success'])) {
                $verifiedStatus = 'enviado';
                $verifyStmt = $mysqli->prepare("UPDATE pedidos SET numero_referencia = ?, telefono_contacto = ?, ff_api_referencia = ?, ff_api_mensaje = ?, ff_api_payload = ?, estado = ? WHERE id = ? AND estado = 'pendiente'");
                if (!$verifyStmt) {
                    json_error('No se pudo confirmar la recarga automáticamente.', 500);
                }
                $verifyStmt->bind_param('ssssssi', $verifiedReference, $phone, $providerReference, $providerMessage, $providerPayload, $verifiedStatus, $orderId);
                if (!$verifyStmt->execute()) {
                    $verifyStmt->close();
                    json_error('No se pudo actualizar el pedido tras procesar la recarga.', 500);
                }
                $verifyStmt->close();

                $verifiedOrder = fetch_order_by_id($mysqli, $orderId) ?: $updatedOrder;
                json_response([
                    'ok' => true,
                    'message' => 'Pago verificado y recarga Free Fire procesada correctamente.',
                    'order_id' => $orderId,
                    'estado' => 'enviado',
                    'verified' => true,
                    'provider_reference' => $providerReference,
                    'provider_message' => $providerMessage,
                ], 200, static function () use ($mysqli, $verifiedOrder, $paymentMethodName, $verifiedReference, $phone, $providerReference, $providerMessage): void {
                    register_influencer_coupon_sale($mysqli, $verifiedOrder);
                    notify_free_fire_recharge_success($mysqli, $verifiedOrder, $paymentMethodName, $verifiedReference, $phone, $providerReference, $providerMessage);
                });
            }

            $paidStatus = 'pagado';
            $paidStmt = $mysqli->prepare("UPDATE pedidos SET numero_referencia = ?, telefono_contacto = ?, ff_api_referencia = ?, ff_api_mensaje = ?, ff_api_payload = ?, estado = ? WHERE id = ? AND estado = 'pendiente'");
            if (!$paidStmt) {
                json_error('No se pudo actualizar el pedido tras validar el pago.', 500);
            }
            $paidStmt->bind_param('ssssssi', $verifiedReference, $phone, $providerReference, $providerMessage, $providerPayload, $paidStatus, $orderId);
            if (!$paidStmt->execute()) {
                $paidStmt->close();
                json_error('No se pudo marcar el pedido como pagado.', 500);
            }
            $paidStmt->close();

            $paidOrder = fetch_order_by_id($mysqli, $orderId) ?: $updatedOrder;
            json_response([
                'ok' => true,
                'message' => 'El pago fue verificado, pero la recarga Free Fire no pudo completarse automáticamente. Nuestro equipo revisará tu pedido.',
                'order_id' => $orderId,
                'estado' => 'pagado',
                'verified' => true,
                'reasons' => [$providerMessage],
                'provider_reference' => $providerReference,
                'provider_message' => $providerMessage,
            ], 200, static function () use ($mysqli, $paidOrder, $paymentMethodName, $verifiedReference, $phone, $providerMessage): void {
                register_influencer_coupon_sale($mysqli, $paidOrder);
                notify_free_fire_recharge_failure($mysqli, $paidOrder, $paymentMethodName, $verifiedReference, $phone, $providerMessage);
            });
        }

        $mismatch = explain_bank_movement_mismatch($bankMovements, $referenceNumber, (float) ($updatedOrder['precio'] ?? 0), $referenceDigitsLimit);
        $pendingOrder = fetch_order_by_id($mysqli, $orderId) ?: $updatedOrder;
        json_response([
            'ok' => true,
            'message' => 'No pudimos validar el pago automáticamente en este momento.',
            'order_id' => $orderId,
            'estado' => 'pendiente',
            'verified' => false,
            'bank_checked' => true,
            'reasons' => $mismatch['reasons'],
            'reference_match' => $mismatch['reference_match'],
            'amount_match' => $mismatch['amount_match'],
            'failure_type' => $mismatch['failure_type'],
        ], 200, static function () use ($mysqli, $pendingOrder, $paymentMethodName, $referenceNumber, $phone, $mismatch): void {
            notify_bank_payment_pending_mismatch(
                $mysqli,
                $pendingOrder,
                $paymentMethodName,
                $referenceNumber,
                $phone,
                $mismatch['reasons']
            );
        });
    }

    $customerMessage = '<p style="margin:0 0 10px;">Recibimos tu pago reportado y ya quedó enviado al equipo administrativo para validación.</p>'
        . '<p style="margin:0;">Cuando el administrador lo revise y apruebe, te notificaremos el siguiente cambio de estado.</p>';
    $adminMessage = '<p style="margin:0 0 10px;">El cliente reportó el pago de este pedido y quedó no verificado hasta la aprobación administrativa.</p>'
        . '<p style="margin:0;">Valida la referencia y el teléfono de contacto antes de aprobar la orden.</p>';

    $customerHtml = render_order_email('Pago reportado', 'Cliente', $customerMessage, [
        'order_id' => $orderId,
        'game_name' => $updatedOrder['juego_nombre'] ?? '',
        'pack_name' => $updatedOrder['paquete_nombre'] ?? '',
        'pack_amount' => $updatedOrder['paquete_cantidad'] ?? '',
        'currency' => $updatedOrder['moneda'] ?? '',
        'price' => number_format((float) ($updatedOrder['precio'] ?? 0), 2, '.', ','),
        'user_identifier' => $updatedOrder['user_identifier'] ?? '',
        'email' => $updatedOrder['email'] ?? '',
        'coupon' => $updatedOrder['cupon'] ?? null,
        'payment_method' => $paymentMethodName,
        'reference_number' => $referenceNumber,
        'phone' => $phone,
        'status' => 'Pago enviado para revisión',
    ], '#f59e0b');
    $adminHtml = render_order_email('Pago recibido para revisión', 'Administrador', $adminMessage, [
        'order_id' => $orderId,
        'game_name' => $updatedOrder['juego_nombre'] ?? '',
        'pack_name' => $updatedOrder['paquete_nombre'] ?? '',
        'pack_amount' => $updatedOrder['paquete_cantidad'] ?? '',
        'currency' => $updatedOrder['moneda'] ?? '',
        'price' => number_format((float) ($updatedOrder['precio'] ?? 0), 2, '.', ','),
        'user_identifier' => $updatedOrder['user_identifier'] ?? '',
        'email' => $updatedOrder['email'] ?? '',
        'coupon' => $updatedOrder['cupon'] ?? null,
        'payment_method' => $paymentMethodName,
        'reference_number' => $referenceNumber,
        'phone' => $phone,
        'status' => 'No Verificado',
    ], '#f59e0b');

    json_response([
        'ok' => true,
        'message' => 'Datos de pago enviados correctamente. Tu pedido sigue no verificado.',
        'order_id' => $orderId,
        'estado' => 'pendiente'
    ], 200, static function () use ($mysqli, $updatedOrder, $adminEmail, $customerHtml, $adminHtml, $brandingImages, $orderId): void {
        register_influencer_coupon_sale($mysqli, $updatedOrder);
        if (!empty($updatedOrder['email']) && filter_var($updatedOrder['email'], FILTER_VALIDATE_EMAIL)) {
            send_app_mail((string) $updatedOrder['email'], "Pago reportado #{$orderId}", $customerHtml, null, $brandingImages);
        }
        if ($adminEmail !== null) {
            send_app_mail($adminEmail, "Pago reportado #{$orderId}", $adminHtml, null, $brandingImages);
        }
    });
}

if ($action === 'expire_order') {
    $orderId = intval($_POST['order_id'] ?? 0);
    if ($orderId <= 0) {
        json_error('Pedido inválido.');
    }
    $order = fetch_order_by_id($mysqli, $orderId);
    if (!$order) {
        json_error('Pedido no encontrado.', 404);
    }

    if (($order['estado'] ?? '') !== 'pendiente') {
        json_response([
            'ok' => true,
            'expired' => ($order['estado'] ?? '') === 'cancelado',
            'message' => 'El pedido ya fue procesado previamente.',
            'estado' => $order['estado'] ?? ''
        ]);
    }

    if (!order_is_expired($order)) {
        json_response([
            'ok' => true,
            'expired' => false,
            'message' => 'La orden aún sigue activa.',
            'remaining_seconds' => max(0, order_expiration_timestamp($order) - time())
        ]);
    }

    $result = cancel_expired_order($mysqli, $order);
    json_response([
        'ok' => true,
        'expired' => true,
        'message' => $result['message']
    ]);
}

if ($action === 'cancel_order') {
    $orderId = intval($_POST['order_id'] ?? 0);
    if ($orderId <= 0) {
        json_error('Pedido inválido.');
    }

    $order = fetch_order_by_id($mysqli, $orderId);
    if (!$order) {
        json_error('Pedido no encontrado.', 404);
    }

    $result = cancel_pending_order_by_customer($mysqli, $order);
    if (!$result['changed']) {
        json_error($result['message'], 409);
    }

    json_response([
        'ok' => true,
        'message' => $result['message'],
        'estado' => 'cancelado',
        'order_id' => $orderId,
    ]);
}

if ($action === 'update_status') {
    if (!isset($_SESSION['auth_user']) || ($_SESSION['auth_user']['rol'] ?? '') !== 'admin') {
        json_error('No autorizado', 403);
    }
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = sanitize_str($_POST['estado'] ?? null, 20);
    $valid = ['pendiente','pagado','enviado','cancelado'];
    if (!$order_id || !in_array($new_status, $valid, true)) {
        json_error('Datos de estado inválidos');
    }

    $res = $mysqli->prepare('SELECT id, email, user_identifier, juego_nombre, paquete_nombre, paquete_cantidad, moneda, precio, estado, cupon FROM pedidos WHERE id=? LIMIT 1');
    $res->bind_param('i', $order_id);
    $res->execute();
    $order = $res->get_result()->fetch_assoc();
    if (!$order) {
        json_error('Pedido no encontrado', 404);
    }

    $stmt = $mysqli->prepare('UPDATE pedidos SET estado=? WHERE id=?');
    $stmt->bind_param('si', $new_status, $order_id);
    $stmt->execute();

    if (in_array($new_status, ['pagado', 'enviado'], true) && !in_array((string) ($order['estado'] ?? ''), ['pagado', 'enviado'], true)) {
        register_influencer_coupon_sale($mysqli, [
            'id' => $order_id,
            'cupon' => $order['cupon'] ?? null,
            'paquete_nombre' => $order['paquete_nombre'] ?? null,
            'moneda' => $order['moneda'] ?? null,
            'precio' => $order['precio'] ?? 0,
        ]);
    }

    $adminEmail = resolve_admin_email($mysqli);
    $statusLabel = ucfirst($new_status);
    $customerStatusMessage = '<p style="margin:0 0 10px;">El estado de tu pedido fue actualizado correctamente.</p>'
        . '<p style="margin:0;">Estado actual: <strong style="color:#22d3ee;">' . email_escape($statusLabel) . '</strong>.</p>';
    $adminStatusMessage = '<p style="margin:0 0 10px;">Se actualizó el estado de un pedido desde el panel administrativo.</p>'
        . '<p style="margin:0;">Estado actual: <strong style="color:#34d399;">' . email_escape($statusLabel) . '</strong>.</p>';
    $customerStatusHtml = render_order_email('Estado actualizado', 'Cliente', $customerStatusMessage, [
        'order_id' => $order_id,
        'game_name' => $order['juego_nombre'],
        'pack_name' => $order['paquete_nombre'],
        'pack_amount' => $order['paquete_cantidad'],
        'currency' => $order['moneda'],
        'price' => number_format((float) $order['precio'], 2, '.', ','),
        'user_identifier' => $order['user_identifier'],
        'email' => $order['email'],
        'status' => $statusLabel,
    ]);
    $adminStatusHtml = render_order_email('Pedido actualizado', 'Administrador', $adminStatusMessage, [
        'order_id' => $order_id,
        'game_name' => $order['juego_nombre'],
        'pack_name' => $order['paquete_nombre'],
        'pack_amount' => $order['paquete_cantidad'],
        'currency' => $order['moneda'],
        'price' => number_format((float) $order['precio'], 2, '.', ','),
        'user_identifier' => $order['user_identifier'],
        'email' => $order['email'],
        'coupon' => null,
        'status' => $statusLabel,
    ], '#34d399');
    $brandingImages = email_branding_embedded_images();
    send_app_mail($order['email'], "Estado actualizado #{$order_id}", $customerStatusHtml, null, $brandingImages);
    if ($adminEmail !== null) {
        send_app_mail($adminEmail, "Pedido #{$order_id} cambiado a {$new_status}", $adminStatusHtml, null, $brandingImages);
    }

    json_response(['ok' => true, 'message' => 'Estado actualizado', 'estado' => $new_status, 'order_id' => $order_id]);
}

json_error('Acción no soportada', 422);
?>
