<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Карточка брони собирается в двух местах: корзина (app/Http/Controllers/CartController.php)
 * пишет чипы в поле info, а панель (bb/rent_orders.php) поднимает их в шапку по маркеру.
 * Контракт между файлами держится на одной строке-маркере — тест ловит её расхождение
 * и проверяет, что разбор не рвёт вложенные чипы.
 */
class BronCardMarkupTest extends TestCase
{
    /** Тот же шаблон, что стоит в bb/rent_orders.php */
    private const CHIPS_PATTERN = '~^\s*<span class="bk-chips">.*?<!--/bk-chips-->~s';

    private const END_MARKER = '<!--/bk-chips-->';

    public function test_both_sides_use_the_same_marker(): void
    {
        $root = dirname(__DIR__, 2);

        $cart = file_get_contents($root . '/app/Http/Controllers/CartController.php');
        $panel = file_get_contents($root . '/bb/rent_orders.php');

        $this->assertStringContainsString(
            self::END_MARKER,
            $cart,
            'CartController перестал закрывать блок чипов маркером — панель не найдёт границу'
        );
        $this->assertStringContainsString(
            self::CHIPS_PATTERN,
            $panel,
            'В bb/rent_orders.php изменился разбор чипов — обновите шаблон и в этом тесте'
        );
    }

    /**
     * @dataProvider infoSamples
     */
    public function test_chips_are_split_off_without_breaking_tags(string $info, int $expectedChips, string $expectedBodyStart): void
    {
        $chips = '';
        $body = $info;
        if (preg_match(self::CHIPS_PATTERN, $info, $m)) {
            $chips = $m[0];
            $body = substr($info, strlen($m[0]));
        }

        $this->assertSame($expectedChips, substr_count($chips, 'class="bk-chip '));
        $this->assertSame(
            substr_count($chips, '<span'),
            substr_count($chips, '</span>'),
            'разбор разорвал вложенные теги чипов'
        );
        $this->assertStringStartsWith($expectedBodyStart, $body);
    }

    public function infoSamples(): array
    {
        $two = '<span class="bk-chips">'
            . '<span class="bk-chip bk-chip--pay">Возврат курьером</span>'
            . '<span class="bk-chip bk-chip--ch">Viber</span>'
            . '</span>' . self::END_MARKER;
        $one = '<span class="bk-chips"><span class="bk-chip bk-chip--ch">SMS</span></span>' . self::END_MARKER;

        return [
            'два чипа' => [$two . '<div>тело</div>', 2, '<div>тело</div>'],
            'один чип' => [$one . '<div>тело</div>', 1, '<div>тело</div>'],
            'заказ без чипов' => ['<div>тело</div>', 0, '<div>тело</div>'],
            'легаси-запись оператора' => ['14.08 звонила, переносим на след. неделю', 0, '14.08 звонила'],
        ];
    }
}
