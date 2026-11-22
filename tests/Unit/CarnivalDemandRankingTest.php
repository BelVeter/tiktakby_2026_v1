<?php

namespace Tests\Unit;

use App\MyClasses\Carnival\DemandRanking;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Рейтинг спроса карнавальных моделей — чистые функции (без БД).
 *
 * Окно смотрит ВПЕРЁД: [сегодня; +45 дней) прошлых лет. В конце ноября реальных выдач ещё мало
 * (пик выдач 18–31 декабря), но бронируют уже на декабрь — поэтому витрина должна показывать
 * декабрьский спрос, а в октябре — Хэллоуин.
 */
class CarnivalDemandRankingTest extends TestCase
{
    private function day(string $d): DateTimeImmutable
    {
        return new DateTimeImmutable($d, new DateTimeZone('Europe/Minsk'));
    }

    public function test_windows_look_forward_45_days_one_and_two_years_back(): void
    {
        $w = DemandRanking::windows($this->day('2026-11-22'));

        $this->assertCount(2, $w);
        $this->assertSame($this->day('2025-11-22')->getTimestamp(), $w[0][0]);
        $this->assertSame($this->day('2026-01-06')->getTimestamp(), $w[0][1]);
        $this->assertSame($this->day('2024-11-22')->getTimestamp(), $w[1][0]);
        $this->assertSame($this->day('2025-01-06')->getTimestamp(), $w[1][1]);
    }

    public function test_score_is_average_per_year_over_years_the_model_existed(): void
    {
        $starts = [$this->day('2025-11-22')->getTimestamp(), $this->day('2024-11-22')->getTimestamp()];

        // старая модель (buy_date = 0 — неизвестно, значит давно): среднее за 2 года
        $this->assertSame(7.0, DemandRanking::historyScore([10, 4], 0, $starts));
        // куплена летом 2025: прошлый год считается, позапрошлый — нет (не делим её спрос на 2)
        $this->assertSame(6.0, DemandRanking::historyScore([6, 0], $this->day('2025-07-01')->getTimestamp(), $starts));
        // старая модель без прокатов в окне — честный 0, а не «новинка»
        $this->assertSame(0.0, DemandRanking::historyScore([0, 0], 0, $starts));
    }

    public function test_model_bought_after_last_season_is_new(): void
    {
        $starts = [$this->day('2025-11-22')->getTimestamp(), $this->day('2024-11-22')->getTimestamp()];

        $this->assertNull(DemandRanking::historyScore([0, 0], $this->day('2026-03-10')->getTimestamp(), $starts));
        $this->assertNull(DemandRanking::historyScore([0, 0], null, $starts), 'без экземпляров — тоже новинка');
    }

    public function test_rentals_in_window_count_even_if_buy_date_is_later(): void
    {
        // buy_date внесли позже реальной покупки, а прокаты в окне есть — данные важнее даты
        $starts = [$this->day('2025-11-22')->getTimestamp(), $this->day('2024-11-22')->getTimestamp()];

        $this->assertSame(3.0, DemandRanking::historyScore([3, 0], $this->day('2026-02-01')->getTimestamp(), $starts));
    }

    public function test_gender_from_model_then_items_majority(): void
    {
        $this->assertSame('m', DemandRanking::gender('m', ['f' => 3]));
        $this->assertSame('f', DemandRanking::gender('0', ['f' => 2, 'm' => 1]));
        $this->assertSame('u', DemandRanking::gender('', ['f' => 1, 'm' => 1]));
        $this->assertSame('u', DemandRanking::gender('', []));
    }

    public function test_accessories_detected_by_name_and_category_type(): void
    {
        foreach ([
            'Валенки',
            'Акссесуар &quot;Крылья Ангела&quot; ',
            'Карнавальный аксессуар Маска "Клоун"  one size',
            'Кокошник "Зимушка"',
            '"Колпак Эльфа" ',
            '"Шляпа-цилиндр" аксессуар к карнавальному костюму',
            'Корона короля золотая регулируемая (кресты)',
            'Шарф, галстук палочка и очки Гарри Поттера',
            'Ковбойская шляпа',
            'Аксессуар "Скипетр" ',
            'Шляпа мушкетера красная',
            // «л» в UTF-8 — D0 BB, «»» — C2 BB: побайтовый trim() откусывал хвост буквы и ломал строку
            'Маска Кот Базил',
            'Корона «Ангел»',
            "\u{00A0}Колпак Звездочёт",
        ] as $name) {
            $this->assertTrue(DemandRanking::isAccessory($name, 'Карнавальный костюм'), $name);
        }

        foreach ([
            '"Снежинка-1" новогодний костюм для девочки 4-5 лет',
            'Платье + шляпка "Ноэль" 10-12 лет',
            '"Кот в сапогах - 2" костюм карнавальный',
            '"Гарри Поттер" накидка для мальчика 7-10 лет',
            '"Король-2" маскарадный костюм для мальчика',
            '"Королева" наряд для девочки маскарадный',
        ] as $name) {
            $this->assertFalse(DemandRanking::isAccessory($name, 'Карнавальный костюм'), $name);
        }

        $this->assertTrue(DemandRanking::isAccessory('Парик Мальвины', 'Аксессуар'), 'категория-аксессуар');
    }

    public function test_filter_is_active_only_for_real_gender_or_height(): void
    {
        $this->assertFalse(DemandRanking::isFilterActive(false));
        $this->assertFalse(DemandRanking::isFilterActive(['gender' => null, 'rost' => null, 'eventDate' => null]));
        $this->assertFalse(DemandRanking::isFilterActive(['gender' => 'all', 'rost' => '']));
        $this->assertTrue(DemandRanking::isFilterActive(['gender' => 'm', 'rost' => '']));
        $this->assertTrue(DemandRanking::isFilterActive(['gender' => 'all', 'rost' => '116']));
    }

    public function test_listing_rows_keep_their_shape_and_unknown_models_go_last(): void
    {
        $map = [
            10 => ['score' => 5.0, 'annual' => 1, 'gender' => 'm', 'cat' => 1, 'acc' => false],
            20 => ['score' => 9.0, 'annual' => 1, 'gender' => 'f', 'cat' => 2, 'acc' => false],
        ];
        $rows = [[10, '3', '-200'], [99, '1', '-500'], [20, '0', '0']];

        $this->assertSame(
            [[20, '0', '0'], [10, '3', '-200'], [99, '1', '-500']],
            DemandRanking::sortListing($rows, ['gender' => null, 'rost' => null], $map)
        );
    }
}
