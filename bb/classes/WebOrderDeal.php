<?php

namespace bb\classes;

use bb\Db;

require_once __DIR__ . '/Deal.php';
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/TariffModel.php';

/**
 * Договор и плановые выезды курьера по заказу с сайта.
 *
 * Заказ с доставкой сразу превращается в то же, что оператор создаёт руками
 * в bb/dogovor_new.php: сделка в rent_deals_act + подсделка `takeaway_plan`
 * со статусом `for_cur` на дату начала проката. Если клиент заказал возврат
 * курьером — добавляется вторая подсделка `cur_return` на дату окончания.
 * Обе попадают на bb/cur_page2.php: страница курьера отбирает записи
 * по `status='for_cur'` и `acc_date` нужного дня.
 *
 * Паспорт и прописку заказ не содержит — курьер дозаполняет их на месте
 * своей формой (bb/cur_page2.php). Карточку найденного клиента не трогаем:
 * адрес из заказа идёт в информацию выезда как «текущий адрес доставки».
 *
 * Вставки идут с явными списками колонок, а не позиционные: по docs/db_notes.md
 * позиционные INSERT в этих таблицах ломаются при любом ALTER TABLE ADD COLUMN.
 */
class WebOrderDeal
{
    /** Особый статус товара на время плановой доставки (см. bb/dogovor_new.php) */
    public const ITEM_STATUS_TO_DELIVER = 'to_deliver';

    /** Порядок сортировки подсделок в интерфейсе курьера */
    private const SORT_TAKEAWAY_PLAN = 5;
    private const SORT_CUR_RETURN = 80;

    /**
     * Находит клиента по телефону или заводит нового.
     *
     * Поиск — по последним 9 цифрам обоих телефонов (Client::findByNumber).
     * Найденного НЕ трогаем: у него уже выверены паспорт, прописка и адрес.
     *
     * @return int client_id, 0 — если завести не удалось
     */
    public static function findOrCreateClient(string $fio, string $phone, string $address): int
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 9) {
            $found = Client::findByNumber($digits);
            if ($found && (int) $found->client_id > 0) {
                return (int) $found->client_id;
            }
        }

        $mysqli = Db::getInstance()->getConnection();

        // ФИО в заказе одной строкой: «Фамилия Имя Отчество»
        $parts = preg_split('/\s+/u', trim($fio), 3);
        $family = $mysqli->real_escape_string(mb_convert_case($parts[0] ?? '', MB_CASE_TITLE, 'UTF-8'));
        $name = $mysqli->real_escape_string(mb_convert_case($parts[1] ?? '', MB_CASE_TITLE, 'UTF-8'));
        $otch = $mysqli->real_escape_string(mb_convert_case($parts[2] ?? '', MB_CASE_TITLE, 'UTF-8'));

        if ($family === '') {
            return 0;
        }

        // Разобрать свободную строку адреса на город/улицу/дом/квартиру нельзя надёжно,
        // поэтому кладём её целиком в улицу — курьер увидит адрес полностью
        $str = $mysqli->real_escape_string(trim($address));
        $phoneEsc = $mysqli->real_escape_string($digits);

        $query = "INSERT INTO clients (family, name, otch, city, str, dom, kv, phone_1, info, cr_time, cr_who)
            VALUES ('$family', '$name', '$otch', '', '$str', '', '', '$phoneEsc', 'Заведён автоматически по заказу с сайта', '" . time() . "', '0')";

        if (!$mysqli->query($query)) {
            error_log('WebOrderDeal: не удалось завести клиента: ' . $mysqli->error);
            return 0;
        }

        return (int) $mysqli->insert_id;
    }

    /**
     * Тариф позиции для подсделки.
     *
     * Срок всегда пишем в сутках, а не в шагах тарифа: сайт считает стоимость
     * от суточной ставки (TariffModel::getDaylyTarifForDaysPeriod), и запись
     * «14 суток по 4.14» сходится с суммой заказа. Если взять шаг из тарифа,
     * прокат на 14 дней по недельному тарифу превращается в «14 нед.».
     * tarif_id при этом указывает на реально применённую ступень.
     *
     * @return array{id:string, step:string, value:string}
     */
    public static function resolveTariff(?TariffModel $tarifModel, int $days): array
    {
        if (!$tarifModel || $days < 1) {
            return ['id' => '', 'step' => 'day', 'value' => '0.00'];
        }

        $best = null;
        foreach ($tarifModel->getTarifs() as $t) {
            $tierDays = $t->getDaysCalculatedNumber();
            if ($tierDays <= $days && ($best === null || $tierDays > $best->getDaysCalculatedNumber())) {
                $best = $t;
            }
        }

        return [
            'id' => $best ? (string) $best->tarif_id : '',
            'step' => 'day',
            'value' => number_format((float) $tarifModel->getDaylyTarifForDaysPeriod($days), 2, '.', ''),
        ];
    }

    /**
     * Создаёт сделку и выезды курьера по одной позиции заказа.
     *
     * @param array $item inv_n, start_ts, return_ts, days, r_to_pay, tarif (из resolveTariff)
     * @param float $deliveryCost стоимость доставки — ставится только одной позиции заказа
     * @param float $pickupCost стоимость возврата курьером; 0 — возврат не заказан
     * @return int deal_id, 0 при ошибке
     */
    public static function createDealWithTrips(int $clientId, array $item, float $deliveryCost, float $pickupCost, string $courierInfo): int
    {
        $mysqli = Db::getInstance()->getConnection();

        $invN = (int) $item['inv_n'];
        $startTs = (int) $item['start_ts'];
        $returnTs = (int) $item['return_ts'];
        $days = max(1, (int) $item['days']);
        $rToPay = number_format((float) $item['r_to_pay'], 2, '.', '');
        $delivery = number_format($deliveryCost, 2, '.', '');
        $pickup = number_format($pickupCost, 2, '.', '');
        $tarif = $item['tarif'];
        $info = $mysqli->real_escape_string($courierInfo);
        $now = time();

        if ($clientId <= 0 || $invN <= 0) {
            return 0;
        }

        // Комплект и офис берём у самого товара — так же делает мультидоговор
        $ri = $mysqli->query("SELECT item_set, item_place FROM tovar_rent_items WHERE item_inv_n = $invN");
        $itRow = $ri ? $ri->fetch_assoc() : null;
        $dealSet = $mysqli->real_escape_string((string) ($itRow['item_set'] ?? ''));
        $place = (int) ($itRow['item_place'] ?? 0);

        // deal_id иногда приходится задавать явно: в архиве может лежать запись
        // с тем же номером, который вот-вот выдаст AUTO_INCREMENT
        $safeDealId = Deal::getSafeDealIdForInsert();
        $idCol = $safeDealId !== '' ? 'deal_id, ' : '';
        $idVal = $safeDealId !== '' ? "'" . (int) $safeDealId . "', " : '';

        $dealQuery = "INSERT INTO rent_deals_act
            ($idCol client_id, item_inv_n, start_date, return_date, delivery_yn, delivery_to_pay, delivery_paid,
             r_to_pay, r_paid, collateral_amount, collateral_cur, deal_status, deal_info,
             acc_person_id, cr_who_id, cr_time, last_sub_deal_ch_time, planned_return_date, deal_set, first_rent_place)
            VALUES ($idVal '$clientId', '$invN', '$startTs', '$returnTs', '1', '$delivery', '0.00',
             '$rToPay', '0.00', '0.00', 'BYN', 'active', '$info',
             '0', '0', '$now', '$now', '$returnTs', '$dealSet', '$place')";

        if (!$mysqli->query($dealQuery)) {
            error_log('WebOrderDeal: не удалось создать сделку: ' . $mysqli->error);
            return 0;
        }
        $dealId = (int) $mysqli->insert_id;

        // Выезд на выдачу: попадёт к курьеру на дату начала проката
        self::insertSubDeal($dealId, [
            'type' => 'takeaway_plan',
            'sort' => self::SORT_TAKEAWAY_PLAN,
            'from' => $startTs,
            'to' => $returnTs,
            'acc_date' => $startTs,
            'tarif_id' => $tarif['id'],
            'tarif_step' => $tarif['step'],
            'tarif_value' => $tarif['value'],
            'rent_tenor' => $days,
            'r_to_pay' => $rToPay,
            'delivery_to_pay' => $delivery,
            'place' => $place,
            'info' => $info,
        ]);

        // Выезд на возврат — только если клиент заказал возврат курьером
        if ($pickupCost > 0) {
            self::insertSubDeal($dealId, [
                'type' => 'cur_return',
                'sort' => self::SORT_CUR_RETURN,
                'from' => $returnTs,
                'to' => 0,
                'acc_date' => $returnTs,
                'tarif_id' => '',
                'tarif_step' => '',
                'tarif_value' => '',
                'rent_tenor' => '',
                'r_to_pay' => '0.00',
                'delivery_to_pay' => $pickup,
                'place' => $place,
                'info' => $info,
            ]);
        }

        $mysqli->query("UPDATE tovar_rent_items SET status = '" . self::ITEM_STATUS_TO_DELIVER . "', active_deal_id = '$dealId' WHERE item_inv_n = $invN");

        return $dealId;
    }

    /**
     * Подсделка для страницы курьера: status='for_cur' + acc_date нужного дня.
     * Курьер не назначается — распределяет диспетчер.
     */
    private static function insertSubDeal(int $dealId, array $p): bool
    {
        $mysqli = Db::getInstance()->getConnection();

        $type = $mysqli->real_escape_string($p['type']);
        $tarifId = $mysqli->real_escape_string((string) $p['tarif_id']);
        $tarifStep = $mysqli->real_escape_string((string) $p['tarif_step']);
        $tarifValue = $mysqli->real_escape_string((string) $p['tarif_value']);
        $tenor = $mysqli->real_escape_string((string) $p['rent_tenor']);
        $to = (int) $p['to'] > 0 ? "'" . (int) $p['to'] . "'" : "''";

        $query = "INSERT INTO rent_sub_deals_act
            (deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor,
             r_to_pay, delivery_yn, delivery_to_pay, courier_id, `status`, `info`, cr_time, cr_who_id, acc_date, `place`)
            VALUES ('$dealId', '$type', '" . (int) $p['sort'] . "', '" . (int) $p['from'] . "', $to,
             '$tarifId', '$tarifStep', '$tarifValue', '$tenor',
             '" . $p['r_to_pay'] . "', '1', '" . $p['delivery_to_pay'] . "', '0', 'for_cur', '" . $p['info'] . "',
             '" . time() . "', '0', '" . (int) $p['acc_date'] . "', '" . (int) $p['place'] . "')";

        if (!$mysqli->query($query)) {
            error_log('WebOrderDeal: не удалось создать выезд ' . $p['type'] . ': ' . $mysqli->error);
            return false;
        }

        return true;
    }

    /**
     * Текст, который курьер видит в своей строке.
     *
     * Только то, чего нет в остальных колонках: комментарий клиента. Адрес,
     * телефон, срок и способ получения курьер и так видит в своих графах,
     * а возврат курьером виден отдельным выездом на дату окончания проката.
     */
    public static function courierInfo(string $comment): string
    {
        $comment = trim($comment);

        return $comment !== '' ? 'Инфо от клиента: ' . $comment : '';
    }
}
