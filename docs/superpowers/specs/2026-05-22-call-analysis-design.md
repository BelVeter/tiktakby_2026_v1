# Call Analysis Page — Design Spec

**Date:** 2026-05-22  
**Status:** Approved  
**Branch target:** `main`

---

## Overview

Страница анализа звонков для внутренней админ-панели (`bb/`). Показывает все звонки за выбранный день (CDR), статистику, ИИ-сводку и детали каждого звонка. ИИ-агент обрабатывает записи асинхронно и возвращает результаты через MCP API.

---

## 1. База данных

### 1.1 `a1_cdr` — все звонки (CDR)

```sql
id             bigint PK autoincrement
uuid           varchar(100) UNIQUE
call_date      datetime     INDEX
call_type      enum('incoming','outgoing','missed') INDEX
caller_number  varchar(30)
callee_number  varchar(30)
call_duration  smallint unsigned  -- секунды, 0 для пропущенных
recording_uuid varchar(100) NULL  -- FK → a1_call_recordings.uuid
created_at     timestamp
```

Заполняется командой `a1:fetch-cdr`. После вставки строки автоматически проставляет `recording_uuid`, если совпадающая запись уже есть в `a1_call_recordings`.

### 1.2 `a1_call_analysis` — ИИ-анализ на каждую запись

```sql
id               bigint PK autoincrement
recording_uuid   varchar(100) UNIQUE  -- FK → a1_call_recordings.uuid
transcript       longtext NULL
ai_summary       text NULL            -- краткое описание разговора
ai_result        varchar(100) NULL    -- new_client|booking|complaint|info|other
ai_result_detail text NULL
ai_status        enum('pending','processing','done','error') DEFAULT 'pending' INDEX
ai_error         text NULL
ai_processed_at  datetime NULL
created_at       timestamp
updated_at       timestamp
```

Запись создаётся автоматически (со статусом `pending`) при добавлении новой строки в `a1_call_recordings`. Статус меняется на `processing` при первой отдаче агенту через `pending-analysis`, на `done`/`error` после ответа агента.

### 1.3 `a1_daily_summaries` — дневные сводки ИИ

```sql
id              bigint PK autoincrement
summary_date    date UNIQUE INDEX
summary_text    text NULL
total_calls     smallint unsigned DEFAULT 0
incoming_calls  smallint unsigned DEFAULT 0
outgoing_calls  smallint unsigned DEFAULT 0
missed_calls    smallint unsigned DEFAULT 0
calls_analyzed  smallint unsigned DEFAULT 0
key_themes      json NULL           -- ["возврат", "бронирование", ...]
created_at      timestamp
updated_at      timestamp
```

---

## 2. Новая Artisan-команда `a1:fetch-cdr`

- Та же схема авторизации и rate-limiting, что у `FetchA1Recordings`
- Период: по умолчанию последние 90 минут; при первом запуске — последние 30 дней
- Дедупликация по `uuid`
- После вставки CDR-строки: если `a1_call_recordings.uuid = cdr.uuid` — проставляет `recording_uuid`
- После вставки записи в `a1_call_recordings`: создаёт запись `a1_call_analysis(recording_uuid, status=pending)`
- Лог: отдельная таблица `a1_cdr_fetch_log` (структура идентична `a1_recordings_fetch_log`)
- Расписание: каждый час через `app/Console/Kernel.php`

---

## 3. Страница `bb/a1_calls.php`

### 3.1 Структура (сверху вниз)

```
┌──────────────────────────────────────────────────────────────┐
│  [← ]  [22.05.2026 ▼]  [→]                                  │
│  Все | Входящие | Исходящие | Пропущенные                    │
├───────────┬──────────┬───────────┬───────────┤               │
│ Всего: 47 │ Вход: 23 │ Исход: 18 │ Пропущ: 6 │               │
└───────────┴──────────┴───────────┴───────────┘
┌── ИИ-сводка за день ──────────────────────── [▼/▲] ──────┐
│ Основные запросы — детские коляски и самокаты.           │
│ Ключевые темы: возврат, бронирование, наличие товара     │
└───────────────────────────────────────────────────────────┘
┌──────┬──────┬────────────┬──────┬───────────┬────────┬───┬───┐
│Время │ Тип  │   Номер    │Длит. │Краткое оп.│Рез. ИИ│ T │ ▶ │
├──────┼──────┼────────────┼──────┼───────────┼────────┼───┼───┤
│09:14 │  ↓   │+375291234  │ 3:22 │"Интересов.│Новый   │[T]│[▶]│
│      │ вх.  │→ офис №2   │      │ колясками"│клиент  │   │   │
├──────┼──────┼────────────┼──────┼───────────┼────────┼───┼───┤
│09:45 │  ✗   │+375441234  │  —   │  (нет зап.)│  —    │ — │ — │
└──────┴──────┴────────────┴──────┴───────────┴────────┴───┴───┘
```

### 3.2 Элементы управления

- **Дата-навигатор**: стрелки `←` / `→` (±1 день), клик на дату открывает `<input type="date">`; по умолчанию — сегодня
- **Фильтр по типу**: Все / Входящие / Исходящие / Пропущенные — GET-параметр `?type=all|incoming|outgoing|missed`
- **Дата** — GET-параметр `?date=YYYY-MM-DD`

### 3.3 Блок статистики

Четыре плитки: Всего / Входящие / Исходящие / Пропущенные. Цифры берутся из `a1_cdr` по выбранной дате.

### 3.4 Блок ИИ-сводки

- Показывается если в `a1_daily_summaries` есть строка для выбранной даты
- Свёрнут/развёрнут кликом (состояние в `localStorage`)
- Если сводки нет — серая плашка "Сводка ещё не готова"

### 3.5 Таблица звонков

Столбцы:
| Столбец | Источник |
|---------|---------|
| Время | `a1_cdr.call_date` → HH:MM |
| Тип | `a1_cdr.call_type` → иконка ↓ вх. / ↑ исх. / ✗ пр. |
| Номер | `caller_number → callee_number`, имя клиента из `clients` по номеру |
| Длит. | `a1_cdr.call_duration` → MM:SS, прочерк для пропущенных |
| Краткое описание | `a1_call_analysis.ai_summary`, серый плейсхолдер "ожидает обработки" |
| Результат ИИ | `a1_call_analysis.ai_result` → цветной бейдж |
| T (транскрипция) | Кнопка, только если `recording_uuid IS NOT NULL`; открывает Bootstrap-модалку с `transcript` |
| ▶ (запись) | HTML5 `<audio>` с `src=/api/mcp/v1/calls/recordings/{uuid}/file` + Bearer-токен через Blob URL; только если `recording_uuid IS NOT NULL` |

### 3.6 CRM-обогащение

При выводе строки: `LEFT JOIN clients ON clients.phone LIKE CONCAT('%', last10digits, '%')`. Показывает имя клиента под номером телефона. Логика аналогична существующей в `a1_missed_calls.php`.

### 3.7 Значок в навигации

В `bb/bb_nav.php` добавляется бейдж с количеством необработанных ИИ-анализом записей за последние 7 дней (`a1_call_analysis.ai_status = 'pending'`). Запрос кешируется на 5 минут.

### 3.7.1 Аудио-маршрут для bb/

MCP API `/calls/recordings/{uuid}/file` требует Bearer-токен — из браузера в контексте bb/ это неудобно. Добавляется отдельный web-маршрут:

```
GET /bb-internal/audio/{uuid}
```

Проверяет `$_COOKIE['tt_is_logged_in']` (та же bb/-авторизация). Переиспользует логику стриминга из `CallsController::streamFile`. Маршрут добавляется в `routes/web.php` через отдельный контроллер `BbAudioController`.

### 3.8 Экспорт CSV

Кнопка "Скачать CSV" в шапке страницы — выгружает все звонки за выбранный день (дата, тип, номер, длительность, краткое описание, результат ИИ).

---

## 4. MCP API — новые эндпоинты

Все под тем же middleware стеком: `mcp.json → mcp.token → mcp.geo → mcp.audit → throttle:60,1`.

Контроллер: `App\Http\Controllers\Mcp\CallsController` (расширяется).

### 4.1 `GET /api/mcp/v1/calls/cdr`

Параметры: `from`, `to`, `call_type` (all|incoming|outgoing|missed), `page`, `per_page` (макс. 200).

Ответ: CDR-строки с `recording_uuid` (null если нет записи).

### 4.2 `GET /api/mcp/v1/calls/pending-analysis`

Параметры: `from`, `to` (YYYY-MM-DD, по умолчанию вчера/сегодня), `limit` (макс. 50).

Возвращает записи где `ai_status = 'pending'`. Перед выборкой: записи со статусом `processing` дольше 2 часов сбрасываются обратно в `pending` (защита от зависания при краше агента). При чтении автоматически переводит отданные записи в `ai_status = 'processing'` (batch update). Каждая строка включает `file_url` — полный URL для скачивания аудио через MCP API.

### 4.3 `POST /api/mcp/v1/calls/recordings/{uuid}/analysis`

Тело (JSON):
```json
{
  "transcript": "...",
  "ai_summary": "Клиент интересовался наличием коляски Chicco",
  "ai_result": "new_client",
  "ai_result_detail": "..."
}
```

Создаёт или обновляет `a1_call_analysis`. Переводит `ai_status → done`. Возвращает 200 с обновлённой записью.

При ошибке агента: `POST` с `{"error": "..."}` переводит в `ai_status = 'error'`.

### 4.4 `GET /api/mcp/v1/calls/recordings/{uuid}/analysis`

Возвращает готовый анализ для записи. 404 если анализа нет.

### 4.5 `GET /api/mcp/v1/calls/daily-summary/{date}`

Возвращает строку из `a1_daily_summaries` для даты. 404 если сводки нет.

### 4.6 `POST /api/mcp/v1/calls/daily-summary/{date}`

Тело (JSON):
```json
{
  "summary_text": "...",
  "key_themes": ["возврат", "бронирование", "наличие"]
}
```

Создаёт или заменяет (`upsert`) строку в `a1_daily_summaries`. Счётчики (`total_calls`, `incoming_calls` и т.д.) заполняются автоматически из `a1_cdr` на сервере — агент их не передаёт.

---

## 5. Workflow ИИ-агента

### Почасово (или чаще):
1. `GET /calls/pending-analysis?from=YYYY-MM-DD&to=YYYY-MM-DD` → получает список с `file_url`, статус меняется на `processing`
2. Скачивает каждый файл по `file_url`
3. Транскрибирует + анализирует
4. `POST /calls/recordings/{uuid}/analysis` → статус → `done`

### В конце дня (раз):
1. `GET /calls/recordings?from=YYYY-MM-DD&to=YYYY-MM-DD` → все записи за день
2. `GET /calls/recordings/{uuid}/analysis` для каждой → собирает `ai_summary`
3. Формирует дневную сводку
4. `POST /calls/daily-summary/YYYY-MM-DD`

---

## 6. Расписание (Kernel.php)

```php
$schedule->command('a1:fetch-recordings')->everyNinetyMinutes();
$schedule->command('a1:fetch-cdr')->hourly();
```

---

## 7. Что не входит в данный спек

- Интерфейс настройки ИИ-агента (промпты, модели)
- Push-уведомления при новых анализах
- Поиск по транскрипциям
- Минский район в CDR (деferred, аналогично GeoController)
