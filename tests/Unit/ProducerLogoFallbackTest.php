<?php

namespace Tests\Unit;

use App\MyClasses\L3Page;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Producer;
use bb\Db;
use Tests\TestCase;

class ProducerLogoFallbackTest extends TestCase
{
    private const SANDBOX_MODEL_ID = 999997;
    private const PRODUCER_NAME = 'Тестовый Производитель ZZZ Лого';

    protected function tearDown(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query('DELETE FROM tovar_rent WHERE tovar_rent_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query('DELETE FROM rent_model_web WHERE model_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query("DELETE FROM producers WHERE name LIKE 'Тестовый Производитель ZZZ%'");
        parent::tearDown();
    }

    public function test_l3_falls_back_to_own_logo_when_producer_has_none(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("
            INSERT INTO tovar_rent SET tovar_rent_id=" . self::SANDBOX_MODEL_ID . ",
            tovar_rent_cat_id=1, producer='" . self::PRODUCER_NAME . "', model='sandbox', cr_ch_date=" . time()
        );

        $producer = new Producer();
        $producer->setName(self::PRODUCER_NAME);
        $producer->setLogo(''); // директория есть, но логотипа в ней нет
        $producer->save();

        $mw = new ModelWeb(self::SANDBOX_MODEL_ID, 'ru');
        $mw->setLogoUrlAddress('/img/own-fallback.webp');

        $p = new L3Page('ru');
        $p->model = Model::getById(self::SANDBOX_MODEL_ID);
        $p->modelWeb = $mw;

        $this->assertSame('/img/own-fallback.webp', $p->getProducerLogoUrl());
    }

    public function test_l3_prefers_directory_logo_over_own(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("
            INSERT INTO tovar_rent SET tovar_rent_id=" . self::SANDBOX_MODEL_ID . ",
            tovar_rent_cat_id=1, producer='" . self::PRODUCER_NAME . "', model='sandbox', cr_ch_date=" . time()
        );

        $producer = new Producer();
        $producer->setName(self::PRODUCER_NAME);
        $producer->setLogo('/img/from-directory.webp');
        $producer->save();

        $mw = new ModelWeb(self::SANDBOX_MODEL_ID, 'ru');
        $mw->setLogoUrlAddress('/img/own-fallback.webp');

        $p = new L3Page('ru');
        $p->model = Model::getById(self::SANDBOX_MODEL_ID);
        $p->modelWeb = $mw;

        $this->assertSame('/img/from-directory.webp', $p->getProducerLogoUrl());
    }
}
