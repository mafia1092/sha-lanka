<?php
// admin/inquiries.php — the inquiry inbox. Read messages from the public
// contact form, change their status, keep private notes, or delete them.
require_once __DIR__ . '/../sys/auth.php';
require_once __DIR__ . '/../sys/notifications.php';

$page_title = 'Inquiries';
$msg = '';
$err = '';

// Allowed values — every status coming from the browser is checked against these.
$valid_statuses = ['new', 'replied', 'closed'];        // real inquiry statuses
$valid_tabs     = ['new', 'replied', 'closed', 'all']; // filter tabs (adds "all")

// ---------- POST actions: set_status / save_note / delete ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $err = 'Session expired — please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);

        // Which filter tab to send the user back to after the redirect.
        $back = $_POST['return_status'] ?? 'all';
        if (!in_array($back, $valid_tabs, true)) $back = 'all';

        $done = ''; // short message code for the redirect (?msg=...)

        if ($id > 0 && $action === 'set_status') {
            $new_status = $_POST['status'] ?? '';
            if (in_array($new_status, $valid_statuses, true)) {
                $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
                $stmt->bind_param('si', $new_status, $id);
                $stmt->execute();
                $stmt->close();
                $done = 'saved';
            } else {
                $err = 'Invalid status.';
            }
        } elseif ($id > 0 && $action === 'save_note') {
            $note = trim($_POST['admin_note'] ?? '');
            $stmt = $conn->prepare("UPDATE inquiries SET admin_note = ? WHERE id = ?");
            $stmt->bind_param('si', $note, $id);
            $stmt->execute();
            $stmt->close();
            $done = 'saved';
        } elseif ($id > 0 && $action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM inquiries WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $done = 'deleted';
        } else {
            $err = 'Invalid request.';
        }

        // Post/Redirect/Get — reload the page so refreshing can't repeat the action.
        if ($done !== '') {
            header('Location: inquiries.php?status=' . $back . '&msg=' . $done);
            exit;
        }
    }
}

// ---------- green banner text from the redirect (short codes only) ----------
$msg_codes = ['saved' => 'Saved.', 'deleted' => 'Inquiry deleted.'];
if (isset($_GET['msg'], $msg_codes[$_GET['msg']])) {
    $msg = $msg_codes[$_GET['msg']];
}

// ---------- counts per status, shown on the filter tabs ----------
$counts = ['new' => 0, 'replied' => 0, 'closed' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM inquiries GROUP BY status");
while ($row = $res->fetch_assoc()) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['c'];
}
$counts['all'] = $counts['new'] + $counts['replied'] + $counts['closed'];

// ---------- highlight: arriving from a notification bell link ----------
$highlight = (int)($_GET['highlight'] ?? 0);
if ($highlight > 0) {
    // Mark every bell notification about this inquiry as read.
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE inquiry_id = ?");
    $stmt->bind_param('i', $highlight);
    $stmt->execute();
    $stmt->close();
}

// ---------- which tab is open? ----------
// Default: "new" when there are new inquiries, otherwise "all". When following
// a highlight link with no tab given, use "all" so the inquiry is visible
// whatever its status is.
if (isset($_GET['status'])) {
    $status = $_GET['status'];
} elseif ($highlight > 0) {
    $status = 'all';
} else {
    $status = ($counts['new'] > 0) ? 'new' : 'all';
}
if (!in_array($status, $valid_tabs, true)) $status = 'all';

// ---------- load the inquiries for this tab (newest first) ----------
if ($status === 'all') {
    $res = $conn->query("SELECT * FROM inquiries ORDER BY created_at DESC LIMIT 100");
    $inquiries = $res->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT * FROM inquiries WHERE status = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $inquiries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Badge colours per status
$badge_classes = [
    'new'     => 'bg-blue-100 text-blue-800',
    'replied' => 'bg-green-100 text-green-800',
    'closed'  => 'bg-gray-200 text-gray-600',
];

include __DIR__ . '/inc/header.php';
?>

<?php if ($msg !== ''): ?>
  <div class="bg-green-100 border border-green-300 text-green-800 rounded p-3 mb-4"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err !== ''): ?>
  <div class="bg-red-100 border border-red-300 text-red-800 rounded p-3 mb-4"><?= h($err) ?></div>
<?php endif; ?>

<!-- Filter tabs -->
<div class="flex flex-wrap gap-2 mb-6">
  <?php foreach (['new' => 'New', 'replied' => 'Replied', 'closed' => 'Closed', 'all' => 'All'] as $tab => $label): ?>
    <a href="inquiries.php?status=<?= $tab ?>"
       class="px-3 py-1.5 rounded-full text-sm font-medium <?= $status === $tab ? 'bg-brand text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' ?>">
      <?= $label ?> (<?= (int)$counts[$tab] ?>)
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$inquiries): ?>
  <div class="bg-white rounded-lg shadow p-6 text-gray-500">No inquiries in this view.</div>
<?php endif; ?>

<div class="space-y-4">
<?php foreach ($inquiries as $inq): ?>
  <?php
    $iid = (int)$inq['id'];
    // WhatsApp needs a digits-only number; skip if too short to be real.
    $wa_digits = preg_replace('/\D/', '', (string)($inq['phone'] ?? ''));
    $badge     = $badge_classes[$inq['status']] ?? 'bg-gray-200 text-gray-600';
  ?>
  <div id="inq-<?= $iid ?>" class="bg-white rounded-lg shadow p-6<?= $highlight === $iid ? ' ring-2 ring-brand' : '' ?>">

    <!-- Who + when -->
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <p class="font-semibold text-lg"><?= h($inq['name']) ?></p>
        <p class="text-sm text-gray-600">
          <a href="mailto:<?= h($inq['email']) ?>" class="text-brand hover:underline"><?= h($inq['email']) ?></a>
          <?php if (!empty($inq['phone'])): ?>
            &middot; <a href="tel:<?= h($inq['phone']) ?>" class="text-brand hover:underline"><?= h($inq['phone']) ?></a>
            <?php if (strlen($wa_digits) >= 9): ?>
              &middot; <a href="https://wa.me/<?= h($wa_digits) ?>" target="_blank" rel="noopener" class="text-green-600 font-medium hover:underline">WhatsApp them</a>
            <?php endif; ?>
          <?php endif; ?>
        </p>
      </div>
      <div class="text-right shrink-0">
        <span class="inline-block text-xs font-semibold px-2.5 py-1 rounded-full <?= $badge ?>"><?= h(ucfirst($inq['status'])) ?></span>
        <p class="text-xs text-gray-500 mt-1"><?= h($inq['created_at']) ?> &middot; <?= h(time_ago($inq['created_at'])) ?></p>
      </div>
    </div>

    <!-- Service they asked about -->
    <?php if (!empty($inq['service_choice'])): ?>
      <p class="mt-2"><span class="inline-block bg-gray-100 text-gray-700 text-xs px-2.5 py-1 rounded-full"><?= h($inq['service_choice']) ?></span></p>
    <?php endif; ?>

    <!-- Full message -->
    <p class="mt-3 text-sm text-gray-800 leading-relaxed"><?= nl2br(h($inq['message'])) ?></p>

    <!-- Private note (admins only) -->
    <?php if (!empty($inq['admin_note'])): ?>
      <div class="mt-3 bg-yellow-50 border border-yellow-200 text-yellow-900 text-sm rounded p-3">
        <span class="font-semibold">Note:</span> <?= nl2br(h($inq['admin_note'])) ?>
      </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
      <!-- Change status: one form, three buttons (the current one is highlighted) -->
      <form method="post" class="flex items-center gap-1.5">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="set_status">
        <input type="hidden" name="id" value="<?= $iid ?>">
        <input type="hidden" name="return_status" value="<?= h($status) ?>">
        <?php foreach ($valid_statuses as $s): ?>
          <button type="submit" name="status" value="<?= $s ?>"
                  class="text-xs px-2.5 py-1 rounded border <?= $inq['status'] === $s ? 'bg-brand border-brand text-white' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' ?>">
            <?= ucfirst($s) ?>
          </button>
        <?php endforeach; ?>
      </form>

      <!-- Delete (permanent, so it asks first) -->
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $iid ?>">
        <input type="hidden" name="return_status" value="<?= h($status) ?>">
        <button type="submit" onclick="return confirm('Delete this inquiry permanently?')"
                class="bg-red-600 text-white text-sm px-3 py-1.5 rounded hover:bg-red-700">Delete</button>
      </form>
    </div>

    <!-- Add / edit the private note (folded away until clicked) -->
    <details class="mt-3">
      <summary class="cursor-pointer select-none text-xs text-gray-500 hover:text-gray-700">
        <?= !empty($inq['admin_note']) ? 'Edit note' : 'Add note' ?>
      </summary>
      <form method="post" class="mt-2 flex flex-col sm:flex-row items-start gap-2">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_note">
        <input type="hidden" name="id" value="<?= $iid ?>">
        <input type="hidden" name="return_status" value="<?= h($status) ?>">
        <textarea name="admin_note" rows="2" placeholder="Private note — only admins see this"
                  class="w-full sm:max-w-md border border-gray-300 rounded p-2 text-sm"><?= h($inq['admin_note'] ?? '') ?></textarea>
        <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Save note</button>
      </form>
    </details>

  </div>
<?php endforeach; ?>
</div>

<?php if ($highlight > 0): ?>
<script>
  // Scroll the highlighted inquiry into view (we arrived via a notification link)
  (function () {
    var el = document.getElementById('inq-<?= $highlight ?>');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  })();
</script>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
