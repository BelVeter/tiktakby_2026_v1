<?php
$sql = "
            SELECT 
                COALESCE(
                    (SELECT u.utm_source 
                     FROM rent_orders ro 
                     JOIN tiktak_utms u ON u.entity_type = 'rent_orders' AND u.entity_id = ro.order_id 
                     WHERE (RIGHT(c.phone_1, 7) = RIGHT(REGEXP_REPLACE(ro.phone, '[^0-9]', ''), 7) OR RIGHT(c.phone_2, 7) = RIGHT(REGEXP_REPLACE(ro.phone, '[^0-9]', ''), 7)) AND ro.phone != '' AND c.phone_1 != ''
                     ORDER BY u.created_at ASC LIMIT 1),
                    (SELECT u.utm_source 
                     FROM zvonki z 
                     JOIN tiktak_utms u ON u.entity_type = 'zvonki' AND u.entity_id = z.zv_id 
                     WHERE (RIGHT(c.phone_1, 7) = RIGHT(REGEXP_REPLACE(z.phone, '[^0-9]', ''), 7) OR RIGHT(c.phone_2, 7) = RIGHT(REGEXP_REPLACE(z.phone, '[^0-9]', ''), 7)) AND z.phone != '' AND c.phone_1 != ''
                     ORDER BY u.created_at ASC LIMIT 1),
                    'direct'
                ) AS source,
                COUNT(DISTINCT da.deal_id) AS cnt
            FROM (
            SELECT deal_id, client_id, item_inv_n, start_date, return_date,
                   delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid,
                   cr_time, first_rent_place, deal_status, planned_return_date,
                   last_sub_deal_ch_time, 'act' AS source
            FROM rent_deals_act
            UNION ALL
            SELECT deal_id, client_id, item_inv_n, start_date, return_date,
                   delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid,
                   cr_time, first_rent_place, deal_status, planned_return_date,
                   last_sub_deal_ch_time, 'arch' AS source
            FROM rent_deals_arch
            ) da
            LEFT JOIN clients c ON c.client_id = da.client_id
            WHERE da.cr_time > 0
            GROUP BY source
";

$start = microtime(true);
$res = \Illuminate\Support\Facades\DB::select($sql);
$end = microtime(true);
print_r($res);
echo "Time: " . ($end - $start) . "s\n";
