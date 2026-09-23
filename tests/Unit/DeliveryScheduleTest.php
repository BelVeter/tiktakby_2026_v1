<?php

namespace Tests\Unit;

use bb\classes\DeliverySchedule;
use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Что карточка товара обещает клиенту в строке «Доставка: ...».
 *
 * Свой курьер ездит только пн–пт, отсечка — 17:00, а сотрудник может снять
 * сегодняшний выезд вручную («курьер уже уехал»). Всё считается от момента,
 * который передаёт тест, поэтому результат не зависит от дня недели, в который
 * запущен набор, и никакой БД не нужно: флаг переноса передаётся явно.
 *
 * Неделя фиксированная: 2026-09-21 — понедельник.
 */
class DeliveryScheduleTest extends TestCase
{
    private const MON = '2026-09-21';
    private const TUE = '2026-09-22';
    private const WED = '2026-09-23';
    private const THU = '2026-09-24';
    private const FRI = '2026-09-25';
    private const SAT = '2026-09-26';
    private const SUN = '2026-09-27';
    private const NEXT_MON = '2026-09-28';

    private function moment(string $day, string $time): DateTime
    {
        return new DateTime($day . ' ' . $time, new DateTimeZone('Europe/Minsk'));
    }

    /**
     * Опечатка в дате ломала бы все остальные тесты молча: «пятница» оказалась бы
     * субботой, а ожидания всё равно бы совпали с логикой, написанной под неё.
     */
    public function test_reference_week_runs_monday_to_sunday(): void
    {
        $days = [self::MON, self::TUE, self::WED, self::THU, self::FRI, self::SAT, self::SUN, self::NEXT_MON];

        foreach ($days as $i => $day) {
            $this->assertSame($i % 7 + 1, (int) $this->moment($day, '12:00')->format('N'), $day);
        }
    }

    // ─── строка «Доставка: ...» по расписанию (флаг не тронут) ───────────────

    /**
     * @dataProvider scheduleProvider
     */
    public function test_text_follows_the_schedule(string $day, string $time, string $expected): void
    {
        $this->assertSame($expected, DeliverySchedule::text($this->moment($day, $time), false));
    }

    public function scheduleProvider(): array
    {
        return [
            'понедельник, сразу после полуночи'    => [self::MON, '00:00', 'сегодня'],
            'вторник утром'                        => [self::TUE, '09:00', 'сегодня'],
            'среда, за минуту до отсечки'          => [self::WED, '16:59', 'сегодня'],
            'среда, ровно на отсечке'              => [self::WED, '17:00', 'завтра'],
            'четверг вечером — завтра пятница'     => [self::THU, '20:00', 'завтра'],
            'пятница утром'                        => [self::FRI, '10:00', 'сегодня'],
            'пятница, за минуту до отсечки'        => [self::FRI, '16:59', 'сегодня'],
            'пятница после отсечки — не завтра'    => [self::FRI, '17:00', 'в понедельник'],
            'пятница поздно вечером'               => [self::FRI, '23:59', 'в понедельник'],
            'суббота утром'                        => [self::SAT, '09:00', 'в понедельник'],
            'суббота вечером'                      => [self::SAT, '20:00', 'в понедельник'],
            'воскресенье: завтра — не «завтра»'    => [self::SUN, '12:00', 'в понедельник'],
        ];
    }

    // ─── «курьер уже уехал» ───────────────────────────────────────────────────

    /**
     * @dataProvider movedProvider
     */
    public function test_text_when_staff_marked_the_courier_as_gone(string $day, string $time, string $expected): void
    {
        $this->assertSame($expected, DeliverySchedule::text($this->moment($day, $time), true));
    }

    public function movedProvider(): array
    {
        return [
            'понедельник до отсечки → завтра'   => [self::MON, '10:00', 'завтра'],
            'четверг до отсечки → завтра'       => [self::THU, '10:00', 'завтра'],
            'пятница до отсечки → понедельник'  => [self::FRI, '10:00', 'в понедельник'],
            'после отсечки флаг ничего не меняет' => [self::WED, '18:00', 'завтра'],
            'суббота — флаг ничего не меняет'   => [self::SAT, '10:00', 'в понедельник'],
            'воскресенье — флаг ничего не меняет' => [self::SUN, '10:00', 'в понедельник'],
        ];
    }

    // ─── ближайший день выезда ────────────────────────────────────────────────

    /**
     * @dataProvider courierDayProvider
     */
    public function test_courier_day_is_the_next_working_day(string $day, string $time, bool $moved, string $expectedDate): void
    {
        $courierDay = DeliverySchedule::courierDay($this->moment($day, $time), $moved);

        $this->assertSame($expectedDate, $courierDay->format('Y-m-d'));
        $this->assertSame('00:00:00', $courierDay->format('H:i:s'), 'Возвращается полночь дня, а не момент.');
    }

    public function courierDayProvider(): array
    {
        return [
            'будний день до отсечки'          => [self::TUE, '09:00', false, self::TUE],
            'будний день после отсечки'       => [self::TUE, '17:00', false, self::WED],
            'будний день, курьер уехал'       => [self::TUE, '09:00', true, self::WED],
            'пятница после отсечки'           => [self::FRI, '18:00', false, self::NEXT_MON],
            'пятница, курьер уехал'           => [self::FRI, '09:00', true, self::NEXT_MON],
            'суббота'                         => [self::SAT, '09:00', false, self::NEXT_MON],
            'воскресенье'                     => [self::SUN, '09:00', false, self::NEXT_MON],
        ];
    }

    public function test_courier_day_does_not_mutate_the_given_moment(): void
    {
        $now = $this->moment(self::FRI, '18:30');

        DeliverySchedule::courierDay($now, false);
        DeliverySchedule::text($now, false);

        $this->assertSame(self::FRI . ' 18:30', $now->format('Y-m-d H:i'));
    }

    // ─── кирпичики ────────────────────────────────────────────────────────────

    public function test_courier_works_monday_to_friday_only(): void
    {
        foreach ([self::MON, self::TUE, self::WED, self::THU, self::FRI] as $day) {
            $this->assertTrue(DeliverySchedule::isCourierWorkingDay($this->moment($day, '12:00')), $day);
        }

        foreach ([self::SAT, self::SUN] as $day) {
            $this->assertFalse(DeliverySchedule::isCourierWorkingDay($this->moment($day, '12:00')), $day);
        }
    }

    public function test_cutoff_is_17_00_and_the_boundary_belongs_to_tomorrow(): void
    {
        $this->assertTrue(DeliverySchedule::isBeforeCutoff($this->moment(self::WED, '16:59')));
        $this->assertFalse(DeliverySchedule::isBeforeCutoff($this->moment(self::WED, '17:00')));
        $this->assertSame('17:00', DeliverySchedule::cutoffTime());
    }
}
