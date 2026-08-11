<?php

namespace App\Http\Controllers;

use App\MyClasses\L2ModelWeb;
use bb\classes\bron;
use bb\classes\TariffModel;
use bb\classes\tovar;
use bb\classes\Zvonok;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /** Стоимость выезда курьера за товаром — платная всегда, независимо от суммы заказа */
    public const COURIER_PICKUP_COST = 10.0;

    /**
     * Стоимость доставки от суммы заказа.
     * Минск: от 30 руб — бесплатно, от 15 до 30 — 10 руб, до 15 — 15 руб.
     * Ближний пригород: от 50 руб — бесплатно, иначе 10 руб.
     * Дублируется в JS корзины (resources/views/cart/index.blade.php) — менять синхронно.
     */
    public static function calcDeliveryCost(float $itemsTotal, bool $isSuburb): float
    {
        if ($isSuburb) {
            return $itemsTotal >= 50 ? 0.0 : 10.0;
        }
        if ($itemsTotal >= 30) {
            return 0.0;
        }
        if ($itemsTotal >= 15) {
            return 10.0;
        }
        return 15.0;
    }

    /**
     * Show the cart page shell (items populated from localStorage via JS)
     */
    public function index()
    {
        return view('cart.index');
    }

    /**
     * AJAX endpoint: returns tariff data for given model IDs
     * Used when cart page needs to recalculate prices server-side for validation
     */
    public function getTariffs(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['tariffs' => []]);
        }

        $ids = array_slice($ids, 0, 10); // Max 10 items
        $result = [];

        foreach ($ids as $id) {
            $id = intval($id);
            if ($id <= 0)
                continue;

            $tarifModel = TariffModel::getTarifModelForModelId($id);
            if (!$tarifModel)
                continue;

            $tariffs = [];
            foreach ($tarifModel->getTarifs() as $t) {
                $daysNum = $t->getDaysCalculatedNumber();
                if ($daysNum > 0) {
                    $dailyRate = round($t->getTotalAmount() / $daysNum, 2);
                    $tariffs[] = [$daysNum, $dailyRate];
                }
            }

            // Sort ascending by days threshold
            usort($tariffs, function ($a, $b) {
                return $a[0] - $b[0];
            });

            // Check availability
            $freeOffices = tovar::getFreeItemsOfficeArrayForModelId($id);
            $hasAvailability = is_array($freeOffices) && count($freeOffices) > 0;

            $result[$id] = [
                'tariffs' => $tariffs,
                'available' => $hasAvailability,
            ];
        }

        return response()->json(['tariffs' => $result]);
    }

    /**
     * AJAX endpoint: check availability for a single model
     * Used when adding to cart from L2 listing
     */
    public function checkAvailability(Request $request)
    {
        $modelId = intval($request->input('model_id', 0));

        if ($modelId <= 0) {
            return response()->json(['available' => false]);
        }

        $freeOffices = tovar::getFreeItemsOfficeArrayForModelId($modelId);
        $hasAvailability = is_array($freeOffices) && count($freeOffices) > 0;

        // Get expected return date if not available
        $returnDate = null;
        if (!$hasAvailability) {
            $rd = tovar::getEarliestReturnDateForModelId($modelId);
            if ($rd) {
                $months = [
                    'января',
                    'февраля',
                    'марта',
                    'апреля',
                    'мая',
                    'июня',
                    'июля',
                    'августа',
                    'сентября',
                    'октября',
                    'ноября',
                    'декабря'
                ];
                $day = $rd->format('j');
                $monthIndex = (int) $rd->format('n') - 1;
                $returnDate = $day . ' ' . $months[$monthIndex];
            }
        }

        return response()->json([
            'available' => $hasAvailability,
            'returnDate' => $returnDate,
        ]);
    }

    /**
     * Checkout: validate cart, create bookings
     * Accepts cart data from client, validates server-side, creates bookings
     */
    public function checkout(Request $request)
    {
        $items = $request->input('items', []);
        $fio = $request->input('fio', '');
        $phone = $request->input('phone', '');
        $delivery = $request->input('delivery', null);
        $address = $request->input('address', '');
        $info = $request->input('info', '');
        $isSuburb = (bool) $request->input('suburb', 0);
        $wantsCourierPickup = (bool) $request->input('courier_pickup', 0);

        // Validation
        $errors = [];

        if (!is_array($items) || empty($items)) {
            $errors[] = 'Корзина пуста';
        }

        if (mb_strlen($fio) < 3) {
            $errors[] = 'Укажите ФИО (не менее 3-х символов)';
        }

        $phoneDigits = preg_replace('/\D/', '', $phone);
        if (strlen($phoneDigits) < 7) {
            $errors[] = 'Укажите корректный номер телефона';
        }

        if ($delivery === null) {
            $errors[] = 'Выберите способ получения';
        }

        if ($delivery == '1' && mb_strlen($address) < 5) {
            $errors[] = 'Укажите адрес доставки';
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        $isDelivery = ($delivery == '1');

        // Courier pickup is a delivery-only service
        if (!$isDelivery) {
            $isSuburb = false;
            $wantsCourierPickup = false;
        }

        // Delivery cost depends on the whole order sum — recalculate it server-side
        // before creating any booking (never trust the client).
        $itemsTotal = 0.0;
        foreach ($items as $item) {
            $modelId = intval($item['modelId'] ?? 0);
            if ($modelId <= 0) {
                continue;
            }
            $tm = TariffModel::getTarifModelForModelId($modelId);
            if ($tm) {
                $itemsTotal += (float) $tm->getAmmountForDaysPeriod(intval($item['days'] ?? 14));
            }
        }

        $deliveryCost = $isDelivery ? self::calcDeliveryCost($itemsTotal, $isSuburb) : 0.0;
        $pickupCost = $wantsCourierPickup ? self::COURIER_PICKUP_COST : 0.0;
        $grandTotal = $itemsTotal + $deliveryCost + $pickupCost;

        // Short summary appended to every booking of this order, so the operator sees
        // the delivery terms the client agreed to in bb/.
        $orderSummary = ' Заказ: ' . number_format($itemsTotal, 2) . ' BYN.';
        if ($isDelivery) {
            $orderSummary .= ' Доставка' . ($isSuburb ? ' (ближний пригород)' : ' (Минск)') . ': '
                . ($deliveryCost > 0 ? number_format($deliveryCost, 2) . ' BYN' : 'бесплатно') . '.';
            $orderSummary .= ' Возврат курьером: '
                . ($wantsCourierPickup ? number_format($pickupCost, 2) . ' BYN' : 'не заказан') . '.';
        } else {
            $orderSummary .= ' Самовывоз (Литературная 22).';
        }
        $orderSummary .= ' Итого к оплате: ' . number_format($grandTotal, 2) . ' BYN.';

        // Process each item
        $results = [];
        $allSuccess = true;

        foreach ($items as $item) {
            $modelId = intval($item['modelId'] ?? 0);
            $days = intval($item['days'] ?? 14);
            $dateFrom = $item['dateFrom'] ?? date('Y-m-d');

            if ($modelId <= 0)
                continue;

            // Recalculate price server-side (never trust client)
            $tarifModel = TariffModel::getTarifModelForModelId($modelId);
            $totalAmount = $tarifModel ? $tarifModel->getAmmountForDaysPeriod($days) : 0;
            $dailyRate = $tarifModel ? $tarifModel->getDaylyTarifForDaysPeriod($days) : 0;

            // Check availability
            $freeOffices = tovar::getFreeItemsOfficeArrayForModelId($modelId);
            $hasAvailability = is_array($freeOffices) && count($freeOffices) > 0;

            if ($hasAvailability) {
                try {
                    $dateFromObj = new \DateTime($dateFrom);
                    $dateToObj = new \DateTime($dateFrom);
                    $dateToObj->modify('+' . $days . ' days');

                    $deliveryYN = $isDelivery ? 1 : 0;

                    if ($deliveryYN == 1) {
                        $freeItems = tovar::getFreeTovarsForModelIdAndOffice($modelId, 'all');
                    } else {
                        $office = $request->input('office', null);
                        if ($office && in_array($office, $freeOffices)) {
                            $freeItems = tovar::getFreeTovarsForModelIdAndOffice($modelId, $office);
                        } else {
                            $freeItems = tovar::getFreeTovarsForModelIdAndOffice($modelId, 'all');
                        }
                    }

                    if (!empty($freeItems)) {
                        $tovar = $freeItems[0];
                        $techInfo = 'Заказ через корзину. С ' . $dateFromObj->format('d.m.Y')
                            . ' по ' . $dateToObj->format('d.m.Y')
                            . ' на ' . $days . ' дн. Сумма: ' . number_format($totalAmount, 2) . ' BYN.'
                            . $orderSummary;

                        $fullInfo = $techInfo . ($info ? '<br>' . $info : '');

                        $br = bron::createBronStrong(
                            $tovar->getInvN(),
                            $fio,
                            $phone,
                            $deliveryYN,
                            $address,
                            1,
                            $fullInfo
                        );
                        if ($br && $br->insert_id) {
                            \App\Helpers\UtmTracker::track('rent_orders', $br->insert_id);
                        }

                        $results[] = [
                            'modelId' => $modelId,
                            'name' => $item['name'] ?? '',
                            'status' => 'booked',
                            'amount' => $totalAmount,
                        ];
                    } else {
                        $allSuccess = false;
                        $results[] = [
                            'modelId' => $modelId,
                            'name' => $item['name'] ?? '',
                            'status' => 'unavailable',
                        ];
                    }
                } catch (\Exception $e) {
                    $allSuccess = false;
                    // Fallback: create a zvonok
                    $z = Zvonok::addLitZvonok($fio, $phone, $info . ' (ошибка корзины: ' . $e->getMessage() . ')', $modelId);
                    if ($z && $z->id) {
                        \App\Helpers\UtmTracker::track('zvonki', $z->id);
                    }
                    $results[] = [
                        'modelId' => $modelId,
                        'name' => $item['name'] ?? '',
                        'status' => 'error',
                    ];
                }
            } else {
                // Item not available — create zayavka
                $validityDays = $days;
                $z = Zvonok::addLitZvonok($fio, $phone, $info, $modelId, 'zayavka', $validityDays);
                if ($z && $z->id) {
                    \App\Helpers\UtmTracker::track('zvonki', $z->id);
                }

                $validityDateObj = new \DateTime();
                if ($validityDays) {
                    $validityDateObj->modify('+' . intval($validityDays) . ' days');
                }
                $zayavka = bron::createZayavka($modelId, $phone, $fio, '', '', $validityDateObj, $info, 1);
                if ($zayavka && $zayavka->insert_id && !$zayavka->is_duplicate) {
                    \App\Helpers\UtmTracker::track('rent_orders', $zayavka->insert_id);
                }
                if (isset($z) && $z->id && $zayavka && $zayavka->insert_id) {
                    (new \bb\classes\Zayavka())->linkAfterCreate((int)$zayavka->insert_id, (int)$z->id);
                }

                $results[] = [
                    'modelId' => $modelId,
                    'name' => $item['name'] ?? '',
                    'status' => 'waitlist',
                ];
            }
        }

        return response()->json([
            'success' => $allSuccess,
            'results' => $results,
            'totals' => [
                'items' => round($itemsTotal, 2),
                'delivery' => round($deliveryCost, 2),
                'courier_pickup' => round($pickupCost, 2),
                'total' => round($grandTotal, 2),
            ],
            'message' => $allSuccess
                ? 'Все товары успешно забронированы! Оператор свяжется с вами в ближайшее время.'
                : 'Некоторые товары не удалось забронировать. Проверьте статус каждого товара в результатах.',
        ]);
    }
}
