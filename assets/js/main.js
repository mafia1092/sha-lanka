/* ============================================================
   Sha Lanka — interactions
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    /* ---- Scroll animations (AOS) ---- */
    if (window.AOS) {
      AOS.init({ duration: 800, easing: 'ease-out-cubic', once: true, offset: 80 });
    }

    /* ---- Gallery lightbox ---- */
    if (window.GLightbox) {
      GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true });
    }

    /* ---- Card slideshows (horizontal sliding carousel, advances every 5s) ---- */
    document.querySelectorAll('[data-slideshow]').forEach(function (show, n) {
      var slides = Array.prototype.slice.call(show.querySelectorAll('.slide'));
      if (slides.length < 2) return;
      // Wrap the slides in a flex track and append a clone of the first for a seamless loop
      var track = document.createElement('div');
      track.className = 'slides-track';
      slides.forEach(function (s) { s.classList.remove('is-active'); track.appendChild(s); });
      var clone = slides[0].cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      track.appendChild(clone);
      show.appendChild(track);

      var total = slides.length;
      var i = 0;
      var advance = function () {
        i++;
        track.style.transition = 'transform .7s cubic-bezier(.4, 0, .2, 1)';
        track.style.transform = 'translateX(' + (-i * 100) + '%)';
        if (i === total) {
          // reached the clone (== first slide) — snap back to the real first, invisibly
          setTimeout(function () {
            track.style.transition = 'none';
            i = 0;
            track.style.transform = 'translateX(0)';
          }, 720);
        }
      };
      // small per-card offset so the cards don't all advance at the same instant
      setTimeout(function () { setInterval(advance, 5000); }, n * 700);
    });

    /* ---- Gallery mosaic: balanced equal-height columns + running "pop" swap ---- */
    var gMosaic = document.getElementById('gallery-mosaic');
    if (gMosaic) {
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      var thumbUrl = function (n) { return 'assets/img/gallery/g' + pad(n) + '.jpg'; };
      var largeUrl = function (n) { return 'assets/img/gallery/g' + pad(n) + '-lg.jpg'; };
      var rint = function (n) { return Math.floor(Math.random() * n); };

      // Gallery photos bucketed by orientation (the 2 square photos ride with landscape)
      var LAND = [1, 2, 3, 4, 5, 8, 9, 10, 11, 12, 13, 14, 15, 16, 18, 21, 22, 23, 33, 35, 36, 37, 38, 42];
      var PORT = [6, 7, 17, 19, 20, 24, 25, 26, 27, 28, 29, 30, 31, 32, 34, 39, 40, 41, 43, 44, 45];

      var gFrames = Array.prototype.slice.call(gMosaic.querySelectorAll('.gframe'));
      var landFrames = gFrames.filter(function (f) { return f.dataset.orient !== 'port'; });
      var portFrames = gFrames.filter(function (f) { return f.dataset.orient === 'port'; });

      var usedLand = {}, usedPort = {};
      gFrames.forEach(function (f) {
        if (f.dataset.orient === 'port') usedPort[+f.dataset.n] = true;
        else usedLand[+f.dataset.n] = true;
      });

      // Lay the frames into N equal-composition columns: every column gets the same
      // number of landscape + portrait frames, so all columns end at the SAME height
      // (flat bottom, no ragged gaps). Responsive: 4 columns wide, 2 when narrow.
      var currentCols = 0;
      var layout = function () {
        var cols = window.innerWidth >= 900 ? 4 : 2;
        if (cols === currentCols) return;
        currentCols = cols;
        var perL = Math.floor(landFrames.length / cols);
        var perP = Math.floor(portFrames.length / cols);
        gMosaic.innerHTML = '';
        var li = 0, pi = 0;
        for (var c = 0; c < cols; c++) {
          var col = document.createElement('div');
          col.className = 'gcol';
          var cl = landFrames.slice(li, li + perL); li += perL;
          var cp = portFrames.slice(pi, pi + perP); pi += perP;
          var maxk = Math.max(cl.length, cp.length);
          for (var k = 0; k < maxk; k++) {           // interleave, flip lead per column
            var a = (c % 2 === 0) ? cl[k] : cp[k];
            var b = (c % 2 === 0) ? cp[k] : cl[k];
            if (a) col.appendChild(a);
            if (b) col.appendChild(b);
          }
          gMosaic.appendChild(col);
        }
      };
      layout();
      var rz;
      window.addEventListener('resize', function () { clearTimeout(rz); rz = setTimeout(layout, 150); });

      var freeFrom = function (pool, used) {
        var n, guard = 0;
        do { n = pool[rint(pool.length)]; guard++; } while (used[n] && guard < 200);
        return n;
      };

      // Swap a frame to a fresh photo of ITS OWN orientation, with a soft fade + pop
      var swapFrame = function (f) {
        var isPort = f.dataset.orient === 'port';
        var pool = isPort ? PORT : LAND;
        var used = isPort ? usedPort : usedLand;
        var oldN = +f.dataset.n, nn = freeFrom(pool, used);
        if (nn === oldN) return;
        used[oldN] = false; used[nn] = true;
        var im = f.querySelector('img');
        im.style.opacity = '0'; im.style.transform = 'scale(.96)';
        setTimeout(function () {
          im.src = thumbUrl(nn); f.dataset.n = nn;
          im.style.opacity = '1'; im.style.transform = 'none';
        }, 280);
      };

      // Click a frame -> open the full original in the lightbox
      gFrames.forEach(function (f) {
        f.addEventListener('click', function () {
          if (window.GLightbox) {
            GLightbox({ elements: [{ href: largeUrl(+f.dataset.n), type: 'image' }] }).open();
          }
        });
      });

      // Running animation: every ~2.2s a random frame pops to a new same-orientation photo
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!reduce) {
        setInterval(function () { swapFrame(gFrames[rint(gFrames.length)]); }, 2200);
      }
    }

    /* ---- Sticky header state ---- */
    var header = document.getElementById('site-header');
    var onScroll = function () {
      if (window.scrollY > 40) header.classList.add('scrolled');
      else header.classList.remove('scrolled');
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    /* ---- Mobile menu ---- */
    var toggle = document.getElementById('menu-toggle');
    var menu = document.getElementById('mobile-menu');
    var iconOpen = document.getElementById('icon-open');
    var iconClose = document.getElementById('icon-close');

    var setMenu = function (open) {
      menu.classList.toggle('hidden', !open);
      iconOpen.classList.toggle('hidden', open);
      iconClose.classList.toggle('hidden', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    if (toggle) {
      toggle.addEventListener('click', function () {
        setMenu(menu.classList.contains('hidden'));
      });
      // Close after tapping a link
      menu.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { setMenu(false); });
      });
    }

    /* ---- FAQ accordion ---- */
    var faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(function (item) {
      var btn = item.querySelector('.faq-q');
      var panel = item.querySelector('.faq-a');
      btn.addEventListener('click', function () {
        var isOpen = item.classList.contains('open');
        // Close all
        faqItems.forEach(function (other) {
          other.classList.remove('open');
          other.querySelector('.faq-a').style.maxHeight = null;
          other.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
        });
        // Open this one
        if (!isOpen) {
          item.classList.add('open');
          panel.style.maxHeight = panel.scrollHeight + 'px';
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });

    /* ---- Current year ---- */
    var yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    /* ---- Contact form -> WhatsApp ---- */
    var form = document.getElementById('contact-form');
    var status = document.getElementById('form-status');
    var WA_NUMBER = '94777488746'; // Sha Lanka Travels WhatsApp (country code + number, no + or spaces)

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Let the browser enforce required fields (name, email, message) first.
        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
          return;
        }

        function val(name) {
          var el = form.querySelector('[name="' + name + '"]');
          return el ? String(el.value).trim() : '';
        }

        var name    = val('name');
        var email   = val('email');
        var phone   = val('phone');
        var choice  = val('choice');
        var message = val('message');

        var lines = [
          'Hello Sha Lanka Travels! I would like to make an enquiry.',
          '',
          'Name: ' + name,
          'Email: ' + email
        ];
        if (phone)  { lines.push('Phone: ' + phone); }
        if (choice) { lines.push('Interested in: ' + choice); }
        lines.push('');
        lines.push('Message:');
        lines.push(message);

        var waUrl = 'https://wa.me/' + WA_NUMBER + '?text=' + encodeURIComponent(lines.join('\n'));

        // Opens WhatsApp Web on desktop or the WhatsApp app on mobile.
        window.open(waUrl, '_blank', 'noopener');
        showStatus("Opening WhatsApp with your details prefilled — just tap Send in the chat to reach us.", 'ok');
      });
    }

    function showStatus(msg, type) {
      if (!status) return;
      var colors = {
        ok:   '#7bbf6a',
        err:  '#e06a5a',
        warn: '#F4A100',
        info: '#F5F0E6'
      };
      status.textContent = msg;
      status.style.color = colors[type] || colors.info;
      status.classList.remove('hidden');
    }
  });
})();
