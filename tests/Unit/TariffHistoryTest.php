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

        // Самолечение: test_model_archive_logs_tariff_deletion() вызывает
        // ModelArchive::archive(), который открывает СВОЮ транзакцию
        // (Db::startTransaction()). В mysqli вложенный START TRANSACTION
        // неявно коммитит внешнюю, поэтому ROLLBACK в tearDown() уже нечего
        // откатывать — строки песочницы остаются в базе навсегда и валят
        // следующий прогон (Duplicate entry, чужие записи в events()).
        // Чистим ДО открытия транзакции, чтобы прошлый прогон не мешал этому.
        $this->purgeSandboxLeftovers();

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

        // На случай, если этот прогон сам коммитнул данные (см. setUp()) —
        // убираем за собой, чтобы следующий прогон стартовал с чистого листа.
        $this->purgeSandboxLeftovers();

        parent::tearDown();
    }

    /**
     * Безусловно вычищает все следы модели-песочницы. Идемпотентна и не
     * зависит от того, была ли открыта транзакция — работает через тот же
     * mysqli-connection, что и весь остальной код в bb/.
     */
    private function purgeSandboxLeftovers(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $id = self::SANDBOX_MODEL_ID;

        $mysqli->query("DELETE FROM rent_tarif_history WHERE model_id = {$id}");
        $mysqli->query("DELETE FROM rent_tarif_act WHERE model_id = {$id}");
        $mysqli->query("DELETE FROM tovar_rent_arch WHERE tovar_rent_id = {$id}");
        $mysqli->query("DELETE FROM tovar_rent WHERE tovar_rent_id = {$id}");
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

    public function test_save_new_writes_create_event(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();

        $this->assertGreaterThan(0, $t->tarif_id, 'save() должен проставить tarif_id');

        $events = $this->events();
        $this->assertCount(1, $events);
        $this->assertSame('create', $events[0]['change_type']);
        $this->assertSame('140.00', $events[0]['new_rent_amount']);
        $this->assertSame($t->tarif_id, (int) $events[0]['tarif_id']);
    }

    public function test_update_writes_before_and_after(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();

        $t->rent_amount   = 160.00;
        $t->rent_per_step = 80.00;
        $t->save();

        $events = $this->events();
        $this->assertCount(2, $events);
        $this->assertSame('update', $events[0]['change_type']);
        $this->assertSame('140.00', $events[0]['old_rent_amount']);
        $this->assertSame('160.00', $events[0]['new_rent_amount']);
    }

    public function test_update_without_real_change_writes_nothing(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();
        $t->save();   // повторное сохранение без изменений

        $this->assertCount(1, $this->events(), 'пустой UPDATE не должен порождать событие');
    }

    public function test_delete_writes_delete_event_and_removes_row(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();
        $tarifId = $t->tarif_id;

        $t->delete();

        $this->assertFalse(Tariff::getById($tarifId), 'строка должна быть удалена');

        $events = $this->events();
        $this->assertSame('delete', $events[0]['change_type']);
        $this->assertSame('140.00', $events[0]['old_rent_amount']);
        $this->assertNull($events[0]['new_rent_amount']);
    }

    public function test_history_source_is_carried_into_event(): void
    {
        $t = $this->makeTariff(140.00);
        $t->historySource = TariffHistory::SOURCE_MODEL_ARCHIVE;
        $t->save();

        $this->assertSame('model_archive', $this->events()[0]['source']);
    }

    public function test_update_nonexistent_tariff_returns_false_and_writes_nothing(): void
    {
        $t = $this->makeTariff(140.00);
        $t->tarif_id = 999999;  // tarif_id, которого точно нет в базе
        $t->rent_amount = 160.00;

        $result = $t->save();

        // save() вызывает update() для существующих tarif_id
        $this->assertFalse($result, 'save() должен вернуть false для несуществующего тарифа');
        $this->assertEmpty($this->events(), 'не должно быть событий в журнале для несуществующего тарифа');
    }

    public function test_model_archive_logs_tariff_deletion(): void
    {
        // Готовим модель-песочницу с одним тарифом.
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("INSERT INTO tovar_rent (tovar_rent_id, tovar_rent_cat_id, producer, model, color)
                        VALUES (" . self::SANDBOX_MODEL_ID . ", 0, 'TestProducer', 'TestModel', 'TestColor')");

        $t = $this->makeTariff(200.00);
        $t->save();

        $result = \bb\classes\ModelArchive::archive(self::SANDBOX_MODEL_ID, 26);
        $this->assertTrue($result === true, is_string($result) ? $result : 'архивация должна пройти');

        $deletes = array_values(array_filter($this->events(), static function ($e) {
            return $e['change_type'] === 'delete';
        }));

        $this->assertCount(1, $deletes, 'архивация модели должна залогировать удаление тарифа');
        $this->assertSame('model_archive', $deletes[0]['source']);
        $this->assertSame('200.00', $deletes[0]['old_rent_amount']);
    }
}
