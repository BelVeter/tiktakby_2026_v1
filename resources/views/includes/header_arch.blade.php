@php
    {{
      $menu=\App\MyClasses\CatMenuItem::getAllMenu();
    }}
@endphp

<div class="row pt-3 mb-2">
    <div class="col-12 col-md-3 text-center text-md-left text-lg-left text-xl-left">
        <div class="row">
            <div class="col-12">
                <img class="img-fluid" src="/public/logo.png">
                <h6 class="m-0" style="color: #025d9f; font-size:1.07rem;">прокат детских товаров</h6>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-9">
        <div class="row h-100">
            <div class="d-none d-sm-block col-md-12 text-right">
                <h4 style="color: #00a0d0; font-size: 1.9rem;font-weight: bold;">VAIKŲ PREKIŲ NUOMA &nbsp;&nbsp;| &nbsp;&nbsp;Rental service</h4>
            </div>
            <div class="col-12 col-md-auto ml-md-auto align-self-end">
                <h4 class="h4-headers m-0 pb-1 text-center text-lg-left text-xl-left pt-2 pt-sm-0" >
                    <svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-geo-alt" style="position: relative; top: -2px;font-size: 26px;" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                    </svg>
                    Pilaite, Vilnius
                </h4>
            </div>
            <div class="d-none d-md-block col-auto ml-4 align-self-end">
                <h4 class="h4-headers m-0 pb-1 text-right">
                    <svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-clock" style="position: relative; top: -2px;font-size: 26px;" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm8-7A8 8 0 1 1 0 8a8 8 0 0 1 16 0z"/>
                        <path fill-rule="evenodd" d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5z"/>
                    </svg>
                    09:00 - 21:00
                </h4>
            </div>
            <div class="col-12 col-md-auto  align-self-end">
                <h4 class="h4-headers m-0 pb-1 text-break text-center text-lg-right text-xl-right" style="color: #025d9f">
                    <a style="text-decoration: none;" href="tel:+37069397294" >
                        <svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-phone" style="position: relative; top: -2px;font-size: 26px;" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M11 1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/>
                            <path fill-rule="evenodd" d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                        </svg>
                        +370 69 397 294</a>
                </h4>
            </div>
        </div>
    </div>
</div>


<!-- Top menu -->
    <nav class="navbar navbar-expand-sm navbar-light bg-white border-bottom border-top box-shadow px-0 py-2 top_navbar" style="border:0!important;">

        <button class="navbar-toggler navbar-dark dropdown-toggle" style="background-color: #025d9f; height: 40px; color:white; border: 0" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="pl-2">Категории</span>
        </button>

        <button class="navbar-toggler ml-auto mr-3" style="border-color: white" type="button" data-toggle="collapse" data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
            <img src="/public/img/menu-dropdown-white.svg">
        </button>

        <div class="collapse navbar-collapse top_navbar2" id="navbarTogglerDemo02">
            <div class="navbar-brand dropdown my-0 top-cat" id="topcatlist">
                <button class="btn btn-primary dropdown-toggle d-none d-sm-block" style="background-color: #025d9f; height: 45px;" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img src="/public/img/menu-dropdown-white.svg" class="cat-menu-lines">Категории
                </button>
                <div class="dropdown-menu cat-menu" aria-labelledby="dropdownMenuButton">
                    <ul class="p-0">
                        @foreach($menu as $m)
                            <li class="dropdown-item">{{$m->getCatNameText()}}</li>
                            <ul>
                                @foreach($m->getChildItems() as $ch)
                                    <li class="dropdown-item"><a href="{{$ch->getUrl()}}">{{$ch->getCatNameText()}}</a></li>
                                @endforeach
                            </ul>
                        @endforeach


                        <li class="dropdown-item">На прогулке и в авто</li>
                        <ul>
                            <li class="dropdown-item"><a href="#">Автокресла</a></li>
                            <li class="dropdown-item"><a href="#">Коляски</a></li>
                            <li class="dropdown-item"><a href="#">Слинги, переноски</a></li>
                            <li class="dropdown-item"><a href="#">Аксессуары к коляскам</a></li>
                        </ul>
                        <li class="dropdown-item">Детская комната</li>
                        <li class="dropdown-item">sdfafa</li>
                    </ul>

                </div>
            </div>



            <form class="col">
                <div style="position: relative;"><img class="srch-img-main" src="/public/img/search.svg" alt="поиск" data-v-41aabd6a=""></div>
                <input class="form-control mr-sm-2 pl-5" type="search" placeholder="поиск">
                <!--<button class="btn btn-outline-success my-2 my-sm-0" type="submit">поиск</button>-->
            </form>


            <ul class="navbar-nav pl-3 pl-md-0 mt-2 mt-lg-0">
                <!--<li class="nav-item active">
                    <a class="nav-link" href="#">Главная <span class="sr-only">(current)</span></a>
                </li>-->
                <li class="nav-item">
                    <a class="nav-link" href="/">Главная</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('conditions', ['lang'=>'ru']) }}">Условия проката</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('delivery', ['lang'=>'ru']) }}">Доставка</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('about', ['lang'=>'ru']) }}">О нас</a>
                </li>

            </ul>

        </div>
    </nav>
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
