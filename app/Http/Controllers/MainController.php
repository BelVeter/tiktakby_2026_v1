<?php

namespace App\Http\Controllers;

use App\MyClasses\CatMainPage;
use App\MyClasses\MainPage;
use bb\classes\Category;
use bb\classes\Model;
use bb\classes\ModelWeb;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class MainController extends Controller{

    public function showPage($lang='ru', Request $req) {
//        return view('home');

        $p=MainPage::getPage($lang, 'main', 'main');
        if(!$p) {
            $p=MainPage::getPage('ru', 'main', 'main');
        }
        return view('home', ['p' => $p]);
    }
}
