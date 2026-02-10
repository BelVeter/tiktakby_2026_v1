<?php

namespace bb\classes;

use bb\Db;

class Producer
{
    private $name;
    private $url;
    /**
     * @var Producer[]
     */
    private static $_prodicers;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
      //<img src="http://www.tiktak.by/images/logo_microlife.png" alt="rent of Microlife items">
      $url = $this->url;
      $url = str_replace('http://www.tiktak.by','',$url);

        return $url;
    }

    /**
     * @return string
     */
    public function getNameUrlEncoded(){
        return urlencode($this->getName());
    }


    /**
     * @param mixed $url
     */
    public function setUrl($url): void
    {
        $this->url = $url;
    }


    /**
     * @return Producer[]|false|void
     */
    public static function getAllProducersTovExists(){
        if (is_array(self::$_prodicers)) return self::$_prodicers;

        $rez = [];

        $mysqli = Db::getInstance()->getConnection();
        $query = "SELECT tovar_rent.producer, MAX(rent_model_web.logo) as logo FROM `tovar_rent`
                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id=tovar_rent.tovar_rent_id
                    LEFT JOIN rent_model_web ON rent_model_web.model_id =tovar_rent.tovar_rent_id
                    WHERE tovar_rent_items.item_id > 0 AND rent_model_web.logo != ''
                    GROUP BY tovar_rent.producer";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        if ($result->num_rows < 1) return false;

        while ($row = $result->fetch_assoc()) {
            $rez[]=self::getFromDbArray($row);
        }

        self::$_prodicers=$rez;


        return $rez;

    }


    /**
     * @param $row
     * @return Producer
     */
    private static function getFromDbArray($row){
        $pr = new self();

        $pr->setName($row['producer']);
//        $pr->setUrl(ModelWeb::getURLCorrectPathFor( $row['logo']));
        $pr->setUrl($row['logo']);

        return $pr;
    }

}
