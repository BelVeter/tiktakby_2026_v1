<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class ZvonokRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_redirect_appends_ck_param_clean_url()
    {
        $response = $this->withHeaders(['Referer' => 'https://tiktak.by/ru/velosipedy/'])
            ->post('/zvonok', ['fio' => 'Тест', 'phone' => '+375447454040', 'info' => '']);
        $this->assertTrue(
            str_contains($response->headers->get('Location') ?? '', 'ck=zvonok'),
            'Location header must contain ck=zvonok'
        );
    }

    public function test_redirect_appends_ck_param_url_with_existing_query()
    {
        $response = $this->withHeaders(['Referer' => 'https://tiktak.by/ru/velosipedy/?age=3'])
            ->post('/zvonok', ['fio' => 'Тест', 'phone' => '+375447454040', 'info' => '']);
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('ck=zvonok', $location);
        $this->assertStringNotContainsString('?ck=zvonok', $location,
            'Should use & not ? when query string already present');
    }

    public function test_redirect_falls_back_to_root_when_no_referer()
    {
        $response = $this->post('/zvonok', ['fio' => 'Тест', 'phone' => '+375447454040', 'info' => '']);
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('ck=zvonok', $location);
    }
}
