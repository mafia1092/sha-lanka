<?php
// admin/services.php — edit the 7 service cards (title, description, link, slide photos).
// The card layout itself lives in the public site code; this page only edits the data.
require_once __DIR__ . '/../sys/auth.php';
require_once __DIR__ . '/../sys/images.php';

$page_title = 'Service Cards';
$msg = '';
$err = '';

// Success message after a redirect (Post/Redirect/Get) — only whitelisted codes.
if (($_GET['msg'] ?? '') === 'saved') {
    $msg = 'Card saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $err = 'Session expired — please try again.';
    } elseif (($_POST['action'] ?? '') === 'save_card') {

        // 1) Look up the card by id — we take the slug from the DATABASE,
        //    never from the form, so file paths can't be tampered with.
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare('SELECT id, slug FROM service_cards WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $link_url    = trim($_POST['link_url'] ?? '');

        if (!$card) {
            $err = 'That card no longer exists.';
        } elseif ($title === '' || $description === '') {
            $err = 'Title and description are both required.';
        } elseif ($link_url !== '' && !filter_var($link_url, FILTER_VALIDATE_URL)) {
            $err = 'The link must be a full URL (starting with https://) or left empty.';
        } else {
            // 2) Save the text fields.
            $stmt = $conn->prepare('UPDATE service_cards SET title = ?, description = ?, link_url = ? WHERE id = ?');
            $stmt->bind_param('sssi', $title, $description, $link_url, $id);
            $stmt->execute();
            $stmt->close();

            // 3) Replace any uploaded slide photos in place.
            //    Slot numbers come from this fixed loop (1..3), never from user input,
            //    and the folder is verified to sit inside assets/img/slides/.
            $slidesRoot = realpath(__DIR__ . '/../assets/img/slides');
            $destDir    = $slidesRoot !== false ? realpath($slidesRoot . '/' . $card['slug']) : false;

            $slideErrs = [];
            if ($destDir === false || strpos($destDir, $slidesRoot . DIRECTORY_SEPARATOR) !== 0) {
                // Folder missing/invalid — only report it if the owner actually uploaded something.
                foreach ([1, 2, 3] as $n) {
                    if (($_FILES['slide' . $n]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $slideErrs[] = 'Photo folder for this card is missing on the server.';
                        break;
                    }
                }
            } else {
                foreach ([1, 2, 3] as $n) {
                    $file = $_FILES['slide' . $n] ?? null;
                    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue; // no new photo for this slot
                    }
                    [$ok, $slideErr] = img_process_slide($file, $destDir . '/' . $n . '.jpg');
                    if (!$ok) {
                        $slideErrs[] = 'Photo ' . $n . ': ' . $slideErr;
                    }
                }
            }

            if ($slideErrs) {
                // Text was saved fine, but one or more photos failed — show why.
                $err = 'Text saved, but some photos were not: ' . implode(' ', $slideErrs);
            } else {
                // All good — redirect back to this card (Post/Redirect/Get).
                header('Location: services.php?msg=saved#card-' . $id);
                exit;
            }
        }
    }
}

// Load all cards, grouped by section in display order.
$sections = ['fleet' => 'Rental Fleet', 'tours' => 'Guided Tours', 'carrier' => 'Car Carrier'];
$cards = ['fleet' => [], 'tours' => [], 'carrier' => []];
$res = $conn->query("SELECT * FROM service_cards ORDER BY FIELD(section,'fleet','tours','carrier'), sort_order");
while ($row = $res->fetch_assoc()) {
    $cards[$row['section']][] = $row;
}

include __DIR__ . '/inc/header.php';
?>

<?php if ($msg): ?>
  <div class="bg-green-100 border border-green-300 text-green-800 text-sm rounded px-4 py-3 mb-6"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="bg-red-100 border border-red-300 text-red-800 text-sm rounded px-4 py-3 mb-6"><?= h($err) ?></div>
<?php endif; ?>

<p class="text-sm text-gray-600 mb-8">The card layout and feature bullet lists are fixed in code; here you edit the title, description, link and photos. Slide photos are replaced in place (about 4:3, at least 800px wide looks best).</p>

<?php foreach ($sections as $sectionKey => $sectionLabel): ?>
  <h2 class="text-lg font-semibold mb-4 <?= $sectionKey !== 'fleet' ? 'mt-10' : '' ?>"><?= h($sectionLabel) ?></h2>

  <?php foreach ($cards[$sectionKey] as $card): ?>
    <div id="card-<?= (int)$card['id'] ?>" class="bg-white rounded-lg shadow p-6 mb-6">
      <form method="post" enctype="multipart/form-data" action="services.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_card">
        <input type="hidden" name="id" value="<?= (int)$card['id'] ?>">

        <div class="grid md:grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium mb-1" for="title-<?= (int)$card['id'] ?>">Title</label>
            <input type="text" id="title-<?= (int)$card['id'] ?>" name="title" value="<?= h($card['title']) ?>" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1" for="link-<?= (int)$card['id'] ?>">Link URL <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="url" id="link-<?= (int)$card['id'] ?>" name="link_url" value="<?= h($card['link_url']) ?>" placeholder="https://..."
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand">
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="desc-<?= (int)$card['id'] ?>">Description</label>
          <textarea id="desc-<?= (int)$card['id'] ?>" name="description" rows="3" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand"><?= h($card['description']) ?></textarea>
        </div>

        <!-- Three slide photos — uploading a new one replaces the old in place -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <?php foreach ([1, 2, 3] as $n):
              $slidePath = __DIR__ . '/../assets/img/slides/' . $card['slug'] . '/' . $n . '.jpg';
              $slideUrl  = '../assets/img/slides/' . rawurlencode($card['slug']) . '/' . $n . '.jpg';
          ?>
            <div>
              <span class="block text-xs text-gray-500 mb-1">Slide <?= $n ?></span>
              <?php if (is_file($slidePath)): ?>
                <img src="<?= h($slideUrl) ?>?v=<?= (int)filemtime($slidePath) ?>" alt="Slide <?= $n ?>" class="h-20 object-cover rounded">
              <?php else: ?>
                <div class="h-20 flex items-center justify-center bg-gray-100 rounded text-xs text-gray-400">No photo yet</div>
              <?php endif; ?>
              <input type="file" name="slide<?= $n ?>" accept="image/*" class="mt-2 block w-full text-xs text-gray-600">
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Save card</button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
