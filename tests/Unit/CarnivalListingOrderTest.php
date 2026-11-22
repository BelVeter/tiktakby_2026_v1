<?php

namespace Tests\Unit;

use App\MyClasses\Carnival\ListingOrder;
use PHPUnit\Framework\TestCase;

/**
 * Порядок карточек карнавального листинга (docs/carnival_2026_plan.md, п. 3–4).
 *
 * Было: ручной rent_model_web.sort_n, которому ~10 лет — 28 закреплённых наверху моделей
 * оказались платьями/снежинками/снегурочками, а хиты (мушкетёры, пираты, снеговики) уходили вниз.
 * Стало: рейтинг спроса (Carnival\DemandRanking) + на листинге без фильтра мягкий баланс
 * мальчики/девочки и разнообразие категорий; аксессуары ниже полноценных костюмов.
 */
class CarnivalListingOrderTest extends TestCase
{
    private function item(int $id, float $score, string $gender, int $cat, bool $acc = false, int $annual = 0): array
    {
        return ['id' => $id, 'score' => $score, 'annual' => $annual, 'gender' => $gender, 'cat' => $cat, 'acc' => $acc];
    }

    public function test_filter_mode_is_pure_demand_rating(): void
    {
        $items = [
            $this->item(1, 1, 'm', 10),
            $this->item(2, 30, 'm', 10),
            $this->item(3, 20, 'm', 10),
            $this->item(4, 5, 'f', 20),
        ];

        $this->assertSame([2, 3, 4, 1], ListingOrder::order($items, false));
    }

    public function test_ties_broken_by_annual_demand_then_newer_model(): void
    {
        $items = [
            $this->item(5, 0, 'm', 10, false, 0),
            $this->item(7, 0, 'm', 11, false, 0),
            $this->item(6, 0, 'm', 12, false, 4),
        ];

        $this->assertSame([6, 7, 5], ListingOrder::order($items, false));
    }

    public function test_accessories_go_below_costumes_in_both_modes(): void
    {
        $items = [
            $this->item(1, 50, 'u', 10, true),   // валенки с большим спросом
            $this->item(2, 3, 'm', 11),
            $this->item(3, 1, 'f', 12),
        ];

        foreach ([true, false] as $diversify) {
            $order = ListingOrder::order($items, $diversify);
            $this->assertSame(1, end($order), $diversify ? 'без фильтра' : 'с фильтром');
        }
    }

    public function test_boys_heavy_demand_is_softly_balanced_with_girls(): void
    {
        $items = [];
        for ($i = 1; $i <= 12; $i++) {
            $items[] = $this->item($i, 100 - $i, 'm', $i);        // 12 мальчиковых хитов разных категорий
        }
        for ($i = 13; $i <= 24; $i++) {
            $items[] = $this->item($i, 30 - $i, 'f', $i);         // девочковые со спросом ниже
        }

        $first12 = array_slice(ListingOrder::order($items, true), 0, 12);
        $boys = count(array_filter($first12, function ($id) { return $id <= 12; }));

        $this->assertGreaterThanOrEqual(5, $boys);
        $this->assertLessThanOrEqual(7, $boys, 'на первом экране не должно быть «только мальчики»');
    }

    public function test_same_category_does_not_clump_at_the_top(): void
    {
        $items = [
            $this->item(1, 32, 'm', 155), $this->item(2, 31, 'm', 155), $this->item(3, 30, 'm', 155), // мушкетёры
            $this->item(4, 10, 'f', 61), $this->item(5, 9, 'u', 164), $this->item(6, 8, 'f', 165),
            $this->item(7, 7, 'm', 166), $this->item(8, 6, 'f', 145),
        ];

        $order = ListingOrder::order($items, true);
        $first4 = array_slice($order, 0, 4);
        $this->assertSame(1, $order[0], 'главный хит — первым');
        $this->assertCount(1, array_intersect($first4, [1, 2, 3]), 'мушкетёры не идут кучей в первых четырёх');
    }

    public function test_single_category_single_gender_page_keeps_demand_order(): void
    {
        // Страница категории «Снегурочки»: только девочки одной категории — ограничения ослабляются.
        $items = [$this->item(1, 2, 'f', 165), $this->item(2, 9, 'f', 165), $this->item(3, 5, 'f', 165)];

        $this->assertSame([2, 3, 1], ListingOrder::order($items, true));
    }

    public function test_unisex_never_breaks_balance(): void
    {
        $items = [
            $this->item(1, 10, 'u', 1), $this->item(2, 9, 'u', 2), $this->item(3, 8, 'u', 3), $this->item(4, 1, 'f', 4),
        ];

        $this->assertSame([1, 2, 3, 4], ListingOrder::order($items, true));
    }

    public function test_order_is_deterministic_and_complete(): void
    {
        $items = [];
        for ($i = 1; $i <= 60; $i++) {
            $items[] = $this->item($i, $i % 7, ['m', 'f', 'u'][$i % 3], $i % 9, $i % 11 === 0, $i % 4);
        }
        $shuffled = $items;
        shuffle($shuffled);

        $a = ListingOrder::order($items, true);
        $this->assertSame($a, ListingOrder::order($shuffled, true), 'порядок не зависит от порядка входа (стабильная пагинация)');
        $this->assertSame(range(1, 60), (function ($x) { sort($x); return $x; })($a), 'ни одна карточка не потеряна и не задвоена');
    }
}
