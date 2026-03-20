<?php
require_once __DIR__ . "/includes/db_connect.php";
require_once __DIR__ . "/includes/store_config.php";
require_once __DIR__ . "/includes/currency.php";
require_once __DIR__ . "/includes/payment_methods.php";
currency_ensure_schema();
$paymentSupportWhatsappBase = store_config_whatsapp_link(store_config_get('whatsapp', ''));
$loggedUserEmail = '';
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (!empty($_SESSION['auth_user']['email'])) {
  $loggedUserEmail = (string) $_SESSION['auth_user']['email'];
}
payment_methods_ensure_table();
$paymentMethodsByCurrency = payment_methods_active_by_currency();
$game = null;
if (isset($_GET['slug'])) {
  $slug = strtolower(trim($_GET['slug']));
  $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
  $stmt = $mysqli->prepare("SELECT * FROM juegos WHERE slug=? LIMIT 1");
  $stmt->bind_param('s', $slug);
  $stmt->execute();
  $res = $stmt->get_result();
  $game = $res->fetch_assoc();
  $stmt->close();
} elseif (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $mysqli->prepare("SELECT * FROM juegos WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $game = $res->fetch_assoc();
  $stmt->close();
}
if (!$game) {
  // Si no se encuentra, mostrar el primero
  $res = $mysqli->query("SELECT * FROM juegos ORDER BY id DESC LIMIT 1");
  $game = $res ? $res->fetch_assoc() : null;
}
if (!$game) {
  die('Juego no encontrado.');
}
$pageTitle = store_config_get('nombre_tienda', 'TVirtualGaming') . " | " . ($game["nombre"] ?? "Juego");
include __DIR__ . "/includes/header.php";
?>


<section class="container mt-5 mb-4 p-4 bg-dark bg-opacity-75 rounded-4 shadow">
  <div class="row align-items-center">
    <div class="col-auto">
      <div class="rounded-4 border border-info bg-dark position-relative overflow-hidden" style="width:64px; height:64px;">
        <img src="/<?= htmlspecialchars($game["imagen"] ?? '', ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($game["nombre"] ?? '', ENT_QUOTES, "UTF-8") ?>" class="w-100 h-100 object-fit-cover" />
        <?php if (!empty($game['popular'])): ?>
          <span title="Popular" class="position-absolute top-0 end-0 text-success fs-4" style="right:8px;top:8px;">★</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="col">
      <h2 class="h4 fw-bold mb-2 text-info"><?= htmlspecialchars($game["nombre"] ?? '', ENT_QUOTES, "UTF-8") ?></h2>
      <div class="d-flex flex-wrap gap-2 text-secondary small">
        <?php 
          $carRes = $mysqli->query("SELECT caracteristica FROM juego_caracteristicas WHERE juego_id=" . intval($game['id']));
          while ($row = $carRes->fetch_assoc()) {
            echo '<span class="badge rounded-pill border border-info text-info px-2 py-1 bg-dark">' . htmlspecialchars($row['caracteristica']) . '</span>';
          }
        ?>
      </div>
    </div>
  </div>
</section>

<section class="container mt-4">
  <div class="row mb-2 align-items-center">
    <div class="col">
      <h3 class="h5 fw-bold text-info">Paquetes disponibles</h3>
    </div>
    <div class="col-auto">
      <span class="text-uppercase text-secondary small">elige uno</span>
    </div>
  </div>
  <?php
    // Obtener todas las monedas
    $monedas = [];
    $resAllMon = $mysqli->query("SELECT * FROM monedas ORDER BY es_base DESC, nombre ASC");
    while ($row = $resAllMon->fetch_assoc()) {
      $monedas[] = $row;
    }
    $is_variable = empty($game['moneda_fija_id']);
    $moneda_actual_id = $is_variable ? ($monedas[0]['id'] ?? null) : $game['moneda_fija_id'];
    $moneda_actual = null;
    foreach ($monedas as $m) {
      if ($m['id'] == $moneda_actual_id) {
        $moneda_actual = $m;
        break;
      }
    }
    if (!$moneda_actual && count($monedas)) $moneda_actual = $monedas[0];
  ?>
  <?php if ($is_variable && count($monedas) > 1): ?>
    <div class="mb-4">
      <label for="moneda-select" class="form-label text-info">Selecciona la moneda:</label>
      <select id="moneda-select" class="form-select bg-dark text-info border-info" style="min-width:180px">
        <?php foreach ($monedas as $m): ?>
          <option value="<?= $m['id'] ?>" data-tasa="<?= htmlspecialchars($m['tasa']) ?>" data-clave="<?= htmlspecialchars($m['clave']) ?>" <?= $m['id'] == $moneda_actual['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3 mb-4" id="pack-grid">
    <?php 
      $resPaq = $mysqli->query("SELECT * FROM juego_paquetes WHERE juego_id=" . intval($game['id']) . " ORDER BY precio ASC");
      $paquetes = [];
      while ($pack = $resPaq->fetch_assoc()) {
        $paquetes[] = $pack;
      }
      foreach ($paquetes as $pack):
        $precio_base = floatval($pack['precio']);
        $precio_mostrar = $moneda_actual ? currency_convert_from_base($precio_base, $moneda_actual) : currency_apply_amount_rule($precio_base, null);
        $clave_moneda = $moneda_actual['clave'] ?? 'USD';
        $mostrarDecimales = $moneda_actual ? currency_should_show_decimals($moneda_actual) : true;
    ?>
      <div class="col">
        <button type="button" class="pack-card card border-info bg-dark text-start w-100 h-100 shadow-sm"
          data-package-id="<?= (int) ($pack['id'] ?? 0) ?>"
          data-base="<?= htmlspecialchars($precio_base) ?>"
          data-name="<?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?>"
          data-cantidad="<?= htmlspecialchars($pack['cantidad'], ENT_QUOTES, 'UTF-8') ?>"
          data-price-value="<?= htmlspecialchars((string) $precio_mostrar, ENT_QUOTES, 'UTF-8') ?>"
          data-show-decimals="<?= $mostrarDecimales ? '1' : '0' ?>"
          data-moneda="<?= htmlspecialchars($clave_moneda) ?>">
          <div class="card-body p-0 d-flex flex-column">
            <?php 
              $img_paquete = !empty($pack['imagen_icono']) ? $pack['imagen_icono'] : (!empty($game['imagen_paquete']) ? $game['imagen_paquete'] : null);
            ?>
            <div class="pack-card-media">
              <?php if ($img_paquete): ?>
                <img src="/<?= htmlspecialchars($img_paquete, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?>" class="pack-card-image" />
              <?php else: ?>
                <span class="pack-card-placeholder">PK</span>
              <?php endif; ?>
              <div class="pack-card-glow"></div>
            </div>
            <div class="pack-card-content">
              <p class="pack-card-name mb-0 fw-semibold"><?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
              <div class="pack-card-footer">
                <span class="moneda-label"><?= htmlspecialchars($clave_moneda) ?></span>
                <span class="precio-label">
                  <?= currency_format_amount($precio_mostrar, $moneda_actual) ?>
                </span>
              </div>
            </div>
          </div>
        </button>
      </div>
    <?php endforeach; ?>
  </div>
  <?php 
    $monedas_js = [];
    foreach ($monedas as $m) {
      $monedas_js[$m['id']] = [
        'tasa' => floatval($m['tasa']),
        'clave' => $m['clave'],
        'mostrar_decimales' => !empty($m['mostrar_decimales']),
      ];
    }
  ?>
  <script>
    const monedas = <?= json_encode($monedas_js) ?>;
    let monedaActualId = "<?= $moneda_actual['id'] ?? '' ?>";
    let monedaActualClave = "<?= $moneda_actual['clave'] ?? 'USD' ?>";
    let monedaActualTasa = <?= $moneda_actual['tasa'] ?? 1 ?>;
    let monedaActualMostrarDecimales = <?= $moneda_actual ? (currency_should_show_decimals($moneda_actual) ? 'true' : 'false') : 'true' ?>;
    const monedaSelect = document.getElementById('moneda-select');
    const packCards = Array.from(document.querySelectorAll('.pack-card'));
    const normalizeCurrencyAmount = (amount, showDecimals) => {
      const numericAmount = Number(amount || 0);
      if (!Number.isFinite(numericAmount)) {
        return 0;
      }
      return showDecimals ? Number(numericAmount.toFixed(2)) : Math.trunc(numericAmount);
    };
    const formatCurrencyAmount = (amount, showDecimals) => {
      const normalized = normalizeCurrencyAmount(amount, showDecimals);
      return normalized.toLocaleString('en-US', {
        minimumFractionDigits: showDecimals ? 2 : 0,
        maximumFractionDigits: showDecimals ? 2 : 0,
      });
    };
    function updatePackPrices() {
      packCards.forEach(card => {
        const base = parseFloat(card.getAttribute('data-base'));
        const precio = normalizeCurrencyAmount(base * monedaActualTasa, monedaActualMostrarDecimales);
        card.querySelector('.precio-label').textContent = formatCurrencyAmount(precio, monedaActualMostrarDecimales);
        card.querySelector('.moneda-label').textContent = monedaActualClave;
        card.setAttribute('data-price-value', String(precio));
        card.setAttribute('data-show-decimals', monedaActualMostrarDecimales ? '1' : '0');
        card.setAttribute('data-moneda', monedaActualClave);
      });
    }
    updatePackPrices();
  </script>
</section>


  <div class="container mb-4">
    <div class="row mb-2">
      <div class="col">
        <h3 class="h5 fw-bold text-info">Resumen de compra</h3>
        <span class="text-uppercase text-secondary small">verifica</span>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-md-8">
        <div class="card bg-dark border-info mb-2">
          <div class="card-body">
            <p class="small text-secondary mb-1">Paquete seleccionado</p>
            <p id="selected-pack" class="fw-bold text-white">Ninguno</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card bg-dark border-info mb-2">
          <div class="card-body">
            <p class="small text-secondary mb-1">Total</p>
            <p id="selected-price" class="fw-bold text-info fs-5"><?= ($moneda_actual['clave'] ?? 'Bs.') . ' ' . currency_format_amount(0, $moneda_actual) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<section class="container mt-5 mb-5 p-4 bg-dark bg-opacity-75 rounded-4 shadow">
  <div class="row mb-2 align-items-center">
    <div class="col">
      <h3 class="h5 fw-bold text-info">Información de pedido</h3>
      <span class="text-uppercase text-secondary small">seguro</span>
    </div>
  </div>
  <form class="row g-3" id="order-form">
    <div class="col-md-4">
      <label class="form-label text-info">ID de usuario</label>
      <input type="text" name="user_id" placeholder="Ej: 12345678" class="form-control bg-dark text-info border-info" required />
    </div>
    <div class="col-md-4">
      <label class="form-label text-info">Correo</label>
      <input type="email" name="email" placeholder="tu@email.com" value="<?= htmlspecialchars($loggedUserEmail, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" class="form-control bg-dark text-info border-info" required />
    </div>
    <div class="col-md-4">
      <label class="form-label text-info">Cupón</label>
      <div class="input-group">
        <input type="text" name="coupon" id="coupon-input" placeholder="Código opcional" pattern="[A-Za-z0-9]+" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" title="Solo letras y números, sin espacios ni caracteres especiales." class="form-control bg-dark text-info border-info" />
        <button type="button" id="apply-coupon-btn" class="btn btn-info fw-bold">Aplicar cupón</button>
      </div>
    </div>
    <div class="col-12">
      <button type="submit" id="buy-button" class="btn btn-success w-100 fw-bold text-uppercase" disabled>
        Compra ahora
      </button>
    </div>
  </form>


  <!-- Modal Loading Bootstrap -->
  <div id="loading-modal" class="modal fade app-overlay-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-info text-center p-4">
        <div class="mb-3">
          <svg width="48" height="48" viewBox="0 0 50 50">
            <circle cx="25" cy="25" r="20" fill="none" stroke="#34d399" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4" transform="rotate(-90 25 25)">
              <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
            </circle>
          </svg>
        </div>
        <h4 id="loading-modal-title" class="fw-bold text-info mb-2">Procesando pedido...</h4>
        <p id="loading-modal-message" class="text-light mb-0 small">Espera un momento mientras completamos la operación.</p>
      </div>
    </div>
  </div>
  <div id="payment-status-modal" class="modal fade app-overlay-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-info text-center p-4">
        <h4 id="payment-status-modal-title" class="fw-bold text-info mb-3">Estado de la operación</h4>
        <p id="payment-status-modal-message" class="text-light mb-4 small">Tu solicitud fue procesada.</p>
        <button type="button" id="payment-status-modal-accept" class="btn btn-info fw-bold px-4">Aceptar</button>
      </div>
    </div>
  </div>
  <!-- Modal Cupón Bootstrap -->
  <div id="coupon-modal" class="modal fade app-overlay-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-info text-center p-4">
        <h4 class="fw-bold text-info mb-2">¿Desea aplicar el cupón <span id="modal-coupon-name" class="text-success"></span>?</h4>
        <div class="d-flex gap-2 justify-content-center mt-4">
          <button type="button" id="modal-yes" class="btn btn-success">Sí</button>
          <button type="button" id="modal-no" class="btn btn-info">No</button>
          <button type="button" id="modal-cancel" class="btn btn-secondary">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <div id="payment-modal" class="modal fade app-overlay-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered payment-modal-dialog">
      <div class="modal-content payment-modal-content text-light border-info">
        <div class="payment-expiration-banner" id="payment-expiration-banner">
          <span>La orden expira en:</span>
          <strong id="payment-timer-value">30:00</strong>
        </div>
        <div id="payment-modal-alert" class="d-none alert mb-3"></div>
        <div id="payment-modal-reasons" class="d-none payment-reasons-card mb-3"></div>
        <div id="payment-modal-actions" class="d-none payment-support-actions mb-4"></div>
        <div class="payment-summary-card mb-4">
          <h3 class="h5 fw-bold text-white mb-3">Resumen de Pago</h3>
          <div class="payment-summary-row"><span>ID Jugador:</span><strong id="payment-summary-user">-</strong></div>
          <div class="payment-summary-row"><span>Producto:</span><strong id="payment-summary-product">-</strong></div>
          <div class="payment-summary-row payment-summary-total"><span>Total a pagar:</span><strong id="payment-summary-total">-</strong></div>
        </div>
        <div class="payment-method-card mb-4">
          <div id="payment-method-select-wrap" class="mb-3 d-none">
            <label for="payment-method-select" class="form-label text-info">Método de pago</label>
            <select id="payment-method-select" class="form-select bg-dark text-info border-info"></select>
          </div>
          <h4 id="payment-method-title" class="h6 fw-bold text-white mb-2">Datos de pago</h4>
          <div id="payment-method-currency" class="small text-info mb-2"></div>
          <div id="payment-method-details" class="small text-light payment-method-details"></div>
        </div>
        <div class="mb-3">
          <label for="payment-reference-input" class="form-label text-info">Número de Referencia</label>
          <input type="text" id="payment-reference-input" class="form-control bg-dark text-info border-info" inputmode="numeric" autocomplete="off" placeholder="Inserte su número de referencia para comprobar el pago">
          <div id="payment-reference-help" class="form-text text-secondary">Inserte su número de referencia para comprobar el pago.</div>
        </div>
        <div class="mb-4">
          <label for="payment-phone-input" class="form-label text-info">Número de teléfono real para contactarte</label>
          <input type="tel" id="payment-phone-input" class="form-control bg-dark text-info border-info" autocomplete="tel" placeholder="Ej: 04121234567">
        </div>
        <button type="button" id="payment-submit-btn" class="btn btn-info w-100 fw-bold text-uppercase py-3">Pagado / Recargar</button>
        <button type="button" id="payment-cancel-order-btn" class="btn btn-danger w-100 fw-bold text-uppercase py-3 mt-3">Cancelar Orden</button>
      </div>
    </div>
  </div>

  <div id="payment-cancel-confirm-modal" class="modal fade app-overlay-modal payment-confirm-overlay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-danger text-light p-4 rounded-4">
        <h4 class="fw-bold text-danger mb-3">¿Deseas cancelar esta orden?</h4>
        <p class="text-light mb-4">La orden se marcará como cancelada y deberás generar una nueva si quieres continuar con la compra.</p>
        <div class="d-flex gap-2 justify-content-end flex-wrap">
          <button type="button" id="payment-cancel-dismiss-btn" class="btn btn-outline-info">Volver</button>
          <button type="button" id="payment-cancel-confirm-btn" class="btn btn-danger">Sí, cancelar orden</button>
        </div>
      </div>
    </div>
  </div>

<style>
  .app-overlay-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1080;
    opacity: 0;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(5, 10, 20, 0.78);
    backdrop-filter: blur(4px);
    overflow-y: auto;
  }

  .app-overlay-modal.is-visible {
    display: flex !important;
    opacity: 1 !important;
  }

  #loading-modal {
    z-index: 1105;
  }

  #payment-status-modal {
    z-index: 1110;
  }

  .app-overlay-modal .modal-dialog {
    width: min(92vw, 28rem);
    margin: 0;
  }

  .payment-modal-dialog {
    width: min(94vw, 34rem) !important;
  }

  .payment-modal-content {
    position: relative;
    padding: 1.25rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    border-radius: 1.5rem;
    background: linear-gradient(180deg, rgba(31, 41, 55, 0.98), rgba(17, 24, 39, 0.98));
    box-shadow: 0 0 28px rgba(34, 211, 238, 0.16);
  }

  .payment-expiration-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 3.4rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(248, 113, 113, 0.45);
    border-radius: 1rem;
    background: rgba(127, 29, 29, 0.12);
    color: #f87171;
    font-weight: 700;
  }

  .payment-summary-card,
  .payment-method-card {
    padding: 1rem;
    border-radius: 1rem;
    background: rgba(8, 15, 24, 0.74);
    border: 1px solid rgba(34, 211, 238, 0.15);
  }

  .payment-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
    color: #cbd5e1;
  }

  .payment-summary-row strong {
    color: #f8fafc;
    text-align: right;
  }

  .payment-summary-total {
    margin-top: 0.8rem;
    padding-top: 0.8rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
  }

  .payment-summary-total strong {
    color: #22d3ee;
    font-size: 1.2rem;
  }

  .payment-method-details {
    white-space: pre-line;
    line-height: 1.7;
  }

  .payment-modal-content .form-control::placeholder {
    color: rgba(148, 163, 184, 0.7) !important;
  }

  .payment-reasons-card {
    padding: 0.95rem 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(248, 113, 113, 0.35);
    background: rgba(127, 29, 29, 0.12);
  }

  .payment-reasons-title {
    color: #f8fafc;
    font-weight: 700;
    margin-bottom: 0.45rem;
  }

  .payment-reasons-summary {
    color: #e2e8f0;
    margin-bottom: 0.75rem;
    line-height: 1.55;
  }

  .payment-reasons-steps {
    margin: 0;
    padding-left: 1.15rem;
    color: #e2e8f0;
  }

  .payment-reasons-steps li + li {
    margin-top: 0.4rem;
  }

  .payment-reasons-caption {
    margin-top: 0.85rem;
    color: #fecaca;
    font-size: 0.92rem;
    font-weight: 700;
  }

  .payment-reasons-card ul {
    margin: 0.65rem 0 0;
    padding-left: 1.15rem;
    color: #fecaca;
  }

  .payment-support-actions {
    display: grid;
    gap: 0.75rem;
  }

  .payment-support-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3rem;
    padding: 0.8rem 1rem;
    border-radius: 999px;
    border: 1px solid rgba(45, 212, 191, 0.65);
    background: linear-gradient(135deg, rgba(6, 78, 59, 0.9), rgba(16, 185, 129, 0.82));
    color: #f0fdf4;
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 0 18px rgba(16, 185, 129, 0.18);
  }

  .payment-support-link:hover {
    color: #ffffff;
    box-shadow: 0 0 22px rgba(16, 185, 129, 0.28);
  }

  .payment-confirm-overlay {
    z-index: 1115;
    background: rgba(5, 10, 20, 0.38);
    backdrop-filter: blur(2px);
  }

  @media (max-width: 575.98px) {
    .app-overlay-modal {
      align-items: flex-start;
      padding: 0.55rem;
    }

    .app-overlay-modal .modal-dialog,
    .payment-modal-dialog {
      width: min(100%, 34rem) !important;
      margin: 0 auto;
    }

    .payment-modal-content {
      padding: 1rem;
      max-height: calc(100vh - 1.1rem);
      border-radius: 1.1rem;
    }

    .payment-expiration-banner {
      font-size: 0.92rem;
    }
  }

  body.overlay-open {
    overflow: hidden;
  }

  .pack-card {
    min-height: 15rem;
    border-width: 1px;
    border-radius: 1.1rem;
    overflow: hidden;
    background:
      radial-gradient(circle at top, rgba(var(--theme-button-primary-rgb), 0.18), transparent 45%),
      linear-gradient(180deg, rgba(var(--theme-button-surface-rgb), 0.98), rgba(var(--theme-bg-main-rgb), 0.98));
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }

  .pack-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 1rem 2rem rgba(var(--theme-button-primary-rgb), 0.2);
  }

  .pack-card .card-body {
    min-height: 100%;
  }

  .pack-card-media {
    width: 100%;
    min-height: 8.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    background: linear-gradient(180deg, rgba(var(--theme-bg-main-rgb), 0.45), rgba(var(--theme-bg-main-rgb), 0.05));
    flex-shrink: 0;
  }

  .pack-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transform: scale(1.02);
  }

  .pack-card-glow {
    position: absolute;
    inset: auto 0 0 0;
    height: 55%;
    background: linear-gradient(180deg, rgba(3, 7, 18, 0) 0%, rgba(3, 7, 18, 0.8) 78%, rgba(3, 7, 18, 0.98) 100%);
  }

  .pack-card-placeholder {
    color: var(--theme-button-primary);
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.18em;
  }

  .pack-card-content {
    display: grid;
    gap: 0.75rem;
    padding: 0.9rem 0.95rem 1rem;
    margin-top: auto;
  }

  .pack-card-name {
    color: var(--theme-text);
    min-height: 2.4rem;
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    text-align: left;
    line-height: 1.15;
    width: 100%;
    font-size: 0.98rem;
    letter-spacing: 0.01em;
    text-shadow: 0 0 10px rgba(var(--theme-button-primary-rgb), 0.18);
  }

  .pack-card-footer {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 0.65rem;
    border-top: 1px solid rgba(var(--theme-button-primary-rgb), 0.18);
    padding-top: 0.65rem;
  }

  .moneda-label {
    color: var(--theme-price-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    opacity: 0.92;
  }

  .precio-label {
    color: var(--theme-price-text);
    font-size: 1.1rem;
    font-weight: 800;
    line-height: 1;
    text-shadow: 0 0 12px rgba(var(--theme-price-text-rgb), 0.16);
  }

  .neon-selected {
    box-shadow: 0 0 16px 4px rgba(var(--theme-button-primary-rgb), 0.95), 0 0 32px 8px rgba(var(--theme-button-secondary-rgb), 0.75);
    border: 2px solid var(--theme-button-primary) !important;
    background: var(--theme-surface-alt) !important;
    transition: box-shadow 0.2s, border-color 0.2s;
    z-index: 2;
  }

  .neon-selected .pack-card-footer {
    border-top-color: rgba(var(--theme-button-secondary-rgb), 0.48);
  }

  @media (max-width: 575.98px) {
    .pack-card {
      min-height: 13.75rem;
    }

    .pack-card-media {
      min-height: 7.3rem;
    }

    .pack-card-content {
      padding: 0.8rem 0.8rem 0.9rem;
      gap: 0.55rem;
    }

    .pack-card-name {
      font-size: 0.9rem;
      min-height: 2.1rem;
    }

    .precio-label {
      font-size: 1rem;
    }
  }
</style>
<script>
  // Todas las variables y lógica JS en un solo bloque
  const defaultOrderEmail = <?= json_encode($loggedUserEmail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const paymentMethodsByCurrency = <?= json_encode($paymentMethodsByCurrency, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const paymentSupportWhatsappBase = <?= json_encode($paymentSupportWhatsappBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const packCards2 = Array.from(document.querySelectorAll('.pack-card'));
  const selectedPack = document.getElementById("selected-pack");
  const selectedPrice = document.getElementById("selected-price");
  const orderForm = document.getElementById("order-form");
  const buyButton = document.getElementById("buy-button");
  const couponInput = document.getElementById('coupon-input');
  const couponModal = document.getElementById('coupon-modal');
  const loadingModal = document.getElementById('loading-modal');
  const loadingModalTitle = document.getElementById('loading-modal-title');
  const loadingModalMessage = document.getElementById('loading-modal-message');
  const paymentStatusModal = document.getElementById('payment-status-modal');
  const paymentStatusModalTitle = document.getElementById('payment-status-modal-title');
  const paymentStatusModalMessage = document.getElementById('payment-status-modal-message');
  const paymentStatusModalAccept = document.getElementById('payment-status-modal-accept');
  const modalCouponName = document.getElementById('modal-coupon-name');
  const modalYes = document.getElementById('modal-yes');
  const modalNo = document.getElementById('modal-no');
  const modalCancel = document.getElementById('modal-cancel');
  const applyCouponButton = document.getElementById('apply-coupon-btn');
  const paymentModal = document.getElementById('payment-modal');
  const paymentModalContent = paymentModal ? paymentModal.querySelector('.payment-modal-content') : null;
  const paymentModalAlert = document.getElementById('payment-modal-alert');
  const paymentModalReasons = document.getElementById('payment-modal-reasons');
  const paymentModalActions = document.getElementById('payment-modal-actions');
  const paymentTimerValue = document.getElementById('payment-timer-value');
  const paymentSummaryUser = document.getElementById('payment-summary-user');
  const paymentSummaryProduct = document.getElementById('payment-summary-product');
  const paymentSummaryTotal = document.getElementById('payment-summary-total');
  const paymentMethodSelectWrap = document.getElementById('payment-method-select-wrap');
  const paymentMethodSelect = document.getElementById('payment-method-select');
  const paymentMethodTitle = document.getElementById('payment-method-title');
  const paymentMethodCurrency = document.getElementById('payment-method-currency');
  const paymentMethodDetails = document.getElementById('payment-method-details');
  const paymentReferenceInput = document.getElementById('payment-reference-input');
  const paymentReferenceHelp = document.getElementById('payment-reference-help');
  const paymentPhoneInput = document.getElementById('payment-phone-input');
  const paymentSubmitButton = document.getElementById('payment-submit-btn');
  const paymentCancelOrderButton = document.getElementById('payment-cancel-order-btn');
  const paymentCancelConfirmModal = document.getElementById('payment-cancel-confirm-modal');
  const paymentCancelDismissButton = document.getElementById('payment-cancel-dismiss-btn');
  const paymentCancelConfirmButton = document.getElementById('payment-cancel-confirm-btn');
  let lastFocusedElement = null;
  let activePack = null;
  let selectedTotalValue = 0;
  let couponApplied = false;
  let couponValue = '';
  let activePaymentOrder = null;
  let paymentTimerInterval = null;

  function scrollToOrderForm() {
    if (!orderForm) {
      return;
    }

    window.setTimeout(() => {
      orderForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
  }

  function syncOverlayState() {
    document.body.classList.toggle('overlay-open', Boolean(document.querySelector('.app-overlay-modal.is-visible')));
  }

  function setOverlayVisible(modalElement, visible) {
    if (!modalElement) {
      return;
    }
    if (visible) {
      lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    } else if (modalElement.contains(document.activeElement) && document.activeElement instanceof HTMLElement) {
      document.activeElement.blur();
    }
    modalElement.classList.toggle('show', visible);
    modalElement.classList.toggle('is-visible', visible);
    modalElement.setAttribute('aria-hidden', visible ? 'false' : 'true');
    syncOverlayState();
    if (visible) {
      const autofocusTarget = modalElement.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (autofocusTarget instanceof HTMLElement) {
        setTimeout(() => autofocusTarget.focus(), 0);
      }
    } else if (lastFocusedElement instanceof HTMLElement && document.body.contains(lastFocusedElement)) {
      setTimeout(() => {
        if (lastFocusedElement instanceof HTMLElement && document.body.contains(lastFocusedElement)) {
          lastFocusedElement.focus();
        }
        lastFocusedElement = null;
      }, 0);
    } else {
      lastFocusedElement = null;
    }
  }

  function removeBuySpinner() {
    const spinner = document.getElementById('spinner-compra');
    if (spinner) {
      spinner.remove();
    }
  }

  function setLoadingModalContent(title, message) {
    if (loadingModalTitle) {
      loadingModalTitle.textContent = title || 'Procesando pedido...';
    }
    if (loadingModalMessage) {
      loadingModalMessage.textContent = message || 'Espera un momento mientras completamos la operación.';
    }
  }

  function scrollPaymentModalToTop() {
    if (paymentModalContent) {
      paymentModalContent.scrollTop = 0;
    }
    if (paymentModal) {
      paymentModal.scrollTop = 0;
    }
  }

  function showPaymentStatusModal(title, message, type) {
    if (paymentStatusModalTitle) {
      paymentStatusModalTitle.textContent = title || 'Estado de la operación';
      paymentStatusModalTitle.classList.remove('text-info', 'text-success', 'text-danger');
      paymentStatusModalTitle.classList.add(type === 'success' ? 'text-success' : (type === 'danger' ? 'text-danger' : 'text-info'));
    }
    if (paymentStatusModalMessage) {
      paymentStatusModalMessage.textContent = message || 'Tu solicitud fue procesada.';
    }
    scrollPaymentModalToTop();
    setOverlayVisible(paymentStatusModal, true);
  }

  function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.position = 'fixed';
    toast.style.top = '30px';
    toast.style.left = '50%';
    toast.style.transform = 'translateX(-50%)';
    toast.style.background = type === 'error' ? '#f87171' : '#34d399';
    toast.style.color = '#222';
    toast.style.padding = '12px 24px';
    toast.style.borderRadius = '8px';
    toast.style.fontWeight = 'bold';
    toast.style.zIndex = '9999';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
  }

  function clearPaymentTimer() {
    if (paymentTimerInterval) {
      clearInterval(paymentTimerInterval);
      paymentTimerInterval = null;
    }
  }

  function escapePaymentHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function paymentReferencePlaceholder(method) {
    const digits = Number(method && method.referencia_digitos ? method.referencia_digitos : 0);
    if (digits > 0) {
      return `Inserte los últimos ${digits} dígitos de su referencia`;
    }
    return 'Inserte su número de referencia para comprobar el pago';
  }

  function paymentReferenceHelpText(method) {
    const digits = Number(method && method.referencia_digitos ? method.referencia_digitos : 0);
    if (digits > 0) {
      return `Solo debes escribir los últimos ${digits} dígitos de la referencia bancaria.`;
    }
    return 'Inserte su número de referencia para comprobar el pago.';
  }

  function getPaymentMethodsForCurrency(currencyCode) {
    return paymentMethodsByCurrency[String(currencyCode || '').toUpperCase()] || [];
  }

  function setPaymentAlert(message, type) {
    if (!paymentModalAlert) {
      return;
    }
    if (!message) {
      paymentModalAlert.className = 'd-none alert mb-3';
      paymentModalAlert.textContent = '';
      return;
    }
    paymentModalAlert.textContent = message;
    paymentModalAlert.className = `alert mb-3 alert-${type || 'info'}`;
    scrollPaymentModalToTop();
  }

  function clearPaymentSupportUi() {
    if (paymentModalReasons) {
      paymentModalReasons.className = 'd-none payment-reasons-card mb-3';
      paymentModalReasons.innerHTML = '';
    }
    if (paymentModalActions) {
      paymentModalActions.className = 'd-none payment-support-actions mb-4';
      paymentModalActions.innerHTML = '';
    }
  }

  if (paymentStatusModalAccept) {
    paymentStatusModalAccept.addEventListener('click', function() {
      setOverlayVisible(paymentStatusModal, false);
      scrollPaymentModalToTop();
    });
  }

  function buildPaymentSupportWhatsappUrl(orderId, reference, totalText) {
    if (!paymentSupportWhatsappBase) {
      return '';
    }

    const gameName = <?= json_encode((string) ($game['nombre'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const productName = paymentSummaryProduct ? paymentSummaryProduct.textContent : '';
    const userIdentifier = paymentSummaryUser ? paymentSummaryUser.textContent : '';
    const message = [
      'Hola, necesito apoyo para revisar manualmente un pago.',
      `Pedido: #${orderId || '-'}`,
      `Juego: ${gameName || '-'}`,
      `Producto: ${productName || '-'}`,
      `ID Jugador: ${userIdentifier || '-'}`,
      `Referencia: ${reference || '-'}`,
      `Monto: ${totalText || '-'}`,
      'Adjunto o enviaré captura del comprobante para revisión manual.'
    ].join('\n');
    return `${paymentSupportWhatsappBase}?text=${encodeURIComponent(message)}`;
  }

  function renderPaymentFailureDetails(data, reference, totalText) {
    clearPaymentSupportUi();
    const failureType = String((data && data.failure_type) || 'server_or_data_mismatch');
    const reasons = Array.isArray(data && data.reasons) ? data.reasons.filter(Boolean) : [];
    let title = 'No pudimos validar el pago automáticamente';
    let summary = 'La validación no se pudo completar con la respuesta actual del servidor bancario.';
    let steps = [
      'Espera 1 o 2 minutos y vuelve a intentar la validación en esta misma ventana.',
      'Si ya te debitaron el pago y sigue sin validarse, contacta al administrador por WhatsApp y envía el comprobante.'
    ];

    if (failureType === 'reference_mismatch') {
      title = 'La referencia no coincide';
      summary = 'La referencia ingresada no aparece igual en la respuesta del banco.';
      steps = [
        'Revisa que hayas escrito exactamente los dígitos solicitados de la referencia bancaria.',
        'Si la transferencia es reciente, espera 1 o 2 minutos y vuelve a intentar.',
        'Si el comprobante está correcto y el problema continúa, contacta al administrador por WhatsApp.'
      ];
    } else if (failureType === 'amount_mismatch') {
      title = 'El monto no coincide';
      summary = 'La referencia sí se encontró, pero el monto recibido por el banco no coincide con el total esperado del pedido.';
      steps = [
        'Verifica que el monto transferido corresponda al total del pedido.',
        'Si el banco aún no refleja el monto correcto, espera 1 o 2 minutos y vuelve a intentar.',
        'Si el cobro fue correcto y continúa el problema, contacta al administrador por WhatsApp con tu comprobante.'
      ];
    } else if (failureType === 'server_partial_response') {
      title = 'El servidor respondió con datos incompletos';
      summary = 'Detectamos coincidencias parciales, pero el banco no devolvió una validación completa en el mismo movimiento.';
      steps = [
        'Espera 1 o 2 minutos y vuelve a intentar la validación.',
        'Si el problema persiste, contacta al administrador por WhatsApp y envía el comprobante para revisión manual.'
      ];
    }

    if (paymentModalReasons && reasons.length) {
      paymentModalReasons.className = 'payment-reasons-card mb-3';
      paymentModalReasons.innerHTML = `
        <div class="payment-reasons-title">${escapePaymentHtml(title)}</div>
        <div class="payment-reasons-summary">${escapePaymentHtml(summary)}</div>
        <ol class="payment-reasons-steps">${steps.map((step) => `<li>${escapePaymentHtml(step)}</li>`).join('')}</ol>
        <div class="payment-reasons-caption">Detalle detectado por el sistema:</div>
        <ul>${reasons.map((reason) => `<li>${escapePaymentHtml(reason)}</li>`).join('')}</ul>
      `;
    } else if (paymentModalReasons) {
      paymentModalReasons.className = 'payment-reasons-card mb-3';
      paymentModalReasons.innerHTML = `
        <div class="payment-reasons-title">${escapePaymentHtml(title)}</div>
        <div class="payment-reasons-summary">${escapePaymentHtml(summary)}</div>
        <ol class="payment-reasons-steps">${steps.map((step) => `<li>${escapePaymentHtml(step)}</li>`).join('')}</ol>
      `;
    }

    const whatsappUrl = buildPaymentSupportWhatsappUrl(activePaymentOrder ? activePaymentOrder.orderId : '', reference, totalText);
    if (paymentModalActions && whatsappUrl) {
      paymentModalActions.className = 'payment-support-actions mb-4';
      paymentModalActions.innerHTML = `<a href="${escapePaymentHtml(whatsappUrl)}" target="_blank" rel="noopener noreferrer" class="payment-support-link">Contactar al administrador por WhatsApp</a>`;
    }
    scrollPaymentModalToTop();
  }

  function renderPaymentServerFailure(errorMessage, reference, totalText) {
    renderPaymentFailureDetails({
      failure_type: 'server_or_data_mismatch',
      reasons: [errorMessage || 'No se recibió una respuesta válida del servidor bancario.']
    }, reference, totalText);
    scrollPaymentModalToTop();
  }

  function setCancelOrderButtonMode(mode) {
    if (!paymentCancelOrderButton) {
      return;
    }
    paymentCancelOrderButton.dataset.mode = mode;
    if (mode === 'close') {
      paymentCancelOrderButton.textContent = 'Cerrar ventana';
      paymentCancelOrderButton.classList.remove('btn-danger');
      paymentCancelOrderButton.classList.add('btn-outline-light');
      return;
    }
    paymentCancelOrderButton.textContent = 'Cancelar Orden';
    paymentCancelOrderButton.classList.remove('btn-outline-light');
    paymentCancelOrderButton.classList.add('btn-danger');
  }

  function setPaymentFormDisabled(disabled) {
    [paymentMethodSelect, paymentReferenceInput, paymentPhoneInput, paymentSubmitButton].forEach((field) => {
      if (field) {
        field.disabled = disabled;
      }
    });
  }

  function renderPaymentMethodDetails(method) {
    if (!method) {
      paymentMethodTitle.textContent = 'Datos de pago';
      paymentMethodCurrency.textContent = '';
      paymentMethodDetails.innerHTML = 'No hay datos de pago disponibles.';
      paymentReferenceInput.placeholder = paymentReferencePlaceholder(null);
      paymentReferenceHelp.textContent = paymentReferenceHelpText(null);
      paymentReferenceInput.maxLength = 120;
      return;
    }

    const currencyLabel = `${method.moneda_nombre || ''}${method.moneda_clave ? ` (${method.moneda_clave})` : ''}`.trim();
    paymentMethodTitle.textContent = `Datos para ${method.nombre || 'el pago'}`;
    paymentMethodCurrency.textContent = currencyLabel;
    paymentMethodDetails.innerHTML = escapePaymentHtml(method.datos || '').replace(/\n/g, '<br>');
    const digits = Number(method.referencia_digitos || 0);
    paymentReferenceInput.placeholder = paymentReferencePlaceholder(method);
    paymentReferenceHelp.textContent = paymentReferenceHelpText(method);
    paymentReferenceInput.maxLength = digits > 0 ? digits : 120;
    paymentReferenceInput.dataset.requiredDigits = String(digits > 0 ? digits : 0);
  }

  function renderPaymentMethodsByCurrency(currencyCode) {
    const methods = getPaymentMethodsForCurrency(currencyCode);
    if (!methods.length) {
      paymentMethodSelectWrap.classList.add('d-none');
      renderPaymentMethodDetails(null);
      return null;
    }

    if (methods.length === 1) {
      paymentMethodSelectWrap.classList.add('d-none');
      paymentMethodSelect.innerHTML = `<option value="${methods[0].id}">${escapePaymentHtml(methods[0].nombre || 'Método')}</option>`;
      renderPaymentMethodDetails(methods[0]);
      return methods[0];
    }

    paymentMethodSelectWrap.classList.remove('d-none');
    paymentMethodSelect.innerHTML = methods.map((method) => `<option value="${method.id}">${escapePaymentHtml(method.nombre || 'Método')}</option>`).join('');
    renderPaymentMethodDetails(methods[0]);
    return methods[0];
  }

  function resetCheckoutState() {
    orderForm.reset();
    orderForm.email.value = defaultOrderEmail || '';
    couponInput.value = '';
    couponInput.disabled = false;
    if (applyCouponButton) {
      applyCouponButton.disabled = false;
    }
    couponApplied = false;
    couponValue = '';
    activePack = null;
    packCards2.forEach((item) => item.classList.remove('neon-selected'));
    updateResumenCompra(null);
    updateButtonState();
  }

  function closePaymentModal(resetState) {
    clearPaymentTimer();
    setOverlayVisible(paymentModal, false);
    setPaymentAlert('', 'info');
    if (resetState) {
      activePaymentOrder = null;
      paymentReferenceInput.value = '';
      paymentPhoneInput.value = '';
      clearPaymentSupportUi();
      setCancelOrderButtonMode('cancel');
    }
  }

  async function expireActiveOrder() {
    if (!activePaymentOrder || activePaymentOrder.expiring) {
      return;
    }
    activePaymentOrder.expiring = true;
    clearPaymentTimer();
    setPaymentFormDisabled(true);
    setPaymentAlert('La orden expiró. Estamos cancelando el pedido y notificando por correo.', 'danger');
    try {
      const response = await fetch('/api/pedidos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=expire_order&order_id=${encodeURIComponent(activePaymentOrder.orderId)}`
      });
      const data = await response.json();
      showToast((data && data.message) ? data.message : 'La orden expiró.', data && data.expired ? 'error' : 'info');
      setPaymentAlert((data && data.message) ? data.message : 'La orden expiró y fue cancelada automáticamente.', 'danger');
    } catch (error) {
      setPaymentAlert('La orden expiró. Si el estado no cambió todavía, vuelve a intentarlo.', 'danger');
    }
  }

  function updatePaymentTimer() {
    if (!activePaymentOrder) {
      paymentTimerValue.textContent = '30:00';
      return;
    }
    const remainingMs = activePaymentOrder.expiresAtMs - Date.now();
    if (remainingMs <= 0) {
      paymentTimerValue.textContent = '00:00';
      expireActiveOrder();
      return;
    }
    const totalSeconds = Math.floor(remainingMs / 1000);
    const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    paymentTimerValue.textContent = `${minutes}:${seconds}`;
  }

  function openPaymentModal(orderId, expiresAt, remainingSeconds, pack, userId, totalText) {
    const currentMethod = renderPaymentMethodsByCurrency(pack.moneda || '');
    if (!currentMethod) {
      showToast(`No hay métodos de pago activos para la moneda ${pack.moneda || ''}.`, 'error');
      return false;
    }

    const safeRemainingSeconds = Number.isFinite(Number(remainingSeconds)) ? Math.max(0, Number(remainingSeconds)) : 1800;

    activePaymentOrder = {
      orderId,
      expiresAtMs: Date.now() + (safeRemainingSeconds * 1000),
      expiresAt,
      currency: pack.moneda || '',
      expiring: false,
    };

    paymentSummaryUser.textContent = userId;
    paymentSummaryProduct.textContent = pack.name || 'Producto';
    paymentSummaryTotal.textContent = totalText;
    paymentReferenceInput.value = '';
    paymentPhoneInput.value = '';
    setPaymentFormDisabled(false);
    setPaymentAlert('', 'info');
    clearPaymentSupportUi();
    setCancelOrderButtonMode('cancel');
    setOverlayVisible(paymentModal, true);
    scrollPaymentModalToTop();
    clearPaymentTimer();
    updatePaymentTimer();
    paymentTimerInterval = setInterval(updatePaymentTimer, 1000);
    return true;
  }

  function updatePackPrices() {
    packCards.forEach(card => {
      const base = parseFloat(card.getAttribute('data-base'));
      const precio = normalizeCurrencyAmount(base * monedaActualTasa, monedaActualMostrarDecimales);
      card.querySelector('.precio-label').textContent = formatCurrencyAmount(precio, monedaActualMostrarDecimales);
      card.querySelector('.moneda-label').textContent = monedaActualClave;
      card.setAttribute('data-price-value', String(precio));
      card.setAttribute('data-show-decimals', monedaActualMostrarDecimales ? '1' : '0');
      card.setAttribute('data-moneda', monedaActualClave);
    });
  }
  updatePackPrices();

  function updateButtonState() {
    // Solo controlar el estado del botón, no mostrar mensajes de error aquí
    const requiredFields = Array.from(orderForm.querySelectorAll("[required]"));
    let requiredFilled = true;
    requiredFields.forEach(field => {
      if (field.value.trim() === "") {
        requiredFilled = false;
      }
    });
    if (!activePack) {
      selectedPack.style.color = "#f87171";
      selectedPack.textContent = "Debes seleccionar un paquete.";
    } else {
      selectedPack.style.color = "";
      selectedPack.textContent = activePack.name;
    }
    buyButton.disabled = !activePack || !requiredFilled;
  }
  function updateResumenCompra(pack) {
    if (pack) {
      selectedPack.textContent = pack.name;
      selectedTotalValue = normalizeCurrencyAmount(pack.priceValue, pack.showDecimals);
      selectedPrice.textContent = `${pack.moneda} ${formatCurrencyAmount(selectedTotalValue, pack.showDecimals)}`;
    } else {
      selectedTotalValue = 0;
      selectedPack.textContent = 'Ninguno';
      selectedPrice.textContent = `${monedaActualClave} ${formatCurrencyAmount(0, monedaActualMostrarDecimales)}`;
    }
  }
  packCards2.forEach((card) => {
    card.addEventListener("click", () => {
      packCards2.forEach((item) => {
        item.classList.remove("neon-selected");
      });
      card.classList.add("neon-selected");
      activePack = {
        id: card.dataset.packageId,
        name: card.dataset.name,
        priceValue: Number(card.dataset.priceValue || 0),
        moneda: card.dataset.moneda,
        cantidad: card.dataset.cantidad,
        showDecimals: card.dataset.showDecimals === '1'
      };
      updateResumenCompra(activePack);
      updateButtonState();
      scrollToOrderForm();
    });
  });
  if (packCards2.length) {
    // Ya no se selecciona automáticamente ningún paquete al cargar
  }
              if (couponInput) {
              function normalizeCouponCode(value) {
                return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
              }

              function resetCouponState() {
                couponApplied = false;
                couponValue = '';
                couponInput.disabled = false;
                if (applyCouponButton) {
                  applyCouponButton.disabled = false;
                }
              }

              if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', function() {
                  const methods = getPaymentMethodsForCurrency(activePaymentOrder ? activePaymentOrder.currency : (activePack ? activePack.moneda : ''));
                  const selectedMethod = methods.find((method) => String(method.id) === String(paymentMethodSelect.value)) || methods[0] || null;
                  renderPaymentMethodDetails(selectedMethod);
                });
              }

              if (paymentReferenceInput) {
                paymentReferenceInput.addEventListener('input', function() {
                  const digitsOnly = paymentReferenceInput.value.replace(/\D+/g, '');
                  const requiredDigits = Number(paymentReferenceInput.dataset.requiredDigits || '0');
                  paymentReferenceInput.value = requiredDigits > 0 ? digitsOnly.slice(0, requiredDigits) : digitsOnly.slice(0, 120);
                });
              }

              if (paymentCancelOrderButton) {
                paymentCancelOrderButton.addEventListener('click', function() {
                  const mode = paymentCancelOrderButton.dataset.mode || 'cancel';
                  if (mode === 'close') {
                    closePaymentModal(true);
                    resetCheckoutState();
                    return;
                  }
                  if (!activePaymentOrder) {
                    return;
                  }
                  setOverlayVisible(paymentCancelConfirmModal, true);
                });
              }

              if (paymentCancelDismissButton) {
                paymentCancelDismissButton.addEventListener('click', function() {
                  setOverlayVisible(paymentCancelConfirmModal, false);
                });
              }

              if (paymentCancelConfirmButton) {
                paymentCancelConfirmButton.addEventListener('click', function() {
                  if (!activePaymentOrder) {
                    setOverlayVisible(paymentCancelConfirmModal, false);
                    return;
                  }
                  paymentCancelConfirmButton.disabled = true;
                  fetch('/api/pedidos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=cancel_order&order_id=${encodeURIComponent(activePaymentOrder.orderId)}`
                  })
                  .then(async (response) => {
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                      throw new Error((data && data.message) ? data.message : 'No se pudo cancelar la orden.');
                    }
                    setOverlayVisible(paymentCancelConfirmModal, false);
                    showToast(data.message || 'Orden cancelada.', 'error');
                    closePaymentModal(true);
                    resetCheckoutState();
                  })
                  .catch((error) => {
                    setOverlayVisible(paymentCancelConfirmModal, false);
                    setPaymentAlert(error.message || 'No se pudo cancelar la orden.', 'danger');
                  })
                  .finally(() => {
                    paymentCancelConfirmButton.disabled = false;
                  });
                });
              }

              if (paymentSubmitButton) {
                paymentSubmitButton.addEventListener('click', function() {
                  if (!activePaymentOrder) {
                    showToast('No hay una orden pendiente para confirmar.', 'error');
                    return;
                  }

                  const methods = getPaymentMethodsForCurrency(activePaymentOrder.currency);
                  const selectedMethod = methods.find((method) => String(method.id) === String(paymentMethodSelect.value)) || methods[0] || null;
                  if (!selectedMethod) {
                    setPaymentAlert('No hay un método de pago disponible para esta orden.', 'danger');
                    return;
                  }

                  const reference = paymentReferenceInput.value.trim();
                  const phone = paymentPhoneInput.value.trim();
                  const requiredDigits = Number(selectedMethod.referencia_digitos || 0);

                  if (!reference) {
                    setPaymentAlert('Debes ingresar el número de referencia.', 'danger');
                    return;
                  }
                  if (requiredDigits > 0 && reference.length !== requiredDigits) {
                    setPaymentAlert(`La referencia debe contener exactamente ${requiredDigits} dígitos.`, 'danger');
                    return;
                  }
                  if (!phone) {
                    setPaymentAlert('Debes ingresar un número de teléfono para contactarte.', 'danger');
                    return;
                  }

                  setPaymentFormDisabled(true);
                  setPaymentAlert('', 'info');
                  setLoadingModalContent('Enviando orden...', 'Estamos registrando tu comprobante y procesando la orden según la moneda del pedido. No cierres esta ventana.');
                  setOverlayVisible(loadingModal, true);
                  fetch('/api/pedidos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: [
                      'action=submit_payment',
                      `order_id=${encodeURIComponent(activePaymentOrder.orderId)}`,
                      `payment_method_id=${encodeURIComponent(selectedMethod.id)}`,
                      `reference_number=${encodeURIComponent(reference)}`,
                      `phone=${encodeURIComponent(phone)}`
                    ].join('&')
                  })
                  .then(async (response) => {
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                      throw new Error((data && data.message) ? data.message : 'No se pudieron guardar los datos del pago.');
                    }
                    setOverlayVisible(loadingModal, false);

                    const nextState = String((data && data.estado) || '').toLowerCase();
                    if (nextState === 'enviado') {
                      const successMessage = data.message || 'La recarga fue procesada correctamente.';
                      setPaymentAlert(successMessage, 'success');
                      clearPaymentSupportUi();
                      setPaymentFormDisabled(true);
                      clearPaymentTimer();
                      setCancelOrderButtonMode('close');
                      showPaymentStatusModal('Operación exitosa', successMessage, 'success');
                      return;
                    }

                    if (nextState === 'cancelado') {
                      const cancelMessage = data.message || 'La orden fue cancelada.';
                      setPaymentAlert(cancelMessage, 'danger');
                      renderPaymentFailureDetails(data, reference, paymentSummaryTotal ? paymentSummaryTotal.textContent : '');
                      setPaymentFormDisabled(true);
                      clearPaymentTimer();
                      setCancelOrderButtonMode('close');
                      showPaymentStatusModal('No se pudo completar la operación', cancelMessage, 'danger');
                      return;
                    }

                    if (nextState === 'pagado') {
                      const paidMessage = data.message || 'El pago fue confirmado correctamente.';
                      setPaymentAlert(paidMessage, 'success');
                      clearPaymentSupportUi();
                      setPaymentFormDisabled(true);
                      clearPaymentTimer();
                      setCancelOrderButtonMode('close');
                      showPaymentStatusModal('Operación exitosa', paidMessage, 'success');
                      return;
                    }

                    if (nextState === 'pendiente' && data && data.bank_checked) {
                      const pendingMessage = data.message || 'No pudimos validar el pago automáticamente.';
                      setPaymentAlert(pendingMessage, 'danger');
                      renderPaymentFailureDetails(data, reference, paymentSummaryTotal ? paymentSummaryTotal.textContent : '');
                      setPaymentFormDisabled(false);
                      showPaymentStatusModal('Revisión requerida', pendingMessage, 'danger');
                      return;
                    }

                    closePaymentModal(true);
                    resetCheckoutState();
                  })
                  .catch((error) => {
                    setOverlayVisible(loadingModal, false);
                    const errorMessage = error.message || 'No se pudo validar el pago por respuesta del servidor.';
                    setPaymentAlert(errorMessage, 'danger');
                    renderPaymentServerFailure(errorMessage, reference, paymentSummaryTotal ? paymentSummaryTotal.textContent : '');
                    setPaymentFormDisabled(false);
                    showPaymentStatusModal('No se pudo completar la validación', errorMessage, 'danger');
                    if (activePaymentOrder && activePaymentOrder.expiresAtMs <= Date.now()) {
                      expireActiveOrder();
                    }
                  });
                });
              }

              if (monedaSelect) {
                monedaSelect.addEventListener('change', function() {
                  const selectedOption = monedaSelect.options[monedaSelect.selectedIndex];
                  monedaActualId = selectedOption.value;
                  monedaActualClave = selectedOption.dataset.clave || 'USD';
                  monedaActualTasa = parseFloat(selectedOption.dataset.tasa || '1');
                  monedaActualMostrarDecimales = Boolean(monedas[monedaActualId] && monedas[monedaActualId].mostrar_decimales);
                  updatePackPrices();

                  if (activePack) {
                    const selectedCard = packCards2.find((card) => card.classList.contains('neon-selected'));
                    if (selectedCard) {
                      activePack = {
                        id: selectedCard.dataset.packageId,
                        name: selectedCard.dataset.name,
                        priceValue: Number(selectedCard.dataset.priceValue || 0),
                        moneda: selectedCard.dataset.moneda,
                        cantidad: selectedCard.dataset.cantidad,
                        showDecimals: selectedCard.dataset.showDecimals === '1'
                      };
                      updateResumenCompra(activePack);
                    }
                  } else {
                    updateResumenCompra(null);
                  }

                  if (couponInput.value.trim() !== '') {
                    couponInput.value = '';
                  }
                  resetCouponState();
                });
              }

              couponInput.addEventListener('input', function() {
                const normalized = normalizeCouponCode(couponInput.value);
                if (couponInput.value !== normalized) {
                  couponInput.value = normalized;
                }
              });

              // Validación de cupón por AJAX
              applyCouponButton.addEventListener('click', function() {
                const cupon = normalizeCouponCode(couponInput.value);
                couponInput.value = cupon;
                const pack = activePack;
                if (!pack) {
                  showToast('Selecciona un paquete antes de aplicar el cupón.', 'error');
                  return;
                }
                // Aseguramos que el precio sea un número puro
                const precioNumerico = String(normalizeCurrencyAmount(pack.priceValue, pack.showDecimals));
                console.log('Enviando cupón:', cupon, 'Precio:', precioNumerico);
                if (!cupon) {
                  showToast('Ingresa un cupón.', 'error');
                  return;
                }
                fetch('../api/validar_cupon.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: `code=${encodeURIComponent(cupon)}&pack_price=${encodeURIComponent(precioNumerico)}&currency=${encodeURIComponent(pack.moneda || '')}`
                })
                .then(res => res.json())
                .then(data => {
                  console.log('Respuesta backend:', data);
                  if (data.success) {
                    selectedTotalValue = normalizeCurrencyAmount(data.nuevo_total, pack.showDecimals);
                    selectedPrice.textContent = `${pack.moneda} ${formatCurrencyAmount(selectedTotalValue, pack.showDecimals)}`;
                    showToast(data.message + ` Descuento: ${formatCurrencyAmount(data.descuento, pack.showDecimals)}`,'success');
                    couponInput.disabled = true;
                    applyCouponButton.disabled = true;
                    couponApplied = true;
                  } else {
                    showToast(data.message, 'error');
                  }
                })
                .catch(() => {
                  showToast('Error de red al validar cupón.', 'error');
                });
              });
              modalNo.addEventListener('click', function() {
                couponApplied = false;
                couponValue = couponInput.value.trim();
                setOverlayVisible(couponModal, false);
                showToast('Compra sin cupón aplicado', 'info');
              });
              modalCancel.addEventListener('click', function() {
                setOverlayVisible(couponModal, false);
              });
              orderForm.addEventListener('input', updateButtonState);
              orderForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const btn = document.getElementById('buy-button');
                const couponVal = normalizeCouponCode(couponInput.value);
                couponInput.value = couponVal;
                const userId = orderForm.user_id.value.trim();
                const email = orderForm.email.value.trim();
                const pack = activePack;
                if (!pack) {
                  showToast('Debes seleccionar un paquete.', 'error');
                  return;
                }
                const paymentMethods = getPaymentMethodsForCurrency(pack.moneda || '');
                if (!paymentMethods.length) {
                  showToast(`No hay métodos de pago activos para la moneda ${pack.moneda || ''}.`, 'error');
                  return;
                }
                // Validar campos obligatorios solo al intentar comprar
                const requiredFields = Array.from(orderForm.querySelectorAll('[required]'));
                let requiredFilled = true;
                requiredFields.forEach(field => {
                  const errorId = field.name + "-error";
                  let errorElem = document.getElementById(errorId);
                  if (field.value.trim() === "") {
                    requiredFilled = false;
                    if (!errorElem) {
                      errorElem = document.createElement("div");
                      errorElem.id = errorId;
                      errorElem.style.color = "#f87171";
                      errorElem.style.fontSize = "12px";
                      errorElem.textContent = "Este campo es obligatorio.";
                      field.parentNode.appendChild(errorElem);
                    }
                  } else {
                    if (errorElem) errorElem.remove();
                  }
                });
                if (!requiredFilled) {
                  return;
                }
                // Si el cupón no está aplicado y hay valor, mostrar modal
                if (couponVal && !couponApplied) {
                  setOverlayVisible(couponModal, true);
                  modalCouponName.textContent = couponVal;
                  modalYes.onclick = function() {
                    setOverlayVisible(couponModal, false);
                    document.getElementById('apply-coupon-btn').click();
                    // Esperar a que se aplique el cupón y luego enviar el formulario
                    setTimeout(() => orderForm.dispatchEvent(new Event('submit', {cancelable: true})), 150);
                  };
                  modalNo.onclick = function() {
                    setOverlayVisible(couponModal, false);
                    couponApplied = false;
                    couponInput.value = '';
                    // Enviar el formulario sin cupón (ya no se mostrará el modal)
                    setTimeout(() => orderForm.dispatchEvent(new Event('submit', {cancelable: true})), 100);
                  };
                  modalCancel.onclick = function() {
                    setOverlayVisible(couponModal, false);
                  };
                  return;
                }
                // Envío AJAX del pedido
                                // Mostrar spinner y deshabilitar botón justo antes de enviar la compra
                                var spinner = document.getElementById('spinner-compra');
                                if (!spinner) {
                                  spinner = document.createElement('span');
                                  spinner.id = 'spinner-compra';
                                  spinner.innerHTML = `<svg width="22" height="22" viewBox="0 0 50 50" style="vertical-align:middle;"><circle cx="25" cy="25" r="20" fill="none" stroke="#34d399" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4" transform="rotate(-90 25 25)"><animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/></circle></svg>`;
                                  spinner.style.marginLeft = '8px';
                                  btn.appendChild(spinner);
                                }
                                btn.disabled = true;
                // El precio mostrado SIEMPRE es el que se envía, así se evita doble descuento
                let precioFinal = selectedPrice.textContent.replace(/[^\d.]/g, '');
                // Si no hay cupón aplicado, usar el precio base del paquete
                if (!couponApplied || !couponVal) {
                  precioFinal = String(normalizeCurrencyAmount(pack.priceValue, pack.showDecimals));
                } else {
                  precioFinal = String(normalizeCurrencyAmount(selectedTotalValue, pack.showDecimals));
                }
                const pedidoData = {
                  action: 'create',
                  game_id: "<?= $game['id'] ?>",
                  package_id: pack.id || '',
                  game_name: "<?= $game['nombre'] ?>",//window.gameName,
                  pack_name: pack.name || '',
                  pack_amount: pack.cantidad || '',
                  currency: pack.moneda || '',
                  price: precioFinal,
                  pack_base: String(normalizeCurrencyAmount(pack.priceValue, pack.showDecimals)),
                  user_identifier: userId,
                  email: email,
                  coupon: couponApplied ? couponVal : '',
                };
                console.log('Datos enviados a pedidos.php:', pedidoData);
                btn.disabled = true;
                setLoadingModalContent('Procesando pedido...', 'Estamos registrando tu pedido para abrir el formulario de pago.');
                setOverlayVisible(loadingModal, true);
                fetch('/api/pedidos.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: Object.keys(pedidoData).map(k => `${encodeURIComponent(k)}=${encodeURIComponent(pedidoData[k])}`).join('&')
                })
                .then(async res => {
                  let data = null;
                  try {
                    data = await res.json();
                  } catch (e) {
                    // Si no es JSON válido pero la respuesta es 200, asumimos éxito
                    if (res.ok) {
                      showToast('Pedido registrado correctamente', 'success');
                      orderForm.reset();
                      couponInput.disabled = false;
                      applyCouponButton.disabled = false;
                      couponApplied = false;
                      selectedPack.textContent = 'Ninguno';
                      selectedPrice.textContent = `${monedaActualClave} ${formatCurrencyAmount(0, monedaActualMostrarDecimales)}`;
                      return;
                    } else {
                      showToast('Error de red al registrar pedido', 'error');
                      return;
                    }
                  }
                  if (data && data.ok) {
                    const opened = openPaymentModal(data.order_id, data.expires_at, data.remaining_seconds, pack, userId, selectedPrice.textContent);
                    if (opened) {
                      showToast('Pedido registrado. Completa ahora los datos del pago.', 'success');
                    }
                  } else {
                    showToast((data && data.message) ? data.message : 'Error al registrar pedido', 'error');
                  }
                })
                .catch(() => {
                  showToast('Error de red al registrar pedido.', 'error');
                })
                .finally(() => {
                  btn.disabled = false;
                  removeBuySpinner();
                  setOverlayVisible(loadingModal, false);
                });
              });
              }
              </script>
            </section>
<?php
include __DIR__ . "/includes/footer.php";
?>
