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

    /* ---- Gallery: TWO full-bleed rows of photos sliding sideways, forever ----
       Every photo has a fixed CSS size (height per row, width per orientation),
       so nothing the browser loads can ever change the wall's geometry — that
       invariant is what finally made the loop stable on iOS. Each row holds two
       identical copies of its photo sequence and the CSS slides it by exactly
       -50% (= one copy), so the wrap is seamless by construction. */
    var gMosaic = document.getElementById('gallery-mosaic');
    if (gMosaic) {
      var largeUrl = function (b) { return 'assets/img/gallery/' + b + '-lg.jpg'; };

      // Drift speed in pixels per second (used only for the DURATION — the loop
      // distance is the CSS -50%, so a slightly-off measurement can never
      // break the wrap, it only nudges the speed).
      var SPEED = 60;

      // index.php renders every active photo as a flat list of frames.
      var allFrames = Array.prototype.slice.call(gMosaic.querySelectorAll('.gframe'));

      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      var build = function () {
        if (!allFrames.length) return;

        // Distribute photos into 2 rows so each row mixes wide (landscape) and
        // narrow (portrait) frames: zip the two orientation lists together,
        // then deal them out in pairs — row 1 gets pair 1, row 2 gets pair 2,
        // and so on. ALL photos are shown.
        var land = allFrames.filter(function (f) { return f.dataset.orient !== 'port'; });
        var port = allFrames.filter(function (f) { return f.dataset.orient === 'port'; });
        var zipped = [];
        var n = Math.max(land.length, port.length);
        for (var i = 0; i < n; i++) {
          if (land[i]) zipped.push(land[i]);
          if (port[i]) zipped.push(port[i]);
        }
        var rowsFrames = [[], []];
        zipped.forEach(function (f, j) { rowsFrames[Math.floor(j / 2) % 2].push(f); });

        gMosaic.innerHTML = '';
        var mosaicW = gMosaic.getBoundingClientRect().width;

        rowsFrames.forEach(function (frames, r) {
          if (!frames.length) return;

          // One sequence = this row's photos, repeated until wider than the
          // screen (otherwise the wrap would expose empty space).
          var seq = document.createElement('div');
          seq.className = 'gseq';
          frames.forEach(function (f) { seq.appendChild(f); });

          var row = document.createElement('div');
          row.className = 'grow';
          row.appendChild(seq);
          gMosaic.appendChild(row);

          var guard = 0;
          while (seq.getBoundingClientRect().width < mosaicW * 1.15 && guard < 4) {
            frames.forEach(function (f) { seq.appendChild(f.cloneNode(true)); });
            guard++;
          }

          // Second identical copy -> -50% slides exactly one copy.
          row.appendChild(seq.cloneNode(true));

          if (!reduce) {
            var oneCopyWidth = row.getBoundingClientRect().width / 2;
            row.style.animationDuration = (oneCopyWidth / SPEED) + 's';
            row.classList.add('is-sliding');
          }
        });

        // The build changes the page height, so AOS must recompute its
        // scroll-reveal trigger positions — otherwise sections below the
        // gallery can end up with triggers past the page bottom and never
        // appear (the "everything after the gallery is missing" bug).
        if (window.AOS && AOS.refresh) AOS.refresh();
      };

      build();
      var rz, lastBuildWidth = window.innerWidth;
      window.addEventListener('resize', function () {
        clearTimeout(rz);
        rz = setTimeout(function () {
          // iOS Safari fires resize while scrolling (the address bar collapses,
          // changing only the HEIGHT) — rebuild only when the width changed.
          if (window.innerWidth === lastBuildWidth) return;
          lastBuildWidth = window.innerWidth;
          build();
        }, 200);
      });

      // One delegated listener, so cloned frames open the lightbox too.
      gMosaic.addEventListener('click', function (e) {
        var f = e.target.closest ? e.target.closest('.gframe') : null;
        if (!f || !window.GLightbox) return;
        GLightbox({ elements: [{ href: largeUrl(f.dataset.base), type: 'image' }] }).open();
      });
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
            } else if (res.body && {name: 1, email: 1, message: 1}[res.body.error]) {
              var fieldMsgs = {
                name: 'Please check the name field (up to 120 characters).',
                email: 'Please check your email address — it doesn\'t look valid.',
                message: 'Please check your message (it can be up to 5000 characters).'
              };
              showStatus(fieldMsgs[res.body.error], 'warn');
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
