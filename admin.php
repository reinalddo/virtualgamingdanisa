<?php
require_once __DIR__ . '/includes/app_session.php';
app_session_start();

function admin_allowed_roles(): array {
    return ['admin', 'empleado'];
}

function admin_default_section_for_role(string $role): string {
    return $role === 'empleado' ? 'pedidos' : 'dashboard';
}

function admin_user_can_access_section(string $role, string $section): bool {
    if ($role === 'admin') {
        return true;
    }

    if ($role === 'empleado') {
        return in_array($section, ['pedidos', 'movimientos'], true);
    }

    return false;
}

// Verificar si el usuario puede entrar al admin
$adminUserRole = trim((string) ($_SESSION['auth_user']['rol'] ?? ''));
if (!isset($_SESSION['auth_user']) || !in_array($adminUserRole, admin_allowed_roles(), true)) {
    header('Location: login.php');
    exit();
}

// Definir la variable $seccion
$seccion = $_GET['seccion'] ?? 'dashboard';
if (isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/admin/([a-zA-Z0-9_-]+)#', $_SERVER['REQUEST_URI'], $m)) {
        $seccion = $m[1];
    }
}

if (!admin_user_can_access_section($adminUserRole, $seccion)) {
    admin_set_flash('error', 'No tienes permisos para acceder a esa sección.');
    admin_redirect(admin_default_section_for_role($adminUserRole));
}

function normalize_coupon_code(string $value): string {
    return strtoupper(trim($value));
}

function is_valid_coupon_code(string $value): bool {
    return $value !== '' && preg_match('/^[A-Za-z0-9]+$/', $value) === 1;
}

function admin_set_flash(string $type, string $message): void {
    $_SESSION['auth_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function admin_redirect(string $section, array $query = []): void {
    $target = '/admin/' . $section;
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target);
    exit();
}

function admin_display_value($value, string $fallback = '—'): string {
    $text = trim((string) $value);
    return $text !== '' ? $text : $fallback;
}

function admin_format_money($amount): string {
    return number_format((float) $amount, 2, '.', ',');
}

function admin_normalize_influencer_payment_filter($value): string {
    $filter = trim((string) $value);
    return in_array($filter, ['pendiente', 'pagado', 'todos'], true) ? $filter : 'pendiente';
}

function admin_normalize_date_filter($value): ?string {
    $date = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
}

function admin_normalize_positive_page($value): int {
    $page = (int) $value;
    return $page > 0 ? $page : 1;
}

function admin_normalize_per_page($value, int $default = 15): int {
    $perPage = (int) $value;
    return in_array($perPage, [15, 30, 50], true) ? $perPage : $default;
}

function admin_normalize_sort_direction($value, string $default = 'desc'): string {
    $direction = strtolower(trim((string) $value));
    return in_array($direction, ['asc', 'desc'], true) ? $direction : $default;
}

function admin_normalize_movement_sort_column($value): string {
    $column = trim((string) $value);
    $allowed = ['referencia', 'descripcion', 'fecha_movimiento', 'monto', 'moneda'];
    return in_array($column, $allowed, true) ? $column : 'fecha_movimiento';
}

function admin_normalize_movement_checked_filter($value): string {
    $filter = trim((string) $value);
    $allowed = ['no_verificados', 'verificados', 'todos'];
    return in_array($filter, $allowed, true) ? $filter : 'no_verificados';
}

function admin_normalize_movement_order_link_filter($value): string {
    $filter = trim((string) $value);
    $allowed = ['todos', 'con_pedido', 'sin_pedido'];
    return in_array($filter, $allowed, true) ? $filter : 'todos';
}

function admin_build_url(string $path, array $query = []): string {
    $query = array_filter($query, static function ($value) {
        return $value !== null && $value !== '';
    });

    if (empty($query)) {
        return $path;
    }

    return $path . '?' . http_build_query($query);
}

function admin_is_ajax_request(): bool {
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));

    return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function admin_movement_query_from_input(array $input): array {
    $query = [];

    $reference = trim((string) ($input['referencia'] ?? ''));
    if ($reference !== '') {
        $query['referencia'] = $reference;
    }

    $dateFrom = admin_normalize_date_filter($input['fecha_desde'] ?? null);
    if ($dateFrom !== null) {
        $query['fecha_desde'] = $dateFrom;
    }

    $dateTo = admin_normalize_date_filter($input['fecha_hasta'] ?? null);
    if ($dateTo !== null) {
        $query['fecha_hasta'] = $dateTo;
    }

    $currency = strtoupper(trim((string) ($input['moneda'] ?? '')));
    if ($currency !== '' && preg_match('/^[A-Z0-9_-]{1,20}$/', $currency) === 1) {
        $query['moneda'] = $currency;
    }

    $query['estado_verificacion'] = admin_normalize_movement_checked_filter($input['estado_verificacion'] ?? 'no_verificados');
    $query['pedido_relacionado'] = admin_normalize_movement_order_link_filter($input['pedido_relacionado'] ?? 'todos');

    $query['orden'] = admin_normalize_movement_sort_column($input['orden'] ?? 'fecha_movimiento');
    $query['direccion'] = admin_normalize_sort_direction($input['direccion'] ?? 'desc');
    $query['por_pagina'] = admin_normalize_per_page($input['por_pagina'] ?? 15);
    $query['pagina'] = admin_normalize_positive_page($input['pagina'] ?? 1);

    return $query;
}

function admin_parse_bank_movement_datetime(?string $value): ?string {
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

function admin_normalize_bank_amount($value): float {
    if (is_numeric($value)) {
        return round((float) $value, 2);
    }

    $clean = str_replace([',', ' '], '', (string) $value);
    return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
}

function admin_http_get_json(string $url, int $timeout = 20, bool $verifySsl = true): array {
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

function admin_fetch_bank_movements_from_api(array $config): array {
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

    $data = admin_http_get_json($url, 20, false);
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
            'descripcion' => substr(trim((string) ($movement['descripcion'] ?? '')), 0, 255),
            'fecha_raw' => substr(trim((string) ($movement['fecha'] ?? '')), 0, 120),
            'fecha_movimiento' => admin_parse_bank_movement_datetime((string) ($movement['fecha'] ?? '')),
            'tipo' => substr(trim((string) ($movement['tipo'] ?? '')), 0, 80),
            'monto' => admin_normalize_bank_amount($movement['monto'] ?? 0),
            'moneda' => 'VES',
            'payload_json' => json_encode($movement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    return $normalized;
}

function admin_sync_bank_movements(PDO $pdo, array $movements): array {
    if (empty($movements)) {
        return [
            'inserted' => 0,
            'updated' => 0,
            'processed' => 0,
        ];
    }

    $references = [];
    foreach ($movements as $movement) {
        $reference = trim((string) ($movement['referencia'] ?? ''));
        if ($reference !== '') {
            $references[$reference] = true;
        }
    }

    $existingReferences = [];
    $referenceList = array_keys($references);
    foreach (array_chunk($referenceList, 200) as $referenceChunk) {
        if (empty($referenceChunk)) {
            continue;
        }

        $placeholders = implode(', ', array_fill(0, count($referenceChunk), '?'));
        $existingStmt = $pdo->prepare('SELECT referencia FROM movimientos WHERE referencia IN (' . $placeholders . ')');
        $existingStmt->execute($referenceChunk);
        foreach ($existingStmt->fetchAll(PDO::FETCH_COLUMN) as $existingReference) {
            $existingReferences[(string) $existingReference] = true;
        }
    }

    $syncStmt = $pdo->prepare(
        'INSERT INTO movimientos (referencia, descripcion, fecha_raw, fecha_movimiento, tipo, monto, moneda, payload_json) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), fecha_raw = VALUES(fecha_raw), fecha_movimiento = COALESCE(VALUES(fecha_movimiento), fecha_movimiento), tipo = VALUES(tipo), monto = VALUES(monto), moneda = VALUES(moneda), payload_json = VALUES(payload_json)'
    );

    $inserted = 0;
    $updated = 0;
    foreach ($movements as $movement) {
        $reference = (string) ($movement['referencia'] ?? '');
        if ($reference === '') {
            continue;
        }

        $wasExisting = isset($existingReferences[$reference]);
        $syncStmt->execute([
            $reference,
            (string) ($movement['descripcion'] ?? ''),
            (string) ($movement['fecha_raw'] ?? ''),
            $movement['fecha_movimiento'] !== null ? (string) $movement['fecha_movimiento'] : null,
            (string) ($movement['tipo'] ?? ''),
            (float) ($movement['monto'] ?? 0),
            (string) ($movement['moneda'] ?? 'VES'),
            (string) ($movement['payload_json'] ?? ''),
        ]);

        if ($wasExisting) {
            $updated++;
        } else {
            $inserted++;
        }
    }

    return [
        'inserted' => $inserted,
        'updated' => $updated,
        'processed' => $inserted + $updated,
    ];
}

require_once __DIR__ . '/includes/influencer_coupons.php';

switch ($seccion) {
    case 'usuarios':
        require_once __DIR__ . '/includes/db.php';
        if (isset($_GET['borrar_usuario'])) {
            $id = intval($_GET['borrar_usuario']);
            if ($id === 1) {
                admin_set_flash('error', 'No puedes eliminar el admin principal.');
            } else {
                $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                admin_set_flash('success', 'Usuario eliminado.');
            }
            admin_redirect('usuarios');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
            $id = intval($_POST['id']);
            $nombre = trim($_POST['nombre'] ?? '');
            $rol = $_POST['rol'] ?? 'usuario';
            if ($id && $nombre && in_array($rol, ['usuario', 'admin', 'empleado'], true)) {
                $pdo->prepare('UPDATE usuarios SET nombre = ?, rol = ? WHERE id = ?')->execute([$nombre, $rol, $id]);
                admin_set_flash('success', 'Usuario actualizado.');
            } else {
                admin_set_flash('error', 'Datos inválidos para actualizar el usuario.');
            }
            admin_redirect('usuarios');
        }
        break;

    case 'juegos':
        if (file_exists(__DIR__ . '/includes/db.php')) {
            require_once __DIR__ . '/includes/db.php';
        }
        if (isset($pdo)) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_juego'])) {
                $nombre = $_POST['nombre'] ?? '';
                $descripcion = $_POST['descripcion'] ?? '';
                $precio = $_POST['precio'] ?? 0;
                $imagen = $_POST['imagen'] ?? '';
                $stmt = $pdo->prepare('INSERT INTO juegos (nombre, descripcion, precio, imagen) VALUES (?, ?, ?, ?)');
                $stmt->execute([$nombre, $descripcion, $precio, $imagen]);
                admin_set_flash('success', 'Juego agregado correctamente.');
                admin_redirect('juegos');
            }

            if (isset($_GET['borrar_juego'])) {
                $id = intval($_GET['borrar_juego']);
                $pdo->prepare('DELETE FROM juegos WHERE id = ?')->execute([$id]);
                admin_set_flash('success', 'Juego eliminado.');
                admin_redirect('juegos');
            }
        }
        break;

    case 'cupones':
        require_once __DIR__ . '/includes/db.php';
        influencer_coupon_ensure_sales_table_pdo($pdo);
        ensure_influencer_payment_status_column_pdo($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estado_pago_influencer'])) {
            $pedidoId = intval($_POST['pedido_id'] ?? 0);
            $nuevoEstadoPago = trim((string) ($_POST['estado_pago_influencer'] ?? 'pendiente')) === 'pagado' ? 'pagado' : 'pendiente';
            $redirectFilter = admin_normalize_influencer_payment_filter($_POST['filtro_estado_pago'] ?? 'pendiente');
            $redirectDateFrom = admin_normalize_date_filter($_POST['fecha_desde'] ?? null);
            $redirectDateTo = admin_normalize_date_filter($_POST['fecha_hasta'] ?? null);
            if ($pedidoId > 0) {
                $stmt = $pdo->prepare('UPDATE pedidos SET estado_pago_influencer = ? WHERE id = ?');
                $stmt->execute([$nuevoEstadoPago, $pedidoId]);
                admin_set_flash('success', 'Estado de comisión actualizado.');
            } else {
                admin_set_flash('error', 'Pedido inválido para actualizar la comisión.');
            }
            $redirectQuery = ['tab' => 'influencers', 'filtro_estado_pago' => $redirectFilter];
            if ($redirectDateFrom !== null) {
                $redirectQuery['fecha_desde'] = $redirectDateFrom;
            }
            if ($redirectDateTo !== null) {
                $redirectQuery['fecha_hasta'] = $redirectDateTo;
            }
            admin_redirect('cupones', $redirectQuery);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_cupon'])) {
            $codigoInput = trim($_POST['codigo'] ?? '');
            $codigo = normalize_coupon_code($codigoInput);
            $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
            $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
            $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
            $limite_usos = ($_POST['limite_usos'] ?? '') !== '' ? intval($_POST['limite_usos']) : null;
            $activo = isset($_POST['activo']) ? 1 : 0;
            $influencerPayload = influencer_coupon_payload_from_input($_POST);
            $influencerErrors = influencer_coupon_validate_payload($influencerPayload);

            if (!is_valid_coupon_code($codigoInput)) {
                admin_set_flash('error', 'El código del cupón solo puede contener letras y números, sin espacios, acentos ni caracteres especiales.');
            } elseif (!empty($influencerErrors)) {
                admin_set_flash('error', implode(' ', $influencerErrors));
            } else {
                $stmt_check = $pdo->prepare('SELECT 1 FROM cupones WHERE codigo = ? LIMIT 1');
                $stmt_check->execute([$codigo]);
                if ($stmt_check->fetch()) {
                    admin_set_flash('error', 'Ya existe un cupón con ese código.');
                } elseif ($codigo && $valor_descuento > 0 && in_array($tipo_descuento, ['porcentaje', 'fijo'], true)) {
                    $stmt = $pdo->prepare('INSERT INTO cupones (codigo, tipo_descuento, valor_descuento, fecha_expiracion, limite_usos, activo, nombre_influencer, telefono_influencer, email_influencer, comision_influencer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $codigo,
                        $tipo_descuento,
                        $valor_descuento,
                        $fecha_expiracion !== '' ? $fecha_expiracion : null,
                        $limite_usos,
                        $activo,
                        $influencerPayload['nombre_influencer'],
                        $influencerPayload['telefono_influencer'],
                        $influencerPayload['email_influencer'],
                        $influencerPayload['comision_influencer'],
                    ]);
                    admin_set_flash('success', 'Cupón creado correctamente.');
                } else {
                    admin_set_flash('error', 'Datos inválidos para el cupón.');
                }
            }
            admin_redirect('cupones');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_cupon'])) {
            $id = intval($_POST['id'] ?? 0);
            $codigoInput = trim($_POST['codigo'] ?? '');
            $codigo = normalize_coupon_code($codigoInput);
            $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
            $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
            $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
            $limite_usos = ($_POST['limite_usos'] ?? '') !== '' ? intval($_POST['limite_usos']) : null;
            $activo = isset($_POST['activo']) ? 1 : 0;
            $influencerPayload = influencer_coupon_payload_from_input($_POST);
            $influencerErrors = influencer_coupon_validate_payload($influencerPayload);

            if (!is_valid_coupon_code($codigoInput)) {
                admin_set_flash('error', 'El código del cupón solo puede contener letras y números, sin espacios, acentos ni caracteres especiales.');
            } elseif (!empty($influencerErrors)) {
                admin_set_flash('error', implode(' ', $influencerErrors));
            } else {
                $stmt_check = $pdo->prepare('SELECT 1 FROM cupones WHERE codigo = ? AND id <> ? LIMIT 1');
                $stmt_check->execute([$codigo, $id]);
                if ($stmt_check->fetch()) {
                    admin_set_flash('error', 'Ya existe un cupón con ese código.');
                } elseif ($id && $codigo && $valor_descuento > 0 && in_array($tipo_descuento, ['porcentaje', 'fijo'], true)) {
                    $stmt = $pdo->prepare('UPDATE cupones SET codigo=?, tipo_descuento=?, valor_descuento=?, fecha_expiracion=?, limite_usos=?, activo=?, nombre_influencer=?, telefono_influencer=?, email_influencer=?, comision_influencer=? WHERE id=?');
                    $stmt->execute([
                        $codigo,
                        $tipo_descuento,
                        $valor_descuento,
                        $fecha_expiracion !== '' ? $fecha_expiracion : null,
                        $limite_usos,
                        $activo,
                        $influencerPayload['nombre_influencer'],
                        $influencerPayload['telefono_influencer'],
                        $influencerPayload['email_influencer'],
                        $influencerPayload['comision_influencer'],
                        $id,
                    ]);
                    admin_set_flash('success', 'Cupón actualizado correctamente.');
                } else {
                    admin_set_flash('error', 'Datos inválidos para el cupón.');
                }
            }
            admin_redirect('cupones', ['editar_cupon' => $id]);
        }

        if (isset($_GET['borrar_cupon'])) {
            $id = intval($_GET['borrar_cupon']);
            $pdo->prepare('DELETE FROM cupones WHERE id = ?')->execute([$id]);
            admin_set_flash('success', 'Cupón eliminado.');
            admin_redirect('cupones');
        }

        if (isset($_GET['toggle_cupon'])) {
            $id = intval($_GET['toggle_cupon']);
            $pdo->prepare('UPDATE cupones SET activo = NOT activo WHERE id = ?')->execute([$id]);
            admin_set_flash('success', 'Estado del cupón actualizado.');
            admin_redirect('cupones');
        }
        break;

    case 'configuracion':
        require_once __DIR__ . '/includes/store_config.php';
        require_once __DIR__ . '/includes/home_gallery.php';
        require_once __DIR__ . '/includes/payment_methods.php';
        $startupPopupTabEnabled = store_config_get('inicio_popup_tab_habilitado', '1') === '1';
        $activeTab = $_GET['tab'] ?? 'correo';
        $allowedTabs = ['correo', 'cabecera', 'sociales', 'api-banco', 'api-free-fire', 'personalizar-colores', 'galeria', 'metodos-pago'];
        if ($startupPopupTabEnabled) {
            $allowedTabs[] = 'ventana-inicial';
        }
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'correo';
        }

        home_gallery_ensure_table();

    case 'movimientos':
        require_once __DIR__ . '/includes/db.php';
        require_once __DIR__ . '/includes/store_config.php';
        if ($seccion === 'movimientos') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_movimientos_api'])) {
                $redirectQuery = admin_movement_query_from_input($_POST);

                try {
                    $bankConfig = [
                        'ff_bank_posicion' => store_config_get('ff_bank_posicion', '0'),
                        'ff_bank_token' => store_config_get('ff_bank_token', ''),
                        'ff_bank_clave' => store_config_get('ff_bank_clave', ''),
                    ];
                    $movements = admin_fetch_bank_movements_from_api($bankConfig);
                    $syncSummary = admin_sync_bank_movements($pdo, $movements);
                    $hasNewMovements = (int) ($syncSummary['inserted'] ?? 0) > 0;

                    if (admin_is_ajax_request()) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'ok' => true,
                            'has_new_movements' => $hasNewMovements,
                            'inserted' => (int) ($syncSummary['inserted'] ?? 0),
                            'updated' => (int) ($syncSummary['updated'] ?? 0),
                            'processed' => (int) ($syncSummary['processed'] ?? 0),
                            'message' => $hasNewMovements
                                ? 'Se encontraron ' . (int) ($syncSummary['inserted'] ?? 0) . ' movimientos nuevos y ya fueron registrados.'
                                : 'No hay movimientos nuevos para actualizar.',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit();
                    }

                    if ($hasNewMovements) {
                        admin_set_flash('success', 'Se registraron ' . (int) ($syncSummary['inserted'] ?? 0) . ' movimientos nuevos desde la API.');
                    } else {
                        admin_set_flash('info', 'No hay movimientos nuevos para actualizar.');
                    }
                } catch (Throwable $e) {
                    if (admin_is_ajax_request()) {
                        http_response_code(500);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'ok' => false,
                            'message' => $e->getMessage(),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit();
                    }

                    admin_set_flash('error', $e->getMessage());
                }

                admin_redirect('movimientos', $redirectQuery);
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_movimiento'])) {
                $movementId = (int) ($_POST['movimiento_id'] ?? 0);
                $redirectQuery = admin_movement_query_from_input($_POST);

                if ($movementId > 0) {
                    $stmt = $pdo->prepare('UPDATE movimientos SET checked = 1 WHERE id = ? AND (checked IS NULL OR checked = 0)');
                    $stmt->execute([$movementId]);
                    if ($stmt->rowCount() > 0) {
                        if (admin_is_ajax_request()) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode([
                                'ok' => true,
                                'movement_id' => $movementId,
                                'checked' => 1,
                                'message' => 'Movimiento verificado correctamente.',
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            exit();
                        }
                        admin_set_flash('success', 'Movimiento verificado correctamente.');
                    } else {
                        $checkStmt = $pdo->prepare('SELECT checked FROM movimientos WHERE id = ? LIMIT 1');
                        $checkStmt->execute([$movementId]);
                        $existingMovement = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingMovement) {
                            if (admin_is_ajax_request()) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode([
                                    'ok' => true,
                                    'movement_id' => $movementId,
                                    'checked' => (int) ($existingMovement['checked'] ?? 0) === 1 ? 1 : 0,
                                    'message' => 'El movimiento ya estaba verificado.',
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                exit();
                            }
                            admin_set_flash('info', 'El movimiento ya estaba verificado.');
                        } else {
                            if (admin_is_ajax_request()) {
                                http_response_code(404);
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode([
                                    'ok' => false,
                                    'message' => 'El movimiento no existe.',
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                exit();
                            }
                            admin_set_flash('error', 'El movimiento no existe.');
                        }
                    }
                } else {
                    if (admin_is_ajax_request()) {
                        http_response_code(422);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'ok' => false,
                            'message' => 'Movimiento inválido para verificar.',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit();
                    }
                    admin_set_flash('error', 'Movimiento inválido para verificar.');
                }

                admin_redirect('movimientos', $redirectQuery);
            }
            break;
        }

        if ($activeTab === 'galeria' && isset($_GET['eliminar_galeria'])) {
            $galleryId = intval($_GET['eliminar_galeria']);
            if ($galleryId > 0 && home_gallery_delete($galleryId)) {
                admin_set_flash('success', 'Elemento de galería eliminado.');
            } else {
                admin_set_flash('error', 'No se pudo eliminar el elemento de galería.');
            }
            admin_redirect('configuracion', ['tab' => 'galeria']);
        }

        if ($activeTab === 'metodos-pago' && isset($_GET['eliminar_metodo_pago'])) {
            $paymentId = intval($_GET['eliminar_metodo_pago']);
            if ($paymentId > 0 && payment_methods_delete($paymentId)) {
                admin_set_flash('success', 'Método de pago eliminado.');
            } else {
                admin_set_flash('error', 'No se pudo eliminar el método de pago.');
            }
            admin_redirect('configuracion', ['tab' => 'metodos-pago']);
        }

        if ($activeTab === 'metodos-pago' && isset($_GET['toggle_metodo_pago'])) {
            $paymentId = intval($_GET['toggle_metodo_pago']);
            if ($paymentId > 0 && payment_methods_toggle($paymentId)) {
                admin_set_flash('success', 'Estado del método de pago actualizado.');
            } else {
                admin_set_flash('error', 'No se pudo actualizar el método de pago.');
            }
            admin_redirect('configuracion', ['tab' => 'metodos-pago']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $activeTab = $_POST['config_section'] ?? $activeTab;
            if (!in_array($activeTab, $allowedTabs, true)) {
                $activeTab = 'correo';
            }

            if ($activeTab === 'correo') {
                $campos = [
                    'correo_corporativo', 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure'
                ];
                foreach ($campos as $clave) {
                    store_config_upsert($clave, trim((string) ($_POST[$clave] ?? '')));
                }
                admin_set_flash('success', 'Configuración de correo actualizada.');
            }

            if ($activeTab === 'cabecera') {
                $nombrePrefijo = trim((string) ($_POST['nombre_prefijo'] ?? ''));
                $nombreTienda = trim((string) ($_POST['nombre_tienda'] ?? ''));
                $nombreTiendaSubtitulo = trim((string) ($_POST['nombre_tienda_subtitulo'] ?? ''));
                $metaTitulo = trim((string) ($_POST['meta_titulo'] ?? ''));
                $metaDescripcion = trim((string) ($_POST['meta_descripcion'] ?? ''));
                $currentLogo = store_config_get('logo_tienda', '');
                $nextLogo = $currentLogo;
                $hasUpload = isset($_FILES['logo_tienda']) && (($_FILES['logo_tienda']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

                if ($nombrePrefijo === '' || $nombreTienda === '' || $nombreTiendaSubtitulo === '' || $metaTitulo === '' || $metaDescripcion === '') {
                    admin_set_flash('error', 'Completa el nombre prefijo, el nombre de la tienda, el subtítulo, el meta title SEO y la meta descripción SEO.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'cabecera']);
                }

                if (function_exists('mb_strlen') ? mb_strlen($metaTitulo, 'UTF-8') > 255 : strlen($metaTitulo) > 255) {
                    admin_set_flash('error', 'El meta title SEO no debe superar los 255 caracteres.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'cabecera']);
                }

                if (function_exists('mb_strlen') ? mb_strlen($metaDescripcion, 'UTF-8') > 320 : strlen($metaDescripcion) > 320) {
                    admin_set_flash('error', 'La meta descripción SEO no debe superar los 320 caracteres.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'cabecera']);
                }

                if ($hasUpload) {
                    $upload = store_config_store_logo_upload($_FILES['logo_tienda']);
                    if (!$upload['success']) {
                        admin_set_flash('error', $upload['message']);
                        define('ADMIN_CONFIG_POST_HANDLED', true);
                        admin_redirect('configuracion', ['tab' => 'cabecera']);
                    }
                    if (!empty($upload['path'])) {
                        $nextLogo = $upload['path'];
                    }
                } elseif (isset($_POST['eliminar_logo_tienda'])) {
                    $nextLogo = '';
                }

                store_config_upsert('nombre_prefijo', $nombrePrefijo);
                store_config_upsert('nombre_tienda', $nombreTienda);
                store_config_upsert('nombre_tienda_subtitulo', $nombreTiendaSubtitulo);
                store_config_upsert('meta_titulo', $metaTitulo);
                store_config_upsert('meta_descripcion', $metaDescripcion);
                if ($nextLogo === '') {
                    store_config_delete('logo_tienda');
                } else {
                    store_config_upsert('logo_tienda', $nextLogo);
                }

                if ($currentLogo !== '' && $currentLogo !== $nextLogo) {
                    store_config_delete_logo_file($currentLogo);
                }

                admin_set_flash('success', 'Datos de cabecera actualizados.');
            }

            if ($activeTab === 'sociales') {
                $facebook = store_config_normalize_social_url((string) ($_POST['facebook'] ?? ''));
                $instagram = store_config_normalize_social_url((string) ($_POST['instagram'] ?? ''));
                $whatsapp = store_config_normalize_whatsapp((string) ($_POST['whatsapp'] ?? ''));
                $whatsappMessage = store_config_normalize_whatsapp_message((string) ($_POST['mensaje_whatsapp'] ?? ''));
                $whatsappChannel = store_config_normalize_social_url((string) ($_POST['whatsapp_channel'] ?? ''));
                $googleClientId = trim((string) ($_POST['google_client_id'] ?? ''));
                $googleClientSecret = trim((string) ($_POST['google_client_secret'] ?? ''));

                if ($facebook !== '' && !store_config_is_valid_social_url($facebook)) {
                    admin_set_flash('error', 'El enlace de Facebook no es válido. Usa una URL completa con http:// o https://');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'sociales']);
                }

                if ($instagram !== '' && !store_config_is_valid_social_url($instagram)) {
                    admin_set_flash('error', 'El enlace de Instagram no es válido. Usa una URL completa con http:// o https://');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'sociales']);
                }

                if ($whatsapp !== '' && !store_config_is_valid_whatsapp($whatsapp)) {
                    admin_set_flash('error', 'El número de WhatsApp debe incluir código de país y número telefónico, por ejemplo: +584121234567.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'sociales']);
                }

                if ($whatsappChannel !== '' && !store_config_is_valid_social_url($whatsappChannel)) {
                    admin_set_flash('error', 'El enlace de WhatsApp Channel no es válido. Usa una URL completa con http:// o https://');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'sociales']);
                }

                store_config_upsert('facebook', $facebook);
                store_config_upsert('instagram', $instagram);
                store_config_upsert('whatsapp', $whatsapp);
                store_config_upsert('mensaje_whatsapp', $whatsappMessage);
                store_config_upsert('whatsapp_channel', $whatsappChannel);
                store_config_upsert('google_client_id', $googleClientId);
                store_config_upsert('google_client_secret', $googleClientSecret);
                admin_set_flash('success', 'Redes sociales actualizadas.');
            }

            if ($activeTab === 'api-banco') {
                $ffBankPosicion = (string) intval($_POST['ff_bank_posicion'] ?? 0);
                $ffBankToken = trim((string) ($_POST['ff_bank_token'] ?? ''));
                $ffBankClave = trim((string) ($_POST['ff_bank_clave'] ?? ''));

                if (!in_array($ffBankPosicion, ['0', '1', '2', '3', '4', '5'], true)) {
                    admin_set_flash('error', 'La Posicion debe estar entre 0 y 5.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'api-banco']);
                }

                if ($ffBankClave !== '' && preg_match('/^[A-Za-z0-9._!-]+$/', $ffBankClave) !== 1) {
                    admin_set_flash('error', 'La Clave del banco solo puede contener letras, números y estos caracteres especiales: . - _ !, sin espacios.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'api-banco']);
                }

                store_config_upsert('ff_bank_posicion', $ffBankPosicion);
                store_config_upsert('ff_bank_token', $ffBankToken);
                store_config_upsert('ff_bank_clave', $ffBankClave);
                admin_set_flash('success', 'Datos de conexión del banco actualizados.');
            }

            if ($activeTab === 'api-free-fire') {
                $ffApiUsuario = trim((string) ($_POST['ff_api_usuario'] ?? ''));
                $ffApiClave = trim((string) ($_POST['ff_api_clave'] ?? ''));
                $ffApiTipo = trim((string) ($_POST['ff_api_tipo'] ?? 'recargaFreefire'));

                store_config_upsert('ff_api_usuario', $ffApiUsuario);
                store_config_upsert('ff_api_clave', $ffApiClave);
                store_config_upsert('ff_api_tipo', $ffApiTipo);
                admin_set_flash('success', 'Datos API Free Fire actualizados.');
            }

            if ($activeTab === 'personalizar-colores') {
                if (isset($_POST['restore_theme_defaults'])) {
                    if (!store_theme_restore_defaults()) {
                        admin_set_flash('error', 'No se pudo restaurar la paleta base.');
                    } else {
                        admin_set_flash('success', 'La paleta editable fue restaurada desde los valores base.');
                    }
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'personalizar-colores']);
                }

                $validation = store_theme_validate_payload($_POST);
                if (!$validation['is_valid']) {
                    admin_set_flash('error', implode(' ', $validation['errors']));
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'personalizar-colores']);
                }

                if (!store_theme_save_values($validation['data'])) {
                    admin_set_flash('error', 'No se pudo guardar la paleta editable.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'personalizar-colores']);
                }

                admin_set_flash('success', 'Paleta de colores actualizada.');
            }

            if ($activeTab === 'ventana-inicial') {
                $popupMode = trim((string) ($_POST['inicio_popup_modo'] ?? 'none'));
                $popupFrequency = trim((string) ($_POST['inicio_popup_frecuencia'] ?? 'per_session'));
                $popupChannelName = trim((string) ($_POST['inicio_popup_nombre_canal'] ?? 'DanisA Gamer Store'));
                $popupVideoUrl = store_config_normalize_youtube_url((string) ($_POST['inicio_popup_video_url'] ?? ''));
                $channelUrl = store_config_normalize_social_url(store_config_get('whatsapp_channel', ''));

                if (!in_array($popupMode, ['none', 'normal', 'video'], true)) {
                    admin_set_flash('error', 'Selecciona un modo válido para la ventana inicial.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'ventana-inicial']);
                }

                if (!in_array($popupFrequency, ['always', 'per_entry', 'per_session'], true)) {
                    admin_set_flash('error', 'Selecciona una frecuencia válida para la ventana inicial.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'ventana-inicial']);
                }

                if ($popupMode === 'normal' && $popupChannelName === '') {
                    admin_set_flash('error', 'Debes indicar el nombre del canal para la ventana inicial.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'ventana-inicial']);
                }

                if ($popupMode !== 'none' && !store_config_is_valid_social_url($channelUrl)) {
                    admin_set_flash('error', 'Debes configurar primero un enlace válido en Redes Sociales > Whatsapp Channel para usar cualquier ventana inicial.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'ventana-inicial']);
                }

                if ($popupMode === 'video' && $popupVideoUrl === '') {
                    admin_set_flash('error', 'Debes indicar un enlace válido de YouTube para activar la ventana inicial con video.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'ventana-inicial']);
                }

                store_config_upsert('inicio_popup_activo', $popupMode === 'normal' ? '1' : '0');
                store_config_upsert('inicio_popup_video_activo', $popupMode === 'video' ? '1' : '0');
                store_config_upsert('inicio_popup_frecuencia', $popupFrequency);
                store_config_upsert('inicio_popup_nombre_canal', $popupChannelName !== '' ? $popupChannelName : 'DanisA Gamer Store');
                store_config_upsert('inicio_popup_video_url', $popupVideoUrl);
                admin_set_flash('success', 'Configuración de la ventana inicial actualizada.');
            }

            if ($activeTab === 'galeria') {
                $galleryId = isset($_POST['gallery_id']) ? intval($_POST['gallery_id']) : 0;
                $existingItem = $galleryId > 0 ? home_gallery_find($galleryId) : null;
                if ($galleryId > 0 && $existingItem === null) {
                    admin_set_flash('error', 'El elemento de galería que intentas editar no existe.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'galeria']);
                }

                $validation = home_gallery_validate_form($_POST, $_FILES, $existingItem);
                if (!$validation['is_valid']) {
                    $newImage = (string) ($validation['data']['imagen'] ?? '');
                    $existingImage = (string) ($existingItem['imagen'] ?? '');
                    if ($newImage !== '' && $newImage !== $existingImage) {
                        home_gallery_delete_image_file($newImage);
                    }
                    admin_set_flash('error', implode(' ', $validation['errors']));
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    $query = ['tab' => 'galeria'];
                    if ($galleryId > 0) {
                        $query['editar_galeria'] = $galleryId;
                    }
                    admin_redirect('configuracion', $query);
                }

                $saved = home_gallery_save($validation['data'], $galleryId > 0 ? $galleryId : null);
                if ($saved) {
                    $replacedImage = (string) ($validation['replaced_image'] ?? '');
                    $newImage = (string) ($validation['data']['imagen'] ?? '');
                    if ($replacedImage !== '' && $replacedImage !== $newImage) {
                        home_gallery_delete_image_file($replacedImage);
                    }
                    admin_set_flash('success', $galleryId > 0 ? 'Elemento de galería actualizado.' : 'Elemento de galería creado.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'galeria']);
                }

                $newImage = (string) ($validation['data']['imagen'] ?? '');
                $existingImage = (string) ($existingItem['imagen'] ?? '');
                if ($newImage !== '' && $newImage !== $existingImage) {
                    home_gallery_delete_image_file($newImage);
                }
                admin_set_flash('error', 'No se pudo guardar el elemento de galería.');
            }

            if ($activeTab === 'metodos-pago') {
                $paymentId = isset($_POST['payment_method_id']) ? intval($_POST['payment_method_id']) : 0;
                $existingMethod = $paymentId > 0 ? payment_methods_find($paymentId) : null;
                if ($paymentId > 0 && $existingMethod === null) {
                    admin_set_flash('error', 'El método de pago que intentas editar no existe.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'metodos-pago']);
                }

                $validation = payment_methods_validate_form($_POST);
                if (!$validation['is_valid']) {
                    admin_set_flash('error', implode(' ', $validation['errors']));
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    $query = ['tab' => 'metodos-pago'];
                    if ($paymentId > 0) {
                        $query['editar_metodo_pago'] = $paymentId;
                    }
                    admin_redirect('configuracion', $query);
                }

                if (payment_methods_save($validation['data'], $paymentId > 0 ? $paymentId : null)) {
                    admin_set_flash('success', $paymentId > 0 ? 'Método de pago actualizado.' : 'Método de pago creado.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'metodos-pago']);
                }

                admin_set_flash('error', 'No se pudo guardar el método de pago.');
            }

            define('ADMIN_CONFIG_POST_HANDLED', true);
            admin_redirect('configuracion', ['tab' => $activeTab]);
        }

        define('ADMIN_CONFIG_ACTIVE_TAB', $activeTab);
        define('ADMIN_CONFIG_POST_HANDLED', true);
        break;
}

define('ADMIN_LAYOUT_EMBEDDED', true);

// Header y menú igual al inicio
require_once __DIR__ . '/includes/header.php';
?>
<body class="min-h-screen text-slate-100">
<div class="relative min-h-screen overflow-hidden">

<div class="container-lg min-vh-100 d-flex flex-column align-items-center justify-content-center px-2">
    <div class="w-100 mt-5">
        <?php if ($seccion === 'dashboard'): ?>
        <div class="mb-5 text-center">
            <h1 class="display-4 fw-bold text-info mb-4">Panel de Administración</h1>
            <h2 class="h3 fw-semibold mb-3">Bienvenido al panel de administración</h2>
            <p class="mb-4">Selecciona una sección para comenzar.</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <?php if ($adminUserRole === 'admin'): ?>
                <a href="/admin/usuarios" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>👤</span>Usuarios</a>
                <a href="/admin/juegos" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>🎮</span>Juegos</a>
                <a href="/admin/monedas" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>💵</span>Monedas</a>
                <?php endif; ?>
                <a href="/admin/movimientos" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>💳</span>Movimientos</a>
                <?php if ($adminUserRole === 'admin'): ?>
                <a href="/admin/cupones" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>✏️</span>Cupones</a>
                <?php endif; ?>
                <a href="/admin/pedidos" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>📋</span>Pedidos</a>
                <?php if ($adminUserRole === 'admin'): ?>
                <a href="/admin/configuracion" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>⚙️</span>Configuración</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="relative mb-8">
        <?php
        switch ($seccion) {
            case 'usuarios':
                require_once __DIR__ . '/includes/db.php';
                echo '<h2 class="display-6 fw-bold text-info mb-4">Gestión de Usuarios</h2>';
                // Borrar usuario
                if (isset($_GET['borrar_usuario'])) {
                    $id = intval($_GET['borrar_usuario']);
                    if ($id !== 1) { // No permitir borrar admin principal
                        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                        echo '<div class="text-green-400 mb-2">Usuario eliminado.</div>';
                    }
                }
                // Edición de usuario (solo nombre y rol)
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
                    $id = intval($_POST['id']);
                    $nombre = trim($_POST['nombre'] ?? '');
                    $rol = $_POST['rol'] ?? 'usuario';
                    if ($id && $nombre && in_array($rol, ['usuario', 'admin', 'empleado'], true)) {
                        $pdo->prepare('UPDATE usuarios SET nombre = ?, rol = ? WHERE id = ?')->execute([$nombre, $rol, $id]);
                        echo '<div class="text-green-400 mb-2">Usuario actualizado.</div>';
                    }
                }
                // Listado de usuarios
                $usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY creado_en DESC')->fetchAll(PDO::FETCH_ASSOC);
                if (count($usuarios) === 0) {
                    echo '<div class="text-secondary">No hay usuarios registrados.</div>';
                } else {
                    // Tabla desktop modelo gamer neon sin fondo blanco
                    echo '<div class="table-responsive mb-4 d-none d-md-block" style="background:#10141a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1rem;">';
                    echo '<table class="table align-middle" style="background:#181f2a; color:#00fff7; border-radius:12px;">';
                    echo '<thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">';
                    echo '<tr>';
                    echo '<th style="color:#00fff7; background:#181f2a;">ID</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Nombre</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Email</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Rol</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Creado</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Acciones</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    $rowAlt = false;
                    foreach ($usuarios as $usuario) {
                        $rowStyle = $rowAlt ? 'background:#151a24;' : 'background:#181f2a;';
                        echo '<tr style="' . $rowStyle . ' color:#fff;">';
                        echo '<td style="color:#00fff7; background:#181f2a;">' . htmlspecialchars($usuario['id']) . '</td>';
                        echo '<td style="background:#181f2a;">';
                        echo '<form method="POST" class="d-flex gap-2 align-items-center">';
                        echo '<input type="hidden" name="editar_usuario" value="1">';
                        echo '<input type="hidden" name="id" value="' . htmlspecialchars($usuario['id']) . '">';
                        echo '<input type="text" name="nombre" value="' . htmlspecialchars($usuario['nombre']) . '" class="form-control form-control-sm" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                        echo '</td>';
                        echo '<td style="color:#fff; background:#181f2a;">' . htmlspecialchars($usuario['email']) . '</td>';
                        echo '<td style="background:#181f2a;">';
                        echo '<select name="rol" class="form-select form-select-sm" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                        foreach (["usuario"=>"Usuario","empleado"=>"Empleado","admin"=>"Admin"] as $rolVal=>$rolTxt) {
                            $sel = $usuario['rol']===$rolVal ? 'selected' : '';
                            echo "<option value='$rolVal' $sel>$rolTxt</option>";
                        }
                        echo '</select>';
                        echo '<button type="submit" class="btn btn-info btn-sm ms-2" style="background:#00fff7; color:#222; border:none; box-shadow:0 0 8px #00fff7;">Guardar</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '<td style="color:#00fff7; background:#181f2a;">' . htmlspecialchars($usuario['creado_en']) . '</td>';
                        echo '<td style="background:#181f2a;">';
                        if ($usuario['id'] != 1) {
                            echo '<a href="?seccion=usuarios&borrar_usuario=' . $usuario['id'] . '" class="btn btn-outline-danger btn-sm" style="border-color:#ff0059; color:#ff0059; background:#181f2a;" onmouseover="this.style.background=\'#ff0059\';this.style.color=\'#fff\'" onmouseout="this.style.background=\'#181f2a\';this.style.color=\'#ff0059\'" onclick="return confirm(\'¿Eliminar este usuario?\')">Eliminar</a>';
                        } else {
                            echo '<span class="text-secondary">Admin</span>';
                        }
                        echo '</td>';
                        echo '</tr>';
                        $rowAlt = !$rowAlt;
                    }
                    echo '</tbody></table>';
                    echo '</div>';

                    // Cards solo en móvil
                    echo '<div class="d-block d-md-none">';
                    foreach ($usuarios as $usuario) {
                        echo '<div class="card bg-dark text-light mb-3 border-info shadow">';
                        echo '<div class="card-header d-flex justify-content-between align-items-center">';
                        echo '<span class="small text-info">ID: ' . htmlspecialchars($usuario['id']) . '</span>';
                        echo '<span class="small text-secondary">' . htmlspecialchars($usuario['creado_en']) . '</span>';
                        echo '</div>';
                        echo '<div class="card-body">';
                        echo '<form method="POST">';
                        echo '<input type="hidden" name="editar_usuario" value="1">';
                        echo '<input type="hidden" name="id" value="' . htmlspecialchars($usuario['id']) . '">';
                        echo '<div class="mb-2">';
                        echo '<label class="form-label text-info">Nombre</label>';
                        echo '<input type="text" name="nombre" value="' . htmlspecialchars($usuario['nombre']) . '" class="form-control">';
                        echo '</div>';
                        echo '<div class="mb-2">';
                        echo '<label class="form-label text-info">Email</label>';
                        echo '<div class="form-control bg-dark text-light">' . htmlspecialchars($usuario['email']) . '</div>';
                        echo '</div>';
                        echo '<div class="mb-2">';
                        echo '<label class="form-label text-info">Rol</label>';
                        echo '<select name="rol" class="form-select">';
                        foreach (["usuario"=>"Usuario","empleado"=>"Empleado","admin"=>"Admin"] as $rolVal=>$rolTxt) {
                            $sel = $usuario['rol']===$rolVal ? 'selected' : '';
                            echo "<option value='$rolVal' $sel>$rolTxt</option>";
                        }
                        echo '</select>';
                        echo '</div>';
                        echo '<div class="d-flex gap-2 mt-2">';
                        echo '<button type="submit" class="btn btn-info flex-fill">Guardar</button>';
                        if ($usuario['id'] != 1) {
                            echo '<a href="?seccion=usuarios&borrar_usuario=' . $usuario['id'] . '" class="btn btn-danger flex-fill" onclick="return confirm(\'¿Eliminar este usuario?\')">Eliminar</a>';
                        } else {
                            echo '<span class="btn btn-secondary flex-fill disabled">Admin</span>';
                        }
                        echo '</div>';
                        echo '</form>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                break;
            case 'juegos':
                echo '<h2 class="text-2xl font-semibold mb-8 text-cyan-300">Gestión de Juegos</h2>';
                if (file_exists(__DIR__ . "/includes/db.php")) {
                    require_once __DIR__ . "/includes/db.php";
                } else {
                    echo '<div class="text-red-400">Error: No se encontró el archivo de conexión a la base de datos (includes/db.php).</div>';
                    break;
                }
                // Alta de juego
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_juego'])) {
                    $nombre = $_POST['nombre'] ?? '';
                    $descripcion = $_POST['descripcion'] ?? '';
                    $precio = $_POST['precio'] ?? 0;
                    $imagen = $_POST['imagen'] ?? '';
                    $stmt = $pdo->prepare("INSERT INTO juegos (nombre, descripcion, precio, imagen) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nombre, $descripcion, $precio, $imagen]);
                    echo '<div class="text-green-400 mb-2">Juego agregado correctamente.</div>';
                }
                // Borrado de juego
                if (isset($_GET['borrar_juego'])) {
                    $id = intval($_GET['borrar_juego']);
                    $pdo->prepare("DELETE FROM juegos WHERE id = ?")->execute([$id]);
                    echo '<div class="text-green-400 mb-2">Juego eliminado.</div>';
                }
                // Listado de juegos
                $juegos = $pdo->query("SELECT * FROM juegos ORDER BY creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
                echo '<form method="POST" action="/admin/juegos" enctype="multipart/form-data" class="bg-slate-900 rounded-xl p-8 max-w-lg w-full relative mb-8" style="box-shadow:0 0 2rem #22d3ee33;">';
                echo '<h3 class="text-xl font-bold mb-4 text-cyan-300">Registrar juego</h3>';
                echo '<label class="block text-slate-300 font-medium mb-2"><input type="checkbox" name="popular" value="1" class="accent-cyan-600"> Marcar como popular</label>';
                echo '<input type="text" name="nombre" placeholder="Nombre del juego" class="block w-full text-xl px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2" required />';
                echo '<textarea name="descripcion" placeholder="Descripción" class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2"></textarea>';
                echo '<label class="block text-slate-300 font-medium mb-2">Imagen principal:';
                echo '<input type="file" name="imagen" accept="image/*" class="block w-full mt-2 mb-2" /></label>';
                echo '<label class="block text-slate-300 font-medium mb-2">Imagen común para paquetes:';
                echo '<input type="file" name="imagen_paquete" accept="image/*" class="block w-full mt-2 mb-2" /></label>';
                echo '<label class="block text-slate-300 font-medium mb-2">Moneda fija o variable:';
                echo '<select name="moneda" class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2">';
                echo '<option value="">Moneda variable (usuario elige)</option>';
                echo '<option value="USD">Dólar estadounidense</option>';
                echo '<option value="VES">Bolívares</option>';
                echo '</select></label>';
                echo '<label class="block text-slate-300 font-medium mb-2">Seleccionar características existentes:';
                echo '<select name="caracteristicas[]" multiple class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2">';
                echo '<option value="Entrega Inmediata">Entrega Inmediata</option>';
                echo '<option value="Global">Global</option>';
                echo '</select></label>';
                echo '<input type="text" name="nueva_caracteristica" placeholder="Nueva característica" class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2" />';
                echo '<button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white text-xl px-4 py-4 rounded font-semibold transition">Registrar juego</button>';
                echo '</form>';
                if (count($juegos) === 0) {
                    echo '<div class="text-gray-400">No hay juegos registrados.</div>';
                } else {
                    // Tabla para desktop
                    echo '<div class="overflow-x-auto hidden sm:block">';
                    echo '<table class="w-full text-left text-sm min-w-[800px]">';
                    echo '<thead><tr class="text-cyan-300">'
                        .'<th>Imagen</th>'
                        .'<th>Nombre</th>'
                        .'<th>Popular</th>'
                        .'<th>Imagen Paquete</th>'
                        .'<th>Descripción</th>'
                        .'<th>Moneda</th>'
                        .'<th>Características</th>'
                        .'<th>Acciones</th>'
                        .'</tr></thead><tbody>';
                    foreach ($juegos as $juego) {
                        echo '<tr class="border-b border-gray-700">';
                        // Imagen principal
                        echo '<td>';
                        if (!empty($juego['imagen'])) {
                            $imgSrc = '/' . ltrim($juego['imagen'], '/');
                            echo '<img src="'.htmlspecialchars($imgSrc).'" alt="img" class="w-16 h-16 object-cover rounded" />';
                        } else {
                            echo '<span class="text-gray-400">Sin imagen</span>';
                        }
                        echo '</td>';
                        // Nombre
                        echo '<td>' . htmlspecialchars($juego['nombre']) . '</td>';
                        // Popular
                        echo '<td>' . ((isset($juego['popular']) && $juego['popular']) ? '<span class="text-green-400">★</span>' : '—') . '</td>';
                        // Imagen Paquete
                        echo '<td>';
                        if (!empty($juego['imagen_paquete'])) {
                            $imgPaqSrc = '/' . ltrim($juego['imagen_paquete'], '/');
                            echo '<img src="'.htmlspecialchars($imgPaqSrc).'" alt="img" class="w-16 h-16 object-cover rounded" />';
                        } else {
                            echo '<span class="text-gray-400">Sin imagen</span>';
                        }
                        echo '</td>';
                        // Descripción
                        echo '<td>' . htmlspecialchars($juego['descripcion'] ?? '') . '</td>';
                        // Moneda
                        echo '<td>' . htmlspecialchars($juego['moneda'] ?? '') . '</td>';
                        // Características
                        echo '<td>' . htmlspecialchars($juego['caracteristicas'] ?? '') . '</td>';
                        // Acciones
                        echo '<td>';
                        echo '<a href="?seccion=juegos&editar_juego=' . $juego['id'] . '" class="text-green-400 hover:underline mr-2">Editar</a>';
                        echo '<a href="?seccion=paquetes&juego_id=' . $juego['id'] . '" class="text-cyan-400 hover:underline mr-2">Paquetes</a>';
                        echo '<a href="?seccion=juegos&borrar_juego=' . $juego['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este juego?\')">Eliminar</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';

                    // Cards para móvil
                    echo '<div class="sm:hidden flex flex-col gap-4">';
                    foreach ($juegos as $juego) {
                        echo '<div class="rounded-xl border border-slate-700 bg-gray-900 p-4 flex flex-col gap-2 shadow">';
                        // Imagen principal
                        if (!empty($juego['imagen'])) {
                            $imgSrc = '/' . ltrim($juego['imagen'], '/');
                            echo '<img src="'.htmlspecialchars($imgSrc).'" alt="img" class="w-full h-32 object-cover rounded mb-2" />';
                        } else {
                            echo '<span class="text-gray-400 mb-2">Sin imagen</span>';
                        }
                        echo '<div class="font-bold text-lg text-cyan-200">' . htmlspecialchars($juego['nombre']) . '</div>';
                        if (isset($juego['popular']) && $juego['popular']) {
                            echo '<div class="text-green-400 text-sm">★ Popular</div>';
                        }
                        // Imagen Paquete
                        if (!empty($juego['imagen_paquete'])) {
                            $imgPaqSrc = '/' . ltrim($juego['imagen_paquete'], '/');
                            echo '<img src="'.htmlspecialchars($imgPaqSrc).'" alt="img" class="w-full h-16 object-cover rounded mb-2" />';
                        } else {
                            echo '<span class="text-gray-400 mb-2">Sin imagen de paquete</span>';
                        }
                        echo '<div class="text-sm text-slate-300"><strong>Descripción:</strong> ' . htmlspecialchars($juego['descripcion'] ?? '') . '</div>';
                        echo '<div class="text-sm text-slate-300"><strong>Moneda:</strong> ' . htmlspecialchars($juego['moneda'] ?? '') . '</div>';
                        echo '<div class="text-sm text-slate-300"><strong>Características:</strong> ' . htmlspecialchars($juego['caracteristicas'] ?? '') . '</div>';
                        echo '<div class="flex gap-4 mt-2">';
                        echo '<a href="?seccion=juegos&editar_juego=' . $juego['id'] . '" class="text-green-400 hover:underline">Editar</a>';
                        echo '<a href="?seccion=paquetes&juego_id=' . $juego['id'] . '" class="text-cyan-400 hover:underline">Paquetes</a>';
                        echo '<a href="?seccion=juegos&borrar_juego=' . $juego['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este juego?\')">Eliminar</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                break;
            case 'cupones':
                require_once __DIR__ . '/includes/db.php';
                influencer_coupon_ensure_sales_table_pdo($pdo);
                sync_coupon_usage_counts_pdo($pdo);
                backfill_influencer_sales_pdo($pdo);
                backfill_influencer_order_payment_status_pdo($pdo);
                $couponAdminTab = $_GET['tab'] ?? 'cupones';
                if (!in_array($couponAdminTab, ['cupones', 'influencers'], true)) {
                    $couponAdminTab = 'cupones';
                }
                $influencerPaymentFilter = admin_normalize_influencer_payment_filter($_GET['filtro_estado_pago'] ?? 'pendiente');
                $influencerDateFrom = admin_normalize_date_filter($_GET['fecha_desde'] ?? null);
                $influencerDateTo = admin_normalize_date_filter($_GET['fecha_hasta'] ?? null);
                $cupones = $pdo->query('SELECT * FROM cupones ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
                $influencerSalesSql = "SELECT s.*, p.estado_pago_influencer, p.juego_nombre
                    FROM cupones_influencer_ventas s
                    INNER JOIN pedidos p ON p.id = s.pedido_id
                    WHERE 1=1";
                $influencerSalesParams = [];
                if ($influencerPaymentFilter !== 'todos') {
                    $influencerSalesSql .= ' AND p.estado_pago_influencer = ?';
                    $influencerSalesParams[] = $influencerPaymentFilter;
                }
                if ($influencerDateFrom !== null) {
                    $influencerSalesSql .= ' AND DATE(s.creado_en) >= ?';
                    $influencerSalesParams[] = $influencerDateFrom;
                }
                if ($influencerDateTo !== null) {
                    $influencerSalesSql .= ' AND DATE(s.creado_en) <= ?';
                    $influencerSalesParams[] = $influencerDateTo;
                }
                $influencerSalesSql .= ' ORDER BY s.creado_en DESC';
                $influencerSalesStmt = $pdo->prepare($influencerSalesSql);
                $influencerSalesStmt->execute($influencerSalesParams);
                $influencerSales = $influencerSalesStmt->fetchAll(PDO::FETCH_ASSOC);
                $edit_cupon = null;
                if (isset($_GET['editar_cupon'])) {
                    $edit_id = intval($_GET['editar_cupon']);
                    foreach ($cupones as $cupon) {
                        if ((int) $cupon['id'] === $edit_id) {
                            $edit_cupon = $cupon;
                            break;
                        }
                    }
                    $couponAdminTab = 'cupones';
                }
                $couponTabLink = '?seccion=cupones&tab=cupones';
                $influencerTabLink = '?seccion=cupones&tab=influencers';
                ?>
                <h2 class="text-center mb-4" style="color:#00fff7;">Gestión de Cupones</h2>

                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    <a href="<?= htmlspecialchars($couponTabLink) ?>" class="btn rounded-pill px-4 py-2 fw-semibold <?= $couponAdminTab === 'cupones' ? 'btn-info' : 'btn-outline-info' ?>" style="<?= $couponAdminTab === 'cupones' ? 'background:#00fff7;color:#181f2a;border:2px solid #00fff7;box-shadow:0 0 12px #00fff7;' : 'border:2px solid #00fff7;color:#00fff7;background:#181f2a;' ?>">Cupones</a>
                    <a href="<?= htmlspecialchars($influencerTabLink) ?>" class="btn rounded-pill px-4 py-2 fw-semibold <?= $couponAdminTab === 'influencers' ? 'btn-info' : 'btn-outline-info' ?>" style="<?= $couponAdminTab === 'influencers' ? 'background:#00fff7;color:#181f2a;border:2px solid #00fff7;box-shadow:0 0 12px #00fff7;' : 'border:2px solid #00fff7;color:#00fff7;background:#181f2a;' ?>">Cupones de Influencers</a>
                </div>

                <?php if ($couponAdminTab === 'cupones'): ?>
                    <form method="POST" action="" class="row g-3 mb-4" style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:2rem;">
                        <?php if ($edit_cupon): ?>
                            <input type="hidden" name="editar_cupon" value="1">
                            <input type="hidden" name="id" value="<?= htmlspecialchars((string) $edit_cupon['id']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="nuevo_cupon" value="1">
                        <?php endif; ?>
                        <div class="col-12">
                            <h3 class="h5 mb-0" style="color:#00fff7;"><?= $edit_cupon ? 'Editar cupón' : 'Crear cupón' ?></h3>
                            <p class="mb-0 mt-2" style="color:#b2f6ff;">Si completas el bloque del influencer, el cupón generará registros de comisión cuando el pedido pase a pagado o enviado.</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:#00fff7;">Código del cupón</label>
                            <input type="text" name="codigo" value="<?= $edit_cupon ? htmlspecialchars((string) $edit_cupon['codigo']) : '' ?>" required pattern="[A-Za-z0-9]+" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" oninput="this.value=this.value.replace(/[^A-Za-z0-9]/g,'').toUpperCase()" title="Solo letras y números, sin espacios, acentos ni caracteres especiales." class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:#00fff7;">Tipo de descuento</label>
                            <select name="tipo_descuento" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                <option value="porcentaje" <?= $edit_cupon && ($edit_cupon['tipo_descuento'] ?? '') === 'porcentaje' ? 'selected' : '' ?>>Porcentaje (%)</option>
                                <option value="fijo" <?= $edit_cupon && ($edit_cupon['tipo_descuento'] ?? '') === 'fijo' ? 'selected' : '' ?>>Monto fijo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:#00fff7;">Valor del descuento</label>
                            <input type="number" name="valor_descuento" step="0.01" min="0.01" value="<?= $edit_cupon ? htmlspecialchars((string) $edit_cupon['valor_descuento']) : '' ?>" required class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:#00fff7;">Fecha expiración</label>
                            <input type="datetime-local" name="fecha_expiracion" value="<?= $edit_cupon && !empty($edit_cupon['fecha_expiracion']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $edit_cupon['fecha_expiracion']))) : '' ?>" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:#00fff7;">Límite de usos</label>
                            <input type="number" name="limite_usos" min="0" value="<?= $edit_cupon ? htmlspecialchars((string) ($edit_cupon['limite_usos'] ?? '')) : '' ?>" placeholder="0 = ilimitado" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="activo" class="form-check-input" id="activoCheck" <?= $edit_cupon ? (!empty($edit_cupon['activo']) ? 'checked' : '') : 'checked' ?>>
                                <label class="form-check-label" for="activoCheck" style="color:#00fff7;">Cupón activo</label>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <div style="background:#0f172a; border:1px solid rgba(0,255,247,0.3); border-radius:16px; padding:1.25rem;">
                                <h4 class="h6 mb-3" style="color:#00fff7;">Configuración del influencer</h4>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" style="color:#00fff7;">Nombre influencer</label>
                                        <input type="text" name="nombre_influencer" value="<?= $edit_cupon ? htmlspecialchars((string) ($edit_cupon['nombre_influencer'] ?? '')) : '' ?>" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="color:#00fff7;">Teléfono influencer</label>
                                        <input type="text" name="telefono_influencer" value="<?= $edit_cupon ? htmlspecialchars((string) ($edit_cupon['telefono_influencer'] ?? '')) : '' ?>" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="color:#00fff7;">Correo influencer</label>
                                        <input type="email" name="email_influencer" value="<?= $edit_cupon ? htmlspecialchars((string) ($edit_cupon['email_influencer'] ?? '')) : '' ?>" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="color:#00fff7;">Comisión influencer (%)</label>
                                        <input type="number" name="comision_influencer" step="0.01" min="0" max="100" value="<?= $edit_cupon ? htmlspecialchars((string) ($edit_cupon['comision_influencer'] ?? '0')) : '0' ?>" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-flex flex-column flex-md-row gap-2">
                            <button type="submit" class="btn btn-info flex-fill" style="background:#00fff7; color:#222; border:none; box-shadow:0 0 8px #00fff7;"><?= $edit_cupon ? 'Guardar cambios' : 'Crear cupón' ?></button>
                            <?php if ($edit_cupon): ?>
                                <a href="<?= htmlspecialchars($couponTabLink) ?>" class="btn btn-outline-light flex-fill">Cancelar edición</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <h3 class="text-info mt-5 mb-3">Cupones existentes</h3>
                    <div class="table-responsive d-none d-md-block">
                        <table class="table align-middle" style="background:#181f2a; color:#00fff7; border-radius:12px;">
                            <thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">
                                <tr>
                                    <th style="color:#00fff7; background:#181f2a;">Código</th>
                                    <th style="color:#00fff7; background:#181f2a;">Descuento</th>
                                    <th style="color:#00fff7; background:#181f2a;">Influencer</th>
                                    <th style="color:#00fff7; background:#181f2a;">Comisión</th>
                                    <th style="color:#00fff7; background:#181f2a;">Usos</th>
                                    <th style="color:#00fff7; background:#181f2a;">Activo</th>
                                    <th style="color:#00fff7; background:#181f2a;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cupones as $c): ?>
                                    <tr style="background:#181f2a; color:#fff;">
                                        <td style="background:#181f2a; color:#00fff7; font-weight:bold;">
                                            <div><?= htmlspecialchars((string) $c['codigo']) ?></div>
                                            <div style="color:#b2f6ff; font-size:0.9em;">ID: <?= htmlspecialchars((string) $c['id']) ?></div>
                                        </td>
                                        <td style="background:#181f2a; color:#b2f6ff;">
                                            <div><?= htmlspecialchars((string) $c['tipo_descuento']) ?></div>
                                            <div><?= htmlspecialchars((string) $c['valor_descuento']) ?></div>
                                        </td>
                                        <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(admin_display_value($c['nombre_influencer'] ?? null)) ?></td>
                                        <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(admin_display_value(isset($c['comision_influencer']) && (float) $c['comision_influencer'] > 0 ? admin_format_money($c['comision_influencer']) . '%' : null)) ?></td>
                                        <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars((string) ($c['usos_actuales'] ?? 0)) ?> / <?= htmlspecialchars(admin_display_value($c['limite_usos'] ?? null, '∞')) ?></td>
                                        <td style="background:#181f2a; color:#b2f6ff;"><?= !empty($c['activo']) ? 'Sí' : 'No' ?></td>
                                        <td style="background:#181f2a;">
                                            <a href="?seccion=cupones&tab=cupones&editar_cupon=<?= urlencode((string) $c['id']) ?>" style="color:#00fff7; text-decoration:underline; margin-right:1em;">Editar</a>
                                            <a href="?seccion=cupones&tab=cupones&toggle_cupon=<?= urlencode((string) $c['id']) ?>" style="color:#00fff7; text-decoration:underline; margin-right:1em;"><?= !empty($c['activo']) ? 'Desactivar' : 'Activar' ?></a>
                                            <a href="?seccion=cupones&tab=cupones&borrar_cupon=<?= urlencode((string) $c['id']) ?>" style="color:#ff0059; text-decoration:underline;" onclick="return confirm('¿Eliminar este cupón?')">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-block d-md-none">
                        <?php foreach ($cupones as $c): ?>
                            <div style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1rem; color:#00fff7; margin-bottom:1.2rem;">
                                <div style="font-weight:bold; font-size:1.15em; color:#00fff7;"><?= htmlspecialchars((string) $c['codigo']) ?></div>
                                <div style="margin-top:0.45rem; color:#b2f6ff;">Tipo: <?= htmlspecialchars((string) $c['tipo_descuento']) ?> | Valor: <?= htmlspecialchars((string) $c['valor_descuento']) ?></div>
                                <div style="margin-top:0.45rem; color:#b2f6ff;">Influencer: <?= htmlspecialchars(admin_display_value($c['nombre_influencer'] ?? null)) ?></div>
                                <div style="margin-top:0.45rem; color:#b2f6ff;">Comisión: <?= htmlspecialchars(admin_display_value(isset($c['comision_influencer']) && (float) $c['comision_influencer'] > 0 ? admin_format_money($c['comision_influencer']) . '%' : null)) ?></div>
                                <div style="margin-top:0.45rem; color:#b2f6ff;">Usos: <?= htmlspecialchars((string) ($c['usos_actuales'] ?? 0)) ?> / <?= htmlspecialchars(admin_display_value($c['limite_usos'] ?? null, '∞')) ?></div>
                                <div style="margin-top:0.45rem; color:#b2f6ff;">Activo: <?= !empty($c['activo']) ? 'Sí' : 'No' ?></div>
                                <div style="display:flex; gap:1rem; margin-top:1rem; flex-wrap:wrap;">
                                    <a href="?seccion=cupones&tab=cupones&editar_cupon=<?= urlencode((string) $c['id']) ?>" style="color:#00fff7; text-decoration:underline; font-weight:bold;">Editar</a>
                                    <a href="?seccion=cupones&tab=cupones&toggle_cupon=<?= urlencode((string) $c['id']) ?>" style="color:#00fff7; text-decoration:underline; font-weight:bold;"><?= !empty($c['activo']) ? 'Desactivar' : 'Activar' ?></a>
                                    <a href="?seccion=cupones&tab=cupones&borrar_cupon=<?= urlencode((string) $c['id']) ?>" style="color:#ff0059; text-decoration:underline; font-weight:bold;" onclick="return confirm('¿Eliminar este cupón?')">Eliminar</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1.5rem;">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap">
                            <div>
                                <h3 class="h5 mb-1" style="color:#00fff7;">Cupones de Influencers</h3>
                                <p class="mb-0" style="color:#b2f6ff;">Ventas confirmadas con cupones asociados a influencers.</p>
                            </div>
                        </div>
                        <form method="GET" action="" class="row g-3 align-items-end mb-4">
                            <input type="hidden" name="seccion" value="cupones">
                            <input type="hidden" name="tab" value="influencers">
                            <div class="col-md-4">
                                <label class="form-label" style="color:#00fff7;">Filtrar estado</label>
                                <select name="filtro_estado_pago" class="form-select" onchange="this.form.submit()" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                    <option value="pendiente" <?= $influencerPaymentFilter === 'pendiente' ? 'selected' : '' ?>>Mostrar Solo Pendientes</option>
                                    <option value="pagado" <?= $influencerPaymentFilter === 'pagado' ? 'selected' : '' ?>>Mostrar Solo Pagados</option>
                                    <option value="todos" <?= $influencerPaymentFilter === 'todos' ? 'selected' : '' ?>>Mostrar Todos</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="color:#00fff7;">Desde</label>
                                <input type="date" name="fecha_desde" value="<?= htmlspecialchars((string) ($influencerDateFrom ?? '')) ?>" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="color:#00fff7;">Hasta</label>
                                <input type="date" name="fecha_hasta" value="<?= htmlspecialchars((string) ($influencerDateTo ?? '')) ?>" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-info flex-fill" style="background:#00fff7; color:#181f2a; border:none; box-shadow:0 0 8px #00fff7;">Filtrar</button>
                                <a href="<?= htmlspecialchars($influencerTabLink) ?>" class="btn btn-outline-info flex-fill" style="border:1px solid #00fff7; color:#00fff7;">Limpiar</a>
                            </div>
                        </form>
                        <?php if (empty($influencerSales)): ?>
                            <p class="mb-0" style="color:#b2f6ff;">Aún no hay ventas registradas para cupones de influencers.</p>
                        <?php else: ?>
                            <div class="table-responsive d-none d-md-block">
                                <table class="table align-middle" style="background:#181f2a; color:#00fff7; border-radius:12px;">
                                    <thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">
                                        <tr>
                                            <th style="color:#00fff7; background:#181f2a;">Nombre Influencer</th>
                                            <th style="color:#00fff7; background:#181f2a;">Cupón</th>
                                            <th style="color:#00fff7; background:#181f2a;">Teléfono</th>
                                            <th style="color:#00fff7; background:#181f2a;">Correo</th>
                                            <th style="color:#00fff7; background:#181f2a;">Comisión</th>
                                            <th style="color:#00fff7; background:#181f2a;">Paquete Vendido</th>
                                            <th style="color:#00fff7; background:#181f2a;">Fecha</th>
                                            <th style="color:#00fff7; background:#181f2a;">Pendiente/Pagado</th>
                                            <th style="color:#00fff7; background:#181f2a;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($influencerSales as $sale): ?>
                                            <tr style="background:#181f2a; color:#fff;">
                                                <td style="background:#181f2a; color:#00fff7; font-weight:bold;"><?= htmlspecialchars(admin_display_value($sale['nombre_influencer'] ?? null)) ?></td>
                                                <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(admin_display_value($sale['codigo_cupon'] ?? null)) ?></td>
                                                <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(admin_display_value($sale['telefono_influencer'] ?? null)) ?></td>
                                                <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(admin_display_value($sale['email_influencer'] ?? null)) ?></td>
                                                <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(admin_format_money($sale['comision_porcentaje'] ?? 0)) ?>%</td>
                                                <td style="background:#181f2a; color:#b2f6ff;">
                                                    <div><?= htmlspecialchars(admin_display_value($sale['paquete_vendido'] ?? null)) ?></div>
                                                    <div style="color:#00fff7; font-size:0.92em; margin-top:0.2rem;"><?= htmlspecialchars(admin_display_value($sale['juego_nombre'] ?? null)) ?></div>
                                                    <div style="color:#b2f6ff; font-size:0.88em; margin-top:0.2rem;">Pedido #<?= htmlspecialchars((string) ($sale['pedido_id'] ?? '')) ?></div>
                                                </td>
                                                <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(admin_display_value($sale['creado_en'] ?? null)) ?></td>
                                                <td style="background:#181f2a; color:#b2f6ff; min-width:170px;">
                                                    <form method="POST" action="" class="m-0">
                                                        <input type="hidden" name="actualizar_estado_pago_influencer" value="1">
                                                        <input type="hidden" name="pedido_id" value="<?= htmlspecialchars((string) $sale['pedido_id']) ?>">
                                                        <input type="hidden" name="filtro_estado_pago" value="<?= htmlspecialchars($influencerPaymentFilter) ?>">
                                                        <input type="hidden" name="fecha_desde" value="<?= htmlspecialchars((string) ($influencerDateFrom ?? '')) ?>">
                                                        <input type="hidden" name="fecha_hasta" value="<?= htmlspecialchars((string) ($influencerDateTo ?? '')) ?>">
                                                        <select name="estado_pago_influencer" class="form-select form-select-sm" onchange="this.form.submit()" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                                            <option value="pendiente" <?= ($sale['estado_pago_influencer'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                            <option value="pagado" <?= ($sale['estado_pago_influencer'] ?? 'pendiente') === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td style="background:#181f2a; color:#00ffb3; font-weight:bold;"><?= htmlspecialchars(admin_display_value($sale['moneda'] ?? null, '')) ?> <?= htmlspecialchars(admin_format_money($sale['total_comision'] ?? 0)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-block d-md-none">
                                <?php foreach ($influencerSales as $sale): ?>
                                    <div style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1rem; color:#00fff7; margin-bottom:1.2rem;">
                                        <div style="font-weight:bold; font-size:1.1em; color:#00fff7;"><?= htmlspecialchars(admin_display_value($sale['nombre_influencer'] ?? null)) ?></div>
                                        <div style="margin-top:0.45rem; color:#b2f6ff;">Cupón: <?= htmlspecialchars(admin_display_value($sale['codigo_cupon'] ?? null)) ?></div>
                                        <div style="margin-top:0.45rem; color:#b2f6ff;">Teléfono: <?= htmlspecialchars(admin_display_value($sale['telefono_influencer'] ?? null)) ?></div>
                                        <div style="margin-top:0.45rem; color:#b2f6ff;">Correo: <?= htmlspecialchars(admin_display_value($sale['email_influencer'] ?? null)) ?></div>
                                        <div style="margin-top:0.45rem; color:#b2f6ff;">Comisión: <?= htmlspecialchars(admin_format_money($sale['comision_porcentaje'] ?? 0)) ?>%</div>
                                        <div style="margin-top:0.45rem; color:#b2f6ff;">Paquete vendido: <?= htmlspecialchars(admin_display_value($sale['paquete_vendido'] ?? null)) ?></div>
                                        <div style="margin-top:0.2rem; color:#00fff7;">Juego: <?= htmlspecialchars(admin_display_value($sale['juego_nombre'] ?? null)) ?></div>
                                        <div style="margin-top:0.2rem; color:#b2f6ff;">Pedido: #<?= htmlspecialchars((string) ($sale['pedido_id'] ?? '')) ?></div>
                                        <div style="margin-top:0.2rem; color:#b2f6ff;">Fecha: <?= htmlspecialchars(admin_display_value($sale['creado_en'] ?? null)) ?></div>
                                        <form method="POST" action="" class="mt-2">
                                            <input type="hidden" name="actualizar_estado_pago_influencer" value="1">
                                            <input type="hidden" name="pedido_id" value="<?= htmlspecialchars((string) $sale['pedido_id']) ?>">
                                            <input type="hidden" name="filtro_estado_pago" value="<?= htmlspecialchars($influencerPaymentFilter) ?>">
                                            <input type="hidden" name="fecha_desde" value="<?= htmlspecialchars((string) ($influencerDateFrom ?? '')) ?>">
                                            <input type="hidden" name="fecha_hasta" value="<?= htmlspecialchars((string) ($influencerDateTo ?? '')) ?>">
                                            <label class="form-label" style="color:#00fff7;">Pendiente/Pagado</label>
                                            <select name="estado_pago_influencer" class="form-select form-select-sm" onchange="this.form.submit()" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                                                <option value="pendiente" <?= ($sale['estado_pago_influencer'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                <option value="pagado" <?= ($sale['estado_pago_influencer'] ?? 'pendiente') === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                                            </select>
                                        </form>
                                        <div style="margin-top:0.45rem; color:#00ffb3; font-weight:bold;">Total: <?= htmlspecialchars(admin_display_value($sale['moneda'] ?? null, '')) ?> <?= htmlspecialchars(admin_format_money($sale['total_comision'] ?? 0)) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php
                break;
            case 'movimientos':
                require_once __DIR__ . '/includes/db.php';
                $movementReference = trim((string) ($_GET['referencia'] ?? ''));
                $movementDateFrom = admin_normalize_date_filter($_GET['fecha_desde'] ?? null);
                $movementDateTo = admin_normalize_date_filter($_GET['fecha_hasta'] ?? null);
                $movementCurrency = strtoupper(trim((string) ($_GET['moneda'] ?? '')));
                $movementCheckedFilter = admin_normalize_movement_checked_filter($_GET['estado_verificacion'] ?? 'no_verificados');
                $movementOrderLinkFilter = admin_normalize_movement_order_link_filter($_GET['pedido_relacionado'] ?? 'todos');
                $movementSort = admin_normalize_movement_sort_column($_GET['orden'] ?? 'fecha_movimiento');
                $movementDirection = admin_normalize_sort_direction($_GET['direccion'] ?? 'desc');
                $movementPage = admin_normalize_positive_page($_GET['pagina'] ?? 1);
                $movementPerPage = admin_normalize_per_page($_GET['por_pagina'] ?? 15);
                if ($movementCurrency !== '' && preg_match('/^[A-Z0-9_-]{1,20}$/', $movementCurrency) !== 1) {
                    $movementCurrency = '';
                }

                $currencies = $pdo->query("SELECT DISTINCT moneda FROM movimientos WHERE moneda IS NOT NULL AND moneda <> '' ORDER BY moneda ASC")->fetchAll(PDO::FETCH_COLUMN);

                $movementBaseSql = ' FROM movimientos m LEFT JOIN pedidos p ON p.id = m.pedido_id WHERE 1=1';
                $movementParams = [];
                if ($movementReference !== '') {
                    $movementBaseSql .= ' AND m.referencia LIKE ?';
                    $movementParams[] = '%' . $movementReference . '%';
                }
                if ($movementDateFrom !== null) {
                    $movementBaseSql .= ' AND DATE(m.fecha_movimiento) >= ?';
                    $movementParams[] = $movementDateFrom;
                }
                if ($movementDateTo !== null) {
                    $movementBaseSql .= ' AND DATE(m.fecha_movimiento) <= ?';
                    $movementParams[] = $movementDateTo;
                }
                if ($movementCurrency !== '') {
                    $movementBaseSql .= ' AND m.moneda = ?';
                    $movementParams[] = $movementCurrency;
                }
                if ($movementOrderLinkFilter === 'con_pedido') {
                    $movementBaseSql .= ' AND m.pedido_id IS NOT NULL AND m.pedido_id > 0';
                } elseif ($movementOrderLinkFilter === 'sin_pedido') {
                    $movementBaseSql .= ' AND (m.pedido_id IS NULL OR m.pedido_id = 0)';
                }

                $movementStatsSql = 'SELECT COUNT(*) AS total, '
                    . 'SUM(CASE WHEN COALESCE(m.checked, 0) = 1 THEN 1 ELSE 0 END) AS checked_count, '
                    . 'SUM(CASE WHEN COALESCE(m.checked, 0) = 0 THEN 1 ELSE 0 END) AS unchecked_count'
                    . $movementBaseSql;
                $movementStatsStmt = $pdo->prepare($movementStatsSql);
                $movementStatsStmt->execute($movementParams);
                $movementStats = $movementStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $movementCheckedCount = (int) ($movementStats['checked_count'] ?? 0);
                $movementUncheckedCount = (int) ($movementStats['unchecked_count'] ?? 0);
                $movementAllCount = (int) ($movementStats['total'] ?? 0);

                if ($movementCheckedFilter === 'verificados') {
                    $movementBaseSql .= ' AND COALESCE(m.checked, 0) = 1';
                } elseif ($movementCheckedFilter === 'no_verificados') {
                    $movementBaseSql .= ' AND COALESCE(m.checked, 0) = 0';
                }

                $movementCountStmt = $pdo->prepare('SELECT COUNT(*)' . $movementBaseSql);
                $movementCountStmt->execute($movementParams);
                $movementTotal = (int) $movementCountStmt->fetchColumn();
                $movementTotalPages = max(1, (int) ceil($movementTotal / $movementPerPage));
                if ($movementPage > $movementTotalPages) {
                    $movementPage = $movementTotalPages;
                }
                $movementOffset = ($movementPage - 1) * $movementPerPage;

                $movementSortColumns = [
                    'referencia' => 'm.referencia',
                    'descripcion' => 'm.descripcion',
                    'fecha_movimiento' => 'm.fecha_movimiento',
                    'monto' => 'm.monto',
                    'moneda' => 'm.moneda',
                ];
                $movementOrderBy = $movementSortColumns[$movementSort] ?? 'm.fecha_movimiento';
                $movementsSql = 'SELECT m.id, m.referencia, m.descripcion, m.fecha_movimiento, m.monto, m.moneda, m.pedido_id, m.checked, p.estado AS pedido_estado'
                    . $movementBaseSql
                    . ' ORDER BY ' . $movementOrderBy . ' ' . strtoupper($movementDirection) . ', m.fecha_movimiento DESC, m.referencia DESC'
                    . ' LIMIT ' . (int) $movementPerPage . ' OFFSET ' . (int) $movementOffset;
                $movementsStmt = $pdo->prepare($movementsSql);
                $movementsStmt->execute($movementParams);
                $movimientos = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

                $movementBaseQuery = [
                    'referencia' => $movementReference,
                    'fecha_desde' => $movementDateFrom,
                    'fecha_hasta' => $movementDateTo,
                    'moneda' => $movementCurrency,
                    'estado_verificacion' => $movementCheckedFilter,
                    'pedido_relacionado' => $movementOrderLinkFilter,
                    'orden' => $movementSort,
                    'direccion' => $movementDirection,
                    'por_pagina' => $movementPerPage,
                ];
                $movementSortLabels = [
                    'referencia' => 'Referencia',
                    'descripcion' => 'Descripción',
                    'fecha_movimiento' => 'Fecha movimiento',
                    'monto' => 'Monto',
                    'moneda' => 'Moneda',
                ];
                $movementCheckedLabels = [
                    'no_verificados' => 'No verificados',
                    'verificados' => 'Verificados',
                    'todos' => 'Todos',
                ];
                $movementOrderLinkLabels = [
                    'todos' => 'Todos',
                    'con_pedido' => 'Con pedido',
                    'sin_pedido' => 'Sin pedido',
                ];
                $movementRangeStart = $movementTotal > 0 ? $movementOffset + 1 : 0;
                $movementRangeEnd = $movementTotal > 0 ? min($movementOffset + count($movimientos), $movementTotal) : 0;

                echo '<h2 class="display-6 fw-bold text-info mb-3">Movimientos Bancarios</h2>';
                echo '<p class="text-secondary mb-4">Listado de movimientos registrados en la tabla movimientos.</p>';

                echo '<form method="GET" action="/admin/movimientos" class="row g-3 mb-4 align-items-end" style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1.5rem;">';
                echo '<div class="col-12 col-lg-4">';
                echo '<label class="form-label" style="color:#00fff7;">Buscar por referencia</label>';
                echo '<input type="search" name="referencia" value="' . htmlspecialchars($movementReference) . '" class="form-control" placeholder="Ej. 5398344305" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '</div>';
                echo '<div class="col-6 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Desde</label>';
                echo '<input type="date" name="fecha_desde" value="' . htmlspecialchars((string) ($movementDateFrom ?? '')) . '" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '</div>';
                echo '<div class="col-6 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Hasta</label>';
                echo '<input type="date" name="fecha_hasta" value="' . htmlspecialchars((string) ($movementDateTo ?? '')) . '" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '</div>';
                echo '<div class="col-12 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Moneda</label>';
                echo '<select name="moneda" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '<option value="">Todas</option>';
                foreach ($currencies as $currencyOption) {
                    $currencyOption = trim((string) $currencyOption);
                    if ($currencyOption === '') {
                        continue;
                    }
                    $selected = $movementCurrency === strtoupper($currencyOption) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($currencyOption) . '"' . $selected . '>' . htmlspecialchars($currencyOption) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="col-6 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Ver movimientos</label>';
                echo '<select name="estado_verificacion" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                foreach ($movementCheckedLabels as $checkedKey => $checkedLabel) {
                    $selected = $movementCheckedFilter === $checkedKey ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($checkedKey) . '"' . $selected . '>' . htmlspecialchars($checkedLabel) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="col-6 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Pedidos</label>';
                echo '<select name="pedido_relacionado" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                foreach ($movementOrderLinkLabels as $orderFilterKey => $orderFilterLabel) {
                    $selected = $movementOrderLinkFilter === $orderFilterKey ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($orderFilterKey) . '"' . $selected . '>' . htmlspecialchars($orderFilterLabel) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="col-6 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Ordenar por</label>';
                echo '<select name="orden" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                foreach ($movementSortLabels as $sortKey => $sortLabel) {
                    $selected = $movementSort === $sortKey ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($sortKey) . '"' . $selected . '>' . htmlspecialchars($sortLabel) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="col-6 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Dirección</label>';
                echo '<select name="direccion" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '<option value="desc"' . ($movementDirection === 'desc' ? ' selected' : '') . '>Descendente</option>';
                echo '<option value="asc"' . ($movementDirection === 'asc' ? ' selected' : '') . '>Ascendente</option>';
                echo '</select>';
                echo '</div>';
                echo '<div class="col-6 col-lg-2">';
                echo '<label class="form-label" style="color:#00fff7;">Por página</label>';
                echo '<select name="por_pagina" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                foreach ([15, 30, 50] as $perPageOption) {
                    $selected = $movementPerPage === $perPageOption ? ' selected' : '';
                    echo '<option value="' . $perPageOption . '"' . $selected . '>' . $perPageOption . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="col-12 col-lg-2 d-flex gap-2">';
                echo '<button type="submit" class="btn btn-info flex-fill fw-bold" style="background:#00fff7; color:#181f2a; border:none; box-shadow:0 0 8px #00fff7;">Filtrar</button>';
                echo '<a href="/admin/movimientos" class="btn btn-outline-info flex-fill fw-bold" style="border:1px solid #00fff7; color:#00fff7; background:#181f2a;">Limpiar</a>';
                echo '</div>';
                echo '</form>';

                echo '<div class="mb-4" style="background:#111827; border-radius:16px; border:1px solid rgba(0,255,247,0.24); box-shadow:0 0 18px rgba(0,255,247,0.08); padding:1rem 1.1rem;">';
                echo '<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">';
                echo '<div>';
                echo '<div class="text-uppercase small fw-semibold" style="color:#7dd3fc; letter-spacing:0.08em;">Sincronización manual</div>';
                echo '<div class="text-secondary small">Consulta la API bancaria e inserta solo los movimientos nuevos en la tabla.</div>';
                echo '</div>';
                echo '<form method="POST" action="/admin/movimientos" class="m-0" data-sync-movements-form="1">';
                echo '<input type="hidden" name="actualizar_movimientos_api" value="1">';
                foreach ($movementBaseQuery as $queryKey => $queryValue) {
                    echo '<input type="hidden" name="' . htmlspecialchars((string) $queryKey) . '" value="' . htmlspecialchars((string) $queryValue) . '">';
                }
                echo '<input type="hidden" name="pagina" value="' . $movementPage . '">';
                echo '<button type="submit" class="btn btn-info fw-bold" data-sync-movements-button="1" style="min-width:240px; background:linear-gradient(135deg, #00fff7, #2dd4bf); color:#0f172a; border:none; box-shadow:0 0 16px rgba(0,255,247,0.28);">Actualizar Movimientos API</button>';
                echo '</form>';
                echo '</div>';
                echo '<div class="mt-3 d-none" data-sync-movements-status style="border-radius:14px; padding:0.9rem 1rem; border:1px solid rgba(0,255,247,0.22); background:rgba(15,23,42,0.88);">';
                echo '<div class="d-flex align-items-center gap-3">';
                echo '<span class="d-inline-flex align-items-center justify-content-center d-none" data-sync-movements-spinner="1" style="width:18px; height:18px; border-radius:999px; border:2px solid rgba(0,255,247,0.24); border-top-color:#00fff7; animation:movement-sync-spin 0.9s linear infinite;"></span>';
                echo '<div>';
                echo '<div class="fw-semibold" data-sync-movements-title style="color:#00fff7;">Listo para actualizar</div>';
                echo '<div class="small text-secondary" data-sync-movements-message>Presiona el botón para consultar la API bancaria.</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';

                echo '<div class="d-flex flex-wrap gap-2 align-items-center mb-3">';
                echo '<span class="small text-uppercase fw-semibold" style="color:#7dd3fc; letter-spacing:0.08em;">Verificación rápida</span>';
                foreach ($movementCheckedLabels as $checkedKey => $checkedLabel) {
                    $chipQuery = $movementBaseQuery;
                    $chipQuery['estado_verificacion'] = $checkedKey;
                    $chipQuery['pagina'] = 1;
                    $chipCount = $checkedKey === 'verificados' ? $movementCheckedCount : ($checkedKey === 'no_verificados' ? $movementUncheckedCount : $movementAllCount);
                    $isActiveChip = $movementCheckedFilter === $checkedKey;
                    $chipStyle = $isActiveChip
                        ? 'background:#00fff7; color:#181f2a; border:1px solid #00fff7; box-shadow:0 0 10px #00fff7;'
                        : 'background:#181f2a; color:#00fff7; border:1px solid rgba(0,255,247,0.45);';
                    echo '<a href="' . htmlspecialchars(admin_build_url('/admin/movimientos', $chipQuery)) . '" class="btn btn-sm rounded-pill fw-semibold" style="' . $chipStyle . '">';
                    echo htmlspecialchars($checkedLabel) . ': <span data-movement-count-label="' . htmlspecialchars($checkedKey) . '">' . $chipCount . '</span>';
                    echo '</a>';
                }
                echo '</div>';

                echo '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">';
                echo '<div class="text-info fw-semibold">Resultados: <span data-movement-results-total>' . $movementTotal . '</span></div>';
                echo '<div class="text-secondary small">Orden actual: ' . htmlspecialchars($movementSortLabels[$movementSort] ?? 'Fecha movimiento') . ' (' . strtoupper($movementDirection) . ')</div>';
                echo '<div class="text-secondary small">Estado: ' . htmlspecialchars($movementCheckedLabels[$movementCheckedFilter] ?? 'No verificados') . '</div>';
                echo '<div class="text-secondary small">Pedidos: ' . htmlspecialchars($movementOrderLinkLabels[$movementOrderLinkFilter] ?? 'Todos') . '</div>';
                if ($movementReference !== '' || $movementDateFrom !== null || $movementDateTo !== null || $movementCurrency !== '' || $movementCheckedFilter !== 'no_verificados' || $movementOrderLinkFilter !== 'todos') {
                    echo '<div class="text-secondary small">Filtros activos aplicados a la tabla de movimientos.</div>';
                }
                echo '</div>';

                if ($movementTotal === 0) {
                    echo '<div class="text-secondary">No hay movimientos registrados.</div>';
                    break;
                }

                echo '<div class="mb-3" style="background:linear-gradient(135deg, rgba(0,255,247,0.14), rgba(0,255,179,0.08)); border:1px solid rgba(0,255,247,0.35); border-radius:16px; padding:1rem 1.1rem; box-shadow:0 0 18px rgba(0,255,247,0.12);">';
                echo '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">';
                echo '<div>';
                echo '<div class="text-uppercase small fw-semibold" style="color:#7dd3fc; letter-spacing:0.08em;">Rango visible</div>';
                echo '<div class="fw-bold" data-movement-range-label style="color:#00fff7; font-size:1.05rem;">Mostrando ' . $movementRangeStart . ' - ' . $movementRangeEnd . ' de ' . $movementTotal . '</div>';
                echo '</div>';
                echo '<div class="text-end">';
                echo '<div class="text-uppercase small fw-semibold" style="color:#7dd3fc; letter-spacing:0.08em;">Paginación</div>';
                echo '<div class="fw-semibold" style="color:#b2f6ff;">Página ' . $movementPage . ' de ' . $movementTotalPages . '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';

                echo '<div class="table-responsive mb-4 d-none d-md-block" style="background:#10141a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1rem;">';
                echo '<table class="table align-middle mb-0" style="background:#181f2a; color:#00fff7; border-radius:12px;">';
                echo '<thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">';
                echo '<tr>';
                foreach (['referencia', 'descripcion', 'fecha_movimiento', 'monto', 'moneda'] as $movementColumnKey) {
                    $columnDirection = $movementSort === $movementColumnKey && $movementDirection === 'asc' ? 'desc' : 'asc';
                    $columnArrow = $movementSort === $movementColumnKey ? ($movementDirection === 'asc' ? ' ↑' : ' ↓') : '';
                    $columnQuery = $movementBaseQuery;
                    $columnQuery['orden'] = $movementColumnKey;
                    $columnQuery['direccion'] = $columnDirection;
                    $columnQuery['pagina'] = 1;
                    $minWidth = $movementColumnKey === 'descripcion' ? '260px' : ($movementColumnKey === 'fecha_movimiento' ? '180px' : ($movementColumnKey === 'monto' ? '130px' : ($movementColumnKey === 'moneda' ? '90px' : '160px')));
                    echo '<th style="color:#00fff7; background:#181f2a; min-width:' . $minWidth . ';">';
                    echo '<a href="' . htmlspecialchars(admin_build_url('/admin/movimientos', $columnQuery)) . '" style="color:#00fff7; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem;">' . htmlspecialchars($movementSortLabels[$movementColumnKey]) . '<span style="opacity:0.9;">' . htmlspecialchars($columnArrow) . '</span></a>';
                    echo '</th>';
                }
                echo '<th style="color:#00fff7; background:#181f2a; min-width:140px;">Verificar</th>';
                echo '<th style="color:#00fff7; background:#181f2a; min-width:170px;">Pedido relacionado</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                $rowAlt = false;
                foreach ($movimientos as $movimiento) {
                    $isChecked = (int) ($movimiento['checked'] ?? 0) === 1;
                    $cellBackground = $isChecked ? '#183f2b' : ($rowAlt ? '#151a24' : '#181f2a');
                    $rowStyle = $isChecked ? 'box-shadow: inset 0 0 0 1px rgba(125, 211, 252, 0.08);' : '';
                    $relatedOrderId = (int) ($movimiento['pedido_id'] ?? 0);
                    $relatedOrderStatus = trim((string) ($movimiento['pedido_estado'] ?? ''));
                    $relatedTab = in_array($relatedOrderStatus, ['pendiente', 'pagado', 'enviado', 'cancelado'], true) ? $relatedOrderStatus : 'pendiente';
                    $relatedOrderHref = '/admin/pedidos?pedido=' . $relatedOrderId . '&order_search=' . urlencode((string) $relatedOrderId) . '&tab=' . urlencode($relatedTab) . '#pedido-' . $relatedOrderId;
                    echo '<tr data-movement-row="' . (int) ($movimiento['id'] ?? 0) . '" data-movement-checked="' . ($isChecked ? '1' : '0') . '" style="' . $rowStyle . ' color:#fff;">';
                    echo '<td data-movement-cell="reference" style="background:' . $cellBackground . '; color:' . ($isChecked ? '#d9ffe8' : '#00fff7') . '; font-weight:600;">' . htmlspecialchars(admin_display_value($movimiento['referencia'] ?? null)) . '</td>';
                    echo '<td data-movement-cell="description" style="background:' . $cellBackground . '; color:' . ($isChecked ? '#ecfff2' : '#fff') . ';">' . htmlspecialchars(admin_display_value($movimiento['descripcion'] ?? null)) . '</td>';
                    echo '<td data-movement-cell="date" style="background:' . $cellBackground . '; color:' . ($isChecked ? '#d7ffe6' : '#b2f6ff') . ';">' . htmlspecialchars(admin_display_value($movimiento['fecha_movimiento'] ?? null)) . '</td>';
                    echo '<td data-movement-cell="amount" style="background:' . $cellBackground . '; color:#9effbd; font-weight:bold;">' . htmlspecialchars(admin_format_money($movimiento['monto'] ?? 0)) . '</td>';
                    echo '<td data-movement-cell="currency" style="background:' . $cellBackground . '; color:' . ($isChecked ? '#d7ffe6' : '#b2f6ff') . '; font-weight:600;">' . htmlspecialchars(admin_display_value($movimiento['moneda'] ?? null)) . '</td>';
                    echo '<td data-movement-cell="verify" data-movement-verify-cell="' . (int) ($movimiento['id'] ?? 0) . '" style="background:' . $cellBackground . '; color:#b2f6ff;">';
                    if ($isChecked) {
                        echo '<span class="fw-bold" data-movement-verified-text="' . (int) ($movimiento['id'] ?? 0) . '" style="color:#a8ffbf;">Verificado</span>';
                    } else {
                        echo '<form method="POST" action="/admin/movimientos" class="m-0 d-inline-flex align-items-center" data-verify-movement-form="' . (int) ($movimiento['id'] ?? 0) . '">';
                        echo '<input type="hidden" name="verificar_movimiento" value="1">';
                        echo '<input type="hidden" name="movimiento_id" value="' . (int) ($movimiento['id'] ?? 0) . '">';
                        foreach ($movementBaseQuery as $queryKey => $queryValue) {
                            echo '<input type="hidden" name="' . htmlspecialchars((string) $queryKey) . '" value="' . htmlspecialchars((string) $queryValue) . '">';
                        }
                        echo '<input type="hidden" name="pagina" value="' . $movementPage . '">';
                        echo '<button type="submit" class="btn btn-sm fw-bold" data-verify-movement-button="' . (int) ($movimiento['id'] ?? 0) . '" title="Verificar movimiento" style="min-width:52px; min-height:44px; border:1px solid #34d399; color:#ffffff; background:#15803d; box-shadow:0 0 12px rgba(52,211,153,0.35); font-size:1.35rem; line-height:1;">&#10003;</button>';
                        echo '</form>';
                    }
                    echo '</td>';
                    echo '<td data-movement-cell="order" style="background:' . $cellBackground . '; color:#b2f6ff;">';
                    if ($relatedOrderId > 0) {
                        echo '<a href="' . htmlspecialchars($relatedOrderHref) . '" class="btn btn-outline-info btn-sm fw-semibold" style="border-color:#00fff7; color:#00fff7; background:#181f2a;">Ver pedido #' . htmlspecialchars((string) $relatedOrderId) . '</a>';
                    } else {
                        echo '<span class="text-secondary">Sin pedido</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                    $rowAlt = !$rowAlt;
                }

                echo '</tbody></table>';
                echo '</div>';

                echo '<div class="d-block d-md-none">';
                foreach ($movimientos as $movimiento) {
                    $isChecked = (int) ($movimiento['checked'] ?? 0) === 1;
                    $relatedOrderId = (int) ($movimiento['pedido_id'] ?? 0);
                    $relatedOrderStatus = trim((string) ($movimiento['pedido_estado'] ?? ''));
                    $relatedTab = in_array($relatedOrderStatus, ['pendiente', 'pagado', 'enviado', 'cancelado'], true) ? $relatedOrderStatus : 'pendiente';
                    $relatedOrderHref = '/admin/pedidos?pedido=' . $relatedOrderId . '&order_search=' . urlencode((string) $relatedOrderId) . '&tab=' . urlencode($relatedTab) . '#pedido-' . $relatedOrderId;
                    $cardBackground = $isChecked ? 'linear-gradient(135deg, #183f2b, #1f5a35)' : '#181f2a';
                    $cardBorder = $isChecked ? '#7ee787' : '#00fff7';
                    echo '<div data-movement-card="' . (int) ($movimiento['id'] ?? 0) . '" data-movement-checked="' . ($isChecked ? '1' : '0') . '" style="background:' . $cardBackground . '; border-radius:16px; border:2px solid ' . $cardBorder . '; box-shadow:0 0 24px ' . ($isChecked ? 'rgba(126,231,135,0.24)' : '#00fff733') . '; padding:1rem; color:#00fff7; margin-bottom:1rem;">';
                    echo '<div style="display:grid; grid-template-columns:1fr; gap:0.75rem;">';
                    echo '<div style="padding-bottom:0.6rem; border-bottom:1px solid rgba(0,255,247,0.18);">';
                    echo '<div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#7dd3fc;">Referencia</div>';
                    echo '<div style="font-weight:700; color:#00fff7; word-break:break-word;">' . htmlspecialchars(admin_display_value($movimiento['referencia'] ?? null)) . '</div>';
                    echo '</div>';
                    echo '<div style="padding-bottom:0.6rem; border-bottom:1px solid rgba(0,255,247,0.18);">';
                    echo '<div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#7dd3fc;">Descripción</div>';
                    echo '<div style="color:#ffffff;">' . htmlspecialchars(admin_display_value($movimiento['descripcion'] ?? null)) . '</div>';
                    echo '</div>';
                    echo '<div style="padding-bottom:0.6rem; border-bottom:1px solid rgba(0,255,247,0.18);">';
                    echo '<div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#7dd3fc;">Fecha movimiento</div>';
                    echo '<div style="color:#b2f6ff;">' . htmlspecialchars(admin_display_value($movimiento['fecha_movimiento'] ?? null)) . '</div>';
                    echo '</div>';
                    echo '<div style="padding-bottom:0.6rem; border-bottom:1px solid rgba(0,255,247,0.18);">';
                    echo '<div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#7dd3fc;">Monto</div>';
                    echo '<div style="color:#00ffb3; font-weight:700;">' . htmlspecialchars(admin_format_money($movimiento['monto'] ?? 0)) . '</div>';
                    echo '</div>';
                    echo '<div>';
                    echo '<div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#7dd3fc;">Moneda</div>';
                    echo '<div style="color:#b2f6ff; font-weight:600;">' . htmlspecialchars(admin_display_value($movimiento['moneda'] ?? null)) . '</div>';
                    echo '</div>';
                    echo '<div style="padding-top:0.4rem;">';
                    echo '<div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#7dd3fc; margin-bottom:0.4rem;">Verificar</div>';
                    if ($isChecked) {
                        echo '<div class="fw-bold" data-movement-card-verified-text="' . (int) ($movimiento['id'] ?? 0) . '" style="color:#a8ffbf;">Verificado</div>';
                    } else {
                        echo '<form method="POST" action="/admin/movimientos" class="m-0" data-verify-movement-form="' . (int) ($movimiento['id'] ?? 0) . '">';
                        echo '<input type="hidden" name="verificar_movimiento" value="1">';
                        echo '<input type="hidden" name="movimiento_id" value="' . (int) ($movimiento['id'] ?? 0) . '">';
                        foreach ($movementBaseQuery as $queryKey => $queryValue) {
                            echo '<input type="hidden" name="' . htmlspecialchars((string) $queryKey) . '" value="' . htmlspecialchars((string) $queryValue) . '">';
                        }
                        echo '<input type="hidden" name="pagina" value="' . $movementPage . '">';
                        echo '<button type="submit" class="btn fw-bold" data-verify-movement-button="' . (int) ($movimiento['id'] ?? 0) . '" style="min-width:64px; min-height:48px; border:1px solid #34d399; color:#ffffff; background:#15803d; box-shadow:0 0 12px rgba(52,211,153,0.35); font-size:1.5rem; line-height:1;">&#10003;</button>';
                        echo '</form>';
                    }
                    echo '</div>';
                    echo '<div style="padding-top:0.4rem;">';
                    echo '<div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#7dd3fc; margin-bottom:0.4rem;">Pedido relacionado</div>';
                    if ($relatedOrderId > 0) {
                        echo '<a href="' . htmlspecialchars($relatedOrderHref) . '" class="btn btn-outline-info btn-sm fw-semibold" style="border-color:#00fff7; color:#00fff7; background:#181f2a;">Ver pedido #' . htmlspecialchars((string) $relatedOrderId) . '</a>';
                    } else {
                        echo '<div class="text-secondary">Sin pedido</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';

                if ($movementTotalPages > 1) {
                    echo '<div class="mt-4" style="background:#181f2a; border-radius:16px; border:1px solid rgba(0,255,247,0.3); box-shadow:0 0 18px rgba(0,255,247,0.08); padding:1rem;">';
                    echo '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">';
                    echo '<div class="text-secondary small">Página actual: ' . $movementPage . ' / ' . $movementTotalPages . '</div>';
                    echo '<div class="text-secondary small">Movimientos por página: ' . $movementPerPage . '</div>';
                    echo '</div>';
                    echo '<div class="d-grid d-sm-flex flex-wrap justify-content-center gap-2">';

                    $previousQuery = $movementBaseQuery;
                    $previousQuery['pagina'] = max(1, $movementPage - 1);
                    $nextQuery = $movementBaseQuery;
                    $nextQuery['pagina'] = min($movementTotalPages, $movementPage + 1);

                    if ($movementPage > 1) {
                        echo '<a href="' . htmlspecialchars(admin_build_url('/admin/movimientos', $previousQuery)) . '" class="btn btn-outline-info btn-sm fw-semibold" style="min-width:110px; border-color:#00fff7; color:#00fff7; background:#181f2a;">Anterior</a>';
                    }

                    $pageStart = max(1, $movementPage - 2);
                    $pageEnd = min($movementTotalPages, $movementPage + 2);
                    for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++) {
                        $pageQuery = $movementBaseQuery;
                        $pageQuery['pagina'] = $pageNumber;
                        $isActivePage = $pageNumber === $movementPage;
                        $pageStyle = $isActivePage
                            ? 'background:#00fff7; color:#181f2a; border:1px solid #00fff7; box-shadow:0 0 8px #00fff7;'
                            : 'border-color:#00fff7; color:#00fff7; background:#181f2a;';
                        echo '<a href="' . htmlspecialchars(admin_build_url('/admin/movimientos', $pageQuery)) . '" class="btn btn-sm fw-semibold ' . ($isActivePage ? 'btn-info' : 'btn-outline-info') . '" style="min-width:44px; ' . $pageStyle . '">' . $pageNumber . '</a>';
                    }

                    if ($movementPage < $movementTotalPages) {
                        echo '<a href="' . htmlspecialchars(admin_build_url('/admin/movimientos', $nextQuery)) . '" class="btn btn-outline-info btn-sm fw-semibold" style="min-width:110px; border-color:#00fff7; color:#00fff7; background:#181f2a;">Siguiente</a>';
                    }

                    echo '</div>';
                    echo '</div>';
                }
                break;
            case 'pedidos':
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Gestión de Pedidos</h2>';
                echo '<p class="text-gray-400">Aquí se listarán y gestionarán los pedidos.</p>';
                break;
            case 'configuracion':
                require_once __DIR__ . '/admin_configuracion.php';
                break;
        }
        ?>
    </div>
</div>
<?php if ($seccion === 'movimientos'): ?>
<style>
    .movement-toast {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 1400;
        min-width: 240px;
        max-width: min(90vw, 360px);
        padding: 0.95rem 1rem;
        border-radius: 14px;
        border: 1px solid rgba(126, 231, 135, 0.65);
        background: linear-gradient(135deg, rgba(24, 63, 43, 0.98), rgba(31, 90, 53, 0.98));
        color: #eafff0;
        box-shadow: 0 0 24px rgba(126, 231, 135, 0.28);
        font-weight: 700;
        opacity: 0;
        transform: translateY(14px);
        pointer-events: none;
        transition: opacity 180ms ease, transform 220ms ease;
    }
    .movement-toast.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    @keyframes movement-sync-spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
    [data-movement-row],
    [data-movement-card] {
        transition: opacity 220ms ease, transform 260ms ease, box-shadow 220ms ease, filter 220ms ease;
    }
    .movement-removing {
        opacity: 0;
        transform: translateY(-8px) scale(0.985);
        filter: saturate(0.88);
    }
</style>
<script>
(() => {
    const movementForms = document.querySelectorAll('[data-verify-movement-form]');
    const syncForm = document.querySelector('[data-sync-movements-form]');
    if (!movementForms.length && !syncForm) {
        return;
    }
    const currentCheckedFilter = <?php echo json_encode($movementCheckedFilter ?? 'no_verificados', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const syncStatus = document.querySelector('[data-sync-movements-status]');
    const syncStatusTitle = document.querySelector('[data-sync-movements-title]');
    const syncStatusMessage = document.querySelector('[data-sync-movements-message]');
    const syncSpinner = document.querySelector('[data-sync-movements-spinner]');
    const syncButton = document.querySelector('[data-sync-movements-button]');

        function wait(ms) {
                return new Promise((resolve) => window.setTimeout(resolve, ms));
        }

    function setSyncStatus(type, title, message, isLoading) {
        if (!syncStatus || !syncStatusTitle || !syncStatusMessage) {
            return;
        }

        syncStatus.classList.remove('d-none');
        syncStatus.style.borderColor = type === 'success'
            ? 'rgba(126,231,135,0.35)'
            : (type === 'error' ? 'rgba(248,113,113,0.35)' : 'rgba(0,255,247,0.24)');
        syncStatus.style.background = type === 'success'
            ? 'rgba(24,63,43,0.92)'
            : (type === 'error' ? 'rgba(69,22,22,0.92)' : 'rgba(15,23,42,0.88)');
        syncStatusTitle.style.color = type === 'success'
            ? '#a8ffbf'
            : (type === 'error' ? '#fca5a5' : '#00fff7');
        syncStatusTitle.textContent = title;
        syncStatusMessage.textContent = message;

        if (syncSpinner) {
            syncSpinner.classList.toggle('d-none', !isLoading);
        }
    }

        function showMovementToast(message) {
                let toast = document.querySelector('[data-movement-toast]');
                if (!toast) {
                        toast = document.createElement('div');
                        toast.className = 'movement-toast';
                        toast.setAttribute('data-movement-toast', '1');
                        document.body.appendChild(toast);
                }

                toast.textContent = message;
                toast.classList.add('is-visible');

                window.clearTimeout(showMovementToast._timerId);
                showMovementToast._timerId = window.setTimeout(() => {
                        toast.classList.remove('is-visible');
                }, 2000);
        }

    function applyVerifiedDesktopState(movementId) {
        const row = document.querySelector(`[data-movement-row="${movementId}"]`);
        if (!row) {
            return;
        }

        row.dataset.movementChecked = '1';
        row.style.boxShadow = 'inset 0 0 0 1px rgba(125, 211, 252, 0.08)';

        const desktopColors = {
            reference: '#d9ffe8',
            description: '#ecfff2',
            date: '#d7ffe6',
            amount: '#9effbd',
            currency: '#d7ffe6',
            verify: '#b2f6ff',
            order: '#b2f6ff'
        };

        row.querySelectorAll('[data-movement-cell]').forEach((cell) => {
            const cellKey = cell.getAttribute('data-movement-cell') || '';
            cell.style.background = '#183f2b';
            if (desktopColors[cellKey]) {
                cell.style.color = desktopColors[cellKey];
            }
        });

        const verifyCell = row.querySelector(`[data-movement-verify-cell="${movementId}"]`);
        if (verifyCell) {
            verifyCell.innerHTML = '<span class="fw-bold" style="color:#a8ffbf;">Verificado</span>';
        }
    }

    function applyVerifiedMobileState(movementId) {
        const card = document.querySelector(`[data-movement-card="${movementId}"]`);
        if (!card) {
            return;
        }

        card.dataset.movementChecked = '1';
        card.style.background = 'linear-gradient(135deg, #183f2b, #1f5a35)';
        card.style.borderColor = '#7ee787';
        card.style.boxShadow = '0 0 24px rgba(126,231,135,0.24)';

        const form = card.querySelector('[data-verify-movement-form]');
        if (form) {
            form.outerHTML = '<div class="fw-bold" style="color:#a8ffbf;">Verificado</div>';
        }
    }

    function updateMovementCountersAfterVerify() {
        const uncheckedLabel = document.querySelector('[data-movement-count-label="no_verificados"]');
        const checkedLabel = document.querySelector('[data-movement-count-label="verificados"]');

        if (uncheckedLabel) {
            const nextValue = Math.max(0, Number(uncheckedLabel.textContent || '0') - 1);
            uncheckedLabel.textContent = String(nextValue);
        }
        if (checkedLabel) {
            checkedLabel.textContent = String(Number(checkedLabel.textContent || '0') + 1);
        }
    }

    function updateVisibleResultsAfterRemoval() {
        const resultsTotal = document.querySelector('[data-movement-results-total]');
        const rangeLabel = document.querySelector('[data-movement-range-label]');
        if (resultsTotal) {
            const nextValue = Math.max(0, Number(resultsTotal.textContent || '0') - 1);
            resultsTotal.textContent = String(nextValue);
            if (rangeLabel) {
                const currentText = rangeLabel.textContent || '';
                rangeLabel.textContent = currentText.replace(/de\s+\d+$/, 'de ' + nextValue);
            }
        }
    }

    function removeMovementFromView(movementId) {
        const row = document.querySelector(`[data-movement-row="${movementId}"]`);
        if (row) {
            row.classList.add('movement-removing');
        }

        const card = document.querySelector(`[data-movement-card="${movementId}"]`);
        if (card) {
            card.classList.add('movement-removing');
        }

        window.setTimeout(() => {
            if (row) {
                row.remove();
            }

            if (card) {
                card.remove();
            }

            updateVisibleResultsAfterRemoval();
        }, 320);
    }

    async function animateVerifiedMovement(movementId, shouldRemoveAfterAnimation) {
        applyVerifiedDesktopState(movementId);
        applyVerifiedMobileState(movementId);
        showMovementToast('Movimiento verificado');

        if (shouldRemoveAfterAnimation) {
            await wait(900);
            removeMovementFromView(movementId);
        }
    }

    async function verifyMovement(form) {
        const button = form.querySelector('[data-verify-movement-button]');
        const formData = new FormData(form);
        const movementId = formData.get('movimiento_id');

        if (button) {
            button.disabled = true;
            button.style.opacity = '0.7';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'No se pudo verificar el movimiento.');
            }

            updateMovementCountersAfterVerify();
            if (currentCheckedFilter === 'no_verificados') {
                await animateVerifiedMovement(String(movementId), true);
            } else {
                await animateVerifiedMovement(String(movementId), false);
            }
        } catch (error) {
            if (button) {
                button.disabled = false;
                button.style.opacity = '1';
            }
            alert(error.message || 'No se pudo verificar el movimiento.');
        }
    }

    movementForms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            verifyMovement(form);
        });
    });

    if (syncForm) {
        syncForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(syncForm);
            if (syncButton) {
                syncButton.disabled = true;
                syncButton.style.opacity = '0.75';
            }

            setSyncStatus('loading', 'Consultando API bancaria', 'Buscando nuevos movimientos y registrando cambios en la tabla movimientos...', true);

            try {
                const response = await fetch(syncForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo actualizar los movimientos desde la API.');
                }

                if (data.has_new_movements) {
                    setSyncStatus('success', 'Nuevos movimientos disponibles', data.message || 'Se registraron nuevos movimientos en la tabla.', false);
                    showMovementToast('Movimientos actualizados desde la API');
                    await wait(1500);
                    window.location.reload();
                    return;
                }

                setSyncStatus('info', 'Sin movimientos nuevos', data.message || 'No hay movimientos nuevos para actualizar.', false);
                await wait(3000);
                if (syncStatus) {
                    syncStatus.classList.add('d-none');
                }
            } catch (error) {
                setSyncStatus('error', 'No se pudo actualizar', error.message || 'Ocurrió un error al consultar la API bancaria.', false);
            } finally {
                if (syncButton) {
                    syncButton.disabled = false;
                    syncButton.style.opacity = '1';
                }
            }
        });
    }
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
