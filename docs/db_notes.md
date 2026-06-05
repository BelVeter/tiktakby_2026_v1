# DB Notes & Findings (rent_orders / zvonki / заявки)

> Консолидированные находки по архитектуре заявок и особенностям БД. **Читать перед правками `rent_orders`, `rent_orders_arch`, `zvonki` и всего, что связано с заявками/бронями.** Накоплено в ходе редизайна заявок (2026-06-05), см. спеку [docs/superpowers/specs/2026-06-05-zayavki-redesign-design.md](superpowers/specs/2026-06-05-zayavki-redesign-design.md).

## ⚠️ Главные ловушки БД (gotchas)

1. **Позиционные `INSERT ... VALUES` повсюду в легаси.** Многие легаси-запросы пишут `INSERT INTO rent_orders VALUES ('', ...)` без списка колонок. **Добавление любой колонки в такую таблицу ломает ВСЕ такие INSERT** («Column count doesn't match value count»). Перед `ALTER TABLE ... ADD COLUMN` обязательно: (а) найти все позиционные вставки в эту таблицу (`grep -rniE "INSERT INTO <table> VALUES"`), (б) перевести живые на явный список колонок. Счётчики колонок: `rent_orders` = 29, `rent_orders_arch` = 32 (3 ведущих arch-поля), `zvonki` = 14.

2. **act/arch — сквозная hot/cold-конвенция (11 пар).** `rent_deals_act/arch`, `rent_sub_deals_act/arch`, `clients/clients_arch`, `tovar_rent_items/arch`, `karn_brons/arch`, `rent_orders/rent_orders_arch` и т.д. `_arch`-таблицы имеют **другую схему**: те же поля + ведущие `arch_*_id, arch_time, arch_who`. **Не сливать.** MCP-аналитика читает `UNION(active, arch)`; методология зафиксирована 2026-05-14 — менять только по согласованию (есть `LegacyParityTest`).

3. **Времена — Unix timestamp (int), не datetime.** `cr_time`, `ch_time`, `order_date`, `validity`, `react_time` и пр. — секунды Unix. Использовать `FROM_UNIXTIME()` / `UNIX_TIMESTAMP()`.

4. **`rent_orders.phone` = `bigint(15) NOT NULL` без дефолта.** Пустой телефон пишется как `0`; UI показывает телефон только при `phone > 1`. Значение `2147483647` (INT_MAX) встречалось как sentinel «телефон не указан» из веб-форм. Историческая особенность: **до ~2020 код вставки заявки вообще не имел поля phone** → 100% phone=0 в 2016–2019 (≈8700 архивных записей). С 2020 телефон сохраняется (phone=0 ≈ 1–2%, реальные край-кейсы — контакт в тексте: Viber/Telegram).

5. **Двойная архитектура доступа к БД.** Laravel (`app/`, `routes/`): Eloquent / Query Builder. Легаси (`bb/`): mysqli через `\bb\Db::getInstance()->getConnection()`. **Не смешивать в одном файле.** Легаси-страницы часто делают `foreach ($_POST as $k=>$v) { $$k = get_post($k); }` — variable-variables из `$_POST` (injection-prone); новый код так не писать, использовать prepared statements.

6. **Триггеров и хранимых процедур в БД нет** (проверено локально и на проде 2026-06-05). Вся логика — в PHP.

## Архитектура заявок (demand requests)

- **Заявка** = `rent_orders.type2='zayavka'` — запрос клиента на товар, которого нет в наличии. **Бронь** = `type2 IN (bron, deliv, remont, out)`. Одна таблица `rent_orders` хранит и то, и другое (+ `stirka`, `sell`).
- **Точки создания (каждая делает звонок + заявку за одну отправку):**
  - `app/Http/Controllers/L3Controller.php` — страница товара (нет в наличии);
  - `app/Http/Controllers/CartController.php` — оформление корзины;
  - `app/Http/Controllers/ZvonokController.php` — модалка заявки / обратный звонок;
  - `bb/zv_ch.php` — кнопка «Оформить заявку» (из звонка).
  - Ядро: `bb\classes\bron::createZayavka()` → `insert()` (чистый INSERT, **без дедупа**).
- **Точки редактирования (через `update()`, безопасно):** `bb/rent_zayavk.php` — действия «сохранить звонок», «недозвон», «самовывоз» (заявка→бронь, `z_to_br()`), «удалить» (`arch_copy()`+`del_br()`). Есть optimistic-lock по `ch_time`/`last_ch_time`.
- **Механизм задвоения:** сайт создаёт `zvonki` (уведомление) + `rent_orders` (заявка) за одну отправку, но **связи между ними нет** (`zvonki` не хранит `order_id`). Оператор в `zv_ch.php` жмёт «Оформить заявку» → создаётся вторая заявка. `Zvonok::addLitZvonok` имеет `isDublicate()`, а `createZayavka` — нет, и уникального индекса в `rent_orders` нет.
- **Soft-delete уже есть по факту:** «удалить» в `rent_zayavk.php` копирует в `rent_orders_arch`, затем удаляет из активной. Страница «Удалённые заявки» — `bb/rent_zayavk_arch.php`.
- **Бейджи** (`bb/bb_nav_badge.php`): «новые» = `type2='zayavka' AND info2 пусто`; «товар появился» = модель снова в наличии.
- **Потребители заявок (read):** `bb/reports.php`, MCP `OperationsController/MarketingController/MetaController/ExportController`. Аддитивные колонки им не мешают.

## Практика работы (по просьбе владельца)

> Владелец писал базу сам ~10 лет, самоучка; в коде есть легаси и неоптимальные решения, особенно ранние. **При каждом удобном случае предлагать мелкие безопасные (low-risk) правки** того кода, который и так трогаем. **Массовый рефакторинг легаси не делать без явного запроса.** Перед добавлением колонок — всегда проверять позиционные INSERT (gotcha №1).
