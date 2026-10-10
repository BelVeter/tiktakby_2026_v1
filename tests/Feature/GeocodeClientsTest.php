<?php

namespace Tests\Feature;

use App\Console\Commands\GeocodeClients;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * geo_status=2 («адрес не распознан») можно ставить только по содержательному ответу Google.
 *
 * С 31.07.2026 Google отвечал REQUEST_DENIED (у проекта нет биллинга), а команда записывала
 * в «нераспознанные» каждый адрес подряд: ~17,5 тыс. нормальных адресов за август–октябрь.
 */
class GeocodeClientsTest extends TestCase
{
    use DatabaseTransactions;

    private const IDS = [999999901, 999999902, 999999903];

    private function geocode(array $googleResponse): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response($googleResponse)]);

        $command = new GeocodeClients();
        $ref = new \ReflectionClass($command);
        foreach (['apiKey' => 'test-key', 'yandexApiKey' => null] as $prop => $value) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($command, $value);
        }
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput()
        ));

        $clients = collect(self::IDS)->map(function ($id) {
            return (object) ['client_id' => $id, 'city' => 'Минск', 'str' => 'пр. Независимости', 'dom' => '1', 'corrected_address' => null];
        });

        $m = $ref->getMethod('processClients');
        $m->setAccessible(true);
        $m->invoke($command, $clients);
    }

    private function statuses(): array
    {
        return DB::table('clients_geo')->whereIn('client_id', self::IDS)->pluck('geo_status', 'client_id')->all();
    }

    public function test_request_denied_does_not_mark_addresses_as_unresolvable(): void
    {
        $this->geocode(['status' => 'REQUEST_DENIED', 'error_message' => 'You must enable Billing', 'results' => []]);

        $this->assertSame([], $this->statuses(), 'При REQUEST_DENIED адрес не должен попадать в geo_status=2.');
        Http::assertSentCount(1); // Google не работает — дальше не спрашиваем, прогон прерывается
    }

    public function test_zero_results_still_marks_addresses_as_unresolvable(): void
    {
        $this->geocode(['status' => 'ZERO_RESULTS', 'results' => []]);

        $this->assertEquals(array_fill_keys(self::IDS, 2), $this->statuses());
    }

    public function test_ok_saves_coordinates(): void
    {
        $this->geocode(['status' => 'OK', 'results' => [['geometry' => ['location' => ['lat' => 53.9, 'lng' => 27.56]]]]]);

        $this->assertEquals(array_fill_keys(self::IDS, 1), $this->statuses());
    }
}
