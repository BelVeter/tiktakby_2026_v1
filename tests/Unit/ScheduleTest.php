<?php

namespace Tests\Unit;

use App\MyClasses\KBForm;
use bb\classes\SalonHours;
use bb\KBron;
use bb\Schedule;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Часы работы офиса, по которым считаются брони карнавальных костюмов —
 * и на сайте (выпадающий список «время» в форме брони), и в админке.
 *
 * Должны совпадать с bb\classes\SalonHours — единственным источником часов салона
 * (попап «Наш салон проката», LocalBusiness, футер «сб, вс: 10.00 - 15.00»).
 * Второе число в Schedule::$schedule — час закрытия, он сам ещё доступен для
 * выдачи/возврата.
 *
 * Неделя фиксированная: 2026-09-21 — понедельник.
 */
class ScheduleTest extends TestCase
{
    public function test_weekdays_are_10_to_19(): void
    {
        foreach (['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $d) {
            $day = new DateTime($d);
            $this->assertSame(10, Schedule::getOpenHour($day), $d);
            $this->assertSame(19, Schedule::getCloseHour($day), $d);
        }
    }

    public function test_weekend_closes_at_15(): void
    {
        foreach (['2026-09-26', '2026-09-27'] as $d) {
            $day = new DateTime($d);
            $this->assertSame(10, Schedule::getOpenHour($day), $d);
            $this->assertSame(15, Schedule::getCloseHour($day), $d);
            $this->assertFalse(Schedule::isWorkingTime(new DateTime($d . ' 16:00')), $d);
        }
    }

    public function test_every_day_matches_salon_hours(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $day = new DateTime('2026-09-21 +' . $i . ' days');
            $d = $day->format('D');
            $this->assertSame((int) substr(SalonHours::openTime($day), 0, 2), Schedule::getOpenHour($day), $d);
            $this->assertSame((int) substr(SalonHours::closeTime($day), 0, 2), Schedule::getCloseHour($day), $d);
        }
    }

    public function test_site_booking_form_offers_no_hours_after_15_on_saturday(): void
    {
        $free = new KBron();
        $free->from_kb = new DateTime('2026-09-25 10:00');
        $free->to_kb = new DateTime('2026-09-29 19:00');

        $this->assertSame(
            [10, 11, 12, 13, 14, 15],
            KBForm::getWorkingHoursArray(new DateTime('2026-09-26'), $free)
        );
    }

    public function test_schedule_inputs_for_site_js_use_weekend_close_hour(): void
    {
        $rows = Schedule::getDateOpenCloseHoursArrayForPreiod(new DateTime('2026-09-25'), new DateTime('2026-09-27'));

        $this->assertSame([
            ['2026-09-25', 10, 19],
            ['2026-09-26', 10, 15],
            ['2026-09-27', 10, 15],
        ], $rows);
    }

    /**
     * Свободное окно, где час конца меньше часа начала (типично — после чужой брони
     * с 16:00 до выходного 15:00). Раньше цикл шёл по дням со временем начала окна,
     * 25.11 16:00 > 25.11 15:00 — и последний день не попадал в .schedule, хотя
     * KBForm::getToDaysArray() предлагал его как дату возврата. На сайте timeChange()
     * не находил .schedule[data-date] и падал, список «время» оставался пустым.
     */
    public function test_last_day_of_window_is_included_when_end_hour_is_before_start_hour(): void
    {
        // 2026-11-20 — пятница
        $rows = Schedule::getDateOpenCloseHoursArrayForPreiod(new DateTime('2026-11-20 16:00'), new DateTime('2026-11-22 15:00'));

        $this->assertSame([
            ['2026-11-20', 10, 19],
            ['2026-11-21', 10, 15],
            ['2026-11-22', 10, 15],
        ], $rows);
    }

    public function test_multi_day_window_covers_every_calendar_day(): void
    {
        $rows = Schedule::getDateOpenCloseHoursArrayForPreiod(new DateTime('2026-11-20 15:00'), new DateTime('2026-11-25 10:00'));

        $this->assertSame(
            ['2026-11-20', '2026-11-21', '2026-11-22', '2026-11-23', '2026-11-24', '2026-11-25'],
            array_column($rows, 0)
        );
    }

    /**
     * Перевёрнутый интервал внутри одного дня — по-прежнему ошибка, а не «один день»:
     * проверка $from > $to идёт до обнуления времени.
     */
    public function test_inverted_window_within_one_day_returns_false(): void
    {
        $this->assertFalse(
            Schedule::getDateOpenCloseHoursArrayForPreiod(new DateTime('2026-11-20 15:00'), new DateTime('2026-11-20 10:00'))
        );
    }

    public function test_source_dates_are_not_modified(): void
    {
        $from = new DateTime('2026-11-20 16:00');
        $to = new DateTime('2026-11-22 15:00');

        Schedule::getDateOpenCloseHoursArrayForPreiod($from, $to);

        $this->assertSame('2026-11-20 16:00', $from->format('Y-m-d H:i'));
        $this->assertSame('2026-11-22 15:00', $to->format('Y-m-d H:i'));
    }
}
