# Security Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Устранить все уязвимости из аудита 2026-06-07 в порядке убывания критичности — от немедленных P0 до харднинга P3.

**Architecture:** Три независимых подсистемы: (1) легаси-панель `bb/` (standalone PHP, auth через `$_SESSION['svoi']`), (2) Laravel-сайт `app/` (PHP 7.4, Laravel 8), (3) CRM `~/sites/tiktak_v2/crm` (Laravel 12, отдельный репо). Задачи 1–16 — репо `~/sites/tiktakby`, задачи 17–19 — репо `~/sites/tiktak_v2/crm`.

**Tech Stack:** PHP 7.4 / Laravel 8 / MariaDB 10.6 / Apache+LiteSpeed / cPanel. CRM: Laravel 12 / Inertia+Vue3. Тесты: PHPUnit (`php artisan test`). Ручная проверка: `curl`.

---

## ⚠️ WAVE 1 — Сегодня (P0-быстрые + P1-срочные)
*Всё что можно сделать без риска сломать функционал: удаления + один флаг*

---

### Task 1: Удалить заведомо опасные файлы из `bb/` и корня сайта

**Repo:** `~/sites/tiktakby`

**Files:**
- Delete: `bb/update_db_schema.php` — выполняет ALTER TABLE без авторизации
- Delete: `bb/avif_to_webp.php` — массовая перезапись файлов + БД без авторизации
- Delete: `bb/br_auto_arch_2.php` — DELETE FROM karn_brons без авторизации
- Delete: `bb/br_auto_arch.php` — шлёт email без авторизации
- Delete: `bb/dima_test.php` — ExpressPay секреты в публичном файле
- Delete: `bb/test2.php` — YML-дамп каталога без авторизации
- Delete: `bb/t.php`, `bb/tp.php`, `bb/qr_jpeg.php` — мусор/сломан
- Delete: `bb/top_menu copy.php` — брошенная копия
- Delete: корень `test_sql.php`, `test_sql2.php`, `t2.php`, `insert_models.php`, `recover_from_archive.php`, `get_imgs2.php`, `get_live_images.php`, `test_images.php`

- [ ] **Step 1: Убедиться что файлы не нужны в проде**

```bash
cd ~/sites/tiktakby
# Проверяем что на эти файлы нет ссылок из других bb/ файлов
grep -r "update_db_schema\|avif_to_webp\|br_auto_arch_2\|br_auto_arch\|dima_test\|test2" bb/ --include="*.php" | grep -v "^bb/update_db_schema\|^bb/avif_to_webp\|^bb/br_auto_arch\|^bb/dima_test\|^bb/test2"
# Ожидаем: пусто (нет зависимостей)
grep -r "test_sql\|t2\.php\|insert_models\|recover_from_archive\|get_imgs2\|get_live_images\|test_images" . --include="*.php" --include="*.blade.php" | grep -v "vendor/"
# Ожидаем: пусто
```

- [ ] **Step 2: Удалить файлы из `bb/`**

```bash
cd ~/sites/tiktakby
rm bb/update_db_schema.php bb/avif_to_webp.php bb/br_auto_arch_2.php bb/br_auto_arch.php
rm bb/dima_test.php bb/test2.php bb/t.php bb/tp.php bb/qr_jpeg.php
rm "bb/top_menu copy.php"
```

- [ ] **Step 3: Удалить тестовые скрипты из корня**

```bash
cd ~/sites/tiktakby
rm -f test_sql.php test_sql2.php t2.php insert_models.php recover_from_archive.php get_imgs2.php get_live_images.php test_images.php
```

- [ ] **Step 4: Проверить что сайт не упал**

```bash
curl -s -o /dev/null -w "%{http_code}" https://tiktak.by/ru/ 
# Ожидаем: 200
curl -s -o /dev/null -w "%{http_code}" https://tiktak.by/bb/
# Ожидаем: 200 (логин-форма)
```

- [ ] **Step 5: Коммит**

```bash
cd ~/sites/tiktakby
git add -A
git commit -m "security: remove dangerous unauthenticated scripts and test files from webroot

Deleted: bb/update_db_schema.php (ran ALTER TABLE without auth), bb/avif_to_webp.php
(bulk file mutations without auth), bb/br_auto_arch_2.php (DELETE karn_brons without
auth), bb/dima_test.php (ExpressPay token hardcoded in public file), and 11 other
debug/test scripts from bb/ and project root."
```

---

### Task 2: Выключить `display_errors` в `bb/` глобально

**Repo:** `~/sites/tiktakby`

**Проблема:** 77 файлов `bb/` содержат `ini_set("display_errors",1)`. Подтверждено вживую: `delited_tovar.php` печатает `Fatal error` и `Notice` анонимам. Один флаг в `.htaccess` перебивает все `ini_set`.

**Files:**
- Create: `bb/.htaccess`

- [ ] **Step 1: Проверить что `bb/.htaccess` не существует (или пустой)**

```bash
ls -la ~/sites/tiktakby/bb/.htaccess 2>/dev/null || echo "НЕТ — создаём"
```

- [ ] **Step 2: Создать `bb/.htaccess`**

```apache
# bb/.htaccess
# Отключаем вывод ошибок PHP в браузер для всего раздела /bb/
php_flag display_errors Off
php_flag log_errors On
```

- [ ] **Step 3: Проверить что ошибки больше не видны анонимам**

```bash
# delited_tovar.php показывал Fatal error без авторизации
curl -s "https://tiktak.by/bb/delited_tovar.php" | grep -i "fatal\|error\|notice\|warning"
# Ожидаем: пусто (ошибки не выводятся)
# Но страница может всё ещё быть доступна (auth guard добавим в Task 4)
```

- [ ] **Step 4: Коммит**

```bash
cd ~/sites/tiktakby
git add bb/.htaccess
git commit -m "security: disable display_errors in bb/ via .htaccess

Suppresses PHP error output to browser across all 77 bb/ files.
Errors still logged to server log file."
```

---

### Task 3: Ротация скомпрометированных секретов (checklist, вне git)

**Это ручные операции — не код. Выполнить до или параллельно с Task 4.**

- [ ] **Step 1: Ротировать пароль БД**

```
1. Зайти в cPanel → MySQL Databases
2. Сменить пароль пользователя tiktakby_tiktak
3. Обновить в прод .env: DB_PASSWORD=<новый>
4. Обновить в bb/Db.php:12 (или, лучше, перенести в /dimanay.php как database.php уже делает)
5. Проверить сайт: curl https://tiktak.by/ru/ → 200
```

- [ ] **Step 2: Ротировать ExpressPay токен**

```
1. Зайти в ЛК ExpressPay → API-ключи → сгенерировать новый
2. Обновить в коде (найти где используется: grep -r "3a0c82e3\|31969\|Golacheva" ~/sites/tiktakby/bb/ --include="*.php")
3. Проверить что dima_test.php уже удалён (Task 1)
```

- [ ] **Step 3: Ротировать ключ Deploy.php**

```bash
# Ключ Deploy-Mb8941 в git-истории — сгенерировать новый:
openssl rand -hex 32
# Вставить в Deploy.php (улучшим в Task 10, пока просто заменить значение)
```

- [ ] **Step 4: Убедиться что CLAUDE.md не содержит реальный пароль (опционально)**

```bash
grep -n "Vai7evahch\|password\|пароль" ~/sites/tiktakby/CLAUDE.md | head
# Если пароль уже сменён — убрать старое значение из CLAUDE.md
```

---

## WAVE 2 — Эта неделя (P0 + P1)

---

### Task 4: Fail-closed auth guard для `bb/` — создать `auth_guard.php` и добавить во все незащищённые файлы

**Repo:** `~/sites/tiktakby`

**Проблема:** 13 файлов в `bb/` не имеют проверки `$_SESSION['svoi']==8941`. Подтверждено вживую: `delited_tovar.php`, `kassa_operations.php`, `predzakaz.php` доступны анонимно.

**Список файлов без гейта (подтверждён grep -L "svoi\|loginCheck"):**
`auto_ostatki.php`, `bb_nav.php`, `delited_tovar.php`, `l_3_ch.php`, `kb_web_url.php`, `kb_ajax_eng.php`, `l_3_br.php`, `predzakaz.php`, `kassa_operations.php`, `karn_srch.php`, `webp_converter.php`, `top_menu.php`, `zakaz2.php`

**Files:**
- Create: `bb/auth_guard.php`
- Modify: каждый из 13 файлов выше — добавить `require_once`

- [ ] **Step 1: Создать `bb/auth_guard.php`**

```php
<?php
// Fail-closed guard — require_once FIRST в каждом bb/ entry-point.
// Использовать ВМЕСТО (не вместе с) inline-проверки svoi.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] !== 8941) {
    http_response_code(403);
    die(
        '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Авторизация</title></head>'
        . '<body style="font-family:sans-serif;padding:2rem">'
        . '<p>Требуется авторизация.</p>'
        . '<a href="/bb/index.php">Войти в панель</a>'
        . '</body></html>'
    );
}
```

- [ ] **Step 2: Добавить guard в `bb/delited_tovar.php`**

Файл начинается с `<?php` + `use` + `session_start()`. Вставить после блока `use`:

```php
// Найти место — после последней строки use, перед session_start():
// use bb\classes\tovar.php;
// use bb\classes\Deal.php;
// ...последний use...
require_once __DIR__ . '/auth_guard.php'; // ← добавить здесь

session_start();  // auth_guard уже вызвал session_start если сессия не открыта
```

Конкретно — открыть файл, найти первую строку кода (после use-блока), вставить перед `session_start()`:

```bash
# Проверяем что session_start есть без guard:
head -15 ~/sites/tiktakby/bb/delited_tovar.php
```

Вставить строку 10 (перед `session_start()`):
```php
require_once __DIR__ . '/auth_guard.php';
```

- [ ] **Step 3: Добавить guard в `bb/kassa_operations.php`**

Файл начинается с `<?php` → блок `use` → (нет session_start) → код. Добавить сразу после `<?php\n\n`:

```php
<?php
require_once __DIR__ . '/auth_guard.php';

use bb\models;
// ... остальной файл без изменений
```

- [ ] **Step 4: Добавить guard в `bb/predzakaz.php`**

```php
<?php
require_once __DIR__ . '/auth_guard.php';

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php');
// ... остальной файл без изменений
```

- [ ] **Step 5: Добавить guard в оставшиеся 10 файлов**

Для каждого из: `auto_ostatki.php`, `bb_nav.php`, `l_3_ch.php`, `kb_web_url.php`, `kb_ajax_eng.php`, `l_3_br.php`, `karn_srch.php`, `webp_converter.php`, `top_menu.php`, `zakaz2.php` — добавить в самое начало файла (сразу после `<?php`):

```php
<?php
require_once __DIR__ . '/auth_guard.php';
// ... остальной файл без изменений
```

Для `webp_converter.php` — там уже есть слабый cookie-guard (строка 16), его заменить (закомментировать старый и добавить наш):

```php
<?php
require_once __DIR__ . '/auth_guard.php';
// [убрать или закомментировать старую строку 16: if (!isset($_SESSION['uid']) && ...)]
```

- [ ] **Step 6: Проверить что защищённые файлы теперь возвращают 403**

```bash
# Должны вернуть 403 (без сессии)
for f in delited_tovar.php kassa_operations.php predzakaz.php zakaz2.php kb_ajax_eng.php; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "https://tiktak.by/bb/$f")
  echo "$f → $code (ожидаем 403)"
done
```

- [ ] **Step 7: Проверить что логин работает**

```bash
# Логин-форма должна работать
curl -s -o /dev/null -w "%{http_code}" "https://tiktak.by/bb/"
# Ожидаем: 200 (форма входа)
```

- [ ] **Step 8: Коммит**

```bash
cd ~/sites/tiktakby
git add bb/auth_guard.php bb/delited_tovar.php bb/kassa_operations.php bb/predzakaz.php \
  bb/auto_ostatki.php bb/bb_nav.php bb/l_3_ch.php bb/kb_web_url.php bb/kb_ajax_eng.php \
  bb/l_3_br.php bb/karn_srch.php bb/webp_converter.php bb/top_menu.php bb/zakaz2.php
git commit -m "security: add fail-closed auth guard to all unprotected bb/ entry points

Created bb/auth_guard.php (fail-closed, checks SESSION svoi===8941).
Added require_once to 13 bb/ files that were reachable without authentication:
delited_tovar, kassa_operations, predzakaz, zakaz2, kb_ajax_eng, kb_web_url,
l_3_br, l_3_ch, karn_srch, auto_ostatki, webp_converter, bb_nav, top_menu.
Replaced weak cookie-only guard in webp_converter.php."
```

---

### Task 5: Исправить SQL-инъекцию (pre-auth) в публичном поиске — P0-2

**Repo:** `~/sites/tiktakby`

**Уязвимость:**
- `bb/classes/ModelWeb.php:1184` — `AGAINST('$text')` — `$text` из `request('search')` без экранирования
- `bb/classes/Model.php:195` — `WHERE producer='$producer'` — `$producer` из `request('producer')` без экранирования

**Files:**
- Modify: `bb/classes/ModelWeb.php` (~строка 1184)
- Modify: `bb/classes/Model.php` (~строка 195)
- Modify: `tests/Feature/SearchSqlInjectionTest.php` (создать)

- [ ] **Step 1: Написать тест (сначала убедиться что ломается)**

Создать `tests/Feature/SearchSqlInjectionTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SearchSqlInjectionTest extends TestCase
{
    public function test_search_with_single_quote_does_not_crash(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode("test' OR '1'='1"));
        // Не должно быть 500 (SQL error)
        $this->assertNotEquals(500, $response->status());
        // Должен вернуть нормальную страницу поиска
        $this->assertStringContainsString('поиск', strtolower($response->getContent()));
    }

    public function test_producer_filter_with_single_quote_does_not_crash(): void
    {
        $response = $this->get('/ru/producer?producer=' . urlencode("test' OR '1'='1"));
        $this->assertNotEquals(500, $response->status());
    }

    public function test_search_with_boolean_fulltext_chars_does_not_crash(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('+велосипед* -самокат'));
        $this->assertNotEquals(500, $response->status());
    }
}
```

- [ ] **Step 2: Запустить тест — убедиться что падает (докажет проблему)**

```bash
cd ~/sites/tiktakby
docker-compose exec app php artisan test tests/Feature/SearchSqlInjectionTest.php
# Ожидаем: FAIL — 500 на один из запросов
```

- [ ] **Step 3: Исправить `bb/classes/ModelWeb.php` — метод `getModelIdsFullTextSearch`**

Найти строку ~1180 (начало метода):
```php
// БЫЛО (~строка 1184):
$query = "SELECT rent_model_web.model_id, MATCH(rent_model_web.title, rent_model_web.l2_name, rent_model_web.item_name_main) AGAINST('$text') AS relevance FROM rent_model_web
                LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = rent_model_web.model_id
                WHERE (
                    MATCH(rent_model_web.title, rent_model_web.l2_name, rent_model_web.item_name_main) AGAINST('$text')
                    )
                AND tovar_rent_items.item_id>0
                GROUP BY rent_model_web.model_id
                ORDER BY relevance DESC";
```

Заменить на:
```php
// СТАЛО:
// Экранируем и чистим спецсимволы boolean full-text search
$safe_text = $mysqli->real_escape_string($text);
$safe_text = preg_replace('/[+\-><\(\)~*"@]/', ' ', $safe_text);
$query = "SELECT rent_model_web.model_id, MATCH(rent_model_web.title, rent_model_web.l2_name, rent_model_web.item_name_main) AGAINST('$safe_text') AS relevance FROM rent_model_web
                LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = rent_model_web.model_id
                WHERE (
                    MATCH(rent_model_web.title, rent_model_web.l2_name, rent_model_web.item_name_main) AGAINST('$safe_text')
                    )
                AND tovar_rent_items.item_id>0
                GROUP BY rent_model_web.model_id
                ORDER BY relevance DESC";
```

- [ ] **Step 4: Исправить `bb/classes/Model.php` — метод `getModelIdsArrayByProducer`**

Найти строку ~192:
```php
// БЫЛО (~строка 195):
$query = "SELECT DISTINCT(tovar_rent.tovar_rent_id) as model_id
                FROM `tovar_rent`
                LEFT JOIN tovar_rent_items ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                WHERE tovar_rent.producer='$producer' AND tovar_rent_items.model_id>0";
// ...
$query = "SELECT tovar_rent.tovar_rent_id as model_id
                FROM `tovar_rent`
                WHERE tovar_rent.producer='$producer'";
```

Заменить (обе ветки if/else):
```php
// СТАЛО:
$safe_producer = $mysqli->real_escape_string($producer);
if ($hasItems == 1) {
    $query = "SELECT DISTINCT(tovar_rent.tovar_rent_id) as model_id
                FROM `tovar_rent`
                LEFT JOIN tovar_rent_items ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                WHERE tovar_rent.producer='$safe_producer' AND tovar_rent_items.model_id>0";
} else {
    $query = "SELECT tovar_rent.tovar_rent_id as model_id
                FROM `tovar_rent`
                WHERE tovar_rent.producer='$safe_producer'";
}
```

- [ ] **Step 5: Запустить тест — убедиться что проходит**

```bash
cd ~/sites/tiktakby
docker-compose exec app php artisan test tests/Feature/SearchSqlInjectionTest.php
# Ожидаем: PASS (все 3 теста)
```

- [ ] **Step 6: Проверить вживую**

```bash
curl -s "https://tiktak.by/ru/search?search=test'+OR+'1'%3D'1" | grep -i "mysql\|error\|fatal" | head
# Ожидаем: пусто (нет SQL ошибок)
curl -s -o /dev/null -w "%{http_code}" "https://tiktak.by/ru/search?search=велосипед"
# Ожидаем: 200
```

- [ ] **Step 7: Коммит**

```bash
cd ~/sites/tiktakby
git add bb/classes/ModelWeb.php bb/classes/Model.php tests/Feature/SearchSqlInjectionTest.php
git commit -m "security: fix pre-auth SQL injection in search and producer filter

ModelWeb::getModelIdsFullTextSearch — escape input + strip FULLTEXT boolean chars.
Model::getModelIdsArrayByProducer — escape producer parameter.
Both were injectable via GET params accessible to unauthenticated users.
Added SearchSqlInjectionTest to prevent regression."
```

---

### Task 6: Ограничить SMS-эндпоинт (toll-fraud, P1-4)

**Repo:** `~/sites/tiktakby`

**Проблема:** `POST /api/mcp/v1/sms/send` — 60 SMS/мин на любые номера. С токеном = платный трафик на чужие номера.

**Files:**
- Modify: `routes/api.php` — отдельный throttle для SMS
- Modify: `app/Http/Controllers/Mcp/SmsController.php` — добавить валидацию формата номера

- [ ] **Step 1: Написать тест для валидации номера**

Добавить в `tests/Feature/Mcp/SmsControllerTest.php` (создать если нет):

```php
<?php

namespace Tests\Feature\Mcp;

use Tests\TestCase;

class SmsControllerTest extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = config('mcp.api_token');
    }

    public function test_rejects_non_by_ru_phone(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/mcp/v1/sms/send', [
                'phone' => '14155552671',  // US номер
                'text'  => 'test',
            ]);
        $response->assertStatus(422);
    }

    public function test_accepts_valid_by_phone(): void
    {
        // Тест только на валидацию формата, не на реальную отправку
        // Используем заведомо несуществующий номер в нужном формате
        $response = $this->withToken($this->token)
            ->postJson('/api/mcp/v1/sms/send', [
                'phone' => '375291234567',
                'text'  => 'test message',
            ]);
        // 200 (отправлено) или 502 (RocketSMS вернул ошибку) — но не 422
        $this->assertNotEquals(422, $response->status());
    }

    public function test_rejects_empty_text(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/mcp/v1/sms/send', [
                'phone' => '375291234567',
                'text'  => '',
            ]);
        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Запустить — убедиться что first test FAILS (US номер сейчас принимается)**

```bash
cd ~/sites/tiktakby
docker-compose exec app php artisan test tests/Feature/Mcp/SmsControllerTest.php --filter test_rejects_non_by_ru_phone
# Ожидаем: FAIL (сейчас валидация phone только max:20)
```

- [ ] **Step 3: Добавить валидацию номера в `SmsController.php`**

В методе `send` заменить правило для `phone`:

```php
// БЫЛО:
'phone'  => 'required|string|max:20',

// СТАЛО:
'phone'  => ['required', 'string', 'regex:/^375\d{9}$|^7\d{10}$/'],
// Формат: BY (375xxxxxxxxx, 12 цифр) или RU (7xxxxxxxxxx, 11 цифр)
```

- [ ] **Step 4: Добавить отдельный throttle для SMS в `routes/api.php`**

Найти строку 120:
```php
Route::post('sms/send', [SmsController::class, 'send'])->name('sms.send');
```

Заменить на:
```php
// 5 SMS в минуту максимум (сверх группового throttle:60,1)
Route::post('sms/send', [SmsController::class, 'send'])
    ->middleware('throttle:5,1')
    ->name('sms.send');
```

- [ ] **Step 5: Запустить тесты — должны PASS**

```bash
docker-compose exec app php artisan test tests/Feature/Mcp/SmsControllerTest.php
# Ожидаем: все PASS
```

- [ ] **Step 6: Коммит**

```bash
cd ~/sites/tiktakby
git add routes/api.php app/Http/Controllers/Mcp/SmsController.php tests/Feature/Mcp/SmsControllerTest.php
git commit -m "security: restrict SMS endpoint to BY/RU phones and throttle to 5/min

Prevents toll-fraud: validates phone format (375xxxxxxxxx or 7xxxxxxxxxx).
Adds dedicated throttle:5,1 on top of group throttle.
Fixes P1-4 from security audit 2026-06-07."
```

---

### Task 7: Rate-limit на публичные формы (P1-5)

**Repo:** `~/sites/tiktakby`

**Проблема:** `POST /zvonok`, `/zvonok/bron`, `/zvonok/kb`, `/subscribe`, `/cart/checkout` — нет rate-limit. Возможен скриптовый спам.

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Добавить throttle на форм-роуты**

Найти в `routes/web.php` блок zvonok-маршрутов (~строки 27-50) и обернуть в группу:

```php
// БЫЛО:
Route::post('/zvonok/bron', 'App\Http\Controllers\ZvonokController@bron');
Route::post('/zvonok/kb', 'App\Http\Controllers\ZvonokController@KBronActions');
Route::post('/zvonok', 'App\Http\Controllers\ZvonokController@addCall')->name('zvonokSave');
Route::post('/subscribe', 'App\Http\Controllers\ZvonokController@addSubscription');
// ...
Route::post('/cart/checkout', 'App\Http\Controllers\CartController@checkout');

// СТАЛО — обернуть группой:
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/zvonok/bron', 'App\Http\Controllers\ZvonokController@bron');
    Route::post('/zvonok/kb', 'App\Http\Controllers\ZvonokController@KBronActions');
    Route::post('/zvonok', 'App\Http\Controllers\ZvonokController@addCall')->name('zvonokSave');
    Route::post('/subscribe', 'App\Http\Controllers\ZvonokController@addSubscription');
});

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/cart/checkout', 'App\Http\Controllers\CartController@checkout');
});
```

> `throttle:10,1` = 10 запросов в минуту с одного IP. При реальном заполнении формы человеком 10/мин более чем достаточно.

- [ ] **Step 2: Сбросить кэш маршрутов**

```bash
docker-compose exec app php artisan route:cache
# Ожидаем: Routes cached successfully.
```

- [ ] **Step 3: Проверить что форма работает (первый запрос)**

```bash
curl -s -o /dev/null -w "%{http_code}" -X POST https://tiktak.by/zvonok \
  -d "phone=375291111111&name=Test&_token=ignored"
# Ожидаем: 419 (CSRF mismatch) — это нормально, главное не 500/404
```

- [ ] **Step 4: Коммит**

```bash
cd ~/sites/tiktakby
git add routes/web.php
git commit -m "security: add rate limiting to public form endpoints

10 req/min on callback/booking forms, 5 req/min on checkout.
Prevents scripted lead spam and fake reservation flooding.
Fixes P1-5 from security audit 2026-06-07."
```

---

## WAVE 3 — Следующая неделя (P2 + завершение P1)

---

### Task 8: Defense-in-depth: защита чувствительных путей в `.htaccess` (P2-2)

**Repo:** `~/sites/tiktakby`

**Проблема:** хостинг сейчас блокирует `.env`/`.git` (403), но это держится на дефолтах хостинга — не на нашей конфигурации. Нужна явная защита.

**Files:**
- Modify: `.htaccess`

- [ ] **Step 1: Добавить блок защиты в корневой `.htaccess`**

Открыть `.htaccess`, добавить в начало файла (до `<IfModule mod_rewrite.c>`):

```apache
# Block access to sensitive files — defense in depth
# (Hosting currently returns 403 for .env/.git, but make it explicit)
<FilesMatch "(^\.env|^\.git|^composer\.(json|lock)|^package(-lock)?\.json|\.sql$|\.md$|CLAUDE\.md|AGENTS\.md)">
    Require all denied
</FilesMatch>

<DirectoryMatch "^.*(\.git|storage|vendor|node_modules)">
    Require all denied
</DirectoryMatch>
```

- [ ] **Step 2: Проверить что .env и CLAUDE.md заблокированы**

```bash
curl -s -o /dev/null -w "%{http_code}" https://tiktak.by/.env
# Ожидаем: 403
curl -s -o /dev/null -w "%{http_code}" https://tiktak.by/CLAUDE.md
# Ожидаем: 403
curl -s -o /dev/null -w "%{http_code}" https://tiktak.by/composer.json
# Ожидаем: 403
```

- [ ] **Step 3: Проверить что сайт работает нормально**

```bash
curl -s -o /dev/null -w "%{http_code}" https://tiktak.by/ru/
# Ожидаем: 200
curl -s -o /dev/null -w "%{http_code}" https://tiktak.by/ru/prokat-velosipedov
# Ожидаем: 200
```

- [ ] **Step 4: Коммит**

```bash
cd ~/sites/tiktakby
git add .htaccess
git commit -m "security: explicitly block sensitive files in .htaccess

Denies access to .env, .git, composer.*, *.sql, *.md, storage/, vendor/.
Supplements existing hosting-level blocks with explicit application-level rules.
Fixes P2-2 from security audit 2026-06-07."
```

---

### Task 9: Hardening `Deploy.php` — ключ в env, POST, hash_equals (P2-4)

**Repo:** `~/sites/tiktakby`

**Проблема:** `Deploy.php:3` — ключ `Deploy-Mb8941` захардкожен и в git-истории. Сравнение через `!==` (не timing-safe). Срабатывает по GET-запросу. Нет логирования.

**Files:**
- Modify: `Deploy.php`
- Modify: прод `.env` (вне git)

- [ ] **Step 1: Обновить прод `.env` (вне репо)**

```bash
# На проде (SSH):
echo "DEPLOY_SECRET=$(openssl rand -hex 32)" >> ~/public_html/.env
# Сохранить значение — это новый ключ деплоя
```

- [ ] **Step 2: Переписать `Deploy.php`**

```php
<?php
// Ключ читается из .env файла — НЕ хранится в репозитории
$env_path = __DIR__ . '/.env';
$secret_key = null;
if (file_exists($env_path)) {
    foreach (file($env_path) as $line) {
        if (str_starts_with(trim($line), 'DEPLOY_SECRET=')) {
            $secret_key = trim(substr(trim($line), strlen('DEPLOY_SECRET=')));
            break;
        }
    }
}

// Только POST-запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Timing-safe сравнение
$provided = $_POST['key'] ?? '';
if (!$secret_key || !hash_equals($secret_key, $provided)) {
    http_response_code(403);
    error_log('[Deploy.php] Unauthorized attempt from ' . $_SERVER['REMOTE_ADDR']);
    die('Access Denied');
}

error_log('[Deploy.php] Deploy triggered by ' . $_SERVER['REMOTE_ADDR']);
set_time_limit(300);

$composer_bin = '/opt/cpanel/composer/bin/composer';

$commands = [
    'echo "Deploy started"',
    'git fetch origin',
    'git reset --hard origin/main',
    "$composer_bin install --no-dev --optimize-autoloader 2>&1",
    'php artisan migrate --force',
    'php artisan optimize:clear',
    'php artisan config:cache',
    'php artisan route:cache',
    'php artisan view:cache',
    'echo "Deploy finished"',
];

echo '<pre>';
foreach ($commands as $cmd) {
    echo htmlspecialchars("$ $cmd\n");
    $output = [];
    exec($cmd . ' 2>&1', $output);
    echo htmlspecialchars(implode("\n", $output) . "\n\n");
    flush();
}
echo '</pre>';
```

- [ ] **Step 3: Обновить вызов деплоя (если есть скрипт/CI)**

```bash
# Теперь нужен POST:
curl -X POST https://tiktak.by/Deploy.php -d "key=<новый_ключ>"
# Вместо GET запроса
```

- [ ] **Step 4: Проверить что GET возвращает 405**

```bash
curl -s -o /dev/null -w "%{http_code}" "https://tiktak.by/Deploy.php"
# Ожидаем: 403 (хостинг может блокировать раньше нас) или 405
```

- [ ] **Step 5: Коммит**

```bash
cd ~/sites/tiktakby
git add Deploy.php
git commit -m "security: harden Deploy.php — env-based key, POST-only, hash_equals

Moved deploy secret from hardcoded string to .env DEPLOY_SECRET.
Require POST (GET → 405), use hash_equals (timing-safe), add error_log audit.
Removes the hardcoded key from repository. Fixes P2-4 from audit."
```

---

### Task 10: Исправить open redirect и ReDoS в `CheckRedirects.php` (P2-5)

**Repo:** `~/sites/tiktakby`

**Files:**
- Modify: `app/Http/Middleware/CheckRedirects.php`

- [ ] **Step 1: Написать тест**

`tests/Feature/CheckRedirectsTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class CheckRedirectsTest extends TestCase
{
    public function test_open_redirect_to_external_host_is_blocked(): void
    {
        // Создать тестовый редирект на внешний сайт
        \Illuminate\Support\Facades\DB::table('redirects')->insert([
            'source_url' => '/test-open-redirect',
            'target_url' => 'https://evil.example.com/phishing',
            'status_code' => 301,
            'is_active'  => 1,
            'is_regex'   => 0,
        ]);

        $response = $this->get('/test-open-redirect');
        // Ожидаем: redirect на наш хост, не на evil.example.com
        // Либо 302 на безопасный URL, либо 404
        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            $this->assertStringNotContainsString('evil.example.com', $location);
        }

        // Cleanup
        \Illuminate\Support\Facades\DB::table('redirects')
            ->where('source_url', '/test-open-redirect')->delete();
    }
}
```

- [ ] **Step 2: Запустить — убедиться что FAILS (open redirect сейчас работает)**

```bash
docker-compose exec app php artisan test tests/Feature/CheckRedirectsTest.php
# Ожидаем: FAIL
```

- [ ] **Step 3: Исправить `CheckRedirects.php` — валидация target_url**

Найти строку ~100 (`return redirect($redirect->target_url, ...)`) и добавить проверку перед ней:

```php
// Добавить приватный метод в класс:
private function isSafeRedirectTarget(string $url): bool
{
    // Разрешаем: относительные пути (/путь), тот же хост
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return true;
    }
    $parsed = parse_url($url);
    if (!isset($parsed['host'])) {
        return true; // относительный
    }
    $appHost = parse_url(config('app.url'), PHP_URL_HOST);
    return $parsed['host'] === $appHost;
}
```

Заменить оба места с `return redirect(...)` (точные/regex) на:

```php
// Для точных совпадений:
if (!$this->isSafeRedirectTarget($redirect->target_url)) {
    \Illuminate\Support\Facades\Log::warning('CheckRedirects: blocked unsafe redirect to ' . $redirect->target_url);
    return null; // продолжить обработку запроса без редиректа
}
return redirect($redirect->target_url, $redirect->status_code);

// Для regex:
$target = preg_replace($regex->source_url, $regex->target_url, $path);
if (!$this->isSafeRedirectTarget($target)) {
    \Illuminate\Support\Facades\Log::warning('CheckRedirects: blocked unsafe regex redirect to ' . $target);
    continue;
}
```

- [ ] **Step 4: Добавить защиту от ReDoS — @preg_match с лимитом**

Найти строку 116 (`if (@preg_match($regex->source_url, $path))`):

```php
// БЫЛО:
if (@preg_match($regex->source_url, $path)) {

// СТАЛО — добавить ini_set для pcre.backtrack_limit и timeout:
set_error_handler(null);
$matched = @preg_match($regex->source_url, $path, $matches);
restore_error_handler();
if ($matched === false) {
    \Illuminate\Support\Facades\Log::error('CheckRedirects: invalid regex pattern ' . $regex->source_url);
    continue;
}
if ($matched) {
```

- [ ] **Step 5: Запустить тест — должен PASS**

```bash
docker-compose exec app php artisan test tests/Feature/CheckRedirectsTest.php
# Ожидаем: PASS
```

- [ ] **Step 6: Коммит**

```bash
cd ~/sites/tiktakby
git add app/Http/Middleware/CheckRedirects.php tests/Feature/CheckRedirectsTest.php
git commit -m "security: fix open redirect and add regex error handling in CheckRedirects

Added isSafeRedirectTarget() — blocks redirects to external hosts.
Added error handling for invalid regex patterns (prevents ReDoS/crashes).
Fixes P2-5 from security audit 2026-06-07."
```

---

### Task 11: Флаги безопасности на cookie + SESSION_SECURE_COOKIE (P2-6, P2-8)

**Repo:** `~/sites/tiktakby`

**Проблема:**
- `bb/index.php:37`, `bb/one_login.php:108` — `setcookie('tt_is_logged_in', ...)` без HttpOnly/Secure/SameSite
- `config/session.php:171` — `SESSION_SECURE_COOKIE` не выставлен

**Files:**
- Modify: `bb/index.php`
- Modify: `bb/one_login.php`
- Modify: `.env` (вне git, на проде)

- [ ] **Step 1: Найти setcookie в bb/index.php**

```bash
grep -n "setcookie" ~/sites/tiktakby/bb/index.php
# Найти строку с tt_is_logged_in
```

- [ ] **Step 2: Обновить setcookie в `bb/index.php`**

```php
// БЫЛО (4-arg форма, без флагов):
setcookie('tt_is_logged_in', '1', time() + 30*24*3600, '/');

// СТАЛО:
setcookie('tt_is_logged_in', '1', [
    'expires'  => time() + 30 * 24 * 3600,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
```

- [ ] **Step 3: Аналогично в `bb/one_login.php`**

```bash
grep -n "setcookie" ~/sites/tiktakby/bb/one_login.php
```

Применить те же изменения.

- [ ] **Step 4: Выставить SESSION_SECURE_COOKIE на проде**

```bash
# На сервере — добавить/изменить в .env:
# SESSION_SECURE_COOKIE=true
grep "SESSION_SECURE_COOKIE" ~/public_html/.env || echo "SESSION_SECURE_COOKIE=true" >> ~/public_html/.env
```

- [ ] **Step 5: Проверить после деплоя**

```bash
curl -sI "https://tiktak.by/bb/" | grep -i "Set-Cookie"
# После логина — должны быть флаги Secure; HttpOnly; SameSite=Lax
```

- [ ] **Step 6: Коммит**

```bash
cd ~/sites/tiktakby
git add bb/index.php bb/one_login.php
git commit -m "security: add HttpOnly/Secure/SameSite flags to bb/ session cookie

setcookie now uses options array with secure=true, httponly=true, samesite=Lax.
Fixes P2-6 from security audit 2026-06-07."
```

---

### Task 12: Исправить open redirect по Referer в ZvonokController (P3-3)

**Repo:** `~/sites/tiktakby`

**Files:**
- Modify: `app/Http/Controllers/ZvonokController.php:65-67`

- [ ] **Step 1: Найти уязвимое место**

```bash
grep -n "referer\|Referer" ~/sites/tiktakby/app/Http/Controllers/ZvonokController.php
```

- [ ] **Step 2: Исправить**

```php
// БЫЛО (строка ~65-67):
$referer = $req->header('referer') ?: '/';
$separator = parse_url($referer, PHP_URL_QUERY) ? '&' : '?';
return Redirect::to($referer . $separator . 'ck=zvonok');

// СТАЛО:
$referer = $req->header('referer') ?: '/';
$appHost = parse_url(config('app.url'), PHP_URL_HOST);
$refererHost = parse_url($referer, PHP_URL_HOST);
// Только тот же хост — иначе редиректим на /
$safeReferer = ($refererHost && $refererHost === $appHost) ? $referer : '/';
$separator = parse_url($safeReferer, PHP_URL_QUERY) ? '&' : '?';
return Redirect::to($safeReferer . $separator . 'ck=zvonok');
```

- [ ] **Step 3: Коммит**

```bash
cd ~/sites/tiktakby
git add app/Http/Controllers/ZvonokController.php
git commit -m "security: validate Referer header before redirect in ZvonokController

Only redirects to same-host URLs — external referers fall back to /.
Fixes P3-3 from security audit 2026-06-07."
```

---

### Task 13: Хеширование паролей в `bb/` + прекратить логировать попытки паролей (P1-1)

**Repo:** `~/sites/tiktakby`

**Проблема:**
- `bb/models/User.php:146` — пароли сравниваются в плейнтексте
- `bb/models/User.php:152` — неверный пароль пишется в `logpass_wrong` в открытом виде

Стратегия: **плавная миграция** — при успешном логине с плейнтекстом сразу же пере-хешировать.

**Files:**
- Modify: `bb/models/User.php`

- [ ] **Step 1: Прочитать метод LogIn (~строки 143-170)**

```bash
sed -n '143,175p' ~/sites/tiktakby/bb/models/User.php
```

- [ ] **Step 2: Обновить `LogIn` — плавная миграция на bcrypt**

```php
public static function LogIn($login='', $pas='') {
    $db = Db::getInstance();
    $mysqli = $db->getConnection();

    // Читаем хранимый хеш (или плейнтекст для старых записей)
    $safe_login = $mysqli->real_escape_string($login);
    $query = "SELECT logpass_id, pass FROM logpass WHERE log='$safe_login' AND `active`>0";
    $result = $mysqli->query($query);

    if (!$result || $result->num_rows < 1) {
        // Логируем неудачу БЕЗ пароля
        $safe_ip = $mysqli->real_escape_string($_SERVER['REMOTE_ADDR']);
        $mysqli->query("INSERT INTO logpass_wrong VALUES(" . time() . ", '$safe_login', '[скрыто]', '$safe_ip', 'wr_logpass')");
        return null;
    }

    $row = $result->fetch_assoc();
    $stored = $row['pass'];
    $id = (int)$row['logpass_id'];

    // Проверяем: bcrypt-хеш или плейнтекст (для совместимости при миграции)
    $passwordOk = false;
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2b$')) {
        // Уже хеширован
        $passwordOk = password_verify($pas, $stored);
    } else {
        // Старый плейнтекст — сравниваем и СРАЗУ обновляем
        $passwordOk = ($pas === $stored);
        if ($passwordOk) {
            $newHash = password_hash($pas, PASSWORD_BCRYPT);
            $safe_hash = $mysqli->real_escape_string($newHash);
            $mysqli->query("UPDATE logpass SET pass='$safe_hash' WHERE logpass_id=$id");
        }
    }

    if (!$passwordOk) {
        $safe_ip = $mysqli->real_escape_string($_SERVER['REMOTE_ADDR']);
        $mysqli->query("INSERT INTO logpass_wrong VALUES(" . time() . ", '$safe_login', '[скрыто]', '$safe_ip', 'wr_logpass')");
        return null;
    }

    $user = self::getUserById($id);
    $lg_log = "INSERT INTO logpass_track VALUES('', $id, 'login', '" . time() . "', '" . $mysqli->real_escape_string($_SERVER['REMOTE_ADDR']) . "', '')";
    $mysqli->query($lg_log);
    return $user;
}
```

- [ ] **Step 3: Обновить метод `save` для новых пользователей**

Найти `save()` (~строка 175), найти строку где `pass='$this->password'` вставляется:

```php
// БЫЛО:
$query="INSERT INTO logpass SET log='$this->login', pass='$this->password', ...

// СТАЛО — хешируем при создании:
$hashed_pass = password_hash($this->password, PASSWORD_BCRYPT);
$safe_hash = $mysqli->real_escape_string($hashed_pass);
$query="INSERT INTO logpass SET log='$this->login', pass='$safe_hash', ...
```

- [ ] **Step 4: Проверить логин вживую**

```bash
# Войти в /bb/ с реальными кредами
# После логина: убедиться что запись в logpass обновилась с bcrypt-хешем
# SELECT pass FROM logpass WHERE log='...' LIMIT 1;
# Ожидаем: начинается с $2y$
```

- [ ] **Step 5: Коммит**

```bash
cd ~/sites/tiktakby
git add bb/models/User.php
git commit -m "security: migrate bb/ passwords to bcrypt with gradual rehash

LogIn: verify bcrypt if stored, else compare plaintext and immediately rehash.
New users: password_hash on save.
Stop logging plaintext attempted passwords to logpass_wrong.
Fixes P1-1 from security audit 2026-06-07."
```

---

## WAVE 4 — CRM (`~/sites/tiktak_v2/crm`)

---

### Task 14: CRM — добавить permission middleware на не-каталожные маршруты (P0-3)

**Repo:** `~/sites/tiktak_v2/crm`

**Проблема:** `routes/web.php:39` — весь блок под `staff.auth` без проверки ролей. Курьер может создать `owner`, провести деньги, удалить клиентов.

**Files:**
- Create: `app/Http/Middleware/RequirePermission.php`
- Modify: `bootstrap/app.php` (или `app/Http/Kernel.php` если он существует)
- Modify: `routes/web.php`
- Create: `tests/Feature/IAM/PermissionMiddlewareTest.php`

- [ ] **Step 1: Написать тест на эскалацию привилегий**

```php
<?php
// tests/Feature/IAM/PermissionMiddlewareTest.php
namespace Tests\Feature\IAM;

use Tests\TestCase;
use TikTak\IAM\Models\StaffUser;
use TikTak\IAM\Enums\StaffRole;

class PermissionMiddlewareTest extends TestCase
{
    public function test_courier_cannot_create_staff(): void
    {
        $courier = StaffUser::factory()->create(['role' => StaffRole::COURIER]);

        $response = $this->actingAs($courier, 'staff')
            ->post(route('bb.staff.store'), [
                'name'     => 'Hacker',
                'username' => 'hacker',
                'role'     => 'owner',
                'password' => 'secret123',
            ]);

        $response->assertForbidden(); // 403
        $this->assertDatabaseMissing('staff_users', ['username' => 'hacker']);
    }

    public function test_owner_can_create_staff(): void
    {
        $owner = StaffUser::factory()->create(['role' => StaffRole::OWNER]);

        $response = $this->actingAs($owner, 'staff')
            ->post(route('bb.staff.store'), [
                'name'     => 'New Employee',
                'username' => 'new_employee',
                'role'     => 'consultant',
                'password' => 'secret123',
            ]);

        $response->assertRedirectContains('staff');
    }

    public function test_courier_cannot_access_financial_transactions(): void
    {
        $courier = StaffUser::factory()->create(['role' => StaffRole::COURIER]);

        $response = $this->actingAs($courier, 'staff')
            ->post(route('bb.transactions.store'), [
                'amount' => 1000,
            ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Запустить — убедиться что FAIL**

```bash
cd ~/sites/tiktak_v2/crm
php artisan test tests/Feature/IAM/PermissionMiddlewareTest.php
# Ожидаем: FAIL — courier может создать staff (403 не возвращается)
```

- [ ] **Step 3: Создать `RequirePermission` middleware**

```php
<?php
// app/Http/Middleware/RequirePermission.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user = $request->user('staff');

        if (!$user) {
            return redirect()->route('bb.login');
        }

        foreach ($permissions as $permission) {
            if (!$user->role->hasPermission($permission)) {
                abort(403, 'Недостаточно прав: ' . $permission);
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Зарегистрировать middleware в `bootstrap/app.php`**

```php
// Найти withMiddleware() в bootstrap/app.php:
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        // Существующие алиасы...
        'permission' => \App\Http\Middleware\RequirePermission::class,
    ]);
})
```

- [ ] **Step 5: Навесить на маршруты в `routes/web.php`**

Обернуть в permission-группы внутри основного `staff.auth`-блока:

```php
// Staff management — только staff.manage (owner, coder)
Route::middleware('permission:staff.manage')->group(function () {
    Route::resource('staff', StaffUserController::class)->except('show');
});
Route::get('roles', [RoleController::class, 'index'])->name('roles.index');

// Finance — только finance.manage
Route::middleware('permission:finance.manage')->group(function () {
    Route::post('cash-registers/days/open', [CashRegisterController::class, 'openDay'])->name('cash-registers.days.open');
    Route::post('cash-registers/days/confirm', [CashRegisterController::class, 'confirmDay'])->name('cash-registers.days.confirm');
    Route::get('cash-registers/{cash_register}/daily-report', [CashRegisterController::class, 'dailyReport'])->whereNumber('cash_register')->name('cash-registers.daily-report');
    Route::resource('cash-registers', CashRegisterController::class)->parameters(['cash-registers' => 'cash_register'])->whereNumber('cash_register');
    Route::resource('transactions', FinancialTransactionController::class)->only(['index', 'create', 'store']);
    Route::resource('payment-channels', PaymentChannelController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('finance-reports', [FinanceReportController::class, 'index'])->name('finance-reports.index');
});

// CRM — deals.create (consultant и выше)
Route::middleware('permission:deals.create')->group(function () {
    Route::resource('clients', ClientController::class);
    Route::resource('deals', RentalDealController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('deals/{id}/items', [RentalDealController::class, 'addItem'])->name('deals.items.add');
    Route::post('deals/{id}/items/{itemId}/extend', [RentalDealController::class, 'extendItem'])->name('deals.items.extend');
    Route::post('deals/{id}/items/{itemId}/return', [RentalDealController::class, 'returnItem'])->name('deals.items.return');
    Route::post('deals/{id}/items/{itemId}/issue', [RentalDealController::class, 'issueItem'])->name('deals.items.issue');
    Route::post('deals/{id}/items/{itemId}/purchase', [RentalDealController::class, 'purchaseItem'])->name('deals.items.purchase');
    Route::post('deals/{id}/payments', [RentalDealController::class, 'recordPayment'])->name('deals.payments.record');
    Route::post('deals/{id}/close', [RentalDealController::class, 'close'])->name('deals.close');
    Route::patch('deals/{id}', [RentalDealController::class, 'update'])->name('deals.update');
    Route::patch('deals/{id}/items/{itemId}', [RentalDealController::class, 'updateItem'])->name('deals.items.update');
    Route::post('deals/{id}/operations/{op}/reverse', [RentalDealController::class, 'reverseOperation'])->name('deals.operations.reverse');
    Route::get('issue', [IssueController::class, 'index'])->name('issue.index');
    Route::post('issue/client', [IssueController::class, 'storeClient'])->name('issue.client.store');
    Route::patch('issue/client/{id}', [IssueController::class, 'updateClient'])->whereNumber('id')->name('issue.client.update');
    Route::post('issue/deal', [IssueController::class, 'storeDeal'])->name('issue.deal.store');
    Route::post('issue/{deal_id}/payment', [IssueController::class, 'recordPayment'])->whereNumber('deal_id')->name('issue.deal.payment');
    Route::post('issue/{deal_id}/{item_id}/issue', [IssueController::class, 'issueItem'])->whereNumber(['deal_id', 'item_id'])->name('issue.deal.issue-item');
    Route::resource('bookings', BookingController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('bookings/{id}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('bookings/{id}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('rental/inventory/search', [InventorySearchController::class, 'search'])->name('rental.inventory.search');
    Route::get('rental/inventory/tariffs', [InventorySearchController::class, 'tariffsForModel'])->name('rental.inventory.tariffs');
    Route::get('operations', [RentalOperationController::class, 'index'])->name('operations.index');
});
```

- [ ] **Step 6: Запустить тесты — PASS**

```bash
cd ~/sites/tiktak_v2/crm
php artisan test tests/Feature/IAM/PermissionMiddlewareTest.php
# Ожидаем: PASS
```

- [ ] **Step 7: Коммит**

```bash
cd ~/sites/tiktak_v2/crm
git add app/Http/Middleware/RequirePermission.php bootstrap/app.php routes/web.php \
  tests/Feature/IAM/PermissionMiddlewareTest.php
git commit -m "security: enforce role permissions on staff, finance, and CRM routes

Added RequirePermission middleware. Staff/finance/deals routes now require
appropriate permissions (staff.manage, finance.manage, deals.create).
Courier/consultant can no longer escalate privileges or access finance.
Fixes P0-3 from security audit 2026-06-07."
```

---

### Task 15: CRM — принудительный tenant scope в Core резолверах (P0-4)

**Repo:** `~/sites/tiktak_v2/crm` (+ изменение в `tiktak/core`)

> ⚠️ Изменение в `tiktak/core` требует согласования по протоколу из CRM CLAUDE.md. Если core — отдельный репо, там нужен отдельный PR.

**Проблема:**
- `core/src/IAM/Services/StaffResolver.php:22` — `StaffUser::find($id)` без tenant
- `core/src/CRM/Services/ClientService.php:34,65` — `Client::find($id)` и `list()` без tenant

**Files:**
- Modify: `vendor/tiktak/core` или path `../core/src/IAM/Services/StaffResolver.php`
- Modify: `vendor/tiktak/core` или path `../core/src/CRM/Services/ClientService.php`
- Modify: `app/Http/Controllers/IAM/StaffUserController.php`
- Modify: `app/Http/Controllers/CRM/ClientController.php`
- Create: `tests/Feature/CRM/TenantIsolationTest.php`

- [ ] **Step 1: Написать тест на IDOR**

```php
<?php
// tests/Feature/CRM/TenantIsolationTest.php
namespace Tests\Feature\CRM;

use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_client_from_another_tenant_returns_404(): void
    {
        $tenantA = \App\Models\Tenant::factory()->create();
        $tenantB = \App\Models\Tenant::factory()->create();
        $staffA  = \TikTak\IAM\Models\StaffUser::factory()->for($tenantA)->create();
        $clientB = \TikTak\CRM\Models\Client::factory()->for($tenantB)->create();

        $response = $this->actingAs($staffA, 'staff')
            ->get(route('bb.clients.edit', $clientB->id));

        $response->assertNotFound(); // 404, не 200
    }
}
```

- [ ] **Step 2: Запустить — FAIL**

```bash
cd ~/sites/tiktak_v2/crm
php artisan test tests/Feature/CRM/TenantIsolationTest.php
# Ожидаем: FAIL — возвращает 200 с данными чужого тенанта
```

- [ ] **Step 3: Обновить `ClientService::findById` в core**

```php
// core/src/CRM/Services/ClientService.php
// БЫЛО (~строка 34):
public function findById(int $id): ?Client
{
    return Client::find($id);
}

// СТАЛО:
public function findById(int $id, int $tenantId): ?Client
{
    return Client::where('id', $id)->where('tenant_id', $tenantId)->first();
}
```

- [ ] **Step 4: Обновить `ClientService::list` — принудительный tenantId**

```php
// БЫЛО (~строка 65):
public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
{
    $query = Client::query();
    if (!empty($filters['tenant_id'])) {
        $query->where('tenant_id', $filters['tenant_id']);
    }
    // ...

// СТАЛО:
public function list(int $tenantId, array $filters = [], int $perPage = 20): LengthAwarePaginator
{
    $query = Client::query()->where('tenant_id', $tenantId);
    // убрать if-проверку, tenantId теперь обязателен
```

- [ ] **Step 5: Обновить `ClientController` — передавать tenantId из сессии**

```php
// app/Http/Controllers/CRM/ClientController.php
// В каждом методе получать tenantId из аутентифицированного пользователя:
$tenantId = auth('staff')->user()->tenant_id;

// Заменить:
$this->clientService->findById($id)
// На:
$this->clientService->findById($id, $tenantId)
// Если null — abort(404)

// Заменить:
$this->clientService->list($filters)
// На:
$this->clientService->list($tenantId, $filters)
```

- [ ] **Step 6: Аналогично для `StaffResolver::findById`**

```php
// core/src/IAM/Services/StaffResolver.php
// СТАЛО:
public function findById(int $id, int $tenantId): ?StaffUser
{
    return StaffUser::where('id', $id)->where('tenant_id', $tenantId)->first();
}

public function listStaff(int $tenantId): Collection
{
    return StaffUser::where('tenant_id', $tenantId)->get();
}
```

- [ ] **Step 7: Запустить тесты — PASS**

```bash
cd ~/sites/tiktak_v2/crm
php artisan test tests/Feature/CRM/TenantIsolationTest.php
php artisan test  # полный прогон — нет регрессий
```

- [ ] **Step 8: Коммит**

```bash
cd ~/sites/tiktak_v2/crm
git add app/Http/Controllers/CRM/ClientController.php \
  app/Http/Controllers/IAM/StaffUserController.php \
  tests/Feature/CRM/TenantIsolationTest.php
git commit -m "security: enforce tenant scoping in ClientController and StaffUserController

Pass tenantId from session to all ClientService/StaffResolver calls.
Cross-tenant IDOR now returns 404. Fixes P0-4 from security audit."

# В core-репо (отдельный PR/коммит):
# git add src/IAM/Services/StaffResolver.php src/CRM/Services/ClientService.php
# git commit -m "security: require tenantId in findById and list methods"
```

---

## WAVE 5 — Харднинг P2/P3 (по приоритету)

---

### Task 16: Добавить mcp.geo middleware (P2-1)

**Repo:** `~/sites/tiktakby`

**Файл:** `routes/api.php:38`

- [ ] **Step 1: Добавить `mcp.geo` в группу MCP**

```php
// БЫЛО:
->middleware(['mcp.json', 'mcp.token', 'mcp.audit', 'throttle:60,1'])

// СТАЛО:
->middleware(['mcp.json', 'mcp.token', 'mcp.geo', 'mcp.audit', 'throttle:60,1'])
```

- [ ] **Step 2: Проверить что GeoLite2 база есть**

```bash
ls ~/sites/tiktakby/storage/app/geoip/GeoLite2-Country.mmdb
# Если нет — скачать или geo-блокировку отложить
```

- [ ] **Step 3: Проверить что запрос из BY проходит**

```bash
curl -s -H "Authorization: Bearer $TIKTAK_MCP_TOKEN" \
  "https://tiktak.by/api/mcp/v1/health"
# Ожидаем: 200 (BY IP)
```

- [ ] **Step 4: Коммит**

```bash
cd ~/sites/tiktakby
git add routes/api.php
git commit -m "security: enable mcp.geo country restriction on MCP API

mcp.geo was registered in Kernel.php but never applied to routes.
Restricts MCP API to BY/RU IPs (requires GeoLite2-Country.mmdb).
Fixes P2-1 from security audit 2026-06-07."
```

---

### Task 17: Security headers (P3-2)

**Repo:** `~/sites/tiktakby`

- [ ] **Step 1: Создать `SecurityHeaders` middleware**

```php
<?php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=()');
        return $response;
    }
}
```

- [ ] **Step 2: Зарегистрировать в `app/Http/Kernel.php` в глобальный стек**

```php
protected $middleware = [
    // ...существующие...
    \App\Http\Middleware\SecurityHeaders::class,
];
```

- [ ] **Step 3: Проверить заголовки**

```bash
curl -sI https://tiktak.by/ru/ | grep -iE "X-Frame|X-Content|Referrer"
# Ожидаем: все три заголовка присутствуют
```

- [ ] **Step 4: Коммит**

```bash
cd ~/sites/tiktakby
git add app/Http/Middleware/SecurityHeaders.php app/Http/Kernel.php
git commit -m "security: add security headers middleware (X-Frame-Options, X-Content-Type, Referrer-Policy)"
```

---

### Task 18: Sanitize SEO-поля против Stored XSS (P2-3)

**Repo:** `~/sites/tiktakby`

**Проблема:** `intro_text`, `seo_text`, `description` рендерятся через `{!! !!}` без санитайза:
- `resources/views/catpage.blade.php:34,198`
- `resources/views/home.blade.php:132`
- `resources/views/l3.blade.php:88`

Запись требует MCP-токена, но это XSS-сток для любого пути записи в `pages`/`rent_model_web`.

**Files:**
- Modify: `app/Http/Controllers/Mcp/PagesListingController.php` — sanitize при записи
- Modify: `app/Http/Controllers/Mcp/PagesProductController.php` — sanitize при записи

Выбрана стратегия: sanitize **при записи**, не при выводе — тогда в БД хранится уже безопасный HTML, и Blade-шаблоны трогать не нужно.

- [ ] **Step 1: Установить HTMLPurifier**

```bash
cd ~/sites/tiktakby
docker-compose exec app composer require ezyang/htmlpurifier
```

- [ ] **Step 2: Создать хелпер `app/MyClasses/HtmlSanitizer.php`**

```php
<?php

namespace App\MyClasses;

class HtmlSanitizer
{
    private static ?\HTMLPurifier $purifier = null;

    private static function purifier(): \HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = \HTMLPurifier_Config::createDefault();
            // Разрешаем форматирование: заголовки, списки, ссылки, таблицы, картинки
            $config->set('HTML.Allowed',
                'p,br,strong,em,b,i,u,ul,ol,li,h1,h2,h3,h4,h5,a[href|title],img[src|alt|width|height],table,tr,td,th,thead,tbody,blockquote,span[class],div[class]'
            );
            $config->set('HTML.AllowedAttributes', 'a.href,a.title,img.src,img.alt,img.width,img.height,*.class');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
            $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier'));
            self::$purifier = new \HTMLPurifier($config);
        }
        return self::$purifier;
    }

    public static function clean(?string $html): ?string
    {
        if ($html === null) return null;
        return self::purifier()->purify($html);
    }
}
```

- [ ] **Step 3: Добавить sanitize в `PagesListingController::update`**

```php
use App\MyClasses\HtmlSanitizer;

// В методе update/upsert — перед записью в БД:
$data = $request->validated();
foreach (['intro_text', 'seo_text', 'h1'] as $field) {
    if (isset($data[$field])) {
        $data[$field] = HtmlSanitizer::clean($data[$field]);
    }
}
// Затем записывать $data в БД
```

- [ ] **Step 4: Аналогично в `PagesProductController::update`**

```php
foreach (['description', 'meta_title', 'meta_description'] as $field) {
    if (isset($data[$field])) {
        $data[$field] = HtmlSanitizer::clean($data[$field]);
    }
}
```

- [ ] **Step 5: Создать storage директорию для HTMLPurifier кеша**

```bash
docker-compose exec app mkdir -p storage/app/htmlpurifier
docker-compose exec app php artisan storage:link 2>/dev/null; echo "ok"
```

- [ ] **Step 6: Проверить что XSS-пейлоад очищается**

```bash
curl -s -X PATCH "https://tiktak.by/api/mcp/v1/pages/listing/velosipedy" \
  -H "Authorization: Bearer $TIKTAK_MCP_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"intro_text": "<script>alert(1)</script>Текст"}'
# Затем:
curl -s "https://tiktak.by/ru/prokat-velosipedov" | grep -i "script"
# Ожидаем: script-тег удалён, текст остался
```

- [ ] **Step 7: Коммит**

```bash
cd ~/sites/tiktakby
git add app/MyClasses/HtmlSanitizer.php \
  app/Http/Controllers/Mcp/PagesListingController.php \
  app/Http/Controllers/Mcp/PagesProductController.php
git commit -m "security: sanitize HTML in SEO fields on write (prevent stored XSS)

Added HtmlSanitizer using HTMLPurifier. Applied to intro_text, seo_text,
h1 (PagesListingController) and description fields (PagesProductController).
Templates use {!! !!} — XSS blocked at DB-write stage.
Fixes P2-3 from security audit 2026-06-07."
```

---

### Task 19: Проверить и зафиксировать APP_DEBUG=false на проде (P2-7)

**Repo:** `~/sites/tiktak_v2/crm` и `~/sites/tiktakby`

- [ ] **Step 1: Проверить текущий прод-конфиг**

```bash
# Через SSH на сервере:
grep "APP_DEBUG\|APP_ENV" ~/public_html/.env
# Ожидаем: APP_DEBUG=false, APP_ENV=production

# Для CRM (если деплоится отдельно):
# grep "APP_DEBUG\|APP_ENV" <путь-к-crm-проду>/.env
```

- [ ] **Step 2: Поправить `.env.example` в обоих репо**

```bash
# В ~/sites/tiktakby:
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' ~/sites/tiktakby/.env.example 2>/dev/null

# В ~/sites/tiktak_v2/crm:
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' ~/sites/tiktak_v2/crm/.env.example
```

- [ ] **Step 3: Коммит CRM**

```bash
cd ~/sites/tiktak_v2/crm
git add .env.example
git commit -m "config: set APP_DEBUG=false in .env.example to prevent accidental debug on prod"
```

- [ ] **Step 4: Коммит tiktakby (если .env.example изменился)**

```bash
cd ~/sites/tiktakby
git add .env.example 2>/dev/null
git diff --staged --quiet || git commit -m "config: set APP_DEBUG=false in .env.example"
```

---

## Backlog (P1-7 и не срочное)

- **Laravel 8 → 10/11 + PHP 7.4 → 8.2** — крупный апгрейд, отдельный проект. Зафиксировать в `docs/backlog.md`.
- **CSP (Content-Security-Policy)** — нужен аудит всех inline-скриптов и CDN-зависимостей перед внедрением. Отдельная задача.
- **CSRF на формы `bb/`** — все ~60 форм в легаси требуют добавления hidden-токена. Отдельный проект.
- **CRM P3-5** (`$guarded=[]`, мёртвый VerifyCsrfToken, deal-tenant проверка в renderDeal) — мелочи, можно по ходу.

---

## Итоговая карта задач

| # | Задача | Приоритет | Репо | ~Время |
|---|--------|-----------|------|--------|
| 1 | Удалить опасные файлы | P0/P1 | tiktakby | 30 мин |
| 2 | Выключить display_errors | P1 | tiktakby | 15 мин |
| 3 | Ротация секретов (manual) | P1 | — | 1 час |
| 4 | auth_guard.php для bb/ | P0 | tiktakby | 2 часа |
| 5 | SQLi fix в поиске | P0 | tiktakby | 2 часа |
| 6 | SMS throttle + phone validation | P1 | tiktakby | 1 час |
| 7 | Rate-limit публичных форм | P1 | tiktakby | 30 мин |
| 8 | .htaccess defense-in-depth | P2 | tiktakby | 30 мин |
| 9 | Deploy.php hardening | P2 | tiktakby | 1 час |
| 10 | Open redirect + ReDoS fix | P2 | tiktakby | 2 часа |
| 11 | Cookie flags + SESSION_SECURE | P2 | tiktakby | 1 час |
| 12 | Referer open redirect | P3 | tiktakby | 30 мин |
| 13 | Хеширование паролей bb/ | P1 | tiktakby | 2 часа |
| 14 | CRM permission middleware | P0 | crm | 4 часа |
| 15 | CRM tenant scoping | P0 | crm | 4 часа |
| 16 | mcp.geo включить | P2 | tiktakby | 30 мин |
| 17 | Security headers | P3 | tiktakby | 1 час |
| 18 | Stored XSS sanitize SEO-полей | P2 | tiktakby | 2 часа |
| 19 | APP_DEBUG=false .env.example | P2 | crm+tiktak | 15 мин |
