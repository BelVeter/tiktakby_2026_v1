<?php


namespace bb\classes;


use bb\Base;

class TariffModel
{
    private $model_id;
    /**
     * @var []Tariff[]
     */
    private static $all_tariffs;
    /**
     * @var Tariff[]
     */
    private $tariffs;

    public function __construct()
    {
        $this->tariffs = [];
    }

    /**
     * @param $model_id
     * @return Tariff[]|bool
     * @throws \Exception
     */
    public static function getTariffsForModel($model_id)
    {

        if (isset(self::$all_tariffs[$model_id])) {//check if already loaded

            return self::$all_tariffs[$model_id];
        } else {

            $tarifs = [];

            $mysqli = \bb\Db::getInstance()->getConnection();
            //запрашиваем тарифы
            $query = "SELECT * FROM rent_tarif_act WHERE model_id='$model_id' ORDER BY sort_num, kol_vo";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }


            while ($row = $result->fetch_assoc()) {
                $tar = Tariff::getFromDbArray($row);
                $tarifs[] = clone $tar;
            }
            //Base::varDamp(self::$tariffs);
            self::$all_tariffs[$model_id] = $tarifs;
            //Base::varDamp(self::$all_tariffs);

            return $tarifs;
        }
    }

    /**
     * @param $modelId
     * @return TariffModel|bool
     * @throws \Exception
     */
    public static function getTarifModelForModelId($modelId)
    {
        $mod_tariffs = new self();
        $mod_tariffs->tariffs = self::getTariffsForModel($modelId);
        return $mod_tariffs;
    }

    /**
     * @return Tariff[]
     */
    public function getTarifs()
    {
        return $this->tariffs;
    }

    /**
     * @param $model_id
     * @return Tariff|void|null
     * @throws \Exception
     */
    public static function getChippestTarifByModelId($model_id)
    {

        $mysqli = \bb\Db::getInstance()->getConnection();
        //запрашиваем тариф the smallest one
        $query = "SELECT *, (rent_amount/(kol_vo*sort_num)) as sort_f FROM rent_tarif_act WHERE model_id='$model_id' ORDER BY sort_f LIMIT 1";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows < 1)
            return null;
        else {
            return Tariff::getFromDbArray($result->fetch_assoc());
        }
    }

    public function allTariffsText()
    {
        if (count($this->tariffs) < 1) {
            return 'нет';
        } else {
            $rez = '';
            foreach ($this->tariffs as $t) {
                $rez .= $t->kol_vo . ' ' . $t->shortStepText() . ' - ' . number_format($t->rent_amount, 2, ',', ' ') . '<br>';
            }
            return $rez;
        }
    }

    public function tarNum()
    {
        if (is_array($this->tariffs))
            return count($this->tariffs);
        else
            return 0;

    }

    public static function getHtmlTarifInputs($model_id)
    {
        $rez = '';
        $tars = self::getTariffsForModel($model_id)->getTarifs();
        if ($tars & count($tars) > 0) {
            foreach ($tars as $t) {
                $rez .= '<input type="hidden" class="tarifs-in-days" name="' . $t->getPeriodInDays() . '" value="' . $t->getTotalAmount() . '">';
            }
            return $rez;
        } else
            return false;

    }

    /**
     * @param $days
     * @return float
     */
    public function getAmmountForDaysPeriod($days)
    {
        if (count($this->getTarifs()) < 1)
            return 0;

        $tarifs = [];

        foreach ($this->getTarifs() as $t) {
            if ($t->getDaysCalculatedNumber() == 0) {
                $tarifs[] = [0, 0];
            } else {
                $tarifs[] = [$t->getDaysCalculatedNumber(), round(($t->getTotalAmount() / $t->getDaysCalculatedNumber()), 2)];
            }

        }

        usort($tarifs, function ($a, $b) {
            return $a[0] - $b[0];
        });

        $tarifPerDay = $tarifs[0][1];
        foreach ($tarifs as $tar) {
            if ($days >= $tar[0])
                $tarifPerDay = $tar[1];
        }

        $amount = round(($days * $tarifPerDay), 2);

        // Ceiling check: don't exceed next tier total
        $tarifsDesc = $tarifs;
        usort($tarifsDesc, function ($a, $b) {
            return $b[0] - $a[0];
        });

        $currentTierDays = $tarifs[0][0];
        foreach ($tarifs as $tar) {
            if ($days >= $tar[0])
                $currentTierDays = $tar[0];
        }

        foreach ($tarifsDesc as $tar) {
            if ($tar[0] > $currentTierDays) {
                $ceilingAmount = round($tar[0] * $tar[1], 2);
                if ($amount > $ceilingAmount) {
                    $amount = $ceilingAmount;
                }
            }
        }

        return $amount;
    }

    /**
     * @param $days
     * @return float
     */
    public function getDaylyTarifForDaysPeriod($days)
    {

        if (count($this->getTarifs()) < 1)
            return 0;

        $totalAmount = $this->getAmmountForDaysPeriod($days);
        if ($days > 0 && $totalAmount > 0) {
            return round($totalAmount / $days, 2);
        }

        return 0;
    }
    /**
     * Build Schema.org Offer array for the primary rental period (L3 product page).
     *
     * Always generates 4 fixed breakpoints (×1, ×2, ×3, ×4) for the given step,
     * using getAmmountForDaysPeriod() — the same interpolation the L2 card uses.
     * This means week-based products always show 1/2/3/4 weeks even when the DB
     * only has a subset of those tariff rows.
     *
     * @param  string $primaryStep  'day' | 'week' | 'month'
     * @param  string $url          Absolute canonical URL of the product page
     * @return array|null           Single Offer array, array of Offer arrays, or null
     */
    public function getSchemaOffers($primaryStep, $url)
    {
        if (count($this->getTarifs()) === 0) return null;

        $unitCodeMap = ['day' => 'DAY', 'week' => 'WEE', 'month' => 'MON'];
        $stepDaysMap = ['day' => 1,     'week' => 7,     'month' => 30];

        $unitCode = $unitCodeMap[$primaryStep] ?? 'DAY';
        $stepDays = $stepDaysMap[$primaryStep] ?? 1;

        $offers = [];
        for ($qty = 1; $qty <= 4; $qty++) {
            // Prefer exact tariff row; fall back to interpolation for missing breakpoints
            $exactPrice = null;
            foreach ($this->getTarifs() as $t) {
                if ($t->step === $primaryStep && (int)$t->kol_vo === $qty) {
                    $exactPrice = $t->getTotalAmount();
                    break;
                }
            }
            $price = $exactPrice !== null ? $exactPrice : $this->getAmmountForDaysPeriod($stepDays * $qty);
            if ($price <= 0) continue;

            $offers[] = [
                '@type'              => 'Offer',
                'priceCurrency'      => 'BYN',
                'price'              => number_format($price, 2, '.', ''),
                'priceSpecification' => [
                    '@type'             => 'UnitPriceSpecification',
                    'price'             => number_format($price, 2, '.', ''),
                    'priceCurrency'     => 'BYN',
                    'referenceQuantity' => [
                        '@type'    => 'QuantitativeValue',
                        'value'    => (string) $qty,
                        'unitCode' => $unitCode,
                    ],
                ],
                'url'    => $url,
                'seller' => [
                    '@type' => 'LocalBusiness',
                    'name'  => 'ТикТак Прокат',
                    'url'   => 'https://tiktak.by',
                ],
            ];
        }

        if (count($offers) === 0) return null;
        if (count($offers) === 1) return $offers[0];
        return $offers;
    }

    /**
     * Build a single Schema.org Offer for the cheapest available tariff (L2 listing).
     *
     * Returns the tariff with the fewest days (= lowest entry price), regardless of
     * step type.  This gives Google the real minimum price a customer can pay.
     *
     * @param  string $url  Absolute canonical URL of the product page
     * @return array|null   Single Offer array, or null if no tariffs exist
     */
    public function getSchemaMinOffer($url)
    {
        $unitCodeMap = ['day' => 'DAY', 'week' => 'WEE', 'month' => 'MON'];

        $minTariff = null;
        $minDays   = PHP_INT_MAX;
        foreach ($this->getTarifs() as $t) {
            $days = $t->getDaysCalculatedNumber();
            if ($days > 0 && $days < $minDays) {
                $minDays   = $days;
                $minTariff = $t;
            }
        }

        if (!$minTariff) return null;

        $unitCode = $unitCodeMap[$minTariff->step] ?? 'DAY';
        return [
            '@type'              => 'Offer',
            'priceCurrency'      => 'BYN',
            'price'              => number_format($minTariff->getTotalAmount(), 2, '.', ''),
            'priceSpecification' => [
                '@type'             => 'UnitPriceSpecification',
                'price'             => number_format($minTariff->getTotalAmount(), 2, '.', ''),
                'priceCurrency'     => 'BYN',
                'referenceQuantity' => [
                    '@type'    => 'QuantitativeValue',
                    'value'    => (string) $minTariff->kol_vo,
                    'unitCode' => $unitCode,
                ],
            ],
            'url'    => $url,
            'seller' => [
                '@type' => 'LocalBusiness',
                'name'  => 'ТикТак Прокат',
                'url'   => 'https://tiktak.by',
            ],
        ];
    }

}
