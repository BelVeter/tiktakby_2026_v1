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

        return $next($request);
    }
}
