<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\bron;

class CreateZayavkaWrapperTest extends TestCase
{
    private $conn; private array $cleanup = [];
    protected function setUp(): void { parent::setUp(); $this->conn = Db::getInstance()->getConnection(); }
    protected function tearDown(): void {
        foreach ($this->cleanup as $id) { $this->conn->query("DELETE FROM rent_orders WHERE order_id=".(int)$id); }
        parent::tearDown();
    }

    public function test_create_zayavka_wrapper_still_returns_insert_id(): void
    {
        $validity = new \DateTime('+10 days');
        $z = bron::createZayavka(999300, 79900000030, '__TEST__ Сидоров', '', '', $validity, '__TEST__ wrapper', 1);
        $this->assertNotNull($z->insert_id);
        $this->cleanup[] = $z->insert_id;
        $row = $this->conn->query("SELECT type2, z_status FROM rent_orders WHERE order_id=".(int)$z->insert_id)->fetch_assoc();
        $this->assertSame('zayavka', $row['type2']);
        $this->assertSame('new', $row['z_status']);
        $this->assertFalse($z->is_duplicate);
    }
}
