<?php

namespace Tests\Feature;

use App\Http\Middleware\SanitizeClientInput;
use App\Http\Middleware\VerifyCsrfToken;
use bb\Db;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Текст из форм сайта (заявка, звонок, бронь, корзина, карнавал, подписка) админка `bb/`
 * во многих местах печатает без экранирования — в т.ч. внутри value="…". Поэтому теги и
 * символы, которыми можно выйти из HTML-текста или атрибута, гасятся ещё на входе.
 */
class ClientInputSanitizeTest extends TestCase
{
    private const PHONE = '79900000051';

    protected function tearDown(): void
    {
        $conn = Db::getInstance()->getConnection();
        $conn->query("DELETE FROM rent_orders WHERE phone=" . self::PHONE);
        $conn->query("DELETE FROM zvonki WHERE phone LIKE '%" . self::PHONE . "%'");
        parent::tearDown();
    }

    /** @dataProvider cleanCases */
    public function test_clean(string $in, string $expected): void
    {
        $this->assertSame($expected, SanitizeClientInput::cleanText($in));
    }

    public function cleanCases(): array
    {
        return [
            'script' => ['<script>alert(1)</script>Привет', 'alert(1)Привет'],
            'img handler' => ['<img src=x onerror=alert(1)>текст', 'текст'],
            'spam link keeps text' => ['<a href=https://spam.example>окна</a> дёшево', 'окна дёшево'],
            'split tag' => ['<scr<script>ipt>alert(1)', '‹script›alert(1)'],
            'lone less-than' => ['рост <100 см', 'рост ‹100 см'],
            'attribute breakout' => ['" autofocus onfocus="alert(1)', '” autofocus onfocus=”alert(1)'],
            'apostrophe' => ["Мар'яна", 'Мар’яна'],
            'plain text untouched' => ['Коляска на 2 недели, рост 100-110, к 15:00 & с адаптерами', 'Коляска на 2 недели, рост 100-110, к 15:00 & с адаптерами'],
        ];
    }

    public function test_phone_keeps_only_phone_characters(): void
    {
        $this->assertSame('+375 (29) 123-45-67', SanitizeClientInput::cleanPhone('+375 (29) 123-45-67<b>"x\''));
    }

    public function test_request_form_stores_no_markup(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->post('/zvonok', [
            'fio' => '"><script>alert(1)</script>Иван',
            'phone' => self::PHONE . '<img src=x onerror=alert(1)>',
            'info' => '<img src=x onerror=alert(1)>нужна с адаптерами',
            'model_id' => 1,
        ]);

        $conn = Db::getInstance()->getConnection();
        $call = $conn->query("SELECT z_name, phone, info FROM zvonki WHERE phone LIKE '%" . self::PHONE . "%' ORDER BY zv_id DESC LIMIT 1")->fetch_assoc();
        $order = $conn->query("SELECT family, info FROM rent_orders WHERE phone=" . self::PHONE . " ORDER BY order_id DESC LIMIT 1")->fetch_assoc();
        $this->assertNotNull($call);
        $this->assertNotNull($order);

        $this->assertSame('”›alert(1)Иван', $call['z_name']);
        $this->assertSame(self::PHONE, $call['phone']);
        $this->assertSame('нужна с адаптерами', $call['info']);
        $this->assertSame('”›alert(1)Иван', $order['family']);
        $this->assertSame('нужна с адаптерами', $order['info']);
    }

    /**
     * Каждый публичный POST сайта должен идти через client.input — новая форма без него
     * снова откроет дыру. Исключения — маршруты, которые ничего из текста клиента не сохраняют.
     */
    public function test_every_public_post_route_is_sanitized(): void
    {
        $noFreeText = ['favorites/cards', 'cart/tariffs', 'cart/check-availability', 'track-event'];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (!in_array('POST', $route->methods()) || \Illuminate\Support\Str::startsWith($route->uri(), 'api/')) continue;
            if (in_array($route->uri(), $noFreeText)) continue;
            // Route::redirect отвечает на любой метод, но ничего не сохраняет
            if (ltrim($route->getActionName(), "\\") === \Illuminate\Routing\RedirectController::class) continue;
            $checked++;
            $this->assertContains('client.input', $route->gatherMiddleware(), $route->uri());
        }
        $this->assertGreaterThanOrEqual(6, $checked);
    }
}
