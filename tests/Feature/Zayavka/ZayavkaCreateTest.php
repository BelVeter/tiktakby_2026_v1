<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\Zayavka;

class ZayavkaCreateTest extends TestCase
{
    private $conn;
    private array $cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = Db::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int)$id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=" . (int)$id);
        }
        $this->conn->query("DELETE FROM zvonki WHERE info LIKE '__TEST__%'");
        $this->conn->query("DELETE FROM rent_orders_arch WHERE family LIKE '__TEST__%'");
        parent::tearDown();
    }

    public function test_create_makes_one_zayavka_with_new_status(): void
    {
        $z = new Zayavka($this->conn);
        $res = $z->create([
            'model_id' => 999001, 'phone' => 79900000010, 'family' => '__TEST__ Петров',
            'info' => '__TEST__ нужна коляска', 'web' => 1, 'planned_date' => null,
        ], 'crm');

        $this->assertFalse($res->isDuplicate);
        $this->assertGreaterThan(0, $res->orderId);
        $this->cleanup[] = $res->orderId;

        $row = $this->conn->query("SELECT type2, z_status, phone FROM rent_orders WHERE order_id=" . (int)$res->orderId)->fetch_assoc();
        $this->assertSame('zayavka', $row['type2']);
        $this->assertSame('new', $row['z_status']);
        $this->assertSame('79900000010', $row['phone']);
    }

    public function test_create_detects_duplicate_by_model_and_phone(): void
    {
        $z = new Zayavka($this->conn);
        $first = $z->create(['model_id' => 999002, 'phone' => 79900000011, 'family' => '__TEST__ A', 'info' => '__TEST__ 1', 'web' => 1], 'crm');
        $this->cleanup[] = $first->orderId;

        $second = $z->create(['model_id' => 999002, 'phone' => 79900000011, 'family' => '__TEST__ A', 'info' => '__TEST__ 2', 'web' => 1], 'crm');
        if ($second->orderId) { $this->cleanup[] = $second->orderId; }

        $this->assertTrue($second->isDuplicate);
        $this->assertNotNull($second->existing);
        $this->assertSame($first->orderId, $second->existing->order_id);
    }

    public function test_create_with_empty_phone_does_not_match_other_empty(): void
    {
        $z = new Zayavka($this->conn);
        $a = $z->create(['model_id' => 999003, 'phone' => 0, 'family' => '__TEST__ X', 'info' => '__TEST__ a', 'web' => 1], 'web_modal');
        $this->cleanup[] = $a->orderId;
        $b = $z->create(['model_id' => 999003, 'phone' => 0, 'family' => '__TEST__ Y', 'info' => '__TEST__ b', 'web' => 1], 'web_modal');
        $this->cleanup[] = $b->orderId;

        $this->assertFalse($b->isDuplicate, 'phone<=1 must NOT be treated as duplicate');
    }

    public function test_archived_match_still_creates_but_flags_repeat(): void
    {
        // prior request, archived as rejected, same model+phone, recent
        // NOTE: rent_orders_arch.phone is int(11) (max 2147483647); use a 9-digit sentinel
        // that fits within int(11) and does not collide with real data.
        $testPhone = 799000012; // 9 digits, fits int(11) signed
        $now = time();
        // pre-clean any stale sentinel rows
        $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=0 AND family='__TEST__ Old'");
        // insert archived match
        $this->conn->query("INSERT INTO rent_orders_arch
            (arch_time, arch_who, order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date)
            VALUES ($now, 0, 0, 'zayavka', $now, $testPhone, 0, '__TEST__ Old', '', '', 0, '', $now, 0, 999004, 0, 'zayavka', 0, '__TEST__ old req', '', 1, $now, 0, 0, 0, '', 0, 0, '', '', '', 'rejected', 'changed_mind', NULL)");

        $z = new Zayavka($this->conn);
        $res = $z->create(['model_id' => 999004, 'phone' => $testPhone, 'family' => '__TEST__ New', 'info' => '__TEST__ new req', 'web' => 1], 'web_modal');

        $this->assertFalse($res->isDuplicate, 'archived-only match must NOT suppress a new active zayavka');
        $this->assertGreaterThan(0, $res->orderId);
        $this->cleanup[] = $res->orderId;
        $this->assertTrue($res->isRepeat, 'should flag as repeat when a prior archived request exists');
        $this->assertNotNull($res->priorArchived);
    }
}
