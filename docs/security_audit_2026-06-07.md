# Аудит безопасности tiktak.by + CRM — план фиксов

**Дата:** 2026-06-07
**Объём:** легаси-админка `bb/`, Laravel-приложение сайта (`app/`, `routes/`, `.htaccess`, `Deploy.php`, MCP API), новая CRM `~/sites/tiktak_v2/crm`.
**Метод:** статический анализ кода (3 параллельных агента) + живая read-only проверка прод-эндпоинтов (только GET, без записи/деструктива).

> Документ — план для фиксов. Правки по нему НЕ внесены. Каждый пункт: серьёзность, доказательство (file:line / curl), как чинить, оценка трудозатрат.
> Шкала: **P0 Critical** (чинить сейчас) · **P1 High** · **P2 Medium** · **P3 Low/харднинг**.

---

## 0. Что подтверждено вживую на проде (read-only)

| URL | Код | Вывод |
|---|---|---|
| `https://tiktak.by/bb/` | 200 | **Логин-форма** (защищено ✅) |
| `https://tiktak.by/bb/reports.php` | 200 | **Логин-форма** (защищено ✅) |
| `https://tiktak.by/bb/delited_tovar.php` | 200 | ❌ **БЕЗ авторизации**: рендерит «Списанный товар» + светит PHP-ошибки |
| `https://tiktak.by/bb/kassa_operations.php` | 200 | ❌ **БЕЗ авторизации**: страница кассовых операций |
| `https://tiktak.by/bb/predzakaz.php` | 500 | ❌ **БЕЗ авторизации**: проходит гейт, падает без `client_id` (с `?client_id=` отдаёт договор с паспортными данными) |
| `https://tiktak.by/bb/w.gif` | 200 | ❌ Статика `bb/` отдаётся напрямую (обход Laravel-auth) |
| `https://tiktak.by/bb/img/bychok.gif` | 301 → `/ru/img/bychok.gif` | Путь устарел, конечный файл сейчас 404. Живых ссылок нет (см. §6) |
| `https://tiktak.by/.env` | **403** | Хостинг блокирует dotfiles ✅ (вопреки опасениям анализа кода) |
| `https://tiktak.by/.git/config` | **403** | Блокируется ✅ |
| `https://tiktak.by/tiktakby.sql` | 200, но `text/html` | Отдаётся homepage, файла на проде **нет** ✅ (это локальный артефакт) |
| `https://tiktak.by/Deploy.php` | **403** | Блокируется на уровне хостинга ✅ |

**Главный вывод по исходному вопросу SEO-агента:** раздел `/bb/` **частично открыт без авторизации**. Корень `/bb/` и большинство отчётов — за логин-формой, НО ряд PHP-файлов в `bb/` **не имеют гейта вообще** и доступны кому угодно из интернета (подтверждено `delited_tovar.php`, `kassa_operations.php`, `predzakaz.php`). Причина — архитектурная: `.htaccess`-правило `RewriteRule ^bb(/|$) - [L]` пускает все `/bb/`-запросы напрямую в Apache мимо Laravel-middleware, а защита реализована «опт-ин» в каждом файле отдельно (`if ($_SESSION['svoi'] != 8941) die(...)`). Любой забытый гейт = публичная дыра.

`/bb/img/bychok.gif` сам по себе не уязвимость — это устаревший путь, отдающий 301. Реальная проблема рядом — что вся статика и часть скриптов `bb/` доступны без пароля.

---

## P0 — Critical (чинить немедленно)

### P0-1. Неаутентифицированные страницы в `bb/` сливают PII и финданные / пишут в БД
**Где:** `bb/` — файлы без гейта `$_SESSION['svoi']==8941`.
**Подтверждено вживую:** `delited_tovar.php`, `kassa_operations.php`, `predzakaz.php`.
**Из кода (агент, не все проверены вживую — проверить и закрыть все):**

| Файл | Что делает без авторизации | Серьёзность |
|---|---|---|
| `bb/predzakaz.php` | Генерит RTF-договор по `?client_id=&inv_n=` с **паспортом, адресом, ФИО, телефонами** клиента. Перебор `client_id` = слив всей клиентской базы (pre-auth IDOR) | Critical |
| `bb/kassa_operations.php` | Кассовые операции; ветка сохранения пишет в БД (`KassaOperation->save()`) | Critical |
| `bb/update_db_schema.php` | Выполняет `ALTER TABLE ...` при заходе — мутация схемы | Critical |
| `bb/delited_tovar.php` | Отчёт «списанный товар»: данные сделок/клиентов/кассы | High |
| `bb/avif_to_webp.php` | Массовая конвертация файлов + запись путей в БД (DoS/мутация) | High |
| `bb/br_auto_arch_2.php` | Массовый `DELETE FROM karn_brons` в цикле | High |
| `bb/zakaz2.php`, `bb/l_3_br.php`, `bb/kb_ajax_eng.php` | Обработка `$_POST` в бронирования (создание/отмена) | High |
| `bb/kb_web_url.php`, `bb/test2.php` | Энумерация/выгрузка каталога | Medium |

**Фикс (рекомендуемый, закрывает класс целиком — fail-closed):**
1. Создать `bb/auth_guard.php` с единой проверкой сессии (вынести логику из `Base::loginCheck`), затем `require_once` первой строкой в КАЖДОМ entry-point. Список «публичных» файлов — явный whitelist, всё остальное закрыто по умолчанию.
2. **Дополнительно** на уровне веб-сервера (надёжнее всего): закрыть весь `/bb/` HTTP Basic Auth или IP-allowlist для всех `*.php`, оставив открытыми только нужные статики. Варианты:
   - `bb/.htaccess` с `AuthType Basic` (LiteSpeed/Apache поддерживает) — но проверить, что `RewriteRule ^bb - [L]` не ломает обработку; возможно потребуется `Require`-блок per-`<Files>`.
   - либо IP-whitelist офисов (IP офисов уже зашиты в `User::save()` — `86.57.139.9`, `82.209.203.36`, `86.57.159.29`).
3. Немедленно (до большого фикса) — удалить/закрыть заведомо опасные обработчики: `update_db_schema.php`, `avif_to_webp.php`, `br_auto_arch_2.php`, `dima_test.php`, `test2.php` и прочие «copy»/test-файлы (они не нужны в проде, см. P3-1).

**Трудозатраты:** гейт-инклюд + whitelist — ~0.5 дня; Basic Auth на `/bb/` — ~1 час (но нужно проверить, что не сломает легитимные AJAX office-флоу). Рекомендуется сделать ОБА слоя.

---

### P0-2. SQL-инъекция (pre-auth) в публичном поиске сайта
**Где:**
- `app/Http/Controllers/SearchController.php:18` → `ModelWeb::getModelIdsFullTextSearch($text)` → `bb/classes/ModelWeb.php:1184`: `... AGAINST('$text') ...` — `$text` из `request('search')` без экранирования. Доступно: `GET /{lang}/search?search=...`.
- `app/Http/Controllers/SearchController.php:84` → `Model::getModelIdsArrayByProducer($producer)` → `bb/classes/Model.php:195`: `... WHERE tovar_rent.producer='$producer' ...` — `$producer` из `request('producer')`. Доступно: `GET /{lang}/producer?producer=...`.

**Риск:** классическая инъекция через `'` без аутентификации, на самой посещаемой части сайта. Чтение всей БД, потенциально запись.

**Фикс:** в обоих методах `bb/classes/ModelWeb.php` и `bb/classes/Model.php` экранировать вход через `$this->mysqli->real_escape_string()` (минимально) либо перейти на prepared statements. Для full-text — экранировать и дополнительно чистить спецсимволы boolean-режима. Добавить тест в `tests/`.
**Трудозатраты:** ~2 часа + тест.

---

### P0-3. CRM: сломанный контроль доступа → эскалация привилегий
**Где:** `~/sites/tiktak_v2/crm`, `routes/web.php:39` — большая группа под `staff.auth` **без проверки ролей**. В `app/Http/Controllers/` нет ни одного `authorize()/->can()/hasPermission/abort(403)`.
**Самое опасное:** `app/Http/Controllers/IAM/StaffUserController.php` — `store/update/destroy` без проверки роли; `StoreStaffUserRequest::authorize()` возвращает просто `auth !== null`. → **любой залогиненный сотрудник (даже курьер) может создать пользователя с `role=owner`, удалить/понизить владельца, провести деньги, отменить операции, удалить клиентов.** Роли (`finance.manage`, `staff.manage` и т.д.) определены, но не применяются нигде, кроме catalog/inventory.

**Фикс:**
1. Ввести middleware проверки прав (напр. `permission:staff.manage`) и навесить на каждую не-каталожную группу маршрутов в `routes/web.php`, либо `$this->authorize(...)` в контроллерах.
2. Покрыть тестами негативные кейсы (курьер не может создать owner и т.п.).
**Трудозатраты:** ~1 день (middleware + развеска по всем группам + тесты).

---

### P0-4. CRM: межтенантный IDOR (нескоупленные резолверы)
**Где:** Core-резолверы не фильтруют по `tenant_id`:
- `core/src/IAM/Services/StaffResolver.php:22` — `StaffUser::find($id)` (без тенанта) → правка/удаление чужого персонала по id; `index()` листит всех тенантов.
- `core/src/CRM/Services/ClientService.php:34` — `Client::find($id)`; `list()` при `tenantId=null` (`:65`) **возвращает клиентов всех тенантов** (телефон, email, адрес, номера документов).
- `Finance/FinancialTransactionController::index` (`:25`) — транзакции без скоупа тенанта.

**Контраст:** `DocumentController`/`DocumentService` скоупят по тенанту корректно — то есть изоляция применяется непоследовательно.

**Риск:** post-auth, но при мультитенантности — чтение/правка чужих PII, персонала, финансов. Если деплой сейчас single-tenant — латентно, но чинить до подключения второго тенанта.
**Фикс:** заставить Core-резолверы требовать/форсить `tenantId` (изменение в `tiktak/core` — по протоколу координации core-изменений из CLAUDE.md CRM). CRM-контроллеры должны всегда передавать тенант из сессии.
**Трудозатраты:** ~1 день (core + CRM + тесты), плюс согласование core.

---

## P1 — High

### P1-1. `bb/`: пароли в открытом виде + слабая модель аутентификации
**Где:** `bb/models/User.php:146` — `SELECT ... WHERE log='$login' AND pass='$pas'` (плейнтекст-сравнение); `save()` (`:181`) пишет `pass='$this->password'` без хеша. Неудачные попытки логируются в `logpass_wrong` **вместе с введённым паролем** (тоже плейнтекст). Нет rate-limit/lockout — безлимитный брутфорс.
**Доп. слабости:** «секрет» — единственная константа `8941`; сравнение `==`, а не `===`; CSRF-токенов нет ни на одной форме (кроме `webp_converter.php`).
**Фикс:** перевести `logpass` на `password_hash()`/`password_verify()` (миграция: при логине, если совпал плейнтекст — пере-хешировать); не логировать сам пароль в `logpass_wrong`; добавить простой лимит попыток (счётчик в БД/сессии + задержка). CSRF — отдельной задачей (P2).
**Трудозатраты:** ~0.5–1 день (с миграцией паролей).

### P1-2. Утечка PHP-ошибок в проде (`display_errors=1`)
**Где:** `ini_set("display_errors",1); error_reporting(E_ALL);` в ~77 файлах `bb/`. Подтверждено вживую: `delited_tovar.php` светит `Fatal error`/`Notice`. Каждый `die('Сбой при доступе к базе данных: '.$query)` печатает сырой SQL и пути.
**Риск:** раскрытие SQL, путей ФС, структуры БД анонимам — упрощает эксплуатацию инъекций.
**Фикс:** глобально выключить `display_errors` на проде (php.ini / `.htaccess` `php_flag display_errors Off`), логировать в файл. Заменить `die($query)` на нейтральное сообщение. Точечно убрать `ini_set` из файлов.
**Трудозатраты:** ~2 часа (один глобальный флаг закрывает почти всё).

### P1-3. Захардкоженные секреты, требующие ротации
**Где:**
- `bb/Db.php:10-12` — прод-креды БД (`tiktakby_tiktak` / `Vai7evahch`) в репозитории.
- `bb/dima_test.php` — **ExpressPay токен** `3a0c82e3...08342` и секретное слово `Golacheva*8941`, в **неаутентифицированном** файле.
- `Deploy.php:3` — ключ деплоя `Deploy-Mb8941` в git (на проде Deploy.php отдаёт 403, но ключ лежит в истории).
- `CLAUDE.md` (в репо и в docroot) — печатает прод-пароль БД и имена токен-переменных. `*.md` могут отдаваться по HTTP (проверить).
**Фикс:** удалить `dima_test.php`; вынести креды БД в env/внешний файл (как уже сделано в `database.php` через `/dimanay.php`); **ротировать** утёкшие секреты (пароль БД, ExpressPay-токен, ключ деплоя); ключ деплоя — в env + `hash_equals` (P2-4); отредактировать CLAUDE.md (убрать реальный пароль) и закрыть `*.md`/`*.sql` от web (P2-2).
**Трудозатраты:** ~0.5 дня (плюс координация ротации ExpressPay с провайдером).

### P1-4. MCP API: SMS-эндпоинт — toll-fraud / спам
**Где:** `app/Http/Controllers/Mcp/SmsController.php:16-55` — принимает произвольные `phone`+`text`, единственный лимит — общий `throttle:60,1`. С валидным токеном — 60 SMS/мин на любые номера от имени компании (платный трафик, SMS-бомбинг).
**Фикс:** отдельный жёсткий throttle на `/sms/send`, валидация формата номера (префиксы BY/RU), суточная квота/allowlist.
**Трудозатраты:** ~2–3 часа.

### P1-5. Спам публичных форм (заявки/звонки/брони) — нет captcha/rate-limit
**Где:** `ZvonokController` (`zvonki`, `rent_orders`, `karn_brons`, `kb_zayavki`), `CartController::checkout` (`createBronStrong`), `L3Controller` (формы заказа). Web-группа без throttle (`RouteServiceProvider`). CSRF не спасает от скриптового POST (он сначала забирает токен).
**Риск:** тысячи фейковых лидов/броней, блокировка инвентаря фейк-бронями, расходы на SMS/email-уведомления, замусоривание CRM.
**Фикс:** per-IP rate-limit на эти маршруты (`throttle:`), captcha/honeypot на формах, серверная дедупликация.
**Трудозатраты:** ~0.5 дня.

### P1-6. Лишние/тестовые скрипты в webroot Laravel
**Где:** в корне репо — `test_sql.php`, `test_sql2.php` (гоняют сырые `DB::select` и `print_r`), `t2.php`/`insert_models.php` (`mysqli('127.0.0.1','root','',...)` — root без пароля, пишут в БД), `recover_from_archive.php`, `get_imgs2.php`, `get_live_images.php`, `test_images.php`. Часть в git, docroot=корень.
**Фикс:** удалить все из репо/webroot. Проверить вживую `curl -I https://tiktak.by/test_sql.php` (на проде может быть 403, как dotfiles — но `.php` вряд ли блокируется хостингом, так что вероятно достижимы).
**Трудозатраты:** ~1 час.

### P1-7. EOL-стек
**Где:** `composer.json` — Laravel **8.75** (security-EOL с янв 2023) на **PHP 7.4** (EOL ноя 2022). Накопленные непропатченные CVE фреймворка/рантайма.
**Фикс:** запланировать апгрейд до поддерживаемого Laravel + PHP 8.2+ (отдельный крупный проект, не разовый фикс).
**Трудозатраты:** большой; вынести в backlog как стратегическую задачу.

---

## P2 — Medium

### P2-1. MCP: middleware `mcp.geo` не применён
`routes/api.php:38` — группа использует `['mcp.json','mcp.token','mcp.audit','throttle:60,1']`; `mcp.geo` зарегистрирован (`Kernel.php:71`), но не навешен. Документированный гео-слой BY/RU = мёртвый код. Токен всё ещё требуется. **Фикс:** либо навесить `mcp.geo`, либо убрать из доков претензию на гео-защиту. (Плюс: `TrustProxies=null` → `X-Forwarded-For` не доверяется, но за Cloudflare реальные IP не видны — учесть при включении.) ~1 час.

### P2-2. Защита чувствительных путей на уровне веб-сервера (defense-in-depth)
Прод сейчас блокирует `.env`/`.git`/`Deploy.php` (403, видимо хостинг-дефолт), НО docroot = корень проекта, и это держится только на конфиге хостинга. **Фикс:** явно в `.htaccess` запретить `\.(env|git|sql|md|lock|json)$`, `.git/`, `storage/`, `composer.*`. Не полагаться на дефолты хостинга. ~1 час.

### P2-3. Stored XSS через SEO-поля, рендеримые `{!! !!}`
SEO-поля (`intro_text`→`getH1LongText`, `seo_text`→`getCodeBlock1`, `description`→`getDescription`) пишутся без санитайза и рендерятся сырыми: `catpage.blade.php:34,198`, `home.blade.php:132`, `l3.blade.php:88`. Запись требует MCP-токена (authed/insider), но это XSS-сток для любого пути записи в `pages`/`rent_model_web` (вкл. легаси `bb/`). **Фикс:** санитайз HTML при выводе (HTMLPurifier) или при записи. ~0.5 дня.

### P2-4. `Deploy.php` — ключ в GET + не timing-safe + без лимита
`Deploy.php:3` хардкод-ключ, `:6` сравнение `!==`, триггер по GET, без rate-limit/лога, эхо `whoami`/`pwd`. На проде сейчас 403, но файл в репо. **Фикс:** ключ в env + `hash_equals`, только POST, IP-allowlist + лог, либо вынести из webroot. ~2 часа.

### P2-5. Open-redirect + подконтрольный regex в redirects
`CheckRedirects.php:100` — `redirect($redirect->target_url)` без проверки хоста (open redirect); `:116-117` — `preg_match/preg_replace` по сырому пользовательскому `source_url` при `is_regex=1` → ReDoS/циклы. Запись требует токена. **Фикс:** валидировать `target_url` (относительный/тот же хост), валидировать/санитайзить regex-паттерны, лимит времени `preg`. ~3 часа.

### P2-6. Доверие к cookie `tt_is_logged_in` (presence-only)
Документированный паттерн `@if(isset($_COOKIE['tt_is_logged_in']))` показывает «админский» контент любому, кто выставит cookie у себя в браузере (значение не подписано/не валидируется). В текущих Blade использований не нашли, но гайд опасен. Cookie ставится без HttpOnly/Secure/SameSite (`bb/index.php:37`, `bb/one_login.php:108`). **Фикс:** никогда не гейтить ничего по присутствию этого cookie; флаги HttpOnly/Secure/SameSite на cookie. ~1–2 часа.

### P2-7. CRM: APP_DEBUG=true / APP_ENV=local
`.env` (и `.env.example`) в CRM — `APP_DEBUG=true`, `APP_ENV=local`. Дефолты `config/app.php` безопасны, риск — если эти значения уедут в прод (Whoops/Ignition светит исходники и env). **Фикс:** убедиться, что прод-`.env` = `APP_DEBUG=false`, `APP_ENV=production`; поправить `.env.example`. ~15 минут. (Аналогично проверить прод-`.env` основного сайта — рабочий `.env` на дев-машине имеет `APP_DEBUG=true`.)

### P2-8. Сессионные cookie сайта: `SESSION_SECURE_COOKIE`
`config/session.php:171` — `'secure' => env('SESSION_SECURE_COOKIE')` (дефолт null/false). На HTTPS-сайте должно быть `true`. **Фикс:** выставить `SESSION_SECURE_COOKIE=true` в прод-`.env`. ~10 минут.

---

## P3 — Low / харднинг

- **P3-1. Чистка мёртвого кода `bb/`:** `dima_test.php`, `test2.php`, `t.php`, `tp.php`, `mytest*.php`, `*copy*.php`, `up-to-low.php` (использует удалённый `mysql_*` API → фаталит). Уменьшить attack surface. ~1 час.
- **P3-2. Security-заголовки** (оба сайта): CSP, X-Frame-Options, X-Content-Type-Options, HSTS — нет. Добавить middleware/`.htaccess`. ~2 часа.
- **P3-3. Open-redirect по Referer:** `ZvonokController.php:65-67` — `Redirect::to($referer.'?ck=zvonok')` без валидации хоста. ~30 мин.
- **P3-4. Необработанные `new DateTime($userInput)`** → 500: `ZvonokController.php:194,202-203`. Обернуть в try/catch. ~30 мин.
- **P3-5. CRM мелочи:** `$guarded=[]` в translation-моделях core (L1, сейчас недостижимо через `request->all()`, но хрупко); мёртвый кастомный `VerifyCsrfToken` в CRM (L2, вводит в заблуждение); `placeholders`→DOCX валидировать по whitelist (M1); `renderDeal` — проверять принадлежность `deal_id` тенанту (M2). По ~30–60 мин каждое.
- **P3-6. `===` вместо `==`** для сравнения `$_SESSION['svoi']` по всему `bb/` (косметика, прямого байпаса из пользовательского ввода не нашли).

---

## Что проверено и оказалось ОК (хорошие новости)

- MCP token: `hash_equals` + env (`McpTokenMiddleware.php:27`), не хардкод — корректно.
- MCP write-контроллеры: параметризованные запросы / Query Builder bindings — SQLi не найдено.
- CSRF на сайте включён глобально, без исключений; `CartController` пересчитывает цену на сервере («never trust client»).
- `.env`/`.git`/`Deploy.php` на проде отдают **403**; `tiktakby.sql` на проде **отсутствует** (локальный артефакт) — критичной утечки `.env`/дампа БД на проде нет.
- CRM: `.env` НЕ в git; docroot = `public/` (Dockerfile переопределяет DocumentRoot) — `.env`/`.git`/`storage` не доступны по web; нет SQLi/XSS/командных инъекций/секретов в git. Аутентификация (guard `staff`, bcrypt rounds 12, регенерация сессии, проверка `is_active`) — крепкая; проблема только в авторизации (P0-3/P0-4).
- `Options -Indexes` включён в обоих `.htaccess` — листинга директорий нет.

---

## §6. Разбор исходного SEO-наблюдения (`/bb/img/*.gif`)

- `GET /bb/img/bychok.gif` → **301** → `/ru/img/bychok.gif` → сейчас **404**. Правила в таблице `redirects` нет (`/api/mcp/v1/redirects?search=bb/img` → 0 строк); в коде/CSS/шаблонах ссылок на `bb/img` нет; на живой главной и `/ru/` ссылок на `bb/img`/`bychok` нет. Вывод: Ahrefs идёт по **историческим** ссылкам, путь устарел, конечный ассет удалён. **Это не уязвимость.**
- НО смежный факт реален и важен: `.htaccess`-правила `^bb(/|$) - [L]` + passthrough картинок отдают **всю статику `bb/` без авторизации** (`/bb/w.gif` = 200). В каталоге `bb/` лежат, помимо картинок, и шаблоны договоров `*.rtf` (пустые бланки — без данных клиентов, но публично доступны). Закрытие `/bb/` Basic-Auth/IP-allowlist (P0-1, слой 2) попутно убирает и это.
- 301-редирект `/bb/img/ → /ru/img/` в локальном репо не найден — вероятно, правило живёт в прод-`.htaccess` или конфиге хостинга. На SEO это влияет (битые внешние ссылки → 404), на безопасность — нет. Рекомендация для SEO: либо отдавать 410 на `/bb/img/*`, либо вернуть ассет, либо убрать внешние ссылки.

---

## Сводный roadmap (рекомендуемый порядок)

1. **Сегодня:** удалить опасные unauth-обработчики `bb/` (`update_db_schema.php`, `avif_to_webp.php`, `br_auto_arch_2.php`, `dima_test.php`, тест-файлы корня) → ротировать ExpressPay-токен и пароль БД → выключить `display_errors` на проде. *(P0-1 частично, P1-2, P1-3, P1-6)*
2. **Эта неделя:** Basic-Auth/IP-allowlist на весь `/bb/` + единый fail-closed гейт-инклюд *(P0-1)*; экранировать SQLi в поиске *(P0-2)*; throttle+captcha на публичные формы и SMS *(P1-4, P1-5)*.
3. **Следующая:** CRM — permission-middleware *(P0-3)* и тенант-скоуп резолверов *(P0-4)*; хеширование паролей `bb/` *(P1-1)*.
4. **Затем:** P2-харднинг (geo, web-server deny-rules, XSS-санитайз, Deploy.php, redirects, cookie-флаги, APP_DEBUG/secure-cookie проверки).
5. **Backlog:** апгрейд Laravel/PHP *(P1-7)*, security-заголовки, чистка легаси.

> Прим.: владелец предпочитает мелкие безопасные правки по ходу; крупный рефакторинг легаси — только по явному запросу. P0/P1 здесь — точечные и безопасные; масштабный апгрейд стека (P1-7) — отдельное решение.
