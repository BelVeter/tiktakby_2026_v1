<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 30.05.2019
 * Time: 21:31
 */

namespace bb\models;


class KassaChannel
{

    public $id;
    public $type; //office, bank, cur
    public $text; // reading name of channel
    public $channel_number;//for office, cur - number, for bank - account number

    /**
     * @var KassaChannel[]
     */
    private static $_all_channels;

    public function loadFromSelect($string){
        $start = substr($string,0,4);
        switch ($start) {
            case 'bank':
                $this->type='bank';
                $this->channel_number=substr($string,5);
                $this->channel_number=0;
                break;
            case 'offi':
                $this->type='office';
                $this->channel_number=substr($string,7);
                break;
            case 'pers':
                $this->type='cur';
                $this->channel_number=substr($string,4);
                break;
        }

    }

    public static function getKassaChannel ($channel, $number){
        //!!! разработать
        $ch=new self();
        $ch->type=$channel;
        $ch->channel_number=$number;
        $ch->text=KassaChannel::getKassaChannelName($channel, $number);
        return $ch;
    }

    /**
     * @param $string
     * @return KassaChannel
     */
    public static function loadFormSelect($string){
        $chan=new self();
        $chan->loadFromSelect($string);
        return $chan;
    }

    public function makeSelectValue(){
        return $this->type.'_'.$this->channel_number;
    }

    public function makeSelectText(){
        switch ($this->type) {
            case 'office':
                return $this->text;
                break;
            case 'bank':
                return $this->text;
                break;
            case 'cur':
                return $this->text;
                break;
        }
    }

    /**
     * @param User|null $user
     * @return KassaChannel[]
     */
    public static function getAllowedChannels($type='allowed',User $user=null) {

        if ($user==null) {
            $user=User::getCurrentUser();
        }


        if ($type=='allowed') {

            /**
             * @var Channel[]
             */
            $channels = array();

            //chose offices
            if ($user->hasRole('owner')) {
                $ofs = Office::getAllOffices();

                foreach ($ofs as $of) {
                    $channel = new KassaChannel();
                    $channel->type = 'office';
                    $channel->channel_number = $of->number;
                    $channel->text = $of->name_short;

                    $channels[] = clone $channel;
                }
            }
            else {
                $of = Office::getCurrentOffice();

                $channel = new KassaChannel();
                $channel->type = 'office';
                $channel->channel_number = $of->number;
                $channel->text = $of->name_short;

                $channels[] = $channel;
            }

            //bank
            if ($user->hasRole('owner') || $user->hasRole('accountant')) {
                $ch = new KassaChannel();
                $ch->type = 'bank';
                $ch->text = 'Банк';
                $channels[] = clone $ch;
            }

            if ($user->hasPersonalKassa()) {
                $ch = new KassaChannel();
                $ch->type = 'personal';
                $ch->channel_number = $user->id_user;
                $ch->text = 'персональная касса: ' . $user->getShortName();
                $ch->channel_number = $user->id_user;
                $channels[] = clone $ch;
            }
            return $channels;
        }
        elseif ($type=='shift_to_channel') {

            /**
             * @var Channel[]
             */
            $channels = array();

            //chose offices
                $ofs = Office::getAllOffices();

                foreach ($ofs as $of) {
                    $channel = new KassaChannel();
                    $channel->type = 'office';
                    $channel->channel_number = $of->number;
                    $channel->text = $of->name_short;

                    $channels[] = clone $channel;
                }

            //bank
                $ch = new KassaChannel();
                $ch->type = 'bank';
                $ch->text = 'Банк';
                $channels[] = clone $ch;

            if ($user->hasPersonalKassa()) {
                $ch = new KassaChannel();
                $ch->type = 'personal';
                $ch->channel_number = $user->id_user;
                $ch->text = 'персональная касса: ' . $user->getShortName();
                $ch->channel_number = $user->id_user;
                $channels[] = clone $ch;
            }
            return $channels;
        }
    }

    public function getDbChannelValue() {
        switch ($this->type) {
            case 'bank':
                $rez="bank";
                break;

            case 'office':
                $rez=$this->channel_number;
                break;

            case 'personal':
                $rez="personal";
                break;
        }
    }


    /**
     * @param $channel_type
     * @param $channel_number
     * @return KassaChannel|bool
     */
    public static function getKassaChannelName($channel_type, $channel_number){
        if ($channel_type=='' && $channel_number='') return false;

        $rez = new KassaChannel();

        if (is_numeric($channel_type)) {
            $channel_number=$channel_type;
            $channel_type='office';
        }

        switch ($channel_type) {
            case 'office':
                $rez->type='office';
                $rez->channel_number=$channel_number;
                $rez->text=Office::getOfficeNameByNumber($channel_number);
                break;
            case 'bank':
                $rez->type='bank';
                $rez->channel_number=$channel_number;
                $rez->text='Банк';
                break;
            case 'cur':
                $rez->type='cur';
                $rez->channel_number=$channel_number;
                //$user=User::getUserById($channel_number);
                $rez->text='Касса курьера '.$channel_number;
                break;
        }

        return $rez->text;
    }
}