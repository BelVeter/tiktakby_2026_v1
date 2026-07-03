<?php

namespace App\Http\Controllers;

use App\MyClasses\L2ModelWeb;
use App\MyClasses\MainPage;
use bb\classes\Category;
use bb\classes\Model;
use bb\classes\ModelWeb;
use Illuminate\Http\Request;

class CatController extends Controller{
    // Legacy /ru/prokat/{cat} route (old menu_items-based catalog, table no longer exists).
    // "karnaval" aggregated several categories under one legacy menu item with no modern equivalent page.
    public function CatMainPage($cat, Request $req){

        if ($cat === 'karnaval') {
            return redirect('/ru/karnavalnye-kostyumy', 301);
        }

        $category = Category::getByUrlName($cat, 'ru');
        if (!$category) {
            return response()->view('not_found', [], 404);
        }

        $url = $category->getUrlForPage('ru');
        if ($url === '/ru/') {
            return response()->view('not_found', [], 404);
        }

        return redirect($url, 301);
    }

    public function razdelMainPage($lang, $razdelName, Request $req) {

      // Check if lang is not 'ru'
      if ($lang !== 'ru') {
        // Redirect to the same route with 'ru' lang
        return redirect("/ru/{$razdelName}", 301);
      }

      $filter=['gender' => $req->input('gender'), 'rost' => $req->input('rost'), 'eventDate' => $req->input('date')];

      $showPageNumber = $req->input('page');
      if (!$showPageNumber) $showPageNumber=1;

        $p=MainPage::getRazdelPageForWeb($lang, $razdelName, $showPageNumber, $filter);

        if(!$p || !$p->isRealPage() || !$p->_razdel) return response()->view('not_found', [], 404);

        return view('catpage', ['p' => $p]);
    }

    public function subRazdelMainPage($lang, $razdelName, $subRazdelName, Request $req) {

      // Check if lang is not 'ru'
      if ($lang !== 'ru') {
        // Redirect to the same route with 'ru' lang
        return redirect("/ru/{$razdelName}/{$subRazdelName}", 301);
      }

      $showPageNumber = $req->input('page');
      if (!$showPageNumber) $showPageNumber=1;

        $filter=['gender' => $req->input('gender'), 'rost' => $req->input('rost'), 'eventDate' => $req->input('date')];

        $p=MainPage::getWebPageBySubRazdelAndRazdel($lang, $razdelName, $subRazdelName, $showPageNumber, $filter);

        if(!$p || !$p->isRealPage()) return response()->view('not_found', [], 404);

        return view('catpage', ['p' => $p]);
    }

    public function categoryMainPage($lang, $razdelName, $subRazdelName, $cateforyName, Request $req) {

      // Check if lang is not 'ru'
      if ($lang !== 'ru') {
        // Redirect to the same route with 'ru' lang
        return redirect("/ru/{$razdelName}/{$subRazdelName}/{$cateforyName}", 301);
      }


      $showPageNumber = $req->input('page');
      if (!$showPageNumber) $showPageNumber=1;

        $filter=['gender' => $req->input('gender'), 'rost' => $req->input('rost'), 'eventDate' => $req->input('date')];

        $p=MainPage::getWebPageByCategoryAndSubRazdelAndRazdel($lang, $razdelName, $subRazdelName, $cateforyName, $showPageNumber, $filter);
        //dd($p);

        if(!$p || !$p->isRealPage()) return response()->view('not_found', [], 404);

        return view('catpage', ['p' => $p]);
    }
}
