<?php

namespace Tests\Feature;

use bb\classes\Producer;
use bb\Db;
use Tests\TestCase;

/**
 * rename() открывает СВОЮ транзакцию (Db::startTransaction()). В mysqli
 * вложенный START TRANSACTION неявно коммитит внешнюю — тот же готча, что
 * уже задокументирован в tests/Unit/TariffHistoryTest.php для
 * ModelArchive::archive(). Поэтому здесь НЕ оборачиваем тест в свою
 * транзакцию с расчётом на ROLLBACK в tearDown() — чистим явными DELETE
 * до и после каждого теста.
 */
class ProducerRenameTest extends TestCase
{
    private const OLD_NAME = 'Тестовый Производитель ZZZ Старый';
    private const NEW_NAME = 'Тестовый Производитель ZZZ Новый';
    private const SANDBOX_MODEL_ID = 999998;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purge();

        $_SESSION['user_id'] = 26;

        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("
            INSERT INTO tovar_rent SET tovar_rent_id=" . self::SANDBOX_MODEL_ID . ",
            tovar_rent_cat_id=1, producer='" . self::OLD_NAME . "', model='sandbox', cr_ch_date=" . time()
        );
        $mysqli->query("
            INSERT INTO tovar_rent_items SET item_id=" . self::SANDBOX_MODEL_ID . ",
            cat_id=1, producer='" . self::OLD_NAME . "', model_id=" . self::SANDBOX_MODEL_ID
        );
    }

    protected function tearDown(): void
    {
        $this->purge();
        unset($_SESSION['user_id']);
        parent::tearDown();
    }

    private function purge(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query('DELETE FROM tovar_rent WHERE tovar_rent_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query('DELETE FROM tovar_rent_items WHERE item_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query("DELETE FROM producers WHERE name LIKE 'Тестовый Производитель ZZZ%'");
    }

    public function test_impact_counts_affected_rows(): void
    {
        $p = new Producer();
        $p->setName(self::OLD_NAME);
        $p->save();

        $impact = $p->impactOfRename();

        $this->assertSame(1, $impact['models']);
        $this->assertSame(1, $impact['items']);
        $this->assertSame(0, $impact['items_arch']);
    }

    public function test_rename_propagates_to_all_three_tables(): void
    {
        $p = new Producer();
        $p->setName(self::OLD_NAME);
        $p->save();

        $ok = $p->rename(self::NEW_NAME, 26);
        $this->assertTrue($ok);
        $this->assertSame(self::NEW_NAME, $p->getName());

        $mysqli = Db::getInstance()->getConnection();
        $model = $mysqli->query('SELECT producer FROM tovar_rent WHERE tovar_rent_id=' . self::SANDBOX_MODEL_ID)->fetch_assoc();
        $item = $mysqli->query('SELECT producer FROM tovar_rent_items WHERE item_id=' . self::SANDBOX_MODEL_ID)->fetch_assoc();

        $this->assertSame(self::NEW_NAME, $model['producer']);
        $this->assertSame(self::NEW_NAME, $item['producer']);

        $reloaded = Producer::getByName(self::NEW_NAME);
        $this->assertNotFalse($reloaded);
        $this->assertFalse(Producer::getByName(self::OLD_NAME));
    }
}
