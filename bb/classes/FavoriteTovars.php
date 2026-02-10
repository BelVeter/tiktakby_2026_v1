<?php
namespace bb\classes;

use bb\Db;

class FavoriteTovars
{
    private $id;
    private $model_id;
    private $pic_url;
    private $pic_alt;
    private $name_text;
    private $description;
    private $active;//reserved
    private $type;//reserved
    private $change_time;
    private $cachedUrl;

    /**
     * @return mixed
     */
    public function getCachedUrl()
    {
        return $this->cachedUrl;
    }

    /**
     * @param mixed $cachedUrl
     */
    public function setCachedUrl($cachedUrl): void
    {
        $this->cachedUrl = $cachedUrl;
    }

    /**
     * @return mixed
     */
    public function getPicAlt()
    {
        return $this->pic_alt;
    }

    /**
     * @param mixed $pic_alt
     */
    public function setPicAlt($pic_alt): void
    {
        $this->pic_alt = $pic_alt;
    }

    /**
     * @return mixed
     */
    public function getPicUrl()
    {
        return $this->pic_url;
    }

    /**
     * @param mixed $pic_url
     */
    public function setPicUrl($pic_url): void
    {
        $this->pic_url = $pic_url;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getModelId()
    {
        return $this->model_id;
    }

    /**
     * @param mixed $model_id
     */
    public function setModelId($model_id): void
    {
        $this->model_id = $model_id;
    }

    /**
     * @return mixed
     */
    public function getNameText()
    {
        return $this->name_text;
    }

    /**
     * @param mixed $name_text
     */
    public function setNameText($name_text): void
    {
        $this->name_text = $name_text;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @param mixed $active
     */
    public function setActive($active): void
    {
        $this->active = $active;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getChangeTime()
    {
        return $this->change_time;
    }

    /**
     * @param mixed $change_time
     */
    public function setChangeTime($change_time): void
    {
        $this->change_time = $change_time;
    }




    /**
     * @return bool|void
     */
    public function save()
    {
        if ($this->getId() > 0)
            $this->update();
        else {
            $mysqli = Db::getInstance()->getConnection();

            $query = "INSERT INTO favorite_tovars SET model_id='$this->model_id', pic_url='$this->pic_url', pic_alt='$this->pic_alt', name_text='" . addslashes($this->name_text) . "', description='" . addslashes($this->description) . "', active='$this->active', `type`='$this->type'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $this->setId($mysqli->insert_id);
        }
        return true;
    }

    /**
     * @return bool|void
     */
    public function update()
    {
        $mysqli = Db::getInstance()->getConnection();

        $query = "UPDATE favorite_tovars SET id='$this->id', model_id='$this->model_id', pic_url='$this->pic_url', pic_alt='$this->pic_alt', name_text='" . addslashes($this->name_text) . "', description='" . addslashes($this->description) . "', active='$this->active', `type`='$this->type', change_time='$this->change_time' WHERE id='$this->id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function delete()
    {
        self::deleteById($this->getId());
        return true;
    }

    /**
     * @return bool|void
     */
    public static function deleteById($id)
    {
        $mysqli = Db::getInstance()->getConnection();

        $query = "DELETE FROM favorite_tovars WHERE id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        return true;
    }

    /**
     * @return FavoriteTovars[]|false|void
     */
    public static function getAll()
    {
        return \Illuminate\Support\Facades\Cache::remember('favorite_tovars_all', 1440, function () {
            $rez = [];

            $mysqli = Db::getInstance()->getConnection();

            $query = "SELECT * FROM favorite_tovars";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }

            if ($result->num_rows < 1)
                return false;

            while ($row = $result->fetch_assoc()) {
                /** @var FavoriteTovars $ft */
                $ft = self::createFromDbArray($row);
                $ft->setCachedUrl($ft->getUrlL3Page());
                $rez[] = $ft;
            }

            return $rez;
        });
    }

    /**
     * @param $model_id
     * @return false|FavoriteTovars|void
     */
    public static function getByModelId($model_id)
    {
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM favorite_tovars WHERE model_id='$model_id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        if ($result->num_rows < 1)
            return false;

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * @param $id
     * @return false|FavoriteTovars|void
     */
    public static function getBylId($id)
    {
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM favorite_tovars WHERE id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        if ($result->num_rows < 1)
            return false;

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * @param $row
     * @return FavoriteTovars
     */
    private static function createFromDbArray($row)
    {
        $ft = new self();

        $ft->setId($row['id']);
        $ft->setModelId($row['model_id']);
        $ft->setPicUrl($row['pic_url']);
        $ft->setPicAlt($row['pic_alt']);
        $ft->setNameText($row['name_text']);
        $ft->setDescription($row['description']);
        $ft->setActive($row['active']);
        $ft->setType($row['type']);
        $ft->setChangeTime($row['change_time']);

        return $ft;
    }

    /**
     * @return string
     */
    public function getUrlL3Page()
    {
        if (!\bb\classes\ModelWeb::getByModelId($this->getModelId())) {
            return '';
        }
        return \bb\classes\ModelWeb::getByModelId($this->getModelId())->getUrlPageAddress();
    }


}
