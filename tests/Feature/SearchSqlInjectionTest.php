<?php

namespace Tests\Feature;

use Tests\TestCase;

class SearchSqlInjectionTest extends TestCase
{
    public function test_search_with_single_quote_does_not_crash(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode("test' OR '1'='1"));
        // Не должно быть 500 (SQL error)
        $this->assertNotEquals(500, $response->status());
        // Должен вернуть нормальную страницу поиска
        $this->assertStringContainsString('поиск', strtolower($response->getContent()));
    }

    public function test_producer_filter_with_single_quote_does_not_crash(): void
    {
        $response = $this->get('/ru/producer?producer=' . urlencode("test' OR '1'='1"));
        $this->assertNotEquals(500, $response->status());
    }

    public function test_search_with_boolean_fulltext_chars_does_not_crash(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('+велосипед* -самокат'));
        $this->assertNotEquals(500, $response->status());
    }
}
