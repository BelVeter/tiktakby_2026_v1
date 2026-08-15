<?php

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use Tests\TestCase;

/**
 * Тарифы доставки продублированы в JS корзины (resources/views/cart/index.blade.php),
 * поэтому пороги закреплены тестом — чтобы расхождение серверного и клиентского
 * расчёта не уехало в прод незамеченным.
 */
class CartDeliveryCostTest extends TestCase
{
    /**
     * @dataProvider minskCases
     */
    public function test_minsk_delivery_cost(float $itemsTotal, float $expected): void
    {
        $this->assertSame($expected, CartController::calcDeliveryCost($itemsTotal, false));
    }

    public function minskCases(): array
    {
        return [
            'от 30 руб — бесплатно' => [40.0, 0.0],
            'ровно 30 руб — бесплатно' => [30.0, 0.0],
            'чуть меньше 30 руб' => [29.99, 10.0],
            'ровно 15 руб' => [15.0, 10.0],
            'чуть меньше 15 руб' => [14.99, 15.0],
            'пустой заказ' => [0.0, 15.0],
        ];
    }

    /**
     * @dataProvider suburbCases
     */
    public function test_suburb_delivery_cost(float $itemsTotal, float $expected): void
    {
        $this->assertSame($expected, CartController::calcDeliveryCost($itemsTotal, true));
    }

    public function suburbCases(): array
    {
        return [
            'от 50 руб — бесплатно' => [60.0, 0.0],
            'ровно 50 руб — бесплатно' => [50.0, 0.0],
            'чуть меньше 50 руб' => [49.99, 10.0],
            'дешёвый заказ' => [10.0, 10.0],
        ];
    }

    public function test_courier_pickup_is_always_paid(): void
    {
        $this->assertSame(10.0, CartController::COURIER_PICKUP_COST);
    }
}
