# История изменений тарифов + аналитика влияния на сделки

**Дата:** 2026-07-31
**Статус:** согласовано, готово к планированию реализации

## Задача

Сохранять историю любого изменения тарифа (создание, правка, удаление), чтобы затем
анализировать влияние ценовых решений на поток новых сделок. Анализ строится
пользователем поверх данных MCP API; готовый расчёт «эффекта» в объём не входит.

## Что есть сейчас

Точек записи в `rent_tarif_act` мало — это делает задачу выполнимой без триггеров:

| Место | Операции |
|-------|----------|
| [bb/rent_tarifs.php:346-439](../../../bb/rent_tarifs.php#L346-L439) | INSERT / UPDATE / DELETE — единственный интерактивный редактор |
| [bb/classes/Tariff.php:58-75](../../../bb/classes/Tariff.php#L58-L75) | `saveNew()` / `update()` — используется веткой «авто расчёт» |
| [bb/classes/ModelArchive.php](../../../bb/classes/ModelArchive.php) | удаление тарифов при архивации модели |
| `database/migrations/2026_07_28_*` | разовые чистки каталога |

Проблемы текущего состояния:

1. **История есть только для удалений.** Таблица `rent_tarif_prev` (158 строк, 2013–2025)
   заполняется единственной веткой `удалить` в `rent_tarifs.php`. При UPDATE старое
   значение затирается безвозвратно.
2. **Потеря копеек в архиве.** `rent_tarif_prev.rent_amount` объявлен `DECIMAL(11,1)`
   против `DECIMAL(11,2)` в `rent_tarif_act`. Уже записанные данные округлены —
   восстановлению не подлежат.
3. **`change_who` не нормализован.** Хранит вперемешку id (`777`, `26`, `2`, `5`) и имена
   (`Кристина`, `Аня`, `Юля`). Фильтр «правки конкретного сотрудника» построить нельзя.
4. **Несогласованность записи актора.** `rent_tarifs.php` пишет `$_SESSION['user_fio']`,
   `Tariff.php` — `User::getCurrentUser()->id_user`.
5. **В MCP API нет ничего по тарифам,** кроме текущего среза в `/inventory/pricing`.
6. **`/inventory/utilization` считает сделки, пересекающие период,** а не начатые в нём —
   для анализа «новые сделки до/после смены цены» методика не подходит.

Триггеров и хранимых процедур в базе нет вообще — вводить их значило бы вводить новый
для проекта паттерн, зависящий от привилегий shared-хостинга.

## Принятые решения

| Развилка | Решение |
|----------|---------|
| Цель | Журнал событий + восстановление прайса на произвольную дату |
| Стартовая точка | Baseline текущего состояния + импорт `rent_tarif_prev` как legacy |
| UI | Блок истории на странице тарифов модели в `bb/` |
| API | `/pricing/history`, `/pricing/snapshot`, `/operations/deals-by-model` |
| Механизм захвата | Из кода, через класс `Tariff` (не триггеры БД) |

## Секция 1. Модель данных

Одно событие = **полный снапшот строки тарифа до и после**, а не «одна строка на изменённое
поле» (как в `mcp_content_versions`). Причина: восстановление прайса на дату D сводится
к «для каждого `tarif_id` взять последнее событие с `changed_at ≤ D`» — один запрос без
проигрывания всей ленты с начала времён.

```sql
CREATE TABLE rent_tarif_history (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tarif_id       INT NOT NULL,             -- id строки в rent_tarif_act
  model_id       INT NOT NULL,
  change_type    ENUM('baseline','create','update','delete') NOT NULL,
  changed_at     INT NOT NULL,             -- unix ts, как везде в легаси
  actor_user_id  INT NULL,                 -- нормализованный id пользователя
  actor_name     VARCHAR(128) NULL,        -- ФИО на момент действия
  source         VARCHAR(32) NOT NULL,     -- bb_admin | model_archive | migration
                                           -- | legacy_import | baseline
  ip             VARCHAR(45) NULL,

  old_step VARCHAR(16) NULL, old_kol_vo INT NULL, old_kol_vo_min INT NULL,
  old_rent_amount DECIMAL(11,2) NULL, old_rent_per_step DECIMAL(11,2) NULL,
  old_start_date INT NULL, old_sort_num INT NULL,

  new_step VARCHAR(16) NULL, new_kol_vo INT NULL, new_kol_vo_min INT NULL,
  new_rent_amount DECIMAL(11,2) NULL, new_rent_per_step DECIMAL(11,2) NULL,
  new_start_date INT NULL, new_sort_num INT NULL,

  note VARCHAR(255) NULL,
  KEY idx_th_model_time (model_id, changed_at),
  KEY idx_th_tarif_time (tarif_id, changed_at),
  KEY idx_th_time (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Правила заполнения:

- `create` и `baseline` — `old_*` = NULL;
- `delete` — `new_*` = NULL;
- `update` — заполнены обе группы;
- деньги `DECIMAL(11,2)`, один в один с `rent_tarif_act` (не повторяем баг `(11,1)`).

Актор разложен на два поля намеренно: `actor_user_id` для фильтрации, `actor_name` для
читаемости через годы, когда сотрудник уже уволен. Колонку `change_who` в
`rent_tarif_act` не трогаем — её читает легаси-код.

## Секция 2. Точки записи

Новый класс `bb\classes\TariffHistory` — единственный, кто пишет в таблицу:

- `log($changeType, ?Tariff $before, ?Tariff $after, string $source, ?string $note)`
- `forModel($modelId, $limit)` — чтение для админки
- `snapshotAt($ts, array $modelIds)` — состояние тарифов на момент времени

Изменения в существующем коде:

| Файл | Что делаем |
|------|-----------|
| [bb/classes/Tariff.php](../../../bb/classes/Tariff.php) | `saveNew()` пишет `create`; `update()` читает состояние **до** UPDATE и пишет `update`; новый `delete()` пишет `delete`. Всё в транзакции: не записалась история — откатывается и правка тарифа. `update` не пишется, если сравнение полей показало отсутствие изменений. |
| [bb/rent_tarifs.php:346-439](../../../bb/rent_tarifs.php#L346-L439) | Три ветки `switch` с сырыми SQL переводим на класс `Tariff`. Заодно убираем `$$key = get_post($key)` (строка 341) — разворот всего `$_POST` в переменные без белого списка. |
| [bb/classes/ModelArchive.php](../../../bb/classes/ModelArchive.php) | При архивации модели логируем `delete` по каждому тарифу, `source='model_archive'`. |
| `rent_tarif_prev` | Прекращаем писать — журнал её замещает. Таблицу и данные не удаляем: ничего в коде из неё не читает (миграции только чистят, `ModelArchive` кладёт в JSON-снапшот). |

**Известный риск.** При захвате из кода будущий сырой `UPDATE rent_tarif_act` мимо класса
`Tariff` в журнал не попадёт. Смягчение: правило в [docs/db_notes.md](../../db_notes.md) и
[docs/tariffs.md](../../tariffs.md) + guard-тест, проверяющий, что сырых DML-запросов
по `rent_tarif_act` вне `bb/classes/Tariff.php` не осталось.

## Секция 3. Baseline и импорт старой истории

Одна Laravel-миграция:

1. Создаёт таблицу.
2. **Baseline**: каждая строка `rent_tarif_act` (на текущем дампе — 8 260) → событие
   `baseline` с `changed_at = change_date` (реальная дата последней правки),
   `new_*` = текущие значения, `source='baseline'`. Актор разбирается из `change_who`:
   строка целиком из цифр → `actor_user_id`, иначе → `actor_name`.
3. **Импорт legacy**: каждая строка `rent_tarif_prev` (158) → событие `delete` с
   `changed_at = change_date`, `old_*` из архива, `tarif_id = tarif_act_id`,
   `source='legacy_import'`, `note` про округлённые до десятых копейки.

Миграция идемпотентна: заливает данные, только если таблица пуста. `down()` — drop table.

**Граница достоверности.** Baseline фиксирует, как строка выглядит сейчас, датируя это
последней правкой. Что было до неё — неизвестно. Правило снапшота на дату D для строки
тарифа:

| Условие | Результат |
|---------|-----------|
| есть событие с `changed_at ≤ D` | значения из события, `extrapolated: false` |
| события нет, но `start_date ≤ D` | baseline-значения, `extrapolated: true` |
| тариф создан позже D (`create` после D либо `start_date > D`) | в снапшот не попадает |

Доля extrapolated-строк выводится в `meta.warnings` ответа и естественно убывает по мере
накопления реальных событий.

## Секция 4. MCP API

Новый контроллер `app/Http/Controllers/Mcp/PricingController.php` (extends `BaseController`),
маршруты в [routes/api.php](../../../routes/api.php) под тем же стеком
`mcp.json → mcp.token → mcp.geo → mcp.audit → throttle:60,1`, спека дописывается в
`resources/openapi/mcp-v1.json`. Ответы — стандартный конверт `{query, data, meta}`.

### GET /pricing/history

Параметры: `model_id`, `category`, `from`, `to`, `change_type`,
`actor_user_id` (числовой id; фильтрация по имени не поддерживается — у legacy-событий
имя может быть не заполнено), `limit` (≤500, по умолчанию 100), `offset`.
Кэш `TTL_DEFAULT`.

```json
{ "event_id": 1024, "changed_at": "2026-07-15T10:22:00+03:00",
  "change_type": "update", "source": "bb_admin",
  "model_id": 1069, "model_name": "Коляска X", "tarif_id": 8123,
  "actor": { "user_id": 5, "name": "Юля" },
  "before": { "step": "week", "kol_vo": 2, "rent_amount": "85.00", "price_per_day": "6.07" },
  "after":  { "step": "week", "kol_vo": 2, "rent_amount": "95.00", "price_per_day": "6.79" },
  "delta_amount_byn": "10.00", "delta_pct": 11.8 }
```

`price_per_day = rent_amount / (kol_vo × дни_шага)`, дни шага фиксированы
`day=1 / week=7 / month=30` согласно [docs/tariffs.md](../../tariffs.md). Без нормализации
тарифы с разным шагом между собой несравнимы.

### GET /pricing/snapshot

Параметры: `as_of` (обязательный, `YYYY-MM-DD`), `model_id`, `category`. Кэш `TTL_HEAVY`.
Отдаёт по каждой модели список тарифов, действовавших на дату, `min_price_per_day` и
флаг `extrapolated` (см. правило в секции 3).

### GET /operations/deals-by-model

Параметры: `from`, `to`, `granularity` (`day|week|month`), `model_id`, `category`,
`include_carnival`. Кэш `TTL_HEAVY`.

Методика строго по CLAUDE.md:

- источник сделок — `UNION(rent_deals_act, rent_deals_arch)`, `_act` содержит ~430 открытых
  сделок и пропускать его нельзя;
- период определяется по `da.cr_time` — как в `/operations/funnel` и `/operations/timeline`.
  Это дата заведения сделки, то есть момент решения клиента;
- модель разрешается через `tovar_rent_items(_arch).model_id` по `item_inv_n`;
- фильтр по разделу — через `BaseController::itemsInRazdelSubquery()`, прямой join
  `subrazdel_category × razdel_subrazdel` даёт M×N раздутие.

```json
{ "model_id": 1069, "model_name": "Коляска X", "period": "2026-07",
  "deals_started": 14, "avg_units": 9.0, "deals_per_unit": 1.56 }
```

`deals_per_unit` включён намеренно: голое число сделок смешивает эффект цены с эффектом
закупки новых юнитов. Знаменатель считает существующий `modelInventoryAtDate()` — сейчас
он `private` в `InventoryController`, переносим его в `BaseController` как `protected`,
рядом с `unifiedDealsSubquery()` / `unifiedItemsSubquery()` / `itemsInRazdelSubquery()`.
Поведение метода не меняется, `/inventory/utilization` продолжает вызывать его как раньше.

## Секция 5. Админка

Под таблицей тарифов в [bb/rent_tarifs.php](../../../bb/rent_tarifs.php) — сворачиваемый блок
«История изменений» по текущей модели: дата, кто, тип операции, было → стало, процент
изменения. Последние 50 событий, ссылка «показать все». Серверный рендер, тем же
inline-JS паттерном show/hide, что уже используется в файле для редактирования тарифов.

## Секция 6. Тестирование

- **Unit** `tests/Unit/TariffHistoryTest.php`: `create`/`update`/`delete` пишут корректные
  события; UPDATE без фактических изменений событие не пишет; расчёт `price_per_day` и
  `delta_pct`; спец-округление `roundHalfEur()` не сломано.
- **Feature** `tests/Feature/Mcp/PricingApiTest.php`: три эндпоинта — коды ответов, конверт
  `{query, data, meta}`, работа фильтров, `as_of` в прошлом отдаёт исторические значения,
  корректность флага `extrapolated`.
- **Feature**: миграция идемпотентна; число записанных событий сверяется с
  `COUNT(rent_tarif_act) + COUNT(rent_tarif_prev)` текущей базы, а не с числом из этой
  спеки — на проде цифры отличаются от локального дампа (8 260 + 158).
- **Guard-тест**: сырых `INSERT|UPDATE|DELETE ... rent_tarif_act` вне
  `bb/classes/Tariff.php` не осталось. Исключение — `database/migrations/`: разовые
  миграции каталога правят таблицу напрямую по своей природе, они из проверки исключаются.
- Существующий `tests/Feature/Mcp/LegacyParityTest.php` должен продолжать проходить.

## Не входит в объём

- Правки цен в уже заключённых сделках (`rent_sub_deals_*.tarif_value`) — отдельная сущность
  со своей историей.
- Готовый эндпоинт расчёта «эффекта изменения» — анализ строится поверх двух рядов
  (`/pricing/history` + `/operations/deals-by-model`) самостоятельно.
- Починка `DECIMAL(11,1)` в `rent_tarif_prev` — записанные данные уже округлены,
  восстанавливать нечего.
- Массовый рефакторинг `bb/rent_tarifs.php` за пределами трёх веток записи и снятия
  `$$key = get_post($key)`.

## Документация к обновлению

- [docs/tariffs.md](../../tariffs.md) — раздел про историю изменений и новую таблицу.
- [docs/db_notes.md](../../db_notes.md) — правило «тарифы правим только через `bb\classes\Tariff`»;
  `rent_tarif_history` в списке таблиц с `model_id` (сейчас их 13, станет 14 — важно при
  слиянии и архивации моделей).
- [CLAUDE.md](../../../CLAUDE.md) и `AGENTS.md` — новый контроллер `PricingController`,
  обновлённое число эндпоинтов MCP API.
- `resources/openapi/mcp-v1.json` — спецификации трёх эндпоинтов.
