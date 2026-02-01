<?php


namespace bb\classes;


use bb\Db;

class CatAnalytics
{
    /**
     * @var \DateTime
     */
    public static $from;
    /**
     * @var \DateTime
     */
    public static $to;
    /**
     * @var Category[]
     */
    public static $categories;

    public $cat_id;
    public $cat_name;
    public $items_n;
    public $sales;
    public $deals_n;
    public $days_rented_avg;
    public $tov_buy_price;
    public $rentedOutPercent;


    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return CatAnalytics[]
     */
    public static function getData(\DateTime $from, \DateTime $to) {
        /**
         * @var CatAnalytics[]
         */
        $rez=array();

        $rez=self::getInterimData($from, $to, 'arch', 'act');

            if ($rez1=self::getInterimData($from, $to, 'arch', 'arch')) {
                $rez=self::mergeSales($rez, $rez1);
            }
            if ($rez1=self::getInterimData($from, $to, 'act', 'act')) {
                $rez=self::mergeSales($rez, $rez1);
            }
            if ($rez1=self::getInterimData($from, $to, 'act', 'arch')) {
                $rez=self::mergeSales($rez, $rez1);
            }
//
//        echo '<pre>';
//var_dump($rez);
//        echo '</pre>';

        //add tovars_num
        $tovs=self::getTovNumByCat(new \DateTime());

        foreach ($rez as $r) {
            if (key_exists($r->cat_id, $tovs)){
                $r->items_n=$tovs[$r->cat_id];
            }
        }

        //add deals num
        if ($dl_n=self::getDealsNumByCat($from, $to, 'arch', 'act')) {
            //var_dump($dl_n);
            foreach ($rez as $r) {
                if (key_exists($r->cat_id, $dl_n)) {
                    $r->deals_n+=$dl_n[$r->cat_id];
                }
            }
        }
        if ($dl_n=self::getDealsNumByCat($from, $to, 'arch', 'arch')) {
            //var_dump($dl_n);
            foreach ($rez as $r) {
                if (key_exists($r->cat_id, $dl_n)) {
                    $r->deals_n+=$dl_n[$r->cat_id];
                }
            }
        }
        if ($dl_n=self::getDealsNumByCat($from, $to, 'act', 'act')) {
            //var_dump($dl_n);
            foreach ($rez as $r) {
                if (key_exists($r->cat_id, $dl_n)) {
                    $r->deals_n+=$dl_n[$r->cat_id];
                }
            }
        }

        if ($tov_cost=self::getTovarCost('act')) {
            foreach ($rez as $r) {
                if (key_exists($r->cat_id, $tov_cost)) {
                    $r->tov_buy_price+=$tov_cost[$r->cat_id];
                }
            }
        }
        foreach ($rez as $r){
          $r->rentedOutPercent = Category::getRentedOutDaysPercent($from, $to, [$r->cat_id]);
        }

        return $rez;

    }

    /**
     * @param \DateTime $date
     * @return array
     */
    private static function getTovNumByCat(\DateTime $date){
        $num_cat=array();

        $mysqli=Db::getInstance()->getConnection();
        //count tovars
        $query="
            SELECT tovar_rent.tovar_rent_cat_id, COUNT(item_inv_n) AS num FROM tovar_rent_items
                LEFT JOIN tovar_rent
                ON tovar_rent.tovar_rent_id=tovar_rent_items.model_id
            GROUP BY tovar_rent.tovar_rent_cat_id
                    ";
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }
        while ($r=$result->fetch_assoc()) {
            $num_cat[$r['tovar_rent_cat_id']]=$r['num'];
        }

        return $num_cat;

    }

    /**
     * @param CatAnalytics[] $ar1
     * @param CatAnalytics[] $ar2
     * @return CatAnalytics[]
     */
    private static function mergeSales($ar1, $ar2) {


        foreach ($ar2 as $ar) {
            if (key_exists($ar->cat_id,$ar1)) {
                $ar1[$ar->cat_id]->sales+=$ar->sales;
            }
            else {
                $ar1[$ar->cat_id]=clone $ar;
            }
        }
        return $ar1;
    }



    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return CatAnalytics[]
     */
    private static function getInterimData(\DateTime $from, \DateTime $to, $deal_status, $tov_status) {
        if ($tov_status=='act') {
            $tov_add='';
        }
        else {
            $tov_add='_arch';
        }
        //var_dump(self::$categories);
        if (self::$categories==null) {
            self::$categories=Category::getAllCategories();
        }
        //var_dump(self::$categories);
        $mysqli=Db::getInstance()->getConnection();

        /**
         * @var CatAnalytics[]
         */
        $rez=array();

        $query="
        SELECT tovar_rent.tovar_rent_cat_id, SUM(model_sel.model_sum) AS cat_sum FROM tovar_rent
        LEFT JOIN
            (SELECT it.model_id, SUM(inv_sum.rp_sum_inv) AS model_sum FROM tovar_rent_items$tov_add AS it
            LEFT JOIN
                (SELECT d.item_inv_n, SUM(sd.rp_sum) AS rp_sum_inv FROM rent_deals_$deal_status AS d
                LEFT JOIN
                    (SELECT rsd.deal_id, SUM(rsd.r_paid) as rp_sum
                    FROM rent_sub_deals_$deal_status AS rsd
                    WHERE rsd.acc_date BETWEEN ".$from->getTimestamp()." AND ".$to->getTimestamp()."
                    GROUP BY rsd.deal_id) AS sd

                    ON d.deal_id=sd.deal_id
                WHERE sd.rp_sum>0 OR sd.rp_sum<0
                GROUP BY d.item_inv_n ) AS inv_sum

                ON inv_sum.item_inv_n=it.item_inv_n
            WHERE inv_sum.rp_sum_inv!='NULL'
            GROUP BY it.model_id) AS model_sel

        ON model_sel.model_id=tovar_rent.tovar_rent_id
        WHERE model_sel.model_sum!='NULL'
        GROUP BY tovar_rent.tovar_rent_cat_id
        ";

        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }

        if ($result->num_rows<1) {
            return null;
        }

        while ($rez_db=$result->fetch_assoc()) {

            $row = new self();
            $row->cat_id=$rez_db['tovar_rent_cat_id'];
            if (array_key_exists($row->cat_id,self::$categories)) {
                $row->cat_name=self::$categories[$row->cat_id]->name;
            }
            else {
                $row->cat_name='Не определено';
            }

            $row->sales=$rez_db['cat_sum'];

            $rez[$row->cat_id]=clone $row;
        }

        return $rez;
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return CatAnalytics[]
     */
    private static function getDealsNumByCat(\DateTime $from, \DateTime $to, $deal_status, $tov_status) {
        if ($tov_status=='act') {
            $tov_add='';
        }
        else {
            $tov_add='_arch';
        }
        //var_dump(self::$categories);
        if (self::$categories==null) {
            self::$categories=Category::getAllCategories();
        }
        //var_dump(self::$categories);
        $mysqli=Db::getInstance()->getConnection();

        $rez=array();

        $query="
        SELECT tovar_rent.tovar_rent_cat_id, SUM(model_sel.model_sum) AS cat_sum FROM tovar_rent
        LEFT JOIN
            (SELECT it.model_id, SUM(inv_sum.rp_sum_inv) AS model_sum FROM tovar_rent_items$tov_add AS it
            LEFT JOIN
                (SELECT d.item_inv_n, COUNT(d.item_inv_n) AS rp_sum_inv FROM rent_deals_$deal_status AS d

                WHERE start_date BETWEEN ".$from->getTimestamp()." AND ".$to->getTimestamp()."
                GROUP BY d.item_inv_n ) AS inv_sum

                ON inv_sum.item_inv_n=it.item_inv_n
            WHERE inv_sum.rp_sum_inv!='NULL'
            GROUP BY it.model_id) AS model_sel

        ON model_sel.model_id=tovar_rent.tovar_rent_id
        WHERE model_sel.model_sum!='NULL'
        GROUP BY tovar_rent.tovar_rent_cat_id
        ";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }

        if ($result->num_rows<1) {
            return null;
        }

        while ($rez_db=$result->fetch_assoc()) {
            $rez[$rez_db['tovar_rent_cat_id']]=$rez_db['cat_sum'];
        }

        return $rez;
    }

    /**
     * @param CatAnalytics[] $ar
     * @param string $sort_field
     */
    public static function sortArray(&$ar, $sort_field) {
        switch ($sort_field) {
            case 'sales':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->sales, $b->sales)*-1;
                });
                break;
            case 'tov_num':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->items_n, $b->items_n)*-1;
                });
                break;
            case 'dl_num':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->deals_n, $b->deals_n)*-1;
                });
                break;
            case 'sales_per_dl':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->salesPerDeal(), $b->salesPerDeal())*-1;
                });
                break;
            case 'sales_per_tov':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->salesPerTovar(), $b->salesPerTovar())*-1;
                });
                break;
            case 'cat':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->cat_name, $b->cat_name);
                });
                break;
            case 'tov_cost':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->tov_buy_price, $b->tov_buy_price)*-1;
                });
                break;
            case 'sales_per_cost':
                usort($ar, function (CatAnalytics $a, CatAnalytics $b){
                    return strnatcmp($a->salesPerCost(), $b->salesPerCost())*-1;
                });
                break;
        }
    }

    private static function getTovarCost($tovar_status, \DateTime $date = NULL){
        $rez=array();
        $mysqli=Db::getInstance()->getConnection();
        $query="SELECT tovar_rent.tovar_rent_cat_id AS `cat`, SUM(inv_n_sum.price) AS `model_price` FROM tovar_rent
                    LEFT JOIN
                        (SELECT `model_id`, SUM(IF (`buy_price_cur`='TBYR', `buy_price`, `buy_price`*2)) AS `price`
                        FROM `tovar_rent_items`
                        GROUP BY model_id) as inv_n_sum
                    ON tovar_rent.tovar_rent_id = inv_n_sum.model_id

                    WHERE inv_n_sum.price!='NULL'

                    GROUP by `cat`";
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }

        while ($rez_db=$result->fetch_assoc()) {
            $rez[$rez_db['cat']]=$rez_db['model_price'];
        }
        return $rez;

    }

    public function salesPerDeal() {
        return $this->sales/$this->deals_n;
    }
    public function salesPerTovar(){
        return $this->sales/$this->items_n;
    }
    public function salesPerCost(){
        if ($this->tov_buy_price!=0) {
            return $this->sales / $this->tov_buy_price;
        }
        else return 0;
    }

}
