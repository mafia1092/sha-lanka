<?php
// admin/faq.php — FAQ manager: add, edit, reorder, show/hide and delete
// the questions shown on the public FAQ page. Changes go live immediately.
require_once __DIR__ . '/../sys/auth.php';
$page_title = 'FAQ';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $err = 'Session expired — please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        $ok     = ''; // short msg code used for the redirect after success

        if ($action === 'add') {
            // New question goes to the end of the list (MAX sort_order + 1).
            $question = trim($_POST['question'] ?? '');
            $answer   = trim($_POST['answer'] ?? '');
            if ($question === '' || $answer === '') {
                $err = 'Both the question and the answer are required.';
            } else {
                $res  = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_pos FROM faq_items");
                $next = (int)$res->fetch_assoc()['next_pos'];
                $stmt = $conn->prepare("INSERT INTO faq_items (question, answer, sort_order, is_active) VALUES (?, ?, ?, 1)");
                $stmt->bind_param('ssi', $question, $answer, $next);
                $stmt->execute();
                $stmt->close();
                $ok = 'added';
            }

        } elseif ($action === 'save' && $id > 0) {
            // Update the question/answer text of one item.
            $question = trim($_POST['question'] ?? '');
            $answer   = trim($_POST['answer'] ?? '');
            if ($question === '' || $answer === '') {
                $err = 'Both the question and the answer are required.';
            } else {
                $stmt = $conn->prepare("UPDATE faq_items SET question = ?, answer = ? WHERE id = ?");
                $stmt->bind_param('ssi', $question, $answer, $id);
                $stmt->execute();
                $stmt->close();
                $ok = 'saved';
            }

        } elseif ($action === 'move' && $id > 0) {
            // Swap this item with its neighbour above/below. We renumber the
            // whole list (1, 2, 3, ...) so duplicate sort_order values can
            // never make an item "stuck".
            $dir = $_POST['dir'] ?? '';
            if ($dir === 'up' || $dir === 'down') {
                $ids = [];
                $res = $conn->query("SELECT id FROM faq_items ORDER BY sort_order, id");
                while ($row = $res->fetch_assoc()) $ids[] = (int)$row['id'];
                $pos  = array_search($id, $ids, true);
                $swap = ($dir === 'up') ? $pos - 1 : $pos + 1;
                if ($pos !== false && $swap >= 0 && $swap < count($ids)) {
                    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                    $stmt = $conn->prepare("UPDATE faq_items SET sort_order = ? WHERE id = ?");
                    foreach ($ids as $i => $itemId) {
                        $order = $i + 1;
                        $stmt->bind_param('ii', $order, $itemId);
                        $stmt->execute();
                    }
                    $stmt->close();
                }
                $ok = 'saved'; // already first/last is not an error — nothing moves
            }

        } elseif ($action === 'toggle' && $id > 0) {
            // Show/hide on the public page without deleting the item.
            $stmt = $conn->prepare("UPDATE faq_items SET is_active = 1 - is_active WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $ok = 'saved';

        } elseif ($action === 'delete' && $id > 0) {
            $stmt = $conn->prepare("DELETE FROM faq_items WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $ok = 'deleted';
        }

        // Post/Redirect/Get: a fresh GET stops the browser re-submitting on refresh.
        if ($ok !== '' && $err === '') {
            header('Location: faq.php?msg=' . $ok);
            exit;
        }
    }
}

// Success message after a redirect — only known short codes are accepted.
$msg_map = ['saved' => 'Saved.', 'added' => 'Question added.', 'deleted' => 'Question deleted.'];
if (isset($_GET['msg']) && isset($msg_map[$_GET['msg']])) {
    $msg = $msg_map[$_GET['msg']];
}

// Load all FAQ items in display order.
$items = [];
$res = $conn->query("SELECT id, question, answer, sort_order, is_active FROM faq_items ORDER BY sort_order, id");
while ($row = $res->fetch_assoc()) $items[] = $row;

include __DIR__ . '/inc/header.php';
?>

<?php if ($msg): ?>
  <div class="bg-green-100 border border-green-300 text-green-800 text-sm rounded px-4 py-3 mb-6"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="bg-red-100 border border-red-300 text-red-800 text-sm rounded px-4 py-3 mb-6"><?= h($err) ?></div>
<?php endif; ?>

<p class="text-sm text-gray-500 mb-6">Changes appear on the public FAQ page immediately.</p>

<!-- Add a new question -->
<div class="bg-white rounded-lg shadow p-6 mb-8">
  <h2 class="font-semibold mb-4">Add a question</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <label class="block text-sm text-gray-600 mb-1" for="new-question">Question</label>
    <input id="new-question" type="text" name="question" required
           class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand">
    <label class="block text-sm text-gray-600 mb-1" for="new-answer">Answer</label>
    <textarea id="new-answer" name="answer" rows="3" required
              class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-brand"></textarea>
    <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Add</button>
  </form>
</div>

<!-- Existing questions -->
<?php if (!$items): ?>
  <p class="text-sm text-gray-400">No questions yet — add your first one above.</p>
<?php endif; ?>

<?php foreach ($items as $i => $item): ?>
  <div class="bg-white rounded-lg shadow p-6 mb-4<?= $item['is_active'] ? '' : ' opacity-60' ?>">
    <?php if (!$item['is_active']): ?>
      <span class="inline-block bg-gray-200 text-gray-600 text-xs font-semibold rounded px-2 py-0.5 mb-3">hidden</span>
    <?php endif; ?>

    <!-- Edit the text -->
    <form method="post" class="mb-3">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
      <label class="block text-sm text-gray-600 mb-1" for="q-<?= (int)$item['id'] ?>">Question</label>
      <input id="q-<?= (int)$item['id'] ?>" type="text" name="question" value="<?= h($item['question']) ?>" required
             class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand">
      <label class="block text-sm text-gray-600 mb-1" for="a-<?= (int)$item['id'] ?>">Answer</label>
      <textarea id="a-<?= (int)$item['id'] ?>" name="answer" rows="3" required
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand"><?= h($item['answer']) ?></textarea>
      <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Save</button>
    </form>

    <!-- Reorder / show-hide / delete (each is its own tiny form) -->
    <div class="flex flex-wrap items-center gap-2">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="move">
        <input type="hidden" name="dir" value="up">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <button type="submit" <?= $i === 0 ? 'disabled' : '' ?>
                class="bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed"
                title="Move up">&#9650;</button>
      </form>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="move">
        <input type="hidden" name="dir" value="down">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <button type="submit" <?= $i === count($items) - 1 ? 'disabled' : '' ?>
                class="bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed"
                title="Move down">&#9660;</button>
      </form>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <button type="submit" class="bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded hover:bg-gray-200">
          <?= $item['is_active'] ? 'Hide' : 'Show' ?>
        </button>
      </form>
      <form method="post" class="ml-auto" onsubmit="return confirm('Delete this question? This cannot be undone.')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <button type="submit" class="bg-red-600 text-white text-sm px-3 py-1.5 rounded hover:bg-red-700">Delete</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
