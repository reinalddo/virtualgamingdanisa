<?php

require_once __DIR__ . '/store_config.php';

if (!function_exists('recharge_notifications_current_tenant_slug')) {
    function recharge_notifications_current_tenant_slug(): string {
        return function_exists('resolve_tenant_slug') ? resolve_tenant_slug() : '';
    }
}

if (!function_exists('recharge_notifications_is_enabled')) {
    function recharge_notifications_is_enabled(): bool {
        return store_config_get('recarga_notificaciones_activas', '1') !== '0';
    }
}

if (!function_exists('recharge_notifications_is_public_context')) {
    function recharge_notifications_is_public_context(): bool {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        return preg_match('#/(admin)(?:/|\.php|$)#', $scriptName) !== 1;
    }
}

if (!function_exists('recharge_notifications_logo_path')) {
    function recharge_notifications_logo_path(): string {
        $configuredLogo = trim(store_config_get('recarga_notificaciones_logo', ''));
        if ($configuredLogo !== '') {
            return $configuredLogo;
        }

        return trim(store_config_get('logo_tienda', ''));
    }
}

if (!function_exists('recharge_notifications_mask_user_label')) {
    function recharge_notifications_mask_user_label(string $email): string {
        $source = trim((string) strtok($email, '@'));
        if ($source === '') {
            $source = trim($email);
        }

        $source = preg_replace('/[^[:alnum:]]+/u', '', $source) ?? $source;
        if ($source === '') {
            return 'Us***';
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($source, 'UTF-8')
            : strlen($source);
        $visible = (int) ceil($length * 0.2);
        if ($length >= 5) {
            $visible = max($visible, 2);
        }
        if ($length >= 8) {
            $visible = max($visible, 3);
        }
        $visible = min(max($visible, 1), 4);

        $prefix = function_exists('mb_substr')
            ? mb_substr($source, 0, $visible, 'UTF-8')
            : substr($source, 0, $visible);

        return $prefix . '***';
    }
}

if (!function_exists('recharge_notifications_recharge_label')) {
    function recharge_notifications_recharge_label(array $order): string {
        $packageName = trim((string) ($order['paquete_nombre'] ?? ''));
        if ($packageName !== '') {
            return $packageName;
        }

        $amountLabel = trim((string) ($order['paquete_cantidad'] ?? ''));
        if ($amountLabel !== '') {
            return $amountLabel;
        }

        return 'una recarga';
    }
}

if (!function_exists('recharge_notifications_build_payload')) {
    function recharge_notifications_build_payload(array $order, int $notificationId): array {
        $userLabel = recharge_notifications_mask_user_label((string) ($order['email'] ?? ''));
        $rechargeLabel = recharge_notifications_recharge_label($order);
        $gameName = trim((string) ($order['juego_nombre'] ?? ''));
        $detail = $gameName !== ''
            ? $rechargeLabel . ' en ' . $gameName
            : $rechargeLabel;

        return [
            'id' => $notificationId,
            'order_id' => (int) ($order['id'] ?? 0),
            'title' => $userLabel . ' acaba de recargar',
            'detail' => $detail,
            'user_label' => $userLabel,
            'recharge_label' => $rechargeLabel,
            'game_name' => $gameName,
        ];
    }
}

if (!function_exists('recharge_notifications_ensure_table')) {
    function recharge_notifications_ensure_table(mysqli $mysqli): void {
        $mysqli->query(
            "CREATE TABLE IF NOT EXISTS recarga_notificaciones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                tenant_slug VARCHAR(80) DEFAULT NULL,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_pedido_id (pedido_id),
                KEY idx_tenant_creado (tenant_slug, creado_en),
                KEY idx_creado (creado_en)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('recharge_notifications_emit_for_order')) {
    function recharge_notifications_emit_for_order(mysqli $mysqli, array $order): void {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        recharge_notifications_ensure_table($mysqli);
        $tenantSlug = trim((string) ($order['tenant_slug'] ?? recharge_notifications_current_tenant_slug()));
        $stmt = $mysqli->prepare('INSERT IGNORE INTO recarga_notificaciones (pedido_id, tenant_slug) VALUES (?, ?)');
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('is', $orderId, $tenantSlug);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('recharge_notifications_current_cursor')) {
    function recharge_notifications_current_cursor(mysqli $mysqli, string $tenantSlug = ''): int {
        recharge_notifications_ensure_table($mysqli);

        if ($tenantSlug !== '') {
            $stmt = $mysqli->prepare('SELECT COALESCE(MAX(id), 0) AS max_id FROM recarga_notificaciones WHERE tenant_slug = ?');
            if (!$stmt) {
                return 0;
            }

            $stmt->bind_param('s', $tenantSlug);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            return (int) ($row['max_id'] ?? 0);
        }

        $result = $mysqli->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM recarga_notificaciones');
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->free();
        }

        return (int) ($row['max_id'] ?? 0);
    }
}

if (!function_exists('recharge_notifications_fetch_since')) {
    function recharge_notifications_fetch_since(mysqli $mysqli, int $cursor, string $tenantSlug = '', int $limit = 20): array {
        recharge_notifications_ensure_table($mysqli);

        $cursor = max(0, $cursor);
        $limit = max(1, min($limit, 50));

        if ($tenantSlug !== '') {
            $stmt = $mysqli->prepare(
                'SELECT rn.id AS notification_id, p.id, p.email, p.juego_nombre, p.paquete_nombre, p.paquete_cantidad, p.tenant_slug
                 FROM recarga_notificaciones rn
                 INNER JOIN pedidos p ON p.id = rn.pedido_id
                 WHERE rn.id > ? AND rn.tenant_slug = ?
                 ORDER BY rn.id ASC
                 LIMIT ?'
            );
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('isi', $cursor, $tenantSlug, $limit);
        } else {
            $stmt = $mysqli->prepare(
                'SELECT rn.id AS notification_id, p.id, p.email, p.juego_nombre, p.paquete_nombre, p.paquete_cantidad, p.tenant_slug
                 FROM recarga_notificaciones rn
                 INNER JOIN pedidos p ON p.id = rn.pedido_id
                 WHERE rn.id > ?
                 ORDER BY rn.id ASC
                 LIMIT ?'
            );
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('ii', $cursor, $limit);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $notifications[] = recharge_notifications_build_payload($row, (int) ($row['notification_id'] ?? 0));
        }
        $stmt->close();

        return $notifications;
    }
}