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

// --- Корневые редиректы ---
Route::get('/', 'App\Http\Controllers\RedirectController@rootToRu');
Route::get('/lt', 'App\Http\Controllers\RedirectController@ltToRu');
Route::get('/en', 'App\Http\Controllers\RedirectController@enToRu');


// --- Карнавальные костюмы: цепочка редиректов ---
Route::get('/ru/prokat-detskih-tovarov/karnavalnye-kostyumy', 'App\Http\Controllers\RedirectController@karnavalRedirect');
Route::redirect('/ru/prokat-detskih-tovarovkarnavalnye-kostyumy', '/ru/karnavalnye-kostyumy', 301);
Route::redirect('/ru/prokat-detskih-tovarovkarnavalnye-kostyumy/{any}', '/ru/karnavalnye-kostyumy/{any}', 301)->where('any', '.*');


// --- Звонки / подписки ---
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

// --- Главная страница ---
Route::get(
    '/{lang}/',
    'App\Http\Controllers\MainController@showPage'
);

// --- Тестовая страница ---
Route::get('/test/', 'App\Http\Controllers\RedirectController@testPage');


// --- Редиректы /en, /lt → /ru для статических страниц ---

Route::get('/en/about', 'App\Http\Controllers\RedirectController@enAboutToRu');
Route::get('/lt/about', 'App\Http\Controllers\RedirectController@ltAboutToRu');

Route::get(
    '/{lang}/about',
    'App\Http\Controllers\AboutController@showAboutPage'
)->name('about');



Route::get('/en/conditions', 'App\Http\Controllers\RedirectController@enConditionsToRu');
Route::get('/lt/conditions', 'App\Http\Controllers\RedirectController@ltConditionsToRu');
Route::get(
    '/{lang}/conditions',
    'App\Http\Controllers\AboutController@showConditionsPage'
);



Route::get('/en/delivery', 'App\Http\Controllers\RedirectController@enDeliveryToRu');
Route::get('/lt/delivery', 'App\Http\Controllers\RedirectController@ltDeliveryToRu');
Route::get(
    '/{lang}/delivery',
    'App\Http\Controllers\AboutController@showDeliveryPage'
);



Route::get('/en/contacts', 'App\Http\Controllers\RedirectController@enContactsToRu');
Route::get('/lt/contacts', 'App\Http\Controllers\RedirectController@ltContactsToRu');

Route::get(
    '/{lang}/contacts',
    'App\Http\Controllers\AboutController@showContactsPage'
);



Route::get('/en/policy', 'App\Http\Controllers\RedirectController@enPolicyToRu');
Route::get('/lt/policy', 'App\Http\Controllers\RedirectController@ltPolicyToRu');

Route::get(
    '/{lang}/policy',
    'App\Http\Controllers\AboutController@showPolicyPage'
);


// --- Поиск и фильтры ---
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


// --- Bioptron ---
Route::redirect('/ru/medical-prokat/bioptron-prokat-minsk/prokat-bioptron-minsk', '/ru/medical-prokat/bioptron', 301);
Route::get('/ru/medical-prokat/bioptron', 'App\Http\Controllers\RedirectController@bioptronAlias');



// --- Каталог ---
Route::get(
    '/ru/prokat/{cat}',
    'App\Http\Controllers\CatController@CatMainPage'
)->name('cat-main-page');


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
    '/{lang}/{razdel}/{subrazdel}/{category}/{model}',
    'App\Http\Controllers\L3Controller@l3Order2'
);

Route::get(
    '/{lang}/{razdel}/{subrazdel}/{category}/{model}',
    'App\Http\Controllers\L3Controller@l3ShowPage2'
)->name('l3page');

// --- Fallback (404) ---
Route::fallback('App\Http\Controllers\RedirectController@notFound');
