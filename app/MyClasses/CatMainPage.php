<?php

namespace App\MyClasses;

use bb\Base;
use bb\classes\Category;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Razdel;
use bb\classes\SubRazdel;
use bb\Db;
use http\Env\Request;

class CatMainPage
{
    private $url_name;
    private $url_name_id;
    private $cat_name;

    /**
     * @var L2ModelWeb []
     */
    private $models;

    private $breadCrumbsArray;

    private $block1;
    private $block2;
    private $h1_title;
    private $meta_description;

    private $_showAgeFilter;

    /**
     * @return mixed
     */
    public function getMetaDescription()
    {
        return $this->meta_description;
    }

    /**
     * @param mixed $meta_description
     */
    public function setMetaDescription($meta_description): void
    {
        $this->meta_description = $meta_description;
    }


    /**
     * @return mixed
     */
    public function getShowAgeFilter()
    {
        return $this->_showAgeFilter;
    }

    /**
     * @param mixed $showAgeFilter
     */
    public function setShowAgeFilter($showAgeFilter): void
    {
        $this->_showAgeFilter = $showAgeFilter;
    }

    public function __construct($lang='ru')
    {
        $this->breadCrumbsArray=[];
        $this->breadCrumbsArray['Главная']='/ru/';
    }

    /**
     * @param $text
     * @param $urlKey
     * @return void
     */
    public function addBreadCrumbItem($text, $urlKey){
        $this->breadCrumbsArray[$text] = $urlKey;
    }

    /**
     * @return mixed
     */
    public function getPageTitle() {
        return $this->h1_title;
    }

    public function setPageTitle($title) {
        $this->h1_title = $title;
    }

    /**
     * @param $razdelUrl
     * @return CatMainPage
     */
    public static function createPageByRazdelUrlName($lang='ru', $razdelUrl) {
        $p = new self();

        if ($razdel = Razdel::getByUrlName($razdelUrl)) {
            $p->setPageTitle($razdel->getNameRazdelText());
            $p->setH1Title($razdel->getNameRazdelText());
            $p->addBreadCrumbItem($razdel->getNameRazdelText(), '');

            $modelIdArray = Model::getModelIdsArrayByRazdelUrlName($razdelUrl);

            foreach ($modelIdArray as $mid) {
                if ($l2m=L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
            }
        }
        else {
            $p->setPageTitle('Раздел не найден!');
        }


        return $p;
    }

    /**
     * @param $razdelUrlCode
     * @return bool
     */
    public function isInRazdel($razdelUrlCode){
        $cat = Category::getByUrlName($this->url_name);
        $razdelUrls = Razdel::getRazdelUrlNamesForCatId($cat->getId());
        if (in_array($razdelUrlCode, $razdelUrls)) {
            return true;
        }
        else{
            return false;
        }
    }


    /**
     * @param $lang
     * @param $razdelUrl
     * @param $subRazdelUrl
     * @return CatMainPage
     */
    public static function createPageByRazdelAndSubRazdelUrlNames($lang='ru', $razdelUrl, $subRazdelUrl){
        $p = new self();

        $razdel = Razdel::getByUrlName($razdelUrl);
        $subRazdel = SubRazdel::getByUrlName($subRazdelUrl);

        if ($razdel && $subRazdel) {

            $p->setPageTitle($subRazdel->getNameSubRazdelText().' напрокат - '.$razdel->getNameRazdelText());
            $p->setH1Title($subRazdel->getNameSubRazdelText());
            $p->addBreadCrumbItem($razdel->getNameRazdelText(), $razdel->getUrlForPage($lang));
            $p->addBreadCrumbItem($subRazdel->getNameSubRazdelText(), '');

            $modelIdArray = Model::getModelIdsArrayByRazdelAndSubRazdelNames($razdelUrl, $subRazdelUrl);

            foreach ($modelIdArray as $mid) {
                if ($l2m=L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
            }
        }
        else {
            $p->setPageTitle('Раздел не найден!');
        }

        return $p;

    }

    /**
     * @param $lang
     * @param $razdelUrl
     * @param $subRazdelUrl
     * @param $catUrlName
     * @return CatMainPage
     */
    public static function createPageByRazdelAndSubRazdelAndCatUrlNames($lang='ru', $razdelUrl, $subRazdelUrl, $catUrlName){
        $p = new self();

        $razdel = Razdel::getByUrlName($razdelUrl);
        $subRazdel = SubRazdel::getByUrlName($subRazdelUrl);
        $cat = Category::getByUrlName($catUrlName);

        if ($razdel && $subRazdel && $cat) {

            $p->setPageTitle($cat->getName().'напрокат в Минске - '.$subRazdel->getNameSubRazdelText());
            $p->setH1Title($cat->getName());
            $p->addBreadCrumbItem($razdel->getNameRazdelText(), $razdel->getUrlForPage($lang));
            $p->addBreadCrumbItem($subRazdel->getNameSubRazdelText(), $subRazdel->getUrlForPage($lang, $razdel->getUrlRazdelName()));
            $p->addBreadCrumbItem($cat->getName(), '');

            $modelIdArray = Model::getModelIdsArrayByCategoryId($cat->getId());

            foreach ($modelIdArray as $mid) {
                if ($l2m=L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
            }
        }
        else {
            $p->setPageTitle('Раздел не найден!');
        }

        return $p;
    }

    /**
     * @param $model_id
     * @return CatMainPage
     */
    public static function getPageByCatId($cat_id): CatMainPage
    {
        $p=new self();
        $m_ids=Category::getModelsForCategoryById($cat_id);


        foreach ($m_ids as $mid) {
            if ($l2m=L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
        }

        return $p;
    }

    /**
     * @return array
     */
    public function getBreadCrumbsArray(){
        return $this->breadCrumbsArray;
    }

    /**
     * @param $url_name
     * @return CatMainPage
     */
    public static function getPageForCatByUrlName($url_name): CatMainPage
    {

      $mysqli = Db::getInstance()->getConnection();
      $url_name = $mysqli->real_escape_string($url_name);


        $p=new self();
        $m_ids=array();

        $m=CatMenuItem::getItemByUrlName($url_name);

        $p->setUrlName($url_name);
        $p->setUrlNameId($m->getId());
        $p->setCatName($m->getCatNameText());

        $p->loadPageWebBlocks();

        if (is_array($m->getCatIds())) {
            //for additional pics of additionals cats for webs for models
            $add_pics=array();

            foreach ($m->getCatIds() as $cat_id) {
                $cat=Category::getModelsForCategoryById($cat_id, 1);
                if ($cat && is_array($cat)){
                    $m_ids = array_merge($m_ids, $cat);
                }
                //dd($m_ids);

                //add additional models
                $add=ModelWeb::getAdditionalModelIdsForCat($cat_id, 1);

                if ($add){
                    foreach ($add as $model_id => $add_pic_url)
                    $m_ids[]=$model_id;
                    $add_pics[$model_id]=$add_pic_url;
                }
            }


            if (is_array($m_ids)) {
                foreach ($m_ids as $mid) {
                    if ($l2m = L2ModelWeb::getL2ModelWebById($mid)) {
                        if (key_exists($mid, $add_pics)) $l2m->changePicUrlAddWeb($add_pics[$mid]);
                        $p->addL2ModelWeb($l2m);
                    }
                }
            }
        }

        ;
        $p->addBreadCrumbItem(strip_tags($m->getCatNameText()), '');

        return $p;
    }

    /**
     * @return mixed
     */
    public function getBlock2()
    {
        return $this->block2;
    }

    /**
     * @param mixed $block2
     */
    public function setBlock2($block2): void
    {
        $this->block2 = $block2;
    }

    /**
     * @return L2ModelWeb[]
     */
    public function getModels()
    {
        return $this->models;
    }

    /**
     * @param L2ModelWeb $mw
     */
    public function addL2ModelWeb(L2ModelWeb $mw) {
        $this->models[]=$mw;
    }

    /**
     * @param $name
     */
    public function setUrlName($name){
        $this->url_name=$name;
    }

    /**
     * @param $id
     */
    public function setUrlNameId($id){
        $this->url_name_id=$id;
    }

    /**
     * @param $name
     */
    public function setCatName($name){
        $this->cat_name=$name;
    }

    /**
     * @param $code
     */
    public function setBlock1($code){
        $this->block1=$code;
    }

    /**
     * @return mixed
     */
    public function getBlock1(){
        return $this->block1;
    }


    public function hasBlock1(){
        if (strlen($this->block1)>0) return true;
        else return false;
    }

    public function hasBlock2(){
        return false;
    }

    /**
     * @return mixed
     */
    public function getH1Title(){
        return $this->h1_title;
    }

    /**
     * @param $title
     */
    public function setH1Title($title){
        $this->h1_title=$title;
    }

    public function loadPageWebBlocks(){
        $mysqli=Db::getInstance()->getConnection();

        $q="SELECT * FROM cat_pages WHERE cat_name='$this->url_name'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при вставке временной брони в MYSQL: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        if ($result->num_rows>0) {
            $rez=$result->fetch_assoc();
            $info=$rez['block1'];
            $this->setH1Title($rez['title1']);
        }
        else {
            $info = '
                Прокат детских электронных весов - это разумно и удобно. Нет надобности покупать дорогостоящие весы, которые, как правило, нужны на несколько месяцев, но в то же время контроль за прибавкой веса необходим каждому малышу в первые месяцы жизни, ведь это важный показатель его здоровья и развития. Особенно необходимы электронные весы для недоношенных деток, и для малышей, находящихся на грудном вскармливании - ведь в этом случае только с помощью электронных весов мама точно сможет определить сколько молочка съел кроха за кормление. Также прокат детских электронных весов избавит молодых родителей от необходимости часто посещать поликлинику и позволит ежедевно контролировать набор веса ребенка.
                                Электронные весы для новорожденных
                                Электронные детские весы сконструированы, для того чтобы помочь Вам отслеживать изменения в весе вашего ребенка, безошибочно определяя, сколько питания усвоилось после каждого кормления.
                                Функция \'TARE\', позволяет Вам взвешивать ребенка, не учитывая вес пеленки, на которой лежит малыш.
                                Функция \'WEIGHT-BLOCK\' показывает точный вес ребенка, даже если Ваш малыш лежит неспокойно.
                                Максимальный вес измерения 20кг.
                                Дискретность измерения в 5 гр.
                ';
        }
        $this->setBlock1($info);

    }

    /**
     * @return int
     */
    public function getModelsNum(){
        if (is_array($this->models)) {
            return count($this->models);
        }
        else{
            return 0;
        }
    }

}
