<?php
// admin/content.php — edit the homepage hero photo + every text block shown
// on the public website. Text blocks live in the site_content table; the hero
// photo filename lives in settings.hero_image (file in assets/img/hero/).
require_once __DIR__ . '/../sys/auth.php';
require_once __DIR__ . '/../sys/images.php';

$page_title = 'Site Content';
$msg = ''; $err = '';

$hero_dir = __DIR__ . '/../assets/img/hero';

// Friendly headings for each section key (also sets the display order).
$section_names = [
    'home'    => 'Homepage — Hero',
    'about'   => 'About Us',
    'fleet'   => 'Fleet — Rentals',
    'tours'   => 'Tours',
    'carrier' => 'Car Carrier',
    'gallery' => 'Gallery',
    'contact' => 'Contact',
    'footer'  => 'Footer',
    'faq'     => 'FAQ',
];

// ---- Handle the save (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $err = 'Session expired — please try again.';

    // ---- Upload a new homepage hero photo ----
    } elseif (($_POST['action'] ?? '') === 'hero_image') {
        $file = $_FILES['hero'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            header('Location: content.php?err=nofile');
            exit;
        }
        if (!is_dir($hero_dir)) {
            @mkdir($hero_dir, 0755, true);
        }
        // A fresh filename every upload, so a browser or the Hostinger CDN can
        // never show a stale cached hero photo.
        $new_name = 'hero-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.jpg';
        [$ok, $up_err] = img_process_hero($file, $hero_dir . '/' . $new_name);
        if (!$ok) {
            $_SESSION['hero_err'] = $up_err;
            header('Location: content.php?err=upload');
            exit;
        }

        $old_name = setting('hero_image');
        $stmt = $conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES ('hero_image', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->bind_param('s', $new_name);
        $stmt->execute();
        $stmt->close();

        // Tidy up the photo we just replaced. The name pattern check means a
        // tampered database value can never delete anything else.
        if ($old_name !== '' && $old_name !== $new_name
            && preg_match('/^hero-[0-9]{14}-[0-9a-f]{8}\.jpg$/', $old_name)) {
            @unlink($hero_dir . '/' . $old_name);
        }
        header('Location: content.php?msg=hero');
        exit;

    } elseif (isset($_POST['content']) && is_array($_POST['content'])) {
        // Update each submitted block. Only UPDATE existing keys — an unknown
        // key simply matches no row, so nothing new is ever inserted here.
        $stmt = $conn->prepare('UPDATE site_content SET content_value = ? WHERE content_key = ?');
        foreach ($_POST['content'] as $key => $val) {
            if (!is_string($val)) continue; // ignore anything that isn't plain text
            $val = trim($val);
            $key = (string)$key;
            $stmt->bind_param('ss', $val, $key);
            $stmt->execute();
        }
        $stmt->close();
        // Post/Redirect/Get: reload the page so a refresh can't re-submit the form.
        header('Location: content.php?msg=saved');
        exit;
    }
}

// Show the banner after the redirect (short whitelisted codes only).
if (($_GET['msg'] ?? '') === 'saved') {
    $msg = 'All changes saved.';
} elseif (($_GET['msg'] ?? '') === 'hero') {
    $msg = 'New hero photo saved — it is live on the homepage now.';
}
if (($_GET['err'] ?? '') === 'nofile') {
    $err = 'Please choose a photo first.';
} elseif (($_GET['err'] ?? '') === 'upload') {
    // The specific reason (e.g. the HEIC message) was stashed before redirect.
    $err = $_SESSION['hero_err'] ?? 'Could not upload that photo.';
    unset($_SESSION['hero_err']);
}

// Current hero photo (empty = still using the default from styles.css)
$hero_image = setting('hero_image');

// ---- Load every text block, grouped by section ----
$sections = []; // section key => list of rows
$res = $conn->query('SELECT content_key, content_value, label, section FROM site_content ORDER BY section, id');
while ($row = $res->fetch_assoc()) {
    $sections[$row['section']][] = $row;
}

// Show known sections in our friendly order first, then any unexpected ones.
$ordered = array_merge(array_intersect_key($section_names, $sections),
                       array_diff_key(array_combine(array_keys($sections), array_keys($sections)), $section_names));

include __DIR__ . '/inc/header.php';
?>

<?php if ($msg): ?>
  <div class="bg-green-100 border border-green-300 text-green-800 text-sm rounded px-4 py-3 mb-4"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="bg-red-100 border border-red-300 text-red-800 text-sm rounded px-4 py-3 mb-4"><?= h($err) ?></div>
<?php endif; ?>

<p class="text-sm text-gray-600 mb-6">Everything here appears on the public website immediately after saving.</p>

<!-- Homepage hero photo -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
  <h2 class="text-lg font-semibold mb-1">Homepage hero photo</h2>
  <p class="text-sm text-gray-500 mb-4">
    The big background photo behind &ldquo;Explore Sri Lanka, Your Way&rdquo;. Use a wide
    landscape photo, at least 1600px across &mdash; it is resized automatically.
    JPEG, PNG or WebP (iPhone HEIC photos are not supported: export as JPEG first).
  </p>

  <div class="flex flex-wrap items-start gap-6">
    <div>
      <?php if ($hero_image !== '' && is_file($hero_dir . '/' . $hero_image)): ?>
        <img src="../assets/img/hero/<?= h($hero_image) ?>" alt="Current hero photo"
             class="w-64 h-36 object-cover rounded border border-gray-200">
        <p class="text-xs text-gray-400 mt-1">Current photo</p>
      <?php else: ?>
        <div class="w-64 h-36 rounded border border-dashed border-gray-300 flex items-center justify-center px-4 text-center text-xs text-gray-400">
          Currently showing the default stock photo &mdash; upload your own to replace it.
        </div>
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="hero_image">
      <input type="file" name="hero" accept="image/*" required
             class="text-sm text-gray-700 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-gray-100 file:text-sm hover:file:bg-gray-200">
      <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Upload new hero photo</button>
    </form>
  </div>
</div>

<?php if (!$sections): ?>
  <div class="bg-white rounded-lg shadow p-6 text-sm text-gray-500">No text blocks found in the database yet.</div>
<?php else: ?>

<form method="post">
  <?= csrfField() ?>

  <?php foreach ($ordered as $section_key => $heading): ?>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4"><?= h($heading) ?></h2>

      <?php foreach ($sections[$section_key] as $row):
          // Longer texts get a taller box so they're easier to edit.
          $rows_attr = strlen($row['content_value'] ?? '') > 160 ? 4 : 2; ?>
        <div class="mb-4">
          <label for="c-<?= h($row['content_key']) ?>" class="block text-sm font-medium text-gray-700 mb-1">
            <?= h($row['label'] !== '' && $row['label'] !== null ? $row['label'] : $row['content_key']) ?>
          </label>
          <textarea id="c-<?= h($row['content_key']) ?>"
                    name="content[<?= h($row['content_key']) ?>]"
                    rows="<?= $rows_attr ?>"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand"><?= h($row['content_value']) ?></textarea>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <!-- Sticky save bar: stays visible at the bottom while scrolling the long form -->
  <div class="sticky bottom-0 z-10 bg-white/95 backdrop-blur border-t border-gray-200 -mx-4 px-4 py-3 flex justify-end shadow-[0_-2px_8px_rgba(0,0,0,0.06)]">
    <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Save all changes</button>
  </div>
</form>

<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
