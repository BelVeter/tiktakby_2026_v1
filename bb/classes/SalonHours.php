<?php

namespace bb\classes;

/**
 * Часы работы салона проката (ул. Литературная, 22).
 *
 * ЕДИНСТВЕННОЕ место, где эти часы записаны. Из него рендерятся:
 *  - попап «Наш салон проката» (resources/views/includes/header.blade.php);
 *  - микроразметка LocalBusiness (resources/views/layouts/app.blade.php);
 *  - строка «Яндекс-доставка сегодня» в карточке товара
 *    (bb/classes/DeliverySchedule.php) — она показывается только пока салон открыт.
 *
 * Поменять часы = поменять константы здесь. Правка в одном месте меняет всё.
 */
class SalonHours
{
    /** Будни, пн–пт. */
    const WEEKDAY_OPEN = '10:00';
    const WEEKDAY_CLOSE = '19:00';

    /** Выходные, сб и вс. */
    const WEEKEND_OPEN = '10:00';
    const WEEKEND_CLOSE = '15:00';

    const TIMEZONE = 'Europe/Minsk';

    /**
     * @return \DateTime
     */
    public static function now()
    {
        return new \DateTime('now', new \DateTimeZone(self::TIMEZONE));
    }

    /**
     * @param \DateTime|null $moment
     * @return bool
     */
    public static function isWeekend(\DateTime $moment = null)
    {
        $moment = $moment ?: self::now();

        return (int) $moment->format('N') >= 6;
    }

    /**
     * @param \DateTime|null $moment
     * @return string 'ЧЧ:ММ'
     */
    public static function openTime(\DateTime $moment = null)
    {
        return self::isWeekend($moment) ? self::WEEKEND_OPEN : self::WEEKDAY_OPEN;
    }

    /**
     * @param \DateTime|null $moment
     * @return string 'ЧЧ:ММ'
     */
    public static function closeTime(\DateTime $moment = null)
    {
        return self::isWeekend($moment) ? self::WEEKEND_CLOSE : self::WEEKDAY_CLOSE;
    }

    /**
     * Открыт ли салон прямо сейчас.
     *
     * @param \DateTime|null $moment
     * @return bool
     */
    public static function isOpenNow(\DateTime $moment = null)
    {
        $moment = $moment ?: self::now();
        $minutes = (int) $moment->format('G') * 60 + (int) $moment->format('i');

        return $minutes >= self::toMinutes(self::openTime($moment))
            && $minutes < self::toMinutes(self::closeTime($moment));
    }

    /**
     * Строка для попапа и подписей: «пн–пт: 10:00–19:00».
     *
     * @return string
     */
    public static function weekdayLine()
    {
        return 'пн–пт: ' . self::WEEKDAY_OPEN . '–' . self::WEEKDAY_CLOSE;
    }

    /**
     * Строка для попапа и подписей: «сб, вс: 10:00–15:00».
     *
     * @return string
     */
    public static function weekendLine()
    {
        return 'сб, вс: ' . self::WEEKEND_OPEN . '–' . self::WEEKEND_CLOSE;
    }

    /**
     * Блок `openingHoursSpecification` для микроразметки LocalBusiness.
     *
     * @return array
     */
    public static function schemaSpecification()
    {
        return array(
            array(
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                'opens' => self::WEEKDAY_OPEN,
                'closes' => self::WEEKDAY_CLOSE,
            ),
            array(
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array('Saturday', 'Sunday'),
                'opens' => self::WEEKEND_OPEN,
                'closes' => self::WEEKEND_CLOSE,
            ),
        );
    }

    /**
     * @param string $time 'ЧЧ:ММ'
     * @return int минут от полуночи
     */
    private static function toMinutes($time)
    {
        list($hours, $minutes) = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }
}
