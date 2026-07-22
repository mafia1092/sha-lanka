<?php
// index.php — the public one-page site. Layout lives here in HTML; the
// editable text/photos come from the database (managed in /admin).
require_once __DIR__ . '/sys/db_connect.php';
require_once __DIR__ . '/sys/helpers.php';
require_once __DIR__ . '/sys/track.php';

// Active gallery photos by orientation, in display order
$gallery = ['land' => [], 'port' => []];
$res = $conn->query("SELECT file_base, orientation FROM gallery_images WHERE is_active = 1 ORDER BY sort_order, id");
while ($row = $res->fetch_assoc()) {
    $gallery[$row['orientation']][] = $row['file_base'];
}

// Service cards keyed by slug (titles/descriptions/links editable in admin)
$GLOBALS['service_cards'] = [];
$res = $conn->query("SELECT * FROM service_cards");
while ($row = $res->fetch_assoc()) {
    $GLOBALS['service_cards'][$row['slug']] = $row;
}
function card($slug, $field, $fallback = '') {
    $v = $GLOBALS['service_cards'][$slug][$field] ?? '';
    // Empty DB value falls back too (e.g. a cleared link keeps the original)
    return h(($v === '' || $v === null) ? $fallback : $v);
}

// (asset() — versioned CSS/JS URLs — now lives in sys/helpers.php)
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sha Lanka Travels — Your Comprehensive Travel Partner in Sri Lanka</title>
  <meta name="description" content="Sha Lanka Travels — adventure-ready motorcycle, jeep and motorhome rentals, guided tours, and nationwide vehicle transport (car carrier) across Sri Lanka." />

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

  <!-- GLightbox (gallery lightbox) -->
  <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />

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
  <link rel="stylesheet" href="<?= asset('assets/css/styles.css') ?>" />

  <?php $hero_image = setting('hero_image'); if ($hero_image !== ''): ?>
  <!-- Hero photo uploaded in Admin -> Site Text (overrides the default in styles.css) -->
  <style>.hero-bg { background-image: url('assets/img/hero/<?= h($hero_image) ?>'); }</style>
  <?php endif; ?>
</head>

<body class="font-body bg-cream text-bark antialiased">

  <!-- ============ HEADER / NAV ============ -->
  <header id="site-header" class="fixed top-0 inset-x-0 z-50 transition-all duration-300">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-24" id="nav-inner">
        <!-- Brand: logo (swaps light/dark version on scroll) -->
        <a href="#home" class="flex items-center group" aria-label="Sha Lanka Travels — home">
          <img src="assets/img/logo-on-dark.png" alt="Sha Lanka Travels" class="brand-logo-img brand-logo-on-dark" />
          <img src="assets/img/logo-on-light.png" alt="Sha Lanka Travels" class="brand-logo-img brand-logo-on-light" />
        </a>

        <!-- Desktop nav -->
        <ul class="hidden lg:flex items-center gap-8 font-display tracking-wide text-[15px] uppercase">
          <li><a href="#about" class="nav-link">About</a></li>
          <li><a href="#gallery" class="nav-link">Gallery</a></li>
          <li><a href="#fleet" class="nav-link">Fleet</a></li>
          <li><a href="#tours" class="nav-link">Tours</a></li>
          <li><a href="#carrier" class="nav-link">Car Carrier</a></li>
          <li><a href="#contact" class="nav-link">Contact</a></li>
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
        <li><a href="#about" class="mobile-link">About</a></li>
        <li><a href="#gallery" class="mobile-link">Gallery</a></li>
        <li><a href="#fleet" class="mobile-link">Fleet</a></li>
        <li><a href="#tours" class="mobile-link">Tours</a></li>
        <li><a href="#carrier" class="mobile-link">Car Carrier</a></li>
        <li><a href="#contact" class="mobile-link">Contact</a></li>
      </ul>
    </div>
  </header>

  <!-- ============ HERO ============ -->
  <section id="home" class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <div class="absolute inset-0 hero-bg"></div>
    <div class="absolute inset-0 hero-overlay"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-6 text-center text-cream pt-24 pb-16">
      <p class="font-display tracking-[0.35em] uppercase text-brandglow text-sm mb-5" data-aos="fade-up"><?= t('hero_eyebrow', 'Your Comprehensive Travel Partner') ?></p>
      <h1 class="font-display font-700 uppercase leading-[0.95] text-5xl sm:text-6xl md:text-7xl mb-6" data-aos="fade-up" data-aos-delay="100">
        <?= t('hero_title_1', 'Explore Sri Lanka,') ?><br /><span class="text-brandglow"><?= t('hero_title_2', 'Your Way') ?></span>
      </h1>
      <p class="max-w-2xl mx-auto text-lg md:text-xl text-cream/85 mb-9 font-light" data-aos="fade-up" data-aos-delay="200">
        <?= t('hero_sub') ?>
      </p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-4" data-aos="fade-up" data-aos-delay="300">
        <a href="#fleet" class="btn-primary text-base">Explore the Fleet</a>
        <a href="#tours" class="btn-ghost text-base">Plan a Tour</a>
      </div>
    </div>

    <a href="#about" class="absolute bottom-6 left-1/2 -translate-x-1/2 z-10 text-cream/70 hover:text-brandglow transition-colors" aria-label="Scroll down">
      <svg class="w-8 h-8 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
    </a>
  </section>

  <!-- ============ ABOUT US ============ -->
  <section id="about" class="py-24 bg-cream">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-up">
        <p class="eyebrow">Who We Are</p>
        <h2 class="section-title">About Us</h2>
        <span class="title-underline"></span>
      </div>

      <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <!-- Left: Our Story -->
        <div data-aos="fade-right">
          <h3 class="font-display font-600 uppercase text-3xl mb-5 text-espresso">Our Story</h3>
          <p class="text-lg leading-relaxed text-bark/80 mb-5 font-light">
            <?= t('about_story_1') ?>
          </p>
          <p class="text-lg leading-relaxed text-bark/80 mb-8 font-light">
            <?= t('about_story_2') ?>
          </p>

          <ul class="clean-list space-y-4">
            <li>
              <span class="list-icon">01</span>
              <span><span class="font-display uppercase tracking-wide text-espresso">Rentals</span> — adventure-ready motorcycles, 4x4 jeeps and overland motorhomes, ready to drive away.</span>
            </li>
            <li>
              <span class="list-icon">02</span>
              <span><span class="font-display uppercase tracking-wide text-espresso">Guided Tours</span> — curated itineraries across the island, led by experienced local guides.</span>
            </li>
            <li>
              <span class="list-icon">03</span>
              <span><span class="font-display uppercase tracking-wide text-espresso">Car Carrier</span> — specialised vehicle transport and towing, anywhere in Sri Lanka.</span>
            </li>
          </ul>

          <div class="mt-9">
            <a href="#fleet" class="btn-primary">Discover the Fleet</a>
          </div>
        </div>

        <!-- Right: Interactive Google Map -->
        <div data-aos="fade-left">
          <div class="map-frame">
            <iframe
              title="Sha Lanka Travels location — 137/4 St. Francis Road, Welihena, Kochchikade"
              src="https://www.google.com/maps?q=137%2F4%20St%20Francis%20Road%2C%20Welihena%2C%20Kochchikade%2C%20Sri%20Lanka&z=15&output=embed"
              width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>
          <p class="text-center text-sm text-bark/60 mt-4">
            <svg class="inline w-4 h-4 -mt-0.5 text-brand" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            137/4, St. Francis Road, Welihena, Kochchikade, Sri Lanka
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ GALLERY ============ -->
  <section id="gallery" class="py-24 bg-sand/40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-up">
        <p class="eyebrow">In the Wild</p>
        <h2 class="section-title">Gallery</h2>
        <span class="title-underline"></span>
        <p class="section-sub"><?= t('gallery_sub') ?></p>
      </div>
    </div>

    <!-- Sits OUTSIDE the centred container so the wall runs edge to edge.
         (Deliberately not width:100vw — that includes the scrollbar and
         pushes the page sideways.) Every active photo is rendered as a flat
         list; main.js groups them into columns of 2 landscape + 2 portrait,
         repeats them to fill the screen, then clones the strip for a
         seamless sideways loop. -->
    <!-- No data-aos here: AOS animates a transform on the element, and a
         transformed/masked container around the sliding track triggers the
         iOS scramble bug (the heading above keeps its own fade-up). -->
    <div class="gallery-mosaic" id="gallery-mosaic">
      <?php foreach (['land', 'port'] as $o):
        $shape = $o === 'port' ? 'portrait' : 'landscape';
        foreach ($gallery[$o] as $i => $base):
          // First few of each orientation load eagerly (they fill the first
          // screenful of both rows); the rest arrive as they slide into view.
          $lazy = $i < 4 ? '' : ' loading="lazy"';
      ?>
      <button class="gframe <?= $shape ?>" data-orient="<?= $o ?>" data-base="<?= h($base) ?>" type="button" aria-label="View photo full size"><img src="assets/img/gallery/<?= h($base) ?>.jpg" alt="Sha Lanka Travels gallery photo"<?= $lazy ?> /></button>
      <?php endforeach; endforeach; ?>
    </div>
  </section>

  <!-- ============ RENTAL FLEET ============ -->
  <section id="fleet" class="py-24 bg-cream">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-up">
        <p class="eyebrow">Rentals</p>
        <h2 class="section-title">Our Rental Fleet</h2>
        <span class="title-underline"></span>
        <p class="section-sub"><?= t('fleet_sub') ?></p>
      </div>

      <div class="grid md:grid-cols-3 gap-8">
        <!-- Motorcycles -->
        <article class="fleet-card" data-aos="fade-up" data-aos-delay="0">
          <div class="fleet-media">
            <div class="slideshow" data-slideshow>
              <img src="assets/img/slides/moto-rental/1.jpg" class="slide is-active" alt="Sha Lanka Travels motorcycle rental" />
              <img src="assets/img/slides/moto-rental/2.jpg" class="slide" alt="Sha Lanka Travels motorcycle rental" loading="lazy" />
              <img src="assets/img/slides/moto-rental/3.jpg" class="slide" alt="Sha Lanka Travels motorcycle rental" loading="lazy" />
            </div>
            <span class="fleet-tag">Two Wheels</span>
            <img src="assets/img/badge-moto-rentals.png" alt="Negombo Motorcycle Tours — Motorcycle Rentals by Sha Lanka Travels" class="card-badge" loading="lazy" />
          </div>
          <div class="fleet-body">
            <h3 class="fleet-title"><?= card('moto-rental', 'title', 'Motorcycles') ?></h3>
            <p class="fleet-desc"><?= card('moto-rental', 'description') ?></p>
            <ul class="clean-list text-[15px] space-y-2.5">
              <li><span class="tick"></span> Adventure &amp; touring models</li>
              <li><span class="tick"></span> Helmets &amp; gear included</li>
              <li><span class="tick"></span> Daily &amp; weekly rates</li>
              <li><span class="tick"></span> Island-wide pickup options</li>
            </ul>
            <a href="<?= card('moto-rental', 'link_url', 'https://negombo-motorcycle-tours.com/') ?>" target="_blank" rel="noopener" class="card-cta">Visit Website →</a>
          </div>
        </article>

        <!-- Jeeps -->
        <article class="fleet-card" data-aos="fade-up" data-aos-delay="150">
          <div class="fleet-media">
            <div class="slideshow" data-slideshow>
              <img src="assets/img/slides/jeep-rental/1.jpg" class="slide is-active" alt="Sha Lanka Travels jeep rental" />
              <img src="assets/img/slides/jeep-rental/2.jpg" class="slide" alt="Sha Lanka Travels jeep rental" loading="lazy" />
              <img src="assets/img/slides/jeep-rental/3.jpg" class="slide" alt="Sha Lanka Travels jeep rental" loading="lazy" />
            </div>
            <span class="fleet-tag">4x4</span>
            <img src="assets/img/badge-jeep.png" alt="Sri Lanka Jeep Tours by Sha Lanka Travels" class="card-badge" loading="lazy" />
          </div>
          <div class="fleet-body">
            <h3 class="fleet-title"><?= card('jeep-rental', 'title', 'Jeeps') ?></h3>
            <p class="fleet-desc"><?= card('jeep-rental', 'description') ?></p>
            <ul class="clean-list text-[15px] space-y-2.5">
              <li><span class="tick"></span> Genuine 4-wheel drive</li>
              <li><span class="tick"></span> Self-drive or with driver</li>
              <li><span class="tick"></span> Safari &amp; off-road ready</li>
              <li><span class="tick"></span> Roof racks &amp; extras</li>
            </ul>
            <a href="<?= card('jeep-rental', 'link_url', 'https://srilankajeeprent.com/') ?>" target="_blank" rel="noopener" class="card-cta">Visit Website →</a>
          </div>
        </article>

        <!-- Motorhomes -->
        <article class="fleet-card" data-aos="fade-up" data-aos-delay="300">
          <div class="fleet-media">
            <div class="slideshow" data-slideshow>
              <img src="assets/img/slides/motorhome-rental/1.jpg" class="slide is-active" alt="Sha Lanka Travels motorhome rental" />
              <img src="assets/img/slides/motorhome-rental/2.jpg" class="slide" alt="Sha Lanka Travels motorhome rental" loading="lazy" />
              <img src="assets/img/slides/motorhome-rental/3.jpg" class="slide" alt="Sha Lanka Travels motorhome rental" loading="lazy" />
            </div>
            <span class="fleet-tag">Overland</span>
            <img src="assets/img/badge-motorhome.png" alt="Camper Explore — Motor Home Tours by Sha Lanka Travels" class="card-badge" loading="lazy" />
          </div>
          <div class="fleet-body">
            <h3 class="fleet-title"><?= card('motorhome-rental', 'title', 'Motorhomes') ?></h3>
            <p class="fleet-desc"><?= card('motorhome-rental', 'description') ?></p>
            <ul class="clean-list text-[15px] space-y-2.5">
              <li><span class="tick"></span> Sleeps 2–4 travellers</li>
              <li><span class="tick"></span> Kitchenette &amp; storage</li>
              <li><span class="tick"></span> Built for long journeys</li>
              <li><span class="tick"></span> Fully serviced &amp; insured</li>
            </ul>
            <a href="<?= card('motorhome-rental', 'link_url', 'https://camperexplore.com/index.html') ?>" target="_blank" rel="noopener" class="card-cta">Visit Website →</a>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- ============ GUIDED TOURS ============ -->
  <section id="tours" class="py-24 bg-espresso text-cream">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-up">
        <p class="eyebrow">Curated Itineraries</p>
        <h2 class="section-title text-cream">Guided Tours</h2>
        <span class="title-underline"></span>
        <p class="section-sub text-cream/75"><?= t('tours_sub') ?></p>
      </div>

      <div class="grid md:grid-cols-3 gap-8">
        <!-- Motorcycle Tours -->
        <article class="tour-card" data-aos="fade-up" data-aos-delay="0">
          <div class="fleet-media">
            <div class="slideshow" data-slideshow>
              <img src="assets/img/slides/moto-tours/1.jpg" class="slide is-active" alt="Sha Lanka Travels guided motorcycle tour" />
              <img src="assets/img/slides/moto-tours/2.jpg" class="slide" alt="Sha Lanka Travels guided motorcycle tour" loading="lazy" />
              <img src="assets/img/slides/moto-tours/3.jpg" class="slide" alt="Sha Lanka Travels guided motorcycle tour" loading="lazy" />
            </div>
            <span class="fleet-tag">Ride</span>
            <img src="assets/img/badge-royal-enfield.png" alt="Ceylon Royal Enfield Tours — Adventure Tours by Sha Lanka Travels" class="card-badge" loading="lazy" />
          </div>
          <div class="p-7">
            <h3 class="fleet-title text-cream"><?= card('moto-tours', 'title', 'Motorcycle Tours') ?></h3>
            <p class="text-cream/70 font-light mb-4"><?= card('moto-tours', 'description') ?></p>
            <ul class="clean-list clean-list--dark text-[15px] space-y-2.5">
              <li><span class="tick"></span> Multi-day routes</li>
              <li><span class="tick"></span> Support vehicle &amp; mechanic</li>
              <li><span class="tick"></span> Curated stays &amp; stops</li>
            </ul>
            <a href="<?= card('moto-tours', 'link_url', 'https://www.srilankamotorcycletours.com/') ?>" target="_blank" rel="noopener" class="card-cta">Visit Website →</a>
          </div>
        </article>

        <!-- Jeep Expeditions -->
        <article class="tour-card" data-aos="fade-up" data-aos-delay="150">
          <div class="fleet-media">
            <div class="slideshow" data-slideshow>
              <img src="assets/img/slides/jeep-tours/1.jpg" class="slide is-active" alt="Sha Lanka Travels jeep expedition" />
              <img src="assets/img/slides/jeep-tours/2.jpg" class="slide" alt="Sha Lanka Travels jeep expedition" loading="lazy" />
              <img src="assets/img/slides/jeep-tours/3.jpg" class="slide" alt="Sha Lanka Travels jeep expedition" loading="lazy" />
            </div>
            <span class="fleet-tag">Explore</span>
            <img src="assets/img/badge-jeep.png" alt="Sri Lanka Jeep Tours by Sha Lanka Travels" class="card-badge" loading="lazy" />
          </div>
          <div class="p-7">
            <h3 class="fleet-title text-cream"><?= card('jeep-tours', 'title', 'Jeep Expeditions') ?></h3>
            <p class="text-cream/70 font-light mb-4"><?= card('jeep-tours', 'description') ?></p>
            <ul class="clean-list clean-list--dark text-[15px] space-y-2.5">
              <li><span class="tick"></span> Wildlife &amp; safari routes</li>
              <li><span class="tick"></span> Expert local drivers</li>
              <li><span class="tick"></span> Off-road adventure trails</li>
            </ul>
            <a href="<?= card('jeep-tours', 'link_url', 'https://srilankajeeptours.com/') ?>" target="_blank" rel="noopener" class="card-cta">Visit Website →</a>
          </div>
        </article>

        <!-- Motorhome Journeys -->
        <article class="tour-card" data-aos="fade-up" data-aos-delay="300">
          <div class="fleet-media">
            <div class="slideshow" data-slideshow>
              <img src="assets/img/slides/motorhome-tours/1.jpg" class="slide is-active" alt="Sha Lanka Travels motorhome journey" />
              <img src="assets/img/slides/motorhome-tours/2.jpg" class="slide" alt="Sha Lanka Travels motorhome journey" loading="lazy" />
              <img src="assets/img/slides/motorhome-tours/3.jpg" class="slide" alt="Sha Lanka Travels motorhome journey" loading="lazy" />
            </div>
            <span class="fleet-tag">Roam</span>
            <img src="assets/img/badge-motorhome.png" alt="Camper Explore — Motor Home Tours by Sha Lanka Travels" class="card-badge" loading="lazy" />
          </div>
          <div class="p-7">
            <h3 class="fleet-title text-cream"><?= card('motorhome-tours', 'title', 'Motorhome Journeys') ?></h3>
            <p class="text-cream/70 font-light mb-4"><?= card('motorhome-tours', 'description') ?></p>
            <ul class="clean-list clean-list--dark text-[15px] space-y-2.5">
              <li><span class="tick"></span> Flexible multi-day loops</li>
              <li><span class="tick"></span> Scenic overnight spots</li>
              <li><span class="tick"></span> Route planning included</li>
            </ul>
            <a href="<?= card('motorhome-tours', 'link_url', 'https://camperexplore.com/tours.html') ?>" target="_blank" rel="noopener" class="card-cta">Visit Website →</a>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- ============ CAR CARRIER ============ -->
  <section id="carrier" class="py-24 bg-cream">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-up">
        <p class="eyebrow">Logistics</p>
        <h2 class="section-title">Car Carrier</h2>
        <span class="title-underline"></span>
        <p class="section-sub"><?= t('carrier_sub') ?></p>
      </div>

      <div class="grid lg:grid-cols-2 gap-12 items-center">
        <div data-aos="fade-right" class="order-2 lg:order-1">
          <h3 class="font-display font-600 uppercase text-3xl mb-5 text-espresso"><?= card('car-carrier', 'title', 'Your Vehicle, Delivered Safely') ?></h3>
          <p class="text-lg leading-relaxed text-bark/80 mb-6 font-light">
            <?= card('car-carrier', 'description') ?>
          </p>
          <ul class="clean-list grid sm:grid-cols-2 gap-x-8 gap-y-3 text-[15px]">
            <li><span class="tick"></span> Flatbed car carrier transport</li>
            <li><span class="tick"></span> Breakdown recovery &amp; towing</li>
            <li><span class="tick"></span> Island-wide delivery</li>
            <li><span class="tick"></span> Motorcycle &amp; 4x4 transport</li>
            <li><span class="tick"></span> Fully insured handling</li>
            <li><span class="tick"></span> Fast response dispatch</li>
          </ul>
          <div class="mt-8 flex flex-wrap gap-4">
            <a href="<?= card('car-carrier', 'link_url', 'https://carcarriernegombo.com/') ?>" target="_blank" rel="noopener" class="btn-primary">Visit the Site →</a>
          </div>
        </div>

        <div data-aos="fade-left" class="order-1 lg:order-2">
          <div class="carrier-media">
            <div class="slideshow" data-slideshow>
              <img src="assets/img/slides/car-carrier/1.jpg" class="slide is-active" alt="Sha Lanka Travels car carrier transport" />
              <img src="assets/img/slides/car-carrier/2.jpg" class="slide" alt="Sha Lanka Travels car carrier transport" loading="lazy" />
              <img src="assets/img/slides/car-carrier/3.jpg" class="slide" alt="Sha Lanka Travels car carrier transport" loading="lazy" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ CONTACT ============ -->
  <section id="contact" class="py-24 bg-espresso text-cream">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-16" data-aos="fade-up">
        <p class="eyebrow">Get in Touch</p>
        <h2 class="section-title text-cream">Contact Us</h2>
        <span class="title-underline"></span>
        <p class="section-sub text-cream/75"><?= t('contact_sub') ?></p>
      </div>

      <div class="grid lg:grid-cols-5 gap-10">
        <!-- Form -->
        <div class="lg:col-span-3" data-aos="fade-up">
          <form id="contact-form" class="contact-card">
            <!-- Submits to api/contact.php (saved to the inbox + emailed to us).
                 After success the visitor can also continue on WhatsApp. -->
            <?= csrfField() ?>
            <!-- Honeypot: humans never see this field; bots fill it -->
            <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
              <label for="website_url">Leave this field empty</label>
              <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off" />
            </div>
            <div class="grid sm:grid-cols-2 gap-5">
              <div>
                <label class="field-label" for="name">Name</label>
                <input class="field" id="name" name="name" type="text" required maxlength="120" placeholder="Your full name" />
              </div>
              <div>
                <label class="field-label" for="email">Email</label>
                <input class="field" id="email" name="email" type="email" required maxlength="190" placeholder="you@example.com" />
              </div>
              <div>
                <label class="field-label" for="phone">Phone</label>
                <input class="field" id="phone" name="phone" type="tel" maxlength="40" placeholder="+94 ..." />
              </div>
              <div>
                <label class="field-label" for="choice">Vehicle / Tour Choice</label>
                <select class="field" id="choice" name="choice">
                  <option value="" disabled selected>Select an option</option>
                  <option>Motorcycle Rental</option>
                  <option>Jeep Rental</option>
                  <option>Motorhome Rental</option>
                  <option>Motorcycle Tour</option>
                  <option>Jeep Expedition</option>
                  <option>Motorhome Journey</option>
                  <option>Car Carrier / Transport</option>
                  <option>Something else</option>
                </select>
              </div>
            </div>
            <div class="mt-5">
              <label class="field-label" for="message">Message</label>
              <textarea class="field" id="message" name="message" rows="5" required maxlength="5000" placeholder="Tell us your dates, group size and what you're looking for..."></textarea>
            </div>
            <button type="submit" class="btn-primary w-full mt-6 text-base justify-center">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
              Send Inquiry
            </button>
            <p class="text-xs text-cream/55 mt-3 text-center">We'll reply by email — or continue the chat on WhatsApp right after sending.</p>
            <p id="form-status" class="text-sm mt-4 text-center hidden"></p>
            <p id="form-wa-follow" class="text-sm mt-2 text-center hidden">
              <a href="#" target="_blank" rel="noopener" class="underline text-brandglow hover:text-cream">Prefer WhatsApp? Continue there →</a>
            </p>
          </form>
        </div>

        <!-- Direct details -->
        <div class="lg:col-span-2 space-y-5" data-aos="fade-up" data-aos-delay="150">
          <a href="tel:<?= h(preg_replace('/[^+\d]/', '', setting('business_phone', '+94777488746'))) ?>" class="contact-detail">
            <span class="contact-ic">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
            </span>
            <span><span class="contact-label">Call us</span><?= h(setting('business_phone', '+94 77 748 8746')) ?></span>
          </a>
          <a href="mailto:<?= h(setting('business_email', 'suranga007@yahoo.com')) ?>" class="contact-detail">
            <span class="contact-ic">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
            </span>
            <span><span class="contact-label">Email</span><?= h(setting('business_email', 'suranga007@yahoo.com')) ?></span>
          </a>
          <a id="whatsapp-link" href="<?= h(wa_link()) ?>" target="_blank" rel="noopener" class="contact-detail">
            <span class="contact-ic contact-ic--wa">
              <svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"/></svg>
            </span>
            <span><span class="contact-label">WhatsApp</span>Chat with us instantly</span>
          </a>
          <div class="contact-detail">
            <span class="contact-ic">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
            </span>
            <span><span class="contact-label">Location</span><?= h(setting('business_address', '137/4, St. Francis Road, Welihena, Kochchikade')) ?></span>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <a href="https://www.facebook.com/profile.php?id=61575452196155" target="_blank" rel="noopener" aria-label="Facebook" class="social-ic"><svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z"/></svg></a>
            <a href="https://www.instagram.com/sha_lanka_travels/" target="_blank" rel="noopener" aria-label="Instagram" class="social-ic"><svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.43.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.43.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.43-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.43-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 3.68a6.16 6.16 0 100 12.32 6.16 6.16 0 000-12.32zm0 10.16a4 4 0 110-8 4 4 0 010 8zm6.41-10.4a1.44 1.44 0 11-2.88 0 1.44 1.44 0 012.88 0z"/></svg></a>
            <a href="https://www.tiktok.com/@shalankatravelsofficial" target="_blank" rel="noopener" aria-label="TikTok" class="social-ic"><svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>
          </div>
        </div>
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
            <li><a href="#gallery" class="footer-link">Gallery</a></li>
            <li><a href="#fleet" class="footer-link">Rental Fleet</a></li>
            <li><a href="#tours" class="footer-link">Guided Tours</a></li>
            <li><a href="#carrier" class="footer-link">Car Carrier</a></li>
          </ul>
        </div>
        <div>
          <h4 class="footer-h">Company</h4>
          <ul class="space-y-2 font-light">
            <li><a href="#about" class="footer-link">About Us</a></li>
            <li><a href="faq.php" class="footer-link">FAQ</a></li>
            <li><a href="#contact" class="footer-link">Contact</a></li>
          </ul>
        </div>
      </div>
      <div class="py-9 border-b border-white/10">
        <h4 class="footer-h text-center" style="margin-bottom:1.5rem">Explore Our Family of Websites</h4>
        <div class="partner-logos">
          <a href="https://negombo-motorcycle-tours.com/" target="_blank" rel="noopener" class="partner-logo" aria-label="Negombo Motorcycle Tours"><img src="assets/img/partners/site-negombo-moto.png" alt="Negombo Motorcycle Tours" loading="lazy" /></a>
          <a href="https://www.srilankamotorcycletours.com/" target="_blank" rel="noopener" class="partner-logo" aria-label="Sri Lanka Motorcycle Tours"><img src="assets/img/partners/site-moto-tours.png" alt="Sri Lanka Motorcycle Tours" loading="lazy" /></a>
          <a href="https://srilankajeeprent.com/" target="_blank" rel="noopener" class="partner-logo" aria-label="Sri Lanka Jeep Rentals"><img src="assets/img/partners/site-jeep-rent.png" alt="Sri Lanka Jeep Rentals" loading="lazy" /></a>
          <a href="https://srilankajeeptours.com/" target="_blank" rel="noopener" class="partner-logo" aria-label="Sri Lanka Jeep Tours"><img src="assets/img/partners/site-jeep-tours.png" alt="Sri Lanka Jeep Tours" loading="lazy" /></a>
          <a href="https://camperexplore.com/index.html" target="_blank" rel="noopener" class="partner-logo" aria-label="Camper Explore"><img src="assets/img/partners/site-camper.png" alt="Camper Explore" loading="lazy" /></a>
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
  <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
  <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
