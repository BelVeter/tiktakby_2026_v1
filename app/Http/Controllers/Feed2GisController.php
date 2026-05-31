<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Feed2GisController extends Controller
{
    private const CACHE_KEY = 'feed_2gis_xml';
    private const BASE_URL  = 'https://tiktak.by';

    private const FEED_CATEGORIES = [
        1 => 'Прокат детских товаров',
        2 => 'Карнавальные костюмы',
        3 => 'Медицинское оборудование',
        4 => 'Уборочное оборудование',
        5 => 'Спортивный прокат',
    ];

    private const SERVICES = [

        // --- Велосипеды, самокаты, беговелы ---
        [
            'id'          => 1,
            'name'        => 'Прокат детских велосипедов',
            'url'         => '/ru/prokat-detskih-tovarov/begovely_velosipedy_samokaty/velosiped-detskii-prokat',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'velosiped-detskii-prokat'],
        ],
        [
            'id'          => 2,
            'name'        => 'Прокат самокатов',
            'url'         => '/ru/prokat-detskih-tovarov/begovely_velosipedy_samokaty/samokaty',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'samokaty'],
        ],
        [
            'id'          => 3,
            'name'        => 'Прокат беговелов',
            'url'         => '/ru/prokat-detskih-tovarov/begovely_velosipedy_samokaty/begovel-naprokat-minsk',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'begovel-naprokat-minsk'],
        ],

        // --- Коляски ---
        [
            'id'          => 4,
            'name'        => 'Прокат колясок',
            'url'         => '/ru/prokat-detskih-tovarov/kolyaski-detskie/',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'sub_razdel', 'value' => 'kolyaski-detskie'],
        ],

        // --- Автокресла ---
        [
            'id'          => 5,
            'name'        => 'Прокат автокресел',
            'url'         => '/ru/prokat-detskih-tovarov/autokresla/',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'sub_razdel', 'value' => 'autokresla'],
        ],

        // --- Детская мебель и снаряжение ---
        [
            'id'          => 6,
            'name'        => 'Прокат ходунков',
            'url'         => '/ru/prokat-detskih-tovarov/detskaya-komnata/hodunki',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'hodunki'],
        ],
        [
            'id'          => 7,
            'name'        => 'Прокат шезлонгов для малышей',
            'url'         => '/ru/prokat-detskih-tovarov/detskaya-komnata/shezlongi',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'shezlongi'],
        ],
        [
            'id'          => 8,
            'name'        => 'Прокат стульчиков для кормления',
            'url'         => '/ru/prokat-detskih-tovarov/detskaya-komnata/stulchiki',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'stulchiki'],
        ],
        [
            'id'          => 9,
            'name'        => 'Прокат колыбелей-качелей',
            'url'         => '/ru/prokat-detskih-tovarov/detskaya-komnata/kolybel-kacheli',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'kolybel-kacheli'],
        ],
        [
            'id'          => 10,
            'name'        => 'Прокат детских манежей',
            'url'         => '/ru/prokat-detskih-tovarov/detskaya-komnata/manezh-krovati',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'manezh-krovati'],
        ],

        // --- Игрушки ---
        [
            'id'          => 11,
            'name'        => 'Прокат прыгунков и качелей',
            'url'         => '/ru/prokat-detskih-tovarov/toys-for-rent/prygunki',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'prygunki'],
        ],
        [
            'id'          => 12,
            'name'        => 'Прокат игрушек',
            'url'         => '/ru/prokat-detskih-tovarov/toys-for-rent/',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'sub_razdel', 'value' => 'toys-for-rent'],
        ],

        // --- Для мам ---
        [
            'id'          => 13,
            'name'        => 'Прокат молокоотсосов',
            'url'         => '/ru/prokat-detskih-tovarov/kupanie-pelenanie/molokootsosy',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'cat', 'value' => 'molokootsosy'],
        ],
        [
            'id'          => 14,
            'name'        => 'Прокат эргорюкзаков и слингов',
            'url'         => '/ru/prokat-detskih-tovarov/ergoryukzaki/',
            'feed_cat_id' => 1,
            'filter'      => ['type' => 'sub_razdel', 'value' => 'ergoryukzaki'],
        ],

        // --- Медицина ---
        [
            'id'          => 15,
            'name'        => 'Прокат ингаляторов',
            'url'         => '/ru/prokat-detskih-tovarov/vesy-baby_monitors/ingalyatory',
            'feed_cat_id' => 3,
            'filter'      => ['type' => 'cat', 'value' => 'ingalyatory'],
        ],
        [
            'id'          => 16,
            'name'        => 'Прокат увлажнителей воздуха',
            'url'         => '/ru/prokat-detskih-tovarov/vesy-baby_monitors/uvlazhniteli-vozduha',
            'feed_cat_id' => 3,
            'filter'      => ['type' => 'cat', 'value' => 'uvlazhniteli-vozduha'],
        ],
        [
            'id'          => 17,
            'name'        => 'Прокат физиотерапевтических приборов',
            'url'         => '/ru/medical-prokat/electroterapiya-prokat/',
            'feed_cat_id' => 3,
            'filter'      => ['type' => 'sub_razdel', 'value' => 'electroterapiya-prokat'],
        ],

        // --- Карнавальные костюмы ---
        [
            'id'          => 18,
            'name'        => 'Прокат детских карнавальных костюмов',
            'url'         => '/ru/karnavalnye-kostyumy/detskie-nariady-prokat/',
            'feed_cat_id' => 2,
            'filter'      => ['type' => 'sub_razdel', 'value' => 'detskie-nariady-prokat'],
        ],
        [
            'id'          => 19,
            'name'        => 'Прокат карнавальных костюмов для взрослых',
            'url'         => '/ru/karnavalnye-kostyumy/carnival-rent-adult/',
            'feed_cat_id' => 2,
            'filter'      => ['type' => 'sub_razdel', 'value' => 'carnival-rent-adult'],
        ],

        // --- Уборка ---
        [
            'id'          => 20,
            'name'        => 'Прокат моющих пылесосов',
            'url'         => '/ru/prokat-uborka/moyushchij-pylesos/moyushchij-pylesos-naprokat-minsk',
            'feed_cat_id' => 4,
            'filter'      => ['type' => 'cat', 'value' => 'moyushchij-pylesos-naprokat-minsk'],
        ],
        [
            'id'          => 21,
            'name'        => 'Прокат пароочистителей',
            'url'         => '/ru/prokat-uborka/moyushchij-pylesos/paroochistitel',
            'feed_cat_id' => 4,
            'filter'      => ['type' => 'cat', 'value' => 'paroochistitel'],
        ],

        // --- Биоптрон ---
        [
            'id'          => 24,
            'name'        => 'Прокат Биоптрон',
            'url'         => '/ru/medical-prokat/bioptron',
            'feed_cat_id' => 3,
            'filter'      => ['type' => 'cat', 'value' => 'prokat-bioptron-minsk'],
        ],

        // --- Спорт и зимний инвентарь ---
        [
            'id'          => 22,
            'name'        => 'Прокат тюбингов и санок',
            'url'         => '/ru/prokat-sports/winter/tyubingi-sanki',
            'feed_cat_id' => 5,
            'filter'      => ['type' => 'cat', 'value' => 'tyubingi-sanki'],
        ],
        [
            'id'          => 23,
            'name'        => 'Спортивный прокат',
            'url'         => '/ru/prokat-sports/',
            'feed_cat_id' => 5,
            'filter'      => ['type' => 'razdel', 'value' => 'prokat-sports'],
        ],
    ];

    public function generate(Request $request): Response
    {
        if ($request->query('refresh') === '1') {
            Cache::forget(self::CACHE_KEY);
        }

        $xml = Cache::get(self::CACHE_KEY);

        if ($xml === null) {
            $xml = $this->buildFeed();
            Cache::put(self::CACHE_KEY, $xml, $this->secondsUntilNextGeneration());
        }

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function buildFeed(): string
    {
        $date  = date('Y-m-d H:i');
        $lines = [];

        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">';
        $lines[] = '<yml_catalog date="' . $date . '">';
        $lines[] = '  <shop>';
        $lines[] = '    <name>TikTak — Прокат детских товаров</name>';
        $lines[] = '    <company>TikTak</company>';
        $lines[] = '    <url>https://tiktak.by</url>';
        $lines[] = '    <currencies>';
        $lines[] = '      <currency id="BYN" rate="1"/>';
        $lines[] = '    </currencies>';

        $lines[] = '    <categories>';
        foreach (self::FEED_CATEGORIES as $catId => $catName) {
            $lines[] = '      <category id="' . $catId . '">' . htmlspecialchars($catName, ENT_XML1) . '</category>';
        }
        $lines[] = '    </categories>';

        $lines[] = '    <offers>';
        foreach (self::SERVICES as $service) {
            $data = $this->queryServiceData($service['filter']);
            if ($data['price'] === null) {
                continue;
            }
            $price = number_format($data['price'], 2, '.', '');

            $description = 'Прокат в Минске от ' . $price . ' руб./неделю. '
                . 'Доставка по Минску. Работаем с 2011 года. '
                . 'Подробнее и полный каталог: tiktak.by';

            $lines[] = '      <offer id="' . $service['id'] . '" available="true">';
            $lines[] = '        <name>' . htmlspecialchars($service['name'], ENT_XML1) . '</name>';
            $lines[] = '        <url>' . htmlspecialchars(self::BASE_URL . $service['url'], ENT_XML1) . '</url>';
            $lines[] = '        <price>' . $price . '</price>';
            $lines[] = '        <currencyId>BYN</currencyId>';
            $lines[] = '        <measure>неделя</measure>';
            $lines[] = '        <categoryId>' . $service['feed_cat_id'] . '</categoryId>';
            if (!empty($data['photo'])) {
                $lines[] = '        <picture>' . htmlspecialchars(self::BASE_URL . $data['photo'], ENT_XML1) . '</picture>';
            }
            $lines[] = '        <description>' . htmlspecialchars($description, ENT_XML1) . '</description>';
            $lines[] = '      </offer>';
        }
        $lines[] = '    </offers>';
        $lines[] = '  </shop>';
        $lines[] = '</yml_catalog>';

        return implode("\n", $lines) . "\n";
    }

    private function queryServiceData(array $filter): array
    {
        [$joinSql, $whereSql, $bindings] = $this->buildFilterClauses($filter);

        $tariffRows = DB::select("
            SELECT
                t.model_id,
                CASE
                    WHEN t.step = 'day'   THEN t.kol_vo
                    WHEN t.step = 'week'  THEN 7  * t.kol_vo
                    WHEN t.step = 'month' THEN 30 * t.kol_vo
                    ELSE 0
                END AS tier_days,
                CASE
                    WHEN t.step = 'day'   THEN t.rent_amount / NULLIF(t.kol_vo, 0)
                    WHEN t.step = 'week'  THEN t.rent_amount / NULLIF(7  * t.kol_vo, 0)
                    WHEN t.step = 'month' THEN t.rent_amount / NULLIF(30 * t.kol_vo, 0)
                    ELSE NULL
                END AS daily_rate
            FROM rent_tarif_act t
            JOIN tovar_rent tr ON tr.tovar_rent_id = t.model_id
            JOIN rent_model_web rmw ON rmw.model_id = tr.tovar_rent_id
                AND rmw.lang = 'ru' AND rmw.status = 'show'
            {$joinSql}
            WHERE {$whereSql}
              AND EXISTS (
                  SELECT 1 FROM tovar_rent_items tri
                  WHERE tri.model_id = tr.tovar_rent_id
              )
            ORDER BY t.model_id, tier_days ASC
        ", $bindings);

        if (empty($tariffRows)) {
            return ['price' => null, 'photo' => null];
        }

        $byModel = [];
        foreach ($tariffRows as $row) {
            $byModel[$row->model_id][] = [
                'tier_days'  => (int)$row->tier_days,
                'daily_rate' => (float)$row->daily_rate,
            ];
        }

        $minPrice = null;
        foreach ($byModel as $tiers) {
            $price = $this->getPriceForWeek($tiers);
            if ($price !== null && ($minPrice === null || $price < $minPrice)) {
                $minPrice = $price;
            }
        }

        $photoRows = DB::select("
            SELECT rmw.l2_pic
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON tr.tovar_rent_id = rmw.model_id
            {$joinSql}
            WHERE {$whereSql}
              AND rmw.lang = 'ru' AND rmw.status = 'show'
              AND rmw.l2_pic IS NOT NULL AND rmw.l2_pic != ''
              AND EXISTS (
                  SELECT 1 FROM tovar_rent_items tri
                  WHERE tri.model_id = tr.tovar_rent_id
              )
            LIMIT 1
        ", $bindings);

        return [
            'price' => $minPrice,
            'photo' => !empty($photoRows) ? $photoRows[0]->l2_pic : null,
        ];
    }

    private function buildFilterClauses(array $filter): array
    {
        $type  = $filter['type'];
        $value = $filter['value'];

        if ($type === 'cat') {
            return [
                'JOIN tovar_rent_cat trc ON trc.tovar_rent_cat_id = tr.tovar_rent_cat_id',
                'trc.cat_url_key = ?',
                [$value],
            ];
        }

        if ($type === 'sub_razdel') {
            return [
                'JOIN tovar_rent_cat trc ON trc.tovar_rent_cat_id = tr.tovar_rent_cat_id
                 JOIN sub_razdel sr ON sr.id_sub_razdel = trc.main_sub_razdel_id',
                'sr.url_sub_razdel_name = ?',
                [$value],
            ];
        }

        // type === 'razdel'
        return [
            'JOIN tovar_rent_cat trc ON trc.tovar_rent_cat_id = tr.tovar_rent_cat_id
             JOIN sub_razdel sr ON sr.id_sub_razdel = trc.main_sub_razdel_id
             JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
             JOIN razdel r ON r.id_razdel = rs.id_razdel',
            'r.url_razdel_name = ?',
            [$value],
        ];
    }

    private function getPriceForWeek(array $tiers): ?float
    {
        $tiers = array_values(array_filter($tiers, fn($t) => $t['tier_days'] > 0 && $t['daily_rate'] > 0));
        if (empty($tiers)) {
            return null;
        }

        // Mirrors bb/classes/TariffModel::getAmmountForDaysPeriod(7)
        $tarifPerDay = $tiers[0]['daily_rate'];
        foreach ($tiers as $tier) {
            if (7 >= $tier['tier_days']) {
                $tarifPerDay = $tier['daily_rate'];
            }
        }

        return round(7 * $tarifPerDay, 2);
    }

    private function secondsUntilNextGeneration(): int
    {
        $next = strtotime('tomorrow 03:00:00');
        if (strtotime('today 03:00:00') > time()) {
            $next = strtotime('today 03:00:00');
        }
        return max(3600, $next - time());
    }
}
