<?php

namespace App\Http\Controllers;

use App\MyClasses\L3Page;
use App\MyClasses\MainPage;
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
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\AssignOp\Mod;
use DateTime;

class L3Controller extends Controller
{
  public function l3ShowPage2($lang, $razdel, $subrazdel, $category, $model, Request $req)
  {
    //Base::addErrorMessage('model: '.$model);

    $p = L3Page::getPageByUrlName($model, $lang, \request()->razdel, \request()->subrazdel);

    if ($p === null) {
      $this->logNotFoundUrl();
      return $this->showCategoryWithNotice($lang, $razdel, $subrazdel, $category);
    }

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
      return response()->view('l3_not_found', ['p' => $p], 404);
    }

    return view('l3', ['p' => $p]);
  }

  public function l3ShowPageLegacy($lang, $r1, $r2, $r3, $r4, $model, Request $req)
  {
    $p = L3Page::getPageByUrlName($model, $lang, $r1, $r2);

    if ($p !== null) {
      if (!tovar::getByModelId($p->getModelId())) {
        $this->logNotFoundUrl();
        return response()->view('l3_not_found', ['p' => $p], 404);
      }
      return view('l3', ['p' => $p]);
    }

    $this->logNotFoundUrl();
    return $this->showCategoryWithNotice($lang, $r1, $r2, $r3);
  }

  /**
   * Договор и выезд курьера по прямому заказу с карточки товара.
   *
   * Повторяет то, что делает корзина (CartController), с двумя отличиями:
   * товар всегда один, а возврата курьером на этой форме нет — её чекбокса
   * не существует, поэтому второй выезд не планируется.
   *
   * Сбой автоматики не должен рушить заказ: бронь уже создана до этого места.
   */
  private function createDeliveryDeal(tovar $tovar, $modelId, DateTime $dateFrom, DateTime $dateTo, Request $req): void
  {
    if (!config('app.cart_auto_deal', true)) {
      return;
    }

    try {
      $days = (int) $dateFrom->diff($dateTo)->days;
      if ($days < 1) {
        $days = max(1, (int) $req->input('days_num'));
      }

      $tarifModel = \bb\classes\TariffModel::getTarifModelForModelId($modelId);
      $amount = $tarifModel ? (float) $tarifModel->getAmmountForDaysPeriod($days) : 0.0;

      $clientId = \bb\classes\WebOrderDeal::findOrCreateClient(
        (string) $req->input('fio'),
        (string) $req->input('phone'),
        (string) $req->input('address')
      );

      if ($clientId <= 0) {
        return;
      }

      \bb\classes\WebOrderDeal::createDealWithTrips(
        $clientId,
        [
          'inv_n' => $tovar->getInvN(),
          'start_ts' => $dateFrom->getTimestamp(),
          'return_ts' => $dateTo->getTimestamp(),
          'days' => $days,
          'r_to_pay' => $amount,
          'tarif' => \bb\classes\WebOrderDeal::resolveTariff($tarifModel, $days),
        ],
        CartController::calcDeliveryCost($amount, false),
        0.0,
        \bb\classes\WebOrderDeal::courierInfo(
          (string) $req->input('address'),
          (string) $req->input('phone'),
          '',
          (string) $req->input('info'),
          false
        )
      );
    } catch (\Throwable $e) {
      \Illuminate\Support\Facades\Log::error('Автодоговор по заказу с карточки товара не создан: ' . $e->getMessage());
    }
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

        $br = bron::createBronStrong($tovar->getInvN(), $req->input('fio'), $req->input('phone'), $deliveryYN, $req->input('address'), 1, $info);
        if ($br) {
            \App\Helpers\UtmTracker::track('rent_orders', $br->insert_id);
        }

        // Заказ с доставкой сразу становится договором и выездом курьера — так же,
        // как заказ через корзину (CartController). Иначе часть заказов с сайта
        // попадала бы к курьеру, а часть терялась бы на этапе брони.
        if ($deliveryYN == 1) {
          $this->createDeliveryDeal($tovar, $model_id, $dateFrom, $dateTo, $req);
        }

        $message = 'Бронь на товар принята. Оператор свяжется с Вами в ближайшее время.';
      } catch (\Exception $e) {
        $z = Zvonok::addLitZvonok($req->input('fio'), $req->input('phone'), $req->input('info') . '---' . $e->getMessage(), $req->input('model_id'));
        if ($z && $z->id) {
            \App\Helpers\UtmTracker::track('zvonki', $z->id);
        }
        $message = 'Что-то пошло не так :( <br> Бронь не принята. Свяжитесь, пожалуйста с оператором по телефону.';
      }
    } else {//hav no items = create zayavka

      $validityDaysNum = $req->input('days_num');

      //create zvonok
      $z = Zvonok::addLitZvonok($req->input('fio'), $req->input('phone'), $req->input('info'), $req->input('model_id'), 'zayavka', $validityDaysNum);
      if ($z && $z->id) {
          \App\Helpers\UtmTracker::track('zvonki', $z->id);
      }

      //create zayavka
      $validityDateObj = new \DateTime();
      if ($validityDaysNum) {
        $validityDateObj->modify('+' . intval($validityDaysNum) . ' days');
      }
      $zayavka = bron::createZayavka($req->input('model_id'), $req->input('phone'), $req->input('fio'), '', '', $validityDateObj, $req->input('info'), 1);
      if ($zayavka && $zayavka->insert_id && !$zayavka->is_duplicate) {
          \App\Helpers\UtmTracker::track('rent_orders', $zayavka->insert_id);
      }
      if (isset($z) && $z->id && $zayavka && $zayavka->insert_id) {
          (new \bb\classes\Zayavka())->linkAfterCreate((int)$zayavka->insert_id, (int)$z->id);
      }

      $message = 'Заявка на товар принята. При поступлении товара в указанный срок ожидания, оператор свяжется с вами по телефону.';
    }


    $p = L3Page::getPageByUrlName($model, $lang, \request()->razdel, \request()->subrazdel);
    if ($p === null) {
      return redirect("/{$lang}/{$razdel}/{$subrazdel}/{$category}", 301);
    }
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

  private function logNotFoundUrl(): void
  {
    try {
      DB::table('not_found_log')->insert([
        'url'        => \Illuminate\Support\Facades\Request::url(),
        'referrer'   => \Illuminate\Support\Facades\Request::header('referer'),
        'ip'         => \Illuminate\Support\Facades\Request::ip(),
        'created_at' => now(),
      ]);
    } catch (\Exception $e) {
      // не прерываем рендеринг страницы если логирование упало
    }
  }

  private function showCategoryWithNotice(string $lang, string $razdel, string $subrazdel, string $category)
  {
    $p = MainPage::getWebPageByCategoryAndSubRazdelAndRazdel($lang, $razdel, $subrazdel, $category, 1, []);
    if (!$p || !$p->isRealPage()) {
      $p = MainPage::getWebPageBySubRazdelAndRazdel($lang, $razdel, $subrazdel, 1, []);
    }
    if (!$p || !$p->isRealPage()) {
      $p = MainPage::getRazdelPageForWeb($lang, $razdel, 1, []);
    }
    if (!$p || !$p->isRealPage()) {
      return response()->view('errors.404', [], 404);
    }

    $notice = 'Этот товар снят с проката. Посмотрите другие товары в этой категории.';
    return response()->view('catpage', ['p' => $p, 'notice' => $notice], 404);
  }
}
