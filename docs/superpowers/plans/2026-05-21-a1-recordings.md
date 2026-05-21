# A1 Recordings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Download and store A1 VATS call recordings (up to 1 GB quota with auto-rotation), exposing them via two MCP API endpoints — a filtered list and a binary file stream.

**Architecture:** New Artisan command `a1:fetch-recordings` syncs recordings hourly using the same token infrastructure as `FetchA1MissedCalls`, with a shared `Cache::lock('a1_api_mutex')` preventing concurrent A1 API calls. Files are stored in `storage/app/a1_recordings/YYYY-MM/` and served only through a new `CallsController` (never public). Quota enforcement deletes the oldest recordings by `call_date` before each download.

**Tech Stack:** Laravel 8, PHP 7.4, MariaDB 10.6, Laravel Filesystem (`Storage::disk('local')`), `Cache::lock` for mutex, `Illuminate\Http\Response` for binary streaming.

---

## File Map

| Action   | File                                                                 | Purpose                                                    |
|----------|----------------------------------------------------------------------|------------------------------------------------------------|
| Create   | `database/migrations/2026_05_21_000001_create_a1_call_recordings_tables.php` | Tables `a1_call_recordings` + `a1_recordings_fetch_log`    |
| Create   | `app/Console/Commands/FetchA1Recordings.php`                        | Artisan command: list, download, quota rotation, log       |
| Modify   | `app/Console/Commands/FetchA1MissedCalls.php`                       | Add `Cache::lock('a1_api_mutex')` at start of `handle()`   |
| Modify   | `app/Console/Kernel.php`                                            | Schedule `a1:fetch-recordings` hourly at `:05`             |
| Create   | `app/Http/Controllers/Mcp/CallsController.php`                     | `index()` list + `streamFile()` binary response            |
| Modify   | `routes/api.php`                                                     | Register two new routes in MCP group                       |

---

## Task 1: Migration — create two tables

**Files:**
- Create: `database/migrations/2026_05_21_000001_create_a1_call_recordings_tables.php`

- [ ] **Step 1.1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateA1CallRecordingsTables extends Migration
{
    public function up()
    {
        Schema::create('a1_call_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('record_name', 255)->unique();
            $table->string('uuid', 100)->unique();
            $table->dateTime('call_date')->index();
            $table->string('caller_part', 30)->default('');
            $table->string('callee_part', 30)->default('');
            $table->unsignedSmallInteger('call_duration')->default(0);
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size')->default(0);
            $table->dateTime('downloaded_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('a1_recordings_fetch_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fetched_at')->index();
            $table->enum('status', ['success', 'error']);
            $table->unsignedInteger('period_start')->default(0);
            $table->unsignedInteger('period_end')->default(0);
            $table->unsignedSmallInteger('records_found')->default(0);
            $table->unsignedSmallInteger('records_new')->default(0);
            $table->unsignedSmallInteger('files_downloaded')->default(0);
            $table->unsignedSmallInteger('files_deleted')->default(0);
            $table->unsignedInteger('bytes_downloaded')->default(0);
            $table->unsignedInteger('bytes_freed')->default(0);
            $table->text('error_message')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('a1_call_recordings');
        Schema::dropIfExists('a1_recordings_fetch_log');
    }
}
```

- [ ] **Step 1.2: Run migration in Docker**

```bash
docker-compose exec app php artisan migrate
```

Expected output contains:
```
Migrating: 2026_05_21_000001_create_a1_call_recordings_tables
Migrated:  2026_05_21_000001_create_a1_call_recordings_tables
```

- [ ] **Step 1.3: Verify tables exist**

```bash
docker-compose exec app php artisan tinker --execute="DB::select('SHOW TABLES LIKE \"a1_%\"');"
```

Expected: shows `a1_call_recordings` and `a1_recordings_fetch_log` in addition to existing `a1_missed_calls` and `a1_api_fetch_log`.

- [ ] **Step 1.4: Commit**

```bash
git add database/migrations/2026_05_21_000001_create_a1_call_recordings_tables.php
git commit -m "feat: migration for a1_call_recordings and a1_recordings_fetch_log tables"
```

---

## Task 2: Add mutex to FetchA1MissedCalls

**Files:**
- Modify: `app/Console/Commands/FetchA1MissedCalls.php:33` (top of `handle()`)

The new `FetchA1Recordings` command will use `Cache::lock('a1_api_mutex', 120)` to serialize A1 API calls. Without the same lock in `FetchA1MissedCalls`, both commands could hit A1 simultaneously if the scheduler fires near the same time.

- [ ] **Step 2.1: Add use statement and lock to FetchA1MissedCalls**

In `app/Console/Commands/FetchA1MissedCalls.php`, add the `Cache` import at the top and wrap `handle()`:

Add to use-block (after `use Illuminate\Support\Facades\DB;`):
```php
use Illuminate\Support\Facades\Cache;
```

Replace the start of `handle()` — insert lock acquisition as the very first lines before the `$companyId` check:

```php
public function handle(): int
{
    $lock = Cache::lock('a1_api_mutex', 120);
    if (!$lock->get()) {
        Log::warning('A1 MissedCalls: не удалось захватить a1_api_mutex, пропускаем запуск');
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
    // ... (весь остальной код handle() переносится сюда без изменений)
```

> **Note:** The existing `handle()` body (lines 33–108 in the current file) moves verbatim into `doHandle()`. Only the outer `handle()` method changes — it acquires the lock, delegates to `doHandle()`, and releases in `finally`.

- [ ] **Step 2.2: Verify syntax is valid**

```bash
docker-compose exec app php artisan list | grep a1
```

Expected: both `a1:fetch-missed-calls` and any other registered a1 commands appear without PHP errors.

- [ ] **Step 2.3: Commit**

```bash
git add app/Console/Commands/FetchA1MissedCalls.php
git commit -m "feat: add a1_api_mutex lock to FetchA1MissedCalls to prevent concurrent A1 API calls"
```

---

## Task 3: Artisan command FetchA1Recordings

**Files:**
- Create: `app/Console/Commands/FetchA1Recordings.php`

This is the main command. It reuses the token management pattern from `FetchA1MissedCalls` (same `storage/app/a1_tokens.json` file, same `getAccessToken` / `authorize` / `refreshToken` / `saveTokens` helpers).

- [ ] **Step 3.1: Create the command file**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FetchA1Recordings extends Command
{
    protected $signature   = 'a1:fetch-recordings {--period=90 : Период выборки в минутах}';
    protected $description = 'Скачивает записи звонков с A1 ВАТС и сохраняет в storage/app/a1_recordings/';

    private const TOKENS_FILE  = 'a1_tokens.json';
    private const BASE_URL     = 'https://vats.a1.by/crm-api/open-api/v1';
    private const QUOTA_BYTES  = 1_073_741_824; // 1 GB
    private const BUFFER_BYTES = 5_242_880;     // 5 MB buffer before download

    public function handle(): int
    {
        $lock = Cache::lock('a1_api_mutex', 120);
        if (!$lock->get()) {
            Log::warning('A1 Recordings: не удалось захватить a1_api_mutex, пропускаем запуск');
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
            $this->error('A1: не задан A1_COMPANY_ID или A1_API_KEY в .env');
            return 1;
        }

        // Determine period
        $end   = time();
        $isEmpty = !DB::table('a1_call_recordings')->exists();
        if ($isEmpty) {
            $start = $end - 30 * 86400; // first run: last 30 days
        } else {
            $periodMinutes = (int) $this->option('period');
            $start = $end - ($periodMinutes * 60);
        }

        // Auth
        try {
            $token = $this->getAccessToken($companyId, $apiKey);
        } catch (\Exception $e) {
            Log::error('A1 Recordings: ошибка авторизации — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, 0, 0, 0, 0, $e->getMessage());
            return 1;
        }

        // Fetch list
        try {
            $records = $this->fetchRecordingsList($token, $companyId, $start, $end);

            if ($records === null) {
                Log::warning('A1 Recordings: токен отклонён (401/403), форсируем re-auth');
                $this->forgetTokens();
                $token   = $this->getAccessToken($companyId, $apiKey);
                $records = $this->fetchRecordingsList($token, $companyId, $start, $end);
            }

            if ($records === null) {
                throw new \RuntimeException('record/list: токен отклонён даже после re-auth');
            }
        } catch (\Exception $e) {
            Log::error('A1 Recordings: ошибка получения списка — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, 0, 0, 0, 0, $e->getMessage());
            return 1;
        }

        $recordsFound = count($records);
        $this->line("A1 Recordings: найдено записей: {$recordsFound}");

        $recordsNew      = 0;
        $filesDownloaded = 0;
        $filesDeleted    = 0;
        $bytesDownloaded = 0;
        $bytesFreed      = 0;

        foreach ($records as $rec) {
            $recordName = $rec['recordName'] ?? null;
            $uuid       = $rec['uuid']       ?? null;

            if (!$recordName || !$uuid) {
                continue;
            }

            // Deduplication
            if (DB::table('a1_call_recordings')->where('record_name', $recordName)->exists()) {
                continue;
            }

            $recordsNew++;

            // Quota enforcement before download
            [$filesDeleted, $bytesFreed] = $this->enforceQuota($filesDeleted, $bytesFreed);

            // Download
            try {
                $bytes = $this->downloadRecording($token, $companyId, $recordName);
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), '404')) {
                    $this->line("  skip (404): {$recordName}");
                    continue;
                }
                // Re-auth on 401/403
                if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), '403')) {
                    $this->forgetTokens();
                    try {
                        $token = $this->getAccessToken($companyId, $apiKey);
                        $bytes = $this->downloadRecording($token, $companyId, $recordName);
                    } catch (\Exception $e2) {
                        Log::error('A1 Recordings: скачивание не удалось после re-auth: ' . $e2->getMessage());
                        $this->logFetch('error', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, $e2->getMessage());
                        return 1;
                    }
                } else {
                    Log::error('A1 Recordings: ошибка скачивания ' . $recordName . ': ' . $e->getMessage());
                    $this->logFetch('error', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, $e->getMessage());
                    return 1;
                }
            }

            // Build file path
            $parts    = explode('/', $recordName);
            $filename = end($parts);
            $callDate = $rec['callDate'] ?? date('Y-m-d H:i:s');
            $folder   = 'a1_recordings/' . date('Y-m', strtotime($callDate));
            Storage::disk('local')->makeDirectory($folder);
            $filePath = $folder . '/' . $filename . '.mp3';
            Storage::disk('local')->put($filePath, $bytes);
            $fileSize = strlen($bytes);

            // Insert DB record
            DB::table('a1_call_recordings')->insert([
                'record_name'   => $recordName,
                'uuid'          => $uuid,
                'call_date'     => date('Y-m-d H:i:s', strtotime($callDate)),
                'caller_part'   => $rec['callerPart'] ?? '',
                'callee_part'   => $rec['calleePart'] ?? '',
                'call_duration' => (int) ($rec['callDuration'] ?? 0),
                'file_path'     => $filePath,
                'file_size'     => $fileSize,
                'downloaded_at' => date('Y-m-d H:i:s'),
            ]);

            $filesDownloaded++;
            $bytesDownloaded += $fileSize;
        }

        $this->logFetch('success', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, null);
        $this->info("A1 Recordings: скачано {$filesDownloaded} файлов, удалено {$filesDeleted} старых.");
        return 0;
    }

    // ─────────────────────────────────────────────────────────────
    // Quota management
    // ─────────────────────────────────────────────────────────────

    private function enforceQuota(int $deleted, int $freed): array
    {
        $used = (int) DB::table('a1_call_recordings')->sum('file_size');

        while (($used + self::BUFFER_BYTES) > self::QUOTA_BYTES) {
            $oldest = DB::table('a1_call_recordings')
                ->orderBy('call_date', 'asc')
                ->first();

            if (!$oldest) {
                break;
            }

            $diskPath = storage_path('app/' . $oldest->file_path);
            if (file_exists($diskPath)) {
                @unlink($diskPath);
            }

            DB::table('a1_call_recordings')->where('id', $oldest->id)->delete();
            $used    -= $oldest->file_size;
            $freed   += $oldest->file_size;
            $deleted++;
        }

        return [$deleted, $freed];
    }

    // ─────────────────────────────────────────────────────────────
    // A1 API calls
    // ─────────────────────────────────────────────────────────────

    private function fetchRecordingsList(string $token, string $companyId, int $start, int $end): ?array
    {
        sleep(1); // rate limit: 1 req/sec
        $response = Http::withHeaders([
            'Authentication' => $token,
        ])->get(self::BASE_URL . '/record/list', [
            'company_id' => $companyId,
            'start'      => $start,
            'end'        => $end,
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            return null;
        }

        if ($response->status() === 429) {
            throw new \RuntimeException('Rate limit A1 API (429)');
        }

        if (!$response->successful()) {
            throw new \RuntimeException('record/list HTTP ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json();
        if (is_array($data) && (isset($data[0]) || $data === [])) {
            return $data;
        }
        foreach (['data', 'items', 'records'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return is_array($data) ? $data : [];
    }

    private function downloadRecording(string $token, string $companyId, string $recordName): string
    {
        sleep(1); // rate limit: 1 req/sec
        $response = Http::withHeaders([
            'Authentication' => $token,
        ])->timeout(60)->get(self::BASE_URL . '/record', [
            'company_id' => $companyId,
            'filename'   => $recordName,
        ]);

        if ($response->status() === 404) {
            throw new \RuntimeException('404: запись не найдена ' . $recordName);
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException($response->status() . ': токен отклонён при скачивании');
        }
        if (!$response->successful()) {
            throw new \RuntimeException('download HTTP ' . $response->status() . ': ' . $response->body());
        }

        return $response->body();
    }

    // ─────────────────────────────────────────────────────────────
    // Fetch log
    // ─────────────────────────────────────────────────────────────

    private function logFetch(
        string $status,
        int $periodStart,
        int $periodEnd,
        int $recordsFound,
        int $recordsNew,
        int $filesDownloaded,
        int $filesDeleted,
        int $bytesDownloaded,
        int $bytesFreed,
        ?string $errorMessage
    ): void {
        DB::table('a1_recordings_fetch_log')->insert([
            'fetched_at'       => date('Y-m-d H:i:s'),
            'status'           => $status,
            'period_start'     => $periodStart,
            'period_end'       => $periodEnd,
            'records_found'    => $recordsFound,
            'records_new'      => $recordsNew,
            'files_downloaded' => $filesDownloaded,
            'files_deleted'    => $filesDeleted,
            'bytes_downloaded' => $bytesDownloaded,
            'bytes_freed'      => $bytesFreed,
            'error_message'    => $errorMessage,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Auth (identical pattern to FetchA1MissedCalls)
    // ─────────────────────────────────────────────────────────────

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
                    Log::warning('A1 Recordings: refresh_token не сработал: ' . $e->getMessage());
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
        $tokens = [
            'access_token'       => $data['access_token']  ?? '',
            'access_expires_at'  => time() + 86400,
            'refresh_token'      => $data['refresh_token'] ?? '',
            'refresh_expires_at' => time() + 604800,
        ];
        file_put_contents(
            $this->tokensPath(),
            json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
```

- [ ] **Step 3.2: Verify command registers correctly**

```bash
docker-compose exec app php artisan list | grep a1
```

Expected output includes:
```
a1:fetch-missed-calls  ...
a1:fetch-recordings    Скачивает записи звонков с A1 ВАТС ...
```

- [ ] **Step 3.3: Dry-run with --help**

```bash
docker-compose exec app php artisan a1:fetch-recordings --help
```

Expected: shows `--period=90` option.

- [ ] **Step 3.4: Commit**

```bash
git add app/Console/Commands/FetchA1Recordings.php
git commit -m "feat: artisan command a1:fetch-recordings — sync recordings with quota rotation"
```

---

## Task 4: Register command in scheduler

**Files:**
- Modify: `app/Console/Kernel.php`

- [ ] **Step 4.1: Add schedule entry**

Add inside the `schedule()` method, after the existing `a1:fetch-missed-calls` entries:

```php
// Записи звонков — каждый час в :05 (не пересекается с пропущенными :00,:10,...,:50)
$schedule->command('a1:fetch-recordings')
    ->hourlyAt(5)
    ->withoutOverlapping();
```

- [ ] **Step 4.2: Verify schedule list**

```bash
docker-compose exec app php artisan schedule:list
```

Expected: `a1:fetch-recordings` appears with `0 * * * *` or similar hourly expression at `:05`.

- [ ] **Step 4.3: Commit**

```bash
git add app/Console/Kernel.php
git commit -m "feat: schedule a1:fetch-recordings hourly at :05"
```

---

## Task 5: MCP CallsController

**Files:**
- Create: `app/Http/Controllers/Mcp/CallsController.php`

- [ ] **Step 5.1: Create controller**

```php
<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/mcp/v1/calls/recordings         — list recordings
 * GET /api/mcp/v1/calls/recordings/{uuid}/file — download binary
 */
class CallsController extends BaseController
{
    /**
     * GET /api/mcp/v1/calls/recordings
     *
     * Query params:
     *   from     YYYY-MM-DD  (default: 30 days ago)
     *   to       YYYY-MM-DD  (default: today)
     *   caller   string      (LIKE %value%)
     *   callee   string      (LIKE %value%)
     *   page     int         (default: 1)
     *   per_page int         (default: 50, max: 200)
     */
    public function index(Request $request): JsonResponse
    {
        $from    = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to      = $request->get('to',   date('Y-m-d'));
        $caller  = $request->get('caller');
        $callee  = $request->get('callee');
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = min(200, max(1, (int) $request->get('per_page', 50)));

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $query = DB::table('a1_call_recordings')
            ->whereBetween('call_date', [$fromDt, $toDt]);

        if ($caller) {
            $query->where('caller_part', 'like', '%' . $caller . '%');
        }
        if ($callee) {
            $query->where('callee_part', 'like', '%' . $callee . '%');
        }

        $total = $query->count();

        $rows = (clone $query)
            ->orderBy('call_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['uuid', 'record_name', 'call_date', 'caller_part', 'callee_part', 'call_duration', 'file_size', 'downloaded_at']);

        $totalSizeBytes = (int) DB::table('a1_call_recordings')->sum('file_size');

        $lastFetch = DB::table('a1_recordings_fetch_log')
            ->where('status', 'success')
            ->orderBy('fetched_at', 'desc')
            ->value('fetched_at');

        $data = $rows->map(function ($row) {
            return [
                'uuid'          => $row->uuid,
                'record_name'   => $row->record_name,
                'call_date'     => $row->call_date,
                'caller_part'   => $row->caller_part,
                'callee_part'   => $row->callee_part,
                'call_duration' => (int) $row->call_duration,
                'file_size'     => (int) $row->file_size,
                'downloaded_at' => $row->downloaded_at,
            ];
        })->values()->all();

        return $this->envelope(
            [
                'from'     => $from,
                'to'       => $to,
                'caller'   => $caller,
                'callee'   => $callee,
                'page'     => $page,
                'per_page' => $perPage,
            ],
            $data,
            [
                'total_rows'        => $total,
                'page'              => $page,
                'per_page'          => $perPage,
                'total_size_bytes'  => $totalSizeBytes,
                'quota_bytes'       => 1_073_741_824,
                'data_freshness'    => $lastFetch,
            ]
        );
    }

    /**
     * GET /api/mcp/v1/calls/recordings/{uuid}/file
     *
     * Streams the mp3 binary. Does NOT return the standard JSON envelope.
     * Returns 404 if the DB record is missing or the file is gone from disk.
     */
    public function streamFile(string $uuid): Response
    {
        $recording = DB::table('a1_call_recordings')
            ->where('uuid', $uuid)
            ->first();

        if (!$recording) {
            abort(404, 'Recording not found');
        }

        $diskPath = storage_path('app/' . $recording->file_path);

        if (!file_exists($diskPath)) {
            abort(404, 'Recording file not found on disk');
        }

        $parts    = explode('/', $recording->file_path);
        $filename = end($parts);

        return response(file_get_contents($diskPath), 200, [
            'Content-Type'        => 'audio/mpeg',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => filesize($diskPath),
        ]);
    }
}
```

- [ ] **Step 5.2: Verify no PHP syntax errors**

```bash
docker-compose exec app php -l app/Http/Controllers/Mcp/CallsController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5.3: Commit**

```bash
git add app/Http/Controllers/Mcp/CallsController.php
git commit -m "feat: CallsController — MCP endpoints for recordings list and file stream"
```

---

## Task 6: Register routes

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 6.1: Add import and routes**

At the top of `routes/api.php`, add to the use-block:

```php
use App\Http\Controllers\Mcp\CallsController;
```

Inside the MCP `->group(function () {` closure, add at the end (before the closing `});`):

```php
        // A1 Call Recordings (ats-calls branch)
        Route::get('calls/recordings', [CallsController::class, 'index'])->name('calls.recordings');
        Route::get('calls/recordings/{uuid}/file', [CallsController::class, 'streamFile'])->name('calls.recordings.file');
```

- [ ] **Step 6.2: Verify routes are registered**

```bash
docker-compose exec app php artisan route:list | grep recordings
```

Expected:
```
GET|HEAD  api/mcp/v1/calls/recordings           mcp.v1.calls.recordings
GET|HEAD  api/mcp/v1/calls/recordings/{uuid}/file  mcp.v1.calls.recordings.file
```

- [ ] **Step 6.3: Smoke test the list endpoint (returns empty results since no data yet)**

```bash
curl -s \
  -H "Authorization: Bearer $(grep MCP_API_TOKEN ~/sites/tiktakby/.env | cut -d= -f2)" \
  http://localhost/api/mcp/v1/calls/recordings | python3 -m json.tool | head -20
```

Expected: JSON with `"data": []` and the standard `{query, data, meta}` envelope. No 500 error.

- [ ] **Step 6.4: Commit**

```bash
git add routes/api.php
git commit -m "feat: register MCP routes for calls/recordings list and file stream"
```

---

## Task 7: End-to-end smoke test

This task verifies the full flow manually without real A1 credentials.

- [ ] **Step 7.1: Insert a synthetic recording row into the DB**

```bash
docker-compose exec app php artisan tinker --execute="
DB::table('a1_call_recordings')->insert([
    'record_name'   => '1080/2026-05-11/999999999999999999',
    'uuid'          => '999999999999999999',
    'call_date'     => '2026-05-11 14:23:00',
    'caller_part'   => '375296303532',
    'callee_part'   => '375291234567',
    'call_duration' => 185,
    'file_path'     => 'a1_recordings/2026-05/999999999999999999.mp3',
    'file_size'     => 1024,
    'downloaded_at' => '2026-05-11 15:05:12',
]);
echo 'inserted';
"
```

- [ ] **Step 7.2: Create the dummy file on disk**

```bash
docker-compose exec app bash -c "
  mkdir -p storage/app/a1_recordings/2026-05
  dd if=/dev/urandom bs=1024 count=1 of=storage/app/a1_recordings/2026-05/999999999999999999.mp3 2>/dev/null
  echo 'file created'
"
```

- [ ] **Step 7.3: Test list endpoint returns the synthetic record**

```bash
curl -s \
  -H "Authorization: Bearer $(grep MCP_API_TOKEN ~/sites/tiktakby/.env | cut -d= -f2)" \
  "http://localhost/api/mcp/v1/calls/recordings?from=2026-05-01&to=2026-05-31" \
  | python3 -m json.tool
```

Expected: `data` array contains the row with `uuid: "999999999999999999"`, `meta.total_rows: 1`.

- [ ] **Step 7.4: Test file stream endpoint**

```bash
curl -s -o /tmp/test_recording.mp3 \
  -H "Authorization: Bearer $(grep MCP_API_TOKEN ~/sites/tiktakby/.env | cut -d= -f2)" \
  "http://localhost/api/mcp/v1/calls/recordings/999999999999999999/file" \
  -w "HTTP %{http_code}, size %{size_download}\n"
```

Expected: `HTTP 200, size 1024` (or similar). File downloaded successfully.

- [ ] **Step 7.5: Test 404 on non-existent UUID**

```bash
curl -s \
  -H "Authorization: Bearer $(grep MCP_API_TOKEN ~/sites/tiktakby/.env | cut -d= -f2)" \
  "http://localhost/api/mcp/v1/calls/recordings/nonexistent/file" \
  -o /dev/null -w "%{http_code}\n"
```

Expected: `404`

- [ ] **Step 7.6: Clean up synthetic data**

```bash
docker-compose exec app php artisan tinker --execute="
DB::table('a1_call_recordings')->where('uuid', '999999999999999999')->delete();
echo 'deleted';
"
docker-compose exec app bash -c "rm -f storage/app/a1_recordings/2026-05/999999999999999999.mp3"
```

- [ ] **Step 7.7: Final commit with cleanup note**

```bash
git add -A
git status
# Ensure only the intended files are staged; if clean, skip commit
```

If there are stray changes (e.g., local storage directories), add them to `.gitignore` if not already excluded:
- `storage/app/a1_recordings/` should be in `.gitignore` (same pattern as `storage/app/*.json`)

---

## Self-Review

### Spec coverage check

| Spec requirement | Task |
|-----------------|------|
| DB tables `a1_call_recordings` + `a1_recordings_fetch_log` | Task 1 |
| Deduplication by `record_name` UNIQUE | Task 3 — `where('record_name', $recordName)->exists()` |
| Quota 1 GB, delete oldest by `call_date` | Task 3 — `enforceQuota()` |
| `Cache::lock('a1_api_mutex')` in new command | Task 3 — `handle()` |
| `Cache::lock('a1_api_mutex')` in existing command | Task 2 |
| Rate limit `sleep(1)` before each A1 call | Task 3 — both `fetchRecordingsList` and `downloadRecording` |
| Auto-detect first run → 30 days | Task 3 — `$isEmpty` check |
| Normal run → 90 min period | Task 3 — `--period=90` default |
| Scheduler `hourlyAt(5)->withoutOverlapping()` | Task 4 |
| A1 404 on download → skip record | Task 3 — `str_contains($e->getMessage(), '404')` |
| A1 401/403 → re-auth, retry | Task 3 — re-auth block |
| File storage `a1_recordings/YYYY-MM/filename.mp3` | Task 3 — `$folder` + `Storage::put` |
| MCP GET `calls/recordings` with filters | Task 5 `index()` |
| MCP GET `calls/recordings/{uuid}/file` binary stream | Task 5 `streamFile()` |
| Routes in MCP middleware group | Task 6 |
| 404 if file missing on disk | Task 5 `streamFile()` — `file_exists` check |

All spec requirements covered. No gaps found.

### Placeholder scan

No TBD, TODO, or "similar to Task N" patterns. All code blocks are complete.

### Type consistency

- `record_name` — used as deduplication key in Task 3, matches UNIQUE column in Task 1 migration
- `uuid` — used as URL param in route (Task 6), looked up via `where('uuid', $uuid)` in Task 5, inserted in Task 3
- `file_path` — stored relative to `storage/app/` in Task 3, expanded with `storage_path('app/')` in Task 5
- `enforceQuota()` — returns `[$deleted, $freed]` tuple, consumed with list-assignment in Task 3
- All column names match migration definition exactly
