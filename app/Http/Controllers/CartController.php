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

    /** Каналы, через которые клиент просит прислать подтверждение заказа */
    public const CONTACT_CHANNELS = [
        'viber' => 'Viber',
        'telegram' => 'Telegram',
        'sms' => 'SMS',
    ];

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
        // Выбора пригорода в корзине пока нет — считаем всегда по минским тарифам.
        // Поле запроса намеренно не читаем, чтобы клиент не мог удешевить доставку.
        $isSuburb = false;
        $wantsCourierPickup = (bool) $request->input('courier_pickup', 0);

        // Канал связи необязателен; принимаем только известные значения
        $channelKey = (string) $request->input('contact_channel', '');
        $contactChannel = self::CONTACT_CHANNELS[$channelKey] ?? '';

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
        //
        // Считаем только те товары, которые действительно можно забронировать:
        // занятый товар уйдёт в заявку, клиент его не арендует, и в сумме заказа
        // ему не место — иначе и порог бесплатной доставки, и итог завышены.
        $itemsTotal = 0.0;
        $itemsCount = 0;
        foreach ($items as $item) {
            $modelId = intval($item['modelId'] ?? 0);
            if ($modelId <= 0) {
                continue;
            }
            $freeOffices = tovar::getFreeItemsOfficeArrayForModelId($modelId);
            if (!is_array($freeOffices) || count($freeOffices) === 0) {
                continue;
            }
            $tm = TariffModel::getTarifModelForModelId($modelId);
            if ($tm) {
                $itemsTotal += (float) $tm->getAmmountForDaysPeriod(intval($item['days'] ?? 14));
                $itemsCount++;
            }
        }

        $deliveryCost = $isDelivery ? self::calcDeliveryCost($itemsTotal, $isSuburb) : 0.0;
        $pickupCost = $wantsCourierPickup ? self::COURIER_PICKUP_COST : 0.0;
        $grandTotal = $itemsTotal + $deliveryCost + $pickupCost;

        // Канал связи — одним словом, без подписи: оператору важно только куда писать
        $channelTag = $contactChannel !== ''
            ? '<span class="bk-ch">' . $contactChannel . '</span>'
            : '';

        // Деньги одной формулой: прокат + доставка + возврат курьером = итого.
        // Нули не прячем — позиции слагаемых постоянны, столбец читается по вертикали.
        $money = $isDelivery
            ? number_format($itemsTotal, 2) . ' + ' . number_format($deliveryCost, 2)
                . ' + ' . number_format($pickupCost, 2) . ' = ' . number_format($grandTotal, 2) . ' BYN'
            : number_format($grandTotal, 2) . ' BYN';
        $moneyBlock = '<div class="bk-money">' . $money . '</div>';

        // Реплика клиента — отдельным блоком и с экранированием: в bb/ поле info выводится как HTML
        $quoteBlock = trim($info) !== ''
            ? '<div class="bk-quote">' . htmlspecialchars(trim($info), ENT_QUOTES, 'UTF-8') . '</div>'
            : '';

        // Заявке (товар занят) расчёт не нужен — там ещё нечего оплачивать, но канал связи нужен
        $infoForZayavka = ($channelTag !== '' ? '<div class="bk-right">' . $channelTag . '</div>' : '')
            . $quoteBlock;

        // Звонок показывается в zv_ch.php, где стили карточки не подключены — туда обычный текст
        $infoForZvonok = trim($info . ($contactChannel !== '' ? ' Связь: ' . $contactChannel . '.' : ''));

        // Договор и выезды курьера заводим только для доставки: самовывоз курьера не касается.
        // Запасное значение — «выключено»: пропал ключ конфига, значит договор создаёт человек.
        $autoDealEnabled = $isDelivery && (bool) config('app.cart_auto_deal', false);
        $autoDealClientId = 0;
        $autoDealDeliveryLeft = $deliveryCost;
        $autoDealPickupLeft = $pickupCost;
        $courierInfo = '';

        if ($autoDealEnabled) {
            try {
                $autoDealClientId = \bb\classes\WebOrderDeal::findOrCreateClient($fio, $phone, $address);
                $courierInfo = \bb\classes\WebOrderDeal::courierInfo($info);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Клиент по заказу с сайта не заведён: ' . $e->getMessage());
            }
        }

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

                        // Правая колонка карточки: сроки сверху, деньги под ними.
                        // Сумму позиции показываем только когда в заказе больше одного товара —
                        // иначе она дословно повторяет первое слагаемое формулы
                        $datesLine = '<div class="bk-dates">' . $channelTag
                            . $dateFromObj->format('d.m') . ' → ' . $dateToObj->format('d.m')
                            . '<span class="bk-days">' . $days . ' дн.</span>'
                            . ($itemsCount > 1 ? '<span class="bk-days">поз. ' . number_format($totalAmount, 2) . '</span>' : '')
                            . '</div>';

                        $fullInfo = '<div class="bk-right">' . $datesLine . $moneyBlock . '</div>' . $quoteBlock;

                        $br = bron::createBronStrong(
                            $tovar->getInvN(),
                            $fio,
                            $phone,
                            $deliveryYN,
                            $address,
                            1,
                            $fullInfo,
                            // срок позиции — в колонки, чтобы «нов.договор» не разбирал вёрстку карточки
                            $days,
                            $dateFromObj->getTimestamp(),
                            $dateToObj->getTimestamp()
                        );

                        if ($br) {
                            if ($br->insert_id) {
                                \App\Helpers\UtmTracker::track('rent_orders', $br->insert_id);
                            }

                            // Заказ с доставкой сразу становится договором и выездами курьера.
                            // Стоимость доставки и возврата вешаем только на первую позицию заказа,
                            // иначе курьер увидит её столько раз, сколько товаров в корзине.
                            if ($isDelivery && $autoDealEnabled && $autoDealClientId > 0) {
                                try {
                                    $dealId = \bb\classes\WebOrderDeal::createDealWithTrips(
                                        $autoDealClientId,
                                        [
                                            'inv_n' => $tovar->getInvN(),
                                            'start_ts' => $dateFromObj->getTimestamp(),
                                            'return_ts' => $dateToObj->getTimestamp(),
                                            'days' => $days,
                                            'r_to_pay' => $totalAmount,
                                            'tarif' => \bb\classes\WebOrderDeal::resolveTariff($tarifModel, $days),
                                        ],
                                        $autoDealDeliveryLeft,
                                        $autoDealPickupLeft,
                                        $courierInfo
                                    );
                                    if ($dealId > 0) {
                                        $autoDealDeliveryLeft = 0.0;
                                        $autoDealPickupLeft = 0.0;
                                    }
                                } catch (\Throwable $e) {
                                    // Заказ клиента важнее автоматики: бронь уже создана, её не рушим
                                    \Illuminate\Support\Facades\Log::error('Автодоговор по заказу с сайта не создан: ' . $e->getMessage());
                                }
                            }

                            $results[] = [
                                'modelId' => $modelId,
                                'name' => $item['name'] ?? '',
                                'status' => 'booked',
                                'amount' => $totalAmount,
                            ];
                        } else {
                            // createBronStrong() отказал (например, единственный свободный
                            // экземпляр помечен фейком/state=-1) — не показываем клиенту
                            // ложное "забронировано", уводим в заявку тем же путём, что и
                            // при полном отсутствии свободных экземпляров ниже.
                            $z = Zvonok::addLitZvonok($fio, $phone, $fullInfo, $modelId, 'zayavka', $days);
                            if ($z && $z->id) {
                                \App\Helpers\UtmTracker::track('zvonki', $z->id);
                            }

                            $validityDateObj = clone $dateToObj;
                            $zayavka = bron::createZayavka($modelId, $phone, $fio, '', '', $validityDateObj, $fullInfo, 1);
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
                $z = Zvonok::addLitZvonok($fio, $phone, $infoForZvonok, $modelId, 'zayavka', $validityDays);
                if ($z && $z->id) {
                    \App\Helpers\UtmTracker::track('zvonki', $z->id);
                }

                $validityDateObj = new \DateTime();
                if ($validityDays) {
                    $validityDateObj->modify('+' . intval($validityDays) . ' days');
                }
                $zayavka = bron::createZayavka($modelId, $phone, $fio, '', '', $validityDateObj, $infoForZayavka, 1);
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
