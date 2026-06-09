<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\Zayavka;

class ConversionStatusTest extends TestCase
{
    private $conn;
    private array $cleanup = [];

    protected function setUp(): void { parent::setUp(); $this->conn = Db::getInstance()->getConnection(); }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int)$id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=" . (int)$id);
        }
        parent::tearDown();
    }

    /**
     * Full z_to_br() needs a real free tovar_rent_items row + LOCK TABLES, so here we
     * cover the "done" semantics that both z_to_br and the board rely on: a zayavka
     * marked done is archived with z_status='done'.
     */
    public function test_zayavka_marked_done_archives_with_done_status(): void
    {
        $z = new Zayavka($this->conn);
        $res = $z->create(['model_id' => 999400, 'phone' => 79900000040, 'family' => '__TEST__ Conv', 'info' => '__TEST__', 'web' => 1], 'crm');
        $this->cleanup[] = $res->orderId;

        $zl = Zayavka::load($res->orderId, $this->conn);
        $zl->setStatus('done');

        $active = $this->conn->query("SELECT COUNT(*) c FROM rent_orders WHERE order_id=" . (int)$res->orderId)->fetch_assoc();
        $this->assertSame(0, (int)$active['c'], 'done is terminal -> removed from active');
        $arch = $this->conn->query("SELECT z_status FROM rent_orders_arch WHERE order_id=" . (int)$res->orderId)->fetch_assoc();
        $this->assertSame('done', $arch['z_status']);
    }
}
