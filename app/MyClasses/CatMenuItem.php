<?php

namespace App\MyClasses;

use bb\Db;

class CatMenuItem
{
    private $id;
    private $level;
    private $parent_id;
    private $url_name;
    private $name_text;
    private $url_icon;

    /**
     * @var array
     */
    private $cat_ids;

    /**
     * @var CatMenuItem[]
     */
    private $child_items;


    /**
     * @param $ar
     * @return CatMenuItem
     */
    public static function getFromDbArray ($ar){
        $rez=new self();

        $rez->setId($ar['id']);
        $rez->setLevel($ar['level']);
        $rez->setParentId($ar['parent_id']);
        $rez->setUrlName($ar['url_name']);
        $rez->setNameText($ar['name_text']);
        $rez->setMainCatIds(explode(',', $ar['cat_ids']));
        $rez->setUrlIcon($ar['url_icon']);

        return $rez;
    }

    /**
     * @param CatMenuItem $m
     */
    public function addChild(CatMenuItem $m){
        $this->child_items[]=$m;
    }



    /**
     * @return CatMenuItem[]
     */
    public static function getAllMenu(){
        $mysqli=Db::getInstance()->getConnection();
        $query="SELECT * FROM menu_items ORDER BY `level`, sort_num";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        /**
         * @param CatMenuItem[]
         */
        $ar1=array();

        //important - should be ordered by level
        while ($r=$result->fetch_assoc()) {
            $m=CatMenuItem::getFromDbArray($r);
            if ($m->getLevel()==1) {
                $ar1[$m->getId()]=$m;
            }
            elseif ($m->getLevel()==2) {
                if (key_exists($m->parent_id, $ar1)){
                    $ar1[$m->parent_id]->addChild($m);
                }
            }
        }

        return $ar1;

    }

    /**
     * @param $url_name
     * @return CatMenuItem|void
     */
    public static function getItemByUrlName($url_name){
        $mysqli=Db::getInstance()->getConnection();
        $query="SELECT * FROM menu_items WHERE url_name='$url_name'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
        $r=$result->fetch_assoc();
        return CatMenuItem::getFromDbArray($r);
    }

    /**
     * @param $id
     */
    public function setId($id){
        $this->id=$id;
    }

    /**
     * @param $level
     */
    public function setLevel($level){
        $this->level = $level;
    }

    /**
     * @param $parent_id
     */
    public function setParentId($parent_id){
        $this->parent_id=$parent_id;
    }

    /**
     * @param $url_name
     */
    public function setUrlName($url_name){
        $this->url_name = $url_name;
    }

    /**
     * @param $name_text
     */
    public function setNameText($name_text){
        $this->name_text=$name_text;
    }

    /**
     * @param $url_icon
     */
    public function setUrlIcon($url_icon){
        $this->url_icon=$url_icon;
    }

    /**
     * @param $id
     */
    public function setMainCatIds(array $ids){
        $this->cat_ids=$ids;
    }

    /**
     * @return mixed
     */
    public function getUrlCatName(){
        return $this->url_name;
    }

    /**
     * @return string
     */
    public function getUrl(){
        return '/ru/prokat/'.$this->getUrlCatName();
    }

    /**
     * @return mixed
     */
    public function getParenId(){
        return $this->parent_id;
    }

    /**
     * @return mixed
     */
    public function getId(){
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getLevel(){
        return $this->level;
    }

    /**
     * @return mixed
     */
    public function getUrlIcon(){
        return $this->url_icon;
    }

    /**
     * @return mixed
     */
    public function getCatNameText(){
        return $this->name_text;
    }

    /**
     * @return CatMenuItem[]
     */
    public function getChildItems(){
        return $this->child_items;
    }

    /**
     * @return mixed
     */
    public function getMainCatId(){
        return $this->main_cat_id;
    }

    /**
     * @return array
     */
    public function getCatIds(){
        return $this->cat_ids;
    }

    /**
     * @param $cat_url
     * @return bool
     */
    public function isCurrent($cat_url){
        if ($this->url_name==$cat_url) return true;
        else return false;
    }

    /**
     * @param $cat_url
     * @return bool
     */
    public function hasChildByCatUrlName($cat_url){
        $rez=false;
        $chs=$this->getChildItems();

        if(count($chs)<1) return false;
        foreach ($chs as $ch) {

            if ($ch->isCurrent($cat_url)) {
                $rez=true;
            }
        }
        return $rez;
    }

}
