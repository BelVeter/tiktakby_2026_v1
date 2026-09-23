<?php

namespace Tests\Unit;

use bb\classes\SalonHours;
use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Часы работы салона — единственный источник для попапа «Наш салон проката»,
 * микроразметки LocalBusiness и строки «Яндекс-доставка сегодня».
 *
 * Неделя фиксированная: 2026-09-23 — среда, 2026-09-26 — суббота.
 */
class SalonHoursTest extends TestCase
{
    private const WEEKDAY = '2026-09-23';
    private const SATURDAY = '2026-09-26';
    private const SUNDAY = '2026-09-27';

    private function moment(string $day, string $time): DateTime
    {
        return new DateTime($day . ' ' . $time, new DateTimeZone('Europe/Minsk'));
    }

    // ─── открыт ли салон ──────────────────────────────────────────────────────

    /**
     * @dataProvider openProvider
     */
    public function test_is_open_now(string $day, string $time, bool $expected): void
    {
        $this->assertSame($expected, SalonHours::isOpenNow($this->moment($day, $time)));
    }

    public function openProvider(): array
    {
        return [
            'будни: за минуту до открытия'      => [self::WEEKDAY, '09:59', false],
            'будни: ровно в открытие'           => [self::WEEKDAY, '10:00', true],
            'будни: середина дня'               => [self::WEEKDAY, '14:30', true],
            'будни: за минуту до закрытия'      => [self::WEEKDAY, '18:59', true],
            'будни: ровно в закрытие — закрыт'  => [self::WEEKDAY, '19:00', false],
            'будни: ночью'                      => [self::WEEKDAY, '02:00', false],
            'суббота: ровно в открытие'         => [self::SATURDAY, '10:00', true],
            'суббота: за минуту до закрытия'    => [self::SATURDAY, '14:59', true],
            // будничные 19:00 не должны «протекать» на выходной
            'суббота: в 15:00 уже закрыт'       => [self::SATURDAY, '15:00', false],
            'суббота: вечером'                  => [self::SATURDAY, '18:00', false],
            'воскресенье: днём'                 => [self::SUNDAY, '12:00', true],
            'воскресенье: в 15:00 уже закрыт'   => [self::SUNDAY, '15:00', false],
        ];
    }

    public function test_weekend_detection(): void
    {
        $this->assertFalse(SalonHours::isWeekend($this->moment(self::WEEKDAY, '12:00')));
        $this->assertTrue(SalonHours::isWeekend($this->moment(self::SATURDAY, '12:00')));
        $this->assertTrue(SalonHours::isWeekend($this->moment(self::SUNDAY, '12:00')));
    }

    public function test_open_and_close_time_switch_with_the_day_type(): void
    {
        $weekday = $this->moment(self::WEEKDAY, '12:00');
        $saturday = $this->moment(self::SATURDAY, '12:00');

        $this->assertSame(SalonHours::WEEKDAY_OPEN, SalonHours::openTime($weekday));
        $this->assertSame(SalonHours::WEEKDAY_CLOSE, SalonHours::closeTime($weekday));
        $this->assertSame(SalonHours::WEEKEND_OPEN, SalonHours::openTime($saturday));
        $this->assertSame(SalonHours::WEEKEND_CLOSE, SalonHours::closeTime($saturday));
    }

    // ─── что видит посетитель сайта ───────────────────────────────────────────

    public function test_popup_lines(): void
    {
        $this->assertSame('пн–пт: 10:00–19:00', SalonHours::weekdayLine());
        $this->assertSame('сб, вс: 10:00–15:00', SalonHours::weekendLine());
    }

    /**
     * Попап, микроразметка и логика «открыт ли» читают одни константы; если
     * кто-то соберёт разметку руками в другом месте, расхождение поймает этот тест.
     */
    public function test_schema_markup_matches_the_hours_used_by_is_open_now(): void
    {
        $spec = SalonHours::schemaSpecification();

        $this->assertCount(2, $spec);

        list($weekdays, $weekend) = $spec;

        $this->assertSame('OpeningHoursSpecification', $weekdays['@type']);
        $this->assertSame(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], $weekdays['dayOfWeek']);
        $this->assertSame(SalonHours::openTime($this->moment(self::WEEKDAY, '12:00')), $weekdays['opens']);
        $this->assertSame(SalonHours::closeTime($this->moment(self::WEEKDAY, '12:00')), $weekdays['closes']);

        $this->assertSame('OpeningHoursSpecification', $weekend['@type']);
        $this->assertSame(['Saturday', 'Sunday'], $weekend['dayOfWeek']);
        $this->assertSame(SalonHours::openTime($this->moment(self::SATURDAY, '12:00')), $weekend['opens']);
        $this->assertSame(SalonHours::closeTime($this->moment(self::SATURDAY, '12:00')), $weekend['closes']);
    }

    /**
     * layouts/app.blade.php вставляет спецификацию через json_encode прямо в
     * <script type="application/ld+json"> — невалидный JSON выключил бы всю разметку.
     */
    public function test_schema_markup_survives_json_encoding(): void
    {
        $json = json_encode(SalonHours::schemaSpecification(), JSON_UNESCAPED_SLASHES);

        $this->assertNotFalse($json);
        $this->assertSame(SalonHours::schemaSpecification(), json_decode($json, true));
    }

    public function test_now_is_in_the_minsk_timezone(): void
    {
        $this->assertSame('Europe/Minsk', SalonHours::now()->getTimezone()->getName());
    }

    // ─── единый источник правды ───────────────────────────────────────────────

    /**
     * Смысл PR #316 — часы записаны в одном месте. Шаблоны, которые их показывают,
     * обязаны брать их из SalonHours, а не хранить своей копией.
     *
     * @dataProvider templatesShowingHoursProvider
     */
    public function test_templates_render_hours_from_salon_hours_not_a_copy(string $template): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $template);

        $this->assertNotFalse($source, $template);
        $this->assertStringContainsString('SalonHours::', $source, $template . ' должен брать часы из SalonHours.');

        foreach ([SalonHours::WEEKDAY_CLOSE, SalonHours::WEEKEND_CLOSE] as $closing) {
            $this->assertStringNotContainsString(
                $closing,
                $this->withoutBladeComments($source),
                sprintf('%s снова хранит время закрытия %s вручную — правьте bb/classes/SalonHours.php.', $template, $closing)
            );
        }
    }

    public function templatesShowingHoursProvider(): array
    {
        return [
            'попап «Наш салон проката»' => ['resources/views/includes/header.blade.php'],
            'микроразметка LocalBusiness' => ['resources/views/layouts/app.blade.php'],
        ];
    }

    private function withoutBladeComments(string $source): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    }
}
