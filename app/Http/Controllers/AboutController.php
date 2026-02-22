<?php

namespace App\Http\Controllers;

use App\MyClasses\MainPage;
use Illuminate\Http\Request;

class AboutController extends Controller
{
  public function showAboutPage($lang, Request $req)
  {
    $lang = htmlspecialchars($lang);

    $p = MainPage::getPage($lang, 'main', 'about');
    if (!$p)
      $p = MainPage::getPage('ru', 'main', 'about');
    if (!$p)
      $p = new MainPage('ru', 'main', 'unknown');
    $p->addBreadCrumbItem('О нас', '');
    return view('about', ['p' => $p]);

  }

  public function showConditionsPage($lang, Request $req)
  {
    $lang = htmlspecialchars($lang);

    $p = MainPage::getPage($lang, 'main', 'conditions');
    if (!$p)
      $p = MainPage::getPage('ru', 'main', 'about');
    if (!$p)
      $p = new MainPage('ru', 'main', 'unknown');
    $p->addBreadCrumbItem('Условия проката', '');
    return view('conditions', ['p' => $p]);
  }

  public function showDeliveryPage($lang, Request $req)
  {
    $lang = htmlspecialchars($lang);

    $p = MainPage::getPage($lang, 'main', 'delivery');
    if (!$p)
      $p = MainPage::getPage('ru', 'main', 'about');
    if (!$p)
      $p = new MainPage('ru', 'main', 'unknown');
    $p->addBreadCrumbItem('Доставка', '');
    return view('delivery', ['p' => $p]);
  }

  public function showPaymentPage($lang, Request $req)
  {
    $lang = htmlspecialchars($lang);

    $p = MainPage::getPage($lang, 'main', 'payment');
    if (!$p)
      $p = MainPage::getPage('ru', 'main', 'about');
    if (!$p)
      $p = new MainPage('ru', 'main', 'unknown');
    $p->addBreadCrumbItem('Оплата', '');
    return view('payment', ['p' => $p]);
  }

  public function showContactsPage($lang, Request $req)
  {
    $lang = htmlspecialchars($lang);

    $p = MainPage::getPage($lang, 'main', 'contacts');
    if (!$p)
      $p = MainPage::getPage('ru', 'main', 'about');
    if (!$p)
      $p = new MainPage('ru', 'main', 'unknown');
    $p->addBreadCrumbItem('Контакты', '');
    return view('about_tmpl', ['p' => $p]);
  }

  public function showPolicyPage($lang, Request $req)
  {
    $lang = htmlspecialchars($lang);

    $p = MainPage::getPage($lang, 'main', 'policy');
    if (!$p)
      $p = MainPage::getPage('ru', 'main', 'about');
    if (!$p)
      $p = new MainPage('ru', 'main', 'unknown');
    $p->addBreadCrumbItem('Контакты', '');
    return view('about_tmpl', ['p' => $p]);
  }
}
