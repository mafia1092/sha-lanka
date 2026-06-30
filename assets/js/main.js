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

    /* ---- Contact form (Web3Forms) ---- */
    var form = document.getElementById('contact-form');
    var status = document.getElementById('form-status');

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();

        var key = form.querySelector('input[name="access_key"]').value;
        if (!key || key === 'YOUR_WEB3FORMS_ACCESS_KEY') {
          showStatus('Form not configured yet — add your free Web3Forms access key to enable sending.', 'warn');
          return;
        }

        var btn = form.querySelector('button[type="submit"]');
        var original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Sending...';

        var data = new FormData(form);
        fetch('https://api.web3forms.com/submit', {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: data
        })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            if (json.success) {
              showStatus("Thank you! Your enquiry has been sent — we'll be in touch shortly.", 'ok');
              form.reset();
            } else {
              showStatus(json.message || 'Something went wrong. Please try again or contact us directly.', 'err');
            }
          })
          .catch(function () {
            showStatus('Network error. Please try again or reach us on WhatsApp.', 'err');
          })
          .finally(function () {
            btn.disabled = false;
            btn.textContent = original;
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
