
<?php
require_once __DIR__ . "/includes/db_connect.php";
require_once __DIR__ . "/includes/currency.php";
currency_ensure_schema();
$pageTitle = "TVirtualGaming | Juegos";
include __DIR__ . "/includes/header.php";

$res = $mysqli->query("SELECT * FROM juegos ORDER BY id DESC");
$allGames = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>


<section class="mt-6">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-xs uppercase tracking-[0.35em] text-cyan-300/70">catálogo</p>
      <h2 class="game-section-title">Todos los juegos</h2>
    </div>
    <a href="/populares" class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Ver populares</a>
  </div>
  <div class="mt-4 row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3">
    <?php foreach ($allGames as $game): ?>
      <?php
        $resPaqCount = $mysqli->query("SELECT COUNT(*) as total FROM juego_paquetes WHERE juego_id=" . intval($game['id']));
        $paqCount = $resPaqCount ? $resPaqCount->fetch_assoc()['total'] : 0;
        if ($paqCount == 0) continue;
      ?>
      <div class="col">
        <a href="/juego/<?= urlencode($game['id']) ?>" class="d-block rounded-4 border bg-dark p-2 h-100 text-decoration-none">
          <div class="position-relative overflow-hidden rounded-3" style="aspect-ratio:1/1;">
            <img src="/<?= htmlspecialchars($game['imagen'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="img-fluid w-100 h-100 object-fit-cover" style="aspect-ratio:1/1;" />
            <?php if (!empty($game['popular'])): ?>
              <span title="Popular" class="position-absolute top-0 end-0 text-success fs-4" style="text-shadow:0 0 4px #000;">★</span>
            <?php endif; ?>
          </div>
          <div class="mt-2">
            <p class="fw-semibold d-flex align-items-center mb-1" style="font-size:1rem;">
              <?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="small text-secondary mb-0">
              <?php if (!empty($game['imagen_paquete'])): ?>
                <img src="/<?= htmlspecialchars($game['imagen_paquete'], ENT_QUOTES, 'UTF-8') ?>" alt="Paquete" class="img-fluid rounded me-1 align-middle" style="height:20px;width:20px;display:inline-block;" />
              <?php endif; ?>
              <?php
                $min_precio_bs = null;
                if (!empty($game['moneda_fija_id'])) {
                  $resPaq = $mysqli->query("SELECT precio FROM juego_paquetes WHERE juego_id=" . intval($game['id']) . " ORDER BY precio ASC LIMIT 1");
                  $paq = $resPaq ? $resPaq->fetch_assoc() : null;
                  if ($paq) {
                    $resMon = $mysqli->query("SELECT tasa, clave, mostrar_decimales FROM monedas WHERE id=" . intval($game['moneda_fija_id']) . " LIMIT 1");
                    $mon = $resMon ? $resMon->fetch_assoc() : null;
                    if ($mon) {
                      $min_precio_bs = currency_convert_from_base((float) $paq['precio'], $mon);
                    }
                  }
                }
              ?>
              <?php if ($min_precio_bs !== null && isset($mon['clave'])): ?>
                Desde <span class="text-info"><?= htmlspecialchars(strtoupper($mon['clave'])) ?> <?= currency_format_amount((float) $min_precio_bs, $mon) ?></span>
              <?php endif; ?>
            </p>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . "/includes/footer.php"; ?>
