<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

    public function notFound()
    {
        return response()->view('not_found', [], 404);
    }
}
