<?php

if (!function_exists('resolve_tenant_slug')) {
    function resolve_tenant_slug(): string {
        $tenantSlug = '';

        if (isset($_GET['tenant'])) {
            $tenantSlug = preg_replace('/[^a-zA-Z0-9-_]/', '', (string) $_GET['tenant']);
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:[0-9]+$/', '', $host);
        $hostMap = [
            'tvirtualgaming.tvirtualshop.com' => 'tvirtualgaming',
            'localhost' => 'localhost',
            '127.0.0.1' => 'localhost',
        ];

        if ($tenantSlug === '' && isset($hostMap[$host])) {
            $tenantSlug = $hostMap[$host];
        }

        return $tenantSlug !== '' ? $tenantSlug : 'default';
    }
}
?>