<?php

namespace App\Http\Controllers;

use App\MyClasses\L3Page;
use bb\Base;
use bb\classes\bron;
use bb\classes\Category;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Razdel;
use bb\classes\SubRazdel;
use bb\classes\tovar;
use bb\classes\Zvonok;
use bb\models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use PhpParser\Node\Expr\AssignOp\Mod;
use DateTime;

class L3Controller extends Controller
{
  public function l3ShowPage2($lang, $razdel, $subrazdel, $category, $model, Request $req)
  {
    //Base::addErrorMessage('model: '.$model);

    $p = L3Page::getPageByUrlName($model, $lang, \request()->razdel, \request()->subrazdel);


    if ($razd = Razdel::getByUrlName($razdel, $lang)) {
      $p->addBreadCrumbs($razd->getNameRazdelText(), $razd->getUrlForPage($lang));
      if ($subRazd = SubRazdel::getByUrlName($subrazdel, $lang)) {
        $p->addBreadCrumbs($subRazd->getNameSubRazdelText(), $subRazd->getUrlForPage($lang, $razd->getUrlRazdelName()));
        if ($cat = Category::getByUrlName($category, $lang)) {
          $p->addBreadCrumbs($cat->getName(), $cat->getUrlForPage($lang, $razd->getUrlRazdelName(), $subRazd->getUrlSubRazdelName()));
        }
      }
    }

    if (!tovar::getByModelId($p->getModelId())) {
      return view('l3_not_found', ['p' => $p]);
    }

    return view('l3', ['p' => $p]);
  }

  public function l3Order2($lang, $razdel, $subrazdel, $category, $model, Request $req)
  {

    $m = ModelWeb::getByUrlName($model);

    if ($m) {
      $model_id = $m->getModelId();
    } else {
      $model_id = false;
    }

    $karnaval = $req->input('karnaval');


    //have free items
    $officesIdAvailableArray = tovar::getFreeItemsOfficeArrayForModelId($m->getModelId());

    if (is_array($officesIdAvailableArray) && count($officesIdAvailableArray) > 0 && $karnaval != '1') {//have free items
      try {
        $dateFrom = new \DateTime($req->input('date_from'));
        $dateTo = new \DateTime($req->input('date_to'));

        $techInfo = '';


        if ($req->input('delivery') == 1) {//delivery
          $deliveryYN = 1;

          $freeItems = tovar::getFreeTovarsForModelIdAndOffice($model_id, 'all');
        } else {//sam vivoz

          $deliveryYN = 0;

          $officeNum = $req->input('office');

          if (in_array($officeNum, $officesIdAvailableArray)) { //office choosen & tovar on the same office
            $freeItems = tovar::getFreeTovarsForModelIdAndOffice($m->getModelId(), $officeNum);
          } else {//office choosen & tovar on another office
            $freeItems = tovar::getFreeTovarsForModelIdAndOffice($model_id, 'all');
            $techInfo .= '<strong style="color:red;">Необходимо переместить товар на ' . Office::getOfficeByNumber($officeNum)->getAddressShort() . '</strong><br>';
          }
        }
        $tovar = $freeItems[0];

        $techInfo .= 'В брони клиент указал: с ' . $dateFrom->format("d.m.Y") . ' по ' . $dateTo->format("d.m.Y") . ' на ' . $req->input('days_num') . ' дня.';
        $info = $techInfo . '<br>' . $req->input('info');

        bron::createBronStrong($tovar->getInvN(), $req->input('fio'), $req->input('phone'), $deliveryYN, $req->input('address'), 1, $info);
        $message = 'Бронь на товар принята. Оператор свяжется с Вами в ближайшее время.';
      } catch (\Exception $e) {
        $z = Zvonok::addLitZvonok($req->input('fio'), $req->input('phone'), $req->input('info') . '---' . $e->getMessage(), $req->input('model_id'));
        $message = 'Что-то пошло не так :( <br> Бронь не принята. Свяжитесь, пожалуйста с оператором по телефону.';
      }
    } else {//hav no items = create zayavka

      $validityDaysNum = $req->input('days_num');

      //create zvonok
      $z = Zvonok::addLitZvonok($req->input('fio'), $req->input('phone'), $req->input('info'), $req->input('model_id'), 'zayavka', $validityDaysNum);

      //create zayavka
      $validityDateObj = new \DateTime();
      if ($validityDaysNum) {
        $validityDateObj->modify('+' . intval($validityDaysNum) . ' days');
      }
      $zayavka = bron::createZayavka($req->input('model_id'), $req->input('phone'), $req->input('fio'), '', '', $validityDateObj, $req->input('info'), 1);

      $message = 'Заявка на товар принята. При поступлении товара в указанный срок ожидания, оператор свяжется с вами по телефону.';
    }


    $p = L3Page::getPageByUrlName($model, $lang, \request()->razdel, \request()->subrazdel);
    $p->addMessage($message);

    if ($razd = Razdel::getByUrlName($razdel, $lang)) {
      $p->addBreadCrumbs($razd->getNameRazdelText(), $razd->getUrlForPage($lang));
      if ($subRazd = SubRazdel::getByUrlName($subrazdel, $lang)) {
        $p->addBreadCrumbs($subRazd->getNameSubRazdelText(), $subRazd->getUrlForPage($lang, $razd->getUrlRazdelName()));
        if ($cat = Category::getByUrlName($category, $lang)) {
          $p->addBreadCrumbs($cat->getName(), $cat->getUrlForPage($lang, $razd->getUrlRazdelName(), $subRazd->getUrlSubRazdelName()));
        }
      }
    }
    return view('l3', ['p' => $p]);
  }
}
