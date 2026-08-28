<?php

namespace Tests\Feature;

use Tests\TestCase;
use bb\Db;

/**
 * CartController::checkout() раньше безусловно отвечало status=booked, даже
 * когда bron::createBronStrong() отказывал (например, единственный свободный
 * экземпляр модели — фейк, state=-1): клиент видел "успешно забронировано",
 * а по факту не создавалось ни брони, ни заявки, ни звонка. Эти тесты фиксируют
 * честный фоллбэк: отказ createBronStrong() должен уводить в заявку+звонок,
 * а не в молчаливую ложь клиенту.
 */
class CartFakeItemFallbackTest extends TestCase
{
    private $conn;
    private array $cleanupInvNs = [];
    private array $cleanupOrderIds = [];

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
        foreach ($this->cleanupOrderIds as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int) $id);
        }
        $this->conn->query("DELETE FROM zvonki WHERE z_name LIKE '__TEST__%'");
        parent::tearDown();
    }

    private function insertFakeItem(int $invN, int $modelId): void
    {
        $this->conn->query("INSERT INTO tovar_rent_items (
            cat_id, producer, model_id, item_n, item_inv_n, sex, item_size, real_item_size,
            item_rost1, item_rost2, item_set, buy_date, buy_price, buy_price_cur, exch_to_byr,
            seller, item_info, cr_ch_date, user, status, active_deal_id, item_color, item_place,
            br_time, state, to_move, qr_yn
        ) VALUES (
            1, '__TEST__', $modelId, 1, $invN, 'u', '', '',
            0, 0, '', " . time() . ", 0, 'BYN', 1,
            '__TEST__', '__TEST__', " . time() . ", '__TEST__', 'to_rent', '', '', 1,
            0, -1, 0, 0
        )");
        $this->cleanupInvNs[] = $invN;
    }

    public function test_checkout_falls_back_to_waitlist_when_only_free_unit_is_fake(): void
    {
        $invN = 999990501;
        $modelId = 999501;
        $this->insertFakeItem($invN, $modelId);

        $response = $this->postJson('/cart/checkout', [
            'items' => [[
                'modelId' => $modelId,
                'days' => 14,
                'dateFrom' => date('Y-m-d'),
                'name' => '__TEST__ Model',
            ]],
            'fio' => '__TEST__ Клиент',
            'phone' => '291234567',
            'delivery' => '0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('results.0.status', 'waitlist');

        $status = $this->conn->query("SELECT status FROM tovar_rent_items WHERE item_inv_n=$invN")
            ->fetch_assoc()['status'];
        $this->assertSame('to_rent', $status, 'the fake item must not be silently moved to bron');

        $order = $this->conn->query("SELECT order_id, type2 FROM rent_orders WHERE model_id=$modelId ORDER BY order_id DESC LIMIT 1")
            ->fetch_assoc();
        $this->assertNotNull($order, 'a zayavka must be created to capture the demand signal');
        $this->assertSame('zayavka', $order['type2']);
        $this->cleanupOrderIds[] = $order['order_id'];

        $zvonok = $this->conn->query("SELECT zv_id FROM zvonki WHERE model_id=$modelId ORDER BY zv_id DESC LIMIT 1")
            ->fetch_assoc();
        $this->assertNotNull($zvonok, 'a zvonok must be created so staff notice the demand signal');
    }
}
