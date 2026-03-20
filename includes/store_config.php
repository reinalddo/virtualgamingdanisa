<?php

function store_theme_definitions(): array {
    return [
        'theme_bg_main' => [
            'label' => 'Fondo principal',
            'default' => '#0A0F14',
            'description' => 'Color base del fondo general de la tienda',
        ],
        'theme_bg_alt' => [
            'label' => 'Fondo secundario',
            'default' => '#0E1722',
            'description' => 'Color usado en degradados y secciones secundarias',
        ],
        'theme_surface' => [
            'label' => 'Superficie principal',
            'default' => '#111827',
            'description' => 'Color de tarjetas, paneles y modales',
        ],
        'theme_surface_alt' => [
            'label' => 'Superficie alterna',
            'default' => '#181F2A',
            'description' => 'Color alterno para cabecera, dropdowns y paneles internos',
        ],
        'theme_primary' => [
            'label' => 'Neón principal',
            'default' => '#22D3EE',
            'description' => 'Color principal del brillo, bordes y textos destacados',
        ],
        'theme_highlight' => [
            'label' => 'Neón intenso',
            'default' => '#00FFF7',
            'description' => 'Color de realce para brillos y botones destacados',
        ],
        'theme_secondary' => [
            'label' => 'Neón secundario',
            'default' => '#2DD4BF',
            'description' => 'Color secundario para degradados, hover y efectos',
        ],
        'theme_success' => [
            'label' => 'Color de éxito',
            'default' => '#34D399',
            'description' => 'Color para acciones positivas y estados correctos',
        ],
        'theme_warning' => [
            'label' => 'Color de advertencia',
            'default' => '#F59E0B',
            'description' => 'Color para alertas y estados de revisión',
        ],
        'theme_danger' => [
            'label' => 'Color de error',
            'default' => '#F87171',
            'description' => 'Color para cancelaciones, errores y alertas críticas',
        ],
        'theme_text' => [
            'label' => 'Texto principal',
            'default' => '#F8FAFC',
            'description' => 'Color principal del texto en la tienda',
        ],
        'theme_text_muted' => [
            'label' => 'Texto secundario',
            'default' => '#CBD5E1',
            'description' => 'Color de textos secundarios, ayudas y descripciones',
        ],
        'theme_price_text' => [
            'label' => 'Precio principal',
            'default' => '#22D3EE',
            'description' => 'Color del monto principal en precios de juegos y paquetes',
        ],
        'theme_price_muted' => [
            'label' => 'Precio auxiliar',
            'default' => '#94A3B8',
            'description' => 'Color del prefijo "Desde" y la moneda en precios de juegos y paquetes',
        ],
        'theme_border' => [
            'label' => 'Borde base',
            'default' => '#164E63',
            'description' => 'Color base de bordes, separadores y contenedores',
        ],
        'theme_button_primary' => [
            'label' => 'Botón principal',
            'default' => '#22D3EE',
            'description' => 'Color principal para botones, acciones y llamadas principales',
        ],
        'theme_button_secondary' => [
            'label' => 'Botón secundario',
            'default' => '#2DD4BF',
            'description' => 'Color secundario para degradados y hover de botones',
        ],
        'theme_button_surface' => [
            'label' => 'Botón base oscuro',
            'default' => '#0E1722',
            'description' => 'Color base para botones oscuros, menú y tarjetas seleccionables',
        ],
        'theme_float_whatsapp_bg' => [
            'label' => 'Flotante WhatsApp',
            'default' => '#22C55E',
            'description' => 'Color principal del botón flotante de WhatsApp',
        ],
        'theme_float_whatsapp_text' => [
            'label' => 'Texto WhatsApp',
            'default' => '#F8FAFC',
            'description' => 'Color del texto e icono del botón flotante de WhatsApp',
        ],
        'theme_float_channel_bg' => [
            'label' => 'Flotante canal',
            'default' => '#1F2937',
            'description' => 'Color principal del botón flotante del canal de difusión',
        ],
        'theme_float_channel_text' => [
            'label' => 'Texto canal',
            'default' => '#F8FAFC',
            'description' => 'Color del texto e icono del botón flotante del canal de difusión',
        ],
        'theme_startup_popup_surface' => [
            'label' => 'Ventana inicial fondo',
            'default' => '#140D0E',
            'description' => 'Color base del panel principal de la ventana inicial',
        ],
        'theme_startup_popup_border' => [
            'label' => 'Ventana inicial borde',
            'default' => '#3D1C1A',
            'description' => 'Color del borde y contornos de la ventana inicial',
        ],
        'theme_startup_popup_accent' => [
            'label' => 'Ventana inicial acento',
            'default' => '#25D366',
            'description' => 'Color del icono principal, resaltes y brillo de la ventana inicial',
        ],
        'theme_startup_popup_chip' => [
            'label' => 'Ventana inicial insignia',
            'default' => '#0E2B1B',
            'description' => 'Color de fondo para la insignia superior de la ventana inicial',
        ],
        'theme_startup_popup_button_text' => [
            'label' => 'Ventana inicial texto botón',
            'default' => '#F8FAFC',
            'description' => 'Color del texto e icono del botón principal de la ventana inicial',
        ],
        'theme_startup_video_popup_surface' => [
            'label' => 'Ventana video fondo',
            'default' => '#1A2233',
            'description' => 'Color base del panel principal de la ventana inicial con video',
        ],
        'theme_startup_video_popup_border' => [
            'label' => 'Ventana video borde',
            'default' => '#314462',
            'description' => 'Color del borde y contornos de la ventana inicial con video',
        ],
        'theme_startup_video_popup_accent' => [
            'label' => 'Ventana video acento',
            'default' => '#F87171',
            'description' => 'Color de detalles destacados y botón de cierre de la ventana inicial con video',
        ],
        'theme_startup_video_popup_button_bg' => [
            'label' => 'Ventana video botón',
            'default' => '#25D366',
            'description' => 'Color principal del botón del canal en la ventana inicial con video',
        ],
        'theme_startup_video_popup_button_text' => [
            'label' => 'Ventana video texto botón',
            'default' => '#F8FAFC',
            'description' => 'Color del texto e icono del botón principal de la ventana inicial con video',
        ],
    ];
}

function store_theme_custom_key(string $baseKey): string {
    if (str_starts_with($baseKey, 'theme_')) {
        return 'theme_custom_' . substr($baseKey, 6);
    }

    return 'theme_custom_' . $baseKey;
}

function store_theme_custom_description(string $baseDescription): string {
    return 'Copia editable: ' . $baseDescription;
}

function store_config_descriptions(): array {
    $descriptions = [
        'correo_corporativo' => 'Correo usado para notificaciones',
        'smtp_host' => 'Host SMTP para envío de correos',
        'smtp_user' => 'Usuario SMTP',
        'smtp_pass' => 'Contraseña SMTP',
        'smtp_port' => 'Puerto SMTP',
        'smtp_secure' => 'Tipo de seguridad SMTP',
        'nombre_prefijo' => 'Texto superior del encabezado de la tienda',
        'nombre_tienda' => 'Nombre principal visible de la tienda',
        'nombre_tienda_subtitulo' => 'Texto complementario usado en el título del inicio y en la instalación de la app',
        'logo_tienda' => 'Ruta del logo visible en el encabezado',
        'facebook' => 'URL de Facebook de la tienda',
        'instagram' => 'URL de Instagram de la tienda',
        'whatsapp' => 'Número o enlace de WhatsApp de la tienda',
        'mensaje_whatsapp' => 'Mensaje predefinido para el botón flotante de WhatsApp',
        'whatsapp_channel' => 'URL del canal de WhatsApp de la tienda',
        'google_client_id' => 'Client ID de Google para login y registro social',
        'google_client_secret' => 'Client Secret de Google para login y registro social',
        'inicio_popup_tab_habilitado' => 'Activa o desactiva globalmente el tab y la función de la ventana inicial',
        'inicio_popup_activo' => 'Activa o desactiva la aparición de la ventana inicial en el index',
        'inicio_popup_video_activo' => 'Activa o desactiva la aparición de la ventana inicial con video en el index',
        'inicio_popup_frecuencia' => 'Frecuencia con la que debe aparecer la ventana inicial en el index',
        'inicio_popup_nombre_canal' => 'Nombre visible del canal en la ventana inicial',
        'inicio_popup_video_url' => 'Enlace de YouTube usado en la ventana inicial con video',
        'ff_bank_posicion' => 'Posicion para la conexion al banco de Free Fire',
        'ff_bank_token' => 'Token para la conexion al banco de Free Fire',
        'ff_bank_clave' => 'Clave para la conexion al banco de Free Fire',
        'ff_api_usuario' => 'Usuario para la API de Free Fire',
        'ff_api_clave' => 'Clave para la API de Free Fire',
        'ff_api_tipo' => 'Tipo para la API de Free Fire',
    ];

    foreach (store_theme_definitions() as $key => $definition) {
        $description = (string) ($definition['description'] ?? 'Color del tema visual');
        $descriptions[$key] = $description;
        $descriptions[store_theme_custom_key($key)] = store_theme_custom_description($description);
    }

    return $descriptions;
}

function store_config_defaults(): array {
    $defaults = [
        'correo_corporativo' => '',
        'smtp_host' => '',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_port' => '587',
        'smtp_secure' => 'tls',
        'nombre_prefijo' => 'TIENDA',
        'nombre_tienda' => 'TVirtualGaming',
        'nombre_tienda_subtitulo' => 'Tienda de monedas digitales',
        'logo_tienda' => '',
        'facebook' => '',
        'instagram' => '',
        'whatsapp' => '',
        'mensaje_whatsapp' => '',
        'whatsapp_channel' => '',
        'google_client_id' => '',
        'google_client_secret' => '',
        'inicio_popup_tab_habilitado' => '1',
        'inicio_popup_activo' => '1',
        'inicio_popup_video_activo' => '0',
        'inicio_popup_frecuencia' => 'per_session',
        'inicio_popup_nombre_canal' => 'DanisA Gamer Store',
        'inicio_popup_video_url' => '',
        'ff_bank_posicion' => '0',
        'ff_bank_token' => '',
        'ff_bank_clave' => '',
        'ff_api_usuario' => '',
        'ff_api_clave' => '',
        'ff_api_tipo' => 'recargaFreefire',
    ];

    foreach (store_theme_definitions() as $key => $definition) {
        $defaultValue = (string) ($definition['default'] ?? '#000000');
        $defaults[$key] = $defaultValue;
        $defaults[store_theme_custom_key($key)] = $defaultValue;
    }

    return $defaults;
}

function store_config_normalize_hex_color(string $value, string $fallback = '#000000'): string {
    $normalized = strtoupper(trim($value));
    if ($normalized === '') {
        return strtoupper($fallback);
    }

    if ($normalized[0] !== '#') {
        $normalized = '#' . $normalized;
    }

    if (!preg_match('/^#([A-F0-9]{3}|[A-F0-9]{6})$/', $normalized)) {
        return strtoupper($fallback);
    }

    if (strlen($normalized) === 4) {
        return '#'
            . $normalized[1] . $normalized[1]
            . $normalized[2] . $normalized[2]
            . $normalized[3] . $normalized[3];
    }

    return $normalized;
}

function store_theme_base_values(bool $refresh = false): array {
    $config = store_config_all($refresh);
    $values = [];

    foreach (store_theme_definitions() as $key => $definition) {
        $values[$key] = store_config_normalize_hex_color(
            (string) ($config[$key] ?? ''),
            (string) ($definition['default'] ?? '#000000')
        );
    }

    return $values;
}

function store_theme_values(bool $refresh = false): array {
    $config = store_config_all($refresh);
    $baseValues = store_theme_base_values($refresh);
    $values = [];

    foreach (store_theme_definitions() as $key => $definition) {
        $customKey = store_theme_custom_key($key);
        $values[$key] = store_config_normalize_hex_color(
            (string) ($config[$customKey] ?? ''),
            $baseValues[$key] ?? (string) ($definition['default'] ?? '#000000')
        );
    }

    return $values;
}

function store_theme_validate_payload(array $input): array {
    $data = [];
    $errors = [];

    foreach (store_theme_definitions() as $key => $definition) {
        $rawValue = trim((string) ($input[$key] ?? ''));
        if ($rawValue === '') {
            $errors[] = 'Debes indicar un color para ' . strtolower((string) ($definition['label'] ?? $key)) . '.';
            continue;
        }

        $normalized = store_config_normalize_hex_color($rawValue, '');
        if ($normalized === '') {
            $errors[] = 'El color de ' . strtolower((string) ($definition['label'] ?? $key)) . ' no es válido. Usa formato hexadecimal, por ejemplo: #22D3EE.';
            continue;
        }

        $data[$key] = $normalized;
    }

    return [
        'is_valid' => empty($errors),
        'errors' => $errors,
        'data' => $data,
    ];
}

function store_theme_save_values(array $values): bool {
    $descriptions = store_config_descriptions();

    foreach (store_theme_definitions() as $baseKey => $definition) {
        if (!array_key_exists($baseKey, $values)) {
            continue;
        }

        $customKey = store_theme_custom_key($baseKey);
        $description = $descriptions[$customKey] ?? store_theme_custom_description((string) ($definition['description'] ?? 'Color del tema visual'));
        if (!store_config_upsert($customKey, (string) $values[$baseKey], $description)) {
            return false;
        }
    }

    return true;
}

function store_theme_restore_defaults(): bool {
    return store_theme_save_values(store_theme_base_values(true));
}

function store_theme_hex_to_rgb(string $hex): array {
    $normalized = store_config_normalize_hex_color($hex, '#000000');
    return [
        hexdec(substr($normalized, 1, 2)),
        hexdec(substr($normalized, 3, 2)),
        hexdec(substr($normalized, 5, 2)),
    ];
}

function store_theme_rgb_string(string $hex): string {
    $rgb = store_theme_hex_to_rgb($hex);
    return implode(', ', $rgb);
}

function store_theme_rgba(string $hex, float $alpha): string {
    $rgb = store_theme_hex_to_rgb($hex);
    $safeAlpha = max(0, min(1, $alpha));
    return 'rgba(' . implode(', ', $rgb) . ', ' . rtrim(rtrim(number_format($safeAlpha, 2, '.', ''), '0'), '.') . ')';
}

function store_theme_mix(string $baseHex, string $mixHex, float $ratio): string {
    $base = store_theme_hex_to_rgb($baseHex);
    $mix = store_theme_hex_to_rgb($mixHex);
    $weight = max(0, min(1, $ratio));
    $channels = [];

    foreach ([0, 1, 2] as $index) {
        $channels[$index] = (int) round(($base[$index] * (1 - $weight)) + ($mix[$index] * $weight));
    }

    return sprintf('#%02X%02X%02X', $channels[0], $channels[1], $channels[2]);
}

function store_theme_contrast_text(string $backgroundHex): string {
    [$red, $green, $blue] = store_theme_hex_to_rgb($backgroundHex);
    $luminance = ((0.299 * $red) + (0.587 * $green) + (0.114 * $blue)) / 255;
    return $luminance > 0.6 ? '#081018' : '#F8FAFC';
}

function store_theme_css_variables(): string {
    $theme = store_theme_values();
    $bodyGlow = store_theme_mix($theme['theme_bg_alt'], '#123247', 0.18);
    $bodyDeep = store_theme_mix($theme['theme_bg_main'], '#000000', 0.28);
    $panelGlow = store_theme_mix($theme['theme_primary'], $theme['theme_secondary'], 0.25);
    $panelBg = store_theme_rgba($theme['theme_bg_alt'], 0.97);
    $panelGradient = 'linear-gradient(135deg, ' . store_theme_rgba($theme['theme_bg_alt'], 0.98) . ' 80%, ' . store_theme_rgba($theme['theme_primary'], 0.08) . ' 100%)';
    $overlayStrong = store_theme_rgba('#0C1522', 0.7);
    $overlaySoft = store_theme_rgba('#0C1522', 0.86);
    $primarySoft = store_theme_rgba($theme['theme_primary'], 0.15);
    $primaryGlow = store_theme_rgba($theme['theme_primary'], 0.22);
    $bgElevated = store_theme_rgba('#081018', 0.82);
    $buttonPrimaryMix = store_theme_mix($theme['theme_button_primary'], $theme['theme_button_secondary'], 0.5);
    $buttonSecondaryMix = store_theme_mix($theme['theme_button_secondary'], $theme['theme_button_primary'], 0.5);
    $buttonSurfaceGlow = store_theme_mix($theme['theme_button_surface'], $theme['theme_button_primary'], 0.18);
    $buttonSurfaceBorder = store_theme_mix($theme['theme_button_primary'], $theme['theme_button_secondary'], 0.35);

    $variables = [
        '--theme-bg-main' => $theme['theme_bg_main'],
        '--theme-bg-alt' => $theme['theme_bg_alt'],
        '--theme-bg-deep' => $bodyDeep,
        '--theme-surface' => $theme['theme_surface'],
        '--theme-surface-alt' => $theme['theme_surface_alt'],
        '--theme-primary' => $theme['theme_primary'],
        '--theme-highlight' => $theme['theme_highlight'],
        '--theme-secondary' => $theme['theme_secondary'],
        '--theme-success' => $theme['theme_success'],
        '--theme-warning' => $theme['theme_warning'],
        '--theme-danger' => $theme['theme_danger'],
        '--theme-text' => $theme['theme_text'],
        '--theme-text-muted' => $theme['theme_text_muted'],
        '--theme-price-text' => $theme['theme_price_text'],
        '--theme-price-muted' => $theme['theme_price_muted'],
        '--theme-border' => $theme['theme_border'],
        '--theme-button-primary' => $theme['theme_button_primary'],
        '--theme-button-secondary' => $theme['theme_button_secondary'],
        '--theme-button-surface' => $theme['theme_button_surface'],
        '--theme-float-whatsapp-bg' => $theme['theme_float_whatsapp_bg'],
        '--theme-float-whatsapp-text' => $theme['theme_float_whatsapp_text'],
        '--theme-float-channel-bg' => $theme['theme_float_channel_bg'],
        '--theme-float-channel-text' => $theme['theme_float_channel_text'],
        '--theme-startup-popup-surface' => $theme['theme_startup_popup_surface'],
        '--theme-startup-popup-border' => $theme['theme_startup_popup_border'],
        '--theme-startup-popup-accent' => $theme['theme_startup_popup_accent'],
        '--theme-startup-popup-chip' => $theme['theme_startup_popup_chip'],
        '--theme-startup-popup-button-text' => $theme['theme_startup_popup_button_text'],
        '--theme-startup-video-popup-surface' => $theme['theme_startup_video_popup_surface'],
        '--theme-startup-video-popup-border' => $theme['theme_startup_video_popup_border'],
        '--theme-startup-video-popup-accent' => $theme['theme_startup_video_popup_accent'],
        '--theme-startup-video-popup-button-bg' => $theme['theme_startup_video_popup_button_bg'],
        '--theme-startup-video-popup-button-text' => $theme['theme_startup_video_popup_button_text'],
        '--theme-body-glow' => $bodyGlow,
        '--theme-panel-glow' => $panelGlow,
        '--theme-panel-bg' => $panelBg,
        '--theme-panel-gradient' => $panelGradient,
        '--theme-overlay-strong' => $overlayStrong,
        '--theme-overlay-soft' => $overlaySoft,
        '--theme-primary-soft' => $primarySoft,
        '--theme-primary-glow' => $primaryGlow,
        '--theme-bg-elevated' => $bgElevated,
        '--theme-button-surface-glow' => $buttonSurfaceGlow,
        '--theme-button-surface-border' => $buttonSurfaceBorder,
        '--theme-shadow-primary' => '0 0 32px ' . store_theme_rgba($theme['theme_primary'], 0.95),
        '--theme-shadow-secondary' => '0 0 8px ' . store_theme_rgba($theme['theme_secondary'], 0.9),
        '--theme-primary-rgb' => store_theme_rgb_string($theme['theme_primary']),
        '--theme-highlight-rgb' => store_theme_rgb_string($theme['theme_highlight']),
        '--theme-secondary-rgb' => store_theme_rgb_string($theme['theme_secondary']),
        '--theme-button-primary-rgb' => store_theme_rgb_string($theme['theme_button_primary']),
        '--theme-button-secondary-rgb' => store_theme_rgb_string($theme['theme_button_secondary']),
        '--theme-button-surface-rgb' => store_theme_rgb_string($theme['theme_button_surface']),
        '--theme-float-whatsapp-bg-rgb' => store_theme_rgb_string($theme['theme_float_whatsapp_bg']),
        '--theme-float-whatsapp-text-rgb' => store_theme_rgb_string($theme['theme_float_whatsapp_text']),
        '--theme-float-channel-bg-rgb' => store_theme_rgb_string($theme['theme_float_channel_bg']),
        '--theme-float-channel-text-rgb' => store_theme_rgb_string($theme['theme_float_channel_text']),
        '--theme-startup-popup-surface-rgb' => store_theme_rgb_string($theme['theme_startup_popup_surface']),
        '--theme-startup-popup-border-rgb' => store_theme_rgb_string($theme['theme_startup_popup_border']),
        '--theme-startup-popup-accent-rgb' => store_theme_rgb_string($theme['theme_startup_popup_accent']),
        '--theme-startup-popup-chip-rgb' => store_theme_rgb_string($theme['theme_startup_popup_chip']),
        '--theme-startup-popup-button-text-rgb' => store_theme_rgb_string($theme['theme_startup_popup_button_text']),
        '--theme-startup-video-popup-surface-rgb' => store_theme_rgb_string($theme['theme_startup_video_popup_surface']),
        '--theme-startup-video-popup-border-rgb' => store_theme_rgb_string($theme['theme_startup_video_popup_border']),
        '--theme-startup-video-popup-accent-rgb' => store_theme_rgb_string($theme['theme_startup_video_popup_accent']),
        '--theme-startup-video-popup-button-bg-rgb' => store_theme_rgb_string($theme['theme_startup_video_popup_button_bg']),
        '--theme-startup-video-popup-button-text-rgb' => store_theme_rgb_string($theme['theme_startup_video_popup_button_text']),
        '--theme-success-rgb' => store_theme_rgb_string($theme['theme_success']),
        '--theme-warning-rgb' => store_theme_rgb_string($theme['theme_warning']),
        '--theme-danger-rgb' => store_theme_rgb_string($theme['theme_danger']),
        '--theme-text-rgb' => store_theme_rgb_string($theme['theme_text']),
        '--theme-text-muted-rgb' => store_theme_rgb_string($theme['theme_text_muted']),
        '--theme-price-text-rgb' => store_theme_rgb_string($theme['theme_price_text']),
        '--theme-price-muted-rgb' => store_theme_rgb_string($theme['theme_price_muted']),
        '--theme-border-rgb' => store_theme_rgb_string($theme['theme_border']),
        '--theme-bg-main-rgb' => store_theme_rgb_string($theme['theme_bg_main']),
        '--theme-bg-alt-rgb' => store_theme_rgb_string($theme['theme_bg_alt']),
        '--theme-surface-rgb' => store_theme_rgb_string($theme['theme_surface']),
        '--theme-surface-alt-rgb' => store_theme_rgb_string($theme['theme_surface_alt']),
        '--theme-button-text' => store_theme_contrast_text($buttonSecondaryMix),
        '--theme-button-text-strong' => store_theme_contrast_text($buttonPrimaryMix),
        '--theme-button-surface-text' => store_theme_contrast_text($theme['theme_button_surface']),
        '--theme-success-text' => store_theme_contrast_text($theme['theme_success']),
        '--theme-danger-text' => store_theme_contrast_text($theme['theme_danger']),
        '--bs-body-bg' => $theme['theme_bg_main'],
        '--bs-body-color' => $theme['theme_text'],
        '--bs-dark' => $theme['theme_surface'],
        '--bs-dark-rgb' => store_theme_rgb_string($theme['theme_surface']),
        '--bs-info' => $theme['theme_primary'],
        '--bs-info-rgb' => store_theme_rgb_string($theme['theme_primary']),
        '--bs-success' => $theme['theme_success'],
        '--bs-success-rgb' => store_theme_rgb_string($theme['theme_success']),
        '--bs-warning' => $theme['theme_warning'],
        '--bs-warning-rgb' => store_theme_rgb_string($theme['theme_warning']),
        '--bs-danger' => $theme['theme_danger'],
        '--bs-danger-rgb' => store_theme_rgb_string($theme['theme_danger']),
        '--bs-secondary-color' => $theme['theme_text_muted'],
        '--bs-secondary-color-rgb' => store_theme_rgb_string($theme['theme_text_muted']),
        '--bs-border-color' => store_theme_rgba($theme['theme_border'], 0.68),
        '--bs-border-color-translucent' => store_theme_rgba($theme['theme_border'], 0.28),
        '--bs-heading-color' => $theme['theme_text'],
        '--bs-emphasis-color' => $theme['theme_text'],
        '--bs-link-color' => $theme['theme_primary'],
        '--bs-link-hover-color' => $theme['theme_highlight'],
    ];

    $lines = [];
    foreach ($variables as $name => $value) {
        $lines[] = '      ' . $name . ': ' . $value . ';';
    }

    return implode("\n", $lines);
}

function store_config_db(): mysqli {
    global $mysqli;

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        require_once __DIR__ . '/db_connect.php';
    }

    return $mysqli;
}

function store_config_all(bool $refresh = false): array {
    static $cache = null;

    if ($refresh || $cache === null) {
        store_config_ensure_defaults();
        $cache = store_config_defaults();
        $mysqli = store_config_db();
        $res = $mysqli->query('SELECT clave, valor FROM configuracion_general');
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $cache[$row['clave']] = $row['valor'];
            }
        }
    }

    return $cache;
}

function store_config_ensure_defaults(): void {
    static $ensuring = false;
    static $done = false;

    if ($done || $ensuring) {
        return;
    }

    $ensuring = true;
    $mysqli = store_config_db();
    $descriptions = store_config_descriptions();

    foreach (store_config_defaults() as $key => $value) {
        $description = $descriptions[$key] ?? null;
        $stmt = $mysqli->prepare('INSERT IGNORE INTO configuracion_general (clave, valor, descripcion) VALUES (?, ?, ?)');
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('sss', $key, $value, $description);
        $stmt->execute();
        $stmt->close();
    }

    $ensuring = false;
    $done = true;
}

function store_config_get(string $key, ?string $default = null): string {
    $config = store_config_all();
    if (array_key_exists($key, $config)) {
        return (string) $config[$key];
    }

    return $default ?? '';
}

function store_config_normalize_social_url(string $value): string {
    return trim($value);
}

function store_config_extract_youtube_video_id(string $value): string {
    $candidate = trim($value);
    if ($candidate === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1) {
        return $candidate;
    }

    if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
    $host = preg_replace('/^(www|m)\./', '', $host);
    $path = trim((string) parse_url($candidate, PHP_URL_PATH), '/');

    if ($host === 'youtu.be') {
        $segments = $path === '' ? [] : explode('/', $path);
        $videoId = $segments[0] ?? '';
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : '';
    }

    if (!in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        return '';
    }

    if ($path === 'watch') {
        parse_str((string) parse_url($candidate, PHP_URL_QUERY), $queryParams);
        $videoId = trim((string) ($queryParams['v'] ?? ''));
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : '';
    }

    $segments = $path === '' ? [] : explode('/', $path);
    if (count($segments) >= 2 && in_array($segments[0], ['shorts', 'embed', 'live'], true)) {
        $videoId = trim((string) $segments[1]);
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : '';
    }

    return '';
}

function store_config_normalize_youtube_url(string $value): string {
    $videoId = store_config_extract_youtube_video_id($value);
    if ($videoId === '') {
        return '';
    }

    return 'https://www.youtube.com/watch?v=' . $videoId;
}

function store_config_is_valid_youtube_url(string $value): bool {
    return store_config_extract_youtube_video_id($value) !== '';
}

function store_config_youtube_embed_url(string $value): string {
    $videoId = store_config_extract_youtube_video_id($value);
    if ($videoId === '') {
        return '';
    }

    return 'https://www.youtube-nocookie.com/embed/' . $videoId . '?rel=0&modestbranding=1&playsinline=1';
}

function store_config_is_valid_social_url(string $value): bool {
    $normalized = store_config_normalize_social_url($value);
    if ($normalized === '') {
        return false;
    }

    if (filter_var($normalized, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function store_config_normalize_whatsapp(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $trimmed);
    if ($digits === null || $digits === '') {
        return '';
    }

    return '+' . $digits;
}

function store_config_is_valid_whatsapp(string $value): bool {
    $normalized = store_config_normalize_whatsapp($value);
    if ($normalized === '') {
        return false;
    }

    return preg_match('/^\+[1-9]\d{9,14}$/', $normalized) === 1;
}

function store_config_whatsapp_link(string $value): string {
    if (!store_config_is_valid_whatsapp($value)) {
        return '';
    }

    $normalized = store_config_normalize_whatsapp($value);
    return 'https://wa.me/' . ltrim($normalized, '+');
}

function store_config_normalize_whatsapp_message(string $value): string {
    $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return $normalized;
}

function store_config_whatsapp_link_with_message(string $value, string $message = ''): string {
    $baseLink = store_config_whatsapp_link($value);
    if ($baseLink === '') {
        return '';
    }

    $normalizedMessage = store_config_normalize_whatsapp_message($message);
    if ($normalizedMessage === '') {
        return $baseLink;
    }

    return $baseLink . '?text=' . rawurlencode($normalizedMessage);
}

function store_config_upsert(string $key, string $value, ?string $description = null): bool {
    $mysqli = store_config_db();
    $descriptions = store_config_descriptions();
    $resolvedDescription = $description ?? ($descriptions[$key] ?? null);

    $stmt = $mysqli->prepare(
        'INSERT INTO configuracion_general (clave, valor, descripcion) VALUES (?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = COALESCE(VALUES(descripcion), descripcion)'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sss', $key, $value, $resolvedDescription);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        store_config_all(true);
    }

    return $ok;
}

function store_config_delete(string $key): bool {
    $mysqli = store_config_db();
    $stmt = $mysqli->prepare('DELETE FROM configuracion_general WHERE clave = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $key);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        store_config_all(true);
    }

    return $ok;
}

function store_config_is_managed_logo_path(string $relativePath): bool {
    return str_starts_with($relativePath, '/assets/img/store/');
}

function store_config_delete_logo_file(string $relativePath): void {
    if ($relativePath === '' || !store_config_is_managed_logo_path($relativePath)) {
        return;
    }

    $absolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function store_config_store_logo_upload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No se pudo cargar el logo.'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'El archivo del logo no es válido.'];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'El logo no puede superar 2 MB.'];
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'El logo debe ser una imagen válida.'];
    }

    $mime = $imageInfo['mime'] ?? '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        return ['success' => false, 'message' => 'Formato de logo no permitido. Usa JPG, PNG, WEBP o GIF.'];
    }

    $targetDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'store';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['success' => false, 'message' => 'No se pudo crear la carpeta del logo.'];
    }

    $fileName = 'store-logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return ['success' => false, 'message' => 'No se pudo guardar el logo en el servidor.'];
    }

    return ['success' => true, 'path' => '/assets/img/store/' . $fileName];
}