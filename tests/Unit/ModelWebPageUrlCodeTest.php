<?php

namespace Tests\Unit;

use bb\classes\ModelWeb;
use PHPUnit\Framework\TestCase;

/**
 * URL код страницы (`rent_model_web.page_addr`) — только символы, которые не нужно кодировать в адресе.
 *
 * Браузер убирает лишнее при вводе (bb/assets/js/model_web_new.js), но это только удобство: вставка мышью,
 * отключённый JS или прямой POST его обходят. Так в базе оказались модели 558 (`laugh_&_learn_smart_stages_home`)
 * и 1465 (`pelenalnyj_stolik _s_vannochkoj_cam_cambio`) — битый canonical, 404 у краулеров на `&amp;`.
 * ModelWeb::isValidPageUrlCode — правило для сервера, а save()/update()/updateUrlKey() не пишут в БД мимо него.
 */
class ModelWebPageUrlCodeTest extends TestCase
{
    /**
     * @dataProvider validCodes
     */
    public function test_accepts_url_safe_codes(string $code): void
    {
        $this->assertTrue(ModelWeb::isValidPageUrlCode($code), $code);
    }

    public function validCodes(): array
    {
        return [
            'обычный'                 => ['pelenalnyj_stolik_s_vannochkoj_cam_cambio'],
            'дефис и цифры'           => ['begovel-naprokat-minsk-2'],
            'заглавные (их много в БД)' => ['MedAll_Zepter'],
            'точка (модель 1727)'     => ['mojka-vysoskogo-davleniya-karcherK3.500'],
            'начинается с подчёркивания' => ['__phpunit_dup_slug__'],
            'одна цифра'              => ['7'],
        ];
    }

    /**
     * @dataProvider invalidCodes
     */
    public function test_rejects_everything_else($code): void
    {
        $this->assertFalse(ModelWeb::isValidPageUrlCode($code), var_export($code, true));
    }

    public function invalidCodes(): array
    {
        return [
            'пробел (модель 1465)'    => ['pelenalnyj_stolik _s_vannochkoj_cam_cambio'],
            'амперсанд (модель 558)'  => ['laugh_&_learn_smart_stages_home'],
            'пусто'                   => [''],
            'только пробелы'          => ['   '],
            'только знаки'            => ['-_.'],
            'кириллица'               => ['коляска'],
            'слэш'                    => ['a/b'],
            'кавычка'                 => ["a'b"],
            'процент'                 => ['a%20b'],
            'перевод строки в конце'  => ["slug\n"],
            'не строка'               => [null],
        ];
    }

    public function test_writers_refuse_an_invalid_code_before_touching_the_database(): void
    {
        foreach (['save', 'update', 'updateUrlKey'] as $method) {
            $mw = new ModelWeb(990099, 'ru');
            $mw->setPageUrlCode('bad slug&');

            try {
                $mw->$method();
                $this->fail("ModelWeb::$method() записал недопустимый URL код.");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('URL код', $e->getMessage());
            }
        }
    }
}
