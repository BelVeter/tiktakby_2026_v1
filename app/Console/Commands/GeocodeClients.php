<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class GeocodeClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geo:geocode-clients {--mode=regular : The mode to run in (regular, quota-filler)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Geocode client addresses using Google Maps API';

    private $apiKey;
    private $yandexApiKey;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Use config() — env() returns null after php artisan config:cache
        $this->apiKey = config('services.google_maps.api_key');
        $this->yandexApiKey = config('services.yandex_maps.api_key');
        
        if (!$this->apiKey) {
            $this->error('GOOGLE_MAPS_API_KEY is not set (check config/services.php and .env)');
            return 1;
        }

        $mode = $this->option('mode');

        if ($mode === 'quota-filler') {
            $this->runQuotaFiller();
        } else {
            $this->runRegularSync();
        }

        return 0;
    }

    private function runRegularSync()
    {
        $this->info('Starting regular sync mode...');

        // Find active clients
        // 1. Newly created (cr_time in last 24h)
        $recentlyCreatedIds = DB::table('clients')
            ->where('cr_time', '>=', time() - 86400)
            ->pluck('client_id')->toArray();

        // 2. Active deals (from rent_deals_act)
        $activeDealsClientIds = DB::table('rent_deals_act')
            ->pluck('client_id')->toArray();

        // 3. Sub-deals activity in last 24h (from rent_sub_deals_act)
        $recentSubDealDealIds = DB::table('rent_sub_deals_act')
            ->where('cr_time', '>=', time() - 86400)
            ->pluck('deal_id')->toArray();
            
        $recentSubDealsClientIds = empty($recentSubDealDealIds) ? [] : DB::table('rent_deals_act')
            ->whereIn('deal_id', $recentSubDealDealIds)
            ->pluck('client_id')->toArray();

        $clientIds = array_unique(array_merge($recentlyCreatedIds, $activeDealsClientIds, $recentSubDealsClientIds));

        if (empty($clientIds)) {
            $this->info('No active clients found.');
            return;
        }

        // Regular mode: include geo_status=2 (failed) too, but only retry them once every 7 days.
        // We always want fresh coordinates for active clients if they were never geocoded or if they changed.
        $clientsToGeocode = DB::table('clients')
            ->leftJoin('clients_geo', 'clients.client_id', '=', 'clients_geo.client_id')
            ->whereIn('clients.client_id', $clientIds)
            ->where(function($q) {
                $q->whereNull('clients_geo.geo_status')
                  ->orWhere('clients_geo.geo_status', 0)
                  ->orWhere(function($q2) {
                      $q2->where('clients_geo.geo_status', 2)
                         ->where('clients_geo.geo_updated_at', '<', Carbon::now()->subDays(7));
                  });
            })
            ->select('clients.client_id', 'clients.city', 'clients.str', 'clients.dom')
            ->get();

        $this->info("Found {$clientsToGeocode->count()} active clients to geocode.");
        
        $this->processClients($clientsToGeocode);
    }

    private function runQuotaFiller()
    {
        $this->info('Starting quota filler mode...');

        $isLastDayOfMonth = Carbon::now()->format('Y-m-d') === Carbon::now()->endOfMonth()->format('Y-m-d');
        if (!$isLastDayOfMonth) {
            $this->info('Quota filler mode only runs on the last day of the month to preserve quota for regular syncs.');
            return;
        }

        // 40,000 requests per month is the free tier.
        $totalQuota = 40000;

        // How many requests made this month?
        $usedQuota = DB::table('clients_geo')
            ->where('geo_status', '>', 0)
            ->whereRaw('MONTH(geo_updated_at) = MONTH(CURRENT_DATE)')
            ->whereRaw('YEAR(geo_updated_at) = YEAR(CURRENT_DATE)')
            ->count();

        $remainingQuota = $totalQuota - $usedQuota;
        if ($remainingQuota <= 0) {
            $this->info('No quota remaining this month.');
            return;
        }

        $limitToProcess = (int)($remainingQuota * 0.8);
        $this->info("Used: {$usedQuota}, Remaining: {$remainingQuota}, Processing up to: {$limitToProcess}");

        // Find clients without geo info
        $clientsToGeocode = DB::table('clients')
            ->leftJoin('clients_geo', 'clients.client_id', '=', 'clients_geo.client_id')
            ->where(function($q) {
                $q->whereNull('clients_geo.geo_status')
                  ->orWhere('clients_geo.geo_status', 0);
            })
            ->select('clients.client_id', 'clients.city', 'clients.str', 'clients.dom')
            ->limit($limitToProcess)
            ->get();

        $this->info("Found {$clientsToGeocode->count()} clients to geocode.");

        $this->processClients($clientsToGeocode);
    }

    private function processClients($clients)
    {
        $success = 0;
        $failed = 0;

        foreach ($clients as $client) {
            // Clean up address parts, strip control characters (e.g. \x01 in legacy data)
            $city = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $client->city));
            $str  = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $client->str));
            $dom  = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $client->dom));

            if (empty($city) && empty($str)) {
                // No usable address data at all
                $this->updateStatus($client->client_id, 2);
                $failed++;
                continue;
            }

            // Append country for better accuracy (prevents mismatches in other countries)
            $address = "{$city}, {$str} {$dom}, Беларусь";
            
            $url = "https://maps.googleapis.com/maps/api/geocode/json";
            
            try {
                $response = Http::get($url, [
                    'address' => $address,
                    'key' => $this->apiKey
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    $isGoogleSuccess = false;
                    $lat = null;
                    $lng = null;

                    if ($data['status'] === 'OK' && !empty($data['results'])) {
                        // Если Google нашел частичное совпадение (например, только город вместо улицы)
                        $isPartial = !empty($data['results'][0]['partial_match']);
                        
                        // Если совпадение полное, берем координаты Google
                        if (!$isPartial) {
                            $location = $data['results'][0]['geometry']['location'];
                            $lat = $location['lat'];
                            $lng = $location['lng'];
                            $isGoogleSuccess = true;
                        }
                    } elseif (in_array($data['status'], ['OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT'])) {
                        $this->error("Google Quota exceeded. Aborting.");
                        break;
                    }

                    // Если Google не нашел или нашел только частично, пробуем Яндекс (если есть ключ)
                    if (!$isGoogleSuccess && $this->yandexApiKey) {
                        $this->info("Google fallback for client {$client->client_id}. Trying Yandex...");
                        
                        $yandexUrl = "https://geocode-maps.yandex.ru/1.x/";
                        $yandexResponse = Http::get($yandexUrl, [
                            'apikey' => $this->yandexApiKey,
                            'format' => 'json',
                            'geocode' => $address
                        ]);

                        if ($yandexResponse->successful()) {
                            $yData = $yandexResponse->json();
                            $featureMembers = $yData['response']['GeoObjectCollection']['featureMember'] ?? [];
                            
                            if (!empty($featureMembers)) {
                                // Yandex возвращает координаты в формате "lng lat" (через пробел)
                                $pos = $featureMembers[0]['GeoObject']['Point']['pos'] ?? '';
                                $coords = explode(' ', $pos);
                                if (count($coords) === 2) {
                                    $lng = $coords[0];
                                    $lat = $coords[1];
                                    $isGoogleSuccess = true; // Отмечаем как успех
                                }
                            }
                        }
                    }

                    // Финальное сохранение результатов
                    if ($isGoogleSuccess && $lat && $lng) {
                        $this->updateStatus($client->client_id, 1, $lat, $lng);
                        $success++;
                    } elseif ($data['status'] === 'ZERO_RESULTS' || $data['status'] === 'INVALID_REQUEST' || !$isGoogleSuccess) {
                        // The address is genuinely unresolvable by both engines
                        $this->updateStatus($client->client_id, 2);
                        $failed++;
                    } else {
                        // It's an API error like OVER_QUERY_LIMIT, REQUEST_DENIED, etc.
                        // We shouldn't mark the address as permanently failed. Just log and stop/skip.
                        $this->error("API Error for client {$client->client_id}: " . $data['status']);
                        if (in_array($data['status'], ['OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT'])) {
                            $this->error("Quota exceeded. Aborting.");
                            break; // Stop processing entirely
                        }
                    }
                } else {
                    $this->error("HTTP Request failed for client {$client->client_id}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to geocode client {$client->client_id}: " . $e->getMessage());
            }

            // Small delay to respect rate limits (Google Maps allows 50qps, but we can be nice)
            usleep(20000); // 20ms
        }

        $this->info("Done. Success: {$success}, Failed: {$failed}");
    }

    private function updateStatus($clientId, $status, $lat = null, $lng = null)
    {
        DB::table('clients_geo')->updateOrInsert(
            ['client_id' => $clientId],
            [
                'lat' => $lat,
                'lng' => $lng,
                'geo_status' => $status,
                'geo_updated_at' => Carbon::now()
            ]
        );
    }
}
