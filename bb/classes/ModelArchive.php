<?php

namespace bb\classes;

use bb\Db;

/**
 * Перенос модели товара в архив вместо удаления.
 *
 * Раньше `bb/tovar_del.php` делал `DELETE FROM tovar_rent` + `DELETE FROM
 * tovar_rent_items` и больше ничего — а `model_id` живёт в 13 таблицах
 * (docs/db_notes.md, п.9). Из-за этого заявки, тарифы, фото и архивные юниты
 * оставались висеть в пустоту; разбор 28.07.2026 нашёл 222 таких заявки.
 *
 * Правильный путь уже был заложен в архитектуру: отчёты резолвят модель через
 * `UNION(tovar_rent, tovar_rent_arch)` (`Deal.php:1003`, `tovar.php:1546`),
 * то есть готовы к архивной модели и продолжают разрешать её категорию.
 * Не хватало только записи в `tovar_rent_arch` — её и делает этот класс.
 *
 * Что происходит при архивации:
 *   1. Строка модели копируется в `tovar_rent_arch` с тем же `tovar_rent_id`,
 *      временем и автором.
 *   2. Спутники (тарифы, доп. фото, веб-страница, мультистраницы, избранное)
 *      складываются в `arch_snapshot` (JSON) и удаляются из живых таблиц.
 *      Веб-страницу обязательно удалить: `ModelWeb::getByUrlName()` не
 *      фильтрует `status`, поэтому `not_show` её не прячет — адрес продолжил бы
 *      отдавать 200. Без строки срабатывает штатный фолбэк
 *      `L3Controller::showCategoryWithNotice()` и отдаётся честный 404.
 *   3. Строка удаляется из `tovar_rent`.
 *
 * Заявки, звонки, сделки и архивные юниты НЕ трогаются: их `model_id`
 * продолжает разрешаться через `tovar_rent_arch`.
 */
class ModelArchive
{
    /** Спутники модели: уходят в снимок и удаляются из живых таблиц. */
    private const SATELLITE_TABLES = [
        'rent_tarif_act',
        'rent_tarif_prev',
        'dop_photos',
        'rent_model_web',
        'multi_web',
        'favorite_tovars',
    ];

    /**
     * Что мешает перенести модель в архив прямо сейчас.
     *
     * Живые единицы товара — единственный жёсткий стоп: модель уедет в архив,
     * а юниты останутся в остатках и в знаменателе загрузки как фантомный
     * товар. Ровно так получилось со строительным инструментом — 129 чужих
     * юнитов числились в наличии с 2022 года. Выбытие оформляется поштучно,
     * с причиной и суммой, на этой же странице.
     *
     * @return string[] список сообщений; пустой массив = можно архивировать
     */
    public static function blockers($modelId)
    {
        $modelId = (int) $modelId;
        $mysqli = Db::getInstance()->getConnection();
        $blockers = [];

        $res = $mysqli->query("SELECT COUNT(*) n FROM tovar_rent_items WHERE model_id='{$modelId}'");
        if (!$res) {
            return ['Сбой при доступе к базе данных: ' . $mysqli->error];
        }
        $liveUnits = (int) $res->fetch_assoc()['n'];

        if ($liveUnits > 0) {
            $blockers[] = 'У модели ' . $liveUnits . ' ед. товара в работе. '
                . 'Сначала оформите выбытие каждой единицы (форма ниже) — '
                . 'иначе они останутся в остатках как товар без модели.';
        }

        $res = $mysqli->query("SELECT COUNT(*) n FROM tovar_rent WHERE tovar_rent_id='{$modelId}'");
        if ($res && (int) $res->fetch_assoc()['n'] === 0) {
            $blockers[] = 'Модель не найдена — возможно, её уже перенесли в архив.';
        }

        return $blockers;
    }

    /**
     * Переносит модель в архив. Вызывать только после проверки blockers().
     *
     * @param  int  $modelId
     * @param  int  $userId   кто архивирует (`$_SESSION['user_id']`)
     * @return string|true    true при успехе, текст ошибки при сбое
     */
    public static function archive($modelId, $userId = 0)
    {
        $modelId = (int) $modelId;
        $userId = (int) $userId;
        $mysqli = Db::getInstance()->getConnection();

        $res = $mysqli->query("SELECT * FROM tovar_rent WHERE tovar_rent_id='{$modelId}'");
        if (!$res || $res->num_rows !== 1) {
            return 'Модель не найдена.';
        }
        $model = $res->fetch_assoc();

        $snapshot = self::collectSatellites($mysqli, $modelId);
        if (!is_array($snapshot)) {
            return $snapshot;
        }

        Db::startTransaction();

        $insert = self::buildArchiveInsert($mysqli, $model, $userId, $snapshot);
        if (!is_string($insert)) {
            $mysqli->query('ROLLBACK');
            return 'Не удалось собрать архивную запись.';
        }

        if (!$mysqli->query($insert)) {
            $error = $mysqli->error;
            $mysqli->query('ROLLBACK');
            return 'Сбой при записи в архив: ' . $error;
        }

        foreach (self::SATELLITE_TABLES as $table) {
            if (!$mysqli->query("DELETE FROM `{$table}` WHERE model_id='{$modelId}'")) {
                $error = $mysqli->error;
                $mysqli->query('ROLLBACK');
                return 'Сбой при очистке ' . $table . ': ' . $error;
            }
        }

        if (!$mysqli->query("DELETE FROM tovar_rent WHERE tovar_rent_id='{$modelId}'")) {
            $error = $mysqli->error;
            $mysqli->query('ROLLBACK');
            return 'Сбой при удалении модели: ' . $error;
        }

        Db::commitTransaction();

        return true;
    }

    /**
     * @return array|string  снимок спутников либо текст ошибки
     */
    private static function collectSatellites($mysqli, $modelId)
    {
        $snapshot = [];

        foreach (self::SATELLITE_TABLES as $table) {
            $res = $mysqli->query("SELECT * FROM `{$table}` WHERE model_id='{$modelId}'");
            if (!$res) {
                return 'Сбой при чтении ' . $table . ': ' . $mysqli->error;
            }

            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }

            if (!empty($rows)) {
                $snapshot[$table] = $rows;
            }
        }

        return $snapshot;
    }

    /**
     * Собирает `INSERT` по пересечению колонок живой и архивной таблиц.
     * Именно по пересечению, а не позиционно: схемы разошлись исторически
     * (docs/db_notes.md, п.1 и п.11), и любое расхождение сломало бы вставку.
     *
     * @return string|false
     */
    private static function buildArchiveInsert($mysqli, array $model, $userId, array $snapshot)
    {
        $res = $mysqli->query('SHOW COLUMNS FROM tovar_rent_arch');
        if (!$res) {
            return false;
        }

        $archColumns = [];
        while ($row = $res->fetch_assoc()) {
            $archColumns[$row['Field']] = true;
        }

        $pairs = [];
        foreach ($model as $column => $value) {
            if (!isset($archColumns[$column])) {
                continue;
            }
            $pairs[] = "`{$column}`='" . $mysqli->real_escape_string($value) . "'";
        }

        if (empty($pairs)) {
            return false;
        }

        $pairs[] = "`arch_time`='" . time() . "'";
        $pairs[] = "`arch_who_id`='" . (int) $userId . "'";
        $pairs[] = "`arch_snapshot`='"
            . $mysqli->real_escape_string(json_encode($snapshot, JSON_UNESCAPED_UNICODE))
            . "'";

        return 'INSERT INTO tovar_rent_arch SET ' . implode(', ', $pairs);
    }
}
