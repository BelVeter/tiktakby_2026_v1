<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Feed2GisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('feed_2gis_xml');
    }

    public function test_feed_returns_200_and_xml_content_type(): void
    {
        $response = $this->get('/feed/2gis');

        $response->assertStatus(200);
        $this->assertStringContainsString('xml', $response->headers->get('Content-Type'));
    }

    public function test_feed_xml_has_required_root_elements(): void
    {
        $response = $this->get('/feed/2gis');

        $body = $response->getContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"', $body);
        $this->assertStringContainsString('<yml_catalog', $body);
        $this->assertStringContainsString('<shop>', $body);
        $this->assertStringContainsString('<offers>', $body);
        $this->assertStringContainsString('<categories>', $body);
    }

    public function test_feed_xml_is_valid_and_parseable(): void
    {
        $response = $this->get('/feed/2gis');

        $body = $response->getContent();
        $bodyNoDtd = preg_replace('/<!DOCTYPE[^>]*>/', '', $body);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($bodyNoDtd);
        $errors = array_map(fn($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $this->assertNotFalse($xml, 'Feed XML failed to parse: ' . implode('; ', $errors));
    }

    public function test_feed_contains_at_least_one_offer_with_positive_price(): void
    {
        $response = $this->get('/feed/2gis');

        $body = $response->getContent();
        $bodyNoDtd = preg_replace('/<!DOCTYPE[^>]*>/', '', $body);
        $xml = simplexml_load_string($bodyNoDtd);

        $this->assertNotFalse($xml);
        $offers = $xml->shop->offers->offer ?? [];
        $this->assertGreaterThan(0, count($offers), 'Feed has no offers');

        $hasPositivePrice = false;
        foreach ($offers as $offer) {
            if ((float)(string)$offer->price > 0) {
                $hasPositivePrice = true;
                break;
            }
        }
        $this->assertTrue($hasPositivePrice, 'No offer has a positive price');
    }

    public function test_feed_is_served_from_cache_on_second_request(): void
    {
        $this->get('/feed/2gis')->assertStatus(200);
        $this->assertTrue(Cache::has('feed_2gis_xml'), 'Cache was not populated after first request');

        Cache::put('feed_2gis_xml', '<!-- cached --><yml_catalog date="test"><shop><name>T</name><company>T</company><url>https://tiktak.by</url><currencies/><categories/><offers/></shop></yml_catalog>');
        $response2 = $this->get('/feed/2gis');
        $this->assertStringContainsString('<!-- cached -->', $response2->getContent());
    }

    public function test_refresh_param_regenerates_feed(): void
    {
        Cache::put('feed_2gis_xml', '<!-- stale -->');

        $response = $this->get('/feed/2gis?refresh=1');
        $response->assertStatus(200);
        $this->assertStringNotContainsString('<!-- stale -->', $response->getContent());
    }

    public function test_each_offer_has_name_url_price_currency(): void
    {
        $response = $this->get('/feed/2gis');

        $bodyNoDtd = preg_replace('/<!DOCTYPE[^>]*>/', '', $response->getContent());
        $xml = simplexml_load_string($bodyNoDtd);
        $this->assertNotFalse($xml);

        foreach ($xml->shop->offers->offer as $offer) {
            $id = (string)$offer['id'];
            $this->assertNotEmpty((string)$offer->name, "offer $id: missing <name>");
            $this->assertNotEmpty((string)$offer->url, "offer $id: missing <url>");
            $this->assertNotEmpty((string)$offer->currencyId, "offer $id: missing <currencyId>");
            $this->assertNotEmpty((string)$offer->price, "offer $id: missing <price>");
        }
    }
}
