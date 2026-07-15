<?php
// admin/gallery.php — manage the homepage photo gallery.
// Upload photos (auto-resized), reorder, activate/deactivate, flip
// orientation, delete. The homepage mosaic needs >= 8 active landscape
// AND >= 8 active portrait images, so any action that would drop an
// orientation below 8 active is blocked with an error.
require_once __DIR__ . '/../sys/auth.php';
require_once __DIR__ . '/../sys/images.php';

$page_title = 'Gallery';
$msgLines = [];   // green banner lines
$errLines = [];   // red banner lines

// Folder where <base>.jpg (thumb) and <base>-lg.jpg (large) live.
$galleryDir = realpath(__DIR__ . '/../assets/img/gallery');

// Count ACTIVE images of one orientation ('land' or 'port').
function gallery_active_count(mysqli $conn, string $orientation): int {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM gallery_images WHERE is_active = 1 AND orientation = ?");
    $stmt->bind_param('s', $orientation);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $count;
}

// Fetch one image row by id (or null). All file paths come from this
// DB row's file_base — never from anything the browser sends.
function gallery_get_row(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT id, file_base, orientation, is_active FROM gallery_images WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Redirect after a POST (Post/Redirect/Get) using a short whitelisted code.
function gallery_redirect(string $param, string $code): void {
    header('Location: gallery.php?' . $param . '=' . $code);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A too-big upload makes PHP drop the whole POST body (so CSRF would
    // "fail" confusingly) — detect that case first and explain it.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errLines[] = 'Upload too large for the server — try fewer / smaller photos at once.';
    } elseif (!validateCsrfToken()) {
        $errLines[] = 'Session expired — please try again.';
    } elseif ($galleryDir === false) {
        $errLines[] = 'Gallery folder not found on the server (assets/img/gallery).';
    } else {
        $action = $_POST['action'] ?? '';

        // ---------- UPLOAD (one or more photos) ----------
        if ($action === 'upload') {
            // PHP gives multi-file uploads as parallel arrays
            // ($_FILES['photos']['name'][0], ['tmp_name'][0], ...) —
            // restructure into one array per file.
            $files = [];
            if (isset($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
                foreach ($_FILES['photos']['name'] as $i => $name) {
                    if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    $files[] = [
                        'name'     => $name,
                        'type'     => $_FILES['photos']['type'][$i],
                        'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                        'error'    => $_FILES['photos']['error'][$i],
                        'size'     => $_FILES['photos']['size'][$i],
                    ];
                }
            }
            if (!$files) {
                gallery_redirect('err', 'nofiles');
            }

            // PHP silently drops files beyond max_file_uploads (default 20) —
            // warn instead of pretending the whole batch succeeded. The form
            // posts how many files were selected (file_count, set by JS).
            $selected  = (int)($_POST['file_count'] ?? 0);
            $uploadCap = (int)ini_get('max_file_uploads');
            $dropped   = 0;
            if ($selected > count($files)) {
                $dropped = $selected - count($files);
            } elseif ($uploadCap > 0 && count($files) >= $uploadCap) {
                $dropped = -1; // no-JS fallback: exactly at the cap, may be more
            }

            // New images go to the end of the sort order.
            $nextSort = (int)$conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_images")->fetch_row()[0];

            $results = []; // per-file outcome shown after the redirect
            $ins = $conn->prepare("INSERT INTO gallery_images (file_base, orientation, sort_order) VALUES (?, ?, ?)");
            foreach ($files as $file) {
                // Unique filename: timestamp + 8 random bytes (never the
                // visitor-supplied name); regenerate on the off-chance of
                // a collision so nothing is ever overwritten.
                do {
                    $base = 'g' . date('YmdHis') . '_' . bin2hex(random_bytes(8));
                } while (file_exists($galleryDir . '/' . $base . '.jpg'));
                [$ok, $errOrBase, $orientation] = img_process_gallery($file, $galleryDir, $base);
                if ($ok) {
                    $ins->bind_param('ssi', $base, $orientation, $nextSort);
                    $ins->execute();
                    $nextSort++;
                    $results[] = ['ok' => true, 'text' => $file['name'] . ' — uploaded (' . ($orientation === 'land' ? 'landscape' : 'portrait') . ')'];
                } else {
                    $results[] = ['ok' => false, 'text' => $file['name'] . ' — ' . $errOrBase];
                }
            }
            $ins->close();
            if ($dropped > 0) {
                $results[] = ['ok' => false, 'text' => "Only " . count($files) . " of $selected photos were received — the server accepts at most $uploadCap per upload. Please upload the remaining $dropped in a smaller batch."];
            } elseif ($dropped === -1) {
                $results[] = ['ok' => false, 'text' => "You hit the server's limit of $uploadCap photos per upload — if you selected more, upload the rest in another batch."];
            }
            $_SESSION['gallery_upload_results'] = $results;
            gallery_redirect('msg', 'uploaded');
        }

        // ---------- Row actions (move / toggle / set_orient / delete) ----------
        if (in_array($action, ['move', 'toggle', 'set_orient', 'delete'], true)) {
            $row = gallery_get_row($conn, (int)($_POST['id'] ?? 0));
            if (!$row) {
                gallery_redirect('err', 'notfound');
            }

            if ($action === 'move') {
                // Swap this image with its neighbour in the ordered list,
                // then renumber everything 1..N (self-heals duplicates).
                $dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
                $ids = array_map('intval', array_column(
                    $conn->query("SELECT id FROM gallery_images ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC), 'id'));
                $pos  = array_search((int)$row['id'], $ids, true);
                $swap = $dir === 'up' ? $pos - 1 : $pos + 1;
                if ($pos !== false && isset($ids[$swap])) {
                    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                    $upd = $conn->prepare("UPDATE gallery_images SET sort_order = ? WHERE id = ?");
                    foreach ($ids as $i => $imgId) {
                        $order = $i + 1;
                        $upd->bind_param('ii', $order, $imgId);
                        $upd->execute();
                    }
                    $upd->close();
                }
                gallery_redirect('msg', 'moved');
            }

            if ($action === 'toggle') {
                if ($row['is_active']) {
                    // Deactivating: keep at least 8 active in this orientation.
                    if (gallery_active_count($conn, $row['orientation']) - 1 < 8) {
                        gallery_redirect('err', 'min');
                    }
                    $stmt = $conn->prepare("UPDATE gallery_images SET is_active = 0 WHERE id = ?");
                    $stmt->bind_param('i', $row['id']);
                    $stmt->execute();
                    $stmt->close();
                    gallery_redirect('msg', 'off');
                }
                $stmt = $conn->prepare("UPDATE gallery_images SET is_active = 1 WHERE id = ?");
                $stmt->bind_param('i', $row['id']);
                $stmt->execute();
                $stmt->close();
                gallery_redirect('msg', 'on');
            }

            if ($action === 'set_orient') {
                // Flipping an ACTIVE image removes it from its old
                // orientation's count — block if that drops below 8.
                if ($row['is_active'] && gallery_active_count($conn, $row['orientation']) - 1 < 8) {
                    gallery_redirect('err', 'min');
                }
                $new = $row['orientation'] === 'land' ? 'port' : 'land';
                $stmt = $conn->prepare("UPDATE gallery_images SET orientation = ? WHERE id = ?");
                $stmt->bind_param('si', $new, $row['id']);
                $stmt->execute();
                $stmt->close();
                gallery_redirect('msg', 'orient');
            }

            if ($action === 'delete') {
                // Same guard as deactivate while the image is still active.
                if ($row['is_active'] && gallery_active_count($conn, $row['orientation']) - 1 < 8) {
                    gallery_redirect('err', 'min');
                }
                // Remove both files (paths built ONLY from the DB file_base).
                foreach ([$row['file_base'] . '.jpg', $row['file_base'] . '-lg.jpg'] as $name) {
                    $path = $galleryDir . '/' . $name;
                    if (is_file($path)) unlink($path);
                }
                $stmt = $conn->prepare("DELETE FROM gallery_images WHERE id = ?");
                $stmt->bind_param('i', $row['id']);
                $stmt->execute();
                $stmt->close();
                gallery_redirect('msg', 'deleted');
            }
        }
    }
}

// ---------- Banner messages after a redirect (whitelisted short codes) ----------
$MSG_CODES = [
    'moved'   => 'Order updated.',
    'on'      => 'Image activated.',
    'off'     => 'Image deactivated.',
    'orient'  => 'Orientation changed.',
    'deleted' => 'Image deleted — its files were removed too.',
];
$ERR_CODES = [
    'notfound' => 'Image not found — it may already be deleted.',
    'min'      => 'Blocked: that would leave fewer than 8 active images in that orientation. The homepage mosaic needs at least 8 landscape and 8 portrait — upload or activate a replacement first.',
    'nofiles'  => 'No files were selected — choose one or more photos first.',
];
if (isset($MSG_CODES[$_GET['msg'] ?? ''])) $msgLines[] = $MSG_CODES[$_GET['msg']];
if (isset($ERR_CODES[$_GET['err'] ?? ''])) $errLines[] = $ERR_CODES[$_GET['err']];

// Per-file upload results were stored in the session before the redirect.
if (($_GET['msg'] ?? '') === 'uploaded') {
    foreach ($_SESSION['gallery_upload_results'] ?? [] as $r) {
        if ($r['ok']) $msgLines[] = $r['text'];
        else          $errLines[] = $r['text'];
    }
    unset($_SESSION['gallery_upload_results']);
}

// ---------- Load data for the page ----------
$images = $conn->query("SELECT id, file_base, orientation, sort_order, is_active FROM gallery_images ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
$activeLand = 0;
$activePort = 0;
foreach ($images as $img) {
    if ($img['is_active']) {
        $img['orientation'] === 'land' ? $activeLand++ : $activePort++;
    }
}
$lastIndex = count($images) - 1;

include __DIR__ . '/inc/header.php';
?>

<?php if ($msgLines): ?>
  <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3 mb-4">
    <?php foreach ($msgLines as $line): ?><div><?= h($line) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($errLines): ?>
  <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3 mb-4">
    <?php foreach ($errLines as $line): ?><div><?= h($line) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Counts + low-stock warning -->
<p class="text-sm text-gray-600 mb-2">
  <span class="font-semibold text-gray-900"><?= $activeLand ?></span> landscape ·
  <span class="font-semibold text-gray-900"><?= $activePort ?></span> portrait active
  <span class="text-gray-400">(<?= count($images) ?> photos total)</span>
</p>
<?php if ($activeLand < 8 || $activePort < 8): ?>
  <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 text-sm rounded-lg px-4 py-3 mb-4">
    <strong>Warning:</strong> the homepage mosaic needs at least 8 active landscape and 8 active portrait images.
    You currently have <?= $activeLand ?> landscape and <?= $activePort ?> portrait active — upload or activate more.
  </div>
<?php endif; ?>

<!-- Upload -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
  <h2 class="font-semibold mb-1">Upload photos</h2>
  <p class="text-sm text-gray-500 mb-4">
    JPEG, PNG or WebP — you can select several at once. Photos are resized automatically.
    iPhone HEIC photos are not supported: export them as JPEG first.
  </p>
  <form method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3"
        onsubmit="this.file_count.value = this.querySelector('[name=&quot;photos[]&quot;]').files.length">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="upload">
    <input type="hidden" name="file_count" value="0">
    <input type="file" name="photos[]" multiple accept="image/*" required
           class="text-sm text-gray-700 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-gray-100 file:text-sm hover:file:bg-gray-200">
    <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Upload</button>
  </form>
</div>

<!-- Thumbnail grid -->
<?php if (!$images): ?>
  <p class="text-gray-500">No gallery images yet — upload some above.</p>
<?php else: ?>
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
    <?php foreach ($images as $i => $img): ?>
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="relative">
          <img src="../assets/img/gallery/<?= h($img['file_base']) ?>.jpg" alt=""
               loading="lazy"
               class="w-full aspect-square object-cover<?= $img['is_active'] ? '' : ' opacity-40' ?>">
          <!-- orientation badge + sort number -->
          <span class="absolute top-1.5 left-1.5 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded <?= $img['orientation'] === 'land' ? 'bg-brand text-white' : 'bg-espresso text-cream' ?>">
            <?= h($img['orientation']) ?>
          </span>
          <span class="absolute top-1.5 right-1.5 text-[10px] font-semibold bg-black/60 text-white px-1.5 py-0.5 rounded">#<?= (int)$img['sort_order'] ?></span>
          <?php if (!$img['is_active']): ?>
            <span class="absolute bottom-1.5 left-1.5 text-[10px] font-semibold bg-gray-700 text-white px-1.5 py-0.5 rounded">inactive</span>
          <?php endif; ?>
        </div>

        <div class="p-2 space-y-1.5">
          <!-- Row 1: reorder -->
          <div class="flex gap-1.5">
            <form method="post" class="flex-1">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="dir" value="up">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <button type="submit" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>
                      class="w-full text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-30 disabled:hover:bg-gray-100">&#9650;</button>
            </form>
            <form method="post" class="flex-1">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="dir" value="down">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <button type="submit" title="Move down" <?= $i === $lastIndex ? 'disabled' : '' ?>
                      class="w-full text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-30 disabled:hover:bg-gray-100">&#9660;</button>
            </form>
          </div>
          <!-- Row 2: toggle / flip orientation / delete -->
          <div class="flex gap-1.5">
            <form method="post" class="flex-1">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <button type="submit" class="w-full text-xs px-2 py-1 rounded <?= $img['is_active'] ? 'bg-gray-100 hover:bg-gray-200' : 'bg-green-600 text-white hover:bg-green-700' ?>">
                <?= $img['is_active'] ? 'Hide' : 'Show' ?>
              </button>
            </form>
            <form method="post" class="flex-1">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="set_orient">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <button type="submit" title="Flip landscape / portrait"
                      class="w-full text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200">Flip</button>
            </form>
            <form method="post" class="flex-1" onsubmit="return confirm('Delete this photo permanently? Its image files will be removed too.');">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <button type="submit" class="w-full text-xs px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700">Del</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
