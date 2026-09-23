<?php

namespace Tests\Feature\Bb;

use bb\classes\DeliverySchedule;
use bb\classes\SalonHours;
use bb\classes\SiteSetting;
use bb\Db;
use DateTime;
use DateTimeZone;
use Tests\TestCase;

/**
 * Переключатель «Курьер уже уехал» на странице курьера и то, что от него видит
 * сайт: флаг в `site_settings`, его самоснятие в полночь и подпись «кто и когда».
 *
 * Флаг хранит ДАТУ, а не «вкл/выкл» — поэтому забытый переключатель не делает
 * сайт «доставка завтра» навсегда. Это главное, что здесь проверяется.
 *
 * Talks to the real MySQL instance (bb\Db ignores Laravel's test database
 * config). Every test runs inside a transaction that is rolled back, so the
 * developer's local `site_settings` is left as it was.
 *
 * Требует применённой миграции 2026_09_22_120000_create_site_settings_table.
 */
class DeliveryToggleTest extends TestCase
{
    private const KEY = SiteSetting::KEY_DELIVERY_NEXT_DAY;

    protected function setUp(): void
    {
        parent::setUp();

        $exists = $this->connection()->query("SHOW TABLES LIKE 'site_settings'")->num_rows > 0;
        if (!$exists) {
            $this->fail('Таблицы site_settings нет — выполните `php artisan migrate`.');
        }

        Db::startTransaction();
        $this->connection()->query("DELETE FROM site_settings WHERE setting_key='" . self::KEY . "'");
        $this->resetDeliveryCaches();
    }

    protected function tearDown(): void
    {
        Db::rollBackTransaction();
        $this->resetDeliveryCaches();

        parent::tearDown();
    }

    // ─── флаг живёт одну дату ─────────────────────────────────────────────────

    public function test_flag_is_off_when_nobody_ever_touched_it(): void
    {
        $this->assertNull(SiteSetting::get(self::KEY));
        $this->assertFalse(SiteSetting::isDeliveryMovedToTomorrow());
    }

    public function test_switching_on_stores_todays_date(): void
    {
        $this->assertTrue(SiteSetting::setDeliveryMovedToTomorrow(true, 'Иванова И.И.'));

        $this->assertSame($this->today(), SiteSetting::get(self::KEY));
        $this->assertTrue(SiteSetting::isDeliveryMovedToTomorrow());
    }

    public function test_switching_off_clears_the_flag(): void
    {
        SiteSetting::setDeliveryMovedToTomorrow(true, 'Иванова И.И.');
        SiteSetting::setDeliveryMovedToTomorrow(false, 'Иванова И.И.');

        $this->assertNull(SiteSetting::get(self::KEY));
        $this->assertFalse(SiteSetting::isDeliveryMovedToTomorrow());
    }

    /**
     * Смена: сотрудник нажал «уехал» вчера и забыл выключить. Сегодня флаг —
     * вчерашняя дата, и сайт обязан вернуться к расписанию без чьих-либо действий.
     */
    public function test_a_flag_left_over_from_yesterday_no_longer_counts(): void
    {
        SiteSetting::set(self::KEY, $this->daysFromToday(-1), 'Иванова И.И.');

        $this->assertFalse(SiteSetting::isDeliveryMovedToTomorrow());
    }

    public function test_setting_the_same_key_twice_keeps_one_row_with_the_latest_author(): void
    {
        SiteSetting::set(self::KEY, $this->today(), 'Первая П.П.');
        SiteSetting::set(self::KEY, $this->today(), 'Вторая В.В.');

        $count = $this->connection()
            ->query("SELECT COUNT(*) FROM site_settings WHERE setting_key='" . self::KEY . "'")
            ->fetch_row()[0];

        $this->assertSame(1, (int) $count);
        $this->assertSame('Вторая В.В.', SiteSetting::getRow(self::KEY)['updated_by']);
    }

    public function test_journal_records_who_and_roughly_when(): void
    {
        SiteSetting::set(self::KEY, $this->today(), 'Петров П.П.');

        $row = SiteSetting::getRow(self::KEY);

        $this->assertSame('Петров П.П.', $row['updated_by']);
        $this->assertLessThan(120, abs(strtotime($row['updated_at']) - time()), 'updated_at — момент записи.');
    }

    /**
     * Имя приходит из сессии, но в SQL оно попадает строкой — кавычки не должны
     * ни ломать запрос, ни выполняться.
     */
    public function test_author_name_with_quotes_is_stored_verbatim_and_harmlessly(): void
    {
        $nasty = "O'Brien \"Bob\"'); DROP TABLE site_settings; --";

        $this->assertTrue(SiteSetting::set(self::KEY, $this->today(), $nasty));

        $this->assertSame($nasty, SiteSetting::getRow(self::KEY)['updated_by']);
        $this->assertTrue($this->connection()->query("SHOW TABLES LIKE 'site_settings'")->num_rows > 0);
    }

    // ─── что видит сайт ───────────────────────────────────────────────────────

    /**
     * Blade зовёт DeliverySchedule::text() без аргументов — флаг тогда читается
     * из БД. Момент подставляем сами: будний день до отсечки, когда без флага
     * обещали бы «сегодня».
     */
    public function test_the_site_promises_tomorrow_once_staff_marked_the_courier_as_gone(): void
    {
        $tuesdayMorning = $this->moment('2026-09-22', '10:00');

        $this->assertSame('сегодня', DeliverySchedule::text($tuesdayMorning));

        DeliverySchedule::setMovedToTomorrow(true, 'Иванова И.И.');
        $this->assertSame('завтра', DeliverySchedule::text($tuesdayMorning));

        DeliverySchedule::setMovedToTomorrow(false, 'Иванова И.И.');
        $this->assertSame('сегодня', DeliverySchedule::text($tuesdayMorning));
    }

    /**
     * Карточек на странице десятки — флаг читается из БД один раз за запрос.
     * Ответ AJAX (`cur_delivery_day.php`) при этом должен видеть свежее значение.
     */
    public function test_flag_is_cached_per_request_but_a_fresh_read_bypasses_the_cache(): void
    {
        $this->assertFalse(DeliverySchedule::isMovedToTomorrow());

        SiteSetting::setDeliveryMovedToTomorrow(true, 'Иванова И.И.'); // мимо DeliverySchedule

        $this->assertFalse(DeliverySchedule::isMovedToTomorrow(), 'Из кэша запроса.');
        $this->assertTrue(DeliverySchedule::isMovedToTomorrow(true), 'Свежее чтение видит флаг.');
    }

    // ─── подпись под переключателем: кто и когда ─────────────────────────────
    // Зависит от реального «сегодня» (флаг привязан к дате), поэтому каждый тест
    // выполняется только в подходящий день недели — остальные помечаются пропущенными.

    public function test_hint_names_who_flipped_it_and_when(): void
    {
        $this->skipUnlessCourierDay();
        $this->putRow($this->today(), $this->today() . ' 12:35:00', 'Иванова И.И.');

        $this->assertSame('Переключено — Иванова И.И., в 12:35', DeliverySchedule::switchHint());
    }

    public function test_hint_without_a_name_shows_only_the_time(): void
    {
        $this->skipUnlessCourierDay();
        $this->putRow($this->today(), $this->today() . ' 12:35:00', '');

        $this->assertSame('Переключено в 12:35', DeliverySchedule::switchHint());
    }

    public function test_hint_says_so_when_the_switch_was_flipped_back(): void
    {
        $this->skipUnlessCourierDay();
        $this->putRow(null, $this->today() . ' 12:35:00', 'Иванова И.И.');

        $this->assertSame('Возвращено к расписанию — Иванова И.И., в 12:35', DeliverySchedule::switchHint());
    }

    /**
     * Вчерашнее имя только путало бы: флаг вчерашний, значит, уже не действует.
     */
    public function test_hint_does_not_show_yesterdays_entry(): void
    {
        $this->skipUnlessCourierDay();
        $this->putRow($this->daysFromToday(-1), $this->daysFromToday(-1) . ' 12:35:00', 'Старая С.С.');

        $hint = DeliverySchedule::switchHint();

        $this->assertStringNotContainsString('Старая', $hint);
        $this->assertSame($this->scheduleHint(), $hint);
    }

    public function test_hint_with_no_entry_describes_the_schedule(): void
    {
        $this->skipUnlessCourierDay();

        $this->assertSame($this->scheduleHint(), DeliverySchedule::switchHint());
    }

    public function test_hint_on_a_weekend_says_the_switch_changes_nothing(): void
    {
        if (DeliverySchedule::isCourierWorkingDay()) {
            $this->markTestSkipped('Проверяется только в субботу и воскресенье.');
        }

        $this->assertSame(
            'В выходные свой курьер не ездит — переключатель ничего не меняет',
            DeliverySchedule::switchHint()
        );
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function connection(): \mysqli
    {
        return Db::getInstance()->getConnection();
    }

    private function today(): string
    {
        return SalonHours::now()->format('Y-m-d');
    }

    private function daysFromToday(int $days): string
    {
        return SalonHours::now()->modify(sprintf('%+d day', $days))->format('Y-m-d');
    }

    private function moment(string $day, string $time): DateTime
    {
        return new DateTime($day . ' ' . $time, new DateTimeZone('Europe/Minsk'));
    }

    private function skipUnlessCourierDay(): void
    {
        if (!DeliverySchedule::isCourierWorkingDay()) {
            $this->markTestSkipped('Подпись со временем показывается только в будни; в выходные другая.');
        }
    }

    /** Подпись, когда сегодня переключатель не трогали: зависит от времени суток. */
    private function scheduleHint(): string
    {
        return DeliverySchedule::isBeforeCutoff()
            ? 'Само переключится в 17:00'
            : 'Уже после 17:00 — дальше по расписанию';
    }

    /**
     * Строка журнала ровно такой, какой её оставил бы ручной клик в прошлом:
     * $at и $who задаём сами, чтобы не зависеть от NOW() сервера.
     */
    private function putRow(?string $value, string $at, string $who): void
    {
        $db = $this->connection();
        $quote = function (?string $v) use ($db) {
            return $v === null ? 'NULL' : "'" . $db->real_escape_string($v) . "'";
        };

        $db->query("DELETE FROM site_settings WHERE setting_key='" . self::KEY . "'");
        $db->query(sprintf(
            "INSERT INTO site_settings (setting_key, setting_value, updated_at, updated_by) VALUES ('%s', %s, %s, %s)",
            self::KEY,
            $quote($value),
            $quote($at),
            $quote($who)
        ));

        $this->resetDeliveryCaches();
    }

    /** Кэши класса живут статикой на весь процесс — между тестами их надо сбрасывать. */
    private function resetDeliveryCaches(): void
    {
        foreach (['movedToTomorrow', 'lastChange'] as $property) {
            $reflection = new \ReflectionProperty(DeliverySchedule::class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue(null, null);
        }
    }
}
