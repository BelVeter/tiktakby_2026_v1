<?php

namespace Tests\Feature\Mcp;

class GeoTest extends McpTestCase
{
    public function test_clients_by_city(): void
    {
        $r = $this->mcp('geo/clients-by-city', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['city_norm', 'unique_clients', 'deals', 'revenue_byn']]]);
    }
}
