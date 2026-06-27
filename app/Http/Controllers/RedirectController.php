<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Контроллер для redirect-маршрутов.
 * Все closures из web.php перенесены сюда, чтобы работал php artisan route:cache.
 */
class RedirectController extends Controller
{
    // --- Корневые редиректы на /ru ---

    public function rootToRu()
    {
        return redirect('/ru', 301);
    }

    // --- Карнавальные костюмы ---

    public function karnavalRedirect()
    {
        return redirect('/ru/prokat-detskih-tovarovkarnavalnye-kostyumy', 301);
    }

    // --- Bioptron alias ---

    public function bioptronAlias(Request $req)
    {
        return app()->make('App\Http\Controllers\CatController')
            ->categoryMainPage('ru', 'medical-prokat', 'bioptron-prokat-minsk', 'prokat-bioptron-minsk', $req);
    }

    // --- Тестовая и fallback ---

    public function testPage()
    {
        return view('home2');
    }

    public function notFound(Request $request)
    {
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));

        if (!empty($segments)) {
            $target = $this->resolveRedirectUrl(end($segments));
            if ($target) {
                return redirect($target, 301);
            }
        }

        return response()->view('not_found', [], 404);
    }

    /**
     * Ищет актуальный URL для slug начиная с самого специфичного уровня (товар → категория → подраздел → раздел).
     * Используется для умного 301-редиректа старых/несуществующих путей.
     */
    private function resolveRedirectUrl(string $slug): ?string
    {
        // 1. Товар (L3): rent_model_web.page_addr
        $model = DB::selectOne("
            SELECT CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', tc.cat_url_key, '/', rmw.page_addr) AS url
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON tr.tovar_rent_id = rmw.model_id
            JOIN tovar_rent_cat tc ON tc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
            JOIN sub_razdel sr ON sr.id_sub_razdel = sc.id_sub_razdel
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE rmw.lang = 'ru' AND rmw.status = 'show' AND rmw.page_addr = ?
            ORDER BY r.razdel_order_num, sr.order_num_sub_razd
            LIMIT 1
        ", [$slug]);
        if ($model) return $model->url;

        // 2. Категория: tovar_rent_cat.cat_url_key
        $cat = DB::selectOne("
            SELECT CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', tc.cat_url_key) AS url
            FROM tovar_rent_cat tc
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
            JOIN sub_razdel sr ON sr.id_sub_razdel = sc.id_sub_razdel
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE tc.cat_url_key = ?
            ORDER BY r.razdel_order_num, sr.order_num_sub_razd
            LIMIT 1
        ", [$slug]);
        if ($cat) return $cat->url;

        // 3. Подраздел: sub_razdel.url_sub_razdel_name
        $sub = DB::selectOne("
            SELECT CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name) AS url
            FROM sub_razdel sr
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE sr.url_sub_razdel_name = ?
            ORDER BY r.razdel_order_num, sr.order_num_sub_razd
            LIMIT 1
        ", [$slug]);
        if ($sub) return $sub->url;

        // 4. Раздел: razdel.url_razdel_name
        $razdel = DB::selectOne("
            SELECT CONCAT('/ru/', url_razdel_name) AS url
            FROM razdel
            WHERE url_razdel_name = ?
            LIMIT 1
        ", [$slug]);
        if ($razdel) return $razdel->url;

        return null;
    }
}
