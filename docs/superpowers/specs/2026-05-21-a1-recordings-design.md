# A1 Записи звонков — дизайн

**Дата:** 2026-05-21
**Статус:** Согласован
**Ветка:** от `ats-calls`

---

## Цель

Скачивать аудиозаписи всех звонков с A1 ВАТС, хранить до 1 GB на сервере с автоматической ротацией старых файлов, предоставлять доступ к записям через MCP API tiktak.by.

---

## Контекст

- Существующая команда `a1:fetch-missed-calls` запускается каждые 10 минут, получает CDR из A1 и хранит пропущенные в `a1_missed_calls`.
- A1 предоставляет отдельный endpoint для записей: `GET /record/list` + `GET /record` (download).
- Rate limit A1: 1 запрос/сек на компанию.
- Диск: 32 GB свободно; квота для записей — 1 GB.
- Авторизация A1: токены в `storage/app/a1_tokens.json` (та же схема, что в `FetchA1MissedCalls`).

---

## Архитектура

### Компоненты

1. **Migration** — создаёт две новые таблицы.
2. **Artisan команда `a1:fetch-recordings`** — синхронизация по расписанию.
3. **Laravel Scheduler** — запускает команду каждый час в `:05`.
4. **`CallsController`** — два MCP API endpoint-а.
5. **Маршруты** — регистрация в существующей MCP-группе.

---

## База данных

### Таблица `a1_call_recordings`

| Поле | Тип | Примечание |
|------|-----|-----------|
| `id` | bigint UNSIGNED PK AI | |
| `record_name` | varchar(255) UNIQUE NOT NULL | Ключ дедупликации. A1 `recordName`: `1080/2026-05-11/177849215631776878` |
| `uuid` | varchar(100) NOT NULL, UNIQUE | A1 uuid звонка. Всегда заполнен для recordings. Используется в URL /file endpoint-а. |
| `call_date` | datetime NOT NULL, INDEX | Дата звонка из A1 |
| `caller_part` | varchar(30) NOT NULL DEFAULT '' | Номер звонящего |
| `callee_part` | varchar(30) NOT NULL DEFAULT '' | Номер принимающего |
| `call_duration` | smallint UNSIGNED NOT NULL DEFAULT 0 | Длительность, сек |
| `file_path` | varchar(500) NOT NULL | Относит. путь от `storage/app/`: `a1_recordings/2026-05/177849215631776878.mp3` |
| `file_size` | int UNSIGNED NOT NULL DEFAULT 0 | Байт |
| `downloaded_at` | datetime NOT NULL | |
| `created_at` | timestamp DEFAULT CURRENT_TIMESTAMP | |

### Таблица `a1_recordings_fetch_log`

| Поле | Тип |
|------|-----|
| `id` | bigint UNSIGNED PK AI |
| `fetched_at` | datetime NOT NULL, INDEX |
| `status` | enum('success','error') NOT NULL |
| `period_start` | int UNSIGNED NOT NULL DEFAULT 0 |
| `period_end` | int UNSIGNED NOT NULL DEFAULT 0 |
| `records_found` | smallint UNSIGNED NOT NULL DEFAULT 0 |
| `records_new` | smallint UNSIGNED NOT NULL DEFAULT 0 |
| `files_downloaded` | smallint UNSIGNED NOT NULL DEFAULT 0 |
| `files_deleted` | smallint UNSIGNED NOT NULL DEFAULT 0 |
| `bytes_downloaded` | int UNSIGNED NOT NULL DEFAULT 0 |
| `bytes_freed` | int UNSIGNED NOT NULL DEFAULT 0 |
| `error_message` | text NULL |

### Файловое хранилище

```
storage/app/a1_recordings/
    2026-05/
        177849215631776878.mp3
        177849215631776879.mp3
    2026-04/
        177849123456789012.mp3
```

- Папка = `YYYY-MM` из `callDate` записи.
- Имя файла = последний сегмент `recordName` (после последнего `/`) + расширение по Content-Type ответа A1 (`.mp3` по умолчанию).
- Файлы хранятся вне `public/` — доступны только через Laravel-контроллер.

---

## Artisan команда `a1:fetch-recordings`

**Файл:** `app/Console/Commands/FetchA1Recordings.php`
**Сигнатура:** `a1:fetch-recordings {--period=90 : Период выборки в минутах}`

### Алгоритм

```
1. Захватить Cache::lock('a1_api_mutex', 120), ждать до 5 сек.
   Если не удалось → завершить с Log::warning, exit(0).

2. Определить период:
   - Таблица a1_call_recordings пуста? → 30 дней назад ... сейчас
   - Иначе → 90 минут назад ... сейчас (перекрытие 30 мин для запоздавших записей)

3. GET /record/list?start&end&company_id
   sleep(1.1)

4. Для каждой записи в списке:
   a. record_name EXISTS в a1_call_recordings? → skip

   b. Управление квотой (до скачивания):
      - $used = SELECT SUM(file_size) FROM a1_call_recordings
      - Пока ($used + 5_242_880) > 1_073_741_824:  // +5 MB запас
            $oldest = SELECT * ORDER BY call_date ASC LIMIT 1
            unlink(storage_path('app/' . $oldest->file_path))
            DELETE FROM a1_call_recordings WHERE id = $oldest->id
            $used -= $oldest->file_size
            $files_deleted++, $bytes_freed += $oldest->file_size

   c. GET /record?filename=record_name&company_id  → bytes
      sleep(1.1)

   d. Разобрать путь:
      $parts = explode('/', $record['recordName'])
      $filename = end($parts)
      $folder   = 'a1_recordings/' . date('Y-m', strtotime($record['callDate']))
      Storage::disk('local')->makeDirectory($folder)
      Storage::disk('local')->put("$folder/$filename.mp3", $bytes)
      $file_path = "$folder/$filename.mp3"

   e. INSERT INTO a1_call_recordings (...)

   f. $files_downloaded++, $bytes_downloaded += strlen($bytes)

5. Освободить лок.

6. INSERT INTO a1_recordings_fetch_log (...)
```

### Изменение существующей команды `FetchA1MissedCalls`

`FetchA1MissedCalls` должна также захватывать `Cache::lock('a1_api_mutex', 120)` в начале `handle()` — иначе лок в новой команде не защищает от одновременных запросов к A1. Это небольшое изменение в существующем файле.

### Обработка ошибок

- A1 API вернул 401/403 → форсировать re-auth (та же логика что в `FetchA1MissedCalls`), повторить запрос.
- A1 API вернул 404 на скачивание конкретной записи → пропустить запись, продолжить.
- Любая другая ошибка → логировать, завершить команду, записать `status='error'` в лог.
- Токены хранятся в `storage/app/a1_tokens.json` (общий файл с `FetchA1MissedCalls`).

### Rate limit

Каждый HTTP-вызов к A1 предваряется `sleep(1.1)` — аналогично Python-клиенту. Лок `a1_api_mutex` предотвращает одновременный вызов из двух команд.

---

## Laravel Scheduler

**Файл:** `app/Console/Kernel.php`

```php
// Пропущенные звонки — каждые 10 минут (:00, :10, :20, :30, :40, :50)
$schedule->command('a1:fetch-missed-calls', ['--period' => 10])
    ->everyTenMinutes()
    ->withoutOverlapping();

// Записи звонков — каждый час в :05 (не пересекается с пропущенными)
$schedule->command('a1:fetch-recordings')
    ->hourlyAt(5)
    ->withoutOverlapping();
```

Первый запуск происходит автоматически: команда сама определяет пустую таблицу и забирает 30 дней.

---

## MCP API

### Контроллер

**Файл:** `app/Http/Controllers/Mcp/CallsController.php`

Extends `BaseController` (как все MCP контроллеры).

### Endpoint 1: список записей

```
GET /api/mcp/v1/calls/recordings
```

**Query parameters:**

| Param | Default | Описание |
|-------|---------|---------|
| `from` | 30 дней назад | Начало периода по `call_date` (YYYY-MM-DD) |
| `to` | сегодня | Конец периода |
| `caller` | — | Фильтр по `caller_part` (LIKE %value%) |
| `callee` | — | Фильтр по `callee_part` |
| `page` | 1 | Страница |
| `per_page` | 50 | Записей на странице (макс. 200) |

**Response:**
```json
{
  "query": { "from": "2026-05-01", "to": "2026-05-21", "page": 1, "per_page": 50 },
  "data": [
    {
      "uuid": "177849215631776878",
      "record_name": "1080/2026-05-11/177849215631776878",
      "call_date": "2026-05-11T14:23:00",
      "caller_part": "375296303532",
      "callee_part": "375291234567",
      "call_duration": 185,
      "file_size": 2949120,
      "downloaded_at": "2026-05-11T15:05:12"
    }
  ],
  "meta": {
    "total_rows": 312,
    "page": 1,
    "per_page": 50,
    "total_size_bytes": 876543210,
    "quota_bytes": 1073741824,
    "currency": "BYN",
    "data_freshness": "2026-05-21T10:05:00"
  }
}
```

### Endpoint 2: скачать файл

```
GET /api/mcp/v1/calls/recordings/{uuid}/file
```

- `{uuid}` — значение поля `uuid` из списка.
- Ищет запись в БД по `uuid`.
- Читает файл из `storage/app/{file_path}`.
- 404 если запись не найдена в БД или файл отсутствует на диске.
- Отдаёт бинарный поток:
  - `Content-Type: audio/mpeg`
  - `Content-Disposition: attachment; filename="{filename}.mp3"`
- **Не возвращает envelope** — только бинарный файл.

### Маршруты

В `routes/api.php`, внутри существующей MCP-группы (с middleware `mcp.json`, `mcp.token`, `mcp.geo`, `mcp.audit`, `throttle:60,1`):

```php
Route::get('calls/recordings', 'App\Http\Controllers\Mcp\CallsController@index');
Route::get('calls/recordings/{uuid}/file', 'App\Http\Controllers\Mcp\CallsController@streamFile');
```

---

## Что НЕ входит в скоуп

- Связывание записей с `a1_missed_calls` по номеру телефона (может быть добавлено позже).
- Транскрипция / speech-to-text.
- Воспроизведение в браузере (плеер в `bb/`).
- Удаление конкретных записей через API.

---

## Миграция

Один файл: `2026_05_21_000001_create_a1_call_recordings_tables.php`
Создаёт обе таблицы: `a1_call_recordings` и `a1_recordings_fetch_log`.
