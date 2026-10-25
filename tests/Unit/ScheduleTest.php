<?php

namespace Tests\Unit;

use App\MyClasses\KBForm;
use bb\KBron;
use bb\Schedule;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Часы работы офиса, по которым считаются брони карнавальных костюмов —
 * и на сайте (выпадающий список «время» в форме брони), и в админке.
 *
 * Должны совпадать с тем, что сайт обещает клиенту в футере:
 * «10.00 - 19.00, сб, вс: 10.00 - 15.00». Второе число в Schedule::$schedule —
 * час закрытия, он сам ещё доступен для выдачи/возврата.
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
}
