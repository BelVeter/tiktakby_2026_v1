<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckRedirects
{
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
            // Also exclude 'bb' (admin), 'js', 'css', 'img' just in case, though they are usually handled by web server
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

        try {
            // 2. Database Redirects
            // Получаем текущий URI (без query string)
            $path = '/' . ltrim($request->path(), '/');

            // Ищем активное перенаправление
            $redirect = DB::table('redirects')
                ->where('source_url', $path)
                ->where('is_active', 1)
                ->first();

            if ($redirect) {
                // Обновляем статистику
                DB::table('redirects')
                    ->where('id', $redirect->id)
                    ->update([
                        'hit_count' => DB::raw('hit_count + 1'),
                        'last_hit_at' => now(),
                    ]);

                return redirect($redirect->target_url, $redirect->status_code);
            }
        } catch (\Exception $e) {
            // Table may not exist in dev environment — skip silently
        }

        return $next($request);
    }
}
