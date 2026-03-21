<?php

if (!function_exists('app_timezone_name')) {
    function app_timezone_name(): string {
        return 'America/Caracas';
    }
}

if (!function_exists('app_configure_timezone')) {
    function app_configure_timezone(): void {
        static $configured = false;

        if ($configured) {
            return;
        }

        date_default_timezone_set(app_timezone_name());
        $configured = true;
    }
}

app_configure_timezone();