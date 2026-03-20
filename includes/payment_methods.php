<?php

function payment_methods_db(): mysqli {
    global $mysqli;

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        require_once __DIR__ . '/db_connect.php';
    }

    return $mysqli;
}

function payment_methods_ensure_table(): void {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $mysqli = payment_methods_db();
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(160) NOT NULL,
    datos TEXT NOT NULL,
    moneda_id INT NULL,
    referencia_digitos INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_methods_activo (activo),
    INDEX idx_payment_methods_nombre (nombre),
    INDEX idx_payment_methods_moneda_id (moneda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $mysqli->query($sql);

    $columns = [];
    $columnResult = $mysqli->query('SHOW COLUMNS FROM payment_methods');
    if ($columnResult instanceof mysqli_result) {
        while ($column = $columnResult->fetch_assoc()) {
            $columns[$column['Field']] = true;
        }
    }

    if (!isset($columns['moneda_id'])) {
        $mysqli->query('ALTER TABLE payment_methods ADD COLUMN moneda_id INT NULL AFTER datos');
    }
    if (!isset($columns['referencia_digitos'])) {
        $mysqli->query('ALTER TABLE payment_methods ADD COLUMN referencia_digitos INT NOT NULL DEFAULT 0 AFTER moneda_id');
    }

    $hasCurrencyIndex = false;
    $indexResult = $mysqli->query("SHOW INDEX FROM payment_methods WHERE Key_name = 'idx_payment_methods_moneda_id'");
    if ($indexResult instanceof mysqli_result) {
        $hasCurrencyIndex = $indexResult->num_rows > 0;
    }
    if (!$hasCurrencyIndex) {
        $mysqli->query('ALTER TABLE payment_methods ADD INDEX idx_payment_methods_moneda_id (moneda_id)');
    }

    $initialized = true;
}

function payment_methods_currency_options(): array {
    $mysqli = payment_methods_db();
    $currencies = [];
    $res = $mysqli->query('SELECT id, nombre, clave FROM monedas ORDER BY es_base DESC, nombre ASC, id ASC');
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $currencies[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre' => (string) ($row['nombre'] ?? ''),
                'clave' => (string) ($row['clave'] ?? ''),
            ];
        }
    }

    return $currencies;
}

function payment_methods_currency_exists(int $currencyId): bool {
    if ($currencyId <= 0) {
        return false;
    }

    $mysqli = payment_methods_db();
    $stmt = $mysqli->prepare('SELECT id FROM monedas WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $currencyId);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res instanceof mysqli_result && (bool) $res->fetch_assoc();
    $stmt->close();

    return $exists;
}

function payment_methods_all(): array {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $items = [];
    $res = $mysqli->query("SELECT pm.*, m.nombre AS moneda_nombre, m.clave AS moneda_clave
        FROM payment_methods pm
        LEFT JOIN monedas m ON m.id = pm.moneda_id
        ORDER BY pm.activo DESC, pm.nombre ASC, pm.id DESC");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['activo'] = (int) $row['activo'];
            $row['moneda_id'] = isset($row['moneda_id']) ? (int) $row['moneda_id'] : 0;
            $row['referencia_digitos'] = isset($row['referencia_digitos']) ? max(0, (int) $row['referencia_digitos']) : 0;
            $items[] = $row;
        }
    }

    return $items;
}

function payment_methods_find(int $id): ?array {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $stmt = $mysqli->prepare("SELECT pm.*, m.nombre AS moneda_nombre, m.clave AS moneda_clave
        FROM payment_methods pm
        LEFT JOIN monedas m ON m.id = pm.moneda_id
        WHERE pm.id = ?
        LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $item = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$item) {
        return null;
    }

    $item['id'] = (int) $item['id'];
    $item['activo'] = (int) $item['activo'];
    $item['moneda_id'] = isset($item['moneda_id']) ? (int) $item['moneda_id'] : 0;
    $item['referencia_digitos'] = isset($item['referencia_digitos']) ? max(0, (int) $item['referencia_digitos']) : 0;
    return $item;
}

function payment_methods_validate_form(array $input): array {
    $nombre = trim((string) ($input['nombre_metodo_pago'] ?? ''));
    $datos = trim((string) ($input['datos_metodo_pago'] ?? ''));
    $monedaId = isset($input['moneda_metodo_pago']) ? (int) $input['moneda_metodo_pago'] : 0;
    $referenciaDigitos = isset($input['referencia_digitos_metodo_pago']) && $input['referencia_digitos_metodo_pago'] !== ''
        ? (int) $input['referencia_digitos_metodo_pago']
        : 0;
    $activo = isset($input['activo_metodo_pago']) ? 1 : 0;
    $errors = [];

    if ($nombre === '') {
        $errors[] = 'El nombre del método de pago es obligatorio.';
    }
    if ($datos === '') {
        $errors[] = 'Los datos del método de pago son obligatorios.';
    }
    if ($monedaId <= 0) {
        $errors[] = 'Debes seleccionar la moneda del método de pago.';
    } elseif (!payment_methods_currency_exists($monedaId)) {
        $errors[] = 'La moneda seleccionada para el método de pago no es válida.';
    }
    if ($referenciaDigitos < 0) {
        $errors[] = 'Los dígitos de referencia no pueden ser negativos.';
    }

    return [
        'is_valid' => empty($errors),
        'errors' => $errors,
        'data' => [
            'nombre' => $nombre,
            'datos' => $datos,
            'moneda_id' => $monedaId,
            'referencia_digitos' => $referenciaDigitos,
            'activo' => $activo,
        ],
    ];
}

function payment_methods_save(array $data, ?int $id = null): bool {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    if ($id === null) {
        $stmt = $mysqli->prepare('INSERT INTO payment_methods (nombre, datos, moneda_id, referencia_digitos, activo) VALUES (?, ?, ?, ?, ?)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssiii', $data['nombre'], $data['datos'], $data['moneda_id'], $data['referencia_digitos'], $data['activo']);
    } else {
        $stmt = $mysqli->prepare('UPDATE payment_methods SET nombre = ?, datos = ?, moneda_id = ?, referencia_digitos = ?, activo = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssiiii', $data['nombre'], $data['datos'], $data['moneda_id'], $data['referencia_digitos'], $data['activo'], $id);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function payment_methods_delete(int $id): bool {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $stmt = $mysqli->prepare('DELETE FROM payment_methods WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function payment_methods_toggle(int $id): bool {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $stmt = $mysqli->prepare('UPDATE payment_methods SET activo = NOT activo WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function payment_methods_active_by_currency(): array {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $items = [];
    $res = $mysqli->query("SELECT pm.id, pm.nombre, pm.datos, pm.moneda_id, pm.referencia_digitos,
        m.nombre AS moneda_nombre, m.clave AS moneda_clave
        FROM payment_methods pm
        INNER JOIN monedas m ON m.id = pm.moneda_id
        WHERE pm.activo = 1
        ORDER BY m.nombre ASC, pm.nombre ASC, pm.id ASC");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $currencyKey = strtoupper(trim((string) ($row['moneda_clave'] ?? '')));
            if ($currencyKey === '') {
                continue;
            }
            if (!isset($items[$currencyKey])) {
                $items[$currencyKey] = [];
            }
            $items[$currencyKey][] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre' => (string) ($row['nombre'] ?? ''),
                'datos' => (string) ($row['datos'] ?? ''),
                'moneda_id' => (int) ($row['moneda_id'] ?? 0),
                'moneda_nombre' => (string) ($row['moneda_nombre'] ?? ''),
                'moneda_clave' => (string) ($row['moneda_clave'] ?? ''),
                'referencia_digitos' => max(0, (int) ($row['referencia_digitos'] ?? 0)),
            ];
        }
    }

    return $items;
}