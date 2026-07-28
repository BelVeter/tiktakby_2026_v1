<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Сливает дубль категории «Принцессы Диснея»: 158 -> 178.
 *
 * Категория 158 лежала в разделе «Детские товары / Детская комната», хотя это
 * карнавальный костюм, и не была привязана к дереву каталога
 * (`subrazdel_category` пуст), поэтому в навигации её не было видно.
 * Категория 178 — правильная: «Карнавальные костюмы / Сказки, мультики»,
 * привязана к дереву, в ней уже 12 моделей костюмов принцесс.
 *
 * В 158 одна живая модель — 730 «София Прекрасная» Дисней, арт. 7067 (Батик),
 * у неё 3 юнита разных размеров с независимой историей аренды:
 *   702342 (размер 26)      — 13 сделок, 270.00 руб., 2016-2023
 *   702460 (размер 3-4 года) — 11 сделок, 250.50 руб., 2018-2022
 *   702459 (размер 2-3 года) — 2 сделки,  49.00 руб., 2017-2019, в архиве
 * Юниты не трогаем — это три физически разных костюма, а не дубли.
 *
 * После переноса меняется канонический адрес модели, поэтому ставим 301
 * и выпрямляем возможные цепочки. Осиротевшая SEO-строка в `pages` для
 * url_key старой категории удаляется.
 */
class MergeCategory158Into178 extends Migration
{
    private const DUP  = 158;
    private const KEEP = 178;

    private const OLD_URL = '/ru/prokat-detskih-tovarov/detskaya-komnata/kostyum-princessy-naprokat/costume_printsesy_sophia_prekrasnaya';
    private const NEW_URL = '/ru/karnavalnye-kostyumy/kostumy-zverei/disney-princesses/costume_printsesy_sophia_prekrasnaya';

    public function up(): void
    {
        // Редиректы — до guard'а, чтобы шаг оставался идемпотентным.
        DB::table('redirects')->updateOrInsert(
            ['source_url' => self::OLD_URL],
            ['target_url' => self::NEW_URL, 'status_code' => 301, 'is_active' => 1]
        );

        $flattened = DB::table('redirects')
            ->where('target_url', self::OLD_URL)
            ->where('source_url', '<>', self::OLD_URL)
            ->update(['target_url' => self::NEW_URL]);

        // CheckRedirects кэширует карту редиректов на 10 минут и сам её не
        // инвалидирует — сбрасываем оба ключа, как это делает RedirectsController.
        Cache::forget('redirects_exact_map');
        Cache::forget('redirects_regex_list');

        if (!DB::table('tovar_rent_cat')->where('tovar_rent_cat_id', self::DUP)->exists()
            || !DB::table('tovar_rent_cat')->where('tovar_rent_cat_id', self::KEEP)->exists()) {
            logger()->info('MergeCategory158Into178: слияние пропущено', ['chains_flattened' => $flattened]);
            return;
        }

        DB::transaction(function () use ($flattened) {
            $moved = [
                'tovar_rent'            => DB::table('tovar_rent')->where('tovar_rent_cat_id', self::DUP)->update(['tovar_rent_cat_id' => self::KEEP]),
                'tovar_rent_items'      => DB::table('tovar_rent_items')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]),
                'tovar_rent_items_arch' => DB::table('tovar_rent_items_arch')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]),
                'rent_orders'           => DB::table('rent_orders')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]),
                'rent_orders_arch'      => DB::table('rent_orders_arch')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]),
            ];

            $deleted = [
                'pages'              => DB::table('pages')->where('url_key', 'kostyum-princessy-naprokat')->delete(),
                'subrazdel_category' => DB::table('subrazdel_category')->where('tovar_rent_cat_id', self::DUP)->delete(),
                'tovar_rent_cat'     => DB::table('tovar_rent_cat')->where('tovar_rent_cat_id', self::DUP)->delete(),
            ];

            logger()->info('MergeCategory158Into178: слито', [
                'moved' => $moved, 'deleted' => $deleted, 'chains_flattened' => $flattened,
            ]);
        });
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа, снятого перед выкладкой.
    }
}
