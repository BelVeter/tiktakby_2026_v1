<?php

namespace Tests\Feature\Mcp;

class HealthTest extends McpTestCase
{
    public function test_health_returns_envelope(): void
    {
        $r = $this->mcp('health');
        $this->assertEnvelope($r);
        $r->assertJsonPath('data.status', 'ok');
    }

    public function test_health_requires_token(): void
    {
        $this->getJson('/api/mcp/v1/health')->assertStatus(401);
    }
}
