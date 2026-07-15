<?php
// admin/index.php — Dashboard. Read-only overview: stats, latest inquiries,
// recent notifications. No POST actions on this page.
require_once __DIR__ . '/../sys/auth.php';
require_once __DIR__ . '/../sys/notifications.php';

$page_title = 'Dashboard';

// --- Stats (no user input, so plain queries are safe here) ---

// Inquiry counts: total + how many are still status 'new', in one query.
$row = $conn->query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'new'), 0) AS new_count FROM inquiries")->fetch_assoc();
$inq_total = (int)$row['total'];
$inq_new   = (int)$row['new_count'];

// Page views: today + last 7 days (today plus the 6 days before it), one query.
$row = $conn->query("SELECT COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS today, COUNT(*) AS week
                     FROM page_views
                     WHERE created_at >= CURDATE() - INTERVAL 6 DAY")->fetch_assoc();
$views_today = (int)$row['today'];
$views_week  = (int)$row['week'];

// --- Latest 5 inquiries ---
$latest = $conn->query("SELECT id, name, email, service_choice, status, created_at
                        FROM inquiries ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// --- Recent notifications ---
$notifs = get_recent_notifications($conn, 6);

// Tailwind classes per inquiry status, for the colored badge.
$status_badge = [
    'new'     => 'bg-blue-100 text-blue-800',
    'replied' => 'bg-amber-100 text-amber-800',
    'closed'  => 'bg-gray-100 text-gray-600',
];

include __DIR__ . '/inc/header.php';
?>

<!-- Stat cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <a href="inquiries.php" class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow">
    <p class="text-sm text-gray-500">New inquiries</p>
    <p class="text-3xl font-bold <?= $inq_new > 0 ? 'text-brand' : 'text-gray-900' ?>"><?= $inq_new ?></p>
  </a>
  <div class="bg-white rounded-lg shadow p-6">
    <p class="text-sm text-gray-500">Total inquiries</p>
    <p class="text-3xl font-bold"><?= $inq_total ?></p>
  </div>
  <div class="bg-white rounded-lg shadow p-6">
    <p class="text-sm text-gray-500">Page views today</p>
    <p class="text-3xl font-bold"><?= $views_today ?></p>
  </div>
  <div class="bg-white rounded-lg shadow p-6">
    <p class="text-sm text-gray-500">Page views last 7 days</p>
    <p class="text-3xl font-bold"><?= $views_week ?></p>
  </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 items-start">

  <!-- Latest inquiries -->
  <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-semibold">Latest inquiries</h2>
      <a href="inquiries.php" class="text-sm text-brand hover:underline">View all</a>
    </div>
    <?php if (!$latest): ?>
      <p class="text-sm text-gray-400 py-6 text-center">No inquiries yet — they'll appear here when the contact form is used.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b border-gray-200">
              <th class="py-2 pr-4">When</th>
              <th class="py-2 pr-4">Name</th>
              <th class="py-2 pr-4">Email</th>
              <th class="py-2 pr-4">Service</th>
              <th class="py-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($latest as $inq): ?>
              <tr class="border-b border-gray-100 last:border-0">
                <td class="py-2.5 pr-4 text-gray-500 whitespace-nowrap"><?= h(time_ago($inq['created_at'])) ?></td>
                <td class="py-2.5 pr-4 font-medium"><?= h($inq['name']) ?></td>
                <td class="py-2.5 pr-4 text-gray-600"><?= h($inq['email']) ?></td>
                <td class="py-2.5 pr-4 text-gray-600"><?= h($inq['service_choice']) ?></td>
                <td class="py-2.5">
                  <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full <?= $status_badge[$inq['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                    <?= h($inq['status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent notifications -->
  <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-semibold">Recent notifications</h2>
      <a href="notifications.php" class="text-sm text-brand hover:underline">View all</a>
    </div>
    <?php if (!$notifs): ?>
      <p class="text-sm text-gray-400 py-6 text-center">Nothing yet.</p>
    <?php else: ?>
      <ul class="divide-y divide-gray-100">
        <?php foreach ($notifs as $n): ?>
          <li class="py-2.5 <?= $n['is_read'] ? 'opacity-60' : '' ?>">
            <p class="text-sm font-medium truncate"><?= h($n['title']) ?></p>
            <p class="text-xs text-gray-500 truncate"><?= h($n['message']) ?> · <?= h(time_ago($n['created_at'])) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
