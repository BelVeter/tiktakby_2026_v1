<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\bron;

class BronRegressionTest extends TestCase
{
    private $conn;
    private array $cleanupOrderIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = Db::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupOrderIds as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int)$id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=" . (int)$id);
        }
        parent::tearDown();
    }

    public function test_bron_insert_and_arch_copy_roundtrip(): void
    {
        $br = new bron();
        $br->type = 'strong';
        $br->order_date = time();
        $br->phone = 79900000001;
        $br->phone_yn = 0;
        $br->family = '__TEST__ Иванов';
        $br->name = '';
        $br->otch = '';
        $br->fio_yn = 0;
        $br->address = '';
        $br->validity = time() + 86400;
        $br->inv_n = 0;
        $br->model_id = 0;
        $br->cat_id = 0;
        $br->type2 = 'bron';
        $br->client_id = 0;
        $br->info = '__TEST__ regression';
        $br->info2 = '';
        $br->web = 0;
        $br->cr_time = time();
        $br->cr_who_id = 0;
        $br->ch_time = 0;
        $br->ch_who_id = 0;
        $br->status = '';
        $br->appr_id = 0;
        $br->appr_time = 0;
        $br->cr_ip = '';
        $br->place_status = '';
        $br->rem_type = '';

        $br->insert();
        $this->assertGreaterThan(0, $br->insert_id, 'insert() must return new id');
        $this->cleanupOrderIds[] = $br->insert_id;
        $br->order_id = $br->insert_id;

        $row = $this->conn->query("SELECT type2, family, phone FROM rent_orders WHERE order_id=" . (int)$br->insert_id)->fetch_assoc();
        $this->assertSame('bron', $row['type2']);
        $this->assertSame('__TEST__ Иванов', $row['family']);

        $br->arch_copy('dogovor_new'); // auto-режим, user=0, без $_SESSION
        $arch = $this->conn->query("SELECT COUNT(*) c FROM rent_orders_arch WHERE order_id=" . (int)$br->insert_id)->fetch_assoc();
        $this->assertSame(1, (int)$arch['c'], 'arch_copy must duplicate the row into _arch');
    }
}
