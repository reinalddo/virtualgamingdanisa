<?php
// admin/juegos.php - Gestión de juegos y características
require_once '../includes/db_connect.php';

function ensure_juegos_api_free_fire_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juegos LIKE 'api_free_fire'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juegos ADD COLUMN api_free_fire TINYINT(1) NOT NULL DEFAULT 0 AFTER popular");
    }
}

function ensure_juegos_activo_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juegos LIKE 'activo'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juegos ADD COLUMN activo TINYINT(1) DEFAULT 1 NULL AFTER api_free_fire");
    }
}

ensure_juegos_api_free_fire_column($mysqli);
ensure_juegos_activo_column($mysqli);

if (isset($_GET['toggle_activo'])) {
    $toggleId = intval($_GET['toggle_activo']);
    if ($toggleId > 0) {
        $stmtToggle = $mysqli->prepare("UPDATE juegos SET activo = IF(COALESCE(activo, 1) = 1, 0, 1) WHERE id = ?");
        $stmtToggle->bind_param('i', $toggleId);
        $stmtToggle->execute();
        $stmtToggle->close();
    }
    header('Location: /admin/juegos');
    exit;
}

// Procesar eliminación de juego (antes de cualquier salida)
if (isset($_GET['eliminar'])) {
    $del_id = intval($_GET['eliminar']);
    // Eliminar imágenes de paquetes asociados
    $stmt_paq = $mysqli->prepare("SELECT imagen_icono FROM juego_paquetes WHERE juego_id=?");
    $stmt_paq->bind_param('i', $del_id);
    $stmt_paq->execute();
    $res_paq = $stmt_paq->get_result();
    while ($row = $res_paq->fetch_assoc()) {
        if ($row['imagen_icono'] && file_exists('../' . $row['imagen_icono'])) {
            unlink('../' . $row['imagen_icono']);
        }
    }
    $stmt_paq->close();
    // Eliminar paquetes
    $stmt = $mysqli->prepare("DELETE FROM juego_paquetes WHERE juego_id=?");
    $stmt->bind_param('i', $del_id);
    $stmt->execute();
    // Eliminar características
    $stmt = $mysqli->prepare("DELETE FROM juego_caracteristicas WHERE juego_id=?");
    $stmt->bind_param('i', $del_id);
    $stmt->execute();
    // Eliminar imagen del juego
    $stmt_img = $mysqli->prepare("SELECT imagen FROM juegos WHERE id=?");
    $stmt_img->bind_param('i', $del_id);
    $stmt_img->execute();
    $stmt_img->bind_result($img_juego);
    $stmt_img->fetch();
    $stmt_img->close();
    if ($img_juego && file_exists('../' . $img_juego)) {
        unlink('../' . $img_juego);
    }
    // Eliminar el juego
    $stmt = $mysqli->prepare("DELETE FROM juegos WHERE id=?");
    $stmt->bind_param('i', $del_id);
    $stmt->execute();
    header('Location: /admin/juegos');
    exit;
}
// Procesar edición de cabecera de juego (antes de cualquier salida)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_juego_submit'], $_POST['edit_juego_id'], $_POST['edit_nombre'], $_POST['edit_descripcion'])) {
    $edit_id = intval($_POST['edit_juego_id']);
    $edit_nombre = trim($_POST['edit_nombre']);
    $edit_descripcion = trim($_POST['edit_descripcion']);
    $edit_popular = isset($_POST['edit_popular']) ? 1 : 0;
    $edit_api_free_fire = isset($_POST['edit_api_free_fire']) ? 1 : 0;
    $edit_activo = isset($_POST['edit_activo']) ? 1 : 0;
    $edit_moneda_fija_id = isset($_POST['edit_moneda_fija_id']) && $_POST['edit_moneda_fija_id'] !== '' ? intval($_POST['edit_moneda_fija_id']) : null;
    $edit_imagen = null;
    $edit_imagen_paquete = null;
    if (isset($_FILES['edit_imagen']) && $_FILES['edit_imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['edit_imagen']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/juegos/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('juego_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['edit_imagen']['tmp_name'], $destino)) {
                $edit_imagen = 'assets/img/juegos/' . $nombre_archivo;
            }
        }
    }
    if (isset($_FILES['edit_imagen_paquete']) && $_FILES['edit_imagen_paquete']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['edit_imagen_paquete']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/juegos/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('juegopaq_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['edit_imagen_paquete']['tmp_name'], $destino)) {
                $edit_imagen_paquete = 'assets/img/juegos/' . $nombre_archivo;
            }
        }
    }
    if ($edit_imagen && $edit_imagen_paquete) {
        $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=?, imagen=?, imagen_paquete=?, popular=?, api_free_fire=?, activo=?, moneda_fija_id=? WHERE id=?");
        $stmt->bind_param('ssssiiiii', $edit_nombre, $edit_descripcion, $edit_imagen, $edit_imagen_paquete, $edit_popular, $edit_api_free_fire, $edit_activo, $edit_moneda_fija_id, $edit_id);
    } elseif ($edit_imagen) {
        $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=?, imagen=?, popular=?, api_free_fire=?, activo=?, moneda_fija_id=? WHERE id=?");
        $stmt->bind_param('sssiiiii', $edit_nombre, $edit_descripcion, $edit_imagen, $edit_popular, $edit_api_free_fire, $edit_activo, $edit_moneda_fija_id, $edit_id);
    } elseif ($edit_imagen_paquete) {
        $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=?, imagen_paquete=?, popular=?, api_free_fire=?, activo=?, moneda_fija_id=? WHERE id=?");
        $stmt->bind_param('sssiiiii', $edit_nombre, $edit_descripcion, $edit_imagen_paquete, $edit_popular, $edit_api_free_fire, $edit_activo, $edit_moneda_fija_id, $edit_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=?, popular=?, api_free_fire=?, activo=?, moneda_fija_id=? WHERE id=?");
        $stmt->bind_param('ssiiiii', $edit_nombre, $edit_descripcion, $edit_popular, $edit_api_free_fire, $edit_activo, $edit_moneda_fija_id, $edit_id);
    }
    $stmt->execute();
    header('Location: /admin/juegos');
    exit;
}

// Procesar creación de juego y características
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['descripcion'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $moneda_fija_id = !empty($_POST['moneda_fija_id']) ? intval($_POST['moneda_fija_id']) : null;
    $popular = isset($_POST['popular']) ? 1 : 0;
    $api_free_fire = isset($_POST['api_free_fire']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $imagen = null;
    $imagen_paquete = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/juegos/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('juego_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $imagen = 'assets/img/juegos/' . $nombre_archivo;
            }
        }
    }
    if (isset($_FILES['imagen_paquete']) && $_FILES['imagen_paquete']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen_paquete']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/juegos/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('juegopaq_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['imagen_paquete']['tmp_name'], $destino)) {
                $imagen_paquete = 'assets/img/juegos/' . $nombre_archivo;
            }
        }
    }
    $stmt = $mysqli->prepare("INSERT INTO juegos (nombre, imagen, imagen_paquete, descripcion, moneda_fija_id, popular, api_free_fire, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssiiii', $nombre, $imagen, $imagen_paquete, $descripcion, $moneda_fija_id, $popular, $api_free_fire, $activo);
    $stmt->execute();
    $juego_id = $mysqli->insert_id;
    // Características seleccionadas del select múltiple
    if (!empty($_POST['caracteristicas_select'])) {
        foreach ($_POST['caracteristicas_select'] as $car) {
            $car = trim($car);
            if ($car !== '') {
                $stmt2 = $mysqli->prepare("INSERT INTO juego_caracteristicas (juego_id, caracteristica) VALUES (?, ?)");
                $stmt2->bind_param('is', $juego_id, $car);
                $stmt2->execute();
            }
        }
    }
    // Características nuevas escritas
    if (!empty($_POST['caracteristicas'])) {
        foreach ($_POST['caracteristicas'] as $car) {
            $car = trim($car);
            if ($car !== '') {
                $stmt2 = $mysqli->prepare("INSERT INTO juego_caracteristicas (juego_id, caracteristica) VALUES (?, ?)");
                $stmt2->bind_param('is', $juego_id, $car);
                $stmt2->execute();
            }
        }
    }
    header('Location: /admin/juegos');
    exit;
}

// Listar monedas para el select
$resm = $mysqli->query("SELECT * FROM monedas ORDER BY nombre ASC");
$monedas = $resm->fetch_all(MYSQLI_ASSOC);
// Listar características únicas
$rescar = $mysqli->query("SELECT DISTINCT caracteristica FROM juego_caracteristicas ORDER BY caracteristica ASC");
$caracteristicas_unicas = [];
while ($row = $rescar->fetch_assoc()) {
    $caracteristicas_unicas[] = $row['caracteristica'];
}
// Listar juegos existentes
$resj = $mysqli->query("SELECT * FROM juegos ORDER BY id DESC");
$juegos = $resj->fetch_all(MYSQLI_ASSOC);
$paquetesPorJuego = [];
$resPaquetes = $mysqli->query("SELECT juego_id, COUNT(*) AS total FROM juego_paquetes GROUP BY juego_id");
if ($resPaquetes instanceof mysqli_result) {
    while ($row = $resPaquetes->fetch_assoc()) {
        $paquetesPorJuego[(int) $row['juego_id']] = (int) $row['total'];
    }
}
?>
<?php include '../includes/header.php'; ?>
<main class="container-lg mt-5 bg-dark bg-opacity-75 rounded-4 p-4 shadow">
    <?php
    // Modal edición de juego
    if (isset($_GET['editar'])) {
            $edit_id = intval($_GET['editar']);
            $res_edit = $mysqli->prepare("SELECT * FROM juegos WHERE id=?");
            $res_edit->bind_param('i', $edit_id);
            $res_edit->execute();
            $juego_edit = $res_edit->get_result()->fetch_assoc();
            if ($juego_edit):
    ?>
    <div class="fixed-top w-100 h-100 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,0.7);z-index:1050;">
        <form method="post" enctype="multipart/form-data" class="bg-dark neon-card p-4 rounded-4 position-relative" style="max-width:600px;width:100%;box-shadow:0 0 2rem #00fff733;">
            <h3 class="text-neon mb-3">Editar juego</h3>
            <input type="hidden" name="edit_juego_id" value="<?= $juego_edit['id'] ?>">
            <div class="mb-3">
                <label class="form-label text-neon">Nombre</label>
                <input type="text" name="edit_nombre" value="<?= htmlspecialchars($juego_edit['nombre']) ?>" required class="form-control" style="background:#222c3a;color:#00fff7;border:1px solid #00fff7;">
            </div>
            <div class="mb-3">
                <label class="form-label text-neon">Descripción</label>
                <textarea name="edit_descripcion" required class="form-control" style="background:#222c3a;color:#00fff7;border:1px solid #00fff7;"><?= htmlspecialchars($juego_edit['descripcion']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label text-neon">Moneda fija o variable</label>
                <select name="edit_moneda_fija_id" class="form-select" style="background:#222c3a;color:#00fff7;border:1px solid #00fff7;">
                    <option value="" <?= ($juego_edit['moneda_fija_id'] === null || $juego_edit['moneda_fija_id'] === '' || $juego_edit['moneda_fija_id'] == 0) ? 'selected' : '' ?>>Moneda variable (usuario elige)</option>
                    <?php foreach ($monedas as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($juego_edit['moneda_fija_id'] == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="edit_popular" class="form-check-input" id="editPopularCheck" <?= !empty($juego_edit['popular']) ? 'checked' : '' ?>>
                <label class="form-check-label text-neon" for="editPopularCheck">Marcar como popular</label>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="edit_api_free_fire" class="form-check-input" id="editApiFreeFireCheck" <?= !empty($juego_edit['api_free_fire']) ? 'checked' : '' ?>>
                <label class="form-check-label text-neon" for="editApiFreeFireCheck">Este Juego usa API Free Fire</label>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="edit_activo" class="form-check-input" id="editActivoCheck" <?= !isset($juego_edit['activo']) || !empty($juego_edit['activo']) ? 'checked' : '' ?>>
                <label class="form-check-label text-neon" for="editActivoCheck">Juego activo / publicado</label>
            </div>
            <div class="mb-3">
                <label class="form-label text-neon">Imagen actual:</label><br>
                <?php if ($juego_edit['imagen']): ?>
                    <img src="/<?= htmlspecialchars($juego_edit['imagen']) ?>" alt="Imagen actual" class="mb-2 rounded" style="max-width:120px;max-height:120px;border:2px solid #00fff7;background:#222c3a;box-shadow:0 0 8px #00fff7;">
                <?php endif; ?>
                <input type="file" name="edit_imagen" accept="image/*" class="form-control mt-2" style="background:#222c3a;color:#00fff7;border:1px solid #00fff7;">
            </div>
            <div class="mb-3">
                <label class="form-label text-neon">Imagen común para paquetes:</label><br>
                <?php if ($juego_edit['imagen_paquete']): ?>
                    <img src="/<?= htmlspecialchars($juego_edit['imagen_paquete']) ?>" alt="Imagen paquete actual" class="mb-2 rounded" style="max-width:80px;max-height:80px;border:2px solid #00fff7;background:#222c3a;box-shadow:0 0 8px #00fff7;">
                <?php endif; ?>
                <input type="file" name="edit_imagen_paquete" accept="image/*" class="form-control mt-2" style="background:#222c3a;color:#00fff7;border:1px solid #00fff7;">
            </div>
            <button type="submit" name="edit_juego_submit" class="btn neon-btn-info w-100 mt-3">Guardar cambios</button>
            <a href="/admin/juegos" class="position-absolute top-0 end-0 m-3 text-neon fs-3" style="text-decoration:none;">&times;</a>
        </form>
    </div>
    <?php endif; }
    ?>
    <h2 class="text-center mb-4" style="color:#00fff7;">Gestión de Juegos</h2>
    <form method="post" enctype="multipart/form-data" class="row g-3 mb-4" style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:2rem;">
        <div class="col-md-6">
            <label class="form-label" style="color:#00fff7;">Nombre del juego</label>
            <input type="text" name="nombre" placeholder="Nombre del juego" required class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
        </div>
        <div class="col-md-6">
            <label class="form-label" style="color:#00fff7;">Marcar como popular</label>
            <div class="form-check">
                <input type="checkbox" name="popular" class="form-check-input" id="popularCheck">
                <label class="form-check-label" for="popularCheck" style="color:#00fff7;">Popular</label>
            </div>
            <div class="form-check mt-3">
                <input type="checkbox" name="api_free_fire" class="form-check-input" id="apiFreeFireCheck">
                <label class="form-check-label" for="apiFreeFireCheck" style="color:#00fff7;">Este Juego usa API Free Fire</label>
            </div>
            <div class="form-check mt-3">
                <input type="checkbox" name="activo" class="form-check-input" id="activoCheck" checked>
                <label class="form-check-label" for="activoCheck" style="color:#00fff7;">Publicar este juego ahora</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" style="color:#00fff7;">Descripción</label>
            <textarea name="descripcion" placeholder="Descripción" required class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" style="color:#00fff7;">Imagen del juego</label>
            <input type="file" name="imagen" accept="image/*" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;" onchange="previewImagenJuego(event)">
            <div class="text-center mt-2">
                <img id="preview-juego-img" src="#" alt="Previsualización" style="display:none;max-width:180px;max-height:180px;border-radius:0.75rem;box-shadow:0 0 0.5rem #00fff7; border:2px solid #00fff7;" />
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label" style="color:#00fff7;">Imagen común para paquetes</label>
            <input type="file" name="imagen_paquete" accept="image/*" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;" onchange="previewImagenPaqueteJuego(event)">
            <div class="text-center mt-2">
                <img id="preview-juego-img-paquete" src="#" alt="Previsualización Paquete" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #00fff7; border:2px solid #00fff7;" />
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label" style="color:#00fff7;">Moneda fija</label>
            <select name="moneda_fija_id" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                <option value="">Moneda variable (usuario elige)</option>
                <?php foreach ($monedas as $m): ?>
                <option value="<?= $m['id'] ?>">Solo <?= htmlspecialchars($m['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" style="color:#00fff7;">Seleccionar características existentes</label>
            <select name="caracteristicas_select[]" multiple class="form-select" size="3" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
                <?php foreach ($caracteristicas_unicas as $car): ?>
                    <option value="<?= htmlspecialchars($car) ?>" style="background:#222c3a; color:#00fff7;"><?= htmlspecialchars($car) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" style="color:#00fff7;">Nuevas características</label>
            <div id="caracteristicas" class="mb-2">
                <input type="text" name="caracteristicas[]" placeholder="Nueva característica" class="form-control mb-2" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">
            </div>
            <button type="button" onclick="addCarField()" class="btn btn-outline-info btn-sm" style="border-color:#00fff7; color:#00fff7;">Agregar nueva característica</button>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-info w-100" style="background:#00fff7; color:#222; border:none; box-shadow:0 0 8px #00fff7;">Agregar juego</button>
        </div>
    </form>
    <h3 class="text-info mt-5 mb-3">Juegos existentes</h3>
    <div class="table-responsive d-none d-md-block">
        <table class="table align-middle" style="background:#181f2a; color:#00fff7; border-radius:12px;">
            <thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">
                <tr>
                    <th style="color:#00fff7; background:#181f2a;">Imagen</th>
                    <th style="color:#00fff7; background:#181f2a;">Nombre</th>
                    <th style="color:#00fff7; background:#181f2a;">Popular</th>
                    <th style="color:#00fff7; background:#181f2a;">Activo</th>
                    <th style="color:#00fff7; background:#181f2a;">Imagen Paquete</th>
                    <th style="color:#00fff7; background:#181f2a;">Descripción</th>
                    <th style="color:#00fff7; background:#181f2a;">Moneda</th>
                    <th style="color:#00fff7; background:#181f2a;">Características</th>
                    <th style="color:#00fff7; background:#181f2a;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($juegos as $j): ?>
                <?php $totalPaquetes = $paquetesPorJuego[(int) $j['id']] ?? 0; ?>
                <tr style="background:#181f2a; color:#fff;">
                    <td style="background:#181f2a;">
                        <?php if (!empty($j['imagen'])): ?>
                            <img src="/<?= htmlspecialchars($j['imagen']) ?>" alt="img" class="rounded img-thumbnail" style="max-height:64px;max-width:64px; border:2px solid #00fff7; background:#222c3a;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                    <td style="background:#181f2a; color:#00fff7;">
                        <div class="fw-semibold"><?= htmlspecialchars($j['nombre']) ?></div>
                        <div class="small" style="color:#b2f6ff;"><?= $totalPaquetes ?> paquete<?= $totalPaquetes === 1 ? '' : 's' ?> registrado<?= $totalPaquetes === 1 ? '' : 's' ?></div>
                        <?php if (!empty($j['api_free_fire'])): ?>
                            <div class="small mt-1"><span style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.2rem 0.55rem;border-radius:999px;border:1px solid rgba(52,211,153,0.7);background:rgba(16,185,129,0.12);color:#6ee7b7;font-weight:700;letter-spacing:0.04em;">API Free Fire</span></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="background:#181f2a;">
                        <?php if (!empty($j['popular'])): ?>
                                <span title="Popular" style="color:#00fff7; font-size:1.2em;">★</span>
                            <?php else: ?>
                                <span style="color:#444;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center" style="background:#181f2a;">
                            <form method="get" action="/admin/juegos" class="m-0 d-inline-block">
                                <input type="hidden" name="toggle_activo" value="<?= (int) $j['id'] ?>">
                                <div class="form-check form-switch d-inline-flex justify-content-center mb-0">
                                    <input class="form-check-input" type="checkbox" <?= !isset($j['activo']) || !empty($j['activo']) ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Activar o desactivar juego <?= htmlspecialchars($j['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </form>
                        </td>
                        <td style="background:#181f2a;">
                            <?php if (!empty($j['imagen_paquete'])): ?>
                                <img src="/<?= htmlspecialchars($j['imagen_paquete']) ?>" alt="imgpaq" class="rounded-lg" style="max-height:48px;max-width:48px; border:2px solid #00fff7; background:#222c3a;">
                            <?php else: ?>
                                <span class="italic text-slate-400">Sin imagen</span>
                            <?php endif; ?>
                        </td>
                        <td style="background:#181f2a; color:#fff; max-width:220px;overflow-x:auto;white-space:pre-line;"><?= nl2br(htmlspecialchars($j['descripcion'])) ?></td>
                        <td style="background:#181f2a; color:#00fff7;">
                            <?php 
                                if (!empty($j['moneda_fija_id'])) {
                                    $mon = $mysqli->query("SELECT nombre FROM monedas WHERE id=" . intval($j['moneda_fija_id']));
                                    $moneda = $mon && $mon->num_rows ? $mon->fetch_assoc()['nombre'] : 'Desconocida';
                                    echo htmlspecialchars($moneda);
                                } else {
                                    echo '<span class="italic text-slate-400">Variable</span>';
                                }
                            ?>
                        </td>
                        <td style="background:#181f2a; color:#00fff7;">
                            <?php 
                                $carRes = $mysqli->query("SELECT caracteristica FROM juego_caracteristicas WHERE juego_id=" . intval($j['id']));
                                $cars = [];
                                while ($row = $carRes->fetch_assoc()) $cars[] = $row['caracteristica'];
                                echo $cars ? htmlspecialchars(implode(', ', $cars)) : '<span class="italic text-slate-400">Ninguna</span>';
                            ?>
                        </td>
                        <td style="background:#181f2a;">
                            <a href="/admin/juegos?editar=<?= $j['id'] ?>" style="color:#00fff7; text-decoration:underline; margin-right:1em;">Editar</a>
                            <a href="/admin/paquetes/<?= $j['id'] ?>" style="color:#00fff7; text-decoration:underline; margin-right:1em;">Paquetes</a>
                            <a href="/admin/juegos?eliminar=<?= $j['id'] ?>" style="color:#ff0059; text-decoration:underline;" onclick="return confirm('¿Eliminar este juego y todos sus paquetes/características?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <!-- Mobile Cards -->
    <div class="d-md-none">
        <div class="row gy-4 mt-1 mb-2">
        <?php foreach ($juegos as $j): ?>
            <?php $totalPaquetes = $paquetesPorJuego[(int) $j['id']] ?? 0; ?>
            <div class="col-12">
                <div class="card neon-card p-3" style="background:#181f2a; border:2px solid #22d3ee; box-shadow:0 0 16px #22d3ee,0 0 4px #2dd4bf; color:#22d3ee; border-radius:16px;">
                    <div class="d-flex align-items-center mb-3">
                        <?php if (!empty($j['imagen'])): ?>
                            <img src="/<?= htmlspecialchars($j['imagen']) ?>" alt="img" class="rounded img-thumbnail me-3" style="max-height:120px;max-width:120px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a; object-fit:cover;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold text-neon" style="font-size:1.1rem; color:#22d3ee;">
                                <?= htmlspecialchars($j['nombre']) ?>
                                <?php if (!empty($j['popular'])): ?>
                                    <span title="Popular" style="margin-left:0.35rem; color:#22d3ee; font-size:1.1rem;">★</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.9rem; color:#b2f6ff;"><?= $totalPaquetes ?> paquete<?= $totalPaquetes === 1 ? '' : 's' ?> registrado<?= $totalPaquetes === 1 ? '' : 's' ?></div>
                            <?php if (!empty($j['api_free_fire'])): ?>
                                <div class="mt-1"><span style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.2rem 0.55rem;border-radius:999px;border:1px solid rgba(52,211,153,0.7);background:rgba(16,185,129,0.12);color:#6ee7b7;font-weight:700;font-size:0.78rem;letter-spacing:0.04em;">API Free Fire</span></div>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size:0.85rem; color:#b2f6ff;">ID: <?= $j['id'] ?></div>
                            <div class="mt-2">
                                <form method="get" action="/admin/juegos" class="m-0 d-inline-flex align-items-center gap-2">
                                    <input type="hidden" name="toggle_activo" value="<?= (int) $j['id'] ?>">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" <?= !isset($j['activo']) || !empty($j['activo']) ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Activar o desactivar juego <?= htmlspecialchars($j['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <span style="color:#b2f6ff;font-size:0.85rem;"><?= !isset($j['activo']) || !empty($j['activo']) ? 'Activo' : 'Inactivo' ?></span>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div style="color:#fff;"><span class="fw-semibold">Descripción:</span> <?= nl2br(htmlspecialchars($j['descripcion'])) ?></div>
                    <div class="mt-2" style="color:#fff;"><span class="fw-semibold">Moneda:</span> <?php 
                        if (!empty($j['moneda_fija_id'])) {
                            $mon = $mysqli->query("SELECT nombre FROM monedas WHERE id=" . intval($j['moneda_fija_id']));
                            $moneda = $mon && $mon->num_rows ? $mon->fetch_assoc()['nombre'] : 'Desconocida';
                            echo '<span style="color:#b2f6ff;">' . htmlspecialchars($moneda) . '</span>';
                        } else {
                            echo '<span class="fst-italic" style="color:#b2f6ff;">Variable</span>';
                        }
                    ?></div>
                    <div class="mt-2" style="color:#fff;"><span class="fw-semibold">Características:</span> <?php 
                        $carRes = $mysqli->query("SELECT caracteristica FROM juego_caracteristicas WHERE juego_id=" . intval($j['id']));
                        $cars = [];
                        while ($row = $carRes->fetch_assoc()) $cars[] = $row['caracteristica'];
                        echo $cars ? '<span style="color:#b2f6ff;">' . htmlspecialchars(implode(', ', $cars)) . '</span>' : '<span class="fst-italic" style="color:#b2f6ff;">Ninguna</span>';
                    ?></div>
                    <div class="mt-3 d-flex gap-3 flex-wrap">
                        <a href="/admin/juegos?editar=<?= $j['id'] ?>" style="color:#22d3ee; text-decoration:underline; font-weight:bold;">Editar</a>
                        <a href="/admin/paquetes/<?= $j['id'] ?>" style="color:#22d3ee; text-decoration:underline; font-weight:bold;">Paquetes</a>
                        <a href="/admin/juegos?eliminar=<?= $j['id'] ?>" style="color:#ff0059; text-decoration:underline; font-weight:bold;" onclick="return confirm('¿Eliminar este juego y todos sus paquetes/características?')">Eliminar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
    // Procesar eliminación de juego
    if (isset($_GET['eliminar'])) {
        $del_id = intval($_GET['eliminar']);
        $stmt = $mysqli->prepare("DELETE FROM juegos WHERE id=?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        header('Location: /admin/juegos');
        exit;
    }
    ?>
    <?php
    // Formulario de edición de cabecera de juego
    if (isset($_GET['editar'])) {
            $edit_id = intval($_GET['editar']);
            $res_edit = $mysqli->prepare("SELECT * FROM juegos WHERE id=?");
            $res_edit->bind_param('i', $edit_id);
            $res_edit->execute();
            $juego_edit = $res_edit->get_result()->fetch_assoc();
            if ($juego_edit):
    ?>
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
        <form method="post" action="/admin/juegos" enctype="multipart/form-data" class="bg-slate-900 rounded-xl p-8 max-w-lg w-full relative" style="box-shadow:0 0 2rem #22d3ee33;">
            <h3 class="text-xl font-bold mb-4 text-cyan-300">Editar juego</h3>
            <input type="hidden" name="edit_juego_id" value="<?= $juego_edit['id'] ?>">
            <input type="text" name="edit_nombre" value="<?= htmlspecialchars($juego_edit['nombre']) ?>" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2">
            <textarea name="edit_descripcion" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2"><?= htmlspecialchars($juego_edit['descripcion']) ?></textarea>
            <label class="block text-slate-300 font-medium mb-1">Moneda fija o variable:</label>
            <select name="edit_moneda_fija_id" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2">
                <option value="" <?= empty($juego_edit['moneda_fija_id']) ? 'selected' : '' ?>>Moneda variable (usuario elige)</option>
                <?php foreach ($monedas as $m): ?>
                <option value="<?= $m['id'] ?>" <?= (!empty($juego_edit['moneda_fija_id']) && $juego_edit['moneda_fija_id'] == $m['id']) ? 'selected' : '' ?>>Solo <?= htmlspecialchars($m['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="inline-flex items-center mb-2">
                <input type="checkbox" name="edit_popular" class="form-checkbox h-5 w-5 text-emerald-500" <?= !empty($juego_edit['popular']) ? 'checked' : '' ?>>
                <span class="ml-2 text-slate-300">Marcar como popular</span>
            </label>
            <label class="block text-slate-300 mb-1">Imagen actual:</label>
            <?php if ($juego_edit['imagen']): ?>
                <img src="/<?= htmlspecialchars($juego_edit['imagen']) ?>" alt="Imagen actual" class="mb-2 rounded-lg max-h-32">
            <?php endif; ?>
            <input type="file" name="edit_imagen" accept="image/*" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2" onchange="previewEditJuegoImg(event)">
            <div class="flex justify-center my-2">
                <img id="preview-edit-juego-img" src="#" alt="Previsualización" style="display:none;max-width:180px;max-height:180px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
            </div>
            <label class="block text-slate-300 mb-1">Imagen común para paquetes:</label>
            <input type="file" name="edit_imagen_paquete" accept="image/*" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2" onchange="previewEditImagenPaqueteJuego(event)">
            <?php if ($juego_edit['imagen_paquete']): ?>
                <img src="/<?= htmlspecialchars($juego_edit['imagen_paquete']) ?>" alt="Imagen Paquete" class="mb-2 rounded-lg max-h-24">
            <?php endif; ?>
            <div class="flex justify-center my-2">
                <img id="preview-edit-juego-img-paquete" src="#" alt="Previsualización Paquete" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
            </div>
            <button type="submit" name="edit_juego_submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg w-full">Guardar cambios</button>
            <script>
            function previewImagenPaqueteJuego(event) {
                const input = event.target;
                const img = document.getElementById('preview-juego-img-paquete');
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
            function previewEditImagenPaqueteJuego(event) {
                const input = event.target;
                const img = document.getElementById('preview-edit-juego-img-paquete');
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
            <a href="/admin/juegos" class="absolute top-2 right-4 text-cyan-300 hover:underline text-lg">&times;</a>
        </form>
    </div>
    <script>
    function previewEditJuegoImg(event) {
            const input = event.target;
            const img = document.getElementById('preview-edit-juego-img');
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
    // Procesar edición de cabecera de juego
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_juego_submit'], $_POST['edit_juego_id'], $_POST['edit_nombre'], $_POST['edit_descripcion'])) {
        $edit_id = intval($_POST['edit_juego_id']);
        $edit_nombre = trim($_POST['edit_nombre']);
        $edit_descripcion = trim($_POST['edit_descripcion']);
        $edit_imagen = null;
        if (isset($_FILES['edit_imagen']) && $_FILES['edit_imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['edit_imagen']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $permitidas)) {
                $dir = '../assets/img/juegos/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $nombre_archivo = uniqid('juego_') . '.' . $ext;
                $destino = $dir . $nombre_archivo;
                if (move_uploaded_file($_FILES['edit_imagen']['tmp_name'], $destino)) {
                    $edit_imagen = 'assets/img/juegos/' . $nombre_archivo;
                }
            }
        }
        if ($edit_imagen) {
            $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=?, imagen=? WHERE id=?");
            $stmt->bind_param('sssi', $edit_nombre, $edit_descripcion, $edit_imagen, $edit_id);
        } else {
            $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=? WHERE id=?");
            $stmt->bind_param('ssi', $edit_nombre, $edit_descripcion, $edit_id);
        }
        $stmt->execute();
        header('Location: /admin/juegos');
        exit;
    }
    ?>
</main>
<script>
function addCarField() {
    var cont = document.getElementById('caracteristicas');
    var input = document.createElement('input');
    input.type = 'text';
    input.name = 'caracteristicas[]';
    input.placeholder = 'Característica';
    input.className = 'w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400 mt-2';
    cont.appendChild(input);
}
function previewImagenJuego(event) {
    const input = event.target;
    const img = document.getElementById('preview-juego-img');
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
<?php include '../includes/footer.php'; ?>