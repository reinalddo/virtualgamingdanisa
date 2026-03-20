<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if ($scriptDir === '/' || $scriptDir === '.') {
  $scriptDir = '';
}
require_once __DIR__ . '/store_config.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/google_oauth.php';

if (!isset($brandPrefix)) {
  $brandPrefix = store_config_get('nombre_prefijo', 'TIENDA');
}
if (!isset($pageTitle)) {
  $pageTitle = store_config_get('nombre_tienda', 'TVirtualGaming');
}
if (!isset($brandName)) {
  $brandName = store_config_get('nombre_tienda', 'TVirtualGaming');
}

$authUser = $_SESSION['auth_user'] ?? null;
$authUserName = trim((string) (($authUser['full_name'] ?? $authUser['nombre'] ?? $authUser['email'] ?? 'Usuario')));
$authUserEmail = trim((string) ($authUser['email'] ?? ''));
$authUserRole = trim((string) ($authUser['rol'] ?? ''));
$authUserInitials = 'US';
if ($authUserName !== '') {
  $nameParts = preg_split('/\s+/', $authUserName);
  $initials = '';
  foreach ($nameParts as $part) {
    if ($part === '') {
      continue;
    }
    $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
    if (strlen($initials) >= 2) {
      break;
    }
  }
  if ($initials !== '') {
    $authUserInitials = strtoupper($initials);
  }
}

$brandLogo = store_config_get('logo_tienda', '');
$brandFavicon = '';
if ($brandLogo !== '') {
  $brandFavicon = $brandLogo;
  if (store_config_is_managed_logo_path($brandLogo)) {
    $brandLogoAbsolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $brandLogo);
    if (is_file($brandLogoAbsolutePath)) {
      $brandFavicon .= '?v=' . rawurlencode((string) filemtime($brandLogoAbsolutePath));
    }
  }
}

if (!function_exists('asset_version')) {
  function asset_version($absolutePath) {
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';
  }
}

$tenantSlugAttr = resolve_tenant_slug();
$mainStylesPath = __DIR__ . '/../assets/css/estilos.css';
$mainStylesVersion = asset_version($mainStylesPath);
$themeVariablesCss = store_theme_css_variables();
$googleAuthEnabled = google_oauth_is_configured();
$googleAuthLoginUrl = $googleAuthEnabled ? google_oauth_login_url() : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8"); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet" />
  <?php if ($brandFavicon !== ''): ?>
  <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($brandFavicon, ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="shortcut icon" href="<?php echo htmlspecialchars($brandFavicon, ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($brandFavicon, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!--<link rel="stylesheet" href="/assets/css/estilos.css" />-->
  <link rel="stylesheet" href="/assets/css/estilos.css?v=<?php echo htmlspecialchars($mainStylesVersion, ENT_QUOTES, 'UTF-8'); ?>" />
  <style>
    :root {
<?php echo $themeVariablesCss; ?>
    }
    body {
      font-family: "Space Grotesk", "Oxanium", sans-serif;
      background: radial-gradient(circle at top, var(--theme-body-glow) 0%, var(--theme-bg-main) 48%, var(--theme-bg-deep) 100%);
      color: var(--theme-text);
    }
    .glow-ring {
      box-shadow: 0 0 0.75rem rgba(var(--theme-primary-rgb), 0.4), 0 0 2.2rem rgba(var(--theme-secondary-rgb), 0.2);
    }
    .theme-panel {
      background: linear-gradient(135deg, rgba(var(--theme-bg-alt-rgb), 0.96), rgba(var(--theme-surface-rgb), 0.94));
      border: 1px solid rgba(var(--theme-border-rgb), 0.64);
      box-shadow: 0 0 22px rgba(var(--theme-primary-rgb), 0.16);
    }
    #menu-panel {
      scrollbar-width: thin;
      scrollbar-color: rgba(var(--theme-primary-rgb), 0.45) transparent;
    }
    #menu-panel::-webkit-scrollbar {
      width: 10px;
    }
    #menu-panel::-webkit-scrollbar-track {
      background: linear-gradient(180deg, rgba(var(--theme-bg-alt-rgb), 0.9), rgba(var(--theme-bg-main-rgb), 0.62));
      border-radius: 999px;
      border: 1px solid rgba(var(--theme-border-rgb), 0.54);
    }
    #menu-panel::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, rgba(var(--theme-primary-rgb), 0.88), rgba(var(--theme-secondary-rgb), 0.88));
      border-radius: 999px;
      box-shadow: 0 0 12px rgba(var(--theme-primary-rgb), 0.35);
      border: 1px solid rgba(var(--theme-bg-alt-rgb), 0.9);
    }
    #menu-panel::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(180deg, rgba(var(--theme-highlight-rgb), 1), rgba(var(--theme-secondary-rgb), 1));
    }
    @keyframes floaty {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-6px); }
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var menuToggle = document.getElementById('menu-toggle');
      var menuPanel = document.getElementById('menu-panel');
      var menuOverlay = document.getElementById('menu-overlay');
      var menuClose = document.getElementById('menu-close');
      if (menuToggle && menuPanel && menuOverlay) {
        menuToggle.addEventListener('click', function() {
          menuPanel.classList.remove('d-none');
          menuOverlay.classList.remove('d-none');
        });
        menuOverlay.addEventListener('click', function() {
          menuPanel.classList.add('d-none');
          menuOverlay.classList.add('d-none');
        });
      }
      if (menuClose) {
        menuClose.addEventListener('click', function() {
          menuPanel.classList.add('d-none');
          menuOverlay.classList.add('d-none');
        });
      }
    });
  </script>
</head>
<body class="bg-dark text-light min-vh-100">
  <div class="position-relative min-vh-100 overflow-hidden">
    <div class="position-absolute top-0 start-50 translate-middle-x rounded-circle" style="height:18rem;width:18rem;background:rgba(var(--theme-primary-rgb),0.15);filter:blur(48px);pointer-events:none;"></div>
    <div class="position-absolute bottom-0 end-0 rounded-circle" style="height:16rem;width:16rem;background:rgba(var(--theme-success-rgb),0.10);filter:blur(48px);pointer-events:none;"></div>

    <div class="container-lg store-shell position-relative pb-5 pt-4" data-tenant="<?php echo htmlspecialchars($tenantSlugAttr, ENT_QUOTES, "UTF-8"); ?>">
      <header class="site-header d-flex align-items-center justify-content-between gap-3">
        <button id="menu-toggle" class="btn btn-outline-info rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;" aria-label="Abrir menú">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M2.5 12.5a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10a.5.5 0 0 1-.5-.5z"/>
          </svg>
        </button>
        <div class="site-brand d-flex align-items-center justify-content-center gap-3 flex-grow-1">
          <?php if ($brandLogo !== ''): ?>
            <div class="rounded-4 overflow-hidden border border-info glow-ring flex-shrink-0" style="width:52px;height:52px;background:rgba(var(--theme-bg-alt-rgb),0.82);">
              <img src="<?php echo htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo de la tienda" class="w-100 h-100 object-fit-cover" />
            </div>
          <?php endif; ?>
          <div class="text-center text-sm-start">
            <p class="small text-uppercase text-info mb-0" style="letter-spacing:0.3em;"><?php echo htmlspecialchars($brandPrefix, ENT_QUOTES, 'UTF-8'); ?></p>
            <h1 class="fw-bold mb-0" style="font-family:'Oxanium', 'Space Grotesk', sans-serif;font-size:1.25rem;color:var(--theme-text);"><?php echo htmlspecialchars($brandName, ENT_QUOTES, "UTF-8"); ?></h1>
          </div>
        </div>
        <div id="auth-container" class="site-auth-container position-relative">
          <?php if (!$authUser): ?>
            <button id="auth-trigger" type="button" class="site-auth-trigger d-flex align-items-center gap-2 neon-btn border border-info bg-dark px-2 py-1 text-uppercase fw-bold text-info shadow-sm" style="font-size:11px;box-shadow:0 0 8px rgba(var(--theme-primary-rgb),0.8), 0 0 2px rgba(var(--theme-secondary-rgb),0.8);transition:box-shadow 0.2s;min-width:120px;">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="width:18px;height:18px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.5-1.632z" />
              </svg>
              <span class="site-auth-label" style="text-shadow:0 0 4px rgba(var(--theme-primary-rgb),0.92), 0 0 1px rgba(var(--theme-secondary-rgb),0.92);">Iniciar Sesión / Registrarse</span>
            </button>
            <div id="auth-menu" class="position-absolute end-0 mt-2 z-3 d-none" style="min-width:160px;max-width:220px;box-shadow:0 0 16px rgba(var(--theme-primary-rgb),0.72), 0 0 4px rgba(var(--theme-secondary-rgb),0.6);border-radius:0.75rem;border:1.5px solid var(--theme-primary);background:var(--theme-surface-alt);padding:0.75rem;">
              <button type="button" class="btn btn-info neon-btn-info w-100 rounded-3 border mb-2 fw-bold text-uppercase shadow-sm" style="font-size:12px;" data-auth-open="login">Iniciar sesión</button>
              <button type="button" class="btn btn-warning neon-btn w-100 rounded-3 border fw-bold text-uppercase shadow-sm" style="font-size:12px;" data-auth-open="register">Registrarse</button>
            </div>
          <?php else: ?>
            <button id="user-trigger" type="button" class="btn btn-admin d-inline-flex align-items-center gap-3 rounded-pill px-3 py-2 shadow-sm border border-info" style="background:linear-gradient(90deg,var(--theme-button-primary) 0%,var(--theme-button-secondary) 100%);color:var(--theme-button-text);min-width:210px;box-shadow:0 0 16px rgba(var(--theme-button-primary-rgb),0.28);">
              <span id="user-trigger-initials" class="d-inline-flex align-items-center justify-content-center rounded-circle fw-bold" style="width:38px;height:38px;background:rgba(var(--theme-bg-main-rgb),0.18);border:1px solid rgba(var(--theme-bg-main-rgb),0.2);font-family:'Oxanium',sans-serif;">
                <?php echo htmlspecialchars($authUserInitials, ENT_QUOTES, 'UTF-8'); ?>
              </span>
              <span class="d-flex flex-column align-items-start text-start lh-sm flex-grow-1 overflow-hidden">
                <span class="small text-uppercase fw-bold" style="letter-spacing:0.15em;opacity:0.7;">Mi cuenta</span>
                <span id="user-trigger-name" class="fw-bold text-truncate w-100"><?php echo htmlspecialchars($authUserName, ENT_QUOTES, 'UTF-8'); ?></span>
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div id="user-menu" class="position-absolute end-0 mt-2 z-3 d-none" style="min-width:240px;max-width:280px;box-shadow:0 0 16px rgba(var(--theme-primary-rgb),0.72), 0 0 4px rgba(var(--theme-secondary-rgb),0.6);border-radius:1rem;border:1.5px solid var(--theme-primary);background:var(--theme-surface-alt);padding:0.85rem;">
              <div class="px-2 pb-2 mb-2 border-bottom border-info-subtle">
                <div id="user-menu-name" class="fw-bold text-light"><?php echo htmlspecialchars($authUserName, ENT_QUOTES, 'UTF-8'); ?></div>
                <div id="user-menu-email" class="small text-info text-break"><?php echo htmlspecialchars($authUserEmail, ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
              <button type="button" class="btn btn-admin w-100 rounded-3 border mb-2 fw-semibold" data-user-open="orders">Ver Pedidos</button>
              <button type="button" class="btn btn-outline-info w-100 rounded-3 border mb-2 fw-semibold" data-user-open="profile">Datos Usuario</button>
              <a href="/logout" class="btn btn-danger w-100 rounded-3 border fw-semibold">Cerrar sesión</a>
            </div>
          <?php endif; ?>
        </div>
      </header>

      <?php
      $authFlash = $_SESSION["auth_flash"] ?? null;
      if ($authFlash) {
        unset($_SESSION["auth_flash"]);
      }
      ?>
      <?php if (!empty($authFlash["message"])): ?>
        <?php
          $flashType = $authFlash["type"] ?? "info";
          $flashClasses = $flashType === "success"
            ? "border-emerald-400/30 bg-emerald-500/10 text-emerald-200"
            : ($flashType === "error" ? "border-rose-400/30 bg-rose-500/10 text-rose-200" : "border-cyan-400/30 bg-cyan-500/10 text-cyan-200");
        ?>
        <div class="mt-4 rounded-3 border px-3 py-2 small <?php echo $flashClasses; ?>">
          <?php echo htmlspecialchars($authFlash["message"], ENT_QUOTES, "UTF-8"); ?>
        </div>
      <?php endif; ?>

        <div id="menu-overlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="background:var(--theme-overlay-strong);backdrop-filter:blur(4px);z-index:1040;"></div>
        <nav id="menu-panel" class="position-fixed start-50 top-0 translate-middle-x d-none w-100" style="max-width:420px;max-height:calc(100vh - 96px);overflow-y:auto;border-radius:1.5rem;border:2px solid var(--theme-primary);background:var(--theme-panel-bg);padding:1.5rem;box-shadow:var(--theme-shadow-primary), var(--theme-shadow-secondary);z-index:1050;">
          <button id="menu-close" class="btn btn-outline-info rounded-circle position-absolute end-0 top-0 m-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;box-shadow:0 0 12px var(--theme-primary);z-index:1060;" aria-label="Cerrar menú">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
          </svg>
        </button>
        <div class="d-flex align-items-center justify-content-between">
          <p class="small text-uppercase text-secondary mb-0" style="letter-spacing:0.35em;">Menu</p>
        </div>
        <div class="mt-4 d-grid gap-2">
          <a href="/" class="btn btn-dark border rounded-3 px-4 py-3 fw-semibold">Inicio</a>
          <a href="/populares" class="btn btn-dark border rounded-3 px-4 py-3 fw-semibold">Juegos populares</a>
          <a href="/juegos" class="btn btn-dark border rounded-3 px-4 py-3 fw-semibold">Juegos</a>
          <?php if ($authUser): ?>
            <?php if ($authUserRole === 'admin'): ?>
              <hr class="my-2 border-slate-700">
              <a href="/admin/dashboard" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Dashboard</a>
              <a href="/admin/juegos" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Juegos</a>
              <a href="/admin/monedas" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Monedas</a>
              <a href="/admin/pedidos" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Pedidos</a>
              <a href="/admin/usuarios" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Usuarios</a>
              <a href="/admin/cupones" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Cupones</a>
              <a href="/admin/configuracion" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Configuración</a>
              <a href="/admin/dashboard" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Ir al Admin</a>
            <?php endif; ?>
            <a href="/logout" class="btn btn-danger border rounded-3 px-4 py-3 fw-semibold">Cerrar sesión</a>
          <?php endif; ?>
        </div>
      </nav>

      <div id="auth-modal" class="position-fixed top-0 start-0 w-100 h-100 d-none d-flex align-items-center justify-content-center px-4" style="z-index:13000;">
        <div class="position-absolute top-0 start-0 w-100 h-100" style="background:var(--theme-overlay-soft);backdrop-filter:blur(6px);box-shadow:var(--theme-shadow-primary), var(--theme-shadow-secondary);z-index:11000;" data-auth-close></div>
        <div class="position-relative w-100 neon-modal" style="max-width:420px;border-radius:1.5rem;border:2px solid var(--theme-primary);background:var(--theme-panel-gradient);padding:2rem 1.5rem;box-shadow:var(--theme-shadow-primary), var(--theme-shadow-secondary);animation:fadeUp 320ms ease-out both;z-index:12000;">
          <button type="button" data-auth-close class="position-absolute" style="top:18px;right:18px;width:48px;height:48px;border-radius:50%;background:var(--theme-primary-soft);border:2px solid var(--theme-primary);display:flex;align-items:center;justify-content:center;z-index:13001;box-shadow:0 0 12px var(--theme-primary);" aria-label="Cerrar">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="var(--theme-primary)" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div id="auth-login" class="d-grid gap-4">
            <div>
              <p class="small text-uppercase text-neon mb-0" style="letter-spacing:0.35em;">Cuenta de usuario</p>
              <h2 class="mt-2 text-neon fw-bold" style="font-family:'Oxanium',sans-serif;font-size:2rem;text-shadow:0 0 8px var(--theme-primary);">Iniciar sesión</h2>
            </div>
            <form action="/login.php" method="post" class="d-grid gap-4" novalidate>
              <div class="d-grid gap-3">
                <label class="form-label small text-neon">Correo electrónico</label>
                <input type="email" name="email" autocomplete="email" class="form-control rounded-3 bg-dark text-neon border border-info" placeholder="nombre@correo.com" />
                <label class="form-label small text-neon">Contraseña</label>
                <div class="position-relative">
                  <input type="password" name="password" autocomplete="current-password" class="form-control rounded-3 bg-dark text-neon border border-info pe-5" placeholder="Ingresa tu contraseña" id="login-password" />
                  <button type="button" class="btn position-absolute end-0 top-50 translate-middle-y d-inline-flex align-items-center justify-content-center text-info" style="width:46px;height:46px;border:none;background:transparent;box-shadow:none;" data-password-toggle="login-password" data-password-label-show="Mostrar contraseña" data-password-label-hide="Ocultar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" data-password-icon="hidden">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12.001C3.226 16.273 7.322 19.5 12 19.5c1.658 0 3.237-.336 4.677-.947M6.228 6.228A9.956 9.956 0 0112 4.5c4.677 0 8.773 3.227 10.065 7.499a10.523 10.523 0 01-4.293 5.774M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" data-password-icon="visible" class="d-none">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </button>
                </div>
              </div>
              <button type="submit" class="btn btn-info neon-btn-info w-100 rounded-3 px-4 py-2 fw-bold text-uppercase shadow">Iniciar sesión</button>
              <a href="/reset.php" class="d-block w-100 text-center small fw-bold text-neon">¿Has olvidado la contraseña?</a>
            </form>
            <?php if ($googleAuthEnabled): ?>
              <div class="d-grid gap-3">
                <div class="d-flex align-items-center gap-3 small text-neon" aria-hidden="true">
                  <span class="flex-grow-1" style="height:1px;background:rgba(var(--theme-primary-rgb),0.32);"></span>
                  <span>o</span>
                  <span class="flex-grow-1" style="height:1px;background:rgba(var(--theme-primary-rgb),0.32);"></span>
                </div>
                <a href="<?php echo htmlspecialchars($googleAuthLoginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn w-100 rounded-3 px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-2" style="background:#ffffff;color:#111827;border:1px solid rgba(255,255,255,0.78);">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.654 32.657 29.233 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.27 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                    <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.27 4 24 4c-7.682 0-14.289 4.337-17.694 10.691z"/>
                    <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.143 35.091 26.715 36 24 36c-5.212 0-9.62-3.329-11.283-7.946l-6.522 5.025C9.56 39.556 16.618 44 24 44z"/>
                    <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.793 2.238-2.231 4.166-4.084 5.571.001-.001 6.19 5.238 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                  </svg>
                  <span>Continuar con Google</span>
                </a>
              </div>
            <?php endif; ?>
            <button type="button" data-auth-switch="register" class="btn btn-link w-100 small fw-semibold text-info">¿No tienes una cuenta? Regístrate ahora</button>
          </div>

          <div id="auth-register" class="d-none d-grid gap-4">
            <div>
              <p class="small text-uppercase text-neon mb-0" style="letter-spacing:0.35em;">Cuenta</p>
              <h2 class="mt-2 text-neon fw-bold" style="font-family:'Oxanium',sans-serif;font-size:2rem;text-shadow:0 0 8px var(--theme-primary);">Crear cuenta</h2>
              <p class="mt-1 small text-neon">Regístrate para empezar a operar en <?php echo htmlspecialchars($brandName, ENT_QUOTES, "UTF-8"); ?>.</p>
            </div>
            <form id="registro-form" class="d-grid gap-4" novalidate autocomplete="off">
              <div class="d-grid gap-3">
                <label class="form-label small text-neon">Nombre completo</label>
                <input type="text" id="nombre" autocomplete="name" class="form-control rounded-3 bg-dark text-neon border border-info" placeholder="Ej. Juan Pérez" required />
                <label class="form-label small text-neon">Correo electrónico</label>
                <input type="email" id="correo" autocomplete="email" class="form-control rounded-3 bg-dark text-neon border border-info" placeholder="nombre@correo.com" required />
                <label class="form-label small text-neon">Número de teléfono</label>
                <input type="tel" id="telefono" autocomplete="tel" class="form-control rounded-3 bg-dark text-neon border border-info" placeholder="+58 412 0000000" />
                <label class="form-label small text-neon">Contraseña</label>
                <div class="position-relative">
                  <input type="password" id="contrasena" autocomplete="new-password" class="form-control rounded-3 bg-dark text-neon border border-info pe-5" placeholder="Crea una contraseña segura" required />
                  <button type="button" class="btn position-absolute end-0 top-50 translate-middle-y d-inline-flex align-items-center justify-content-center text-info" style="width:46px;height:46px;border:none;background:transparent;box-shadow:none;" data-password-toggle="contrasena" data-password-label-show="Mostrar contraseña" data-password-label-hide="Ocultar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" data-password-icon="hidden">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12.001C3.226 16.273 7.322 19.5 12 19.5c1.658 0 3.237-.336 4.677-.947M6.228 6.228A9.956 9.956 0 0112 4.5c4.677 0 8.773 3.227 10.065 7.499a10.523 10.523 0 01-4.293 5.774M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" data-password-icon="visible" class="d-none">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </button>
                </div>
              </div>
              <button type="submit" id="registro-btn" class="btn btn-info neon-btn-info w-100 rounded-3 px-4 py-2 fw-bold text-uppercase shadow">Registrarse ahora</button>
            </form>
            <?php if ($googleAuthEnabled): ?>
              <div class="d-grid gap-3">
                <div class="d-flex align-items-center gap-3 small text-neon" aria-hidden="true">
                  <span class="flex-grow-1" style="height:1px;background:rgba(var(--theme-primary-rgb),0.32);"></span>
                  <span>o</span>
                  <span class="flex-grow-1" style="height:1px;background:rgba(var(--theme-primary-rgb),0.32);"></span>
                </div>
                <a href="<?php echo htmlspecialchars($googleAuthLoginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn w-100 rounded-3 px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-2" style="background:#ffffff;color:#111827;border:1px solid rgba(255,255,255,0.78);">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.654 32.657 29.233 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.27 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                    <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.27 4 24 4c-7.682 0-14.289 4.337-17.694 10.691z"/>
                    <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.143 35.091 26.715 36 24 36c-5.212 0-9.62-3.329-11.283-7.946l-6.522 5.025C9.56 39.556 16.618 44 24 44z"/>
                    <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.793 2.238-2.231 4.166-4.084 5.571.001-.001 6.19 5.238 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                  </svg>
                  <span>Registrarme con Google</span>
                </a>
              </div>
            <?php endif; ?>
            <script src="<?php echo htmlspecialchars($scriptDir . '/registro.js?v=' . date('YmdHis'), ENT_QUOTES, 'UTF-8'); ?>" data-register-endpoint="<?php echo htmlspecialchars($scriptDir . '/register_user.php', ENT_QUOTES, 'UTF-8'); ?>" data-login-url="<?php echo htmlspecialchars($scriptDir . '/login.php', ENT_QUOTES, 'UTF-8'); ?>"></script>
            <button type="button" data-auth-switch="login" class="btn btn-link w-100 small fw-bold text-neon">¿Ya tienes una cuenta? Inicia sesión</button>
          </div>
        </div>
      </div>

      <?php if ($authUser): ?>
      <div id="user-orders-modal" class="position-fixed top-0 start-0 w-100 h-100 d-none d-flex align-items-start align-items-md-center justify-content-center px-3 py-3 overflow-auto" style="z-index:13100;">
        <div class="position-absolute top-0 start-0 w-100 h-100" style="background:var(--theme-overlay-soft);backdrop-filter:blur(6px);" data-user-close></div>
        <div class="position-relative w-100" style="max-width:820px;z-index:1;">
          <div class="rounded-4 border border-info overflow-hidden" style="background:var(--theme-panel-gradient);box-shadow:0 0 32px var(--theme-primary-glow);">
            <div class="d-flex align-items-center justify-content-between gap-3 px-4 py-3 border-bottom border-info-subtle">
              <div>
                <div class="small text-uppercase text-info" style="letter-spacing:0.3em;">Mi cuenta</div>
                <h3 class="h5 mb-0 text-white">Pedidos realizados</h3>
              </div>
              <button type="button" class="btn btn-outline-info rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;" data-user-close aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <div class="px-4 py-4" style="max-height:calc(100vh - 170px);overflow-y:auto;">
              <div id="user-orders-feedback" class="d-none alert mb-3 py-2"></div>
              <div id="user-orders-loading" class="text-center py-5 text-info">Cargando pedidos...</div>
              <div id="user-orders-empty" class="d-none text-center py-5 text-secondary">Todavía no has realizado pedidos con esta cuenta.</div>
              <div id="user-orders-list" class="d-none">
                <div class="table-responsive d-none d-md-block rounded-4 border border-info-subtle overflow-hidden" style="background:var(--theme-bg-elevated);">
                  <table class="table align-middle mb-0" style="--bs-table-bg:transparent;--bs-table-color:var(--theme-text);">
                    <thead>
                      <tr>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Pedido</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Juego</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Paquete</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Correo</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Estado</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody id="user-orders-table-body"></tbody>
                  </table>
                </div>
                <div id="user-orders-cards" class="d-grid d-md-none gap-3"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div id="user-profile-modal" class="position-fixed top-0 start-0 w-100 h-100 d-none d-flex align-items-center justify-content-center px-3" style="z-index:13100;">
        <div class="position-absolute top-0 start-0 w-100 h-100" style="background:var(--theme-overlay-soft);backdrop-filter:blur(6px);" data-user-close></div>
        <div class="position-relative w-100" style="max-width:560px;z-index:1;">
          <div class="rounded-4 border border-info overflow-hidden" style="background:var(--theme-panel-gradient);box-shadow:0 0 32px var(--theme-primary-glow);">
            <div class="d-flex align-items-center justify-content-between gap-3 px-4 py-3 border-bottom border-info-subtle">
              <div>
                <div class="small text-uppercase text-info" style="letter-spacing:0.3em;">Mi cuenta</div>
                <h3 class="h5 mb-0 text-white">Datos de usuario</h3>
              </div>
              <button type="button" class="btn btn-outline-info rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;" data-user-close aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <div class="px-4 py-4">
              <div id="user-profile-feedback" class="d-none alert mb-3 py-2"></div>
              <form id="user-profile-form" class="d-grid gap-3" novalidate>
                <div>
                  <label class="form-label small text-info">Nombre</label>
                  <input type="text" name="name" class="form-control bg-dark text-info border-info" value="<?php echo htmlspecialchars($authUserName, ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div>
                  <label class="form-label small text-info">Correo</label>
                  <input type="email" name="email" class="form-control bg-dark text-info border-info" value="<?php echo htmlspecialchars($authUserEmail, ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div>
                  <label class="form-label small text-info">Nueva contraseña</label>
                  <input type="password" name="password" class="form-control bg-dark text-info border-info" placeholder="Opcional" autocomplete="new-password" />
                </div>
                <div>
                  <label class="form-label small text-info">Confirmar contraseña</label>
                  <input type="password" name="password_confirm" class="form-control bg-dark text-info border-info" placeholder="Repite la contraseña nueva" autocomplete="new-password" />
                </div>
                <button type="submit" class="btn btn-admin w-100 rounded-3 py-2 fw-bold">Guardar cambios</button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
