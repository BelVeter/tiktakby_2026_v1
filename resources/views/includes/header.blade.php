@php
    {{
    /** @var \bb\classes\TopMenu */
      $topMenu = \bb\classes\TopMenu::getTopMenu(request()->lang);
    /** @var \App\MyClasses\Header */
      $header = new \App\MyClasses\Header(request()->lang);
    }}
@endphp


<header>
    <div class="top-header-full-width d-none d-md-block">
        <div class="container-app top-header-bar">
            <div class="top-bar-left">
                <a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/about">{{$header->translate('О компании')}}</a>
                <a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/conditions">{{$header->translate('Условия проката')}}</a>
                <a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/delivery">{{$header->translate('Доставка и оплата')}}</a>
            </div>
            <div class="top-bar-center">
                <div class="top-phone">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 16.92V19.92C22.0011 20.1985 21.9441 20.4742 21.8325 20.7294C21.7209 20.9845 21.5573 21.2136 21.3521 21.4019C21.1468 21.5901 20.9046 21.7335 20.6407 21.8227C20.3769 21.9119 20.0974 21.9451 19.82 21.92C16.7428 21.5856 13.787 20.5342 11.19 18.85C8.77382 17.3147 6.72533 15.2662 5.18999 12.85C3.49997 10.2412 2.44824 7.271 2.11999 4.18001C2.095 3.90347 2.12787 3.62477 2.21649 3.36163C2.30512 3.09849 2.44756 2.85669 2.63476 2.65163C2.82196 2.44656 3.0498 2.28271 3.30379 2.17053C3.55777 2.05834 3.83233 2.00027 4.10999 2.00001H7.10999C7.5953 1.99523 8.06579 2.16708 8.43376 2.48354C8.80173 2.79999 9.04207 3.23945 9.10999 3.72001C9.22952 4.68141 9.46455 5.62488 9.80999 6.53001C9.94454 6.88793 9.97366 7.27692 9.8939 7.65089C9.81415 8.02485 9.62886 8.36812 9.35999 8.64001L8.08999 9.91001C9.51355 12.4136 11.5864 14.4865 14.09 15.91L15.36 14.64C15.6319 14.1859 16.3491 14.1061C16.7231 14.0263 17.1121 14.0555 17.47 14.19C18.3751 14.5355 19.3186 14.7705 20.28 14.89C20.7658 14.9585 21.2094 15.2032 21.5265 15.5775C21.8437 15.9518 22.0122 16.4296 22 16.92Z" stroke="#3180d1" style="stroke: #3180d1 !important;" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 9C16.7956 9 17.5587 9.31607 18.1213 9.87868C18.6839 10.4413 19 11.2044 19 12" stroke="#3180d1" style="stroke: #3180d1 !important;" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M13 6C14.5913 6 16.1174 6.63214 17.2426 7.75736C18.3679 8.88258 19 10.4087 19 12" stroke="#3180d1" style="stroke: #3180d1 !important;" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <a href="tel:+375447454040">+375 44 745 40 40</a>
                </div>
            </div>
            <div class="top-bar-right">
                <div class="top-address">
                   <a href="#" data-bs-toggle="modal" data-bs-target="#officesModal">
                    <img src="/public/svg/geo_logo.svg" alt="geo-logo">
                    <span>Минск, наши салоны</span>
                    <img src="/public/svg/arrow_down.svg" alt="arrow" class="arrow-down">
                   </a>
                </div>
                <a href="https://www.instagram.com/prokat_tiktak.by?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17 2H7C4.23858 2 2 4.23858 2 7V17C2 19.7614 4.23858 22 7 22H17C19.7614 22 22 19.7614 22 17V7C22 4.23858 19.7614 2 17 2Z" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 11.37C16.1234 12.2022 15.9813 13.0522 15.5938 13.799C15.2063 14.5458 14.5932 15.1514 13.8416 15.5297C13.0901 15.9079 12.2385 16.0396 11.4078 15.9059C10.5771 15.7723 9.80977 15.3801 9.21485 14.7852C8.61993 14.1902 8.22774 13.4229 8.09408 12.5922C7.96042 11.7615 8.09208 10.9099 8.47034 10.1584C8.8486 9.40685 9.4542 8.79374 10.201 8.40624C10.9478 8.01874 11.7978 7.87658 12.63 8C13.4789 8.12588 14.2649 8.52146 14.8717 9.1283C15.4785 9.73515 15.8741 10.5211 16 11.37Z" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M17.5 6.5H17.51" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="viber://chat?number=+375297454040" aria-label="Viber">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 16.92V19.92C22.0011 20.1985 21.9441 20.4742 21.8325 20.7294C21.7209 20.9845 21.5573 21.2136 21.3521 21.4019C21.1468 21.5901 20.9046 21.7335 20.6407 21.8227C20.3769 21.9119 20.0974 21.9451 19.82 21.92C16.7428 21.5856 13.787 20.5342 11.19 18.85C8.77382 17.3147 6.72533 15.2662 5.18999 12.85C3.49997 10.2412 2.44824 7.271 2.11999 4.18001C2.095 3.90347 2.12787 3.62477 2.21649 3.36163C2.30512 3.09849 2.44756 2.85669 2.63476 2.65163C2.82196 2.44656 3.0498 2.28271 3.30379 2.17053C3.55777 2.05834 3.83233 2.00027 4.10999 2.00001H7.10999C7.5953 1.99523 8.06579 2.16708 8.43376 2.48354C8.80173 2.79999 9.04207 3.23945 9.10999 3.72001C9.22952 4.68141 9.46455 5.62488 9.80999 6.53001C9.94454 6.88793 9.97366 7.27692 9.8939 7.65089C9.81415 8.02485 9.62886 8.36812 9.35999 8.64001L8.08999 9.91001C9.51355 12.4136 11.5864 14.4865 14.09 15.91L15.36 14.64C15.6319 14.3711 15.9751 14.1859 16.3491 14.1061C16.7231 14.0263 17.1121 14.0555 17.47 14.19C18.3751 14.5355 19.3186 14.7705 20.28 14.89C20.7658 14.9585 21.2094 15.2032 21.5265 15.5775C21.8437 15.9518 22.0122 16.4296 22 16.92Z" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 9C16.7956 9 17.5587 9.31607 18.1213 9.87868C18.6839 10.4413 19 11.2044 19 12" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M13 6C14.5913 6 16.1174 6.63214 17.2426 7.75736C18.3679 8.88258 19 10.4087 19 12" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="https://t.me/TIKTAK_PROKAT" target="_blank">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2L11 13" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
    
    <div class="header-main-full-width d-none d-md-block">
        <div class="container-app header-main-block">
            <a class="header-logo-small" href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/">
                <img src="/public/svg/logo_main.svg" alt="Tiktak.lt logo">
            </a>

            <form method="get" action="/{{(request()->lang ? request()->lang : 'ru')}}/search" class="header-search-small">
                <input name="search" type="text" placeholder="Я ищу... (например, автокресло)">
                <button type="submit"><img src="/public/svg/lupa.svg"></button>
            </form>

            <div class="header-actions">
                <a href="/login" class="action-item">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Войти</span>
                </a>
                <a href="/favorites" class="action-item">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20.84 4.60999C20.3292 4.099 19.7228 3.69364 19.0554 3.41708C18.3879 3.14052 17.6725 2.99817 16.95 2.99817C16.2275 2.99817 15.5121 3.14052 14.8446 3.41708C14.1772 3.69364 13.5708 4.099 13.06 4.60999L12 5.66999L10.94 4.60999C9.9083 3.5783 8.50903 2.9987 7.05 2.9987C5.59096 2.9987 4.19169 3.5783 3.16 4.60999C2.1283 5.64169 1.54871 7.04096 1.54871 8.49999C1.54871 9.95903 2.1283 11.3583 3.16 12.39L4.22 13.45L12 21.23L19.78 13.45L20.84 12.39C21.351 11.8792 21.7563 11.2728 22.0329 10.6053C22.3095 9.93789 22.4518 9.22248 22.4518 8.49999C22.4518 7.77751 22.3095 7.0621 22.0329 6.39464C21.7563 5.72718 21.351 5.12075 20.84 4.60999Z" stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Избранное</span>
                </a>
                <a href="/cart" class="action-item">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 22C9.55228 22 10 21.5523 10 21C10 20.4477 9.55228 20 9 20C8.44772 20 8 20.4477 8 21C8 21.5523 8.44772 22 9 22Z" stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M20 22C20.5523 22 21 21.5523 21 21C21 20.4477 20.5523 20 20 20C19.4477 20 19 20.4477 19 21C19 21.5523 19.4477 22 20 22Z" stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 1H5L7.68 14.39C7.77144 14.8504 8.02191 15.264 8.38755 15.5583C8.75318 15.8526 9.2107 16.009 9.68 16H19.4C19.8693 16.009 20.3268 15.8526 20.6925 15.5583C21.0581 15.264 21.3086 14.8504 21.4 14.39L23 6H6" stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Корзина</span>
                </a>
            </div>
        </div>
    </div>

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
        <div class="header-desktop d-md-none">
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
                    <ul class="d-md-none">
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
{{--                    <div class="messangers">--}}
{{--                      <a href="https://t.me/TIKTAK_PROKAT" aria-label="Telegram" target="_blank">--}}
{{--                        <img src="/public/svg/telagram-icon.svg" alt="Telegram">--}}
{{--                      </a>--}}
{{--                      <a href="viber://chat?number=+375297454040" aria-label="Viber">--}}
{{--                        <img src="/public/svg/viber-icon.svg" alt="Viber">--}}
{{--                      </a>--}}
{{--                      <a href="https://www.instagram.com/prokat_tiktak.by?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" aria-label="Instagram" target="_blank">--}}
{{--                        <img src="/public/svg/instagram-icon.svg" alt="Instagram">--}}
{{--                      </a>--}}

{{--                    </div>--}}
{{--                    <div class="phone-number-div">--}}
{{--                        <a class="header-phone" href="tel:+375447454040">+375 44 745 40 40</a>--}}
{{--                        <span class="messangers-span visually-hidden"><span>A1</span><span>MTC</span><span>Life</span><span>Viber</span></span>--}}
{{--                    </div>--}}

                </div>
            </div>
            <div class="header-col3 d-none d-md-flex">
{{--                <a class="office_geo-a" href="#" data-bs-toggle="modal" data-bs-target="#officesModal"><img class="geo" src="/public/svg/geo_logo.svg" alt="geo-logo"><span>Минск, наши салоны:</span></a>--}}
{{--                <a class="office_geo-a" href="#" data-bs-toggle="modal" data-bs-target="#officesModal"><span>ул. Ложинская, 5</span><img class="arrow" src="/public/svg/arrow_down.svg" alt="Arrow down"></a>--}}
{{--                <a class="office_geo-a" href="#" data-bs-toggle="modal" data-bs-target="#officesModal"><span>ул. Литературная, 22</span><img class="arrow" src="/public/svg/arrow_down.svg" alt="Arrow down"></a>--}}
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


