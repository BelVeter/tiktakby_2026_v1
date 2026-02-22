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
// --- Корневые редиректы ---
Route::get('/', 'App\Http\Controllers\RedirectController@rootToRu');


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

Route::get('/favorites', 'App\Http\Controllers\FavoritesController@index');
Route::post('/favorites/cards', 'App\Http\Controllers\FavoritesController@getCards');

// --- Корзина ---
Route::get('/cart', 'App\Http\Controllers\CartController@index');
Route::post('/cart/checkout', 'App\Http\Controllers\CartController@checkout');
Route::post('/cart/tariffs', 'App\Http\Controllers\CartController@getTariffs');
Route::post('/cart/check-availability', 'App\Http\Controllers\CartController@checkAvailability');

// --- Главная страница ---
Route::get(
    '/{lang}/',
    'App\Http\Controllers\MainController@showPage'
);

// --- Тестовая страница ---
Route::get('/test/', 'App\Http\Controllers\RedirectController@testPage');


// --- Статические страницы ---

Route::get(
    '/{lang}/about',
    'App\Http\Controllers\AboutController@showAboutPage'
)->name('about');


Route::get(
    '/{lang}/conditions',
    'App\Http\Controllers\AboutController@showConditionsPage'
);


Route::get(
    '/{lang}/delivery',
    'App\Http\Controllers\AboutController@showDeliveryPage'
);

Route::get(
    '/{lang}/payment',
    'App\Http\Controllers\AboutController@showPaymentPage'
);

Route::get(
    '/{lang}/contacts',
    'App\Http\Controllers\AboutController@showContactsPage'
);


Route::get(
    '/{lang}/policy',
    'App\Http\Controllers\AboutController@showPolicyPage'
);

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
