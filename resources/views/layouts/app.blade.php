@php
  $v = 177;
@endphp

<!DOCTYPE html>
<html lang="{{(request()->lang ? request()->lang : 'ru')}}">

<head>
  <!-- Global site tag (gtag.js) - Yandex webmaster -->
  <meta name="yandex-verification" content="61541709f8b93408" />
  <!-- Global site tag (gtag.js) - Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=UA-15543442-1"></script>

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-WWTHNS0FYG"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());

    gtag('config', 'G-WWTHNS0FYG');
  </script>

  <!-- Yandex.Metrika counter -->
  <script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
      m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments) };
      m[i].l = 1 * new Date(); k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
    })
      (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(4170664, "init", {
      clickmap: true,
      trackLinks: true,
      accurateTrackBounce: true,
      webvisor: true
    });
  </script>
  <noscript>
    <div><img src="https://mc.yandex.ru/watch/4170664" style="position:absolute; left:-9999px;" alt="" /></div>
  </noscript>
  <!-- /Yandex.Metrika counter -->

  {{--
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">--}}
  <link href="/public/css/bootstrap.min.css?v={{$v}}" rel="stylesheet" crossorigin="anonymous">

  <link rel="stylesheet" href="/public{{ mix('/css/app.css') }}">

  <meta charset="UTF-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="@yield('meta-description')">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" media="print"
    onload="this.media='all'">
  <link
    href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'">
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'" />

  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <link
      href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
      rel="stylesheet">
    <link
      href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap"
      rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
      integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ=="
      crossorigin="anonymous" referrerpolicy="no-referrer" />
  </noscript>
  @yield('style')
  @yield('canonical')

  <link rel="icon" href="/tiktak.ico" type="image/x-icon">
  <link rel="icon" type="image/png" href="/public/favicon-32x32.png" sizes="32x32">
  {{--
  <link rel="icon" type="image/png" href="/public/favicon-32x32.png" sizes="16x16">--}}
  {{--
  <link rel="icon" type="image/png" href="/public/favicon-32x32.png" sizes="64x64">--}}
  <link rel="apple-touch-icon" sizes="32x32" href="/public/favicon-32x32.png">

  <title>@yield('page-title')</title>
</head>

<body>
  <div class="general-wrapper">
    <div id="shadow"></div>
    @include('includes.header')
    {{-- <div class="row">--}}
      {{-- <div class="alert alert-danger text-center" role="alert">--}}
        {{-- Внимание! 7 ноября работает только салон по адресу Ложинская, 5 с 11:00 до 15:00.--}}
        {{-- </div>--}}
      {{-- </div>--}}
    <main>
      <!-- Main content1-->
      @yield('content')
    </main>

    @include('includes.footer')


    <!-- Bootstrap  -->
    {{--
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
      integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
      crossorigin="anonymous"></script>--}}
    {{--
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"
      integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q"
      crossorigin="anonymous"></script>--}}
    {{--
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns"
      crossorigin="anonymous"></script>--}}

    {{--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"
      integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p"
      crossorigin="anonymous"></script>--}}
    <script src="/public/js/popper.min.js?v={{$v}}" crossorigin="anonymous"></script>
    {{--
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"
      integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF"
      crossorigin="anonymous"></script>--}}
    <script src="/public/js/bootstrap.min.js?v={{$v}}" crossorigin="anonymous"></script>


    <script src="/public/js/app.js?v={{$v}}"></script>

    {{-- Favorites Manager (localStorage-based, session-scope) --}}
    <style>
      .l2-card_wishlist[data-model-id] {
        position: relative;
      }

      .l2-card_wishlist.active[data-model-id]::after {
        content: 'Убрать из избранного';
        position: absolute;
        bottom: calc(100% + 6px);
        right: 0;
        background: #333;
        color: #fff;
        font-size: 12px;
        font-weight: 400;
        padding: 4px 10px;
        border-radius: 4px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
        z-index: 100;
        font-family: 'Nunito', sans-serif;
      }

      .l2-card_wishlist.active[data-model-id]:hover::after {
        opacity: 1;
      }
    </style>
    <script>
      (function () {
        var STORAGE_KEY = 'tiktak_favorites';

        function getFavorites() {
          try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
          } catch (e) {
            return {};
          }
        }

        function saveFavorites(favs) {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(favs));
        }

        function updateBadges() {
          var favs = getFavorites();
          var count = Object.keys(favs).length;
          var badges = document.querySelectorAll('.favorites-badge');
          badges.forEach(function (badge) {
            if (count > 0) {
              badge.textContent = count;
              badge.style.display = 'inline-block';
            } else {
              badge.style.display = 'none';
            }
          });
        }

        function toggleFavorite(el) {
          var modelId = el.getAttribute('data-model-id');
          if (!modelId) return;

          var favs = getFavorites();

          if (favs[modelId]) {
            // Remove from favorites
            delete favs[modelId];
            el.classList.remove('active');
          } else {
            // Add to favorites
            favs[modelId] = {
              name: el.getAttribute('data-model-name') || '',
              pic: el.getAttribute('data-model-pic') || '',
              url: el.getAttribute('data-model-url') || ''
            };
            el.classList.add('active');
          }

          saveFavorites(favs);
          updateBadges();

          // If on favorites page, remove the card when un-favorited
          if (window.location.pathname === '/favorites') {
            if (!favs[modelId]) {
              var card = el.closest('.l2-card_container');
              if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(function () {
                  card.remove();
                  // Show empty state if no more favorites
                  var gridEl = document.getElementById('favorites-grid');
                  if (gridEl && gridEl.querySelectorAll('.l2-card_container').length === 0) {
                    var emptyEl = document.getElementById('favorites-empty');
                    if (emptyEl) emptyEl.style.display = 'block';
                    gridEl.style.display = 'none';
                  }
                }, 300);
              }
            }
          }
        }

        function initHearts() {
          var favs = getFavorites();
          var hearts = document.querySelectorAll('.l2-card_wishlist[data-model-id]');
          hearts.forEach(function (heart) {
            if (heart.getAttribute('data-fav-bound') === '1') return;
            heart.setAttribute('data-fav-bound', '1');

            var modelId = heart.getAttribute('data-model-id');
            // Restore active state
            if (favs[modelId]) {
              heart.classList.add('active');
            }
            heart.addEventListener('click', function (e) {
              e.preventDefault();
              e.stopPropagation();
              toggleFavorite(heart);
            });
          });
        }

        // Expose for favorites page
        window.TiktakFavorites = {
          initHearts: initHearts,
          updateBadges: updateBadges
        };

        // Init on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function () {
          initHearts();
          updateBadges();
        });
      })();
    </script>

    {{-- Cart Manager (localStorage-based) --}}
    <script>
      (function () {
        var STORAGE_KEY = 'tiktak_cart';
        var MAX_ITEMS = 10;

        function getCartData() {
          try {
            var data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            if (!data.items || !Array.isArray(data.items)) data.items = [];
            return data;
          } catch (e) {
            return { items: [] };
          }
        }

        function saveCartData(data) {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        }

        function getItems() {
          return getCartData().items;
        }

        function saveItems(items) {
          saveCartData({ items: items });
          updateBadges();
        }

        function getCount() {
          return getItems().length;
        }

        function hasItems() {
          return getCount() > 0;
        }

        /**
         * Add item to cart
         * @param {Object} item { modelId, name, picUrl, l3Url, dateFrom, days, tariffs }
         * @returns {boolean}
         */
        function addItem(item) {
          var data = getCartData();

          // Check if already in cart
          for (var i = 0; i < data.items.length; i++) {
            if (data.items[i].modelId == item.modelId) {
              showToast('Этот товар уже добавлен в корзину', 'warning');
              return false;
            }
          }

          // Check max items
          if (data.items.length >= MAX_ITEMS) {
            showToast('В корзине может быть не более ' + MAX_ITEMS + ' товаров', 'warning');
            return false;
          }

          item.addedAt = Date.now();
          data.items.push(item);
          saveCartData(data);
          updateBadges();
          showToast('Товар добавлен в корзину!', 'success');
          return true;
        }

        function removeByIndex(index) {
          var data = getCartData();
          if (index >= 0 && index < data.items.length) {
            data.items.splice(index, 1);
            saveCartData(data);
            updateBadges();
          }
        }

        function removeByModelId(modelId) {
          var data = getCartData();
          data.items = data.items.filter(function (item) {
            return item.modelId != modelId;
          });
          saveCartData(data);
          updateBadges();
        }

        function clear() {
          saveCartData({ items: [] });
          updateBadges();
        }

        /**
         * Calculate price from tariffs array
         * @param {Array} tariffs [[daysThreshold, dailyRate], ...]
         * @param {number} days
         * @returns {number}
         */
        function calculatePrice(tariffs, days) {
          if (!tariffs || tariffs.length === 0 || days < 1) return 0;

          // Sort ascending
          var sorted = tariffs.slice().sort(function (a, b) { return a[0] - b[0]; });

          var dailyRate = sorted[0][1];
          for (var i = 0; i < sorted.length; i++) {
            if (days >= sorted[i][0]) dailyRate = sorted[i][1];
          }

          var amount = Math.round(days * dailyRate * 100) / 100;

          // Ceiling check: don't exceed next tier total
          var sortedDesc = sorted.slice().sort(function (a, b) { return b[0] - a[0]; });
          var currentTierDays = sorted[0][0];
          for (var i = 0; i < sorted.length; i++) {
            if (days >= sorted[i][0]) currentTierDays = sorted[i][0];
          }

          for (var i = 0; i < sortedDesc.length; i++) {
            if (sortedDesc[i][0] > currentTierDays) {
              var ceilingAmount = sortedDesc[i][0] * sortedDesc[i][1];
              if (amount > ceilingAmount) amount = ceilingAmount;
            }
          }

          return Math.round(amount * 100) / 100;
        }

        function updateBadges() {
          var count = getCount();
          var badges = document.querySelectorAll('.cart-badge');
          badges.forEach(function (badge) {
            if (count > 0) {
              badge.textContent = count;
              badge.style.display = 'inline-block';
            } else {
              badge.style.display = 'none';
            }
          });
        }

        function showToast(message, type) {
          type = type || 'success';
          // Remove existing toast
          var existing = document.querySelector('.cart-toast');
          if (existing) existing.remove();

          var toast = document.createElement('div');
          toast.className = 'cart-toast ' + type;
          toast.textContent = message;
          document.body.appendChild(toast);

          // Trigger animation
          setTimeout(function () { toast.classList.add('show'); }, 10);

          // Auto-hide
          setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
          }, 3000);
        }

        function todayStr() {
          var d = new Date();
          return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
        }

        // Expose globally
        window.TiktakCart = {
          getItems: getItems,
          saveItems: saveItems,
          getCount: getCount,
          hasItems: hasItems,
          addItem: addItem,
          removeByIndex: removeByIndex,
          removeByModelId: removeByModelId,
          clear: clear,
          calculatePrice: calculatePrice,
          updateBadges: updateBadges,
          showToast: showToast,
          todayStr: todayStr,
        };

        // Init on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function () {
          updateBadges();
        });
      })();
    </script>

    <script src="/public{{ mix('/js/app.js') }}"></script>

    @if(isset($_COOKIE['tt_is_logged_in']))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          // Check for the cookie on the client side
          if (document.cookie.indexOf('tt_is_logged_in=1') === -1) {
            return;
          }

          console.log('Admin shortcut listener active (Shift+Alt+E)');

          document.addEventListener('keydown', function (event) {
            if (event.shiftKey && event.altKey && (event.code === 'KeyE')) {
              let editEl = document.querySelector('[data-bb-edit-url]');
              if (editEl) {
                let editUrl = editEl.getAttribute('data-bb-edit-url');
                let method = editEl.getAttribute('data-bb-edit-method') || 'GET';

                if (method.toUpperCase() === 'POST') {
                  let params = JSON.parse(editEl.getAttribute('data-bb-edit-params') || '{}');
                  let form = document.createElement('form');
                  form.method = 'POST';
                  form.action = editUrl;
                  form.target = '_blank';

                  for (let key in params) {
                    if (params.hasOwnProperty(key)) {
                      let input = document.createElement('input');
                      input.type = 'hidden';
                      input.name = key;
                      input.value = params[key];
                      form.appendChild(input);
                    }
                  }

                  document.body.appendChild(form);
                  form.submit();
                  document.body.removeChild(form);
                } else {
                  if (editUrl) window.open(editUrl, '_blank');
                }
              } else {
                console.log('Edit URL element not found');
              }
            }
          });
        });
      </script>
    @endif
  </div> {{-- end of general wrapper--}}
</body>

</html>