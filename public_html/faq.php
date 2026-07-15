<?php
// faq.php — FAQ page; questions/answers come from the database (Admin -> FAQ).
require_once __DIR__ . '/sys/db_connect.php';
require_once __DIR__ . '/sys/helpers.php';
require_once __DIR__ . '/sys/track.php';

$faq_items = [];
$res = $conn->query("SELECT question, answer FROM faq_items WHERE is_active = 1 ORDER BY sort_order, id");
while ($row = $res->fetch_assoc()) {
    $faq_items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FAQ — Sha Lanka Travels</title>
  <meta name="description" content="Frequently asked questions about Sha Lanka Travels — rentals, guided tours and nationwide vehicle transport (car carrier) across Sri Lanka." />

  <!-- Favicon (brand "S" mark) -->
  <link rel="icon" href="favicon.ico" sizes="any" />
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="favicon-16.png" />
  <link rel="apple-touch-icon" href="apple-touch-icon.png" />

  <!-- Google Fonts: Oswald (display) + Inter (body) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />

  <!-- AOS scroll animations -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />

  <!-- Tailwind CSS (Play CDN) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            cream:    '#F5F0E6',
            sand:     '#D9C3A3',
            clay:     '#B5532A',
            olive:    '#556B2F',
            espresso: '#1C1A17',
            bark:     '#2B2620',
            brand:    '#1577BE',
            brandglow:'#2BA8E0',
          },
          fontFamily: {
            display: ['Oswald', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            body:    ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
          },
        },
      },
    };
  </script>

  <!-- Custom styles -->
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>

<body class="font-body bg-cream text-bark antialiased">

  <!-- ============ HEADER / NAV ============ -->
  <header id="site-header" class="fixed top-0 inset-x-0 z-50 transition-all duration-300">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-24" id="nav-inner">
        <!-- Brand: logo (swaps light/dark version on scroll) -->
        <a href="index.php#home" class="flex items-center group" aria-label="Sha Lanka Travels — home">
          <img src="assets/img/logo-on-dark.png" alt="Sha Lanka Travels" class="brand-logo-img brand-logo-on-dark" />
          <img src="assets/img/logo-on-light.png" alt="Sha Lanka Travels" class="brand-logo-img brand-logo-on-light" />
        </a>

        <!-- Desktop nav -->
        <ul class="hidden lg:flex items-center gap-8 font-display tracking-wide text-[15px] uppercase">
          <li><a href="index.php#about" class="nav-link">About</a></li>
          <li><a href="index.php#fleet" class="nav-link">Fleet</a></li>
          <li><a href="index.php#tours" class="nav-link">Tours</a></li>
          <li><a href="index.php#carrier" class="nav-link">Car Carrier</a></li>
          <li><a href="index.php#gallery" class="nav-link">Gallery</a></li>
          <li><a href="index.php#contact" class="nav-link">Contact</a></li>
        </ul>

        <!-- Mobile toggle -->
        <button id="menu-toggle" class="lg:hidden inline-flex items-center justify-center w-11 h-11 rounded-md nav-link" aria-label="Open menu" aria-expanded="false">
          <svg id="icon-open" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
          <svg id="icon-close" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </nav>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="lg:hidden hidden bg-espresso/98 backdrop-blur border-t border-white/10">
      <ul class="px-6 py-4 space-y-1 font-display tracking-wide uppercase text-cream">
        <li><a href="index.php#about" class="mobile-link">About</a></li>
        <li><a href="index.php#fleet" class="mobile-link">Fleet</a></li>
        <li><a href="index.php#tours" class="mobile-link">Tours</a></li>
        <li><a href="index.php#carrier" class="mobile-link">Car Carrier</a></li>
        <li><a href="index.php#gallery" class="mobile-link">Gallery</a></li>
        <li><a href="index.php#contact" class="mobile-link">Contact</a></li>
      </ul>
    </div>
  </header>

  <!-- ============ PAGE BANNER ============ -->
  <section class="relative bg-espresso text-cream pt-40 pb-20 overflow-hidden">
    <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <p class="eyebrow">Good to Know</p>
      <h1 class="section-title text-cream">Frequently Asked Questions</h1>
      <span class="title-underline mx-auto"></span>
      <p class="section-sub text-cream/75"><?= t('faq_banner_sub') ?></p>
    </div>
  </section>

  <!-- ============ FAQ ============ -->
  <section id="faq" class="py-24 bg-cream">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="space-y-4" id="faq-list">
        <?php foreach ($faq_items as $item): ?>
        <div class="faq-item" data-aos="fade-up">
          <button class="faq-q" aria-expanded="false">
            <span><?= h($item['question']) ?></span>
            <svg class="faq-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div class="faq-a"><p><?= nl2br(h($item['answer'])) ?></p></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-14" data-aos="fade-up">
        <a href="index.php" class="btn-primary">← Back to Home</a>
      </div>
    </div>
  </section>

  <!-- ============ FOOTER ============ -->
  <footer class="bg-bark text-cream/80 pt-16 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-3 gap-10 pb-10 border-b border-white/10">
        <div>
          <img src="assets/img/logo-on-dark.png" alt="Sha Lanka Travels" class="footer-logo mb-4" />
          <p class="font-light text-cream/70 max-w-xs"><?= t('footer_tagline') ?></p>
          <div class="flex items-center gap-3 mt-5">
            <a href="https://www.facebook.com/profile.php?id=61575452196155" target="_blank" rel="noopener" aria-label="Facebook" class="social-ic"><svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z"/></svg></a>
            <a href="https://www.instagram.com/sha_lanka_travels/" target="_blank" rel="noopener" aria-label="Instagram" class="social-ic"><svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.43.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.43.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.43-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.43-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 3.68a6.16 6.16 0 100 12.32 6.16 6.16 0 000-12.32zm0 10.16a4 4 0 110-8 4 4 0 010 8zm6.41-10.4a1.44 1.44 0 11-2.88 0 1.44 1.44 0 012.88 0z"/></svg></a>
            <a href="https://www.tiktok.com/@shalankatravelsofficial" target="_blank" rel="noopener" aria-label="TikTok" class="social-ic"><svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>
          </div>
        </div>
        <div>
          <h4 class="footer-h">Explore</h4>
          <ul class="space-y-2 font-light">
            <li><a href="index.php#fleet" class="footer-link">Rental Fleet</a></li>
            <li><a href="index.php#tours" class="footer-link">Guided Tours</a></li>
            <li><a href="index.php#carrier" class="footer-link">Car Carrier</a></li>
            <li><a href="index.php#gallery" class="footer-link">Gallery</a></li>
          </ul>
        </div>
        <div>
          <h4 class="footer-h">Company</h4>
          <ul class="space-y-2 font-light">
            <li><a href="index.php#about" class="footer-link">About Us</a></li>
            <li><a href="faq.php" class="footer-link">FAQ</a></li>
            <li><a href="index.php#contact" class="footer-link">Contact</a></li>
          </ul>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-6 text-sm text-cream/60">
        <p>&copy; <span id="year"></span> Sha Lanka Travels. All rights reserved.</p>
        <p class="font-light">Built for the adventure-ready.</p>
      </div>
    </div>
  </footer>

  <!-- Scripts -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>
