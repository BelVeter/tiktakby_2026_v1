@php
    {{
      $menu=\App\MyClasses\CatMenuItem::getAllMenu();
      $topMenu = new \bb\classes\TopMenu('ru');
    }}
@endphp
<header class="container-fluid d-none d-md-block" style="max-width: var(--max-app-size);">
    <div class="row header-desktop">
        <div class="col header-logo-col">
            <a href="/ru/"><img src="/public/img/logo_main.jpg" alt="Tiktak.lt logo" width="227" height="90"></a>
            <h4><a href="/ru/">прокат детских товаров</a></h4>
        </div>
        <div class="col-9 header-col2">
            <div class="header-line1">
                <form method="post" action="/zvonok" class="back-coll">
                    @csrf
                    <input type="hidden" name="action" value="back-coll">
                    <img class="close-cross" src="/public/png/close_cross.png">
                    <p>Для заказа обратного звонка заполните, пожалуйста, форму</p>
                    <div class="input-wrapper"><input data-targetinput="" class="call-input1" type="text" name="fio" placeholder="Ваше имя"></div>
                    <div class="input-wrapper"><input data-targetinput="" class="call-input1" type="text" name="phone" placeholder="Телефон"></div>
                    <div class="input-wrapper">
                        <textarea data-targetinput="" class="call-textarea1" name="info" placeholder="Дополнительная информация"></textarea>
                    </div>
                    <button>Отправить</button>
                </form>
                <ul>
                    <li><a href="/ru/#onas">О компании</a></li>
                    <li><a href="/ru/#conditions">Условия проката</a></li>
                    <li><a href="/ru/#delivery">Доставка и оплата</a></li>
                    <li><a href="#">Контакты</a></li>
                    <li><a data-callback href="#">Обратный звонок</a></li>
                </ul>
                <select class="lang">
                    <option>RU</option>
                </select>
            </div>
            <div class="header-line2">
                <form class="head-srch">
                    <img src="/public/svg/lupa.svg">
                    <input type="text" placeholder="Поиск">
                </form>
                <img class="messanger-icons" src="/public/jpg/messangers.jpg">
                <a class="header-phone" href="tel:+37069397294">+370 6 939 72 94 </a>
            </div>
            <div class="header-line3">
                <span>9.00-22.00 без выходных | Vilnius, Pilaitė</span>
            </div>
        </div>
    </div>
    </div>

</header>
<div class="container-fluid top-nav-container">
    <ul class="top-nav-row">
        @foreach($topMenu->getRazdels() as $r)
            <li class="top-nav-item">
                <a class="item-a" href="{{$r->getUrlForPage('ru')}}">
                    <img class="item-img" src="{{$r->getUrlIconRazdel()}}">
                    <span>{{$r->getNameRazdelText()}}</span>
                </a>
                <!-- SubRazdels-->
                @if($r->getSubRazdels())
                <div class="container-fluid top-cat-menu-container">
                    <div class="top-cat-sub-container">
                        @foreach($r->getSubRazdels() as $sr)
                            <ul class="top-cat-list">
                                <li><a class="list-header-img-li" href="{{$sr->getUrlForPage('ru', $r->getUrlRazdelName())}}"><img src="{{$sr->getUrlSubRazdelIcon()}}"></a></li>
                                <li class="list-header"><a href="{{$sr->getUrlForPage('ru', $r->getUrlRazdelName())}}">{{$sr->getNameSubRazdelText()}}</a></li>
                                @foreach($sr->getCategories() as $cat)
                                    <li><a href="{{$cat->getUrlForPage('ru', $r->getUrlRazdelName(), $sr->getUrlSubRazdelName())}}">{{$cat->getName()}}</a></li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                </div>
                @endif
            </li>
        @endforeach
{{--        <li class="top-nav-item"><a href="#" style="background-image: url('/public/svg/trips.svg')">ПРОГУЛКИ И ПОЕЗДКИ</a></li>--}}
{{--        <li class="top-nav-item"><a href="#" style="background-image: url('/public/svg/toys.svg')">ИГРУШКИ И ИГРЫ</a></li>--}}
{{--        <li class="top-nav-item"><a href="#" style="background-image: url('/public/svg/health.svg')">УХОД И ЗДОРОВЬЕ</a></li>--}}
{{--        <li class="top-nav-item"><a href="#" style="background-image: url('/public/svg/sport.svg')">СПОРТ И ОТДЫХ</a></li>--}}
{{--        <li class="top-nav-item"><a href="#" style="background-image: url('/public/svg/karnaval.svg')">КОСТЮМЕРНАЯ</a></li>--}}
    </ul>
</div>

<div class="container-fluid d-block d-md-none sticky-top nav-mob-top-menu" style="background-color: white">
    <div class="row py-2">
        <div class="col pt-1 align-content-center">
            <button class="navbar-toggler navbar-dark" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <img src="/public/menu1.png?v=2" style="height: 28px; position: relative; bottom: -1px"> <span style="color: var(--main-blue); position: relative; top: 2px;">MENU</span>
            </button>

        </div>
        <div class="col text-center">
            <a href="/ru/"><img src="/public/logo.png" style="height: 40px;"></a>
        </div>
        <div class="col text-right pt-1">
            <button class="navbar-toggler navbar-dark" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <img class="mr-2" src="/public/search.png?v=2" style="height: 29px;">
            </button>
            <a href="tel:+37069397294"><img class="mr-3" src="/public/phone.png" style="height: 25px;"></a>
        </div>
    </div>
    <div class="row">
        <div class="col-12 navbar-container" style="position: sticky">
            <!-- Вертикальное меню -->
            @include('includes.leftmenumob')
        </div>
    </div>
</div>
<!--  Top menu - the case earlier

<div class="d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom border-top box-shadow">
    <a class="my-0 mr-md-auto font-weight-normal">Company name</a>
    <nav class="my-2 my-md-0 mr-md-3">
        <a class="p-2 text-dark" href="#">Features</a>
        <a class="p-2 text-dark" href="#">Enterprise</a>
        <a class="p-2 text-dark" href="#">Support</a>
        <a class="p-2 text-dark" href="#">Pricing</a>
    </nav>
    <a class="btn btn-outline-primary" href="#">Sign up</a>
</div>


-->
