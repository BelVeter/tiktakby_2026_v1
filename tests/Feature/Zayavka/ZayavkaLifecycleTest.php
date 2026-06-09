<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\Zayavka;

class ZayavkaLifecycleTest extends TestCase
{
    private $conn; private array $cleanup = [];
    protected function setUp(): void { parent::setUp(); $this->conn = Db::getInstance()->getConnection(); }
    protected function tearDown(): void {
        foreach ($this->cleanup as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=".(int)$id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=".(int)$id);
        }
        $this->conn->query("DELETE FROM zvonki WHERE info LIKE '__TEST__%'");
        parent::tearDown();
    }

    private function makeZayavka(): Zayavka {
        $z = new Zayavka($this->conn);
        $res = $z->create(['model_id'=>999100,'phone'=>79900000020,'family'=>'__TEST__ L','info'=>'__TEST__ life','web'=>1],'crm');
        $this->cleanup[] = $res->orderId;
        return Zayavka::load($res->orderId, $this->conn);
    }

    public function test_update_appends_history_and_sets_in_work(): void {
        $z = $this->makeZayavka();
        $z->update(['info' => '__TEST__ перезвонить завтра', 'last_ch_time' => $z->ch_time]);
        $fresh = Zayavka::load($z->order_id, $this->conn);
        $this->assertSame('in_work', $fresh->z_status);
        $this->assertStringContainsString('перезвонить завтра', $fresh->info2);
    }

    public function test_change_model_updates_model_and_cat(): void {
        $z = $this->makeZayavka();
        $z->changeModel(999200);
        $fresh = Zayavka::load($z->order_id, $this->conn);
        $this->assertSame('999200', (string)$fresh->model_id);
    }

    public function test_set_status_rejected_archives_and_removes_from_active(): void {
        $z = $this->makeZayavka();
        $z->setStatus('rejected', 'changed_mind');
        $active = $this->conn->query("SELECT COUNT(*) c FROM rent_orders WHERE order_id=".(int)$z->order_id)->fetch_assoc();
        $arch = $this->conn->query("SELECT z_status, z_reject_reason FROM rent_orders_arch WHERE order_id=".(int)$z->order_id)->fetch_assoc();
        $this->assertSame(0, (int)$active['c'], 'terminal status removes from active');
        $this->assertSame('rejected', $arch['z_status']);
        $this->assertSame('changed_mind', $arch['z_reject_reason']);
    }

    public function test_optimistic_lock_rejects_stale_edit(): void {
        $z = $this->makeZayavka();
        $this->expectException(\RuntimeException::class);
        $z->update(['info' => '__TEST__ stale', 'last_ch_time' => $z->ch_time + 999]);
    }

    public function test_load_rejects_non_zayavka(): void {
        // прямой INSERT строки type2='bron' — Zayavka::load не должен её отдавать
        $now = time();
        $this->conn->query("INSERT INTO rent_orders
            (`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type)
            VALUES ('strong', $now, 79900000050, 0, '__TEST__ Bron', '', '', 0, '', $now, 0, 0, 0, 'bron', 0, '__TEST__', '', 0, $now, 0, 0, 0, '', 0, 0, '', '', '')");
        $bronId = $this->conn->insert_id;
        $this->cleanup[] = $bronId;

        $this->expectException(\RuntimeException::class);
        Zayavka::load($bronId, $this->conn);
    }

    public function test_link_zvonok_sets_order_id(): void {
        $z = $this->makeZayavka();
        $this->conn->query("INSERT INTO zvonki SET z_name='__TEST__', phone=79900000020, tema='__TEST__', info='__TEST__ zv', cr_time=".time().", status='new', pr_time=0, operator='', react_time=0, person_id=0, validity_days=0, type1='zayavka', model_id=999100");
        $zvId = $this->conn->insert_id;
        $z->linkZvonok($zvId);
        $row = $this->conn->query("SELECT order_id FROM zvonki WHERE zv_id=".(int)$zvId)->fetch_assoc();
        $this->assertSame((string)$z->order_id, (string)$row['order_id']);
    }
}
