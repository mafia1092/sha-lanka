<?php
// admin/notifications.php — full notification list. The bell in the header
// shows the latest few; this page shows up to 50 and lets the owner mark
// them read or dismiss (hide) them.
require_once __DIR__ . '/../sys/auth.php';
require_once __DIR__ . '/../sys/notifications.php';
$page_title = 'Notifications';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $err = 'Session expired — please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        $ok     = ''; // short msg code used for the redirect after success

        if ($action === 'read_all') {
            mark_all_notifications_read($conn);
            $ok = 'allread';

        } elseif ($action === 'read' && $id > 0) {
            mark_notification_read($conn, $id);
            $ok = 'read';

        } elseif ($action === 'dismiss' && $id > 0) {
            dismiss_notification($conn, $id);
            $ok = 'dismissed';
        }

        // Post/Redirect/Get: a fresh GET stops the browser re-submitting on refresh.
        if ($ok !== '' && $err === '') {
            header('Location: notifications.php?msg=' . $ok);
            exit;
        }
    }
}

// Success message after a redirect — only known short codes are accepted.
$msg_map = ['allread' => 'All notifications marked as read.', 'read' => 'Marked as read.', 'dismissed' => 'Notification dismissed.'];
if (isset($_GET['msg']) && isset($msg_map[$_GET['msg']])) {
    $msg = $msg_map[$_GET['msg']];
}

// Latest 50 non-dismissed notifications, newest first.
$notifications = get_recent_notifications($conn, 50);

include __DIR__ . '/inc/header.php';
?>

<?php if ($msg): ?>
  <div class="bg-green-100 border border-green-300 text-green-800 text-sm rounded px-4 py-3 mb-6"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="bg-red-100 border border-red-300 text-red-800 text-sm rounded px-4 py-3 mb-6"><?= h($err) ?></div>
<?php endif; ?>

<!-- Toolbar: mark everything read in one click -->
<div class="flex items-center justify-between mb-4">
  <p class="text-sm text-gray-500">Showing the latest <?= count($notifications) ?> notification<?= count($notifications) === 1 ? '' : 's' ?>.</p>
  <?php if ($notifications): ?>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="read_all">
      <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Mark all as read</button>
    </form>
  <?php endif; ?>
</div>

<?php if (!$notifications): ?>
  <div class="bg-white rounded-lg shadow p-6 text-center text-gray-400 text-sm">No notifications.</div>
<?php else: ?>
  <div class="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
    <?php foreach ($notifications as $n): ?>
      <?php
        // Unread rows stand out (white + bold); read rows are dimmed.
        $unread   = !$n['is_read'];
        $dotColor = $n['type'] === 'inquiry' ? 'bg-blue-500' : 'bg-gray-400';
      ?>
      <div class="flex items-start gap-3 px-4 py-3 <?= $unread ? 'bg-white' : 'bg-gray-50 opacity-60' ?>">
        <!-- Type dot: blue = inquiry, gray = system -->
        <span class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0 <?= $dotColor ?>" title="<?= h($n['type']) ?>"></span>

        <div class="min-w-0 grow">
          <p class="text-sm <?= $unread ? 'font-semibold' : 'font-normal text-gray-600' ?>"><?= h($n['title']) ?></p>
          <p class="text-sm text-gray-500 break-words"><?= h($n['message']) ?></p>
          <p class="text-xs text-gray-400 mt-1">
            <?= h(time_ago($n['created_at'])) ?>
            <?php if (!empty($n['link'])): ?>
              · <a href="<?= h($n['link']) ?>" class="text-brand hover:underline">Open &rarr;</a>
            <?php endif; ?>
          </p>
        </div>

        <!-- Row actions -->
        <div class="flex items-center gap-2 shrink-0 mt-0.5">
          <?php if ($unread): ?>
            <form method="post">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="read">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
              <button type="submit" class="text-xs text-brand border border-brand/40 px-2 py-1 rounded hover:bg-brand hover:text-white">Mark read</button>
            </form>
          <?php endif; ?>
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="dismiss">
            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
            <button type="submit" class="text-xs text-red-600 border border-red-200 px-2 py-1 rounded hover:bg-red-600 hover:text-white">Dismiss</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
