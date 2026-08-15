<?php

namespace bb\classes;

use bb\Db;

require_once __DIR__ . '/Similarity.php';

/**
 * Справочник производителей (`producers`). Один бренд — одна запись, один
 * логотип. `tovar_rent.producer` остаётся свободной строкой — справочник
 * источник для ЗАПИСИ и для витрин, 52 читающих места не переписываются
 * (docs/superpowers/specs/2026-08-14-producers-directory-design.md).
 *
 * Скрытые (is_active=0) не показываются в подсказках (getAllActive()), но
 * находятся при вводе точного названия через findDuplicates() — иначе
 * скрытый бренд стало бы невозможно найти и включить обратно, отдельной
 * страницы управления в проекте нет.
 */
class Producer
{
    private $producer_id;
    private $name = '';
    private $name_norm = '';
    private $logo = '';
    private $comment = '';
    private $is_active = true;
    private $cr_time;
    private $cr_user_id;
    private $ch_time;
    private $ch_user_id;

    /**
     * @var Producer[]
     */
    private static $_prodicers;

    public static function getMysqlTableName()
    {
        return 'producers';
    }

    public function getId()
    {
        return $this->producer_id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Тот же контракт, что был у старого DTO: адрес логотипа без
     * абсолютного префикса домена. Сохранён ради home.blade.php — см. Task 11.
     */
    public function getUrl()
    {
        return str_replace('http://www.tiktak.by', '', $this->logo);
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function isActive()
    {
        return (bool) $this->is_active;
    }

    public function setActive($active)
    {
        $this->is_active = (bool) $active;
    }

    public function getNameUrlEncoded()
    {
        return urlencode($this->getName());
    }

    private static function createFromDbArray($row)
    {
        $p = new self();
        $p->producer_id = (int) $row['producer_id'];
        $p->name        = $row['name'];
        $p->name_norm   = $row['name_norm'];
        $p->logo        = $row['logo'];
        $p->comment     = $row['comment'];
        $p->is_active   = (bool) $row['is_active'];
        $p->cr_time     = $row['cr_time'];
        $p->cr_user_id  = $row['cr_user_id'];
        $p->ch_time     = $row['ch_time'];
        $p->ch_user_id  = $row['ch_user_id'];

        return $p;
    }

    /**
     * @return Producer|false
     */
    public static function getById($id)
    {
        $id = (int) $id;
        if ($id < 1) {
            return false;
        }

        $mysqli = Db::getInstance()->getConnection();
        $result = $mysqli->query("SELECT * FROM producers WHERE producer_id=$id");
        if (!$result || $result->num_rows < 1) {
            return false;
        }

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * Точное совпадение СЫРОЙ строки (не нормализованной) — этим методом
     * ModelWeb ищет бренд модели по значению tovar_rent.producer, которое
     * должно 1-в-1 совпадать со значением, записанным через справочник.
     *
     * @return Producer|false
     */
    public static function getByName($name)
    {
        $name = (string) $name;
        if ($name === '') {
            return false;
        }

        $mysqli = Db::getInstance()->getConnection();
        $escaped = addslashes($name);
        $result = $mysqli->query("SELECT * FROM producers WHERE name='$escaped'");
        if (!$result || $result->num_rows < 1) {
            return false;
        }

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * Для подсказок живого поиска — только активные.
     *
     * @return Producer[]
     */
    public static function getAllActive()
    {
        return self::queryAll('WHERE is_active=1');
    }

    /**
     * Включая скрытые — для проверки на дубли (findDuplicates()) и для
     * административных списков.
     *
     * @return Producer[]
     */
    public static function getAll()
    {
        return self::queryAll('');
    }

    private static function queryAll($where)
    {
        $mysqli = Db::getInstance()->getConnection();
        $result = $mysqli->query("SELECT * FROM producers $where ORDER BY name");
        if (!$result) {
            return [];
        }

        $out = [];
        while ($row = $result->fetch_assoc()) {
            $out[] = self::createFromDbArray($row);
        }

        return $out;
    }

    /**
     * Точный дубль (после нормализации, включая скрытые) + похожие названия
     * (комбинированный сигнал Jaccard+Левенштейн, только среди активных —
     * предлагать слияние со скрытым брендом сотруднику смысла нет).
     *
     * @return array{exact: Producer|null, similar: array}
     */
    public static function findDuplicates($name)
    {
        $all = self::getAll();
        $labels = [];
        foreach ($all as $i => $p) {
            $labels[$i] = $p->getName();
        }

        $exactKey = Similarity::findExact($name, $labels);
        $exact = $exactKey === false ? null : $all[$exactKey];

        $activeLabels = [];
        foreach ($all as $i => $p) {
            if ($p->isActive()) {
                $activeLabels[$i] = $p->getName();
            }
        }

        $similar = [];
        foreach (Similarity::findSimilarByEdit($name, $activeLabels) as $match) {
            $similar[] = ['producer' => $all[$match['key']], 'score' => $match['score']];
        }

        return ['exact' => $exact, 'similar' => $similar];
    }

    /**
     * @return bool
     */
    public function save()
    {
        $mysqli = Db::getInstance()->getConnection();

        $name = addslashes($this->name);
        $nameNorm = addslashes(Similarity::normalize($this->name));
        $logo = addslashes($this->logo);
        $comment = addslashes($this->comment);
        $isActive = $this->is_active ? 1 : 0;
        $now = time();

        if ($this->producer_id > 0) {
            $query = "UPDATE producers SET
                name='$name', name_norm='$nameNorm', logo='$logo', comment='$comment',
                is_active=$isActive, ch_time=$now
                WHERE producer_id={$this->producer_id}";
        } else {
            $query = "INSERT INTO producers SET
                name='$name', name_norm='$nameNorm', logo='$logo', comment='$comment',
                is_active=$isActive, cr_time=$now";
        }

        $result = $mysqli->query($query);
        if (!$result) {
            return false;
        }

        if ($this->producer_id === null) {
            $this->producer_id = $mysqli->insert_id;
        }

        \Illuminate\Support\Facades\Cache::forget('all_producers_tov_exists');

        return true;
    }

    /**
     * Масштаб переименования — для предпросмотра перед подтверждением
     * (спека: «затронет 11 моделей, 15 единиц, 4 архивных записи»).
     *
     * @return array{models: int, items: int, items_arch: int}
     */
    public function impactOfRename()
    {
        $mysqli = Db::getInstance()->getConnection();
        $name = addslashes($this->name);

        $count = function ($table, $column = 'producer') use ($mysqli, $name) {
            $result = $mysqli->query("SELECT COUNT(*) n FROM $table WHERE $column='$name'");
            return (int) $result->fetch_assoc()['n'];
        };

        return [
            'models'      => $count('tovar_rent'),
            'items'       => $count('tovar_rent_items'),
            'items_arch'  => $count('tovar_rent_items_arch'),
        ];
    }

    /**
     * Переименовывает бренд везде: справочник + три таблицы каталога —
     * одной транзакцией, либо всё, либо ничего. Не проверяет, что $newName
     * уже не занят другим брендом — это ответственность вызывающего кода
     * (ajax_producer_update.php), как Category::save() не проверяет
     * уникальность cat_url_key сама.
     *
     * @return bool
     */
    public function rename($newName, $userId)
    {
        $mysqli = Db::getInstance()->getConnection();
        $oldName = addslashes($this->name);
        $newNameEsc = addslashes($newName);

        Db::startTransaction();

        $ok = $mysqli->query("UPDATE tovar_rent SET producer='$newNameEsc' WHERE producer='$oldName'")
            && $mysqli->query("UPDATE tovar_rent_items SET producer='$newNameEsc' WHERE producer='$oldName'")
            && $mysqli->query("UPDATE tovar_rent_items_arch SET producer='$newNameEsc' WHERE producer='$oldName'");

        if (!$ok) {
            Db::rollBackTransaction();
            return false;
        }

        $this->name = $newName;
        $this->ch_user_id = $userId;

        if (!$this->save()) {
            Db::rollBackTransaction();
            return false;
        }

        Db::commitTransaction();

        return true;
    }

    /**
     * Для главной страницы: бренды с живыми товарами И логотипом. Читает
     * пока со старого источника (GROUP BY MAX(logo) по копиям в
     * rent_model_web) — переключение на справочник делается отдельным шагом
     * (Task 11), после того как заливка логотипа начнёт писать в него.
     *
     * @return Producer[]|false|void
     */
    public static function getAllProducersTovExists()
    {
        return \Illuminate\Support\Facades\Cache::remember('all_producers_tov_exists', 1440, function () {
            if (is_array(self::$_prodicers))
                return self::$_prodicers;

            $rez = [];

            $mysqli = Db::getInstance()->getConnection();
            $query = "SELECT tovar_rent.producer, MAX(rent_model_web.logo) as logo FROM `tovar_rent`
                        LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id=tovar_rent.tovar_rent_id
                        LEFT JOIN rent_model_web ON rent_model_web.model_id =tovar_rent.tovar_rent_id
                        WHERE tovar_rent_items.item_id > 0 AND rent_model_web.logo != ''
                        GROUP BY tovar_rent.producer";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }

            if ($result->num_rows < 1)
                return false;

            while ($row = $result->fetch_assoc()) {
                $p = new self();
                $p->name = $row['producer'];
                $p->logo = $row['logo'];
                $rez[] = $p;
            }

            self::$_prodicers = $rez;

            return $rez;
        });
    }
}
