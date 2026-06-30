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
