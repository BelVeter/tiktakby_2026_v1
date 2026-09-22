<?php

namespace bb\classes;

require_once __DIR__ . '/../Db.php';

use bb\Db;

/**
 * Key-value настройки сайта (таблица `site_settings`).
 *
 * Читается и из легаси-админки (этот класс), и из Laravel
 * (см. app/MyClasses/DeliverySchedule.php).
 *
 * Все обращения защищены: если таблицы ещё нет (миграция не доехала
 * до прода), get() вернёт null, а set() — false, вместо фатала.
 */
class SiteSetting
{
    /** Дата (Y-m-d), на которую доставка «сегодня» уже невозможна — курьер уехал. */
    const KEY_DELIVERY_NEXT_DAY = 'delivery_next_day_date';

    /**
     * @param string $key
     * @return string|null
     */
    public static function get($key)
    {
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT setting_value FROM `site_settings`
                  WHERE setting_key='" . $mysqli->real_escape_string($key) . "' LIMIT 1";

        try {
            $result = $mysqli->query($query);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$result) {
            return null;
        }

        $row = $result->fetch_assoc();

        return $row ? $row['setting_value'] : null;
    }

    /**
     * @param string $key
     * @param string|null $value  null — настройка снимается
     * @param string|null $who    ФИО сотрудника для журнала
     * @return bool
     */
    public static function set($key, $value, $who = null)
    {
        $mysqli = Db::getInstance()->getConnection();

        $keyEsc = $mysqli->real_escape_string($key);
        $valueSql = $value === null ? 'NULL' : "'" . $mysqli->real_escape_string($value) . "'";
        $whoSql = $who === null ? 'NULL' : "'" . $mysqli->real_escape_string($who) . "'";

        $query = "INSERT INTO `site_settings` (setting_key, setting_value, updated_at, updated_by)
                  VALUES ('" . $keyEsc . "', " . $valueSql . ", NOW(), " . $whoSql . ")
                  ON DUPLICATE KEY UPDATE
                      setting_value=VALUES(setting_value),
                      updated_at=VALUES(updated_at),
                      updated_by=VALUES(updated_by)";

        try {
            return (bool) $mysqli->query($query);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Включён ли на сегодня перенос доставки на завтра.
     *
     * Флаг хранит дату, поэтому сам «сгорает» в полночь — забытый
     * переключатель не превратит сайт в «доставка завтра» навсегда.
     *
     * @return bool
     */
    public static function isDeliveryMovedToTomorrow()
    {
        return self::get(self::KEY_DELIVERY_NEXT_DAY) === date('Y-m-d');
    }

    /**
     * @param bool $on
     * @param string|null $who
     * @return bool
     */
    public static function setDeliveryMovedToTomorrow($on, $who = null)
    {
        return self::set(self::KEY_DELIVERY_NEXT_DAY, $on ? date('Y-m-d') : null, $who);
    }
}
