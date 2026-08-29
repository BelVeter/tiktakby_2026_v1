<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\tovar;

/**
 * tovar::toggleFake() — тумблер пометки ФЕЙК из контекстного меню kr_baza_new.php.
 * Пометка (реальный -> фейк) — в одно действие. Снятие (фейк -> реальный)
 * требует явно указанное состояние (0/1/2/4), т.к. состояние "до фейка"
 * нигде не хранится и восстановить его автоматически нельзя. Оба направления
 * запрещены, если товар физически у клиента/курьера (rented_out/to_deliver).
 */
class ToggleFakeTest extends TestCase
{
    private $conn;
    private array $cleanupInvNs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = Db::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupInvNs as $invN) {
            $this->conn->query("DELETE FROM tovar_rent_items WHERE item_inv_n=" . (int) $invN);
        }
        parent::tearDown();
    }

    private function insertTestItem(int $invN, int $modelId, int $state, string $status = 'to_rent'): int
    {
        $this->conn->query("INSERT INTO tovar_rent_items (
            cat_id, producer, model_id, item_n, item_inv_n, sex, item_size, real_item_size,
            item_rost1, item_rost2, item_set, buy_date, buy_price, buy_price_cur, exch_to_byr,
            seller, item_info, cr_ch_date, user, status, active_deal_id, item_color, item_place,
            br_time, state, to_move, qr_yn
        ) VALUES (
            1, '__TEST__', $modelId, 1, $invN, 'u', '', '',
            0, 0, '', " . time() . ", 0, 'BYN', 1,
            '__TEST__', '__TEST__', " . time() . ", '__TEST__', '$status', '', '', 1,
            0, $state, 0, 0
        )");
        $this->cleanupInvNs[] = $invN;
        return (int) $this->conn->insert_id;
    }

    private function itemState(int $itemId): int
    {
        $row = $this->conn->query("SELECT `state` FROM tovar_rent_items WHERE item_id=" . $itemId)->fetch_assoc();
        return (int) $row['state'];
    }

    public function test_marks_real_item_as_fake(): void
    {
        $invN = 999990201;
        $itemId = $this->insertTestItem($invN, 999201, 1);

        $result = tovar::toggleFake($itemId);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(-1, $result['state']);
        $this->assertTrue($result['is_fake']);
        $this->assertNotSame('', $result['badge_html']);
        $this->assertSame(-1, $this->itemState($itemId));
    }

    public function test_unmarks_fake_item_with_explicit_restore_state(): void
    {
        $invN = 999990202;
        $itemId = $this->insertTestItem($invN, 999202, -1);

        $result = tovar::toggleFake($itemId, 2);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(2, $result['state']);
        $this->assertFalse($result['is_fake']);
        $this->assertSame('', $result['badge_html']);
        $this->assertSame(2, $this->itemState($itemId));
    }

    public function test_unmark_requires_a_valid_restore_state(): void
    {
        $invN = 999990203;
        $itemId = $this->insertTestItem($invN, 999203, -1);

        $result = tovar::toggleFake($itemId, null);

        $this->assertSame('error', $result['status']);
        $this->assertSame(-1, $this->itemState($itemId), 'item must stay fake when no restore state is given');
    }

    public function test_unmark_rejects_state_3_as_it_is_not_a_selectable_condition(): void
    {
        $invN = 999990204;
        $itemId = $this->insertTestItem($invN, 999204, -1);

        $result = tovar::toggleFake($itemId, 3);

        $this->assertSame('error', $result['status']);
        $this->assertSame(-1, $this->itemState($itemId));
    }

    public function test_blocks_toggle_when_item_is_rented_out(): void
    {
        $invN = 999990205;
        $itemId = $this->insertTestItem($invN, 999205, 1, 'rented_out');

        $result = tovar::toggleFake($itemId);

        $this->assertSame('error', $result['status']);
        $this->assertSame(1, $this->itemState($itemId), 'state must stay untouched when the item is handed to a customer');
    }

    public function test_blocks_toggle_when_item_is_out_for_delivery(): void
    {
        $invN = 999990206;
        $itemId = $this->insertTestItem($invN, 999206, -1, 'to_deliver');

        $result = tovar::toggleFake($itemId, 1);

        $this->assertSame('error', $result['status']);
        $this->assertSame(-1, $this->itemState($itemId));
    }

    public function test_allows_toggle_when_item_is_in_bron(): void
    {
        $invN = 999990207;
        $itemId = $this->insertTestItem($invN, 999207, 1, 'bron');

        $result = tovar::toggleFake($itemId);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(-1, $this->itemState($itemId));
    }
}
