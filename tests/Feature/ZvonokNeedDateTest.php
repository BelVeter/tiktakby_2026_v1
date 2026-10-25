<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use bb\Db;
use Tests\TestCase;

/**
 * Форма «Оставить заявку» на карточке товара: клиент может указать, с какой даты нужен товар.
 * Дата ложится в rent_orders.planned_date (📅 и сортировка в «Заявках»), дублируется текстом
 * в info (видно в «Звонках»), а срок заявки продлевается, чтобы она не истекла раньше даты.
 */
class ZvonokNeedDateTest extends TestCase
{
    private $conn;
    private array $phones = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->conn = Db::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->phones as $p) {
            $this->conn->query("DELETE FROM rent_orders WHERE phone=" . (int)$p);
            $this->conn->query("DELETE FROM zvonki WHERE phone='" . (int)$p . "'");
        }
        parent::tearDown();
    }

    private function send(string $phone, array $extra = []): array
    {
        $this->phones[] = $phone;
        $this->post('/zvonok', array_merge(
            ['fio' => '__TEST__ Дата', 'phone' => $phone, 'info' => '', 'model_id' => 1],
            $extra
        ));
        $order = $this->conn->query("SELECT planned_date, validity, info FROM rent_orders WHERE phone=" . (int)$phone . " ORDER BY order_id DESC LIMIT 1")->fetch_assoc();
        $call = $this->conn->query("SELECT info FROM zvonki WHERE phone='" . (int)$phone . "' ORDER BY zv_id DESC LIMIT 1")->fetch_assoc();
        $this->assertNotNull($order, 'заявка должна создаться');
        $this->assertNotNull($call, 'звонок должен создаться');
        return [$order, $call];
    }

    public function test_need_date_goes_to_planned_date_info_and_validity(): void
    {
        $need = new \DateTime('+30 days');
        [$order, $call] = $this->send('79900000041', ['need_date' => $need->format('Y-m-d'), 'info' => 'нужен с адаптерами']);

        $this->assertSame($need->format('Y-m-d'), $order['planned_date']);
        $this->assertStringContainsString('Нужен с: ' . $need->format('d.m.Y'), $order['info']);
        $this->assertStringContainsString('нужен с адаптерами', $order['info']);
        $this->assertStringContainsString('Нужен с: ' . $need->format('d.m.Y'), $call['info']);
        $this->assertGreaterThanOrEqual((clone $need)->setTime(0, 0)->modify('+3 days')->getTimestamp(), (int)$order['validity']);
    }

    public function test_near_date_keeps_default_two_week_validity(): void
    {
        $need = new \DateTime('+2 days');
        [$order] = $this->send('79900000042', ['need_date' => $need->format('Y-m-d')]);

        $this->assertSame($need->format('Y-m-d'), $order['planned_date']);
        $this->assertGreaterThanOrEqual(time() + 13 * 86400, (int)$order['validity']);
    }

    public function test_no_date_leaves_planned_date_empty(): void
    {
        [$order, $call] = $this->send('79900000043');

        $this->assertNull($order['planned_date']);
        $this->assertStringNotContainsString('Нужен с', $order['info']);
        $this->assertStringNotContainsString('Нужен с', $call['info']);
    }

    public function test_past_or_garbage_date_is_ignored(): void
    {
        foreach (['79900000044' => (new \DateTime('-1 day'))->format('Y-m-d'), '79900000045' => '15.11', '79900000046' => '2026-02-30'] as $phone => $bad) {
            [$order, $call] = $this->send((string)$phone, ['need_date' => $bad]);
            $this->assertNull($order['planned_date'], $bad);
            $this->assertStringNotContainsString('Нужен с', $call['info'], $bad);
        }
    }
}
