@php
    {{
    /** @var \bb\classes\TopMenu */
    $topMenu = \bb\classes\TopMenu::getTopMenu(request()->lang);
    /** @var \App\MyClasses\Header */
    $header = new \App\MyClasses\Header(request()->lang);
    }}
@endphp

<header class="site-header">
    {{-- 1. TOP BAR (Only Desktop) --}}
    <div class="top-bar bg-light py-1 d-none d-lg-block border-bottom">
        <div class="container">
            <div class="row align-items-center">
                {{-- Left: Menu Info --}}
                <div class="col-6">
                    <nav class="top-links">
                        <a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/about"
                            class="text-secondary text-decoration-none me-3 small">{{$header->translate('О компании')}}</a>
                        <a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/conditions"
                            class="text-secondary text-decoration-none me-3 small">{{$header->translate('Условия')}}</a>
                        <a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/delivery"
                            class="text-secondary text-decoration-none small">{{$header->translate('Доставка')}}</a>
                    </nav>
                </div>

                {{-- Right: Location, Socials, Language --}}
                <div class="col-6 d-flex justify-content-end align-items-center">
                    {{-- Location (Dropdown) --}}
                    <div class="dropdown me-3">
                        <button class="btn btn-sm btn-link text-dark text-decoration-none dropdown-toggle p-0"
                            type="button" data-bs-toggle="dropdown">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24" class="me-1">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Минск
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted">ул. Ложинская, 5</span></li>
                            <li><span class="dropdown-item-text small text-muted">ул. Литературная, 22</span></li>
                        </ul>
                    </div>

                    {{-- Socials (compact) --}}
                    <div class="socials me-3 d-flex gap-2">
                        <a href="https://t.me/TIKTAK_PROKAT" target="_blank" class="text-primary"><i
                                class="fab fa-telegram"></i></a>
                        <a href="viber://chat?number=+375297454040" class="text-primary"><i
                                class="fab fa-viber"></i></a>
                        <a href="https://www.instagram.com/prokat_tiktak.by?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                            target="_blank" class="text-primary"><i class="fab fa-instagram"></i></a>
                    </div>

                    {{-- Language --}}
                    <div class="lang-switch text-uppercase small fw-bold">
                        {{$topMenu->getLang() ?? 'RU'}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. MAIN HEADER (Common Block) --}}
    <div class="main-header py-3 bg-white shadow-sm sticky-top">
        <div class="container">
            <div class="row align-items-center justify-content-between">

                {{-- A. LEFT: Hamburger (mob) + Logo --}}
                <div class="col-auto d-flex align-items-center">
                    {{-- Hamburger (Only Mobile) --}}
                    <button class="btn btn-link text-dark d-lg-none me-2 p-0 menu1-open">
                        <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path d="M3 12h18M3 6h18M3 18h18"></path>
                        </svg>
                    </button>

                    {{-- Logo --}}
                    {{-- Logo --}}
                    <a href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/" class="navbar-brand me-4">
                        <img src="/public/svg/logo-tiktak.svg" alt="TikTak — сервис проката товаров в Минске"
                            height="40">
                    </a>
                </div>

                {{-- B. CENTER: Search (Only Desktop) --}}
                <div class="col d-none d-lg-flex align-items-center">
                    {{-- Search --}}
                    <form method="get" action="/{{(request()->lang ? request()->lang : 'ru')}}/search"
                        class="input-group flex-grow-1">
                        <input type="text" name="search" class="form-control border-end-0 rounded-start-pill bg-light"
                            placeholder="{{$header->translate('Поиск товаров (например, весы)...')}}">
                        <button class="btn btn-outline-secondary border-start-0 rounded-end-pill bg-light"
                            type="submit">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="M21 21l-4.35-4.35"></path>
                            </svg>
                        </button>
                    </form>
                </div>

                {{-- C. RIGHT: Action Icons --}}
                <div class="col-auto d-flex align-items-center gap-3 ms-lg-3">

                    {{-- Phone (Desktop only) --}}
                    <div class="d-none d-xl-block text-end me-2">
                        <a href="tel:+375447454040" class="d-block text-dark fw-bold text-decoration-none">+375 44 745
                            40 40</a>
                        <a href="#" class="d-block text-primary small text-decoration-none"
                            data-callback>{{$header->translate('Перезвоните мне')}}</a>
                    </div>

                    {{-- Search (Mobile only trigger) --}}
                    <button class="btn btn-link text-dark p-0 d-lg-none" type="button" data-bs-toggle="collapse"
                        data-bs-target="#mobileSearch">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                    </button>

                    {{-- Icon: Auth --}}
                    <a href="/login"
                        class="action-icon d-none d-md-flex flex-column align-items-center text-dark text-decoration-none">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="d-none d-xl-inline small mt-1">{{$header->translate('Войти')}}</span>
                    </a>

                    {{-- Icon: Wishlist --}}
                    <a href="/wishlist" class="action-icon position-relative text-dark text-decoration-none">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path
                                d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                            </path>
                        </svg>
                    </a>

                    {{-- Icon: Cart --}}
                    <a href="/cart" class="action-icon position-relative text-dark text-decoration-none">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">3</span>
                    </a>
                </div>
            </div>

            {{-- Mobile Search Dropdown --}}
            <div class="collapse d-lg-none mt-2" id="mobileSearch">
                <form method="get" action="/{{(request()->lang ? request()->lang : 'ru')}}/search" class="col">
                    <input type="text" name="search" class="form-control"
                        placeholder="{{$header->translate('Поиск...')}}">
                </form>
            </div>
        </div>
    </div>

    {{-- 3. NAV BAR (Category Navigation - blue strip) --}}
    <div class="desktop-catalog-strip d-none d-lg-block bg-primary">
        <div class="container h-100">
            <div class="row h-100 no-gutters">
                @foreach($topMenu->getRazdels() as $r)
                    <div class="col h-100">
                        <a href="{{$r->getUrlForPage(request()->lang)}}"
                            class="catalog-item d-flex flex-column align-items-center justify-content-center text-white text-decoration-none h-100 px-2 text-center hover-overlay">
                            <img src="{{$r->getUrlIcon2Razdel()}}" alt="{{$r->getNameRazdelText()}}" class="mb-2"
                                height="35">
                            <span class="small text-uppercase fw-bold">{{$r->getNameRazdelText()}}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Legacy Mobile Menu (Preserved for functionality) --}}
    <nav class="row d-flex d-md-none mobile-topmenu-container" style="display: none;">
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
                <button type="submit" class="srch-btn"><img class="srch-icon" src="/public/svg/lupa2.svg"
                        alt="search icon"></button>
            </form>
        </div>
        <ul class="mobile-menu-list left" data-navlevel="razdel">
            @foreach($topMenu->getRazdels() as $r)
                <li class="nav-item-mobile expand" data-razdelid="{{$r->getIdRazdel()}}"><img class="icon"
                        src="{{$r->getUrlIcon2Razdel()}}"><span class="razdel-text">{{$r->getNameRazdelText()}}</span><img
                        class="arrow" src="/public/svg/v_arrow_left.svg"></li>
            @endforeach
        </ul>
        @foreach($topMenu->getRazdels() as $r)
            @if(is_array($topMenu->getRazdels()) && count($topMenu->getRazdels()) > 0)
                <ul class="mobile-menu-list right" data-navlevel="subrazdel" data-razdelid="{{$r->getIdRazdel()}}">
                    <li class="nav-item-mobile expand back" data-backto="razdel"><img class="arrow-back"
                            src="/public/svg/v_arrow_left.svg"><span class="razdel-text">{{$r->getNameRazdelText()}}</span></li>
                    @if(is_array($r->getSubRazdels()) && count($r->getSubRazdels()) > 0)
                        @foreach($r->getSubRazdels() as $sr)
                            <li class="nav-item-mobile link" data-subrazdelid="{{$sr->getIdSubRazdel()}}"><a
                                    href="{{$sr->getUrlForPage(request()->lang)}}"><img class="icon"
                                        src="{{$sr->getUrlSubRazdelIcon()}}"><span
                                        class="razdel-text">{{$sr->getNameSubRazdelText()}}</span><img class="arrow"
                                        src="/public/svg/v_arrow_left.svg"></a></li>
                        @endforeach
                    @endif
                </ul>
            @endif
        @endforeach
    </nav>
</header>