<?php
session_start();
if (!isset($_SESSION['auth_user']) || ($_SESSION['auth_user']['rol'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/header.php';

$statuses = ['pendiente','pagado','enviado','cancelado'];
$ordersByStatus = array_fill_keys($statuses, []);

$ordersRes = $mysqli->query("SELECT * FROM pedidos ORDER BY creado_en DESC");
if ($ordersRes) {
    while ($row = $ordersRes->fetch_assoc()) {
        $estado = $row['estado'] ?? 'pendiente';
        if (!isset($ordersByStatus[$estado])) {
            $ordersByStatus[$estado] = [];
        }
        $ordersByStatus[$estado][] = $row;
    }
}

function format_money($amount): string {
    return number_format((float)$amount, 2, '.', ',');
}

function order_meta_value($value): string {
  $text = trim((string) $value);
  return $text !== '' ? $text : '—';
}

function order_search_index(array $order): string {
  $parts = [
    '#' . ($order['id'] ?? ''),
    $order['creado_en'] ?? '',
    $order['user_identifier'] ?? '',
    $order['email'] ?? '',
    $order['numero_referencia'] ?? '',
    $order['telefono_contacto'] ?? '',
    $order['juego_nombre'] ?? '',
    $order['paquete_nombre'] ?? '',
    $order['paquete_cantidad'] ?? '',
    $order['moneda'] ?? '',
    $order['precio'] ?? '',
    $order['cupon'] ?? '',
    $order['estado'] ?? '',
  ];

  return strtolower(trim(implode(' ', array_map(static fn ($value) => trim((string) $value), $parts))));
}

function order_status_color(string $status): string {
  return match ($status) {
    'pendiente' => '#ffc107',
    'pagado' => '#00ffb3',
    'enviado' => '#2196f3',
    'cancelado' => '#ff0059',
    default => '#00fff7',
  };
}

function order_status_label(string $status): string {
  return match ($status) {
    'pendiente' => 'No Verificado',
    'pagado' => 'Verificado',
    'enviado' => 'Enviado',
    'cancelado' => 'Cancelado',
    default => ucfirst($status),
  };
}

function order_status_button_style(string $status, bool $isActive = false): string {
  $color = order_status_color($status);
  $background = $isActive ? $color : ($status === 'pendiente' ? 'rgba(255, 193, 7, 0.08)' : ($status === 'pagado' ? 'rgba(0, 255, 179, 0.08)' : ($status === 'enviado' ? 'rgba(33, 150, 243, 0.08)' : 'rgba(255, 0, 89, 0.08)')));
  $textColor = $isActive
    ? ($status === 'pendiente' ? '#181f2a' : '#ffffff')
    : $color;
  $shadow = $isActive ? '0 0 12px ' . $color . '66' : 'none';

  return 'border:1px solid ' . $color . '; background:' . $background . '; color:' . $textColor . '; box-shadow:' . $shadow . ';';
}
?>
<main class="container-lg mt-5 mb-5 px-2">
  <style>
    .hidden { display: none !important; }
    .table { background: #181f2a !important; border-radius:12px !important; border:2px solid #00fff7 !important; box-shadow:0 0 24px #00fff733 !important; }
    .tab-btn.active, .tab-btn.border-cyan-400 {
      border:2px solid #00fff7 !important;
      color:#00fff7 !important;
      box-shadow:0 0 8px #00fff7;
      background:#181f2a !important;
    }
    .tab-panel { margin-top: 0.5rem !important; }
    .admin-loading-modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1080;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      background: rgba(5, 10, 20, 0.78);
      backdrop-filter: blur(4px);
    }
    .admin-loading-modal.is-visible {
      display: flex;
    }
    .admin-loading-card {
      width: min(92vw, 25rem);
      border-radius: 18px;
      border: 2px solid #00fff7;
      background: linear-gradient(135deg, rgba(11, 17, 32, 0.97), rgba(24, 31, 42, 0.96));
      box-shadow: 0 0 24px rgba(0, 255, 247, 0.25);
      padding: 1.75rem 1.5rem;
      text-align: center;
      color: #b2f6ff;
    }
    .admin-loading-spinner {
      width: 3rem;
      height: 3rem;
      margin: 0 auto 1rem;
      border: 4px solid rgba(0, 255, 247, 0.22);
      border-top-color: #00fff7;
      border-radius: 50%;
      animation: adminSpin 0.85s linear infinite;
    }
    body.admin-loading-open {
      overflow: hidden;
    }
    @keyframes adminSpin {
      to { transform: rotate(360deg); }
    }
    .order-status-actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.6rem;
      margin-top: 1rem;
    }
    .order-status-btn {
      border-radius: 10px;
      font-weight: 700;
      padding: 0.55rem 0.75rem;
      transition: transform 0.18s ease, opacity 0.18s ease, box-shadow 0.18s ease;
    }
    .order-status-btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }
    .order-status-btn:not(:disabled):active {
      transform: scale(0.98);
    }
  </style>
  <div class="row mb-4">
    <div class="col-12 text-center">
      <p class="text-uppercase text-info mb-1">Panel</p>
      <h1 class="display-5 fw-bold text-info mb-2">Gestión de Pedidos</h1>
      <p class="text-secondary">Administra estados y revisa el histórico de compras.</p>
    </div>
  </div>

  <div class="row mb-3" id="tabs">
    <div class="col-auto d-flex flex-wrap gap-2 justify-content-center" style="margin-bottom:0.5rem;">
      <?php foreach ($statuses as $st): ?>
        <button data-tab="<?= $st ?>" class="btn btn-outline-info rounded-pill px-4 py-2 fw-semibold tab-btn" type="button">
          <?= htmlspecialchars(order_status_label($st)) ?>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="col-12 mt-3">
      <form id="date-filter-form" class="row g-2 align-items-center justify-content-center" style="margin-bottom:0.5rem;">
        <div class="col-auto">
          <label class="form-label mb-0" style="color:#00fff7;">Desde:</label>
          <input type="date" id="date-from" name="date_from" class="form-control form-control-sm" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
        </div>
        <div class="col-auto">
          <label class="form-label mb-0" style="color:#00fff7;">Hasta:</label>
          <input type="date" id="date-to" name="date_to" class="form-control form-control-sm" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
        </div>
        <div class="col-auto">
          <label class="form-label mb-0" style="color:#00fff7;">Buscar pedido:</label>
          <input type="search" id="order-search" name="order_search" class="form-control form-control-sm" placeholder="ID, cliente, email, referencia..." style="min-width:260px; background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-info btn-sm fw-bold" style="background:#00fff7; color:#222c3a; border:none; box-shadow:0 0 8px #00fff7;">Filtrar</button>
          <button type="button" id="clear-date-filter" class="btn btn-outline-info btn-sm fw-bold" style="border:1px solid #00fff7; color:#00fff7; background:#181f2a; box-shadow:0 0 8px #00fff7; display:flex; align-items:center; gap:4px;">
            <svg width="14" height="14" fill="none" viewBox="0 0 14 14"><circle cx="7" cy="7" r="6" stroke="#00fff7" stroke-width="1.2"/><path d="M4 4l6 6M10 4l-6 6" stroke="#00fff7" stroke-width="1.2" stroke-linecap="round"/></svg>
            <span style="color:#00fff7; font-weight:bold;">Limpiar</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php foreach ($statuses as $st): ?>
    <?php $list = $ordersByStatus[$st] ?? []; ?>
    <section data-panel="<?= $st ?>" class="tab-panel mt-6<?= ($st !== ($initialTab ?? 'pendiente')) ? ' hidden' : '' ?>">
      <div style="border-radius:16px; border:2px solid #00fff7; background:#181f2a; box-shadow:0 0 24px #00fff733; padding:1.5rem; margin-bottom:2rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem;">
          <div style="display:flex; align-items:center; gap:0.75rem;">
            <span style="display:inline-block; height:10px; width:10px; border-radius:50%; background:<?= order_status_color($st) ?>;"></span>
            <h2 style="font-size:1.2em; font-weight:bold; color:#00fff7;">Estado: <?= htmlspecialchars(order_status_label($st)) ?></h2>
          </div>
          <p data-total-label style="font-size:1em; color:#b2f6ff;">Total: <?= count($list) ?> pedidos</p>
        </div>

        <?php if (count($list) === 0): ?>
          <p style="margin-top:1.5rem; color:#b2f6ff; font-size:1em;">No hay pedidos en este estado.</p>
        <?php else: ?>
          <div class="table-responsive d-none d-md-block" style="margin-top:1.5rem;">
            <table class="table align-middle" style="background:#181f2a; color:#00fff7; border-radius:12px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733;">
              <thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">
                <tr>
                  <th style="color:#00fff7; background:#181f2a; min-width:150px;">Pedido / Fecha</th>
                  <th style="color:#00fff7; background:#181f2a; min-width:240px;">Cliente / Email</th>
                  <th style="color:#00fff7; background:#181f2a;">Referencia</th>
                  <th style="color:#00fff7; background:#181f2a;">Teléfono</th>
                  <th style="color:#00fff7; background:#181f2a; min-width:210px;">Juego / Paquete</th>
                  <th style="color:#00fff7; background:#181f2a;">Total</th>
                  <th style="color:#00fff7; background:#181f2a;">Cupón</th>
                  <th style="color:#00fff7; background:#181f2a;">Estado</th>
                </tr>
              </thead>
              <tbody id="table-body-<?= $st ?>">
                <?php foreach ($list as $order): ?>
                  <tr data-order-row="<?= $order['id'] ?>" data-status="<?= $st ?>" data-created-date="<?= htmlspecialchars(substr((string) ($order['creado_en'] ?? ''), 0, 10)) ?>" data-search-text="<?= htmlspecialchars(order_search_index($order)) ?>" style="background:#181f2a; color:#fff;">
                    <td style="background:#181f2a; color:#00fff7;">
                      <div style="font-weight:bold;">#<?= $order['id'] ?></div>
                      <div style="color:#b2f6ff; margin-top:0.2rem;"><?= htmlspecialchars($order['creado_en']) ?></div>
                    </td>
                    <td style="background:#181f2a; color:#00fff7;">
                      <div style="font-weight:bold;"><?= htmlspecialchars($order['user_identifier']) ?></div>
                      <div style="color:#b2f6ff; margin-top:0.2rem;"><?= htmlspecialchars($order['email']) ?></div>
                    </td>
                    <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(order_meta_value($order['numero_referencia'] ?? '')) ?></td>
                    <td style="background:#181f2a; color:#b2f6ff;"><?= htmlspecialchars(order_meta_value($order['telefono_contacto'] ?? '')) ?></td>
                    <td style="background:#181f2a; color:#00fff7;">
                      <div style="font-weight:bold;">
                        <?php
                          $juegoTexto = htmlspecialchars($order['juego_nombre']);
                          if ($juegoTexto === '' || str_contains($order['juego_nombre'], '<?')) {
                            $juegoTexto = 'Juego #' . htmlspecialchars((string)($order['juego_id'] ?? '')); }
                          echo $juegoTexto;
                        ?>
                      </div>
                      <div style="color:#b2f6ff; margin-top:0.2rem;"><?= htmlspecialchars($order['paquete_nombre'] ?? '') ?></div>
                    </td>
                    <td style="background:#181f2a; color:#00ffb3; font-weight:bold;"><?= htmlspecialchars($order['moneda'] ?? '') ?> <?= format_money($order['precio']) ?></td>
                    <td style="background:#181f2a; color:#b2f6ff;">
                      <?= !empty($order['cupon']) ? htmlspecialchars($order['cupon']) : '—' ?>
                    </td>
                    <td style="background:#181f2a;">
                      <select class="js-status" style="border-radius:8px; border:1px solid #00fff7; background:#222c3a; color:#00fff7; font-weight:bold; padding:0.25em 0.5em;" data-order-id="<?= $order['id'] ?>" data-status="<?= $st ?>">
                        <option value="" selected disabled>Cambiar estado...</option>
                        <?php foreach ($statuses as $opt): ?>
                          <?php if ($opt === $st) { continue; } ?>
                          <option value="<?= $opt ?>"><?= htmlspecialchars(order_status_label($opt)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards -->
          <div id="cards-<?= $st ?>" class="d-block d-md-none" style="margin-top:1.5rem;">
            <?php foreach ($list as $order): ?>
              <div data-order-card="<?= $order['id'] ?>" data-status="<?= $st ?>" data-created-date="<?= htmlspecialchars(substr((string) ($order['creado_en'] ?? ''), 0, 10)) ?>" data-search-text="<?= htmlspecialchars(order_search_index($order)) ?>" style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1rem; color:#00fff7; margin-bottom:1.5rem;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                  <div style="font-weight:bold; font-size:1.1em; color:#00fff7;">#<?= $order['id'] ?></div>
                  <div style="font-size:0.95em; color:#b2f6ff;"><?= htmlspecialchars($order['creado_en']) ?></div>
                </div>
                <div style="margin-top:0.5em; color:#00fff7; font-size:1em;">Cliente: <span style="color:#b2f6ff; font-weight:bold;"><?= htmlspecialchars($order['user_identifier']) ?></span></div>
                <div style="color:#b2f6ff; font-size:1em;">Email: <?= htmlspecialchars($order['email']) ?></div>
                <div style="color:#b2f6ff; font-size:1em;">Referencia: <?= htmlspecialchars(order_meta_value($order['numero_referencia'] ?? '')) ?></div>
                <div style="color:#b2f6ff; font-size:1em;">Teléfono: <?= htmlspecialchars(order_meta_value($order['telefono_contacto'] ?? '')) ?></div>
                <div style="margin-top:0.5em; color:#00fff7; font-size:1em;">Juego: <span style="color:#b2f6ff; font-weight:bold;">
                  <?php
                    $juegoTexto = htmlspecialchars($order['juego_nombre']);
                    if ($juegoTexto === '' || str_contains($order['juego_nombre'], '<?')) {
                      $juegoTexto = 'Juego #' . htmlspecialchars((string)($order['juego_id'] ?? '')); }
                    echo $juegoTexto;
                  ?>
                </span></div>
                <div style="color:#b2f6ff; font-size:1em;">Paquete: <span style="color:#00fff7; font-weight:bold;"><?= htmlspecialchars($order['paquete_nombre'] ?? '') ?></span></div>
                <div style="color:#00ffb3; font-weight:bold; margin-top:0.5em;">Total: <?= htmlspecialchars($order['moneda'] ?? '') ?> <?= format_money($order['precio']) ?></div>
                <div style="color:#b2f6ff; font-size:0.95em; margin-top:0.5em;">Cupón: <?= !empty($order['cupon']) ? htmlspecialchars($order['cupon']) : '—' ?></div>
                <div class="order-status-actions" data-order-actions="<?= $order['id'] ?>">
                  <?php foreach ($statuses as $opt): ?>
                    <button
                      type="button"
                      class="order-status-btn js-status-btn"
                      data-order-id="<?= $order['id'] ?>"
                      data-status="<?= $st ?>"
                      data-status-value="<?= $opt ?>"
                      style="<?= htmlspecialchars(order_status_button_style($opt, false)) ?>;<?= $opt === $st ? ' display:none;' : '' ?>"
                    ><?= htmlspecialchars(order_status_label($opt)) ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div id="admin-loading-modal" class="admin-loading-modal" aria-hidden="true">
    <div class="admin-loading-card">
      <div class="admin-loading-spinner" aria-hidden="true"></div>
      <h3 class="h5 fw-bold text-info mb-2">Actualizando pedido...</h3>
      <p class="mb-0">Espera mientras se procesa el cambio de estado y se envian las notificaciones.</p>
    </div>
  </div>
</main>

<script>
// Forzar ocultamiento inicial y mostrar solo el tab activo
(function(){
  // Detectar tab inicial
  var initialTab = localStorage.getItem('tvg_tab') || 'pendiente';
  window.initialTab = initialTab;
  // Filtro de rango de fecha
  const dateForm = document.getElementById('date-filter-form');
  const dateFrom = document.getElementById('date-from');
  const dateTo = document.getElementById('date-to');
  const orderSearch = document.getElementById('order-search');
  const clearBtn = document.getElementById('clear-date-filter');
  const calendarFromBtn = document.getElementById('calendar-from-btn');
  const calendarToBtn = document.getElementById('calendar-to-btn');

  // Abrir selector de fecha al hacer clic en el ícono
  if (calendarFromBtn) {
    calendarFromBtn.addEventListener('click', function(){ dateFrom.showPicker && dateFrom.showPicker(); dateFrom.focus(); });
  }
  if (calendarToBtn) {
    calendarToBtn.addEventListener('click', function(){ dateTo.showPicker && dateTo.showPicker(); dateTo.focus(); });
  }

  // Responsive: los inputs y botones ocupan todo el ancho en móvil
  function adjustDateFilterResponsive(){
    if (window.innerWidth < 600) {
      dateForm.classList.add('flex-col','items-stretch');
      dateForm.classList.remove('ml-4');
      dateForm.style.minWidth = '0';
      dateForm.parentElement.style.overflowX = 'auto';
      dateForm.parentElement.style.width = '100%';
      dateForm.querySelectorAll('input,button').forEach(el => {
        el.classList.add('w-full','mb-1');
        el.style.minWidth = '0';
      });
    } else {
      dateForm.classList.remove('flex-col','items-stretch');
      dateForm.classList.add('ml-4');
      dateForm.style.minWidth = '320px';
      dateForm.parentElement.style.overflowX = '';
      dateForm.parentElement.style.width = '';
      dateForm.querySelectorAll('input,button').forEach(el => {
        el.classList.remove('w-full','mb-1');
        el.style.minWidth = '';
      });
    }
  }
  window.addEventListener('resize', function(){
    adjustDateFilterResponsive();
    updateTabCounts();
  });
  adjustDateFilterResponsive();

  function applyFilters(){
    const from = dateFrom.value;
    const to = dateTo.value;
    const query = (orderSearch?.value || '').trim().toLowerCase();

    document.querySelectorAll('.tab-panel').forEach(panel => {
      panel.querySelectorAll('[data-order-row], [data-order-card]').forEach(item => {
        const createdDate = item.dataset.createdDate || '';
        const searchText = item.dataset.searchText || '';
        let visible = true;

        if (from && createdDate && createdDate < from) {
          visible = false;
        }
        if (to && createdDate && createdDate > to) {
          visible = false;
        }
        if (query && !searchText.includes(query)) {
          visible = false;
        }

        item.style.display = visible ? '' : 'none';
      });
    });

    updateTabCounts();
  }

  dateForm.addEventListener('submit', function(e){
    e.preventDefault();
    applyFilters();
  });

  if (orderSearch) {
    orderSearch.addEventListener('input', applyFilters);
    orderSearch.addEventListener('search', applyFilters);
  }

  clearBtn.addEventListener('click', function(){
    dateFrom.value = '';
    dateTo.value = '';
    if (orderSearch) {
      orderSearch.value = '';
    }
    applyFilters();
  });
  const tabs = Array.from(document.querySelectorAll('.tab-btn'));
  const panels = Array.from(document.querySelectorAll('.tab-panel'));
  const adminLoadingModal = document.getElementById('admin-loading-modal');
  const STATUS_ORDER = ['pendiente', 'pagado', 'enviado', 'cancelado'];
  const STATUS_LABELS = {
    pendiente: 'No Verificado',
    pagado: 'Verificado',
    enviado: 'Enviado',
    cancelado: 'Cancelado'
  };

  function setAdminLoadingVisible(visible) {
    if (!adminLoadingModal) {
      return;
    }
    adminLoadingModal.classList.toggle('is-visible', visible);
    adminLoadingModal.setAttribute('aria-hidden', visible ? 'false' : 'true');
    document.body.classList.toggle('admin-loading-open', visible);
  }

  function showTab(tab){
    panels.forEach(p => p.classList.add('hidden'));
    const activePanel = panels.find(p => p.dataset.panel === tab);
    if (activePanel) activePanel.classList.remove('hidden');
    tabs.forEach(b => b.classList.remove('active','border-cyan-400','text-cyan-200'));
    const activeTab = tabs.find(b => b.dataset.tab === tab);
    if (activeTab) {
      activeTab.classList.add('active','border-cyan-400','text-cyan-200');
    }
    localStorage.setItem('tvg_tab', tab);
  }
  const initial = localStorage.getItem('tvg_tab') || 'pendiente';
  showTab(initial);
  tabs.forEach(btn => btn.addEventListener('click', () => showTab(btn.dataset.tab)));

  function moveOrder(id, newStatus){
    const row = document.querySelector(`[data-order-row="${id}"]`);
    const card = document.querySelector(`[data-order-card="${id}"]`);
    if (row) {
      row.dataset.status = newStatus;
      const targetBody = document.getElementById('table-body-' + newStatus);
      if (targetBody) targetBody.prepend(row);
      const selectRow = row.querySelector('select');
      if (selectRow) selectRow.value = newStatus;
    }
    if (card) {
      card.dataset.status = newStatus;
      const targetCards = document.getElementById('cards-' + newStatus);
      if (targetCards) targetCards.prepend(card);
    }
  }

  function buildDesktopStatusOptions(currentStatus) {
    const placeholder = '<option value="" selected disabled>Cambiar estado...</option>';
    const options = STATUS_ORDER
      .filter(status => status !== currentStatus)
      .map(status => `<option value="${status}">${STATUS_LABELS[status] || status}</option>`)
      .join('');

    return placeholder + options;
  }

  function refreshDesktopStatusSelects(orderId, currentStatus) {
    document.querySelectorAll(`.js-status[data-order-id="${orderId}"]`).forEach(select => {
      select.dataset.status = currentStatus;
      select.innerHTML = buildDesktopStatusOptions(currentStatus);
      select.value = '';
    });
  }

  function statusColor(status) {
    switch (status) {
      case 'pendiente':
        return '#ffc107';
      case 'pagado':
        return '#00ffb3';
      case 'enviado':
        return '#2196f3';
      case 'cancelado':
        return '#ff0059';
      default:
        return '#00fff7';
    }
  }

  function applyButtonState(button, isActive) {
    const status = button.dataset.statusValue || '';
    const color = statusColor(status);
    button.style.borderColor = color;
    button.style.background = isActive
      ? color
      : (status === 'pendiente'
        ? 'rgba(255, 193, 7, 0.08)'
        : (status === 'pagado'
          ? 'rgba(0, 255, 179, 0.08)'
          : (status === 'enviado' ? 'rgba(33, 150, 243, 0.08)' : 'rgba(255, 0, 89, 0.08)')));
    button.style.color = isActive
      ? (status === 'pendiente' ? '#181f2a' : '#ffffff')
      : color;
    button.style.boxShadow = isActive ? `0 0 12px ${color}66` : 'none';
  }

  function updateCardStatusButtons(orderId, newStatus) {
    document.querySelectorAll(`.js-status-btn[data-order-id="${orderId}"]`).forEach(button => {
      button.dataset.status = newStatus;
      const isCurrent = button.dataset.statusValue === newStatus;
      button.style.display = isCurrent ? 'none' : '';
      applyButtonState(button, false);
    });
  }

  function updateTabCounts() {
    panels.forEach(panel => {
      const source = window.innerWidth >= 768
        ? Array.from(panel.querySelectorAll('[data-order-row]'))
        : Array.from(panel.querySelectorAll('[data-order-card]'));
      const count = source.filter(item => item.style.display !== 'none').length;
      const totalLabel = panel.querySelector('[data-total-label]');
      if (totalLabel) {
        totalLabel.textContent = `Total: ${count} pedidos`;
      }
    });
  }

  async function submitStatusChange(orderId, prevStatus, newStatus) {
    const fd = new FormData();
    fd.append('action','update_status');
    fd.append('order_id', orderId);
    fd.append('estado', newStatus);

    const res = await fetch('/api/pedidos.php', { method: 'POST', body: fd });
    const txt = await res.text();
    let data;
    try {
      data = JSON.parse(txt);
    } catch (_) {
      throw new Error('Respuesta no válida del servidor');
    }
    if (!data.ok) {
      throw new Error(data.message || 'Error');
    }

    moveOrder(orderId, newStatus);
    refreshDesktopStatusSelects(orderId, newStatus);
    updateCardStatusButtons(orderId, newStatus);
    updateTabCounts();
  }

  function bindStatusSelectors(){
    document.querySelectorAll('.js-status').forEach(sel => {
      sel.addEventListener('change', async () => {
        const orderId = sel.dataset.orderId;
        const prevStatus = sel.dataset.status;
        const newStatus = sel.value;
        if (!newStatus) {
          return;
        }
        sel.disabled = true;
        setAdminLoadingVisible(true);
        try {
          await submitStatusChange(orderId, prevStatus, newStatus);
          sel.dataset.status = newStatus;
        } catch(err){
          refreshDesktopStatusSelects(orderId, prevStatus);
          alert(err.message || 'No se pudo cambiar el estado');
        } finally {
          sel.disabled = false;
          setAdminLoadingVisible(false);
        }
      });
    });

    document.querySelectorAll('.js-status-btn').forEach(button => {
      applyButtonState(button, button.dataset.statusValue === button.dataset.status);
      button.addEventListener('click', async () => {
        const orderId = button.dataset.orderId;
        const prevStatus = button.dataset.status;
        const newStatus = button.dataset.statusValue;
        if (!orderId || !newStatus || prevStatus === newStatus) {
          return;
        }

        const relatedButtons = document.querySelectorAll(`.js-status-btn[data-order-id="${orderId}"]`);
        relatedButtons.forEach(item => { item.disabled = true; });
        setAdminLoadingVisible(true);
        try {
          await submitStatusChange(orderId, prevStatus, newStatus);
        } catch (err) {
          updateCardStatusButtons(orderId, prevStatus);
          alert(err.message || 'No se pudo cambiar el estado');
        } finally {
          relatedButtons.forEach(item => { item.disabled = false; });
          setAdminLoadingVisible(false);
        }
      });
    });
  }
  updateTabCounts();
  bindStatusSelectors();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
