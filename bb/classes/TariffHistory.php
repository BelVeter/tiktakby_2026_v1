<?php

namespace bb\classes;

use bb\Db;

/**
 * Журнал изменений тарифов — единственный писатель `rent_tarif_history`.
 *
 * Одно событие хранит полный снимок строки тарифа до и после изменения, а не
 * набор изменённых полей. Так восстановление прайса на дату сводится к
 * «последнее событие с changed_at <= D», без проигрывания ленты с начала.
 *
 * Вызывается из `Tariff` (создание/правка/удаление) и `ModelArchive`
 * (снятие тарифов при архивации модели). Транзакциями управляет вызывающий
 * код: и `bb/rent_tarifs.php`, и `ModelArchive::archive()` уже оборачивают
 * свои операции, а вложенный START TRANSACTION в mysqli неявно коммитит
 * внешнюю — поэтому здесь их нет.
 */
class TariffHistory
{
    const TYPE_BASELINE = 'baseline';
    const TYPE_CREATE   = 'create';
    const TYPE_UPDATE   = 'update';
    const TYPE_DELETE   = 'delete';

    const SOURCE_BB_ADMIN      = 'bb_admin';
    const SOURCE_MODEL_ARCHIVE = 'model_archive';
    const SOURCE_MIGRATION     = 'migration';

    /** Дней в одном шаге тарифа. Конвертация фиксированная — см. docs/tariffs.md. */
    public static function stepDays($step)
    {
        switch ($step) {
            case 'day':   return 1;
            case 'week':  return 7;
            case 'month': return 30;
            case 'year':  return 365;
            default:      return 0;
        }
    }

    /**
     * Цена за день — нормализованная метрика для сравнения тарифов с разным шагом.
     *
     * @return float|null null, если период нулевой
     */
    public static function pricePerDay($rentAmount, $step, $kolVo)
    {
        $days = self::stepDays($step) * (int) $kolVo;
        if ($days <= 0) {
            return null;
        }
        return round((float) $rentAmount / $days, 2);
    }

    /**
     * @param string      $changeType одна из TYPE_* констант
     * @param Tariff|null $before     состояние до (null для create/baseline)
     * @param Tariff|null $after      состояние после (null для delete)
     * @param string      $source     одна из SOURCE_* констант
     * @param string|null $note
     */
    public static function log($changeType, $before, $after, $source = self::SOURCE_BB_ADMIN, $note = null)
    {
        $subject = $after ?: $before;
        if (!$subject) {
            die('TariffHistory::log() вызван без состояния тарифа: нужен хотя бы один из $before / $after. '
                . 'Тихо пропустить запись нельзя — журнал цен восстановлению не подлежит.');
        }

        $mysqli = Db::getInstance()->getConnection();

        $columns = [
            'tarif_id'      => (int) $subject->tarif_id,
            'model_id'      => (int) $subject->model_id,
            'change_type'   => self::quote($mysqli, $changeType),
            'changed_at'    => time(),
            'actor_user_id' => self::actorUserId(),
            'actor_name'    => self::quote($mysqli, self::actorName()),
            'source'        => self::quote($mysqli, $source),
            'ip'            => self::quote($mysqli, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null),
            'note'          => self::quote($mysqli, $note),
        ];

        $columns = array_merge($columns, self::snapshotColumns($mysqli, 'old', $before));
        $columns = array_merge($columns, self::snapshotColumns($mysqli, 'new', $after));

        $query = 'INSERT INTO rent_tarif_history (' . implode(', ', array_keys($columns)) . ') '
               . 'VALUES (' . implode(', ', array_values($columns)) . ')';

        if (!$mysqli->query($query)) {
            die('Сбой при записи истории тарифов: ' . $query . ' (' . $mysqli->errno . ') ' . $mysqli->error);
        }
    }

    /**
     * Последние события по модели, новые сверху.
     *
     * @return array[] ассоциативные строки таблицы
     */
    public static function forModel($modelId, $limit = 50)
    {
        $modelId = (int) $modelId;
        $limit   = max(1, (int) $limit);

        $mysqli = Db::getInstance()->getConnection();
        $query  = "SELECT * FROM rent_tarif_history
                   WHERE model_id = {$modelId}
                   ORDER BY changed_at DESC, id DESC
                   LIMIT {$limit}";

        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->errno . ') ' . $mysqli->error);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Колонки снимка одной стороны события.
     *
     * @param string      $prefix 'old' | 'new'
     * @param Tariff|null $t
     * @return array<string,string|int>
     */
    private static function snapshotColumns($mysqli, $prefix, $t)
    {
        if (!$t) {
            return [
                $prefix . '_step'          => 'NULL',
                $prefix . '_kol_vo'        => 'NULL',
                $prefix . '_kol_vo_min'    => 'NULL',
                $prefix . '_rent_amount'   => 'NULL',
                $prefix . '_rent_per_step' => 'NULL',
                $prefix . '_start_date'    => 'NULL',
                $prefix . '_sort_num'      => 'NULL',
            ];
        }

        return [
            $prefix . '_step'          => self::quote($mysqli, $t->step),
            $prefix . '_kol_vo'        => (int) $t->kol_vo,
            $prefix . '_kol_vo_min'    => (int) $t->kol_vo_min,
            $prefix . '_rent_amount'   => "'" . number_format((float) $t->rent_amount, 2, '.', '') . "'",
            $prefix . '_rent_per_step' => "'" . number_format((float) $t->rent_per_step, 2, '.', '') . "'",
            $prefix . '_start_date'    => $t->start_date instanceof \DateTime ? $t->start_date->getTimestamp() : 0,
            $prefix . '_sort_num'      => (int) $t->sort_num,
        ];
    }

    /** @return string SQL-литерал: экранированная строка либо NULL */
    private static function quote($mysqli, $value)
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        return "'" . $mysqli->real_escape_string($value) . "'";
    }

    /** @return int|string id пользователя из сессии либо SQL NULL */
    private static function actorUserId()
    {
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0
            ? (int) $_SESSION['user_id']
            : 'NULL';
    }

    /** @return string|null ФИО из сессии */
    private static function actorName()
    {
        return isset($_SESSION['user_fio']) && $_SESSION['user_fio'] !== ''
            ? $_SESSION['user_fio']
            : null;
    }
}
