# Логирование поисковых запросов пользователей

**Дата:** 2026-08-13
**Статус:** согласовано, готово к планированию реализации

## Задача

Поисковая строка сайта (`/{lang}/search`) ничего не сохраняет: введённый текст сразу уходит
в `MATCH() AGAINST()` по `rent_model_web` и нигде не пишется — ни в БД, ни в лог-файл (см.
[docs/mcp_server.md](../../mcp_server.md) не затрагивается; проверено вручную:
`SearchController::search()`, `bb\classes\ModelWeb::getModelIdsFullTextSearch()`). Единственный
след — URL `?search=...` в GA4/Яндекс.Метрике как обычный pageview.

Владелец хочет анализировать поисковые запросы в будущем (какие товары ищут, чего не хватает
в каталоге) и видеть повторные запросы с одного посетителя. Нужна собственная таблица
в MySQL: время, IP, текст запроса.

## Что есть сейчас

Прямого аналога нет, но в проекте уже есть паттерн request-логирования для MCP API:

| Место | Роль |
|-------|------|
| [database/migrations/2026_05_06_000001_create_mcp_api_log_table.php](../../../database/migrations/2026_05_06_000001_create_mcp_api_log_table.php) | Таблица `mcp_api_log`: `created_at` (indexed), `ip varchar(45)`, метод/путь, JSON-параметры, статус, `response_ms`, `user_agent` |
| [app/Http/Middleware/McpAuditLogMiddleware.php](../../../app/Http/Middleware/McpAuditLogMiddleware.php) | Пишет строку на каждый запрос; чистит потенциальные ПД-поля из query params; `try/catch` вокруг `insert()`, чтобы сбой лога не ронял ответ |

Этот дизайн повторяет тот же стиль (схема, обработка ошибок), но без отдельного middleware —
у поиска ровно одна точка входа, и она отличается от MCP-группы роутов.

`routes/web.php`:
```php
Route::get('/{lang}/search', 'App\Http\Controllers\SearchController@search')->name('search');
Route::get('/{lang}/producer', 'App\Http\Controllers\SearchController@producerFilter')->name('filter.producer');
Route::get('/{lang}/filter', 'App\Http\Controllers\SearchController@ageFilter')->name('filter.age');
```
Все три — методы одного `SearchController`, но `producerFilter`/`ageFilter` не принимают
свободный текст (только `producer`/`age_from`/`age_to`), поэтому в объём логирования не входят.

`SearchController::search()` ([app/Http/Controllers/SearchController.php:32-53](../../../app/Http/Controllers/SearchController.php#L32-L53)):
```php
public function search($lang, Request $req) {
    $text = trim($req->input('search'));
    ...
    $modelIdArray = ModelWeb::getModelIdsFullTextSearch($text);
    $page = max(1, (int) $req->input('page', 1));
    $total = $this->buildPageModels($p, $modelIdArray, $page);   // $total = число найденных моделей
    ...
}
```
`$total` уже содержит количество результатов — можно взять готовым, без лишнего запроса к БД.

## Принятые решения

| Развилка | Решение |
|----------|---------|
| Точка записи | Прямо в `SearchController::search()`, без отдельного middleware (см. "Варианты, где логировать" ниже) |
| IP | Полный, `$request->ip()` — как в `mcp_api_log` |
| Retention | Без автоочистки; владелец чистит вручную при необходимости |
| Что логировать | Только непустой `trim($text)`; известные боты по User-Agent отфильтрованы |
| Доп. поле | `results_count` — почти бесплатно (уже посчитан в `$total`), даёт самый ценный будущий срез: запросы с 0 результатов |

### Варианты, где логировать (рассмотрены)

| Вариант | Решение |
|---|---|
| **Инлайн в `SearchController::search()`** | **Выбран.** Один call site, ~10 строк, не плодит класс ради одного места вызова |
| Отдельный middleware на роут `/search` (как `McpAuditLogMiddleware`) | Отклонён: пришлось бы вешать middleware точечно на один named route, а не на группу — сложнее, чем инлайн, без выигрыша |
| Laravel event + listener | Отклонён как overengineering для единственной точки записи |

## Модель данных

Новая миграция `database/migrations/2026_08_13_xxxxxx_create_search_log_table.php`:

```php
Schema::create('search_log', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->dateTime('created_at')->index();
    $table->string('ip', 45);
    $table->string('query', 255);
    $table->smallInteger('results_count')->unsigned()->nullable();
    $table->string('user_agent', 255)->nullable();
});

Schema::table('search_log', function (Blueprint $table) {
    $table->index(['ip', 'created_at']);   // разрез "повторные запросы с одного IP"
});
```

Идемпотентно (`if (Schema::hasTable('search_log')) return;` в `up()`), `down()` — `dropIfExists`,
по тому же паттерну, что `create_mcp_api_log_table.php`.

## Точка записи

В `SearchController::search()`, после вычисления `$total`, до `return view(...)`:

```php
if ($text !== '' && !self::isBotUserAgent($req->userAgent())) {
    try {
        DB::table('search_log')->insert([
            'created_at'     => now(),
            'ip'             => $req->ip(),
            'query'          => substr($text, 0, 255),
            'results_count'  => $total,
            'user_agent'     => substr($req->userAgent() ?? '', 0, 255),
        ]);
    } catch (\Exception $e) {
        \Log::error('SearchLog failed: ' . $e->getMessage());
    }
}
```

`isBotUserAgent()` — приватный статический метод/константа со списком подстрок
(`googlebot`, `bingbot`, `yandex`, `ahrefsbot`, `semrushbot`, `mj12bot`, `dotbot`,
`petalbot`, `bytespider`, `mail.ru_bot`), регистронезависимая проверка `str_contains`,
по аналогии с `PD_PARAMS` в `McpAuditLogMiddleware`. Список — не исчерпывающий фильтр
приватности (боты могут его обходить), а просто снижение шума в аналитике.

Сбой записи лога никогда не должен ломать выдачу результатов поиска — `try/catch`,
как в `McpAuditLogMiddleware`.

## Тестирование

- **Feature** `tests/Feature/SearchLogTest.php`:
  - непустой поисковый запрос от обычного UA → появляется строка в `search_log` с верными
    `ip`/`query`/`results_count`;
  - пустой запрос (`?search=`) → строка не пишется;
  - запрос с UA из списка ботов → строка не пишется;
  - `results_count` соответствует числу найденных моделей (0 для запроса без совпадений);
  - запрос длиннее 255 символов не роняет insert (обрезается).
- Ручная проверка: `filter.producer`/`filter.age` не создают записей в `search_log`
  (они не проходят через изменённый метод).

## Не входит в объём

- UI для просмотра/агрегации логов (ни в `bb/`, ни в MCP API) — анализ через прямые SQL-запросы
  к `search_log`, как и остальная ad-hoc аналитика владельца.
- Автоочистка/retention policy — осознанно не делаем сейчас.
- Изменение состава ботов из статического списка на внешний user-agent-парсер/пакет.
- Логирование `filter.producer`/`filter.age` (не свободный текстовый ввод, вне задачи).

## Документация к обновлению

- Ничего во внешних доках/CLAUDE.md — таблица `search_log` не входит в MCP API и не меняет
  публичные контракты; при желании завести туда read-эндпоинт — отдельная задача.
