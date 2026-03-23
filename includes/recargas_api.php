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

function recargas_api_product_label(array $product): string {
    $name = trim((string) ($product['nombre'] ?? 'Producto'));
    $id = (int) ($product['id'] ?? 0);
    $price = isset($product['precio']) ? number_format((float) $product['precio'], 4, '.', '') : '0.0000';
    $manual = !empty($product['procesamiento_manual']) ? 'Manual' : 'Automatico';

    return $name . ' [ID ' . $id . '] - $' . $price . ' - ' . $manual;
}