<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/store_config.php";
require_once __DIR__ . "/includes/db_connect.php";
require_once __DIR__ . "/includes/tenant.php";

$pageTitle = store_config_get('nombre_tienda', 'TVirtualGaming') . " | Restablecer contraseña";

function ensure_reset_requested_at_column(mysqli $mysqli): void {
  $columns = $mysqli->query("SHOW COLUMNS FROM usuarios LIKE 'reset_requested_at'");
  if ($columns && $columns->num_rows > 0) {
    return;
  }
  $mysqli->query("ALTER TABLE usuarios ADD COLUMN reset_requested_at DATETIME NULL AFTER rol");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = auth_normalize_email($_POST["email"] ?? "");

  if ($email !== "") {
    ensure_reset_requested_at_column($mysqli);
    $requestedAt = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("UPDATE usuarios SET reset_requested_at = ? WHERE LOWER(email) = ?");
    if ($stmt) {
      $stmt->bind_param('ss', $requestedAt, $email);
      $stmt->execute();
      $stmt->close();
    }
  }

  auth_set_flash("success", "Si el correo existe, enviamos instrucciones para restablecer la contraseña.");
  header("Location: /reset.php");
  exit;
}

include __DIR__ . "/includes/header.php";
?>

      <section class="mt-10 flex items-center justify-center">
        <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/95 p-6 shadow-2xl" style="animation: fadeUp 320ms ease-out both;">
          <div>
            <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Recuperación</p>
            <h2 class="mt-2 font-oxanium text-2xl font-semibold">Restablecer contraseña</h2>
            <p class="mt-1 text-xs text-slate-400">Ingresa tu correo para recibir instrucciones.</p>
          </div>
          <form action="/reset.php" method="post" class="mt-4 space-y-4" novalidate>
            <label class="block text-xs text-slate-400">Correo electrónico</label>
            <input type="email" name="email" autocomplete="email" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70" placeholder="nombre@correo.com" />
            <button type="submit" class="w-full rounded-xl border border-sky-400/30 bg-sky-500/80 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-sky-400">Enviar instrucciones</button>
          </form>
        </div>
      </section>

<?php
include __DIR__ . "/includes/footer.php";
?>
