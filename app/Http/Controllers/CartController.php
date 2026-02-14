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
        $promoCode = $request->input('promo_code', '');
        $giftCertificate = $request->input('gift_certificate', '');

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
            $errors[] = 'Выберите способ доставки';
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

                    $deliveryYN = ($delivery == '1') ? 1 : 0;

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
                            . ' на ' . $days . ' дн. Сумма: ' . number_format($totalAmount, 2) . ' BYN.';

                        if ($promoCode) {
                            $techInfo .= ' Промокод: ' . $promoCode . '.';
                        }
                        if ($giftCertificate) {
                            $techInfo .= ' Сертификат: ' . $giftCertificate . '.';
                        }

                        $fullInfo = $techInfo . ($info ? '<br>' . $info : '');

                        bron::createBronStrong(
                            $tovar->getInvN(),
                            $fio,
                            $phone,
                            $deliveryYN,
                            $address,
                            1,
                            $fullInfo
                        );

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
                    Zvonok::addLitZvonok($fio, $phone, $info . ' (ошибка корзины: ' . $e->getMessage() . ')', $modelId);
                    $results[] = [
                        'modelId' => $modelId,
                        'name' => $item['name'] ?? '',
                        'status' => 'error',
                    ];
                }
            } else {
                // Item not available — create zayavka
                $validityDays = $days;
                Zvonok::addLitZvonok($fio, $phone, $info, $modelId, 'zayavka', $validityDays);
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
            'message' => $allSuccess
                ? 'Все товары успешно забронированы! Оператор свяжется с вами в ближайшее время.'
                : 'Некоторые товары не удалось забронировать. Проверьте статус каждого товара в результатах.',
        ]);
    }
}
