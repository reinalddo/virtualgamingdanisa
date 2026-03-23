<?php

require_once __DIR__ . '/store_config.php';

function recargas_api_key(): string {
    return trim(store_config_get('recargas_api_key', ''));
}

function recargas_api_is_configured(): bool {
    return recargas_api_key() !== '';
}

function recargas_api_http_get_json(string $url, array $headers = [], int $timeout = 20, bool $verifySsl = true): array {
    $body = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('No se pudo consultar la API de recargas: ' . $error);
        }

        if ($status >= 400) {
            throw new RuntimeException('La API de recargas respondió con código HTTP ' . $status . '.');
        }

        $body = $response;
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('No se pudo consultar la API de recargas.');
        }

        $body = $response;
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        throw new RuntimeException('La API de recargas no devolvió un JSON válido.');
    }

    return $data;
}

function recargas_api_http_post_json(string $url, array $payload, array $headers = [], int $timeout = 25, bool $verifySsl = true): array {
    $body = null;
    $requestBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($requestBody)) {
        throw new RuntimeException('No se pudo serializar la solicitud JSON para la API de recargas.');
    }

    $httpHeaders = array_merge(['Content-Type: application/json'], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('No se pudo consultar la API de recargas: ' . $error);
        }

        if ($status >= 400) {
            throw new RuntimeException('La API de recargas respondió con código HTTP ' . $status . '.');
        }

        $body = $response;
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => implode("\r\n", $httpHeaders),
                'content' => $requestBody,
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('No se pudo consultar la API de recargas.');
        }

        $body = $response;
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        throw new RuntimeException('La API de recargas no devolvió un JSON válido.');
    }

    return $data;
}

function recargas_api_fetch_products(): array {
    static $cachedProducts = null;

    if ($cachedProducts !== null) {
        return $cachedProducts;
    }

    $apiKey = recargas_api_key();
    if ($apiKey === '') {
        throw new RuntimeException('Configura primero la API KEY de recargas.');
    }

    try {
        $data = recargas_api_http_get_json(
            'https://tiendagiftven.tech/api/v1/productos',
            ['X-API-Key: ' . $apiKey],
            20,
            true
        );
    } catch (Throwable $e) {
        $message = (string) $e->getMessage();
        $sslIssue = stripos($message, 'SSL certificate problem') !== false
            || stripos($message, 'unable to get local issuer certificate') !== false;

        if (!$sslIssue) {
            throw $e;
        }

        $data = recargas_api_http_get_json(
            'https://tiendagiftven.tech/api/v1/productos',
            ['X-API-Key: ' . $apiKey],
            20,
            false
        );
    }

    $products = $data['productos'] ?? null;
    if (!is_array($products)) {
        throw new RuntimeException('La API de recargas no devolvió una lista válida de productos.');
    }

    $cachedProducts = $products;
    return $cachedProducts;
}

function recargas_api_fetch_categories(): array {
    $products = recargas_api_fetch_products();
    $categories = [];

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        $category = trim((string) ($product['categoria'] ?? ''));
        if ($category === '') {
            continue;
        }

        $categories[$category] = $category;
    }

    natcasesort($categories);
    return array_values($categories);
}

function recargas_api_fetch_products_by_category(string $category): array {
    $normalizedCategory = mb_strtolower(trim($category), 'UTF-8');
    if ($normalizedCategory === '') {
        return [];
    }

    $matches = [];
    foreach (recargas_api_fetch_products() as $product) {
        if (!is_array($product)) {
            continue;
        }

        $productCategory = mb_strtolower(trim((string) ($product['categoria'] ?? '')), 'UTF-8');
        if ($productCategory !== $normalizedCategory) {
            continue;
        }

        $matches[] = $product;
    }

    usort($matches, static function (array $left, array $right): int {
        return strcmp((string) ($left['nombre'] ?? ''), (string) ($right['nombre'] ?? ''));
    });

    return $matches;
}

function recargas_api_fetch_product_by_id(int $productId): ?array {
    if ($productId <= 0) {
        return null;
    }

    foreach (recargas_api_fetch_products() as $product) {
        if (!is_array($product)) {
            continue;
        }

        if ((int) ($product['id'] ?? 0) === $productId) {
            return $product;
        }
    }

    return null;
}

function recargas_api_extract_required_field_meta($field): ?array {
    if (is_array($field)) {
        $rawName = trim((string) ($field['nombre'] ?? $field['name'] ?? ''));
        $rawDescription = trim((string) ($field['descripcion'] ?? $field['label'] ?? ''));
        $rawType = strtolower(trim((string) ($field['tipo'] ?? $field['type'] ?? 'string')));
        $options = $field['opciones'] ?? [];
    } else {
        $rawName = trim((string) $field);
        $rawDescription = '';
        $rawType = 'string';
        $options = [];
    }

    $name = strtolower($rawName);
    $name = preg_replace('/[^a-z0-9_]+/u', '', $name) ?? '';
    if ($name === '') {
        return null;
    }

    if (!is_array($options)) {
        $options = [];
    }

    return [
        'name' => $name,
        'description' => $rawDescription,
        'type' => $rawType !== '' ? $rawType : 'string',
        'options' => $options,
    ];
}

function recargas_api_normalize_required_fields($fields): array {
    if (!is_array($fields)) {
        return [];
    }

    $normalized = [];
    foreach ($fields as $field) {
        $fieldMeta = recargas_api_extract_required_field_meta($field);
        if ($fieldMeta === null || isset($normalized[$fieldMeta['name']])) {
            continue;
        }

        $normalized[$fieldMeta['name']] = $fieldMeta;
    }

    return array_values($normalized);
}

function recargas_api_field_label(string $fieldName): string {
    $normalized = strtolower(trim($fieldName));

    return match ($normalized) {
        'id_juego', 'player_id' => 'ID del jugador',
        'user_id' => 'ID del usuario',
        'input1' => 'Dato principal',
        'input2' => 'Dato adicional',
        'zona', 'zone' => 'Zona',
        'zone_id' => 'ID de zona',
        'server' => 'Servidor',
        'server_id' => 'ID de servidor',
        'gamepoint' => 'Game Point',
        'email' => 'Correo',
        'telefono', 'phone' => 'Telefono',
        default => ucwords(str_replace('_', ' ', $normalized !== '' ? $normalized : 'dato')),
    };
}

function recargas_api_field_placeholder(string $fieldName): string {
    $normalized = strtolower(trim($fieldName));

    return match ($normalized) {
        'id_juego', 'player_id' => 'Ingresa el ID del jugador',
        'user_id' => 'Ingresa el ID del usuario',
        'input1' => 'Ingresa el dato principal',
        'input2' => 'Ingresa el dato adicional',
        'zona', 'zone' => 'Ingresa la zona',
        'zone_id' => 'Ingresa el ID de zona',
        'server' => 'Ingresa el servidor',
        'server_id' => 'Ingresa el ID de servidor',
        'gamepoint' => 'Ingresa el Game Point',
        'email' => 'Ingresa el correo',
        'telefono', 'phone' => 'Ingresa el telefono',
        default => 'Ingresa ' . strtolower(recargas_api_field_label($normalized)),
    };
}

function recargas_api_field_input_mode(string $fieldName): string {
    $normalized = strtolower(trim($fieldName));
    $numericFields = ['id_juego', 'player_id', 'user_id', 'zone_id', 'server_id', 'telefono', 'phone'];

    return in_array($normalized, $numericFields, true) ? 'numeric' : 'text';
}

function recargas_api_field_max_length(string $fieldName): int {
    $normalized = strtolower(trim($fieldName));

    return match ($normalized) {
        'telefono', 'phone' => 40,
        default => 180,
    };
}

function recargas_api_describe_required_fields(array $product): array {
    $descriptions = [];

    foreach (recargas_api_normalize_required_fields($product['campos_requeridos'] ?? []) as $fieldMeta) {
        $fieldName = (string) ($fieldMeta['name'] ?? '');
        $description = trim((string) ($fieldMeta['description'] ?? ''));
        $inputMode = recargas_api_field_input_mode($fieldName);
        if (($fieldMeta['type'] ?? '') === 'number') {
            $inputMode = 'numeric';
        }

        $descriptions[] = [
            'name' => $fieldName,
            'label' => $description !== '' ? $description : recargas_api_field_label($fieldName),
            'placeholder' => $description !== '' ? ('Ingresa ' . strtolower($description)) : recargas_api_field_placeholder($fieldName),
            'inputMode' => $inputMode,
            'maxLength' => recargas_api_field_max_length($fieldName),
            'options' => is_array($fieldMeta['options'] ?? null) ? $fieldMeta['options'] : [],
        ];
    }

    return $descriptions;
}

function recargas_api_post_json_with_fallback(string $url, array $payload, array $headers = [], int $timeout = 25): array {
    try {
        return recargas_api_http_post_json($url, $payload, $headers, $timeout, true);
    } catch (Throwable $e) {
        $message = (string) $e->getMessage();
        $sslIssue = stripos($message, 'SSL certificate problem') !== false
            || stripos($message, 'unable to get local issuer certificate') !== false;

        if (!$sslIssue) {
            throw $e;
        }

        return recargas_api_http_post_json($url, $payload, $headers, $timeout, false);
    }
}

function recargas_api_purchase_is_accepted(array $response): bool {
    return !empty($response['ok']);
}

function recargas_api_purchase_is_completed(array $response): bool {
    if (!recargas_api_purchase_is_accepted($response)) {
        return false;
    }

    $status = strtolower(trim((string) ($response['estado'] ?? '')));
    return $status === '' || in_array($status, ['completado', 'completed', 'success', 'enviado'], true);
}

function recargas_api_product_label(array $product): string {
    $name = trim((string) ($product['nombre'] ?? 'Producto'));
    $id = (int) ($product['id'] ?? 0);
    $price = isset($product['precio']) ? number_format((float) $product['precio'], 4, '.', '') : '0.0000';
    $manual = !empty($product['procesamiento_manual']) ? 'Manual' : 'Automatico';

    return $name . ' [ID ' . $id . '] - $' . $price . ' - ' . $manual;
}