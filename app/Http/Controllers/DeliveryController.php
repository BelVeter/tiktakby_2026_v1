<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function showPage($lang, Request $req){
        return view('delivery', ['$p'=>$lang]);
    }
}
