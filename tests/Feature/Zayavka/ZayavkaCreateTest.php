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
}
