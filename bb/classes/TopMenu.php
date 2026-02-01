<?php

namespace bb\classes;

use bb\Base;
use bb\Db;

class TopMenu
{
    /**
     * @var TopMenu
     */
    private static $_self;

    private $lang='';
    /**
     * @var Razdel[]
     */
    private $razdels;

    /**
     * @var SubRazdel[]
     */
    private $subRazdels;

    /**
     * @var Category[]
     */
    private $categories;

    /**
     * @return mixed|string
     */
    public function getLang()
    {
        return strtoupper($this->lang);
    }

    /**
     * @param mixed|string $lang
     */
    public function setLang($lang): void
    {
        $this->lang = $lang;
    }


    /**
     * @return array
     */
    public function getLangHrefArrayForCurrentPage($currentPath){
        $langArray=[
            ['ru', ''],
            ['lt', ''],
            ['en', ''],
        ];

        $rezLangArray=[];

        $urlArray=explode('/',$currentPath);
        $currentLang= $urlArray[0];

        foreach ($langArray as $lang) {
            if ($lang[0] == $currentLang) {
                continue;
            }
            $urlArray[0]=$lang[0];
            //form url
            $lang[1] = implode('/', $urlArray);
            $rezLangArray[]=$lang;
        }
        return $rezLangArray;
    }


    /**
     * @param $razdelUrl
     * @return Razdel|false|mixed
     */
    public function getRazdel($razdelUrl){
        foreach ($this->getRazdels() as $razdel) {
            if ($razdel->getUrlRazdelName()==$razdelUrl) return $razdel;
        }

        //default
        $ar = $this->getRazdels();
        if($ar) return reset($ar);

        return false;
    }

    /**
     * @param $subRazdelUrl
     * @return SubRazdel|false|mixed
     */
    public function getSubRazdel($subRazdelUrl){
        foreach ($this->getAllSubRasdels() as $sr) {
            if ($sr->getUrlSubRazdelName()==$subRazdelUrl) return $sr;
        }
        return false;
    }


    /**
     * @return Razdel[]|false|void
     */
    public function getRazdels(){
        return $this->razdels;
    }

    /**
     * @return SubRazdel[]|false
     */
    public function getAllSubRasdels(){
        $rez = [];
        foreach ($this->getRazdels() as $r) {
            if ($r->getSubRazdels()) {
                foreach ($r->getSubRazdels() as $sr) {
                    $rez[$sr->getIdSubRazdel()]=$sr;
                }
            }
        }
        if (count($rez)>0){
            return $rez;
        }
        else return false;
    }

    /**
     * @param $lang
     * @return TopMenu
     */
    public static function getTopMenu($lang){
        if (!self::$_self) self::$_self = new self($lang);

        return  self::$_self;
    }

    /**
     * @param $razdelUrlCode
     * @param $subRazelUrlCode
     * @param $lineNumber
     * @return array
     */
    public function getNexLevelMenuArrayLine($razdelUrlCode, $subRazelUrlCode, $lineNumber){
        $outArray = [];
        if ($razdelUrlCode && !$subRazelUrlCode) {
            $r = $this->getRazdel($razdelUrlCode);
            if ($r && $r->getSubRazdels()){
                foreach ($r->getSubRazdels() as $sr) {
                    $outArray[]=[$sr->getUrlForPage($this->lang, $razdelUrlCode), $sr->getNameSubRazdelText()];
                }
            }
        }
        elseif ($subRazelUrlCode) {
            $sr = $this->getSubRazdel($subRazelUrlCode);
            if ($sr && $sr->getCategories()) {
                foreach ($sr->getCategories() as $cat) {
                    $outArray[]=[$cat->getUrlForPage($this->lang), $cat->getName()];
                }
            }
        }

        if ($lineNumber==1 && count($outArray)<4) return $outArray;

        $rezArray = [];

        $totalItemCount = count($outArray);
        $middle = round($totalItemCount/2, 0);

        foreach ($outArray as $key => $value) {
            if ($lineNumber==1) {
                if ($key*1+1 <= $middle) {
                    $rezArray[]=$value;
                }
            }
            else {//line number ==2
                if ($key*1+1 > $middle) {
                    $rezArray[]=$value;
                }
            }
        }

        if (count($outArray)<4){
            if ($lineNumber==1) return $outArray;
            else return [];
        }
        else{
            return $rezArray ;
        }
    }


    /**
     * @param $lang
     */
    public function __construct($lang='ru')
    {
        $this->lang=$lang;
        if ($lang=='') $this->lang='ru';
        $mysqli = Db::getInstance()->getConnection();

        $this->razdels = Razdel::getAll($lang);
        $this->subRazdels = SubRazdel::getAll($lang);
        $this->categories = Category::getAllCategories($lang);

        //1. categories to subrazdels pairing
        $query = "SELECT * FROM subrazdel_category ORDER BY id_sub_razdel";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                //pair subrazdels and categories
                if (key_exists($row['id_sub_razdel'], $this->subRazdels) && key_exists($row['tovar_rent_cat_id'], $this->categories)) {
                    $this->subRazdels[$row['id_sub_razdel']]->addCategory($this->categories[$row['tovar_rent_cat_id']]);
                }
                else {
                    //!!!add error handling
                }
            }
            foreach ($this->subRazdels as &$sr) {
                $sr->sortCategoriesBySortNumber();
            }
        }

        //!2. razdels & subrazdels pairing
        $query = "SELECT * FROM razdel_subrazdel ORDER BY id_razdel";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                //pair razdels and subrazdels
                if (key_exists($row['id_razdel'], $this->razdels) && key_exists($row['id_sub_razdel'], $this->subRazdels)) {
                    $this->razdels[$row['id_razdel']]->addSubRazdel($this->subRazdels[$row['id_sub_razdel']]);
                }
                else {
                    //!!!add error handling
                }
            }
        }

        //sortin
        //SubRazd sort function
//        function sortSubRazdel(SubRazdel $a, SubRazdel $b) {
//            if ($a->getOrderNumSubRazd() == $b->getOrderNumSubRazd()) {
//                return $a->getIdSubRazdel() - $b->getIdSubRazdel();
//            }
//            else {
//                return $a->getOrderNumSubRazd() - $b->getOrderNumSubRazd();
//            }
//        }

        foreach ($this->razdels as $r) {
            $toSort = $r->getSubRazdels();
            if(is_array($toSort)) {
                usort($toSort, function (SubRazdel $a, SubRazdel $b) {
                    if ($a->getOrderNumSubRazd() == $b->getOrderNumSubRazd()) {
                        return $a->getIdSubRazdel() - $b->getIdSubRazdel();
                    }
                    else {
                        return $a->getOrderNumSubRazd() - $b->getOrderNumSubRazd();
                    }
                });
                $r->setSubRazdels($toSort);
            }

        }

    }
}
