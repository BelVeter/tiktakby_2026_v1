<?php

namespace bb\classes;

require_once __DIR__ . '/SalonHours.php';
require_once __DIR__ . '/SiteSetting.php';

/**
 * Что писать в карточке товара на сайте в блоке доставки.
 *
 * Две строки:
 *  1. «Доставка: сегодня / завтра / в понедельник» — свой курьер;
 *  2. «Яндекс-доставка сегодня» — срочный платный вариант, показывается
 *     только пока салон открыт (часы — bb/classes/SalonHours.php).
 *
 * Свой курьер ездит ТОЛЬКО по будням. Поэтому в субботу и воскресенье,
 * а также в пятницу после отсечки ближайший выезд — понедельник.
 *
 * Курьер выезжает каждый день в разное время, поэтому сотрудник может снять
 * сегодняшнюю доставку раньше отсечки переключателем на странице курьера
 * (bb/cur_page2.php → bb/cur_delivery_day.php). Флаг привязан к дате и
 * в полночь снимается сам.
 *
 * Единственный источник правды и для сайта (resources/views/includes/
 * l2_model_block.blade.php), и для админки.
 */
class DeliverySchedule
{
    /** После этого часа заказ уже не попадает в сегодняшний выезд. */
    const COURIER_CUTOFF_HOUR = 17;

    /** Дни недели (ISO-8601, 1 = пн), когда ездит свой курьер. */
    const COURIER_WORKING_DAYS = array(1, 2, 3, 4, 5);

    /** Винительный падеж для «в ...» — какой день показать, если выезд не сегодня и не завтра. */
    private static $dayNames = array(
        1 => 'в понедельник',
        2 => 'во вторник',
        3 => 'в среду',
        4 => 'в четверг',
        5 => 'в пятницу',
        6 => 'в субботу',
        7 => 'в воскресенье',
    );

    /** @var bool|null Ответ БД на время одного запроса — карточек на странице десятки. */
    private static $movedToTomorrow = null;

    /** @var array|false|null Журнал последнего переключения; false — записи нет. */
    private static $lastChange = null;

    // ----------------------------------------------------------------- курьер

    /**
     * Строка первой строки блока: 'сегодня' | 'завтра' | 'в понедельник'.
     *
     * @param \DateTime|null $now   момент расчёта (параметр — чтобы логику можно было прогнать тестом)
     * @param bool|null $moved       перенос выезда; null — прочитать из БД
     * @return string
     */
    public static function text(\DateTime $now = null, $moved = null)
    {
        $now = $now ?: SalonHours::now();
        $deliveryDay = self::courierDay($now, $moved);

        $today = clone $now;
        $today->setTime(0, 0, 0);

        $daysAhead = (int) $today->diff($deliveryDay)->days;

        if ($daysAhead === 0) {
            return 'сегодня';
        }

        // «завтра» честно только если сегодня курьер вообще ездит:
        // в воскресенье ближайший выезд тоже завтра, но клиенту понятнее «в понедельник».
        if ($daysAhead === 1 && self::isCourierWorkingDay($now)) {
            return 'завтра';
        }

        return self::$dayNames[(int) $deliveryDay->format('N')];
    }

    /**
     * Ближайший день, когда свой курьер повезёт заказ.
     *
     * @param \DateTime|null $now
     * @param bool|null $moved перенос выезда; null — прочитать из БД
     * @return \DateTime полночь этого дня
     */
    public static function courierDay(\DateTime $now = null, $moved = null)
    {
        $now = $now ?: SalonHours::now();
        $moved = $moved === null ? self::isMovedToTomorrow() : (bool) $moved;

        $day = clone $now;
        $day->setTime(0, 0, 0);

        if (self::isCourierWorkingDay($now) && !$moved && self::isBeforeCutoff($now)) {
            return $day;
        }

        do {
            $day->modify('+1 day');
        } while (!self::isCourierWorkingDay($day));

        return $day;
    }

    /**
     * @param \DateTime|null $moment
     * @return bool
     */
    public static function isCourierWorkingDay(\DateTime $moment = null)
    {
        $moment = $moment ?: SalonHours::now();

        return in_array((int) $moment->format('N'), self::COURIER_WORKING_DAYS, true);
    }

    /**
     * Успевает ли курьер сегодня по времени, без учёта дня недели и переключателя.
     *
     * @param \DateTime|null $moment
     * @return bool
     */
    public static function isBeforeCutoff(\DateTime $moment = null)
    {
        $moment = $moment ?: SalonHours::now();

        return (int) $moment->format('G') < self::COURIER_CUTOFF_HOUR;
    }

    /**
     * @return string 'ЧЧ:ММ' — во сколько доставка сама переедет на следующий день
     */
    public static function cutoffTime()
    {
        return self::COURIER_CUTOFF_HOUR . ':00';
    }

    // --------------------------------------------------------- Яндекс-доставка

    /**
     * Можно ли прямо сейчас предложить срочную доставку день в день.
     *
     * Привязана к часам салона: заказ собирают и передают курьеру Яндекса
     * сотрудники, а вне рабочих часов обещать это нельзя.
     *
     * @return bool
     */
    public static function isYandexAvailableNow()
    {
        return SalonHours::isOpenNow();
    }

    /**
     * @return string
     */
    public static function yandexText()
    {
        return 'Яндекс-доставка сегодня';
    }

    // --------------------------------------------- подписи для страницы курьера

    /**
     * Вторая строка так, как её видит сотрудник в админке.
     *
     * @return string
     */
    public static function yandexStatus()
    {
        return self::isYandexAvailableNow()
            ? self::yandexText()
            : 'Яндекс-доставка скрыта — салон закрыт';
    }

    /**
     * Подпись под переключателем на странице курьера.
     *
     * Живёт здесь, а не в шаблоне, потому что её показывают оба места:
     * bb/cur_page2.php при отрисовке и bb/cur_delivery_day.php после клика.
     *
     * @return string
     */
    public static function switchHint()
    {
        if (!self::isCourierWorkingDay()) {
            return 'В выходные свой курьер не ездит — переключатель ничего не меняет';
        }

        $change = self::lastChangeToday();

        if (self::isMovedToTomorrow()) {
            return ($change ? 'Переключено' . $change . '. ' : '')
                . 'Снимется само завтра утром';
        }

        // сегодня уже трогали, но вернули обратно — это тоже стоит показать
        if ($change) {
            return 'Возвращено к расписанию' . $change;
        }

        if (self::isBeforeCutoff()) {
            return 'Само переключится в ' . self::cutoffTime();
        }

        return 'Уже после ' . self::cutoffTime() . ' — дальше по расписанию';
    }

    /**
     * Кто и когда трогал переключатель сегодня: ' — Иванова И.И., 12:35'.
     *
     * Вчерашние правки не показываем: флаг всё равно привязан к дате,
     * и старое имя в подписи только путало бы.
     *
     * @return string пустая строка, если сегодня никто не трогал
     */
    private static function lastChangeToday()
    {
        if (self::$lastChange === null) {
            $row = SiteSetting::getRow(SiteSetting::KEY_DELIVERY_NEXT_DAY);
            self::$lastChange = $row ?: false;
        }

        if (!self::$lastChange || empty(self::$lastChange['updated_at'])) {
            return '';
        }

        $at = strtotime(self::$lastChange['updated_at']);

        if ($at === false || date('Y-m-d', $at) !== SalonHours::now()->format('Y-m-d')) {
            return '';
        }

        $who = trim((string) self::$lastChange['updated_by']);

        return ($who === '' ? '' : ' — ' . $who . ',') . ' в ' . date('H:i', $at);
    }

    // ------------------------------------------------- ручной перенос выезда

    /**
     * Снял ли сотрудник сегодняшнюю доставку вручную.
     *
     * @param bool $fresh не брать ответ, закэшированный в этом запросе
     * @return bool
     */
    public static function isMovedToTomorrow($fresh = false)
    {
        if (!$fresh && self::$movedToTomorrow !== null) {
            return self::$movedToTomorrow;
        }

        return self::$movedToTomorrow = SiteSetting::isDeliveryMovedToTomorrow();
    }

    /**
     * @param bool $on
     * @param string|null $who ФИО сотрудника для журнала
     * @return bool
     */
    public static function setMovedToTomorrow($on, $who = null)
    {
        $saved = SiteSetting::setDeliveryMovedToTomorrow($on, $who);

        if ($saved) {
            self::$movedToTomorrow = (bool) $on;
            self::$lastChange = null; // журнал перечитать — подпись покажет свежую запись
        }

        return $saved;
    }
}
