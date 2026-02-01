<?php


namespace bb\classes;


use bb\Db;

class CurKassaLine
{
    public $cur_id;
    public $r_to_pay;
    public $del_to_pay;

    public $r_paid_k1;
    public $r_paid_k2;
    public $r_paid_card;

    public $del_paid_k1;
    public $del_paid_k2;
    public $del_paid_card;

    public function getToPayTotal(){

    }

    public function getRentPaidTotal(){
        return $this->r_paid_k1+$this->r_paid_k2+$this->r_paid_card;
    }

    public function getCurPaidTotal(){
        return $this->del_paid_k1+$this->del_paid_k2+$this->del_paid_card;
    }

    /**
     * @param \DateTime $date
     * @return CurKassaLine[]
     */
    public static function getLines(\DateTime $date){
        /**
         * @var CurKassaLine[]
         */
        $rez = array();

        $mysqli=Db::getInstance()->getConnection();

        $query="SELECT courier_id, SUM(r_to_pay) AS r_to_pay_sum, SUM(delivery_to_pay) AS  delivery_to_pay_sum
                FROM `rent_sub_deals_arch`
                WHERE acc_date='".$date->getTimestamp()."' AND `status` IN ('for_cur', 'delivered')
                GROUP BY courier_id";
//        echo $query.'<br><br>';
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        while ($row = $result->fetch_assoc()){
            if (!isset($rez[$row['courier_id']])) {
                $rez[$row['courier_id']]=new self();
                $rez[$row['courier_id']]->cur_id=$row['courier_id'];
            }

            $rez[$row['courier_id']]->r_to_pay+=$row['r_to_pay_sum'];
            $rez[$row['courier_id']]->del_to_pay+=$row['delivery_to_pay_sum'];

        }

                $query="SELECT courier_id, SUM(r_to_pay) AS r_to_pay_sum, SUM(delivery_to_pay) AS  delivery_to_pay_sum
                        FROM `rent_sub_deals_act`
                        WHERE acc_date='".$date->getTimestamp()."' AND `status` IN ('for_cur', 'delivered')
                        GROUP BY courier_id";
//        echo $query.'<br><br>';
                $result = $mysqli->query($query);
                if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
                while ($row = $result->fetch_assoc()){
                    if (!isset($rez[$row['courier_id']])) {
                        $rez[$row['courier_id']]=new self();
                        $rez[$row['courier_id']]->cur_id=$row['courier_id'];
                    }
                    $rez[$row['courier_id']]->r_to_pay+=$row['r_to_pay_sum'];
                    $rez[$row['courier_id']]->del_to_pay+=$row['delivery_to_pay_sum'];

                }

        //payments for rent
        $query="SELECT courier_id, r_payment_type, SUM(r_paid) AS r_paid_sum
                FROM `rent_sub_deals_arch`
                WHERE acc_date='".$date->getTimestamp()."' AND courier_id>0
                GROUP BY courier_id, r_payment_type";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        while ($row=$result->fetch_assoc()) {
            if ($row['r_paid_sum']==0) continue;

            if (!isset($rez[$row['courier_id']])) {
                $rez[$row['courier_id']]=new self();
                $rez[$row['courier_id']]->cur_id=$row['courier_id'];
            }

            if($row['r_payment_type']=='nal_cheque') $rez[$row['courier_id']]->r_paid_k1+=$row['r_paid_sum'];
            if($row['r_payment_type']=='nal_no_cheque') $rez[$row['courier_id']]->r_paid_k2+=$row['r_paid_sum'];
            if($row['r_payment_type']=='card') $rez[$row['courier_id']]->r_paid_card+=$row['r_paid_sum'];
        }

                $query="SELECT courier_id, r_payment_type, SUM(r_paid) AS r_paid_sum
                        FROM `rent_sub_deals_act`
                        WHERE acc_date='".$date->getTimestamp()."' AND courier_id>0
                        GROUP BY courier_id, r_payment_type";
                $result = $mysqli->query($query);
                if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
                while ($row=$result->fetch_assoc()) {
                    if ($row['r_paid_sum']==0) continue;

                    if (!isset($rez[$row['courier_id']])) {
                        $rez[$row['courier_id']]=new self();
                        $rez[$row['courier_id']]->cur_id=$row['courier_id'];
                    }

                    if($row['r_payment_type']=='nal_cheque') $rez[$row['courier_id']]->r_paid_k1+=$row['r_paid_sum'];
                    if($row['r_payment_type']=='nal_no_cheque') $rez[$row['courier_id']]->r_paid_k2+=$row['r_paid_sum'];
                    if($row['r_payment_type']=='card') $rez[$row['courier_id']]->r_paid_card+=$row['r_paid_sum'];
                }

        //payments for delivery
        $query="SELECT courier_id, del_payment_type, SUM(delivery_paid) AS del_paid_sum
                FROM `rent_sub_deals_arch`
                WHERE acc_date='".$date->getTimestamp()."' AND courier_id>0
                GROUP BY courier_id, del_payment_type";
//        echo $query.'<br><br>';
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        while ($row=$result->fetch_assoc()) {
            if ($row['del_paid_sum']==0) continue;

            if (!isset($rez[$row['courier_id']])) {
                $rez[$row['courier_id']]=new self();
                $rez[$row['courier_id']]->cur_id=$row['courier_id'];
            }

            if($row['del_payment_type']=='nal_cheque') $rez[$row['courier_id']]->del_paid_k1+=$row['del_paid_sum'];
            if($row['del_payment_type']=='nal_no_cheque') $rez[$row['courier_id']]->del_paid_k2+=$row['del_paid_sum'];
            if($row['del_payment_type']=='card') $rez[$row['courier_id']]->del_paid_card+=$row['del_paid_sum'];
        }

                $query="SELECT courier_id, del_payment_type, SUM(delivery_paid) AS del_paid_sum
                        FROM `rent_sub_deals_act`
                        WHERE acc_date='".$date->getTimestamp()."' AND courier_id>0
                        GROUP BY courier_id, del_payment_type";
                $result = $mysqli->query($query);
                if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
                while ($row=$result->fetch_assoc()) {
                    if ($row['del_paid_sum']==0) continue;

                    if (!isset($rez[$row['courier_id']])) {
                        $rez[$row['courier_id']]=new self();
                        $rez[$row['courier_id']]->cur_id=$row['courier_id'];
                    }

                    if($row['del_payment_type']=='nal_cheque') $rez[$row['courier_id']]->del_paid_k1+=$row['del_paid_sum'];
                    if($row['del_payment_type']=='nal_no_cheque') $rez[$row['courier_id']]->del_paid_k2+=$row['del_paid_sum'];
                    if($row['del_payment_type']=='card') $rez[$row['courier_id']]->del_paid_card+=$row['del_paid_sum'];
                }

        return $rez;
    }



}