<?php

namespace App\MyClasses;

class Header
{
    private $lang;

    //ru key, then lt, en
    private static $_translations = [
        "Сервис проката" => [
            "Nuomos paslauga",
            "Rental service"
        ],
        "Еще" => [
            "Daugiau",
            "More"
        ],
        "сервис проката" => [
          "nuomos paslauga",
          "rental service"
        ],
        "Для заказа обратного звонка заполните, пожалуйста, форму" => [
            "Norėdami užsisakyti atgalinį skambutį, užpildykite formą",
            "To order a callback, please fill out the form"
        ],
        "Ваше имя" => [
            "Jūsų vardas",
            "Your name"
        ],
        "Телефон" => [
            "Telefonas",
            "Phone number"
        ],
        "Дополнительная информация" => [
            "Daugiau informacijos",
            "Additional information"
        ],
        "Отправить" => [
            "Siųsti užklausą",
            "Send request"
        ],
        "О компании" => [
            "Apie įmonę",
            "About us"
        ],
        "Условия проката" => [
            "Nuomos sąlygos",
            "Terms and Conditions"
        ],
        "Доставка и оплата" => [
            "Pristatymas ir mokėjimas",
            "Shipping & Payment"
        ],
        "Контакты" => [
            "Kontaktai",
            "Contacts"
        ],
        "Обратный звонок" => [
            "Perskambinkite man",
            "Call me back"
        ],
        "Поиск" => [
            "Paieška",
            "Search"
        ],
        "пн-вс" => [
            "pr-sk",
            "mon-sun"
        ],
        "без выходных" => [
            "nėra laisvų dienų",
            "no days off"
        ],
      "Вильнюс" => [
            "Vilnius",
            "Vilnius"
        ],
      "Подпишись на наши новости" => [
            "Prenumeruokite mūsų naujienlaiškį",
            "Subscribe to our news"
        ],
      "бронирование" => [
            "užsakymas",
            "booking"
        ],
      "Часы работы" => [
            "Darbo laikas",
            "Office hours"
        ],
      "Публичная оферта" => [
            "Viešasis pasiūlymas",
            "Public offer"
        ],
      "Политика обработки персональных данных" => [
            "Asmens duomenų tvarkymo politika",
            "Personal data processing policy"
        ],
      "Cоглашение на получение рассылки" => [
            "Sutikimas gauti naujienlaiškį",
            "Agreement to receive the newsletter"
        ],
      "Все права защищены" => [
            "Visos teisės ginamos",
            "All rights reserved"
        ],
      "Разработка сайта" => [
            "Interneto svetainių kūrimas",
            "WEB-Site development"
        ],
        "Укажите свой" => [
            "Įveskite savo",
            "Enter your"
        ],
    ];

  /**
   * @return mixed|string
   */
  public function getLang()
  {
    return $this->lang;
  }

  /**
   * @param mixed|string $lang
   */
  public function setLang($lang): void
  {
    $this->lang = $lang;
  }

    /**
     * @param $textRU
     * @param $targetLang
     * @return mixed|string
     */
    public function translate($textRU){
        if ($this->lang=='ru' || $this->lang=='') return $textRU;

        $this->lang == 'lt' ? $langIndex = 0 : $langIndex = 1; //lt = 0, en = 1
        $translatedText='';

        if (isset(self::$_translations[$textRU])){
             $translatedText=self::$_translations[$textRU][$langIndex];
        }

        return $translatedText;
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


    public function __construct($lang='ru')
    {
        $this->lang = $lang;
    }
}
