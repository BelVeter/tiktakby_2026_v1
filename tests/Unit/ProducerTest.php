<?php

namespace Tests\Unit;

use bb\classes\Producer;
use bb\Db;
use Tests\TestCase;

class ProducerTest extends TestCase
{
    private const SANDBOX_NAME = 'Тестовый Производитель ZZZ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->purgeSandbox();
    }

    protected function tearDown(): void
    {
        $this->purgeSandbox();
        parent::tearDown();
    }

    private function purgeSandbox(): void
    {
        // Все имена песочницы начинаются с SANDBOX_NAME (маркер ZZZ). Шаблон
        // без маркера цеплял бы и настоящие записи — см. тест ниже.
        Db::getInstance()->getConnection()->query(
            "DELETE FROM producers WHERE name LIKE '" . self::SANDBOX_NAME . "%'"
        );
    }

    /**
     * Чистка песочницы не должна задевать настоящие записи. Колонка producers.name
     * в utf8mb4_unicode_ci, то есть LIKE регистронезависим: шаблон
     * «Тестовый Производитель%» стирал реального «Тестового производителя Димы»
     * (им пользуется модель) — а ProducersSeedTest в полном прогоне красным
     * показывал, что «чего-то не хватает».
     */
    public function test_sandbox_cleanup_leaves_look_alike_real_producers_alone(): void
    {
        $lookAlike = 'Тестовый производитель — не песочница';
        $db = Db::getInstance()->getConnection();

        $real = new Producer();
        $real->setName($lookAlike);
        $real->save();

        try {
            $this->purgeSandbox();

            $this->assertNotFalse(
                Producer::getByName($lookAlike),
                'purgeSandbox() удалил запись, которая только похожа на песочницу.'
            );
        } finally {
            $db->query("DELETE FROM producers WHERE name = '" . $db->real_escape_string($lookAlike) . "'");
        }
    }

    public function test_save_inserts_then_update_reuses_id(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->setLogo('/img/test.webp');
        $this->assertTrue($p->save());
        $id = $p->getId();
        $this->assertGreaterThan(0, $id);

        $p->setComment('заметка');
        $this->assertTrue($p->save());
        $this->assertSame($id, $p->getId());

        $reloaded = Producer::getById($id);
        $this->assertSame('заметка', $reloaded->getComment());
    }

    public function test_get_by_name_is_exact_match(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->save();

        $this->assertNotFalse(Producer::getByName(self::SANDBOX_NAME));
        $this->assertFalse(Producer::getByName(self::SANDBOX_NAME . ' другое'));
    }

    public function test_get_all_active_excludes_hidden(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->setActive(false);
        $p->save();

        $activeNames = array_map(function (Producer $x) { return $x->getName(); }, Producer::getAllActive());
        $this->assertNotContains(self::SANDBOX_NAME, $activeNames);

        $allNames = array_map(function (Producer $x) { return $x->getName(); }, Producer::getAll());
        $this->assertContains(self::SANDBOX_NAME, $allNames);
    }

    public function test_find_duplicates_reports_exact(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->save();

        $result = Producer::findDuplicates(self::SANDBOX_NAME);
        $this->assertNotNull($result['exact']);
        $this->assertSame(self::SANDBOX_NAME, $result['exact']->getName());
    }

    public function test_find_duplicates_reports_similar_typo(): void
    {
        $p = new Producer();
        $p->setName('Тестовый Производитель ZZZ Chicco');
        $p->save();

        $result = Producer::findDuplicates('Тестовый Производитель ZZZ Chico');
        $this->assertNull($result['exact']);
        $this->assertNotEmpty($result['similar']);
    }

    public function test_find_duplicates_sees_hidden_producers(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->setActive(false);
        $p->save();

        $result = Producer::findDuplicates(self::SANDBOX_NAME);
        $this->assertNotNull($result['exact'], 'скрытый бренд обязан находиться по точному имени (см. спеку)');
    }
}
