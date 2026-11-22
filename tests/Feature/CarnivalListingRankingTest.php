<?php

namespace Tests\Feature;

use App\MyClasses\Carnival\DemandRanking;
use App\MyClasses\Carnival\ListingOrder;
use App\MyClasses\MainPage;
use bb\classes\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Карнавальные листинги на dev-БД (копии прода): порядок по спросу, а не по ручному sort_n.
 *
 * Было (22.11.2026): на 1-й странице /ru/karnavalnye-kostyumy 18 платьев подряд, 3 снежинки,
 * 3 снегурочки и 0 мальчиков — так были закреплены sort_n лет 10 назад; при ?gender=m&rost=116
 * первыми шли «Помидор», «Баклажан», «Лимон». Проверки не привязаны к сезону: баланс полов,
 * разнообразие категорий, аксессуары ниже костюмов, стабильная пагинация, кэш карты.
 */
class CarnivalListingRankingTest extends TestCase
{
    private const RAZDEL = 'karnavalnye-kostyumy';

    private function pageModelIds(int $page, $filter = false): array
    {
        $p = MainPage::getRazdelPageForWeb('ru', self::RAZDEL, $page, $filter);
        if (!$p || $p->getModelsNum() === 0) {
            $this->markTestSkipped('В dev-БД нет карнавального раздела с моделями.');
        }

        return array_map(function ($m) {
            return (int) $m->getModelId();
        }, $p->getModels());
    }

    public function test_first_page_is_balanced_diverse_and_without_accessories(): void
    {
        $ids = $this->pageModelIds(1);
        $map = DemandRanking::map();

        $boys = $girls = $acc = 0;
        $cats = [];
        foreach ($ids as $id) {
            $m = $map[$id];
            $boys += $m['gender'] === 'm';
            $girls += $m['gender'] === 'f';
            $acc += $m['acc'];
            $cats[$m['cat']] = true;
        }

        $this->assertSame(0, $acc, 'аксессуары (валенки, шляпы, короны) — ниже полноценных костюмов');
        $this->assertLessThanOrEqual(ListingOrder::GENDER_TOLERANCE, abs($boys - $girls), "мальчиков $boys, девочек $girls");
        $this->assertGreaterThanOrEqual(12, $boys + $girls);
        $this->assertGreaterThanOrEqual(10, count($cats), 'одна категория не забирает первый экран');
    }

    public function test_pages_do_not_overlap(): void
    {
        $first = $this->pageModelIds(1);
        $second = $this->pageModelIds(2);

        $this->assertCount(24, $first);
        $this->assertSame([], array_values(array_intersect($first, $second)), 'карточки не дублируются между страницами');
    }

    public function test_filtered_listing_is_pure_demand_order_with_accessories_last(): void
    {
        $ids = $this->pageModelIds(1, ['gender' => 'm', 'rost' => '116', 'eventDate' => null]);
        $map = DemandRanking::map();

        $prev = INF;
        $seenAccessory = false;
        foreach ($ids as $id) {
            if ($map[$id]['acc']) {
                $seenAccessory = true;
                continue;
            }
            $this->assertFalse($seenAccessory, 'костюм после аксессуара');
            $this->assertLessThanOrEqual($prev, $map[$id]['score'], 'с фильтром — чистый рейтинг спроса');
            $prev = $map[$id]['score'];
        }
    }

    public function test_no_subsection_puts_a_model_without_demand_above_a_hit(): void
    {
        // Ревью 22.11.2026: в подразделах с 2–3 категориями или одним полом баланс и разнообразие вытаскивали
        // модели с 0 прокатов на 3–8-е места («Мажоретка» — 3-я в «Воинах», «Помидор» выше «Волка Прохора»).
        $subs = DB::select('SELECT s.url_sub_razdel_name AS url FROM razdel r
            JOIN razdel_subrazdel rs ON rs.id_razdel = r.id_razdel
            JOIN sub_razdel s ON s.id_sub_razdel = rs.id_sub_razdel
            WHERE r.url_razdel_name = ?', [self::RAZDEL]);
        if (!$subs) {
            $this->markTestSkipped('В dev-БД нет подразделов карнавала.');
        }
        $map = DemandRanking::map();

        foreach ($subs as $s) {
            $seenZero = null;
            foreach (DemandRanking::sortListing(Model::getModelIdsArrayByRazdelAndSubRazdelNames(self::RAZDEL, $s->url, false), false, $map) as $row) {
                $m = $map[(int) $row[0]] ?? null;
                if (!$m || $m['acc']) {
                    continue;   // аксессуары — отдельным блоком после всех костюмов
                }
                if ($m['score'] <= 0) {
                    $seenZero = $seenZero ?? (int) $row[0];
                    continue;
                }
                $this->assertNull($seenZero, "{$s->url}: модель {$row[0]} со спросом ниже модели $seenZero без спроса");
            }
        }
    }

    public function test_ranking_map_is_cached_for_the_day(): void
    {
        Cache::flush();
        DemandRanking::map();

        DB::flushQueryLog();
        DB::enableQueryLog();
        DemandRanking::map();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries, 'тяжёлый запрос по архиву сделок — раз в сутки, не на каждое открытие');
    }

    public function test_next_day_rebuilds_the_map_under_the_same_key(): void
    {
        Cache::flush();
        $today = new \DateTimeImmutable('today');
        DemandRanking::map($today);

        DB::flushQueryLog();
        DB::enableQueryLog();
        DemandRanking::map($today->modify('+1 day'));
        $rebuilt = count(DB::getQueryLog()) > 0;
        DB::disableQueryLog();

        $this->assertTrue($rebuilt, 'на следующий день карта пересчитывается (окно сдвигается)');
        $this->assertSame(
            $today->modify('+1 day')->format('Y-m-d'),
            Cache::get('carnival_demand_ranking_v2')['day'],
            'один ключ перезаписывается, а не копия карты на каждый день'
        );
    }
}
