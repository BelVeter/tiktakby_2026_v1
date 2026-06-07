<?php

namespace Tests\Feature\Mcp;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class SmsAccessTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Setup valid MCP token
        Config::set('mcp.api_token', 'test-token');
    }

    public function test_sms_send_fails_from_external_ip(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
        ])->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->postJson('/api/mcp/v1/sms/send', [
            'phone' => '375291234567',
            'text' => 'Test'
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['error' => 'Forbidden']);
    }

    public function test_sms_send_succeeds_from_local_ip(): void
    {
        // Mock the RocketSMS class or just expect validation error or 400 since credentials aren't set
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
        ])->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->postJson('/api/mcp/v1/sms/send', [
            'phone' => '375291234567',
            'text' => 'Test'
        ]);

        // Should not be 403. Will probably be 400 because of invalid SMS credentials in test
        $this->assertNotEquals(403, $response->status());
    }
}
