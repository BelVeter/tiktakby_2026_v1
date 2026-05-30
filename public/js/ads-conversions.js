(function () {
  'use strict';

  function safeGtag() {
    if (typeof gtag === 'function') {
      gtag.apply(window, arguments);
    }
  }

  // ── Conversion 4: Tel click ──────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href^="tel:"]').forEach(function (el) {
      el.addEventListener('click', function () {
        safeGtag('event', 'conversion', {
          send_to: 'AW-18182822550/sDSkCKye87UcEJa1n95D',
        });
        safeGtag('event', 'phone_click');
      });
    });
  });

  // ── Conversion 1: Zvonok form (redirect-based, ?ck=zvonok) ──────────────

  // Enhanced Conversions: capture phone before form submits
  document.addEventListener('DOMContentLoaded', function () {
    var zvonokForm = document.querySelector('form.back-coll-modal');
    if (!zvonokForm) return;
    zvonokForm.addEventListener('submit', function () {
      var phone = zvonokForm.querySelector('input[name="phone"]');
      if (phone && phone.value) {
        safeGtag('set', 'user_data', { phone_number: phone.value });
      }
    });
  });

  // Fire conversion when landing back with ?ck=zvonok
  document.addEventListener('DOMContentLoaded', function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('ck') !== 'zvonok') return;

    safeGtag('event', 'conversion', {
      send_to: 'AW-18182822550/u1JiCKOe87UcEJa1n95D',
    });
    safeGtag('event', 'form_zvonok_submit');

    // Remove ?ck= from URL so F5 doesn't re-fire
    params.delete('ck');
    var newSearch = params.toString();
    var newUrl = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
    window.history.replaceState(null, '', newUrl);
  });

  // ── Conversion 2: Cart checkout (AJAX XHR patch) ─────────────────────────
  // Intercepts XHR to /cart/checkout and fires on success response.
  (function () {
    var _open = XMLHttpRequest.prototype.open;
    var _send = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
      this._tiktak_url = url;
      return _open.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
      var self = this;
      var origOnload = this.onload;
      this.onload = function (e) {
        if (self._tiktak_url && self._tiktak_url.indexOf('/cart/checkout') !== -1 && self.status === 200) {
          try {
            var data = JSON.parse(self.responseText);
            if (data && data.success !== false) {
              var phoneEl = document.getElementById('cart-phone');
              if (phoneEl && phoneEl.value) {
                safeGtag('set', 'user_data', { phone_number: phoneEl.value });
              }
              safeGtag('event', 'conversion', {
                send_to: 'AW-18182822550/QN1cCKme87UcEJa1n95D',
              });
              safeGtag('event', 'purchase');
            }
          } catch (err) { /* silent */ }
        }
        if (origOnload) origOnload.call(this, e);
      };
      return _send.apply(this, arguments);
    };
  })();

  // ── Conversion 3: Bron form (#orderModal) ───────────────────────────────
  // bronFormValidate() in l3.js calls form.submit() directly — this does NOT
  // fire the 'submit' event. Override the instance method to intercept it.
  document.addEventListener('DOMContentLoaded', function () {
    var bronForm = document.getElementById('orderModal');
    if (!bronForm) return;

    var _nativeSubmit = HTMLFormElement.prototype.submit;
    bronForm.submit = function () {
      var phoneEl = bronForm.querySelector('[name="phone"]');
      if (phoneEl && phoneEl.value) {
        safeGtag('set', 'user_data', { phone_number: phoneEl.value });
      }
      safeGtag('event', 'conversion', {
        send_to: 'AW-18182822550/POkMCKae87UcEJa1n95D',
      });
      safeGtag('event', 'booking_submit');
      _nativeSubmit.call(this);
    };
  });

})();
