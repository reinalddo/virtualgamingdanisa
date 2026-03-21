<?php

require_once __DIR__ . '/app_timezone.php';

if (!function_exists('app_session_cookie_lifetime')) {
    function app_session_cookie_lifetime(): int {
        return 60 * 60 * 24 * 30;
    }
}

if (!function_exists('app_session_is_secure')) {
    function app_session_is_secure(): bool {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return $https === 'on' || $https === '1' || $forwardedProto === 'https';
    }
}

if (!function_exists('app_session_start')) {
    function app_session_start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = app_session_cookie_lifetime();
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_lifetime', (string) $lifetime);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => app_session_is_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

if (!function_exists('app_session_forget_cookie')) {
    function app_session_forget_cookie(): void {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
}