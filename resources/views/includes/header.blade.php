@php
    {{
    /** @var \bb\classes\TopMenu */
      $topMenu = \bb\classes\TopMenu::getTopMenu(request()->lang);
    /** @var \App\MyClasses\Header */
      $header = new \App\MyClasses\Header(request()->lang);
    }}
@endphp


<header>
    <div class="container-app">
        <!-- mobile 1-t header line -->
        <div class="row mobile-header-row d-md-none">
            <div class="col mobile-header">
                <button class="menu1-open"><span>{{$header->translate('Еще')}}</span><div class="more-sign"></div></button>
                <span class="top-header-mobile">{{$header->translate('Сервис проката')}}</span>
                <button class="lang-open">{{$topMenu->getLang()}} <img src="/public/svg/arrow_top_menu1.svg"></button>
                <div class="lang-choice-container">
                    @foreach($header->getLangHrefArrayForCurrentPage(request()->path()) as $arr)
                        <a href="/{{$arr[1]}}/">{{strtoupper($arr[0])}}</a>
                    @endforeach
                </div>
            </div>
        </div>
        <!-- mobile & desktop header content-->
        <div class="header-desktop">
            <div class="header-logo-col">
                <a class="header__logo-a" href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/">
                    <span class="prokat-text">прокат</span>
                    <img class="header-logo-img" src="/public/svg/logo_main.svg" alt="Tiktak.lt logo">
                </a>

            </div>
            <div class="header-col2">
                <div class="header-line1">
                    <form method="post" action="/zvonok" class="back-coll">
                        @csrf
                        <input type="hidden" name="action" value="back-coll">
                        <img class="close-cross" src="/public/png/close_cross.png">
                        <p>{{$header->translate('Для заказа обратного звонка заполните, пожалуйста, форму')}}</p>
                        <div class="input-wrapper"><input data-targetinput="" class="call-input1" type="text" name="fio" placeholder="{{$header->translate('Ваше имя')}}"></div>
                        <div class="input-wrapper"><input data-targetinput="" class="call-input1" type="text" name="phone" placeholder="{{$header->translate('Телефон')}}"></div>
                        <div class="input-wrapper">
                            <textarea data-targetinput="" class="call-textarea1" name="info" placeholder="{{$header->translate('Дополнительная информация')}}"></textarea>
                        </div>
                        <button>{{$header->translate('Отправить')}}</button>
                    </form>
                    <ul>
                        <li><a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/about"><span>{{$header->translate('О компании')}}</span><img class="d-md-none" src="/public/svg/arrow_v_r.svg"></a></li>
                        <li><a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/conditions"><span>{{$header->translate('Условия проката')}}</span><img class="d-md-none" src="/public/svg/arrow_v_r.svg"></a></li>
                        <li><a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/delivery"><span>{{$header->translate('Доставка и оплата')}}</span><img class="d-md-none" src="/public/svg/arrow_v_r.svg"></a></li>
                        <li><a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/contacts"><span>{{$header->translate('Контакты')}}</span><img class="d-md-none" src="/public/svg/arrow_v_r.svg"></a></li>
                        <li class=""><a data-callback href="#">{{$header->translate('Обратный звонок')}}</a></li>
                    </ul>

                </div>
                <div class="header-line2 d-none d-md-flex">
                    <form method="get" action="/{{(request()->lang ? request()->lang : 'ru')}}/search" class="head-srch">
                        <input name="search" type="text" placeholder="{{$header->translate('Поиск')}}">
                        <button class="top-srch-btn"><img src="/public/svg/lupa.svg"></button>
                    </form>
                    <div class="messangers">
                      <a href="https://t.me/TIKTAK_PROKAT" aria-label="Telegram" target="_blank">
                        <img src="/public/svg/telagram-icon.svg" alt="Telegram">
                      </a>
                      <a href="viber://chat?number=+375297454040" aria-label="Viber">
                        <img src="/public/svg/viber-icon.svg" alt="Viber">
                      </a>
                      <a href="https://www.instagram.com/prokat_tiktak.by?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" aria-label="Instagram" target="_blank">
                        <img src="/public/svg/instagram-icon.svg" alt="Instagram">
                      </a>

                    </div>
                    <div class="phone-number-div">
                        <a class="header-phone" href="tel:+375447454040">+375 44 745 40 40</a>
{{--                        <span class="messangers-span visually-hidden"><span>A1</span><span>MTC</span><span>Life</span><span>Viber</span></span>--}}
                    </div>

                </div>
            </div>
            <div class="header-col3 d-none d-md-flex">
                <a class="office_geo-a" href="#" data-bs-toggle="modal" data-bs-target="#officesModal"><img class="geo" src="/public/svg/geo_logo.svg" alt="geo-logo"><span>Минск, наши салоны:</span></a>
                <a class="office_geo-a" href="#" data-bs-toggle="modal" data-bs-target="#officesModal"><span>ул. Ложинская, 5</span><img class="arrow" src="/public/svg/arrow_down.svg" alt="Arrow down"></a>
                <a class="office_geo-a" href="#" data-bs-toggle="modal" data-bs-target="#officesModal"><span>ул. Литературная, 22</span><img class="arrow" src="/public/svg/arrow_down.svg" alt="Arrow down"></a>
            </div>
            <div class="header-col4">
                <a class="google-map-pointer d-md-none" href="#" data-bs-toggle="modal" data-bs-target="#officesModal"><img src="/public/svg/map_pointer.svg?v=1"></a>
                <a class="call-back-pointer d-md-none" data-callback="" href="#"><img src="/public/svg/mail_icon.svg?v=1"></a>
                <a class="mobile-phone d-md-none" href="tel:+375447454040" data-mob_phone="1"><img src="/public/svg/phone_top.svg?v=1"></a>
{{--              <div class="mob_phones_div hide">--}}
{{--                <a class="header-phone" href="tel:+375297454040">+375 (29) 745 40 40</a>--}}
{{--                <a class="header-phone" href="tel:+375297454040">+375 (29) 745 40 40</a>--}}
{{--                <a class="header-phone" href="tel:+375297454040">+375 (29) 745 40 40</a>--}}
{{--                <span class="btn btn-close"></span>--}}
{{--              </div>--}}
            </div>
        </div>

        <!-- mobile navigation -->
        <nav class="row d-flex d-md-none mobile-topmenu-container">
            <div class="col mobile-menu-line1">
                <div class="col1">
                    <div class="hamburger-lines2">
                        <div class="line line1"></div>
                        <div class="line line2"></div>
                        <div class="line line3"></div>
                    </div>
                    <div class="cat-text">Каталог</div>
                </div>
                <form method="get" action="/{{request()->lang}}/search" class="col2">
                    <input name="search" type="text" placeholder="{{$header->translate('Поиск')}}">
                    <button type="submit" class="srch-btn"><img class="srch-icon" src="/public/svg/lupa2.svg" alt="search icon"></button>
                </form>
            </div>
            <ul class="mobile-menu-list left" data-navlevel="razdel">
                @foreach($topMenu->getRazdels() as $r)
                    <li class="nav-item-mobile expand" data-razdelid="{{$r->getIdRazdel()}}"><img class="icon" src="{{$r->getUrlIcon2Razdel()}}"><span class="razdel-text">{{$r->getNameRazdelText()}}</span><img class="arrow" src="/public/svg/v_arrow_left.svg"></li>
                @endforeach
            </ul>
            @foreach($topMenu->getRazdels() as $r)
                @if(is_array($topMenu->getRazdels()) && count($topMenu->getRazdels())>0)
                    <ul class="mobile-menu-list right" data-navlevel="subrazdel" data-razdelid="{{$r->getIdRazdel()}}">
                        <li class="nav-item-mobile expand back" data-backto="razdel"><img class="arrow-back" src="/public/svg/v_arrow_left.svg"><span class="razdel-text">{{$r->getNameRazdelText()}}</span></li>
                        @if(is_array($r->getSubRazdels()) && count($r->getSubRazdels())>0)
                            @foreach($r->getSubRazdels() as $sr)
                                <li class="nav-item-mobile link" data-subrazdelid="{{$sr->getIdSubRazdel()}}"><a href="{{$sr->getUrlForPage(request()->lang)}}"><img class="icon" src="{{$sr->getUrlSubRazdelIcon()}}"><span class="razdel-text">{{$sr->getNameSubRazdelText()}}</span><img class="arrow" src="/public/svg/v_arrow_left.svg"></a></li>
                                {{--                        <li class="nav-item-mobile expand" data-subrazdelid="{{$sr->getIdSubRazdel()}}"><img class="icon" src="{{$sr->getUrlSubRazdelIcon()}}"><span class="razdel-text">{{$sr->getNameSubRazdelText()}}</span><img class="arrow" src="/public/svg/v_arrow_left.svg"></li>--}}
                            @endforeach
                        @endif
                    </ul>
                @endif
            @endforeach



        </nav>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="officesModal" tabindex="-1" aria-labelledby="officesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="officesModalLabel">Наши офисы</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="office-top-container off-1">
                        <div class="ofcol1">
                            <a class="office-address" href="#">
                                <img src="/assets/pics/png/zamok.png" alt="Офис">
                                <span>ул. Литературная 22</span>
                            </a>
                            <span class="sub-header">Телефоны:</span>
                            <a href="tel:+375296303532" class="textline phone">+375 (29) 630-35-32</a>
                            <a href="tel:+375447454040" class="textline phone">+375 (44) 745-40-40</a>
                            <span class="sub-header">Часы работы:</span>
                            <span class="textline">пн-пт: 10.00-19.00</span>
                            <span class="textline">сб, вс: 10.00-15.00</span>
                        </div>
                        <div class="ofcol2">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d37576.53071565148!2d27.49688255176978!3d53.940037097398736!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbcf987119c2ed%3A0x38a520fb62e6031d!2sTIKTAK%20SALON%20PROKATA%20DETSKIH%20TOVAROV%20UP%20TODDLER%20FAN!5e0!3m2!1sen!2spl!4v1648465519790!5m2!1sen!2spl" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                    <div class="office-top-container off-2">
                        <div class="ofcol1">
                            <a class="office-address" href="#">
                                <img src="/assets/pics/png/zamok.png" alt="Офис">
                                <span>ул. Ложинская, 5</span>
                            </a>
                            <span class="sub-header">Телефоны:</span>
                            <a href="tel:+375296303558" class="textline phone">+375 (29) 630-35-58</a>
                            <a href="tel:+375297454040" class="textline phone">+375 (29) 745-40-40</a>
                            <span class="sub-header">Часы работы:</span>
                            <span class="textline">пн-пт: 10.00-19.00</span>
                            
                            <span class="textline">сб, вс: выходной</span>
                        </div>
                        <div class="ofcol2">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2348.1549247760463!2d27.685609051599968!3d53.94675598001161!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbc9357a6cbe07%3A0xc6249534647e615d!2z0J_RgNC-0LrQsNGCINC00LXRgtGB0LrQuNGFINGC0L7QstCw0YDQvtCyIFRpa1Rhay4g0KHQsNC70L7QvSDihJYyLg!5e0!3m2!1sen!2spl!4v1648465657389!5m2!1sen!2spl" height="450" style="width: 100% border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- desktop navigation -->
    <nav class="container-fluid top-nav-container d-none d-md-block">
            <div class="backgound"></div>
            <ul class="top-nav-row">
                @foreach($topMenu->getRazdels() as $r)
                    <li data-id="{{$r->getIdRazdel()}}" class="top-nav-item">
                        <a class="item-a" href="{{$r->getUrlForPage(request()->lang)}}">
                            <img class="item-img" src="{{$r->getUrlIconRazdel()}}">
                            <span>{{$r->getNameRazdelText()}}</span>
                        </a>
                        <!-- SubRazdels-->
                        @if($r->getSubRazdels())
                            <div class="container-fluid top-cat-menu-container">
                                <div class="top-cat-sub-container">
                                    @foreach($r->getSubRazdels() as $sr)
                                        <ul class="top-cat-list">
                                            <li><a class="list-header-img-li" href="{{$sr->getUrlForPage(request()->lang, $r->getUrlRazdelName())}}"><img src="{{$sr->getUrlSubRazdelIcon()}}"></a></li>
                                            <li class="list-header"><a href="{{$sr->getUrlForPage(request()->lang, $r->getUrlRazdelName())}}">{{$sr->getNameSubRazdelText()}}</a></li>
                                            @foreach($sr->getCategories() as $cat)
                                                <li><a href="{{$cat->getUrlForPage(request()->lang, $r->getUrlRazdelName(), $sr->getUrlSubRazdelName())}}">{{$cat->getName()}}</a></li>
                                            @endforeach
                                        </ul>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
</header>
@php
  $an=\bb\classes\Announcement::getMessageByType('main');
@endphp
@if($an && $an->toShow())
<message>
  <div class="container-app">
    <div class="alert-danger text-center pt-2 pb-2" style="font-weight: bold; font-size: 22px">
      {!! $an->getMessage() !!}
    </div>
  </div>
</message>
@endif


