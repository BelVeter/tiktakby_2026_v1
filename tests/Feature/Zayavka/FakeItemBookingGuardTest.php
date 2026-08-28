<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\bron;
use bb\classes\Zayavka;

/**
 * tovar_rent_items.state=-1 ("Фейк, не настоящий") — товар для демо/заглушки,
 * который не должен реально сдаваться в аренду. Эти тесты фиксируют границу:
 * бронь (самовывоз/доставка/CRM/публичный сайт) на такой товар обязана
 * блокироваться на уровне записи в БД, а заявка (она привязана к модели, а
 * не к конкретному инв. номеру) — работать как обычно.
 */
class FakeItemBookingGuardTest extends TestCase
{
    private $conn;
    private array $cleanupInvNs = [];
    private array $cleanupOrderIds = [];
    private array $cleanupModelIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = Db::getInstance()->getConnection();
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['user_id'] = 0;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupInvNs as $invN) {
            $this->conn->query("DELETE FROM tovar_rent_items WHERE item_inv_n=" . (int) $invN);
        }
        foreach ($this->cleanupOrderIds as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int) $id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=" . (int) $id);
        }
        foreach ($this->cleanupModelIds as $id) {
            $this->conn->query("DELETE FROM tovar_rent WHERE tovar_rent_id=" . (int) $id);
        }
        parent::tearDown();
    }

    private function insertTestModel(int $catId): int
    {
        $this->conn->query("INSERT INTO tovar_rent (
            tovar_rent_cat_id, producer, model, `set`, color, agr_price, agr_price_cur, lom_srok,
            cr_ch_date, user, model_addr, ph_addr, age_from, age_to, weight_from, weight_to,
            ny, zv, tale, rez1, rez2, collateral, m_sex
        ) VALUES (
            $catId, '__TEST__', '__TEST__', '', '', 0, 'BYN', 0,
            " . time() . ", '__TEST__', '', '', 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, '0'
        )");
        $id = (int) $this->conn->insert_id;
        $this->cleanupModelIds[] = $id;
        return $id;
    }

    private function insertTestItem(int $invN, int $modelId, int $state, string $status = 'to_rent'): void
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
    }

    private function itemStatus(int $invN): string
    {
        $row = $this->conn->query("SELECT `status` FROM tovar_rent_items WHERE item_inv_n=" . $invN)->fetch_assoc();
        return $row['status'];
    }

    public function test_create_bron_strong_blocks_fake_item(): void
    {
        $invN = 999990101;
        $this->insertTestItem($invN, 999101, -1);

        $result = bron::createBronStrong($invN, '__TEST__ Клиент', '79900000101', false, '', true, '__TEST__ info');

        $this->assertFalse($result, 'createBronStrong() must refuse a state=-1 (fake) item');
        $this->assertSame('to_rent', $this->itemStatus($invN), 'item status must stay untouched when booking is refused');

        $orphan = $this->conn->query("SELECT COUNT(*) c FROM rent_orders WHERE inv_n=" . $invN)->fetch_assoc();
        $this->assertSame(0, (int) $orphan['c'], 'no rent_orders row should be created for a refused booking');
    }

    public function test_create_bron_strong_allows_real_item(): void
    {
        $invN = 999990102;
        $modelId = $this->insertTestModel(1);
        $this->insertTestItem($invN, $modelId, 0);

        $result = bron::createBronStrong($invN, '__TEST__ Клиент', '79900000102', false, '', true, '__TEST__ info');

        $this->assertNotFalse($result, 'createBronStrong() must still work for a normal (non-fake) item');
        $this->assertGreaterThan(0, $result->insert_id);
        $this->cleanupOrderIds[] = $result->insert_id;
        $this->assertSame('bron', $this->itemStatus($invN));
    }

    public function test_z_to_br_blocks_fake_item(): void
    {
        $invN = 999990103;
        $modelId = 999103;
        $this->insertTestItem($invN, $modelId, -1);

        $now = time();
        $this->conn->query("INSERT INTO rent_orders
            (`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date)
            VALUES ('zayavka', $now, 79900000103, 0, '__TEST__ Заяв', '', '', 0, '', $now, 0, $modelId, 1, 'zayavka', 0, '__TEST__ info', '', 1, $now, 0, 0, 0, '', 0, 0, '', '', '', 'new', NULL, NULL)");
        $orderId = $this->conn->insert_id;
        $this->cleanupOrderIds[] = $orderId;

        $br = new bron();
        $br->br_load($orderId);
        $br->inv_n = $invN;
        $br->z_to_br();

        $this->assertSame(1, $br->failure, 'z_to_br() must refuse a state=-1 (fake) item');
        $this->assertStringContainsString('ФЕЙК', $br->alert);
        $this->assertSame('to_rent', $this->itemStatus($invN));

        $row = $this->conn->query("SELECT type2 FROM rent_orders WHERE order_id=" . $orderId)->fetch_assoc();
        $this->assertSame('zayavka', $row['type2'], 'the order must stay a zayavka, not be converted to a bron');
    }

    public function test_zayavka_create_unaffected_by_fake_only_stock(): void
    {
        $invN = 999990104;
        $modelId = 999104;
        $this->insertTestItem($invN, $modelId, -1);

        $z = new Zayavka($this->conn);
        $res = $z->create([
            'model_id' => $modelId, 'phone' => 79900000104, 'family' => '__TEST__ Заявитель',
            'info' => '__TEST__ модель только с фейковым остатком', 'web' => 1,
        ], 'crm');

        $this->assertFalse($res->isDuplicate);
        $this->assertGreaterThan(0, $res->orderId, 'a zayavka must still be creatable for a model whose only free unit is fake');
        $this->cleanupOrderIds[] = $res->orderId;
    }
}
