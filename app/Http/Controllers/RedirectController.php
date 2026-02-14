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

    public function ltToRu()
    {
        return redirect('/ru', 301);
    }

    public function enToRu()
    {
        return redirect('/ru', 301);
    }

    // --- Карнавальные костюмы ---

    public function karnavalRedirect()
    {
        return redirect('/ru/prokat-detskih-tovarovkarnavalnye-kostyumy', 301);
    }

    // --- /en, /lt → /ru редиректы для статических страниц ---

    public function enAboutToRu()
    {
        return redirect('/ru/about', 301);
    }

    public function ltAboutToRu()
    {
        return redirect('/ru/about', 301);
    }

    public function enConditionsToRu()
    {
        return redirect('/ru/conditions', 301);
    }

    public function ltConditionsToRu()
    {
        return redirect('/ru/conditions', 301);
    }

    public function enDeliveryToRu()
    {
        return redirect('/ru/delivery', 301);
    }

    public function ltDeliveryToRu()
    {
        return redirect('/ru/delivery', 301);
    }

    public function enContactsToRu()
    {
        return redirect('/ru/contacts', 301);
    }

    public function ltContactsToRu()
    {
        return redirect('/ru/contacts', 301);
    }

    public function enPolicyToRu()
    {
        return redirect('/ru/policy', 301);
    }

    public function ltPolicyToRu()
    {
        return redirect('/ru/policy', 301);
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
