<?php

namespace App\Console\Commands;

use App\Http\Controllers\Feed2GisController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateFeed2Gis extends Command
{
    protected $signature = 'feed:generate-2gis';
    protected $description = 'Pre-generate and cache the 2GIS YML service feed';

    public function handle(): int
    {
        $this->info('Generating 2GIS feed...');

        Cache::forget('feed_2gis_xml');

        $controller = new Feed2GisController();
        $xml = $controller->buildFeed();

        Cache::put('feed_2gis_xml', $xml, 25 * 3600);

        $offerCount = substr_count($xml, '<offer ');
        $this->info("Done. {$offerCount} offers cached.");

        return 0;
    }
}
