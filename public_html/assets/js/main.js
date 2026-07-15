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
      var thumbUrl = function (b) { return 'assets/img/gallery/' + b + '.jpg'; };
      var largeUrl = function (b) { return 'assets/img/gallery/' + b + '-lg.jpg'; };
      var rint = function (n) { return Math.floor(Math.random() * n); };

      // Photo lists come from the database — index.php injects window.GALLERY.
      // The hardcoded arrays below are only a fallback if that injection is missing.
      var LAND = (window.GALLERY && window.GALLERY.land && window.GALLERY.land.length) ? window.GALLERY.land
        : ['g01','g02','g03','g04','g05','g08','g09','g10','g11','g12','g13','g14','g15','g16','g18','g21','g22','g23','g33','g35','g36','g37','g38','g42'];
      var PORT = (window.GALLERY && window.GALLERY.port && window.GALLERY.port.length) ? window.GALLERY.port
        : ['g06','g07','g17','g19','g20','g24','g25','g26','g27','g28','g29','g30','g31','g32','g34','g39','g40','g41','g43','g44','g45'];

      var gFrames = Array.prototype.slice.call(gMosaic.querySelectorAll('.gframe'));
      var landFrames = gFrames.filter(function (f) { return f.dataset.orient !== 'port'; });
      var portFrames = gFrames.filter(function (f) { return f.dataset.orient === 'port'; });

      var usedLand = {}, usedPort = {};
      gFrames.forEach(function (f) {
        if (f.dataset.orient === 'port') usedPort[f.dataset.base] = true;
        else usedLand[f.dataset.base] = true;
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
        var oldB = f.dataset.base, nb = freeFrom(pool, used);
        if (nb === oldB) return;
        used[oldB] = false; used[nb] = true;
        var im = f.querySelector('img');
        im.style.opacity = '0'; im.style.transform = 'scale(.96)';
        setTimeout(function () {
          im.src = thumbUrl(nb); f.dataset.base = nb;
          im.style.opacity = '1'; im.style.transform = 'none';
        }, 280);
      };

      // Click a frame -> open the full original in the lightbox
      gFrames.forEach(function (f) {
        f.addEventListener('click', function () {
          if (window.GLightbox) {
            GLightbox({ elements: [{ href: largeUrl(f.dataset.base), type: 'image' }] }).open();
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

    /* ---- Contact form -> backend inbox (api/contact.php) ---- */
    var form = document.getElementById('contact-form');
    var status = document.getElementById('form-status');
    var waFollow = document.getElementById('form-wa-follow');

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

        // Same message, offered as an optional WhatsApp follow-up after sending
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

        // WhatsApp number comes from the DB-rendered contact link (fallback hardcoded)
        var waLinkEl = document.getElementById('whatsapp-link');
        var waBase = (waLinkEl && waLinkEl.href) ? waLinkEl.href.split('?')[0] : 'https://wa.me/94777488746';
        var waUrl = waBase + '?text=' + encodeURIComponent(lines.join('\n'));

        var showWa = function () {
          if (!waFollow) return;
          var a = waFollow.querySelector('a');
          if (a) a.href = waUrl;
          waFollow.classList.remove('hidden');
        };
        var fail = function () {
          showStatus('Something went wrong sending your inquiry — please try again, or message us directly on WhatsApp:', 'err');
          showWa();
        };

        var btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        fetch('api/contact.php', { method: 'POST', body: new FormData(form) })
          .then(function (r) {
            return r.json().then(function (j) { return { code: r.status, body: j }; });
          })
          .then(function (res) {
            if (btn) btn.disabled = false;
            if (res.body && res.body.ok) {
              showStatus("Thanks, " + name + "! Your inquiry has been sent — we'll get back to you by email soon.", 'ok');
              showWa();
              form.reset();
            } else if (res.code === 429) {
              showStatus('Too many messages in a short time — please wait a few minutes, or continue on WhatsApp:', 'warn');
              showWa();
            } else if (res.body && res.body.error === 'expired') {
              showStatus('This page was open for a long time and the form expired — please reload the page and try again.', 'err');
            } else {
              fail();
            }
          })
          .catch(function () {
            if (btn) btn.disabled = false;
            fail();
          });
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
