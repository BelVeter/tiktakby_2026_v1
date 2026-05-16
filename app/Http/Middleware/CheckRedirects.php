<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckRedirects
{
    /**
     * Расширения файлов, которые не нужно проверять на редиректы.
     * Боты часто запрашивают URL изображений как страницы — это вызывает
     * лишние SQL-запросы и засоряет статистику hit_count.
     */
    private const STATIC_EXTENSIONS = [
        'png',
        'jpg',
        'jpeg',
        'gif',
        'webp',
        'avif',
        'svg',
        'ico',
        'css',
        'js',
        'woff',
        'woff2',
        'ttf',
        'eot',
        'pdf',
        'xml',
        'txt',
    ];

    /**
     * TTL кэша таблицы redirects (в секундах).
     * После добавления/изменения редиректа через admin-панель
     * новый редирект начнёт работать не позже чем через это время.
     */
    private const CACHE_TTL = 600; // 10 минут

    /**
     * Проверяет, есть ли активное перенаправление для текущего URL.
     * Если есть — выполняет redirect с нужным кодом.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Language Enforcement
        $segments = $request->segments();

        if (count($segments) > 0) {
            $firstSegment = $segments[0];

            // Check if first segment is a 2-letter code (like 'en', 'lt', 'fr') AND not 'ru'
            if (strlen($firstSegment) === 2 && $firstSegment !== 'ru' && !in_array($firstSegment, ['bb', 'js', 'css', 'img'])) {

                // Replace first segment with 'ru'
                $segments[0] = 'ru';
                $newPath = implode('/', $segments);

                // Preserve query string
                $queryString = $request->getQueryString();
                $target = $newPath . ($queryString ? '?' . $queryString : '');

                return redirect($target, 301);
            }
        }

        // 2. Пропускаем запросы к статическим файлам (изображения, CSS, JS и т.д.)
        // Это исключает мусорные запросы от ботов и снижает нагрузку на БД.
        $path = '/' . ltrim($request->path(), '/');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension && in_array($extension, self::STATIC_EXTENSIONS)) {
            return $next($request);
        }

        try {
            // 3. Database Redirects — с кэшированием всей таблицы

            // Сначала точное совпадение — O(1) поиск по ключу массива.
            $exactMap = Cache::remember('redirects_exact_map', self::CACHE_TTL, function () {
                $rows = DB::table('redirects')->where('is_active', 1)->where('is_regex', 0)->get();
                $map = [];
                foreach ($rows as $row) {
                    $map[$row->source_url] = $row;
                }
                return $map;
            });

            if (isset($exactMap[$path])) {
                $redirect = $exactMap[$path];
                DB::table('redirects')
                    ->where('id', $redirect->id)
                    ->update(['hit_count' => DB::raw('hit_count + 1'), 'last_hit_at' => now()]);
                return redirect($redirect->target_url, $redirect->status_code);
            }

            // Затем regex-паттерны — перебор по списку (их немного).
            // source_url хранит полный PCRE-паттерн (например: #^/[^/]+/(prokat-.+)$#).
            // target_url — строка замены с backreferences (например: /ru/$1).
            $regexList = Cache::remember('redirects_regex_list', self::CACHE_TTL, function () {
                return DB::table('redirects')
                    ->where('is_active', 1)
                    ->where('is_regex', 1)
                    ->orderBy('id')
                    ->get()
                    ->toArray();
            });

            foreach ($regexList as $regex) {
                if (@preg_match($regex->source_url, $path)) {
                    $target = preg_replace($regex->source_url, $regex->target_url, $path);
                    DB::table('redirects')
                        ->where('id', $regex->id)
                        ->update(['hit_count' => DB::raw('hit_count + 1'), 'last_hit_at' => now()]);
                    return redirect($target, $regex->status_code);
                }
            }
        } catch (\Exception $e) {
            // Таблица может не существовать в dev-среде — пропускаем молча.
            // Log::warning('CheckRedirects: ' . $e->getMessage());
        }

        return $next($request);
    }
}
