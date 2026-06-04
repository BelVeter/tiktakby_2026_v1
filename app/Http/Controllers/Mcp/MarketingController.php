<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MarketingController extends BaseController
{
    /**
     * GET /api/mcp/v1/marketing/conversions
     */
    public function conversions(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp(); // These are UNIX timestamps
        $to   = $request->toTimestamp();
        
        // Convert unix timestamps to MySQL timestamps for created_at
        $fromDate = date('Y-m-d H:i:s', $from);
        $toDate   = date('Y-m-d H:i:s', $to);
        
        $utmSource = $request->input('utm_source');
        $utmCampaign = $request->input('utm_campaign');

        $query = DB::table('tiktak_utms as u')
            ->select(
                'u.created_at as date',
                'u.entity_type',
                'u.utm_source',
                'u.utm_medium',
                'u.utm_campaign',
                'u.utm_term',
                'z.z_name as zvonki_name',
                'z.phone as zvonki_phone',
                'z.info as zvonki_info',
                'z.status as zvonki_status',
                DB::raw("TRIM(CONCAT_WS(' ', ro.family, ro.name, ro.otch)) as ro_fio"),
                'ro.phone as ro_phone',
                'ro.status as ro_status',
                'ro.model_id as ro_model_id',
                'ro_rmw.item_name_main as ro_model_name',
                'ro.cat_id as ro_cat_id',
                'ro_trc.rent_cat_name as ro_cat_name',
                'kb.fio as kb_fio',
                'kb.phone1 as kb_phone',
                'kb.status as kb_status',
                'kz.phone as kz_phone',
                'kz.info as kz_info',
                'kz.model_id as kz_model_id',
                'kz_rmw.item_name_main as kz_model_name'
            )
            ->leftJoin('zvonki as z', function ($join) {
                $join->on('u.entity_id', '=', 'z.zv_id')
                     ->where('u.entity_type', '=', 'zvonki');
            })
            ->leftJoin('rent_orders as ro', function ($join) {
                $join->on('u.entity_id', '=', 'ro.order_id')
                     ->where('u.entity_type', '=', 'rent_orders');
            })
            ->leftJoin('rent_model_web as ro_rmw', function ($join) {
                $join->on('ro.model_id', '=', 'ro_rmw.model_id')
                     ->where('ro_rmw.lang', '=', 'ru');
            })
            ->leftJoin('tovar_rent_cat as ro_trc', 'ro.cat_id', '=', 'ro_trc.tovar_rent_cat_id')
            ->leftJoin('karn_brons as kb', function ($join) {
                $join->on('u.entity_id', '=', 'kb.kb_id')
                     ->where('u.entity_type', '=', 'karn_brons');
            })
            ->leftJoin('kb_zayavki as kz', function ($join) {
                $join->on('u.entity_id', '=', 'kz.id')
                     ->where('u.entity_type', '=', 'kb_zayavki');
            })
            ->leftJoin('rent_model_web as kz_rmw', function ($join) {
                $join->on('kz.model_id', '=', 'kz_rmw.model_id')
                     ->where('kz_rmw.lang', '=', 'ru');
            })
            ->whereBetween('u.created_at', [$fromDate, $toDate]);

        if ($utmSource) {
            $query->where('u.utm_source', $utmSource);
        }
        if ($utmCampaign) {
            $query->where('u.utm_campaign', $utmCampaign);
        }

        $query->orderBy('u.created_at', 'desc');

        $results = $query->get()->map(function ($row) {
            $fio = null;
            $phone = null;
            $info = null;
            $status = null;
            $model_id = null;
            $model_name = null;
            $cat_id = null;
            $cat_name = null;

            switch ($row->entity_type) {
                case 'zvonki':
                    $fio = $row->zvonki_name;
                    $phone = $row->zvonki_phone;
                    $info = $row->zvonki_info;
                    $status = $row->zvonki_status;
                    break;
                case 'rent_orders':
                    $fio = $row->ro_fio;
                    $phone = $row->ro_phone;
                    $status = $row->ro_status;
                    $model_id = $row->ro_model_id;
                    $model_name = $row->ro_model_name;
                    $cat_id = $row->ro_cat_id;
                    $cat_name = $row->ro_cat_name;
                    break;
                case 'karn_brons':
                    $fio = $row->kb_fio;
                    $phone = $row->kb_phone;
                    $status = $row->kb_status;
                    break;
                case 'kb_zayavki':
                    $phone = $row->kz_phone;
                    $info = $row->kz_info;
                    $model_id = $row->kz_model_id;
                    $model_name = $row->kz_model_name;
                    break;
            }

            return [
                'date'         => $row->date,
                'entity_type'  => $row->entity_type,
                'utm_source'   => $row->utm_source,
                'utm_medium'   => $row->utm_medium,
                'utm_campaign' => $row->utm_campaign,
                'utm_term'     => $row->utm_term,
                'fio'          => $fio,
                'phone'        => $phone,
                'info'         => $info,
                'status'       => $status,
                'model_id'     => $model_id,
                'model_name'   => $model_name,
                'cat_id'       => $cat_id,
                'cat_name'     => $cat_name,
            ];
        });

        return $this->envelope($request->queryEcho(), $results);
    }
}
