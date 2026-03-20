<?php
// admin/paquetes.php - Gestión de paquetes de un juego

require_once '../includes/db_connect.php';

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
$usesFreeFireApi = !empty($juego['api_free_fire']);

if (isset($_GET['toggle_activo'])) {
    $toggleId = intval($_GET['toggle_activo']);
    if ($toggleId > 0) {
        $stmtToggle = $mysqli->prepare("UPDATE juego_paquetes SET activo = IF(COALESCE(activo, 1) = 1, 0, 1) WHERE id = ? AND juego_id = ?");
        $stmtToggle->bind_param('ii', $toggleId, $juego_id);
        $stmtToggle->execute();
        $stmtToggle->close();
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
    $edit_monto_ff = $usesFreeFireApi ? trim((string) ($_POST['edit_monto_ff'] ?? '')) : null;
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
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, monto_ff=?, cantidad=?, precio=?, imagen_icono=?, activo=? WHERE id=?");
        $stmt->bind_param('sssidsii', $edit_nombre, $edit_clave, $edit_monto_ff, $edit_cantidad, $edit_precio, $edit_imagen_icono, $edit_activo, $edit_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, monto_ff=?, cantidad=?, precio=?, activo=? WHERE id=?");
        $stmt->bind_param('sssidii', $edit_nombre, $edit_clave, $edit_monto_ff, $edit_cantidad, $edit_precio, $edit_activo, $edit_id);
    }
    $stmt->execute();
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Procesar creación de paquete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['clave'], $_POST['cantidad'], $_POST['precio'])) {
    $nombre = trim($_POST['nombre']);
    $clave = trim($_POST['clave']);
    $monto_ff = $usesFreeFireApi ? trim((string) ($_POST['monto_ff'] ?? '')) : null;
    $cantidad = intval($_POST['cantidad']);
    $precio = floatval($_POST['precio']);
    $activo = isset($_POST['activo']) ? 1 : 0;
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
    $stmt = $mysqli->prepare("INSERT INTO juego_paquetes (juego_id, nombre, clave, monto_ff, cantidad, precio, imagen_icono, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssidsi', $juego_id, $nombre, $clave, $monto_ff, $cantidad, $precio, $imagen_icono, $activo);
    $stmt->execute();
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Listar paquetes
$res = $mysqli->prepare("SELECT * FROM juego_paquetes WHERE juego_id=?");
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
        <?php if ($usesFreeFireApi): ?>
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
    <div class="table-responsive d-none d-md-block">
        <table class="table table-dark table-bordered align-middle" style="border:2px solid #22d3ee;">
            <thead>
                <tr>
                    <th style="color:#22d3ee; background:#181f2a;">Icono</th>
                    <th style="color:#22d3ee; background:#181f2a;">Nombre</th>
                    <th style="color:#22d3ee; background:#181f2a;">Clave</th>
                    <?php if ($usesFreeFireApi): ?>
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
                    <?php if ($usesFreeFireApi): ?>
                        <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars(!empty($p['monto_ff']) ? free_fire_api_amount_label((string) $p['monto_ff']) : '—') ?></td>
                    <?php endif; ?>
                    <td class="text-center" style="background:#181f2a;">
                        <form method="get" action="/admin/paquetes/<?= $juego_id ?>" class="m-0 d-inline-block">
                            <input type="hidden" name="toggle_activo" value="<?= (int) $p['id'] ?>">
                            <div class="form-check form-switch d-inline-flex justify-content-center mb-0">
                                <input class="form-check-input" type="checkbox" <?= !isset($p['activo']) || !empty($p['activo']) ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Activar o desactivar paquete <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>">
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
                            <div class="text-muted" style="font-size:0.85rem; color:#b2f6ff;">ID: <?= $p['id'] ?></div>
                            <div class="mt-2">
                                <form method="get" action="/admin/paquetes/<?= $juego_id ?>" class="m-0 d-inline-flex align-items-center gap-2">
                                    <input type="hidden" name="toggle_activo" value="<?= (int) $p['id'] ?>">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" <?= !isset($p['activo']) || !empty($p['activo']) ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Activar o desactivar paquete <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <span style="color:#b2f6ff;font-size:0.85rem;"><?= !isset($p['activo']) || !empty($p['activo']) ? 'Activo' : 'Inactivo' ?></span>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div style="color:#fff;"><span class="fw-semibold">Clave:</span> <?= htmlspecialchars($p['clave']) ?></div>
                    <?php if ($usesFreeFireApi): ?>
                        <div style="color:#fff;"><span class="fw-semibold">Monto FF:</span> <?= htmlspecialchars(!empty($p['monto_ff']) ? free_fire_api_amount_label((string) $p['monto_ff']) : '—') ?></div>
                    <?php endif; ?>
                    <div class="text-neon" style="color:#22d3ee;"><span class="fw-semibold">Precio:</span> $<?= number_format($p['precio'], 2) ?></div>
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
        <?php if ($usesFreeFireApi): ?>
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
</script>
<?php endif; }
?>

<?php include '../includes/footer.php'; ?>