# Аудит безопасности 10.10.2026

Повод: ИИ-агент польского форка нашёл у себя четыре класса дыр. Польский сайт когда-то
форкнут с этого, поэтому прод tiktak.by проверен по тем же пунктам — снаружи, только
GET/HEAD-запросами, и по коду.

## Итог по пунктам польского аудита

| Пункт | У нас | Что сделано |
|---|---|---|
| 1. `bb/kb_ajax_eng.php` и другие скрипты `bb/` без входа | **Закрыто раньше** (PR #190, `bb/auth_guard.php`). На проде без входа — «Авторизация», API — `Unauthorized`/`forbidden`. | — |
| 2. SQL-инъекции на публичных страницах, текст запроса виден посетителю | **Есть** | ветка `fix/public-sql-injection` |
| 3. Служебные файлы скачиваются по прямой ссылке | **Есть, хуже польского**: открыт `/.env` | ветка `fix/block-service-files` |
| 4. Публичные формы без ограничения частоты | **Есть** | отложено, решение владельца |

## 3. Служебные файлы (ветка `fix/block-service-files`)

Причина: корень сайта = корень проекта, корневой `.htaccess` отдаёт любой существующий файл.
На проде перед Apache стоит nginx: `.env`, `.sql`, `.md`, логи он проксирует в Apache (ETag
вида `"размер-mtime"`), а `.zip`/`.rar`/картинки отдаёт сам (ETag `"mtime-размер"`) — на них
`.htaccess` не действует.

Было открыто (200): `/.env` (пароль БД, `APP_KEY`, `MCP_API_TOKEN`, почта, RocketSMS, ключ
Google Maps), `/.env.bak`, `/.env.example`, `/storage/logs/laravel.log` (4.5 МБ, `LOG_LEVEL=debug`),
`bb/error_log`, `/error_log`, `bb/logs/webp_conversion.log`, `bb/log_auto.txt`, `*.sql`,
`includes/20120204_212539.zip`, `AGENTS.md`, `CLAUDE.md`, весь `docs/` (в том числе прошлый аудит
`docs/security_audit_2026-06-07.md` и `docs/archive/*.csv`), `.claude/settings.local.json`,
`phpunit.xml`, `artisan`, `*.py`; выполнялись `check_canonical.php` (100 запросов к самому
сайту за вызов) и `scratch_schema_test.php`.

Сделано:
- корневой `.htaccess`: 404 для файлов и каталогов с точкой (кроме `.well-known/`), каталогов
  `app bootstrap config database docker docs resources routes storage tests vendor node_modules bb/logs`,
  расширений `env bak old orig save swp sql log md sh py lock yml yaml ini dist csv cache` и имён
  `error_log artisan phpunit.xml composer.json package*.json webpack.mix.js server.php log_auto.txt`.
  Правила стоят до правила для `bb/`. У `public/` свой `RewriteEngine`, поэтому туда добавлены
  те же правила для файлов;
- из git удалены `.env.bak`, все `error_log`, `*.sql`, архив `includes/*.zip`, одноразовые
  скрипты и отчёты в корне, `bb/log_auto.txt`, `.DS_Store`. `git reset --hard` в `Deploy.php`
  удалит их и на проде (они были отслеживаемыми);
- `bb/auto_merge_analyze.php`, `bb/auto_merge_duplicates.php`: пароль БД убран из кода (читается
  из `.env`), из браузера скрипты отвечают 404;
- `tests/Feature/ServiceFilesNotTrackedTest.php` — не даёт вернуть такие файлы в git, проверяет
  правила `.htaccess` и (если поднят локальный Apache) реальные ответы 404/200.

**Не закрывается кодом:** секреты из `.env` надо считать утёкшими и сменить (см. `docs/prod_pending.md`).
Пароль БД к тому же лежит в истории git и в `docker-compose.yml`/`docs/` — после смены на проде
это перестаёт иметь значение.

## 2. SQL-инъекции (ветка `fix/public-sql-injection`)

Проверено одной кавычкой в адресе:
- сегменты адреса каталога — `Razdel::getByUrlName`, `SubRazdel::getByUrlName`,
  `Category::getByUrlName` подставляли значение без экранирования; при ошибке `die()` выводил
  посетителю текст запроса. Срабатывало и на страницах товаров (сегменты идут в хлебные крошки);
- фильтр роста карнавальных костюмов `?rost=` (`Model.php`, два места) — `'1''` проходил
  проверку `> 0` и попадал в запрос.

Поиск, фильтр производителя и возраста защищены (привязка параметров, `real_escape_string`, `intval`).

## 4. Частота отправки форм — отложено

`/zvonok/kb` сразу ставит реальную бронь карнавального костюма; `/zvonok/bron`, `/zvonok`,
`/cart/checkout`, POST страницы товара — без `throttle`, капчи и honeypot. `TrustProxies::$proxies`
не задан — перед ограничением по IP проверить, какой IP видит Laravel за nginx/Cloudflare.
