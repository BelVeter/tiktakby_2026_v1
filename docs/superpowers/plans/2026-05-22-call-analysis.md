# Call Analysis Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Создать страницу аналитики звонков (`bb/a1_calls.php`) с CDR-статистикой по дням, ИИ-анализом через MCP API и аудиоплеером.

**Architecture:** Новые таблицы `a1_cdr` / `a1_call_analysis` / `a1_daily_summaries` / `a1_cdr_fetch_log` заполняются Laravel-командами по расписанию. MCP API предоставляет агенту 6 новых эндпоинтов для чтения очереди и записи результатов. Страница в `bb/` рендерит CDR за выбранный день, показывает ИИ-сводку и стримит аудио через отдельный web-маршрут с bb/-авторизацией.

**Tech Stack:** PHP 7.4+, Laravel 8.x, MariaDB 10.6, Bootstrap 4 (bb/), HTML5 Audio API.

**Spec:** `docs/superpowers/specs/2026-05-22-call-analysis-design.md`

---

## File Map

### New files
| Файл | Назначение |
|------|-----------|
| `database/migrations/2026_05_22_000001_create_a1_cdr_tables.php` | Таблицы `a1_cdr` и `a1_cdr_fetch_log` |
| `database/migrations/2026_05_22_000002_create_a1_call_analysis_tables.php` | Таблицы `a1_call_analysis` и `a1_daily_summaries` |
| `app/Console/Commands/FetchA1Cdr.php` | Артизан-команда: скачать CDR с A1 |
| `app/Http/Controllers/BbAudioController.php` | Стриминг аудио для bb/ (без MCP-токена) |
| `bb/a1_calls.php` | Страница анализа звонков |
| `tests/Feature/Mcp/CallsTest.php` | Тесты новых API эндпоинтов |

### Modified files
| Файл | Что меняется |
|------|-------------|
| `app/Console/Commands/FetchA1Recordings.php` | После insert создаёт запись `a1_call_analysis(pending)` |
| `app/Console/Kernel.php` | Добавляет расписание для `a1:fetch-cdr` |
| `app/Http/Controllers/Mcp/CallsController.php` | 6 новых методов |
| `routes/api.php` | 6 новых MCP-маршрутов |
| `routes/web.php` | Маршрут `/bb-internal/audio/{uuid}` |
| `bb/bb_nav.php` | Добавляет пункт "Звонки" с бейджем |
| `bb/bb_nav_badge.php` | Добавляет `calls_pending` в JSON-ответ |

---

## Task 1: Миграции — CDR-таблицы

**Files:**
- Create: `database/migrations/2026_05_22_000001_create_a1_cdr_tables.php`

- [ ] **Step 1: Создать файл миграции**

```php
<?php
// database/migrations/2026_05_22_000001_create_a1_cdr_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateA1CdrTables extends Migration
{
    public function up()
    {
        Schema::create('a1_cdr', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 100)->unique();
            $table->dateTime('call_date')->index();
            $table->enum('call_type', ['incoming', 'outgoing', 'missed'])->index();
            $table->string('caller_number', 30)->default('');
            $table->string('callee_number', 30)->default('');
            $table->unsignedSmallInteger('call_duration')->default(0);
            $table->string('recording_uuid', 100)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('a1_cdr_fetch_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fetched_at')->index();
            $table->enum('status', ['success', 'error']);
            $table->unsignedInteger('period_start')->default(0);
            $table->unsignedInteger('period_end')->default(0);
            $table->unsignedSmallInteger('records_found')->default(0);
            $table->unsignedSmallInteger('records_new')->default(0);
            $table->text('error_message')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('a1_cdr');
        Schema::dropIfExists('a1_cdr_fetch_log');
    }
}
```

- [ ] **Step 2: Применить миграцию**

```bash
docker-compose exec app php artisan migrate
```

Ожидаемый вывод: `Migrated: 2026_05_22_000001_create_a1_cdr_tables`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_22_000001_create_a1_cdr_tables.php
git commit -m "feat: add a1_cdr and a1_cdr_fetch_log tables"
```

---

## Task 2: Миграции — таблицы анализа

**Files:**
- Create: `database/migrations/2026_05_22_000002_create_a1_call_analysis_tables.php`

- [ ] **Step 1: Создать файл миграции**

```php
<?php
// database/migrations/2026_05_22_000002_create_a1_call_analysis_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateA1CallAnalysisTables extends Migration
{
    public function up()
    {
        Schema::create('a1_call_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('recording_uuid', 100)->unique();
            $table->longText('transcript')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('ai_result', 100)->nullable();
            $table->text('ai_result_detail')->nullable();
            $table->enum('ai_status', ['pending', 'processing', 'done', 'error'])
                  ->default('pending')->index();
            $table->text('ai_error')->nullable();
            $table->dateTime('ai_processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('a1_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->unique();
            $table->text('summary_text')->nullable();
            $table->unsignedSmallInteger('total_calls')->default(0);
            $table->unsignedSmallInteger('incoming_calls')->default(0);
            $table->unsignedSmallInteger('outgoing_calls')->default(0);
            $table->unsignedSmallInteger('missed_calls')->default(0);
            $table->unsignedSmallInteger('calls_analyzed')->default(0);
            $table->json('key_themes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down()
    {
        Schema::dropIfExists('a1_call_analysis');
        Schema::dropIfExists('a1_daily_summaries');
    }
}
```

- [ ] **Step 2: Применить миграцию**

```bash
docker-compose exec app php artisan migrate
```

Ожидаемый вывод: `Migrated: 2026_05_22_000002_create_a1_call_analysis_tables`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_22_000002_create_a1_call_analysis_tables.php
git commit -m "feat: add a1_call_analysis and a1_daily_summaries tables"
```

---

## Task 3: Обновить FetchA1Recordings — авто-создание анализа

**Files:**
- Modify: `app/Console/Commands/FetchA1Recordings.php`

После успешного `DB::table('a1_call_recordings')->insert(...)` нужно создать запись в `a1_call_analysis` со статусом `pending`.

- [ ] **Step 1: Найти место вставки в FetchA1Recordings.php**

Открыть `app/Console/Commands/FetchA1Recordings.php`. Найти строку:
```php
$filesDownloaded++;
$bytesDownloaded += $fileSize;
```

- [ ] **Step 2: Добавить вставку в a1_call_analysis сразу после DB::insert**

Найти блок вставки:
```php
            DB::table('a1_call_recordings')->insert([
                'record_name'   => $recordName,
                'uuid'          => $uuid,
                ...
                'downloaded_at' => date('Y-m-d H:i:s'),
            ]);

            $filesDownloaded++;
```

Заменить на:
```php
            DB::table('a1_call_recordings')->insert([
                'record_name'   => $recordName,
                'uuid'          => $uuid,
                'call_date'     => date('Y-m-d H:i:s', strtotime($callDate)),
                'caller_part'   => $this->extractNumber($rec['callerPart'] ?? ''),
                'callee_part'   => $this->extractNumber($rec['calleePart'] ?? ''),
                'call_duration' => (int) ($rec['callDuration'] ?? 0),
                'file_path'     => $filePath,
                'file_size'     => $fileSize,
                'downloaded_at' => date('Y-m-d H:i:s'),
            ]);

            // Create pending analysis record for AI agent
            DB::table('a1_call_analysis')->insertOrIgnore([
                'recording_uuid' => $uuid,
                'ai_status'      => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            // Link to CDR if already fetched
            DB::table('a1_cdr')
                ->where('uuid', $uuid)
                ->whereNull('recording_uuid')
                ->update(['recording_uuid' => $uuid]);

            $filesDownloaded++;
```

- [ ] **Step 3: Запустить существующие тесты**

```bash
docker-compose exec app php artisan test --filter=Calls
```

Ожидаемый вывод: тесты прошли (или нет тестов — тогда 0 failures).

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/FetchA1Recordings.php
git commit -m "feat: auto-create a1_call_analysis(pending) when recording downloaded"
```

---

## Task 4: Команда FetchA1Cdr

**Files:**
- Create: `app/Console/Commands/FetchA1Cdr.php`

CDR-эндпоинт A1: `GET /crm-api/open-api/v1/cdr` с параметрами `company_id`, `start`, `end`.
Поля ответа: `uuid`, `callTimestamp`, `callType`, `callerNumber`, `calleeNumber`, `callDuration`, `callStatus`.
Пропущенные определяются по `callStatus` IN: `NOT_ANSWERED_COMMON`, `CANCELLED_BY_CALLER`, `DENIED_DUE_TO_NOT_WORK_TIME`, `DENIED_DUE_TO_MAX_SESSION`.

- [ ] **Step 1: Создать файл команды**

```php
<?php
// app/Console/Commands/FetchA1Cdr.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchA1Cdr extends Command
{
    protected $signature   = 'a1:fetch-cdr {--period=90 : Период выборки в минутах}';
    protected $description = 'Скачивает CDR (историю звонков) с A1 ВАТС';

    private const TOKENS_FILE = 'a1_tokens.json';
    private const BASE_URL    = 'https://vats.a1.by/crm-api/open-api/v1';

    private const MISSED_STATUSES = [
        'NOT_ANSWERED_COMMON',
        'CANCELLED_BY_CALLER',
        'DENIED_DUE_TO_NOT_WORK_TIME',
        'DENIED_DUE_TO_MAX_SESSION',
    ];

    public function handle(): int
    {
        $lock = Cache::lock('a1_cdr_mutex', 120);
        if (!$lock->get()) {
            Log::warning('A1 CDR: не удалось захватить a1_cdr_mutex, пропускаем');
            return 0;
        }
        try {
            return $this->doHandle();
        } finally {
            $lock->release();
        }
    }

    private function doHandle(): int
    {
        $companyId = config('services.a1.company_id');
        $apiKey    = config('services.a1.api_key');

        if (!$companyId || !$apiKey) {
            $this->error('A1 CDR: не задан A1_COMPANY_ID или A1_API_KEY');
            return 1;
        }

        $end     = time();
        $isEmpty = !DB::table('a1_cdr')->exists();
        if ($isEmpty) {
            $start = $end - 30 * 86400;
        } else {
            $periodMinutes = (int) $this->option('period');
            $start = $end - ($periodMinutes * 60);
        }

        try {
            $token = $this->getAccessToken($companyId, $apiKey);
        } catch (\Exception $e) {
            Log::error('A1 CDR: ошибка авторизации — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, $e->getMessage());
            return 1;
        }

        try {
            $records = $this->fetchCdr($token, $companyId, $start, $end);

            if ($records === null) {
                $this->forgetTokens();
                $token   = $this->getAccessToken($companyId, $apiKey);
                $records = $this->fetchCdr($token, $companyId, $start, $end);
            }

            if ($records === null) {
                throw new \RuntimeException('CDR: токен отклонён даже после re-auth');
            }
        } catch (\Exception $e) {
            Log::error('A1 CDR: ошибка получения — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, $e->getMessage());
            return 1;
        }

        $recordsFound = count($records);
        $recordsNew   = 0;

        foreach ($records as $rec) {
            $uuid      = $rec['uuid'] ?? null;
            $timestamp = $rec['callTimestamp'] ?? null;

            if (!$uuid || !$timestamp) {
                continue;
            }

            if (DB::table('a1_cdr')->where('uuid', $uuid)->exists()) {
                continue;
            }

            $callType = $this->resolveCallType($rec);
            $duration = (int) ($rec['callDuration'] ?? 0);

            // Check if recording exists for this CDR entry
            $recordingExists = DB::table('a1_call_recordings')
                ->where('uuid', $uuid)
                ->exists();
            $recordingUuid = $recordingExists ? $uuid : null;

            DB::table('a1_cdr')->insert([
                'uuid'           => $uuid,
                'call_date'      => date('Y-m-d H:i:s', (int) $timestamp),
                'call_type'      => $callType,
                'caller_number'  => (string) ($rec['callerNumber'] ?? ''),
                'callee_number'  => (string) ($rec['calleeNumber'] ?? ''),
                'call_duration'  => $duration,
                'recording_uuid' => $recordingUuid,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            // If recording exists but analysis record doesn't — create it
            if ($recordingUuid) {
                DB::table('a1_call_analysis')->insertOrIgnore([
                    'recording_uuid' => $recordingUuid,
                    'ai_status'      => 'pending',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            $recordsNew++;
        }

        $this->logFetch('success', $start, $end, $recordsFound, $recordsNew, null);
        $this->info("A1 CDR: найдено {$recordsFound}, новых {$recordsNew}.");
        return 0;
    }

    private function resolveCallType(array $rec): string
    {
        $status = $rec['callStatus'] ?? '';
        if (in_array($status, self::MISSED_STATUSES, true)) {
            return 'missed';
        }
        $type = strtolower($rec['callType'] ?? '');
        if (str_contains($type, 'outgoing') || str_contains($type, 'out')) {
            return 'outgoing';
        }
        return 'incoming';
    }

    private function fetchCdr(string $token, string $companyId, int $start, int $end): ?array
    {
        usleep(1_100_000);
        $response = Http::withHeaders([
            'Authentication' => $token,
        ])->get(self::BASE_URL . '/cdr', [
            'company_id' => $companyId,
            'start'      => $start,
            'end'        => $end,
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            return null;
        }
        if (!$response->successful()) {
            throw new \RuntimeException('cdr HTTP ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json();
        if (is_array($data) && (isset($data[0]) || $data === [])) {
            return $data;
        }
        foreach (['data', 'items', 'records', 'cdr'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return is_array($data) ? $data : [];
    }

    private function logFetch(string $status, int $start, int $end, int $found, int $new, ?string $error): void
    {
        DB::table('a1_cdr_fetch_log')->insert([
            'fetched_at'    => date('Y-m-d H:i:s'),
            'status'        => $status,
            'period_start'  => $start,
            'period_end'    => $end,
            'records_found' => $found,
            'records_new'   => $new,
            'error_message' => $error,
        ]);
    }

    // ── Auth (копия из FetchA1Recordings) ──────────────────────────

    private function getAccessToken(string $companyId, string $apiKey): string
    {
        $tokens = $this->loadTokens();

        if (!empty($tokens['access_token']) && !empty($tokens['access_expires_at'])) {
            if ($tokens['access_expires_at'] > time() + 300) {
                return $tokens['access_token'];
            }
        }

        if (!empty($tokens['refresh_token']) && !empty($tokens['refresh_expires_at'])) {
            if ($tokens['refresh_expires_at'] > time() + 60) {
                try {
                    return $this->refreshToken($tokens['refresh_token']);
                } catch (\Exception $e) {
                    Log::warning('A1 CDR: refresh не сработал: ' . $e->getMessage());
                }
            }
        }

        return $this->authorize($companyId, $apiKey);
    }

    private function authorize(string $companyId, string $apiKey): string
    {
        $credential = base64_encode($companyId . ':' . $apiKey);
        $response = Http::withHeaders([
            'Authorization' => $credential,
        ])->get(self::BASE_URL . '/auth/tokens');

        if (!$response->successful()) {
            throw new \RuntimeException('Авторизация A1 провалилась: HTTP ' . $response->status());
        }

        $data = $response->json();
        $this->saveTokens($data);
        return $data['access_token'];
    }

    private function refreshToken(string $refreshToken): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $refreshToken,
        ])->put(self::BASE_URL . '/auth/tokens');

        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('refresh_token отклонён (HTTP ' . $response->status() . ')');
        }
        if (!$response->successful()) {
            throw new \RuntimeException('Refresh failed: HTTP ' . $response->status());
        }

        $data = $response->json();
        $this->saveTokens($data);
        return $data['access_token'];
    }

    private function tokensPath(): string
    {
        return storage_path('app/' . self::TOKENS_FILE);
    }

    private function loadTokens(): array
    {
        $path = $this->tokensPath();
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function forgetTokens(): void
    {
        @unlink($this->tokensPath());
    }

    private function saveTokens(array $data): void
    {
        file_put_contents(
            $this->tokensPath(),
            json_encode([
                'access_token'       => $data['access_token']  ?? '',
                'access_expires_at'  => time() + 86400,
                'refresh_token'      => $data['refresh_token'] ?? '',
                'refresh_expires_at' => time() + 604800,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
```

- [ ] **Step 2: Убедиться что команда видна артизану**

```bash
docker-compose exec app php artisan list | grep a1
```

Ожидаемый вывод: видны `a1:fetch-cdr`, `a1:fetch-missed-calls`, `a1:fetch-recordings`.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/FetchA1Cdr.php
git commit -m "feat: add a1:fetch-cdr command for CDR history"
```

---

## Task 5: Расписание в Kernel.php

**Files:**
- Modify: `app/Console/Kernel.php`

- [ ] **Step 1: Добавить расписание для a1:fetch-cdr**

В методе `schedule()` после блока `a1:fetch-recordings` добавить:

```php
        // CDR (история всех звонков) — каждый час в :15
        $schedule->command('a1:fetch-cdr', ['--period' => 90])
            ->hourlyAt(15)
            ->withoutOverlapping();
```

- [ ] **Step 2: Проверить что расписание добавлено**

```bash
docker-compose exec app php artisan schedule:list
```

Ожидаемый вывод: строка `a1:fetch-cdr` присутствует.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Kernel.php
git commit -m "feat: schedule a1:fetch-cdr hourly"
```

---

## Task 6: Тесты для новых MCP API эндпоинтов

**Files:**
- Create: `tests/Feature/Mcp/CallsTest.php`

Тесты пишутся ДО реализации контроллера (TDD).

- [ ] **Step 1: Создать тест-файл**

```php
<?php
// tests/Feature/Mcp/CallsTest.php

namespace Tests\Feature\Mcp;

use Illuminate\Support\Facades\DB;

class CallsTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('a1_cdr')->truncate();
        DB::table('a1_call_recordings')->truncate();
        DB::table('a1_call_analysis')->truncate();
        DB::table('a1_daily_summaries')->truncate();
    }

    // ── GET /calls/cdr ────────────────────────────────────────────

    public function test_calls_cdr_requires_token(): void
    {
        $this->assertRequiresToken('calls/cdr');
    }

    public function test_calls_cdr_returns_envelope(): void
    {
        DB::table('a1_cdr')->insert([
            'uuid'          => 'test-cdr-1',
            'call_date'     => '2026-05-21 10:00:00',
            'call_type'     => 'incoming',
            'caller_number' => '+375291111111',
            'callee_number' => '+375296303532',
            'call_duration' => 120,
            'recording_uuid' => null,
            'created_at'    => now(),
        ]);

        $response = $this->mcp('calls/cdr', ['from' => '2026-05-21', 'to' => '2026-05-21']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['query', 'data', 'meta']);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('incoming', $response->json('data.0.call_type'));
    }

    public function test_calls_cdr_filters_by_type(): void
    {
        DB::table('a1_cdr')->insert([
            ['uuid' => 'cdr-in',  'call_date' => '2026-05-21 09:00:00', 'call_type' => 'incoming',
             'caller_number' => '+375291111111', 'callee_number' => '+375296303532', 'call_duration' => 60, 'created_at' => now()],
            ['uuid' => 'cdr-out', 'call_date' => '2026-05-21 09:30:00', 'call_type' => 'outgoing',
             'caller_number' => '+375296303532', 'callee_number' => '+375291111111', 'call_duration' => 90, 'created_at' => now()],
            ['uuid' => 'cdr-mis', 'call_date' => '2026-05-21 10:00:00', 'call_type' => 'missed',
             'caller_number' => '+375441111111', 'callee_number' => '+375296303532', 'call_duration' => 0,  'created_at' => now()],
        ]);

        $r = $this->mcp('calls/cdr', ['from' => '2026-05-21', 'to' => '2026-05-21', 'call_type' => 'missed']);
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
        $this->assertEquals('missed', $r->json('data.0.call_type'));
    }

    // ── GET /calls/pending-analysis ───────────────────────────────

    public function test_pending_analysis_requires_token(): void
    {
        $this->assertRequiresToken('calls/pending-analysis');
    }

    public function test_pending_analysis_returns_pending_recordings(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid'          => 'rec-pending',
            'record_name'   => 'test/record',
            'call_date'     => '2026-05-21 10:00:00',
            'caller_part'   => '+375291111111',
            'callee_part'   => '+375296303532',
            'call_duration' => 120,
            'file_path'     => 'a1_recordings/2026-05/test.mp3',
            'file_size'     => 1024,
            'created_at'    => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-pending',
            'ai_status'      => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21']);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertArrayHasKey('file_url', $response->json('data.0'));
    }

    public function test_pending_analysis_sets_status_to_processing(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-set-processing', 'record_name' => 'test/record2',
            'call_date' => '2026-05-21 11:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 60,
            'file_path' => 'a1_recordings/2026-05/test2.mp3', 'file_size' => 512, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-set-processing',
            'ai_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21']);

        $status = DB::table('a1_call_analysis')
            ->where('recording_uuid', 'rec-set-processing')
            ->value('ai_status');
        $this->assertEquals('processing', $status);
    }

    public function test_pending_analysis_resets_stale_processing(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-stale', 'record_name' => 'test/record3',
            'call_date' => '2026-05-21 11:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 60,
            'file_path' => 'a1_recordings/2026-05/test3.mp3', 'file_size' => 512, 'created_at' => now(),
        ]);
        // Stale processing record (updated 3 hours ago)
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-stale',
            'ai_status'  => 'processing',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $response = $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21']);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // ── POST /calls/recordings/{uuid}/analysis ────────────────────

    public function test_submit_analysis_creates_record(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-submit', 'record_name' => 'test/submit',
            'call_date' => '2026-05-21 12:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 200,
            'file_path' => 'a1_recordings/2026-05/submit.mp3', 'file_size' => 2048, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-submit', 'ai_status' => 'processing',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->postMcp('calls/recordings/rec-submit/analysis', [
            'transcript'      => 'Клиент: Есть ли коляска? Менеджер: Да, есть.',
            'ai_summary'      => 'Клиент интересовался наличием коляски Chicco',
            'ai_result'       => 'info',
            'ai_result_detail'=> 'Уточнил наличие, не забронировал',
        ]);

        $response->assertStatus(200);

        $analysis = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-submit')->first();
        $this->assertEquals('done', $analysis->ai_status);
        $this->assertEquals('info', $analysis->ai_result);
        $this->assertNotNull($analysis->ai_processed_at);
    }

    public function test_submit_analysis_error_status(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-error', 'record_name' => 'test/error',
            'call_date' => '2026-05-21 13:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 300,
            'file_path' => 'a1_recordings/2026-05/error.mp3', 'file_size' => 3072, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-error', 'ai_status' => 'processing',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->postMcp('calls/recordings/rec-error/analysis', [
            'error' => 'Audio file corrupt',
        ]);

        $response->assertStatus(200);
        $status = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-error')->value('ai_status');
        $this->assertEquals('error', $status);
    }

    // ── GET /calls/recordings/{uuid}/analysis ─────────────────────

    public function test_get_analysis_returns_done_record(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-done', 'record_name' => 'test/done',
            'call_date' => '2026-05-21 14:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 150,
            'file_path' => 'a1_recordings/2026-05/done.mp3', 'file_size' => 1500, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-done',
            'transcript'     => 'Полный текст разговора',
            'ai_summary'     => 'Клиент бронировал велосипед',
            'ai_result'      => 'booking',
            'ai_status'      => 'done',
            'ai_processed_at'=> now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->mcp('calls/recordings/rec-done/analysis');
        $response->assertStatus(200);
        $this->assertEquals('booking', $response->json('data.ai_result'));
        $this->assertEquals('done', $response->json('data.ai_status'));
    }

    public function test_get_analysis_returns_404_for_unknown(): void
    {
        $response = $this->mcp('calls/recordings/nonexistent/analysis');
        $response->assertStatus(404);
    }

    // ── GET/POST /calls/daily-summary/{date} ──────────────────────

    public function test_daily_summary_get_returns_404_when_missing(): void
    {
        $response = $this->mcp('calls/daily-summary/2026-05-21');
        $response->assertStatus(404);
    }

    public function test_daily_summary_post_creates_summary(): void
    {
        DB::table('a1_cdr')->insert([
            ['uuid' => 's1', 'call_date' => '2026-05-21 09:00:00', 'call_type' => 'incoming',
             'caller_number' => '+375291111111', 'callee_number' => '+375296303532', 'call_duration' => 60, 'created_at' => now()],
            ['uuid' => 's2', 'call_date' => '2026-05-21 10:00:00', 'call_type' => 'missed',
             'caller_number' => '+375441111111', 'callee_number' => '+375296303532', 'call_duration' => 0,  'created_at' => now()],
        ]);

        $response = $this->postMcp('calls/daily-summary/2026-05-21', [
            'summary_text' => 'День прошёл спокойно. Основные запросы — велосипеды.',
            'key_themes'   => ['велосипеды', 'наличие'],
        ]);

        $response->assertStatus(200);
        $row = DB::table('a1_daily_summaries')->where('summary_date', '2026-05-21')->first();
        $this->assertNotNull($row);
        $this->assertEquals(2, $row->total_calls);
        $this->assertEquals(1, $row->incoming_calls);
        $this->assertEquals(1, $row->missed_calls);
    }

    public function test_daily_summary_get_returns_existing(): void
    {
        DB::table('a1_daily_summaries')->insert([
            'summary_date'   => '2026-05-20',
            'summary_text'   => 'Тихий день',
            'total_calls'    => 10,
            'incoming_calls' => 7,
            'outgoing_calls' => 2,
            'missed_calls'   => 1,
            'calls_analyzed' => 5,
            'key_themes'     => json_encode(['прокат', 'возврат']),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->mcp('calls/daily-summary/2026-05-20');
        $response->assertStatus(200);
        $this->assertEquals('Тихий день', $response->json('data.summary_text'));
    }
}
```

- [ ] **Step 2: Добавить метод postMcp в McpTestCase**

Открыть `tests/Feature/Mcp/McpTestCase.php` и добавить после метода `mcp()`:

```php
    protected function postMcp(string $path, array $body = []): TestResponse
    {
        $url = '/api/mcp/v1/' . ltrim($path, '/');
        return $this->postJson($url, $body, [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
    }
```

- [ ] **Step 3: Запустить тесты — убедиться что они падают (TDD)**

```bash
docker-compose exec app php artisan test tests/Feature/Mcp/CallsTest.php
```

Ожидаемый вывод: ошибки вида `Route [mcp.v1.calls.cdr] not defined` или 404 — это ОК, тесты ещё не реализованы.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Mcp/CallsTest.php tests/Feature/Mcp/McpTestCase.php
git commit -m "test: add failing tests for new calls API endpoints"
```

---

## Task 7: Новые MCP API эндпоинты

**Files:**
- Modify: `app/Http/Controllers/Mcp/CallsController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Добавить маршруты в routes/api.php**

Найти блок "A1 Call Recordings" и заменить на:

```php
        // A1 Calls: recordings (existing)
        Route::get('calls/recordings', [CallsController::class, 'index'])->name('calls.recordings');
        Route::get('calls/recordings/{uuid}/file', [CallsController::class, 'streamFile'])->name('calls.recordings.file');

        // A1 Calls: CDR and AI analysis (new)
        Route::get('calls/cdr', [CallsController::class, 'cdr'])->name('calls.cdr');
        Route::get('calls/pending-analysis', [CallsController::class, 'pendingAnalysis'])->name('calls.pending-analysis');
        Route::get('calls/recordings/{uuid}/analysis', [CallsController::class, 'getAnalysis'])->name('calls.recordings.analysis.get');
        Route::post('calls/recordings/{uuid}/analysis', [CallsController::class, 'submitAnalysis'])->name('calls.recordings.analysis.post');
        Route::get('calls/daily-summary/{date}', [CallsController::class, 'getDailySummary'])->name('calls.daily-summary.get');
        Route::post('calls/daily-summary/{date}', [CallsController::class, 'submitDailySummary'])->name('calls.daily-summary.post');
```

- [ ] **Step 2: Добавить новые методы в CallsController**

Открыть `app/Http/Controllers/Mcp/CallsController.php`. Добавить `use Carbon\Carbon;` в начало файла (в блок use) и 6 новых методов перед закрывающей `}`.

Метод `cdr()`:
```php
    /**
     * GET /api/mcp/v1/calls/cdr
     * Params: from, to, call_type (all|incoming|outgoing|missed), page, per_page
     */
    public function cdr(Request $request): JsonResponse
    {
        $from     = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to       = $request->get('to',   date('Y-m-d'));
        $callType = $request->get('call_type', 'all');
        $page     = max(1, (int) $request->get('page', 1));
        $perPage  = min(200, max(1, (int) $request->get('per_page', 100)));

        $query = DB::table('a1_cdr')
            ->whereBetween('call_date', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if ($callType !== 'all' && in_array($callType, ['incoming', 'outgoing', 'missed'], true)) {
            $query->where('call_type', $callType);
        }

        $total = $query->count();
        $rows  = (clone $query)
            ->orderBy('call_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['uuid', 'call_date', 'call_type', 'caller_number', 'callee_number', 'call_duration', 'recording_uuid']);

        return $this->envelope(
            ['from' => $from, 'to' => $to, 'call_type' => $callType],
            $rows->values()->all(),
            ['total_rows' => $total, 'page' => $page, 'per_page' => $perPage]
        );
    }
```

Метод `pendingAnalysis()`:
```php
    /**
     * GET /api/mcp/v1/calls/pending-analysis
     * Params: from, to, limit (max 50)
     * Resets stale 'processing' records (>2h) back to 'pending'.
     * Returns pending recordings and sets them to 'processing'.
     */
    public function pendingAnalysis(Request $request): JsonResponse
    {
        $from  = $request->get('from', date('Y-m-d', strtotime('-1 day')));
        $to    = $request->get('to',   date('Y-m-d'));
        $limit = min(50, max(1, (int) $request->get('limit', 20)));

        // Reset stale processing records (stuck > 2 hours)
        DB::table('a1_call_analysis')
            ->where('ai_status', 'processing')
            ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-2 hours')))
            ->update(['ai_status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')]);

        // Find pending recordings in date range
        $rows = DB::table('a1_call_analysis as ca')
            ->join('a1_call_recordings as r', 'r.uuid', '=', 'ca.recording_uuid')
            ->where('ca.ai_status', 'pending')
            ->whereBetween('r.call_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('r.call_date', 'asc')
            ->limit($limit)
            ->get(['r.uuid', 'r.call_date', 'r.caller_part', 'r.callee_part', 'r.call_duration', 'r.file_size']);

        if ($rows->isEmpty()) {
            return $this->envelope(['from' => $from, 'to' => $to], [], ['total_rows' => 0]);
        }

        $uuids = $rows->pluck('uuid')->all();

        // Mark as processing
        DB::table('a1_call_analysis')
            ->whereIn('recording_uuid', $uuids)
            ->update(['ai_status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')]);

        $baseUrl = config('app.url') . '/api/mcp/v1/calls/recordings/';
        $data = $rows->map(fn($r) => array_merge((array) $r, [
            'file_url' => $baseUrl . $r->uuid . '/file',
        ]))->values()->all();

        return $this->envelope(
            ['from' => $from, 'to' => $to],
            $data,
            ['total_rows' => count($data)]
        );
    }
```

Метод `submitAnalysis()`:
```php
    /**
     * POST /api/mcp/v1/calls/recordings/{uuid}/analysis
     * Body: {transcript, ai_summary, ai_result, ai_result_detail} | {error}
     */
    public function submitAnalysis(Request $request, string $uuid): JsonResponse
    {
        $analysis = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();

        if (!$analysis) {
            // Auto-create if recording exists
            $recording = DB::table('a1_call_recordings')->where('uuid', $uuid)->first();
            if (!$recording) {
                return response()->json(['error' => 'Recording not found'], 404);
            }
            DB::table('a1_call_analysis')->insert([
                'recording_uuid' => $uuid,
                'ai_status'      => 'processing',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $analysis = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();
        }

        if ($request->has('error')) {
            DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->update([
                'ai_status'  => 'error',
                'ai_error'   => $request->input('error'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->update([
                'transcript'      => $request->input('transcript'),
                'ai_summary'      => $request->input('ai_summary'),
                'ai_result'       => $request->input('ai_result'),
                'ai_result_detail'=> $request->input('ai_result_detail'),
                'ai_status'       => 'done',
                'ai_processed_at' => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        }

        $updated = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();
        return $this->envelope(['uuid' => $uuid], (array) $updated, []);
    }
```

Метод `getAnalysis()`:
```php
    /**
     * GET /api/mcp/v1/calls/recordings/{uuid}/analysis
     */
    public function getAnalysis(string $uuid): JsonResponse
    {
        $analysis = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();

        if (!$analysis) {
            return response()->json(['error' => 'Analysis not found'], 404);
        }

        return $this->envelope(['uuid' => $uuid], (array) $analysis, []);
    }
```

Метод `getDailySummary()`:
```php
    /**
     * GET /api/mcp/v1/calls/daily-summary/{date}
     */
    public function getDailySummary(string $date): JsonResponse
    {
        $row = DB::table('a1_daily_summaries')->where('summary_date', $date)->first();

        if (!$row) {
            return response()->json(['error' => 'Summary not found'], 404);
        }

        $data = (array) $row;
        $data['key_themes'] = json_decode($row->key_themes ?? '[]', true);

        return $this->envelope(['date' => $date], $data, []);
    }
```

Метод `submitDailySummary()`:
```php
    /**
     * POST /api/mcp/v1/calls/daily-summary/{date}
     * Body: {summary_text, key_themes[]}
     * Counts filled from a1_cdr automatically.
     */
    public function submitDailySummary(Request $request, string $date): JsonResponse
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['error' => 'Invalid date format, expected YYYY-MM-DD'], 422);
        }

        $fromDt = $date . ' 00:00:00';
        $toDt   = $date . ' 23:59:59';

        $counts = DB::table('a1_cdr')
            ->whereBetween('call_date', [$fromDt, $toDt])
            ->selectRaw("
                COUNT(*) as total,
                SUM(call_type = 'incoming') as incoming,
                SUM(call_type = 'outgoing') as outgoing,
                SUM(call_type = 'missed') as missed
            ")
            ->first();

        $analyzed = DB::table('a1_call_analysis as ca')
            ->join('a1_call_recordings as r', 'r.uuid', '=', 'ca.recording_uuid')
            ->whereBetween('r.call_date', [$fromDt, $toDt])
            ->where('ca.ai_status', 'done')
            ->count();

        $payload = [
            'summary_date'   => $date,
            'summary_text'   => $request->input('summary_text', ''),
            'total_calls'    => (int) ($counts->total    ?? 0),
            'incoming_calls' => (int) ($counts->incoming ?? 0),
            'outgoing_calls' => (int) ($counts->outgoing ?? 0),
            'missed_calls'   => (int) ($counts->missed   ?? 0),
            'calls_analyzed' => $analyzed,
            'key_themes'     => json_encode($request->input('key_themes', [])),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $existing = DB::table('a1_daily_summaries')->where('summary_date', $date)->exists();
        if ($existing) {
            DB::table('a1_daily_summaries')->where('summary_date', $date)->update($payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            DB::table('a1_daily_summaries')->insert($payload);
        }

        $row = DB::table('a1_daily_summaries')->where('summary_date', $date)->first();
        $data = (array) $row;
        $data['key_themes'] = json_decode($row->key_themes ?? '[]', true);

        return $this->envelope(['date' => $date], $data, []);
    }
```

- [ ] **Step 3: Запустить тесты — должны пройти**

```bash
docker-compose exec app php artisan test tests/Feature/Mcp/CallsTest.php
```

Ожидаемый вывод: все тесты PASS. Если падают — исправить.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Mcp/CallsController.php routes/api.php
git commit -m "feat: add CDR, pending-analysis, analysis and daily-summary API endpoints"
```

---

## Task 8: BbAudioController — аудио для bb/ без MCP-токена

**Files:**
- Create: `app/Http/Controllers/BbAudioController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Создать контроллер**

```php
<?php
// app/Http/Controllers/BbAudioController.php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BbAudioController extends Controller
{
    /**
     * GET /bb-internal/audio/{uuid}
     * Стримит аудиофайл с проверкой bb/-куки (tt_is_logged_in).
     */
    public function stream(string $uuid): BinaryFileResponse
    {
        if (!isset($_COOKIE['tt_is_logged_in'])) {
            abort(403, 'Unauthorized');
        }

        $recording = DB::table('a1_call_recordings')
            ->where('uuid', $uuid)
            ->first();

        if (!$recording) {
            abort(404, 'Recording not found');
        }

        $diskPath = storage_path('app/' . $recording->file_path);
        $allowed  = storage_path('app/a1_recordings') . DIRECTORY_SEPARATOR;
        $resolved = realpath($diskPath);

        if ($resolved === false || strpos($resolved, $allowed) !== 0) {
            abort(404, 'Recording not found');
        }

        if (!file_exists($diskPath)) {
            abort(404, 'Recording file not found on disk');
        }

        $parts    = explode('/', $recording->file_path);
        $filename = end($parts);

        return response()->download($diskPath, $filename, ['Content-Type' => 'audio/mpeg']);
    }
}
```

- [ ] **Step 2: Добавить маршрут в routes/web.php**

Найти конец файла `routes/web.php` и добавить перед закрывающей `});` или в конец файла:

```php
// bb/ audio streaming (no MCP token required — uses bb/ cookie auth)
Route::get('/bb-internal/audio/{uuid}', 'App\Http\Controllers\BbAudioController@stream')
    ->where('uuid', '[a-zA-Z0-9_-]+')
    ->name('bb.audio.stream');
```

- [ ] **Step 3: Проверить что маршрут добавлен**

```bash
docker-compose exec app php artisan route:list | grep bb-internal
```

Ожидаемый вывод: строка с `bb-internal/audio/{uuid}` и именем `bb.audio.stream`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/BbAudioController.php routes/web.php
git commit -m "feat: add bb-internal audio streaming route with bb/ cookie auth"
```

---

## Task 9: Страница bb/a1_calls.php

**Files:**
- Create: `bb/a1_calls.php`

- [ ] **Step 1: Создать файл страницы**

```php
<?php
// bb/a1_calls.php

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

\bb\Base::loginCheck();

$mysqli = \bb\Db::getInstance()->getConnection();

// ─── Параметры ────────────────────────────────────────────────────────────
$date     = trim($_GET['date'] ?? date('Y-m-d'));
$typeFilter = trim($_GET['type'] ?? 'all');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
if (!in_array($typeFilter, ['all', 'incoming', 'outgoing', 'missed'], true)) {
    $typeFilter = 'all';
}

$safeDate = $mysqli->real_escape_string($date);

// ─── Статистика за день ───────────────────────────────────────────────────
$statsRow = $mysqli->query("
    SELECT
        COUNT(*) AS total,
        SUM(call_type = 'incoming') AS incoming,
        SUM(call_type = 'outgoing') AS outgoing,
        SUM(call_type = 'missed')   AS missed
    FROM a1_cdr
    WHERE DATE(call_date) = '{$safeDate}'
")->fetch_assoc();

$total    = (int) ($statsRow['total']    ?? 0);
$incoming = (int) ($statsRow['incoming'] ?? 0);
$outgoing = (int) ($statsRow['outgoing'] ?? 0);
$missed   = (int) ($statsRow['missed']   ?? 0);

// ─── Дневная ИИ-сводка ────────────────────────────────────────────────────
$summaryRow = $mysqli->query("
    SELECT * FROM a1_daily_summaries WHERE summary_date = '{$safeDate}'
")->fetch_assoc();

// ─── Список звонков ───────────────────────────────────────────────────────
$typeWhere = $typeFilter !== 'all' ? " AND cdr.call_type = '{$mysqli->real_escape_string($typeFilter)}'" : '';

$calls = [];
$result = $mysqli->query("
    SELECT
        cdr.uuid,
        cdr.call_date,
        cdr.call_type,
        cdr.caller_number,
        cdr.callee_number,
        cdr.call_duration,
        cdr.recording_uuid,
        ca.ai_summary,
        ca.ai_result,
        ca.ai_status,
        ca.transcript
    FROM a1_cdr cdr
    LEFT JOIN a1_call_analysis ca ON ca.recording_uuid = cdr.recording_uuid
    WHERE DATE(cdr.call_date) = '{$safeDate}'
    {$typeWhere}
    ORDER BY cdr.call_date DESC
    LIMIT 500
");

while ($row = $result->fetch_assoc()) {
    // CRM lookup: find client by last 9 digits of caller_number
    $lookupNum = $row['call_type'] !== 'outgoing' ? $row['caller_number'] : $row['callee_number'];
    $digits = preg_replace('/\D/', '', $lookupNum);
    $last9  = substr($digits, -9);
    $clientName = '';
    if (strlen($last9) >= 7) {
        $safeNum = $mysqli->real_escape_string($last9);
        $cl = $mysqli->query("SELECT fio FROM clients WHERE REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(','') LIKE '%{$safeNum}' LIMIT 1");
        if ($cl && $clRow = $cl->fetch_assoc()) {
            $clientName = $clRow['fio'];
        }
    }
    $row['client_name'] = $clientName;
    $calls[] = $row;
}

// ─── Вспомогательные функции ──────────────────────────────────────────────
function formatDuration(int $secs): string {
    if ($secs === 0) return '—';
    return sprintf('%d:%02d', intdiv($secs, 60), $secs % 60);
}

function callTypeIcon(string $type): string {
    return match($type) {
        'incoming' => '<span title="Входящий" style="color:#28a745;font-weight:bold;">↓</span>',
        'outgoing' => '<span title="Исходящий" style="color:#007bff;font-weight:bold;">↑</span>',
        'missed'   => '<span title="Пропущенный" style="color:#dc3545;font-weight:bold;">✗</span>',
        default    => '?',
    };
}

function aiResultBadge(?string $result, ?string $status): string {
    if (!$result || $status !== 'done') {
        if ($status === 'processing') return '<span class="badge badge-secondary">обрабатывается</span>';
        if ($status === 'error')      return '<span class="badge badge-danger">ошибка</span>';
        return '<span class="text-muted small">—</span>';
    }
    $labels = [
        'new_client' => ['Новый клиент', 'success'],
        'booking'    => ['Бронирование', 'primary'],
        'complaint'  => ['Жалоба',       'danger'],
        'info'       => ['Инфо-запрос',  'info'],
        'other'      => ['Другое',       'secondary'],
    ];
    [$label, $color] = $labels[$result] ?? [$result, 'secondary'];
    return "<span class=\"badge badge-{$color}\">{$label}</span>";
}

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$today    = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анализ звонков — <?= htmlspecialchars($date) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/bb/bb_nav.css?v=5">
    <style>
        body { padding-top: 60px; background: #f8f9fa; }
        .calls-header { background: #fff; padding: 16px 20px; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .date-nav { display: flex; align-items: center; gap: 10px; }
        .date-nav a { color: #495057; text-decoration: none; font-size: 1.2rem; padding: 2px 8px; border: 1px solid #dee2e6; border-radius: 4px; }
        .date-nav a:hover { background: #e9ecef; }
        .stat-tiles { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-tile { flex: 1; min-width: 120px; background: #fff; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
        .stat-tile .num { font-size: 2rem; font-weight: 700; }
        .stat-tile .lbl { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; letter-spacing: .5px; }
        .stat-total   .num { color: #343a40; }
        .stat-in      .num { color: #28a745; }
        .stat-out     .num { color: #007bff; }
        .stat-missed  .num { color: #dc3545; }
        .summary-block { background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .summary-block .themes { margin-top: 8px; }
        .summary-block .themes .badge { margin-right: 4px; font-size: 0.85rem; }
        .filter-tabs .nav-link { cursor: pointer; }
        .calls-table { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .calls-table th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: .5px; color: #6c757d; background: #f8f9fa; border-top: none; }
        .calls-table td { vertical-align: middle; font-size: 0.9rem; }
        .transcript-modal pre { white-space: pre-wrap; font-size: 0.85rem; max-height: 60vh; overflow-y: auto; }
        .audio-player { width: 200px; height: 32px; }
    </style>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php'; ?>

<div class="container-fluid" style="max-width:1400px;">
    <!-- Шапка с навигацией по дням -->
    <div class="calls-header rounded mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="date-nav">
                <a href="?date=<?= $prevDate ?>&type=<?= $typeFilter ?>">←</a>
                <input type="date" id="date-picker" value="<?= $date ?>" max="<?= $today ?>"
                       class="form-control form-control-sm" style="width:160px;"
                       onchange="window.location='?date='+this.value+'&type=<?= $typeFilter ?>'">
                <?php if ($date < $today): ?>
                <a href="?date=<?= $nextDate ?>&type=<?= $typeFilter ?>">→</a>
                <?php else: ?>
                <a style="opacity:.3;cursor:default;">→</a>
                <?php endif; ?>
            </div>

            <!-- Фильтр по типу -->
            <ul class="nav nav-pills filter-tabs">
                <?php foreach (['all' => 'Все', 'incoming' => 'Входящие', 'outgoing' => 'Исходящие', 'missed' => 'Пропущенные'] as $val => $lbl): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $typeFilter === $val ? 'active' : '' ?>"
                       href="?date=<?= $date ?>&type=<?= $val ?>"><?= $lbl ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Статистика -->
    <div class="stat-tiles">
        <div class="stat-tile stat-total"><div class="num"><?= $total ?></div><div class="lbl">Всего</div></div>
        <div class="stat-tile stat-in"><div class="num"><?= $incoming ?></div><div class="lbl">Входящие</div></div>
        <div class="stat-tile stat-out"><div class="num"><?= $outgoing ?></div><div class="lbl">Исходящие</div></div>
        <div class="stat-tile stat-missed"><div class="num"><?= $missed ?></div><div class="lbl">Пропущенные</div></div>
    </div>

    <!-- ИИ-сводка -->
    <?php if ($summaryRow): ?>
    <div class="summary-block">
        <div class="d-flex align-items-center justify-content-between">
            <strong>ИИ-сводка за день</strong>
            <button class="btn btn-sm btn-link" onclick="toggleSummary()">скрыть/показать</button>
        </div>
        <div id="summary-body">
            <p class="mb-1 mt-2"><?= nl2br(htmlspecialchars($summaryRow['summary_text'] ?? '')) ?></p>
            <?php if (!empty($summaryRow['key_themes'])): ?>
            <div class="themes">
                <?php $themes = json_decode($summaryRow['key_themes'], true) ?: []; ?>
                <?php foreach ($themes as $theme): ?>
                <span class="badge badge-light border"><?= htmlspecialchars($theme) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-light border mb-3" style="font-size:.9rem;">
        ИИ-сводка за <?= htmlspecialchars($date) ?> ещё не готова.
    </div>
    <?php endif; ?>

    <!-- Таблица звонков -->
    <div class="calls-table mb-4">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Время</th>
                    <th>Тип</th>
                    <th>Номер</th>
                    <th>Длит.</th>
                    <th>Краткое описание</th>
                    <th>Результат ИИ</th>
                    <th>Транскр.</th>
                    <th>Запись</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($calls)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Звонков за этот день нет</td></tr>
            <?php endif; ?>
            <?php foreach ($calls as $call): ?>
            <tr>
                <td><?= date('H:i', strtotime($call['call_date'])) ?></td>
                <td><?= callTypeIcon($call['call_type']) ?></td>
                <td>
                    <div><?= htmlspecialchars($call['call_type'] !== 'outgoing' ? $call['caller_number'] : $call['callee_number']) ?></div>
                    <?php if ($call['client_name']): ?>
                    <small class="text-muted"><?= htmlspecialchars($call['client_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= formatDuration((int) $call['call_duration']) ?></td>
                <td>
                    <?php if ($call['ai_summary'] && $call['ai_status'] === 'done'): ?>
                        <span title="<?= htmlspecialchars($call['ai_summary']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($call['ai_summary'], 0, 60, '…')) ?>
                        </span>
                    <?php elseif ($call['recording_uuid'] && $call['ai_status'] === 'processing'): ?>
                        <span class="text-muted small">обрабатывается…</span>
                    <?php elseif ($call['recording_uuid']): ?>
                        <span class="text-muted small">ожидает обработки</span>
                    <?php else: ?>
                        <span class="text-muted small">нет записи</span>
                    <?php endif; ?>
                </td>
                <td><?= aiResultBadge($call['ai_result'], $call['ai_status']) ?></td>
                <td>
                    <?php if ($call['transcript'] && $call['ai_status'] === 'done'): ?>
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="showTranscript(<?= htmlspecialchars(json_encode($call['transcript'])) ?>)">T</button>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($call['recording_uuid']): ?>
                    <audio class="audio-player" controls preload="none"
                           src="/bb-internal/audio/<?= htmlspecialchars($call['recording_uuid']) ?>"></audio>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модалка транскрипции -->
<div class="modal fade" id="transcriptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Транскрипция разговора</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <pre id="transcriptContent" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script>
function showTranscript(text) {
    document.getElementById('transcriptContent').textContent = text;
    $('#transcriptModal').modal('show');
}
function toggleSummary() {
    var el = document.getElementById('summary-body');
    if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
</body>
</html>
```

- [ ] **Step 2: Открыть страницу в браузере**

Перейти на `http://localhost/bb/a1_calls.php` (войдя под bb/-аккаунтом).
Проверить: навигационные стрелки работают, фильтр по типу работает, таблица рендерится без ошибок PHP.

- [ ] **Step 3: Commit**

```bash
git add bb/a1_calls.php
git commit -m "feat: add bb/a1_calls.php — call analysis page"
```

---

## Task 10: Бейдж в навигации bb/

**Files:**
- Modify: `bb/bb_nav.php`
- Modify: `bb/bb_nav_badge.php`

- [ ] **Step 1: Добавить пункт "Звонки" в bb_nav.php**

Найти массив `$_bb_nav_items`. После строки с `a1_missed_calls` (или перед Архивом) добавить:

```php
    ['label' => 'Звонки', 'href' => '/bb/a1_calls.php', 'icon' => '<svg class="bb-icon-nav__home-icon" viewBox="0 0 24 24" fill="none" stroke="#3a4a5c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8a19.79 19.79 0 01-3.07-8.64A2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>', 'page' => 'a1_calls.php', 'badge' => 'calls'],
```

Найти в блоке рендеринга бейджей (где `id="bb-nav-badge-<?= $item['badge'] ?>"`), убедиться что шаблон уже покрывает `calls`. Бейдж `bb-nav-badge-calls` будет заполнен через AJAX.

Найти JS-блок `fetch('/bb/bb_nav_badge.php')` и добавить обработку после последнего бейджа:

```javascript
                    var badgeCalls = document.getElementById('bb-nav-badge-calls');
                    if (badgeCalls) {
                        if (data.calls_pending > 0) {
                            badgeCalls.textContent = data.calls_pending;
                            badgeCalls.classList.add('bb-icon-nav__badge--visible');
                        } else {
                            badgeCalls.classList.remove('bb-icon-nav__badge--visible');
                        }
                    }
```

- [ ] **Step 2: Добавить calls_pending в bb_nav_badge.php**

Открыть `bb/bb_nav_badge.php`. После последнего SQL-запроса добавить:

```php
// Count pending AI analysis (last 7 days, excludes errors)
$query_calls = "
    SELECT COUNT(*) as cnt
    FROM a1_call_analysis ca
    JOIN a1_call_recordings r ON r.uuid = ca.recording_uuid
    WHERE ca.ai_status = 'pending'
      AND r.call_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
";
$result_calls = $mysqli->query($query_calls);
$calls_pending = 0;
if ($result_calls && $row = $result_calls->fetch_assoc()) {
    $calls_pending = (int) $row['cnt'];
}
```

И в итоговый `json_encode()` добавить `'calls_pending' => $calls_pending`:

```php
echo json_encode([
    'count_bron'         => $count_bron,
    'count_zayavk_new'   => $count_zayavk_new,
    'count_zayavk_avail' => $count_zayavk_avail,
    'calls_pending'      => $calls_pending,
]);
```

- [ ] **Step 3: Проверить бейдж**

Открыть DevTools → Network → найти запрос к `/bb/bb_nav_badge.php`. Убедиться что JSON содержит ключ `calls_pending`.

- [ ] **Step 4: Commit**

```bash
git add bb/bb_nav.php bb/bb_nav_badge.php
git commit -m "feat: add Calls badge to bb/ navigation"
```

---

## Task 11: CSV-экспорт и финальная проверка

**Files:**
- Modify: `bb/a1_calls.php`

- [ ] **Step 1: Добавить кнопку и обработчик экспорта**

В шапку страницы (`.calls-header`) добавить кнопку справа:

```php
<a href="?date=<?= $date ?>&type=<?= $typeFilter ?>&export=csv"
   class="btn btn-sm btn-outline-secondary">↓ CSV</a>
```

В начало файла (перед HTML) добавить обработчик:

```php
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="calls_' . $date . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['Время', 'Тип', 'Номер', 'Длительность', 'Краткое описание', 'Результат ИИ'], ';');
    foreach ($calls as $call) {
        fputcsv($out, [
            date('H:i', strtotime($call['call_date'])),
            $call['call_type'],
            $call['call_type'] !== 'outgoing' ? $call['caller_number'] : $call['callee_number'],
            formatDuration((int) $call['call_duration']),
            $call['ai_summary'] ?? '',
            $call['ai_result']  ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}
```

Убедиться что этот блок стоит ПОСЛЕ вычисления `$calls`, но ПЕРЕД любым HTML-выводом.

- [ ] **Step 2: Запустить все тесты проекта**

```bash
docker-compose exec app php artisan test
```

Ожидаемый вывод: все тесты PASS, никаких регрессий.

- [ ] **Step 3: Финальный commit**

```bash
git add bb/a1_calls.php
git commit -m "feat: add CSV export to calls analysis page"
```

---

## Self-review checklist

- [x] **Spec coverage:**
  - DB: a1_cdr ✓, a1_call_analysis ✓, a1_daily_summaries ✓, a1_cdr_fetch_log ✓
  - FetchA1Cdr command ✓
  - FetchA1Recordings auto-creates analysis record ✓
  - Kernel scheduling ✓
  - MCP API: cdr ✓, pending-analysis ✓, submit-analysis ✓, get-analysis ✓, get-summary ✓, post-summary ✓
  - Stale processing reset (2h) ✓
  - BbAudioController ✓
  - bb/a1_calls.php с датой, фильтром, статистикой, сводкой, таблицей, аудио ✓
  - CRM lookup по номеру ✓
  - bb_nav badge ✓
  - CSV export ✓

- [x] **No placeholders:** все шаги содержат реальный код

- [x] **Type consistency:** `recording_uuid` использован везде одинаково как varchar(100); методы контроллера совпадают с именами маршрутов
