<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Приём отчётов CSP со страниц bb/ — без CSRF-токена (браузер его не шлёт), в отдельный лог.
 */
class CspReportTest extends TestCase
{
    public function test_browser_report_is_logged(): void
    {
        $channel = \Mockery::mock();
        $channel->shouldReceive('warning')->once()->with('csp', \Mockery::on(function ($ctx) {
            return $ctx['directive'] === 'img-src'
                && $ctx['blocked'] === 'https://spam.example/a.jpg'
                && $ctx['page'] === 'https://tiktak.by/bb/zv_ch.php';
        }));
        Log::shouldReceive('channel')->with('csp')->andReturn($channel);

        $body = json_encode(['csp-report' => [
            'document-uri' => 'https://tiktak.by/bb/zv_ch.php',
            'effective-directive' => 'img-src',
            'blocked-uri' => 'https://spam.example/a.jpg',
            'line-number' => 470,
        ]]);
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], $body)
            ->assertStatus(204);
    }

    public function test_garbage_is_ignored(): void
    {
        Log::shouldReceive('channel')->never();
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'not json')
            ->assertStatus(204);
    }
}
