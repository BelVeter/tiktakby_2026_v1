<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::get('/',
//    'App\Http\Controllers\MainController@showPage'
//);

Route::get('/', function () {
    return redirect('/ru', 301);
});
Route::get('/lt', function () {
    return redirect('/ru', 301);
});
Route::get('/en', function () {
    return redirect('/ru', 301);
});





Route::get('/ru/prokat-detskih-tovarov/karnavalnye-kostyumy', function () {
    return redirect('/ru/prokat-detskih-tovarovkarnavalnye-kostyumy', 301);
});

Route::redirect('/ru/prokat-detskih-tovarovkarnavalnye-kostyumy', '/ru/karnavalnye-kostyumy', 301);
Route::redirect('/ru/prokat-detskih-tovarovkarnavalnye-kostyumy/{any}', '/ru/karnavalnye-kostyumy/{any}', 301)->where('any', '.*');


Route::post(
    '/zvonok/bron',
    'App\Http\Controllers\ZvonokController@bron'
);
Route::post(
    '/zvonok/kb',
    'App\Http\Controllers\ZvonokController@KBronActions'
);

Route::post(
    '/zvonok',
    'App\Http\Controllers\ZvonokController@addCall'
)->name('zvonokSave');

Route::post(
    '/subscribe',
    'App\Http\Controllers\ZvonokController@addSubscription'
);

Route::get(
    '/{lang}/',
    'App\Http\Controllers\MainController@showPage'
);

Route::get('/test/', function () {
    //$menu=\App\MyClasses\CatMenuItem::getAllMenu();
    //dd($menu);
    return view('home2');
});





Route::get('/en/about', function () {
    return redirect('/ru/about', 301);
});
Route::get('/lt/about', function () {
    return redirect('/ru/about', 301);
});

Route::get(
    '/{lang}/about',
    'App\Http\Controllers\AboutController@showAboutPage'
)->name('about');




Route::get('/en/conditions', function () {
    return redirect('/ru/conditions', 301);
});
Route::get('/lt/conditions', function () {
    return redirect('/ru/conditions', 301);
});
Route::get(
    '/{lang}/conditions',
    'App\Http\Controllers\AboutController@showConditionsPage'
);



Route::get('/en/delivery', function () {
    return redirect('/ru/delivery', 301);
});
Route::get('/lt/delivery', function () {
    return redirect('/ru/delivery', 301);
});
Route::get(
    '/{lang}/delivery',
    'App\Http\Controllers\AboutController@showDeliveryPage'
);



Route::get('/en/contacts', function () {
    return redirect('/ru/contacts', 301);
});
Route::get('/lt/contacts', function () {
    return redirect('/ru/contacts', 301);
});

Route::get(
    '/{lang}/contacts',
    'App\Http\Controllers\AboutController@showContactsPage'
);



Route::get('/en/policy', function () {
    return redirect('/ru/policy', 301);
});
Route::get('/lt/policy', function () {
    return redirect('/ru/policy', 301);
});

Route::get(
    '/{lang}/policy',
    'App\Http\Controllers\AboutController@showPolicyPage'
);


Route::get(
    '/{lang}/search',
    'App\Http\Controllers\SearchController@search'
)->name('search');

Route::get(
    '/{lang}/producer',
    'App\Http\Controllers\SearchController@producerFilter'
)->name('filter.producer');


Route::get(
    '/{lang}/filter',
    'App\Http\Controllers\SearchController@ageFilter'
)->name('filter.age');


// Bioptron URL Alias & Redirect
Route::redirect('/ru/medical-prokat/bioptron-prokat-minsk/prokat-bioptron-minsk', '/ru/medical-prokat/bioptron', 301);

Route::get('/ru/medical-prokat/bioptron', function (\Illuminate\Http\Request $req) {
    return app()->make('App\Http\Controllers\CatController')->categoryMainPage('ru', 'medical-prokat', 'bioptron-prokat-minsk', 'prokat-bioptron-minsk', $req);
});



Route::get(
    '/ru/prokat/{cat}',
    'App\Http\Controllers\CatController@CatMainPage'
)->name('cat-main-page');

//Route::post('/ru/prokat/{cat}/{model}', 'L3Controller@l3Order');

//Route::get(
//    '/ru/prokat/{cat}/{model}',
//    'L3Controller@l3ShowPage'
//)->name('l3page');


Route::get(
    '/{lang}/{razdel}',
    'App\Http\Controllers\CatController@razdelMainPage'
)->name('razdelPage');

Route::get(
    '/{lang}/{razdel}/{subrazdel}',
    'App\Http\Controllers\CatController@subRazdelMainPage'
)->name('subRazdelPage');

Route::get(
    '/{lang}/{razdel}/{subrazdel}/{category}',
    'App\Http\Controllers\CatController@categoryMainPage'
)->name('categoryPage');

Route::post(
    //'/ru/prokat/{cat}/{model}',
    '/{lang}/{razdel}/{subrazdel}/{category}/{model}',
    'App\Http\Controllers\L3Controller@l3Order2'
);

Route::get(
    //'/ru/prokat/{cat}/{model}',
    '/{lang}/{razdel}/{subrazdel}/{category}/{model}',
    'App\Http\Controllers\L3Controller@l3ShowPage2'
)->name('l3page');

Route::fallback(function () {
    return response()->view('not_found');
});
