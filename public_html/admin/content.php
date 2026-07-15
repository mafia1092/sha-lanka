<?php
// admin/content.php — edit every text block shown on the public website.
// All blocks live in the site_content table; this page updates them in one form.
require_once __DIR__ . '/../sys/auth.php';

$page_title = 'Site Text';
$msg = ''; $err = '';

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

// Show the success banner after the redirect (short whitelisted code only).
if (($_GET['msg'] ?? '') === 'saved') {
    $msg = 'All changes saved.';
}

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

<p class="text-sm text-gray-600 mb-6">These texts appear on the public website immediately after saving.</p>

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
