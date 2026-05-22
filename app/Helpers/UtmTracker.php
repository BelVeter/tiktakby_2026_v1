<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class UtmTracker
{
    /**
     * Get UTM parameters from cookies or session
     */
    public static function getUtmData(): array
    {
        $parameters = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $data = [];
        
        foreach ($parameters as $param) {
            $val = Cookie::get($param) ?? session()->get($param);
            if ($val) {
                $data[$param] = $val;
            }
        }
        
        return $data;
    }

    /**
     * Save UTM parameters for a given entity
     *
     * @param string $entityType e.g. 'rent_orders', 'clients', 'zvonki'
     * @param string|int $entityId
     * @return bool
     */
    public static function track(string $entityType, $entityId): bool
    {
        $utm = self::getUtmData();
        if (empty($utm)) {
            return false;
        }

        try {
            DB::table('tiktak_utms')->insert(array_merge([
                'entity_type' => $entityType,
                'entity_id'   => (string)$entityId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ], $utm));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
