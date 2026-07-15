<?php
// admin/inc/header.php — shared admin layout top. Pages must have already
// included ../sys/auth.php (login gate) and set $page_title.
require_once __DIR__ . '/../../sys/notifications.php';
$unread   = get_unread_count($conn);
$recents  = get_recent_notifications($conn, 8);
$__self   = basename($_SERVER['PHP_SELF'] ?? '');
$__nav    = [
    'index.php'         => 'Dashboard',
    'inquiries.php'     => 'Inquiries',
    'gallery.php'       => 'Gallery',
    'content.php'       => 'Content',
    'services.php'      => 'Services',
    'faq.php'           => 'FAQ',
    'analytics.php'     => 'Analytics',
    'settings.php'      => 'Settings',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($page_title ?? 'Admin') ?> — Sha Lanka Admin</title>
  <link rel="icon" type="image/svg+xml" href="../favicon.svg">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: {
      brand: '#1577BE', brandglow: '#2BA8E0', espresso: '#1C1A17', cream: '#F5F0E6'
    }}}};
  </script>
  <style>
    [x-cloak] { display: none; }
    .nav-item { padding: .45rem .8rem; border-radius: .4rem; font-size: .875rem; }
    .nav-item:hover { background: rgba(255,255,255,.08); }
    .nav-item.active { background: #1577BE; color: #fff; }
  </style>
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen">
<header class="bg-espresso text-cream shadow">
  <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
    <a href="index.php" class="font-bold tracking-wide text-lg mr-2">Sha Lanka <span class="text-brandglow">Admin</span></a>
    <nav class="flex flex-wrap items-center gap-1 text-cream/85 grow">
      <?php foreach ($__nav as $file => $label): ?>
        <a href="<?= $file ?>" class="nav-item<?= $__self === $file ? ' active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>

    <!-- Notification bell -->
    <div class="relative" id="bell-wrap">
      <button id="bell-btn" class="relative p-2 rounded hover:bg-white/10" aria-label="Notifications">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
        <?php if ($unread > 0): ?>
          <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"><?= $unread > 99 ? '99+' : $unread ?></span>
        <?php endif; ?>
      </button>
      <div id="bell-menu" class="hidden absolute right-0 mt-2 w-80 bg-white text-gray-800 rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
          <span class="font-semibold text-sm">Notifications</span>
          <a href="notifications.php" class="text-xs text-brand hover:underline">View all</a>
        </div>
        <?php if (!$recents): ?>
          <p class="px-4 py-6 text-sm text-gray-400 text-center">Nothing yet.</p>
        <?php else: foreach ($recents as $n): ?>
          <a href="<?= h($n['link'] ?: 'notifications.php') ?>" class="block px-4 py-2.5 border-b border-gray-50 hover:bg-gray-50 <?= $n['is_read'] ? 'opacity-60' : '' ?>">
            <span class="block text-sm font-medium truncate"><?= h($n['title']) ?></span>
            <span class="block text-xs text-gray-500 truncate"><?= h($n['message']) ?> · <?= h(time_ago($n['created_at'])) ?></span>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="flex items-center gap-3 text-sm text-cream/75">
      <span class="hidden sm:inline"><?= h($_SESSION['admin_username'] ?? '') ?></span>
      <a href="logout.php" class="hover:text-white underline underline-offset-2">Log out</a>
    </div>
  </div>
</header>
<script>
  (function () {
    var btn = document.getElementById('bell-btn'), menu = document.getElementById('bell-menu');
    if (!btn) return;
    btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
    document.addEventListener('click', function () { menu.classList.add('hidden'); });
  })();
</script>
<main class="max-w-6xl mx-auto px-4 py-8">
  <h1 class="text-2xl font-bold mb-6"><?= h($page_title ?? 'Admin') ?></h1>
