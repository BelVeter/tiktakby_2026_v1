<?php

namespace Tests\Unit;

use bb\classes\Tariff;
use bb\classes\TariffHistory;
use bb\Db;
use Tests\TestCase;

/**
 * Тесты идут против живой dev-базы (проект не использует RefreshDatabase),
 * поэтому каждый тест выполняется внутри транзакции и откатывается.
 */
class TariffHistoryTest extends TestCase
{
    /** Модель-песочница: id, которого заведомо нет в каталоге. */
    private const SANDBOX_MODEL_ID = 999999;

    protected function setUp(): void
    {
        parent::setUp();
        Db::getInstance()->getConnection()->query('START TRANSACTION');

        // Tariff::saveNew()/update() читают User::getCurrentUser()->id_user.
        // Без сессии getCurrentUser() возвращает false и change_who уедет пустым,
        // поэтому подставляем реального пользователя (id 26 — «КристинаН»).
        $_SESSION['user_id']  = 26;
        $_SESSION['user_fio'] = 'PHPUnit';
    }

    protected function tearDown(): void
    {
        Db::getInstance()->getConnection()->query('ROLLBACK');
        unset($_SESSION['user_id'], $_SESSION['user_fio']);
        parent::tearDown();
    }

    private function makeTariff(float $amount = 100.00, int $kolVo = 2): Tariff
    {
        $t = new Tariff();
        $t->tarif_id      = 0;
        $t->model_id      = self::SANDBOX_MODEL_ID;
        $t->start_date    = new \DateTime('2026-01-01');
        $t->step          = 'week';
        $t->kol_vo        = $kolVo;
        $t->kol_vo_min    = $kolVo;
        $t->rent_amount   = $amount;
        $t->rent_per_step = round($amount / $kolVo, 2);
        $t->sort_num      = 7;
        return $t;
    }

    private function events(): array
    {
        return TariffHistory::forModel(self::SANDBOX_MODEL_ID, 100);
    }

    public function test_step_days_covers_all_units(): void
    {
        $this->assertSame(1,   TariffHistory::stepDays('day'));
        $this->assertSame(7,   TariffHistory::stepDays('week'));
        $this->assertSame(30,  TariffHistory::stepDays('month'));
        $this->assertSame(365, TariffHistory::stepDays('year'));
        $this->assertSame(0,   TariffHistory::stepDays('fortnight'));
    }

    public function test_price_per_day_divides_by_full_period(): void
    {
        // 2 недели за 85.00 → 85 / 14 = 6.07
        $this->assertSame(6.07, TariffHistory::pricePerDay(85.00, 'week', 2));
        $this->assertSame(50.0, TariffHistory::pricePerDay(50.00, 'day', 1));
    }

    public function test_price_per_day_is_null_for_zero_period(): void
    {
        $this->assertNull(TariffHistory::pricePerDay(50.00, 'week', 0));
        $this->assertNull(TariffHistory::pricePerDay(50.00, 'fortnight', 2));
    }

    public function test_log_create_writes_only_new_values(): void
    {
        $t = $this->makeTariff(120.00);
        $t->tarif_id = 12345;

        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertCount(1, $events);
        $this->assertSame('create', $events[0]['change_type']);
        $this->assertSame('bb_admin', $events[0]['source']);
        $this->assertNull($events[0]['old_rent_amount']);
        $this->assertSame('120.00', $events[0]['new_rent_amount']);
        $this->assertSame(12345, (int) $events[0]['tarif_id']);
    }

    public function test_log_update_writes_both_sides(): void
    {
        $before = $this->makeTariff(85.00);
        $before->tarif_id = 12345;
        $after = $this->makeTariff(95.00);
        $after->tarif_id = 12345;

        TariffHistory::log(TariffHistory::TYPE_UPDATE, $before, $after, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertSame('85.00', $events[0]['old_rent_amount']);
        $this->assertSame('95.00', $events[0]['new_rent_amount']);
    }

    public function test_log_delete_writes_only_old_values(): void
    {
        $before = $this->makeTariff(85.00);
        $before->tarif_id = 12345;

        TariffHistory::log(TariffHistory::TYPE_DELETE, $before, null, TariffHistory::SOURCE_MODEL_ARCHIVE);

        $events = $this->events();
        $this->assertSame('delete', $events[0]['change_type']);
        $this->assertSame('model_archive', $events[0]['source']);
        $this->assertSame('85.00', $events[0]['old_rent_amount']);
        $this->assertNull($events[0]['new_rent_amount']);
    }

    public function test_for_model_returns_newest_first(): void
    {
        $t = $this->makeTariff(100.00);
        $t->tarif_id = 12345;
        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_BB_ADMIN);
        TariffHistory::log(TariffHistory::TYPE_UPDATE, $t, $t, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertCount(2, $events);
        $this->assertSame('update', $events[0]['change_type'], 'новое событие должно быть первым');
    }

    public function test_actor_is_taken_from_session(): void
    {
        $t = $this->makeTariff();
        $t->tarif_id = 12345;
        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertSame(26, (int) $events[0]['actor_user_id']);
        $this->assertSame('PHPUnit', $events[0]['actor_name']);
    }

    public function test_actor_is_null_without_session(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_fio']);

        $t = $this->makeTariff();
        $t->tarif_id = 12345;
        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_MIGRATION);

        $events = $this->events();
        $this->assertNull($events[0]['actor_user_id']);
        $this->assertNull($events[0]['actor_name']);
    }
}
