<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConditionsController extends Controller
{
    public function showPage($lang, Request $req){
        return view('conditions', ['$p'=>$lang]);
    }
}
