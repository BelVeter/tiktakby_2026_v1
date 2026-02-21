<?php

namespace App\Http\Controllers;

use App\MyClasses\KBForm;
use bb\Base;
use bb\classes\bron;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Subscription;
use bb\classes\tovar;
use bb\classes\Zvonok;
use bb\Db;
use bb\KBron;
use bb\models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ZvonokController extends Controller
{
  //
  public function addCall(Request $req)
  {
    $modelId = intval($req->input('model_id', 0));

    Zvonok::addLitZvonok(
      $req->input('fio'),
      $req->input('phone'),
      $req->input('info'),
      $modelId > 0 ? $modelId : null,
      $modelId > 0 ? 'zayavka' : null
    );

    // If model_id present — also create a record in rent_orders so it appears in CRM
    if ($modelId > 0) {
      try {
        $validityDate = new \DateTime();
        $validityDate->modify('+14 days'); // default 2-week waiting period
        bron::createZayavka(
          $modelId,
          $req->input('phone'),
          $req->input('fio'),
          '',
          '',
          $validityDate,
          $req->input('info'),
          1
        );
      } catch (\Exception $e) {
        // log but don't break the redirect
        \Illuminate\Support\Facades\Log::error('createZayavka failed: ' . $e->getMessage());
      }
    }

    return Redirect::to($req->header('referer'));
  }

  public function addSubscription(Request $req)
  {
    $z = new Zvonok();
    $z->tema = 'Подписка на нашу рассылку';
    $z->info = '<br>e-mail:' . $req->input('email');
    if (!$z->isDublicate()) {
      $z->save();

      $subscr = new Subscription();
      $subscr->setEmail($req->input('email'));
      $subscr->save();

      Base::addClientMessage($req->input('Ваша заявка на подписку принята.'));
    }

    return Redirect::to($req->header('referer'));
  }

  public function bron(Request $req)
  {
    $rez = new \stdClass();
    $mysqli = Db::getInstance()->getConnection();

    if ($action = $mysqli->real_escape_string($req->input('action'))) {
      $rez->action = $action;
      switch ($action) {
        case 'get-offices-for-model':

          $rez->offices = [];

          $modelId = intval($req->input('model_id'));

          $offsHasItemArray = tovar::getFreeItemsOfficeArrayForModelId($modelId);
          if (!$offsHasItemArray)
            $offsHasItemArray = [];

          //dd($offsHasItemArray);

          $offsAllArray = [];

          foreach (Office::getAllActiveOffices() as $of) {
            $offsAllArray[] = $of->getNumber();
          }

          if (is_array($offsHasItemArray) && count($offsHasItemArray) > 0) {
            $offsWithNoItemsArray = array_diff($offsAllArray, $offsHasItemArray);
          } else {
            $offsWithNoItemsArray = $offsAllArray;
          }

          if (count($offsWithNoItemsArray) > 0) {
            $offsArray = array_merge($offsHasItemArray, $offsWithNoItemsArray);
          } else {
            $offsArray = $offsHasItemArray;
          }


          $rez->tmp = $offsArray;



          if ($offsHasItemArray && count($offsHasItemArray) > 0) {
            $rez->hasFree = true;
            foreach ($offsArray as $offId) {
              $of = Office::getOfficeByNumber($offId);
              if (!$of)
                continue;
              $office = new \stdClass();
              $office->offNum = $of->getNumber();

              if (in_array($offId, $offsHasItemArray)) {
                $office->hasItem = true;
              } else {
                $office->hasItem = false;
              }

              $office->address = $of->getAddressShort();

              $today = new \DateTime();
              $tomorrow = new \DateTime();
              $tomorrow->modify("+1 day");
              $todayOpenArray = $of->getOpenHoursMinutesArrayForDate($today);
              $todayCloseArray = $of->getCloseHoursMinutesArrayForDate($today);
              $tomorrowOpenArray = $of->getOpenHoursMinutesArrayForDate($tomorrow);
              $tomorrowCloseArray = $of->getCloseHoursMinutesArrayForDate($tomorrow);

              $office->todayFrom = $todayOpenArray[0] . '.' . $todayOpenArray[1];
              $office->todayTo = $todayCloseArray[0] . '.' . $todayCloseArray[1];
              $office->tomorrowFrom = $tomorrowOpenArray[0] . '.' . $tomorrowOpenArray[1];
              $office->tomorrowTo = $tomorrowCloseArray[0] . '.' . $tomorrowCloseArray[1];

              $rez->offices[] = $office;
            }

            $rez->result = 'ok';

          } else {
            $rez->hasFree = false;
          }



          $rez->data = 'some data';
          $rez->modelId = $req->input('model_id') * 1;
          break;
      }
    }



    return json_encode($rez);

  }

  public function KBronActions(Request $req)
  {
    $mysqli = Db::getInstance()->getConnection();

    $rez = new \stdClass();

    $modelId = $mysqli->real_escape_string($req->input('model_id'));
    $invNGot = $mysqli->real_escape_string($req->input('invn'));
    $date = $mysqli->real_escape_string($req->input('date'));
    $eventDate = new \DateTime($date);
    $rostFrom = $mysqli->real_escape_string($req->input('rost_from'));
    $rostTo = $mysqli->real_escape_string($req->input('rost_to'));
    $rostSize = $mysqli->real_escape_string($req->input('size'));
    $phoneNum = $mysqli->real_escape_string($req->input('phone'));
    $fio = $mysqli->real_escape_string($req->input('fio'));
    $info = $mysqli->real_escape_string($req->input('info'));

    $fromCustomer = new \DateTime($mysqli->real_escape_string($req->input('from')));
    $toCustomer = new \DateTime($mysqli->real_escape_string($req->input('to')));


    if ($action = $mysqli->real_escape_string($req->input('action'))) {
      switch ($action) {

        case 'new_bron'://first step (no brons saved, just get info)

          $from = clone $eventDate;
          $from->modify('-1 day');
          $from->setTime(0, 0, 1);
          $to = clone $eventDate;
          $to->modify('+1 day');
          $to->setTime(23, 0, 0);

          $freePeriods = KBron::getFreePeriodsForModelIdAndRostsCrossingDate($modelId, $from, $to, $rostFrom, $rostTo, $eventDate);
          //return json_encode($freePeriods);

          //remove dublicates
          $freePeriodsToShow = [];
          foreach ($freePeriods as $fp) {
            $exists = false;
            foreach ($freePeriodsToShow as $fptsh) {
              if (($fp->from_kb->format('Y-m-d H') == $fptsh->from_kb->format('Y-m-d H')) && ($fp->to_kb->format('Y-m-d H') == $fptsh->to_kb->format('Y-m-d H'))) {
                $exists = true;
              }
            }
            if (!$exists)
              $freePeriodsToShow[($fp->to_kb->getTimestamp() - $fp->from_kb->getTimestamp())] = $fp;
          }

          krsort($freePeriodsToShow); //sort by key which is duration in seconds. longer -> first

          $freePeriods = $freePeriodsToShow;



          if (is_array($freePeriods) && count($freePeriods) > 0) {
            $hasFrees = true;
          } else {
            $hasFrees = false;
          }

          if (!$hasFrees) {//if no free preiods
            $rez->status = 'no_free';
            $f = new KBForm();
            $f->setEventDate($eventDate);

            $m = Model::getById($modelId);
            $mw = ModelWeb::getByModelId($modelId);
            if ($m) {
              $f->setTovarName($mw->getL2Name());
              $f->setRostFrom($rostFrom);
              $f->setRostTo($rostTo);
              $f->setSize($rostSize);
              $f->setEventDate($eventDate);
            }
            $rez->rez = view('kb_reject', ['f' => $f])->render();

          } else {//has free periods
            $rez->status = 'free';
            $f = new KBForm();
            $f->setFreePeriods($freePeriods);
            $f->setEventDate($eventDate);
            $prevDay = clone $eventDate;
            $prevDay->modify('-1 day');
            $nextDay = clone $eventDate;
            $nextDay->modify('+1 day');
            $today = new \DateTime();

            if ($eventDate->format('d.m.Y') != $today->format('d.m.Y'))
              $f->addFromDay($prevDay);
            $f->addFromDay($eventDate);
            $f->addToDay($eventDate);
            $f->addToDay($nextDay);

            $m = Model::getById($modelId);
            $mw = ModelWeb::getByModelId($modelId);
            if ($m) {
              $f->setTovarName($mw->getL2Name());
              $f->setRostFrom($rostFrom);
              $f->setRostTo($rostTo);
              $f->setSize($rostSize);
            }

            $rez->rez = view('kb_free', ['f' => $f])->render();
          }

          //!!!
//          $message ='123';
//            $kb = KBron::getById(24456);
//            $rez->rez = view('kb_br_ok_final_message_new', ['kb' => $kb, 'message' => $message])->render();
//            ///!!!

          break;

        case 'kb_save':

          $newKB = KBron::saveNewBronSafeAtOneStepConciderOtherInvs($invNGot, $modelId, $rostFrom, $rostTo, $fromCustomer, $toCustomer, $phoneNum, $fio, $info);

          if (!$newKB) {
            $rez->status = 'error';
            $rez->message = 'Произошла ошибка. Возможно, этот костюм, кто-то успел забронировать. Попробуйте забронировать еще раз, или свяжитесь с консультантом по телефону. ';
            break;
          }

          $rez->status = 'kb_ok';

          $today = new \DateTime();
          $diff = $today->diff($newKB->from_kb);
          if ($diff->days > 7) {
            $message = 'Напоминаем о необходмости внести предоплату в течение 3-х календарных дней, в противном случае бронь будет автоматически аннулирована';
          } else {
            $message = 'Если вы передумаете, пожалуйста, отмените бронь - возможно кому-то очень нужен этот костюм!<br><br>
              <span style="font-style: italic;">Отменить бронь можно любым способом:
              сообщением на viber, почту, звонком, через форму обратной связи на сайте.</span>
          ';
          }

          $rez->message = view('kb_br_ok_final_message_new', ['kb' => $newKB, 'message' => $message])->render();

          break;

        case 'zayavka':
          $query = "INSERT INTO kb_zayavki SET model_id='$modelId', event_date='" . $eventDate->format('Y-m-d') . "', rost_from='$rostFrom', rost_to='$rostTo', phone='$phoneNum', info='', cr_when='" . time() . "'";
          $result_item_def = $mysqli->query($query);
          if (!$result_item_def) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
          }
          $rez->status = 'zayavka_ok';
          $message = 'Мы свяжемся с вами как только появится возможность удовлетворить вашу заявку';
          $rez->rez = view('kb_br_ok_message', ['message' => $message])->render();
          break;

      }
    }



    return json_encode($rez);
  }

}
