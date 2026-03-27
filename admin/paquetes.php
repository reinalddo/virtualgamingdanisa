<?php
// admin/paquetes.php - Gestión de paquetes de un juego

require_once '../includes/db_connect.php';
require_once '../includes/recargas_api.php';

function admin_packages_is_ajax_request(): bool {
    if (isset($_REQUEST['ajax']) && (string) $_REQUEST['ajax'] === '1') {
        return true;
    }

    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));

    return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function admin_packages_json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_juego_paquetes_monto_ff_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'monto_ff'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN monto_ff VARCHAR(20) NULL AFTER clave");
    }
}

function ensure_juego_paquetes_activo_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'activo'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN activo TINYINT(1) DEFAULT 1 NULL AFTER imagen_icono");
    }
}

function ensure_juego_paquetes_paquete_api_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'paquete_api'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN paquete_api INT NULL AFTER monto_ff");
    }
}

function ensure_juego_paquetes_orden_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'orden'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN orden INT NULL AFTER activo");
    }
}

function admin_package_next_order(mysqli $mysqli, int $juegoId): int {
    $stmt = $mysqli->prepare("SELECT COALESCE(MAX(orden), 0) + 1 AS next_order FROM juego_paquetes WHERE juego_id = ?");
    $stmt->bind_param('i', $juegoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return max(1, (int) ($row['next_order'] ?? 1));
}

function free_fire_api_amount_options(): array {
    return [
        '1' => ['suggested_name' => 'FF_110', 'diamonds' => '110 diamantes'],
        '2' => ['suggested_name' => 'FF_341', 'diamonds' => '341 diamantes'],
        '3' => ['suggested_name' => 'FF_572', 'diamonds' => '572 diamantes'],
        '4' => ['suggested_name' => 'FF_1166', 'diamonds' => '1166 diamantes'],
        '5' => ['suggested_name' => 'FF_2376', 'diamonds' => '2376 diamantes'],
        '6' => ['suggested_name' => 'FF_6138', 'diamonds' => '6138 diamantes'],
    ];
}

function free_fire_api_amount_label(string $amount): string {
    $options = free_fire_api_amount_options();
    if (!isset($options[$amount])) {
        return $amount;
    }

    $option = $options[$amount];
    return $amount . ' - ' . $option['suggested_name'] . ' - ' . $option['diamonds'];
}

ensure_juego_paquetes_monto_ff_column($mysqli);
ensure_juego_paquetes_activo_column($mysqli);
ensure_juego_paquetes_paquete_api_column($mysqli);
ensure_juego_paquetes_orden_column($mysqli);

$juego_id = 0;
if (isset($_GET['juego'])) {
    $juego_id = intval($_GET['juego']);
} elseif (isset($_SERVER['REQUEST_URI'])) {
    // Soporta /admin/paquetes/2
    if (preg_match('#/admin/paquetes/(\\d+)#', $_SERVER['REQUEST_URI'], $m)) {
        $juego_id = intval($m[1]);
    }
}
if ($juego_id <= 0) { die('Juego no especificado.'); }

$juego = [];
$res_juego = $mysqli->prepare("SELECT * FROM juegos WHERE id=?");
$res_juego->bind_param('i', $juego_id);
$res_juego->execute();
$juego = $res_juego->get_result()->fetch_assoc();
$freeFireApiOptions = free_fire_api_amount_options();
$juegoCategoriaApi = trim((string) ($juego['categoria_api'] ?? ''));
$usesApiCatalog = $juegoCategoriaApi !== '';
$usesLegacyFreeFire = !$usesApiCatalog && !empty($juego['api_free_fire']);
$apiProducts = [];
$apiProductsById = [];
$apiProductsError = null;

if ($usesApiCatalog) {
    try {
        $apiProducts = recargas_api_fetch_products_by_category($juegoCategoriaApi);
        foreach ($apiProducts as $apiProduct) {
            $apiProductsById[(int) ($apiProduct['id'] ?? 0)] = $apiProduct;
        }
    } catch (Throwable $e) {
        $apiProductsError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_paquete_activo'], $_POST['paquete_id'], $_POST['activo'])) {
    $packageId = intval($_POST['paquete_id']);
    $activeValue = intval($_POST['activo']) === 1 ? 1 : 0;
    if ($packageId > 0) {
        $stmtToggle = $mysqli->prepare("UPDATE juego_paquetes SET activo = ? WHERE id = ? AND juego_id = ?");
        $stmtToggle->bind_param('iii', $activeValue, $packageId, $juego_id);
        $stmtToggle->execute();
        $stmtToggle->close();
        if (admin_packages_is_ajax_request()) {
            admin_packages_json_response(['ok' => true, 'id' => $packageId, 'activo' => $activeValue]);
        }
    }
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

if (isset($_GET['toggle_activo'])) {
    $toggleId = intval($_GET['toggle_activo']);
    if ($toggleId > 0) {
        $stmtToggle = $mysqli->prepare("UPDATE juego_paquetes SET activo = IF(COALESCE(activo, 1) = 1, 0, 1) WHERE id = ? AND juego_id = ?");
        $stmtToggle->bind_param('ii', $toggleId, $juego_id);
        $stmtToggle->execute();
        $stmtToggle->close();
        if (admin_packages_is_ajax_request()) {
            admin_packages_json_response(['ok' => true, 'id' => $toggleId]);
        }
    }
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_orden_paquete'], $_POST['paquete_id'], $_POST['orden'])) {
    $packageId = intval($_POST['paquete_id']);
    $order = max(1, intval($_POST['orden']));
    if ($packageId > 0) {
        $stmtOrder = $mysqli->prepare("UPDATE juego_paquetes SET orden = ? WHERE id = ? AND juego_id = ?");
        $stmtOrder->bind_param('iii', $order, $packageId, $juego_id);
        $stmtOrder->execute();
        $stmtOrder->close();
        if (admin_packages_is_ajax_request()) {
            admin_packages_json_response(['ok' => true, 'id' => $packageId, 'orden' => $order]);
        }
    }
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Procesar eliminación de paquete (antes de cualquier salida)
if (isset($_GET['eliminar'])) {
    $del_id = intval($_GET['eliminar']);
    // Obtener la ruta de la imagen antes de borrar
    $stmt_img = $mysqli->prepare("SELECT imagen_icono FROM juego_paquetes WHERE id=? AND juego_id=?");
    $stmt_img->bind_param('ii', $del_id, $juego_id);
    $stmt_img->execute();
    $stmt_img->bind_result($img_path);
    $stmt_img->fetch();
    $stmt_img->close();
    // Borrar el registro
    $stmt = $mysqli->prepare("DELETE FROM juego_paquetes WHERE id=? AND juego_id=?");
    $stmt->bind_param('ii', $del_id, $juego_id);
    $stmt->execute();
    // Borrar la imagen física si existe y no está vacía
    if ($img_path && file_exists('../' . $img_path)) {
        unlink('../' . $img_path);
    }
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Procesar edición de paquete (antes de cualquier salida)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_paquete_id'])) {
    $edit_id = intval($_POST['edit_paquete_id']);
    $edit_nombre = trim($_POST['edit_nombre'] ?? '');
    $edit_clave = trim($_POST['edit_clave'] ?? '');
    $edit_monto_ff = $usesLegacyFreeFire ? trim((string) ($_POST['edit_monto_ff'] ?? '')) : '';
    $edit_paquete_api = $usesApiCatalog ? trim((string) ($_POST['edit_paquete_api'] ?? '')) : '';
    $edit_cantidad = intval($_POST['edit_cantidad'] ?? 0);
    $edit_precio = floatval($_POST['edit_precio'] ?? 0);
    $edit_activo = isset($_POST['edit_activo']) ? 1 : 0;
    $edit_imagen_icono = null;
    if (isset($_FILES['edit_imagen_icono']) && $_FILES['edit_imagen_icono']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['edit_imagen_icono']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/paquetes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('paquete_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['edit_imagen_icono']['tmp_name'], $destino)) {
                $edit_imagen_icono = 'assets/img/paquetes/' . $nombre_archivo;
            }
        }
    }
    if ($edit_imagen_icono) {
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, monto_ff=NULLIF(?, ''), paquete_api=NULLIF(?, ''), cantidad=?, precio=?, imagen_icono=?, activo=? WHERE id=?");
        $stmt->bind_param('ssssidsii', $edit_nombre, $edit_clave, $edit_monto_ff, $edit_paquete_api, $edit_cantidad, $edit_precio, $edit_imagen_icono, $edit_activo, $edit_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, monto_ff=NULLIF(?, ''), paquete_api=NULLIF(?, ''), cantidad=?, precio=?, activo=? WHERE id=?");
        $stmt->bind_param('ssssidii', $edit_nombre, $edit_clave, $edit_monto_ff, $edit_paquete_api, $edit_cantidad, $edit_precio, $edit_activo, $edit_id);
    }
    $stmt->execute();
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Procesar creación de paquete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['clave'], $_POST['cantidad'], $_POST['precio'])) {
    $nombre = trim($_POST['nombre']);
    $clave = trim($_POST['clave']);
    $monto_ff = $usesLegacyFreeFire ? trim((string) ($_POST['monto_ff'] ?? '')) : '';
    $paquete_api = $usesApiCatalog ? trim((string) ($_POST['paquete_api'] ?? '')) : '';
    $cantidad = intval($_POST['cantidad']);
    $precio = floatval($_POST['precio']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $orden = admin_package_next_order($mysqli, $juego_id);
    $imagen_icono = null;
    if (isset($_FILES['imagen_icono']) && $_FILES['imagen_icono']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen_icono']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/paquetes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('paquete_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['imagen_icono']['tmp_name'], $destino)) {
                $imagen_icono = 'assets/img/paquetes/' . $nombre_archivo;
            }
        }
    }
    $stmt = $mysqli->prepare("INSERT INTO juego_paquetes (juego_id, nombre, clave, monto_ff, paquete_api, cantidad, precio, imagen_icono, activo, orden) VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssidsii', $juego_id, $nombre, $clave, $monto_ff, $paquete_api, $cantidad, $precio, $imagen_icono, $activo, $orden);
    $stmt->execute();
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Listar paquetes
$res = $mysqli->prepare("SELECT * FROM juego_paquetes WHERE juego_id=? ORDER BY CASE WHEN orden IS NULL THEN 1 ELSE 0 END, orden ASC, id ASC");
$res->bind_param('i', $juego_id);
$res->execute();
$result = $res->get_result();
$paquetes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Incluir header
include '../includes/header.php';
?>
<main class="container py-4">
    <h2 class="mb-4 text-neon">Paquetes de <?= htmlspecialchars($juego['nombre'] ?? 'Juego') ?></h2>
    <form method="post" enctype="multipart/form-data" class="row g-3 mb-4" style="background:#181f2a; border-radius:16px; border:2px solid #22d3ee; box-shadow:0 0 24px #22d3ee33; padding:2rem;">
        <div class="col-md-6">
            <label class="form-label text-neon">Nombre del paquete</label>
            <input type="text" name="nombre" placeholder="Nombre del paquete" required class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
        </div>
        <div class="col-md-6">
            <label class="form-label text-neon">Clave interna</label>
            <input type="text" name="clave" placeholder="Clave" required class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
        </div>
        <?php if ($usesApiCatalog): ?>
            <div class="col-md-6">
                <label class="form-label text-neon">Producto API</label>
                <select name="paquete_api" required class="form-select" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
                    <option value="">Selecciona un producto API</option>
                    <?php foreach ($apiProducts as $apiProduct): ?>
                        <option value="<?= (int) ($apiProduct['id'] ?? 0) ?>"><?= htmlspecialchars(recargas_api_product_label($apiProduct), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text mt-2" style="color:#8be9fd;">Categoria API vinculada: <?= htmlspecialchars($juegoCategoriaApi, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php elseif ($usesLegacyFreeFire): ?>
            <div class="col-md-6">
                <label class="form-label text-neon">Montos (API)</label>
                <select name="monto_ff" required class="form-select" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
                    <option value="">Selecciona un monto API</option>
                    <?php foreach ($freeFireApiOptions as $amount => $option): ?>
                        <option value="<?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?>">&#128142; <?= htmlspecialchars($option['suggested_name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($option['diamonds'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-4" style="display:none;">
            <label class="form-label text-neon">Cantidad</label>
            <input type="number" name="cantidad_visible" min="0" placeholder="Cantidad" class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;" value="1">
        </div>
        <input type="hidden" name="cantidad" value="1">
        <div class="col-md-4">
            <label class="form-label text-neon">Precio USD</label>
            <input type="number" step="0.01" min="0" name="precio" placeholder="Precio" required class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
        </div>
        <div class="col-md-4">
            <label class="form-label text-neon">Icono del paquete</label>
            <input type="file" name="imagen_icono" accept="image/*" class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;" onchange="previewNuevoPaqueteImg(event)">
        </div>
        <div class="col-12">
            <div class="form-check mt-2">
                <input type="checkbox" name="activo" class="form-check-input" id="paqueteActivoCheck" checked>
                <label class="form-check-label text-neon" for="paqueteActivoCheck">Paquete activo / publicado</label>
            </div>
        </div>
        <div class="col-12 text-center">
            <img id="preview-nuevo-paquete-img" src="#" alt="Previsualización" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;border:2px solid #22d3ee;background:#222c3a;" />
        </div>
        <div class="col-12">
            <button type="submit" class="btn neon-btn-info w-100">Agregar paquete</button>
        </div>
    </form>
    <?php if ($usesApiCatalog && $apiProductsError !== null): ?>
        <div class="alert alert-warning mb-4">No se pudieron cargar los productos de la categoria API: <?= htmlspecialchars($apiProductsError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($usesApiCatalog && empty($apiProducts)): ?>
        <div class="alert alert-warning mb-4">No hay productos disponibles en la API para la categoria <?= htmlspecialchars($juegoCategoriaApi, ENT_QUOTES, 'UTF-8') ?>.</div>
    <?php endif; ?>
    <div class="table-responsive d-none d-md-block">
        <table class="table table-dark table-bordered align-middle" style="border:2px solid #22d3ee;">
            <thead>
                <tr>
                    <th style="color:#22d3ee; background:#181f2a;">Icono</th>
                    <th style="color:#22d3ee; background:#181f2a;">Nombre</th>
                    <th style="color:#22d3ee; background:#181f2a;">Clave</th>
                    <th style="color:#22d3ee; background:#181f2a;">Orden</th>
                    <?php if ($usesApiCatalog): ?>
                        <th style="color:#22d3ee; background:#181f2a;">Producto API</th>
                    <?php elseif ($usesLegacyFreeFire): ?>
                        <th style="color:#22d3ee; background:#181f2a;">Monto FF</th>
                    <?php endif; ?>
                    <th style="color:#22d3ee; background:#181f2a;">Activo</th>
                    <th style="color:#22d3ee; background:#181f2a;">Precio</th>
                    <th style="color:#22d3ee; background:#181f2a;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paquetes as $p): ?>
                <tr style="background:#181f2a; color:#fff;">
                    <td style="background:#181f2a;">
                        <?php if (!empty($p['imagen_icono'])): ?>
                            <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded img-thumbnail" style="max-height:48px;max-width:48px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php elseif (!empty($juego['imagen_paquete'])): ?>
                            <img src="/<?= htmlspecialchars($juego['imagen_paquete']) ?>" alt="icono" class="rounded img-thumbnail" style="max-height:48px;max-width:48px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold text-neon" style="background:#181f2a; color:#22d3ee;"><?= htmlspecialchars($p['nombre']) ?></td>
                    <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars($p['clave']) ?></td>
                    <td class="text-center" style="background:#181f2a;">
                        <form method="post" action="/admin/paquetes/<?= $juego_id ?>" class="d-inline-flex align-items-center gap-2 m-0 js-ajax-order-form">
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="update_orden_paquete" value="1">
                            <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                            <input type="number" name="orden" min="1" value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" class="form-control form-control-sm text-center js-ajax-order-input" style="width:84px;background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;" data-last-value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" onchange="window.adminPackageOrderChange(this)">
                        </form>
                    </td>
                    <?php if ($usesApiCatalog): ?>
                        <?php $apiProductId = (int) ($p['paquete_api'] ?? 0); ?>
                        <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars($apiProductId > 0 && isset($apiProductsById[$apiProductId]) ? recargas_api_product_label($apiProductsById[$apiProductId]) : ($apiProductId > 0 ? 'ID ' . $apiProductId : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php elseif ($usesLegacyFreeFire): ?>
                        <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars(!empty($p['monto_ff']) ? free_fire_api_amount_label((string) $p['monto_ff']) : '—') ?></td>
                    <?php endif; ?>
                    <td class="text-center" style="background:#181f2a;">
                        <form method="post" action="/admin/paquetes/<?= $juego_id ?>" class="m-0 d-inline-block js-ajax-toggle-form">
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="toggle_paquete_activo" value="1">
                            <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="activo" value="<?= !isset($p['activo']) || !empty($p['activo']) ? '1' : '0' ?>" class="js-ajax-toggle-value">
                            <div class="form-check form-switch d-inline-flex justify-content-center mb-0">
                                <input class="form-check-input js-ajax-toggle-input" type="checkbox" <?= !isset($p['activo']) || !empty($p['activo']) ? 'checked' : '' ?> aria-label="Activar o desactivar paquete <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>" onchange="window.adminPackageToggle(this)">
                            </div>
                        </form>
                    </td>
                    <td class="text-neon" style="background:#181f2a; color:#22d3ee;">$<?= number_format($p['precio'], 2) ?></td>
                    <td style="background:#181f2a;" class="text-nowrap">
                        <a href="/admin/paquetes/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="btn neon-btn-info btn-sm me-2">Editar</a>
                        <a href="/admin/paquetes/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Cards móvil -->
    <div class="d-md-none">
        <div class="row gy-4">
            <?php foreach ($paquetes as $p): ?>
            <div class="col-12">
                <div class="card neon-card p-3" style="background:#181f2a; border:2px solid #22d3ee; box-shadow:0 0 16px #22d3ee,0 0 4px #2dd4bf; color:#22d3ee;">
                    <div class="d-flex align-items-center mb-2">
                        <?php if (!empty($p['imagen_icono'])): ?>
                            <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded img-thumbnail me-3" style="max-height:56px;max-width:56px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php elseif (!empty($juego['imagen_paquete'])): ?>
                            <img src="/<?= htmlspecialchars($juego['imagen_paquete']) ?>" alt="icono" class="rounded img-thumbnail me-3" style="max-height:56px;max-width:56px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold text-neon" style="font-size:1.1rem; color:#22d3ee;"><?= htmlspecialchars($p['nombre']) ?></div>
                            <div class="small" style="font-size:0.85rem; color:#b2f6ff;">Orden: <?= max(1, (int) ($p['orden'] ?? 0)) ?></div>
                            <div class="text-muted" style="font-size:0.85rem; color:#b2f6ff;">ID: <?= $p['id'] ?></div>
                            <div class="mt-2">
                                <form method="post" action="/admin/paquetes/<?= $juego_id ?>" class="m-0 d-inline-flex align-items-center gap-2 js-ajax-toggle-form">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="toggle_paquete_activo" value="1">
                                    <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="activo" value="<?= !isset($p['activo']) || !empty($p['activo']) ? '1' : '0' ?>" class="js-ajax-toggle-value">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input js-ajax-toggle-input" type="checkbox" <?= !isset($p['activo']) || !empty($p['activo']) ? 'checked' : '' ?> aria-label="Activar o desactivar paquete <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>" onchange="window.adminPackageToggle(this)">
                                    </div>
                                    <span style="color:#b2f6ff;font-size:0.85rem;" class="js-ajax-toggle-label"><?= !isset($p['activo']) || !empty($p['activo']) ? 'Activo' : 'Inactivo' ?></span>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div style="color:#fff;"><span class="fw-semibold">Clave:</span> <?= htmlspecialchars($p['clave']) ?></div>
                    <?php if ($usesApiCatalog): ?>
                        <?php $apiProductId = (int) ($p['paquete_api'] ?? 0); ?>
                        <div style="color:#fff;"><span class="fw-semibold">Producto API:</span> <?= htmlspecialchars($apiProductId > 0 && isset($apiProductsById[$apiProductId]) ? recargas_api_product_label($apiProductsById[$apiProductId]) : ($apiProductId > 0 ? 'ID ' . $apiProductId : '—'), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif ($usesLegacyFreeFire): ?>
                        <div style="color:#fff;"><span class="fw-semibold">Monto FF:</span> <?= htmlspecialchars(!empty($p['monto_ff']) ? free_fire_api_amount_label((string) $p['monto_ff']) : '—') ?></div>
                    <?php endif; ?>
                    <div class="text-neon" style="color:#22d3ee;"><span class="fw-semibold">Precio:</span> $<?= number_format($p['precio'], 2) ?></div>
                    <form method="post" action="/admin/paquetes/<?= $juego_id ?>" class="mt-3 d-flex align-items-center gap-2 flex-wrap js-ajax-order-form">
                        <input type="hidden" name="ajax" value="1">
                        <input type="hidden" name="update_orden_paquete" value="1">
                        <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                        <label class="small" style="color:#b2f6ff;">Orden</label>
                        <input type="number" name="orden" min="1" value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" class="form-control form-control-sm js-ajax-order-input" style="width:96px;background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;" data-last-value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" onchange="window.adminPackageOrderChange(this)">
                    </form>
                    <div class="mt-3 d-flex gap-2">
                        <a href="/admin/paquetes/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="btn neon-btn-info btn-sm flex-fill">Editar</a>
                        <a href="/admin/paquetes/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="btn btn-danger btn-sm flex-fill" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <a href="/admin/juegos" class="inline-block mt-4 text-neon">&larr; Volver a juegos</a>
</main>


<?php
// Modal edición de paquete
if (isset($_GET['editar'])) {
    $edit_id = intval($_GET['editar']);
    $res_edit = $mysqli->prepare("SELECT * FROM juego_paquetes WHERE id=? AND juego_id=?");
    $res_edit->bind_param('ii', $edit_id, $juego_id);
    $res_edit->execute();
    $paq_edit = $res_edit->get_result()->fetch_assoc();
    if ($paq_edit):
?>
<div class="fixed-top w-100 h-100 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,0.7);z-index:1050;">
    <form method="post" enctype="multipart/form-data" class="bg-dark neon-card p-4 rounded-4 position-relative" style="max-width:500px;width:100%;box-shadow:0 0 2rem #22d3ee33;">
        <h3 class="text-neon mb-3">Editar paquete</h3>
        <input type="hidden" name="edit_paquete_id" value="<?= $paq_edit['id'] ?>">
        <div class="mb-3">
            <label class="form-label text-neon">Nombre</label>
            <input type="text" name="edit_nombre" value="<?= htmlspecialchars($paq_edit['nombre']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <div class="mb-3">
            <label class="form-label text-neon">Clave interna</label>
            <input type="text" name="edit_clave" value="<?= htmlspecialchars($paq_edit['clave']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <?php if ($usesApiCatalog): ?>
            <div class="mb-3">
                <label class="form-label text-neon">Producto API</label>
                <select name="edit_paquete_api" required class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
                    <option value="">Selecciona un producto API</option>
                    <?php foreach ($apiProducts as $apiProduct): ?>
                        <option value="<?= (int) ($apiProduct['id'] ?? 0) ?>" <?= (int) ($paq_edit['paquete_api'] ?? 0) === (int) ($apiProduct['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars(recargas_api_product_label($apiProduct), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text mt-2" style="color:#8be9fd;">Categoria API vinculada: <?= htmlspecialchars($juegoCategoriaApi, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php elseif ($usesLegacyFreeFire): ?>
            <div class="mb-3">
                <label class="form-label text-neon">Montos (API)</label>
                <select name="edit_monto_ff" required class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
                    <option value="">Selecciona un monto API</option>
                    <?php foreach ($freeFireApiOptions as $amount => $option): ?>
                        <option value="<?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($paq_edit['monto_ff'] ?? '') === (string) $amount ? 'selected' : '' ?>>&#128142; <?= htmlspecialchars($option['suggested_name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($option['diamonds'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label text-neon">Cantidad</label>
            <input type="number" name="edit_cantidad" value="<?= htmlspecialchars($paq_edit['cantidad']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <div class="mb-3">
            <label class="form-label text-neon">Precio USD</label>
            <input type="number" step="0.01" name="edit_precio" value="<?= htmlspecialchars($paq_edit['precio']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="edit_activo" class="form-check-input" id="editPaqueteActivoCheck" <?= !isset($paq_edit['activo']) || !empty($paq_edit['activo']) ? 'checked' : '' ?>>
            <label class="form-check-label text-neon" for="editPaqueteActivoCheck">Paquete activo / publicado</label>
        </div>
        <div class="mb-3">
            <label class="form-label text-neon">Icono actual:</label><br>
            <?php if ($paq_edit['imagen_icono']): ?>
                <img src="/<?= htmlspecialchars($paq_edit['imagen_icono']) ?>" alt="Icono actual" class="mb-2 rounded" style="max-width:80px;max-height:80px;border:2px solid #22d3ee;background:#222c3a;box-shadow:0 0 8px #22d3ee;">
            <?php endif; ?>
            <input type="file" name="edit_imagen_icono" accept="image/*" class="form-control mt-2" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;" onchange="previewEditPaqueteImg(event)">
            <div class="text-center my-2">
                <img id="preview-edit-paquete-img" src="#" alt="Previsualización" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
            </div>
        </div>
        <button type="submit" name="edit_paquete_submit" class="btn neon-btn-info w-100 mt-3">Guardar cambios</button>
        <a href="/admin/paquetes/<?= $juego_id ?>" class="position-absolute top-0 end-0 m-3 text-neon fs-3" style="text-decoration:none;">&times;</a>
    </form>
</div>
<script>
function previewNuevoPaqueteImg(event) {
    const input = event.target;
    const img = document.getElementById('preview-nuevo-paquete-img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        img.src = '#';
        img.style.display = 'none';
    }
}

function previewEditPaqueteImg(event) {
        const input = event.target;
        const img = document.getElementById('preview-edit-paquete-img');
        if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
        } else {
                img.src = '#';
                img.style.display = 'none';
        }
}

async function submitAjaxAdminForm(form, requestData = null) {
    const method = (form.method || 'POST').toUpperCase();
    const formData = requestData instanceof FormData ? requestData : new FormData(form);
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json, text/plain, */*'
    };
    let response;
    if (method === 'GET') {
        const params = new URLSearchParams(formData);
        const separator = (form.action || window.location.href).includes('?') ? '&' : '?';
        response = await fetch((form.action || window.location.href) + separator + params.toString(), {
            method,
            headers,
            cache: 'no-store'
        });
    } else {
        response = await fetch(form.action || window.location.href, {
            method,
            headers,
            body: formData
        });
    }
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload || payload.ok !== true) {
        throw new Error(payload && payload.message ? payload.message : 'No se pudo guardar el cambio.');
    }
    return payload;
}

window.adminPackageToggle = async function(input) {
    if (!input || input.dataset.busy === '1' || !input.form) {
        return;
    }

    const form = input.form;
    const valueInput = form.querySelector('.js-ajax-toggle-value');
    const label = form.querySelector('.js-ajax-toggle-label');

    if (valueInput) {
        valueInput.value = input.checked ? '1' : '0';
    }

    const requestData = new FormData(form);
    input.dataset.busy = '1';
    input.disabled = true;

    try {
        const payload = await submitAjaxAdminForm(form, requestData);
        input.checked = String(payload.activo || 0) === '1';
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        if (label) {
            label.textContent = input.checked ? 'Activo' : 'Inactivo';
        }
    } catch (error) {
        input.checked = !input.checked;
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        window.alert(error.message);
    } finally {
        input.disabled = false;
        input.dataset.busy = '0';
    }
};

window.adminPackageOrderChange = async function(input) {
    if (!input || !input.form) {
        return;
    }

    const form = input.form;
    const normalized = String(Math.max(1, parseInt(input.value || '1', 10) || 1));
    const lastValue = String(input.dataset.lastValue || input.defaultValue || '1');
    if (normalized === lastValue) {
        input.value = normalized;
        return;
    }

    input.value = normalized;
    const requestData = new FormData(form);
    input.readOnly = true;

    try {
        const payload = await submitAjaxAdminForm(form, requestData);
        input.dataset.lastValue = String(payload.orden || normalized);
        input.value = input.dataset.lastValue;
    } catch (error) {
        input.value = lastValue;
        window.alert(error.message);
    } finally {
        input.readOnly = false;
    }
};
</script>
<?php endif; }
?>

<script>
if (typeof window.previewNuevoPaqueteImg !== 'function') {
    window.previewNuevoPaqueteImg = function(event) {
        const input = event.target;
        const img = document.getElementById('preview-nuevo-paquete-img');
        if (!img) {
            return;
        }

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            img.src = '#';
            img.style.display = 'none';
        }
    };
}

if (typeof window.previewEditPaqueteImg !== 'function') {
    window.previewEditPaqueteImg = function(event) {
        const input = event.target;
        const img = document.getElementById('preview-edit-paquete-img');
        if (!img) {
            return;
        }

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            img.src = '#';
            img.style.display = 'none';
        }
    };
}

if (typeof window.submitAjaxAdminForm !== 'function') {
    window.submitAjaxAdminForm = async function(form, requestData = null) {
        const method = (form.method || 'POST').toUpperCase();
        const formData = requestData instanceof FormData ? requestData : new FormData(form);
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json, text/plain, */*'
        };

        let response;
        if (method === 'GET') {
            const params = new URLSearchParams(formData);
            const separator = (form.action || window.location.href).includes('?') ? '&' : '?';
            response = await fetch((form.action || window.location.href) + separator + params.toString(), {
                method,
                headers,
                cache: 'no-store'
            });
        } else {
            response = await fetch(form.action || window.location.href, {
                method,
                headers,
                body: formData
            });
        }

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(payload && payload.message ? payload.message : 'No se pudo guardar el cambio.');
        }

        return payload;
    };
}

window.adminPackageToggle = async function(input) {
    if (!input || input.dataset.busy === '1' || !input.form) {
        return;
    }

    const form = input.form;
    const valueInput = form.querySelector('.js-ajax-toggle-value');
    const label = form.querySelector('.js-ajax-toggle-label');

    if (valueInput) {
        valueInput.value = input.checked ? '1' : '0';
    }

    const requestData = new FormData(form);
    input.dataset.busy = '1';
    input.disabled = true;

    try {
        const payload = await window.submitAjaxAdminForm(form, requestData);
        input.checked = String(payload.activo || 0) === '1';
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        if (label) {
            label.textContent = input.checked ? 'Activo' : 'Inactivo';
        }
    } catch (error) {
        input.checked = !input.checked;
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        window.alert(error.message);
    } finally {
        input.disabled = false;
        input.dataset.busy = '0';
    }
};

window.adminPackageOrderChange = async function(input) {
    if (!input || !input.form) {
        return;
    }

    const form = input.form;
    const normalized = String(Math.max(1, parseInt(input.value || '1', 10) || 1));
    const lastValue = String(input.dataset.lastValue || input.defaultValue || '1');
    if (normalized === lastValue) {
        input.value = normalized;
        return;
    }

    input.value = normalized;
    const requestData = new FormData(form);
    input.readOnly = true;

    try {
        const payload = await window.submitAjaxAdminForm(form, requestData);
        input.dataset.lastValue = String(payload.orden || normalized);
        input.value = input.dataset.lastValue;
    } catch (error) {
        input.value = lastValue;
        window.alert(error.message);
    } finally {
        input.readOnly = false;
    }
};
</script>

<?php include '../includes/footer.php'; ?>