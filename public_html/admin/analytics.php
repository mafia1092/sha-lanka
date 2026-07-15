<?php
// admin/analytics.php — read-only traffic stats built from the page_views
// table (the public site logs one row per page view). No POST actions here.
require_once __DIR__ . '/../sys/auth.php';
$page_title = 'Analytics';

// Small helper: run a query with no user input and return the first value.
function pv_count(mysqli $conn, string $sql): int {
    return (int)$conn->query($sql)->fetch_assoc()['c'];
}

// ---- Stat cards -------------------------------------------------------
// Windows are calendar days: "7 days" = today + the 6 days before it.
$views_today   = pv_count($conn, "SELECT COUNT(*) AS c FROM page_views WHERE DATE(created_at) = CURDATE()");
$views_7d      = pv_count($conn, "SELECT COUNT(*) AS c FROM page_views WHERE created_at >= CURDATE() - INTERVAL 6 DAY");
$views_30d     = pv_count($conn, "SELECT COUNT(*) AS c FROM page_views WHERE created_at >= CURDATE() - INTERVAL 29 DAY");
$uniques_30d   = pv_count($conn, "SELECT COUNT(DISTINCT session_id) AS c FROM page_views WHERE created_at >= CURDATE() - INTERVAL 29 DAY");

// ---- Top pages (30 days) ----------------------------------------------
$top_pages = [];
$res = $conn->query("SELECT page_url, COUNT(*) AS c FROM page_views
                     WHERE created_at >= CURDATE() - INTERVAL 29 DAY
                     GROUP BY page_url ORDER BY c DESC LIMIT 10");
while ($row = $res->fetch_assoc()) $top_pages[] = $row;
$top_pages_max = $top_pages ? (int)$top_pages[0]['c'] : 0;

// ---- Devices (30 days) ------------------------------------------------
$devices = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];
$res = $conn->query("SELECT device_type, COUNT(*) AS c FROM page_views
                     WHERE created_at >= CURDATE() - INTERVAL 29 DAY
                     GROUP BY device_type");
while ($row = $res->fetch_assoc()) {
    if (isset($devices[$row['device_type']])) $devices[$row['device_type']] = (int)$row['c'];
}
$devices_total = array_sum($devices);

// ---- Top referrers (30 days) ------------------------------------------
// Group by full referer URL in SQL, then merge down to just the hostname
// in PHP (e.g. all google.com/search?... rows become one "google.com" line).
$ref_hosts = [];
$res = $conn->query("SELECT referer, COUNT(*) AS c FROM page_views
                     WHERE created_at >= CURDATE() - INTERVAL 29 DAY
                       AND referer IS NOT NULL AND referer != ''
                     GROUP BY referer ORDER BY c DESC LIMIT 200");
while ($row = $res->fetch_assoc()) {
    $host = parse_url($row['referer'], PHP_URL_HOST);
    if (!$host) $host = $row['referer']; // referer wasn't a full URL — show as-is
    $host = strtolower($host);
    $ref_hosts[$host] = ($ref_hosts[$host] ?? 0) + (int)$row['c'];
}
arsort($ref_hosts);
$ref_hosts = array_slice($ref_hosts, 0, 10, true);
$ref_max   = $ref_hosts ? max($ref_hosts) : 0;

// ---- Last 14 days ------------------------------------------------------
// Query the days that have rows, then fill in the missing days with 0 so
// the list always shows a full two weeks.
$by_day = [];
$res = $conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM page_views
                     WHERE created_at >= CURDATE() - INTERVAL 13 DAY
                     GROUP BY DATE(created_at)");
while ($row = $res->fetch_assoc()) $by_day[$row['d']] = (int)$row['c'];

$days = []; // newest first
for ($i = 0; $i <= 13; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $days[] = ['date' => $date, 'views' => $by_day[$date] ?? 0];
}
$days_max = max(array_column($days, 'views'));

$empty_note = 'No data yet — stats build up as visitors browse the site.';

include __DIR__ . '/inc/header.php';
?>

<!-- Stat cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $cards = [
      ['Views today', $views_today],
      ['Views 7 days', $views_7d],
      ['Views 30 days', $views_30d],
      ['Unique visitors 30 days', $uniques_30d],
  ];
  foreach ($cards as [$label, $value]): ?>
    <div class="bg-white rounded-lg shadow p-6">
      <p class="text-sm text-gray-500"><?= h($label) ?></p>
      <p class="text-3xl font-bold mt-1"><?= number_format($value) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Three side-by-side breakdowns -->
<div class="grid lg:grid-cols-3 gap-4 mb-6">

  <!-- Top pages -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="font-semibold mb-4">Top pages (30 days)</h2>
    <?php if (!$top_pages): ?>
      <p class="text-sm text-gray-400"><?= h($empty_note) ?></p>
    <?php else: ?>
      <ul class="space-y-3">
        <?php foreach ($top_pages as $p): $pct = $top_pages_max ? round($p['c'] / $top_pages_max * 100) : 0; ?>
          <li>
            <div class="flex justify-between text-sm gap-2">
              <span class="truncate" title="<?= h($p['page_url']) ?>"><?= h($p['page_url']) ?></span>
              <span class="text-gray-500 shrink-0"><?= number_format((int)$p['c']) ?></span>
            </div>
            <div class="bg-gray-100 rounded h-2 mt-1">
              <div class="bg-brand rounded h-2" style="width: <?= $pct ?>%"></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Devices -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="font-semibold mb-4">Devices (30 days)</h2>
    <?php if ($devices_total === 0): ?>
      <p class="text-sm text-gray-400"><?= h($empty_note) ?></p>
    <?php else: ?>
      <ul class="space-y-3">
        <?php foreach ($devices as $type => $count): $pct = round($count / $devices_total * 100); ?>
          <li>
            <div class="flex justify-between text-sm">
              <span class="capitalize"><?= h($type) ?></span>
              <span class="text-gray-500"><?= number_format($count) ?> · <?= $pct ?>%</span>
            </div>
            <div class="bg-gray-100 rounded h-2 mt-1">
              <div class="bg-brand rounded h-2" style="width: <?= $pct ?>%"></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Top referrers -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="font-semibold mb-4">Top referrers (30 days)</h2>
    <?php if (!$ref_hosts): ?>
      <p class="text-sm text-gray-400"><?= h($empty_note) ?></p>
    <?php else: ?>
      <ul class="space-y-3">
        <?php foreach ($ref_hosts as $host => $count): $pct = $ref_max ? round($count / $ref_max * 100) : 0; ?>
          <li>
            <div class="flex justify-between text-sm gap-2">
              <span class="truncate" title="<?= h($host) ?>"><?= h($host) ?></span>
              <span class="text-gray-500 shrink-0"><?= number_format($count) ?></span>
            </div>
            <div class="bg-gray-100 rounded h-2 mt-1">
              <div class="bg-brand rounded h-2" style="width: <?= $pct ?>%"></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<!-- Last 14 days -->
<div class="bg-white rounded-lg shadow p-6">
  <h2 class="font-semibold mb-4">Last 14 days</h2>
  <?php if ($days_max === 0): ?>
    <p class="text-sm text-gray-400"><?= h($empty_note) ?></p>
  <?php else: ?>
    <ul class="space-y-2">
      <?php foreach ($days as $i => $d): $pct = round($d['views'] / $days_max * 100); ?>
        <li class="flex items-center gap-3 text-sm">
          <span class="w-28 shrink-0 text-gray-600">
            <?= h(date('D j M', strtotime($d['date']))) ?><?= $i === 0 ? ' <span class="text-xs text-brand">(today)</span>' : '' ?>
          </span>
          <div class="grow bg-gray-100 rounded h-3">
            <div class="bg-brand rounded h-3" style="width: <?= $pct ?>%"></div>
          </div>
          <span class="w-12 shrink-0 text-right text-gray-500"><?= number_format($d['views']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
