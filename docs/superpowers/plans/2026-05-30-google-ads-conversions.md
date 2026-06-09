# Google Ads Conversions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Настроить отслеживание 4 конверсий Google Ads (AW-18182822550) на tiktak.by без замедления сайта и без изменения существующих GA4/Yandex.Metrika тегов.

**Architecture:** Один файл `public/js/ads-conversions.js` содержит весь JS для конверсий; подключается в `app.blade.php` после существующих скриптов с cache-busting. Google Ads config добавляется одной строкой в существующий gtag init-блок (не новый тег). Бэкенд: только исправление URL-редиректа в `ZvonokController`.

**Tech Stack:** PHP/Laravel 8, Blade, vanilla JS (ES5-совместимый, как весь остальной JS проекта), gtag.js (уже загружен).

---

## Карта файлов

| Файл | Действие | Что меняется |
|------|----------|--------------|
| `app/Http/Controllers/ZvonokController.php` | Modify | Исправить строки 62 и 80: null referer + query string concatenation |
| `resources/views/layouts/app.blade.php` | Modify | +1 строка в gtag init, +1 переменная cache-bust, +1 тег script |
| `public/js/ads-conversions.js` | Create | Все 4 конверсии |

---

## Task 1: Исправить ZvonokController — редирект с `?ck=zvonok`

**Files:**
- Modify: `app/Http/Controllers/ZvonokController.php:62`
- Modify: `app/Http/Controllers/ZvonokController.php:80`

Текущий код (строка 62):
```php
return Redirect::to($req->header('referer'));
```

Проблемы: (1) `referer` может быть `null` → PHP-ошибка при конкатенации; (2) если referer уже содержит `?param=val`, добавление `?ck=zvonok` сломает URL.

- [ ] **Step 1.1: Написать Feature-тест (запускаем чтобы убедиться что он падает)**

Создать файл `tests/Feature/ZvonokRedirectTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ZvonokRedirectTest extends TestCase
{
    public function test_redirect_appends_ck_param_clean_url()
    {
        $response = $this->withHeaders(['Referer' => 'https://tiktak.by/ru/velosipedy/'])
            ->post('/zvonok', ['fio' => 'Тест', 'phone' => '+375447454040', 'info' => '']);
        $this->assertTrue(
            str_contains($response->headers->get('Location') ?? '', 'ck=zvonok'),
            'Location header must contain ck=zvonok'
        );
    }

    public function test_redirect_appends_ck_param_url_with_existing_query()
    {
        $response = $this->withHeaders(['Referer' => 'https://tiktak.by/ru/velosipedy/?age=3'])
            ->post('/zvonok', ['fio' => 'Тест', 'phone' => '+375447454040', 'info' => '']);
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('ck=zvonok', $location);
        $this->assertStringNotContainsString('?ck=zvonok', $location,
            'Should use & not ? when query string already present');
    }

    public function test_redirect_falls_back_to_root_when_no_referer()
    {
        $response = $this->post('/zvonok', ['fio' => 'Тест', 'phone' => '+375447454040', 'info' => '']);
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('ck=zvonok', $location);
    }
}
```

- [ ] **Step 1.2: Запустить тест, убедиться что падает**

```bash
cd /home/dmitry/sites/tiktakby
docker-compose exec app php artisan test --filter=ZvonokRedirectTest
```

Ожидаем: FAIL (все 3 теста)

- [ ] **Step 1.3: Исправить `addCall()` в ZvonokController.php**

Найти строку 62:
```php
    return Redirect::to($req->header('referer'));
```

Заменить на:
```php
    $referer = $req->header('referer') ?: '/';
    $separator = parse_url($referer, PHP_URL_QUERY) ? '&' : '?';
    return Redirect::to($referer . $separator . 'ck=zvonok');
```

- [ ] **Step 1.4: Исправить `addSubscription()` в ZvonokController.php (строка 80)**

Найти:
```php
    return Redirect::to($req->header('referer'));
```

Заменить на:
```php
    $referer = $req->header('referer') ?: '/';
    return Redirect::to($referer);
```

> Note: подписка не является конверсией Ads, поэтому `?ck=` не добавляем, но null-safety нужна.

- [ ] **Step 1.5: Запустить тест, убедиться что проходит**

```bash
docker-compose exec app php artisan test --filter=ZvonokRedirectTest
```

Ожидаем: 3 PASSED

- [ ] **Step 1.6: Commit**

```bash
git add app/Http/Controllers/ZvonokController.php tests/Feature/ZvonokRedirectTest.php
git commit -m "$(cat <<'EOF'
fix(zvonok): safe referer redirect with ?ck=zvonok tracking param

Fixes null referer (privacy extensions, direct POST) and broken URL
when referer already contains query string parameters.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Добавить Google Ads config в gtag init-блок

**Files:**
- Modify: `resources/views/layouts/app.blade.php:19-25` (gtag init block)
- Modify: `resources/views/layouts/app.blade.php:1-6` (@php block)

**Почему одна строка, не новый тег:** gtag.js уже загружен для G-WWTHNS0FYG. Добавление второго `<script src="gtag.js?id=AW-...">` грузит одну и ту же библиотеку дважды. Правильный способ — добавить `gtag('config', 'AW-...')` в существующий init-блок.

- [ ] **Step 2.1: Добавить `gtag('config', 'AW-18182822550')` в init-блок**

Найти в `app.blade.php` (строки 19-25):
```js
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());

    gtag('config', 'G-WWTHNS0FYG');
  </script>
```

Заменить на:
```js
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());

    gtag('config', 'G-WWTHNS0FYG');
    gtag('config', 'AW-18182822550');
  </script>
```

- [ ] **Step 2.2: Добавить переменную cache-busting в @php блок**

Найти блок (строки 1-6):
```php
@php
  $v_css_bootstrap = file_exists(public_path('css/bootstrap.min.css')) ? filemtime(public_path('css/bootstrap.min.css')) : 1;
  $v_js_popper = file_exists(public_path('js/popper.min.js')) ? filemtime(public_path('js/popper.min.js')) : 1;
  $v_js_bootstrap = file_exists(public_path('js/bootstrap.min.js')) ? filemtime(public_path('js/bootstrap.min.js')) : 1;
  $v_js_app = file_exists(public_path('js/app.js')) ? filemtime(public_path('js/app.js')) : 1;
@endphp
```

Заменить на:
```php
@php
  $v_css_bootstrap = file_exists(public_path('css/bootstrap.min.css')) ? filemtime(public_path('css/bootstrap.min.css')) : 1;
  $v_js_popper = file_exists(public_path('js/popper.min.js')) ? filemtime(public_path('js/popper.min.js')) : 1;
  $v_js_bootstrap = file_exists(public_path('js/bootstrap.min.js')) ? filemtime(public_path('js/bootstrap.min.js')) : 1;
  $v_js_app = file_exists(public_path('js/app.js')) ? filemtime(public_path('js/app.js')) : 1;
  $v_js_ads = file_exists(public_path('js/ads-conversions.js')) ? filemtime(public_path('js/ads-conversions.js')) : 1;
@endphp
```

- [ ] **Step 2.3: Commit (промежуточный — до создания JS-файла)**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "$(cat <<'EOF'
feat(ads): add Google Ads AW-18182822550 config to gtag init block

Single gtag('config') line reuses the already-loaded gtag.js library.
Also adds $v_js_ads cache-busting variable for upcoming ads-conversions.js.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Создать `public/js/ads-conversions.js`

**Files:**
- Create: `public/js/ads-conversions.js`

Архитектура файла: один IIFE, внутри 4 независимых секции (каждая в `DOMContentLoaded` или сразу). Весь код — ES5-совместимый vanilla JS (как `app.js` в проекте).

**Conversion IDs:**
- Conversion 1 (zvonok form): `AW-18182822550/u1JiCKOe87UcEJa1n95D`
- Conversion 2 (cart checkout): `AW-18182822550/QN1cCKme87UcEJa1n95D`
- Conversion 3 (bron form): `AW-18182822550/POkMCKae87UcEJa1n95D`
- Conversion 4 (tel click): `AW-18182822550/sDSkCKye87UcEJa1n95D`

- [ ] **Step 3.1: Создать файл со скелетом и Conversion 4 (tel click — самый простой)**

Создать `public/js/ads-conversions.js`:

```js
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

})();
```

- [ ] **Step 3.2: Добавить include в app.blade.php и проверить в браузере**

Найти в `app.blade.php` строку:
```html
    <script src="/public{{ mix('/js/app.js') }}"></script>
```

После неё добавить:
```html
    <script src="/public/js/ads-conversions.js?v={{$v_js_ads}}" defer></script>
```

Открыть любую страницу сайта, открыть DevTools → Console. Кликнуть на номер телефона в шапке. Убедиться что нет JS-ошибок. В Network tab должен быть запрос к `google.com/pagead/...` (или в GA4 DebugView если включён).

- [ ] **Step 3.3: Добавить Conversion 1 — `?ck=zvonok` detection**

Добавить в `public/js/ads-conversions.js` после секции tel click:

```js
  // ── Conversion 1: Zvonok form (redirect-based, ?ck=zvonok) ──────────────
  document.addEventListener('DOMContentLoaded', function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('ck') !== 'zvonok') return;

    // Enhanced Conversions: pass phone from zvonok modal if present
    var phoneEl = document.querySelector('form.back-coll-modal input[name="phone"]');
    if (phoneEl && phoneEl.value) {
      safeGtag('set', 'user_data', { phone_number: phoneEl.value });
    }

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
```

> Note: После редиректа с `?ck=zvonok` форма уже закрыта, поэтому поле phone пустое — это нормально. Enhanced Conversions для этой конверсии лучше реализовать на pre-submit (Step 3.4).

- [ ] **Step 3.4: Добавить Enhanced Conversions pre-submit для Conversion 1**

Добавить в `public/js/ads-conversions.js` в секцию DOMContentLoaded (в блок Conversion 1):

Это отдельный обработчик — перехватывает сабмит формы `#requestModal` до отправки:

```js
  // Enhanced Conversions: capture phone before zvonok form submits
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
```

- [ ] **Step 3.5: Добавить Conversion 2 — Cart checkout (AJAX)**

Добавить в `public/js/ads-conversions.js`:

```js
  // ── Conversion 2: Cart checkout (AJAX success) ───────────────────────────
  // Hook fires from cart/index.blade.php xhr.onload when xhr.status === 200.
  // We expose a global callback that the cart JS can call — but since we
  // cannot modify cart/index.blade.php here, we patch XMLHttpRequest instead.
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
```

> Note: Патч `XMLHttpRequest` — стандартная практика для аналитики без изменения бизнес-логики. Срабатывает только на `/cart/checkout`. Не влияет на другие запросы.

- [ ] **Step 3.6: Добавить Conversion 3 — Bron form submit**

Конверсия срабатывает когда `bronFormValidate()` в `l3.js` вызывает `form.submit()`. Поскольку `form.submit()` не генерирует событие `submit`, перехватываем через переопределение метода на конкретном элементе.

Добавить в `public/js/ads-conversions.js`:

```js
  // ── Conversion 3: Bron form (#orderModal) ───────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    var bronForm = document.getElementById('orderModal');
    if (!bronForm) return;

    // form.submit() called by bronFormValidate() doesn't fire 'submit' event.
    // Override the instance method to intercept programmatic submits.
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
```

- [ ] **Step 3.7: Финальная проверка полного файла в консоли**

Открыть браузер, DevTools → Console, проверить что нет ошибок на:
1. Главной странице (L2 без #orderModal)
2. L3 странице товара (есть #orderModal и #requestModal)
3. Странице `/cart`
4. Кликнуть по телефону — в консоли должно появиться обращение к gtag

- [ ] **Step 3.8: Commit финального JS**

```bash
git add public/js/ads-conversions.js resources/views/layouts/app.blade.php
git commit -m "$(cat <<'EOF'
feat(ads): add Google Ads conversion tracking (4 conversions)

- Conversion 1: ?ck=zvonok redirect detection + Enhanced Conversions pre-submit
- Conversion 2: XHR patch on /cart/checkout success
- Conversion 3: form.submit() override on #orderModal (bron)
- Conversion 4: tel: link click handler

All conversions also fire GA4 events (form_zvonok_submit, purchase,
booking_submit, phone_click). No impact on existing GA4/Metrika tags.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Финальная проверка всех 4 конверсий

- [ ] **Step 4.1: Проверить Conversion 1 вручную**

1. Открыть любую страницу сайта
2. Открыть форму заявки (кнопка "Оставить заявку")
3. Заполнить имя и телефон
4. Отправить форму
5. После редиректа URL должен содержать `?ck=zvonok`
6. В DevTools Network проверить запрос к `www.googletagmanager.com` или `google.com/pagead`
7. Обновить страницу (F5) — `?ck=zvonok` должен исчезнуть, конверсия не дублируется

- [ ] **Step 4.2: Проверить Conversion 2 вручную**

1. Открыть `/cart`, добавить товар
2. Заполнить форму оформления, нажать "Оформить заказ"
3. После появления экрана успеха проверить в DevTools что ушли события конверсии

- [ ] **Step 4.3: Проверить Conversion 3 вручную**

1. Открыть любую L3-страницу товара
2. Нажать "Оформить заказ" (открыть #orderModal)
3. Заполнить имя и телефон, нажать "Заказать"
4. Убедиться что в DevTools видны события gtag

- [ ] **Step 4.4: Проверить Conversion 4 вручную**

1. Кликнуть по номеру телефона в шапке сайта
2. В DevTools → Network должен быть запрос gtag с конверсией tel

---

## Самопроверка плана против ТЗ

| Требование ТЗ | Покрыто |
|---|---|
| Global tag AW-18182822550 | ✅ Task 2, Step 2.1 |
| Conversion 1: ?ck=zvonok редирект | ✅ Task 1 + Task 3, Steps 3.3 |
| Enhanced Conversions (zvonok phone) | ✅ Task 3, Step 3.4 |
| GA4 event form_zvonok_submit | ✅ Task 3, Step 3.3 |
| replaceState после ?ck= | ✅ Task 3, Step 3.3 |
| Conversion 2: cart checkout AJAX | ✅ Task 3, Step 3.5 |
| Enhanced Conversions (cart phone) | ✅ Task 3, Step 3.5 |
| GA4 event purchase | ✅ Task 3, Step 3.5 |
| Conversion 3: bron form submit | ✅ Task 3, Step 3.6 |
| Enhanced Conversions (bron phone) | ✅ Task 3, Step 3.6 |
| GA4 event booking_submit | ✅ Task 3, Step 3.6 |
| Conversion 4: tel click | ✅ Task 3, Step 3.1 |
| GA4 event phone_click | ✅ Task 3, Step 3.1 |
| Весь JS в одном файле | ✅ public/js/ads-conversions.js |
| Не трогать GA4/Yandex.Metrika | ✅ только +1 строка в init блок |
| Не менять логику бэкенда кроме редиректа | ✅ только ZvonokController строки 62 и 80 |
| Cache-busting для нового файла | ✅ Task 2, Step 2.2 |
| Исправить null referer bug | ✅ Task 1, Step 1.3 |
| Исправить query string bug | ✅ Task 1, Step 1.3 |
| Не дублировать gtag.js script | ✅ Task 2 — только config line |
