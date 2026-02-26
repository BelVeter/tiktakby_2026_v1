@php
    {{
    /** @var \bb\classes\TopMenu */
    $topMenu = \bb\classes\TopMenu::getTopMenu(request()->lang);
    /** @var \App\MyClasses\Header */
    $header = new \App\MyClasses\Header(request()->lang);
    }}
@endphp


<header>
    <!-- Announcement Banner -->
    <div
        style="background: linear-gradient(90deg, #3180D1, #8E24AA); color: white; text-align: center; padding: 8px 15px; font-size: 0.95rem; font-weight: 500; letter-spacing: 0.3px; position: relative; z-index: 1050; line-height: 1.3;">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-center">
            <div class="d-flex align-items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="margin-right: 8px; flex-shrink: 0;">
                    <polyline points="20 12 20 22 4 22 4 12"></polyline>
                    <rect x="2" y="7" width="20" height="5"></rect>
                    <line x1="12" y1="22" x2="12" y2="7"></line>
                    <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                    <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
                </svg>
                <span>Бесплатная доставка по Минску</span>
            </div>
            <a href="/ru/delivery" class="mt-1 mt-md-0 ms-md-2"
                style="color: #FFD54F; text-decoration: none; font-weight: bold; border-bottom: 1px dashed #FFD54F;">Узнать
                подробнее</a>
        </div>
    </div>

    <div class="top-header-full-width d-none d-md-block">
        <div class="container-app top-header-bar">
            <div class="top-bar-left">
                <a
                    href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/about">{{$header->translate('О компании')}}</a>
                <a
                    href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/conditions">{{$header->translate('Условия проката')}}</a>
                <a
                    href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/delivery">{{$header->translate('Доставка')}}</a>
                <a
                    href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/payment">{{$header->translate('Оплата')}}</a>
            </div>
            <div class="top-bar-center">
                <div class="top-phone">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M22 16.92V19.92C22.0011 20.1985 21.9441 20.4742 21.8325 20.7294C21.7209 20.9845 21.5573 21.2136 21.3521 21.4019C21.1468 21.5901 20.9046 21.7335 20.6407 21.8227C20.3769 21.9119 20.0974 21.9451 19.82 21.92C16.7428 21.5856 13.787 20.5342 11.19 18.85C8.77382 17.3147 6.72533 15.2662 5.18999 12.85C3.49997 10.2412 2.44824 7.271 2.11999 4.18001C2.095 3.90347 2.12787 3.62477 2.21649 3.36163C2.30512 3.09849 2.44756 2.85669 2.63476 2.65163C2.82196 2.44656 3.0498 2.28271 3.30379 2.17053C3.55777 2.05834 3.83233 2.00027 4.10999 2.00001H7.10999C7.5953 1.99523 8.06579 2.16708 8.43376 2.48354C8.80173 2.79999 9.04207 3.23945 9.10999 3.72001C9.22952 4.68141 9.46455 5.62488 9.80999 6.53001C9.94454 6.88793 9.97366 7.27692 9.8939 7.65089C9.81415 8.02485 9.62886 8.36812 9.35999 8.64001L8.08999 9.91001C9.51355 12.4136 11.5864 14.4865 14.09 15.91L15.36 14.64C15.6319 14.1859 16.3491 14.1061C16.7231 14.0263 17.1121 14.0555 17.47 14.19C18.3751 14.5355 19.3186 14.7705 20.28 14.89C20.7658 14.9585 21.2094 15.2032 21.5265 15.5775C21.8437 15.9518 22.0122 16.4296 22 16.92Z"
                            stroke="#3180d1" style="stroke: #3180d1 !important;" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path d="M16 9C16.7956 9 17.5587 9.31607 18.1213 9.87868C18.6839 10.4413 19 11.2044 19 12"
                            stroke="#3180d1" style="stroke: #3180d1 !important;" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path d="M13 6C14.5913 6 16.1174 6.63214 17.2426 7.75736C18.3679 8.88258 19 10.4087 19 12"
                            stroke="#3180d1" style="stroke: #3180d1 !important;" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#callbackModal"
                        style="color: #333; text-decoration: none;">+375 44 745 40 40</a>
                </div>
            </div>
            <div class="top-bar-right">
                <div class="top-address">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#officesModal">
                        <img src="/public/svg/geo_logo.svg" alt="geo-logo">
                        <span>Минск, Литературная 22</span>
                        <img src="/public/svg/arrow_down.svg" alt="arrow" class="arrow-down">
                    </a>
                </div>
                <a href="https://www.instagram.com/prokat_tiktak.by?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                    target="_blank">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M17 2H7C4.23858 2 2 4.23858 2 7V17C2 19.7614 4.23858 22 7 22H17C19.7614 22 22 19.7614 22 17V7C22 4.23858 19.7614 2 17 2Z"
                            stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round" />
                        <path
                            d="M16 11.37C16.1234 12.2022 15.9813 13.0522 15.5938 13.799C15.2063 14.5458 14.5932 15.1514 13.8416 15.5297C13.0901 15.9079 12.2385 16.0396 11.4078 15.9059C10.5771 15.7723 9.80977 15.3801 9.21485 14.7852C8.61993 14.1902 8.22774 13.4229 8.09408 12.5922C7.96042 11.7615 8.09208 10.9099 8.47034 10.1584C8.8486 9.40685 9.4542 8.79374 10.201 8.40624C10.9478 8.01874 11.7978 7.87658 12.63 8C13.4789 8.12588 14.2649 8.52146 14.8717 9.1283C15.4785 9.73515 15.8741 10.5211 16 11.37Z"
                            stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M17.5 6.5H17.51" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
                <a href="viber://chat?number=+375297454040" aria-label="Viber">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M22 16.92V19.92C22.0011 20.1985 21.9441 20.4742 21.8325 20.7294C21.7209 20.9845 21.5573 21.2136 21.3521 21.4019C21.1468 21.5901 20.9046 21.7335 20.6407 21.8227C20.3769 21.9119 20.0974 21.9451 19.82 21.92C16.7428 21.5856 13.787 20.5342 11.19 18.85C8.77382 17.3147 6.72533 15.2662 5.18999 12.85C3.49997 10.2412 2.44824 7.271 2.11999 4.18001C2.095 3.90347 2.12787 3.62477 2.21649 3.36163C2.30512 3.09849 2.44756 2.85669 2.63476 2.65163C2.82196 2.44656 3.0498 2.28271 3.30379 2.17053C3.55777 2.05834 3.83233 2.00027 4.10999 2.00001H7.10999C7.5953 1.99523 8.06579 2.16708 8.43376 2.48354C8.80173 2.79999 9.04207 3.23945 9.10999 3.72001C9.22952 4.68141 9.46455 5.62488 9.80999 6.53001C9.94454 6.88793 9.97366 7.27692 9.8939 7.65089C9.81415 8.02485 9.62886 8.36812 9.35999 8.64001L8.08999 9.91001C9.51355 12.4136 11.5864 14.4865 14.09 15.91L15.36 14.64C15.6319 14.3711 15.9751 14.1859 16.3491 14.1061C16.7231 14.0263 17.1121 14.0555 17.47 14.19C18.3751 14.5355 19.3186 14.7705 20.28 14.89C20.7658 14.9585 21.2094 15.2032 21.5265 15.5775C21.8437 15.9518 22.0122 16.4296 22 16.92Z"
                            stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M16 9C16.7956 9 17.5587 9.31607 18.1213 9.87868C18.6839 10.4413 19 11.2044 19 12"
                            stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M13 6C14.5913 6 16.1174 6.63214 17.2426 7.75736C18.3679 8.88258 19 10.4087 19 12"
                            stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
                <a href="https://t.me/TIKTAK_PROKAT" target="_blank">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2L11 13" stroke="#3180D1" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="#3180D1" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <div class="header-main-full-width d-none d-md-block">
        <div class="container-app header-main-block">
            <a class="header-logo-composite" href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/">
                <span class="logo-text-tiktak"><span class="c-blue">Tik</span><span class="c-dark">T</span><span
                        class="c-blue">a</span><span class="c-dark">k</span></span>
                <img src="/public/png/logo_icon.png"
                    alt="Прокат детских товаров, автокресел, колясок, биоптрона и других бытовых товаров"
                    class="logo-icon-svg">
                <span class="logo-text-rental">прокат</span>
            </a>

            <form method="get" action="/{{(request()->lang ? request()->lang : 'ru')}}/search"
                class="header-search-small">
                <input name="search" type="text" placeholder="Я ищу... (например, автокресло)">
                <button type="submit"><img src="/public/svg/lupa.svg"></button>
            </form>

            <div class="header-actions">
                <a href="#" class="action-item" data-bs-toggle="modal" data-bs-target="#profileComingSoonModal">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21"
                            stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        <path
                            d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z"
                            stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Войти</span>
                </a>
                <a href="/favorites" class="action-item">
                    <span style="position: relative; display: inline-block;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M20.84 4.60999C20.3292 4.099 19.7228 3.69364 19.0554 3.41708C18.3879 3.14052 17.6725 2.99817 16.95 2.99817C16.2275 2.99817 15.5121 3.14052 14.8446 3.41708C14.1772 3.69364 13.5708 4.099 13.06 4.60999L12 5.66999L10.94 4.60999C9.9083 3.5783 8.50903 2.9987 7.05 2.9987C5.59096 2.9987 4.19169 3.5783 3.16 4.60999C2.1283 5.64169 1.54871 7.04096 1.54871 8.49999C1.54871 9.95903 2.1283 11.3583 3.16 12.39L4.22 13.45L12 21.23L19.78 13.45L20.84 12.39C21.351 11.8792 21.7563 11.2728 22.0329 10.6053C22.3095 9.93789 22.4518 9.22248 22.4518 8.49999C22.4518 7.77751 22.3095 7.0621 22.0329 6.39464C21.7563 5.72718 21.351 5.12075 20.84 4.60999Z"
                                stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="favorites-badge" id="favorites-badge-desktop"
                            style="display:none; position:absolute; top:-6px; right:-8px; background:#E53935; color:#fff; font-size:11px; font-weight:700; border-radius:50%; min-width:18px; height:18px; line-height:18px; text-align:center; padding:0 4px;"></span>
                    </span>
                    <span>Избранное</span>
                </a>
                <a href="/cart" class="action-item">
                    <span style="position: relative; display: inline-block;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M9 22C9.55228 22 10 21.5523 10 21C10 20.4477 9.55228 20 9 20C8.44772 20 8 20.4477 8 21C8 21.5523 8.44772 22 9 22Z"
                                stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path
                                d="M20 22C20.5523 22 21 21.5523 21 21C21 20.4477 20.5523 20 20 20C19.4477 20 19 20.4477 19 21C19 21.5523 19.4477 22 20 22Z"
                                stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path
                                d="M1 1H5L7.68 14.39C7.77144 14.8504 8.02191 15.264 8.38755 15.5583C8.75318 15.8526 9.2107 16.009 9.68 16H19.4C19.8693 16.009 20.3268 15.8526 20.6925 15.5583C21.0581 15.264 21.3086 14.8504 21.4 14.39L23 6H6"
                                stroke="#3180D1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="cart-badge" id="cart-badge-desktop"
                            style="display:none; position:absolute; top:-6px; right:-8px; background:#E53935; color:#fff; font-size:11px; font-weight:700; border-radius:50%; min-width:18px; height:18px; line-height:18px; text-align:center; padding:0 4px;"></span>
                    </span>
                    <span>Корзина</span>
                </a>
            </div>
        </div>
    </div>

    <header>
        <div class="container-app">
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // Fix for opening the mobile menu
                    var menuToggles = document.querySelectorAll('.menu1-open');
                    // Defined variables in outer scope for cross-access
                    var mobileMenu = document.querySelector('.mobile-topmenu-container');
                    var infoMenu = document.querySelector('.mobile-info-menu-container');

                    // Helper function for closing info menu (needs to be available)
                    function closeInfoMenu() {
                        if (infoMenu) {
                            infoMenu.classList.add('d-none');
                            infoMenu.style.display = 'none';
                        }
                    }

                    // Helper function for closing mobile menu (catalog)
                    function closeMobileMenu() {
                        if (mobileMenu) {
                            mobileMenu.classList.add('d-none');
                            mobileMenu.style.display = 'none';
                            // Reset state when closing
                            var catalogList = document.querySelector('ul[data-navlevel="razdel"]');
                            if (catalogList) catalogList.classList.add('left');
                        }
                    }

                    if (mobileMenu && menuToggles.length > 0) {
                        function toggleMobileMenu(e) {
                            e.preventDefault();
                            e.stopPropagation();

                            if (mobileMenu.classList.contains('d-none')) {
                                // Close info menu if open
                                closeInfoMenu();

                                mobileMenu.classList.remove('d-none');
                                mobileMenu.style.display = 'flex'; // backdrop is flex/block

                                // NEW: Auto-open the catalog list and hide the intermediate row
                                if (intermediateRow) intermediateRow.style.display = 'none';
                                if (catalogList) {
                                    catalogList.classList.remove('left'); // Remove 'left' class which likely hides it
                                    catalogList.style.display = 'block'; // Ensure it's visible
                                }

                            } else {
                                closeMobileMenu();
                            }
                        }

                        menuToggles.forEach(function (btn) {
                            btn.addEventListener('click', toggleMobileMenu);
                            btn.addEventListener('touchstart', toggleMobileMenu, { passive: false });
                        });

                        // Close menu when clicking on the backdrop (mobileMenu container)
                        mobileMenu.addEventListener('click', function (e) {
                            if (e.target === mobileMenu) {
                                closeMobileMenu();
                            }
                        });
                    }

                    // Info Menu Logic (Hamburger)
                    var infoMenuToggles = document.querySelectorAll('.info-menu-open');
                    // infoMenu variable already defined above

                    if (infoMenu && infoMenuToggles.length > 0) {
                        function toggleInfoMenu(e) {
                            e.preventDefault();
                            e.stopPropagation();

                            if (infoMenu.classList.contains('d-none')) {
                                // Close catalog menu if open
                                closeMobileMenu();

                                infoMenu.classList.remove('d-none');
                                infoMenu.style.display = 'block';
                            } else {
                                closeInfoMenu();
                            }
                        }

                        infoMenuToggles.forEach(function (btn) {
                            btn.addEventListener('click', toggleInfoMenu);
                            btn.addEventListener('touchstart', toggleInfoMenu, { passive: false });
                        });

                        infoMenu.addEventListener('click', function (e) {
                            if (e.target === infoMenu) {
                                closeInfoMenu();
                            }
                        });
                    }
                });
            </script>
            <div class="mobile-header-new d-md-none"
                style="background: #fff; border-bottom: 1px solid #eee; position: relative;">

                <div class="mobile-top-row">

                    <div class="mobile-top-left">
                        <div class="menu-trigger info-menu-open" style="cursor: pointer;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="3" y1="12" x2="21" y2="12"></line>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <line x1="3" y1="18" x2="21" y2="18"></line>
                            </svg>
                        </div>
                        <a href="tel:+375447454040"
                            style="display: flex; align-items: center; text-decoration: none; margin-left: 15px;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                </path>
                            </svg>
                        </a>
                    </div>

                    <div class="mobile-logo-center">
                        <a class="header-logo-composite"
                            href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/">
                            <span class="logo-text-tiktak"><span class="c-blue">Tik</span><span
                                    class="c-dark">T</span><span class="c-blue">a</span><span
                                    class="c-dark">k</span></span>
                            <img src="/public/png/logo_icon.png"
                                alt="Прокат детских товаров, автокресел, колясок, биоптрона и других бытовых товаров"
                                class="logo-icon-svg">
                            <span class="logo-text-rental">прокат</span>
                        </a>
                    </div>

                    <div class="mobile-top-actions"
                        style="display: flex; gap: 15px; flex: 0 0 auto; justify-content: flex-end;">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#profileComingSoonModal"
                            style="color: #3180D1;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg></a>
                        <a href="/favorites" style="color: #3180D1; position: relative;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                                </path>
                            </svg>
                            <span class="favorites-badge" id="favorites-badge-mobile"
                                style="display:none; position:absolute; top:-6px; right:-6px; background:#E53935; color:#fff; font-size:11px; font-weight:700; border-radius:50%; min-width:18px; height:18px; line-height:18px; text-align:center; padding:0 4px;"></span>
                        </a>
                        <a href="/cart" style="color: #3180D1; position: relative;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            <span class="cart-badge" id="cart-badge-mobile"
                                style="display:none; position:absolute; top:-6px; right:-6px; background:#E53935; color:#fff; font-size:11px; font-weight:700; border-radius:50%; min-width:18px; height:18px; line-height:18px; text-align:center; padding:0 4px;"></span>
                        </a>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; padding: 10px 15px; background: #f8f9fa;">

                    <div class="menu1-open"
                        style="display: flex; align-items: center; background: #4A90E2; color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: 500; cursor: pointer;">
                        <svg style="margin-right: 8px;" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        {{$header->translate('Каталог')}}
                    </div>

                    <form action="/{{(request()->lang ? request()->lang : 'ru')}}/search" method="get"
                        style="flex-grow: 1; position: relative; margin-bottom: 0;">
                        <input type="text" name="search" placeholder="Я ищу..."
                            style="width: 100%; height: 40px; padding-left: 15px; border: 1px solid #ddd; border-radius: 8px; background: #fff;">
                        <button type="submit"
                            style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </button>
                    </form>
                </div>


                <!-- Moved Mobile Menu -->
                <!-- Moved Mobile Menu (Catalog) -->
                <nav class="row d-none mobile-topmenu-container"
                    style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); z-index: 1000; width: 100vw; margin: 0; height: calc(100vh - 100px); background: rgba(0,0,0,0.5); display: block;">
                    <div class="col mobile-menu-line1" style="display: none !important;">
                        <!-- Restore dummy elements to prevent JS error in app.js -->
                        <div class="col1"></div>
                        <div class="col2"></div>
                    </div>
                    <ul class="mobile-menu-list left" data-navlevel="razdel"
                        style="background: #fff; max-height: 80vh; overflow-y: auto; margin: 0; padding: 0; list-style: none; display: block; position: absolute; top: 0 !important; left: 0 !important; width: 100%; border-radius: 0 0 16px 16px; clip-path: inset(0 0 0 0 round 0 0 16px 16px);">
                        @foreach($topMenu->getRazdels() as $r)
                            <li class="nav-item-mobile expand" data-razdelid="{{$r->getIdRazdel()}}"><img class="icon"
                                    src="{{$r->getUrlIcon2Razdel()}}"><span
                                    class="razdel-text">{{$r->getNameRazdelText()}}</span><img class="arrow"
                                    src="/public/svg/v_arrow_left.svg"></li>
                        @endforeach
                    </ul>
                    @foreach($topMenu->getRazdels() as $r)
                        @if(is_array($topMenu->getRazdels()) && count($topMenu->getRazdels()) > 0)
                            <ul class="mobile-menu-list right" data-navlevel="subrazdel" data-razdelid="{{$r->getIdRazdel()}}"
                                style="background: #fff; max-height: 80vh; overflow-y: auto; margin: 0; padding: 0; list-style: none; position: absolute; top: 0 !important; width: 100%; border-radius: 0 0 16px 16px; clip-path: inset(0 0 0 0 round 0 0 16px 16px);">
                                <li class="nav-item-mobile expand back" data-backto="razdel"><img class="arrow-back"
                                        src="/public/svg/v_arrow_left.svg"><span
                                        class="razdel-text">{{$r->getNameRazdelText()}}</span></li>
                                @if(is_array($r->getSubRazdels()) && count($r->getSubRazdels()) > 0)
                                    @foreach($r->getSubRazdels() as $sr)
                                        <li class="nav-item-mobile link" data-subrazdelid="{{$sr->getIdSubRazdel()}}"><a
                                                href="{{$sr->getUrlForPage(request()->lang)}}"><img class="icon"
                                                    src="{{$sr->getUrlSubRazdelIcon()}}"><span
                                                    class="razdel-text">{{$sr->getNameSubRazdelText()}}</span><img class="arrow"
                                                    src="/public/svg/v_arrow_left.svg"></a></li>
                                        {{-- <li class="nav-item-mobile expand" data-subrazdelid="{{$sr->getIdSubRazdel()}}"><img
                                                class="icon" src="{{$sr->getUrlSubRazdelIcon()}}"><span
                                                class="razdel-text">{{$sr->getNameSubRazdelText()}}</span><img class="arrow"
                                                src="/public/svg/v_arrow_left.svg"></li>--}}
                                    @endforeach
                                @endif
                            </ul>
                        @endif
                    @endforeach
                </nav>

                <!-- Info Menu (Hamburger) -->
                <nav class="row d-none mobile-info-menu-container"
                    style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); z-index: 1000; width: 100vw; margin: 0; height: calc(100vh - 100px); background: rgba(0,0,0,0.5); display: block;">
                    <ul class="mobile-menu-list"
                        style="background: #fff; margin: 0; padding: 0; list-style: none; display: block; position: absolute; top: 0 !important; left: 0 !important; width: 100%; border-radius: 0 0 16px 16px; clip-path: inset(0 0 0 0 round 0 0 16px 16px);">
                        <li class="nav-item-mobile" style="border-bottom: 1px solid #eee;">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#officesModal"
                                style="display: flex; align-items: center; padding: 15px; text-decoration: none; color: #333; font-size: 16px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="margin-right: 10px;">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                Адреса наших салонов
                            </a>
                        </li>
                        <li class="nav-item-mobile" style="border-bottom: 1px solid #eee;">
                            <a href="/{{(request()->lang ? request()->lang : 'ru')}}/about"
                                style="display: flex; align-items: center; padding: 15px; text-decoration: none; color: #333; font-size: 16px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="margin-right: 10px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                О компании
                            </a>
                        </li>
                        <li class="nav-item-mobile" style="border-bottom: 1px solid #eee;">
                            <a href="/{{(request()->lang ? request()->lang : 'ru')}}/conditions"
                                style="display: flex; align-items: center; padding: 15px; text-decoration: none; color: #333; font-size: 16px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="margin-right: 10px;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                Условия проката
                            </a>
                        </li>
                        <li class="nav-item-mobile" style="border-bottom: 1px solid #eee;">
                            <a href="/{{(request()->lang ? request()->lang : 'ru')}}/delivery"
                                style="display: flex; align-items: center; padding: 15px; text-decoration: none; color: #333; font-size: 16px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="margin-right: 10px;">
                                    <rect x="1" y="3" width="15" height="13"></rect>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                </svg>
                                Доставка
                            </a>
                        </li>
                        <li class="nav-item-mobile" style="border-bottom: 1px solid #eee;">
                            <a href="/{{(request()->lang ? request()->lang : 'ru')}}/payment"
                                style="display: flex; align-items: center; padding: 15px; text-decoration: none; color: #333; font-size: 16px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="margin-right: 10px;">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                </svg>
                                Оплата
                            </a>
                        </li>
                        <li class="nav-item-mobile" style="border-bottom: 1px solid #eee;">
                            <a href="/{{(request()->lang ? request()->lang : 'ru')}}/contacts"
                                style="display: flex; align-items: center; padding: 15px; text-decoration: none; color: #333; font-size: 16px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="margin-right: 10px;">
                                    <path
                                        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                    </path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                Контакты
                            </a>
                        </li>
                        <li class="nav-item-mobile">
                            <a href="#" class="callback-trigger" data-bs-toggle="modal" data-bs-target="#callbackModal"
                                style="display: flex; align-items: center; padding: 15px; text-decoration: none; color: #333; font-size: 16px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="margin-right: 10px;">
                                    <path
                                        d="M15.05 5A5 5 0 0 1 19 8.95M15.05 1A9 9 0 0 1 23 8.94m-1 7.98v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                    </path>
                                </svg>
                                Заказать обратный звонок
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- Callback Modal -->
            <div class="modal fade" id="callbackModal" tabindex="-1" aria-labelledby="callbackModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="callbackModalLabel">Заказать обратный звонок</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="post" action="/zvonok" class="back-coll-modal">
                                @csrf
                                <div class="input-wrapper">
                                    <input data-targetinput="" class="call-input1" type="text" name="fio"
                                        placeholder="{{$header->translate('Ваше имя')}}"
                                        style="width: 100%; margin-bottom: 15px;">
                                </div>
                                <div class="input-wrapper">
                                    <input data-targetinput="" class="call-input1" type="text" name="phone"
                                        placeholder="{{$header->translate('Телефон')}}"
                                        style="width: 100%; margin-bottom: 15px;">
                                </div>
                                <div class="input-wrapper">
                                    <textarea data-targetinput="" class="call-textarea1" name="info"
                                        placeholder="{{$header->translate('Дополнительная информация')}}"
                                        style="width: 100%; margin-bottom: 15px;"></textarea>
                                </div>
                                <button type="submit"
                                    style="width: 100%; padding: 10px; border-radius: 25px; background: #fff; border: 1px solid #275991; color: #275991;">{{$header->translate('Отправить')}}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Call Back Form (Preserved) -->
            <div class="d-none">
                <form method="post" action="/zvonok" class="back-coll">
                    @csrf
                    <input type="hidden" name="action" value="back-coll">
                    <img class="close-cross" src="/public/png/close_cross.png">
                    <p>{{$header->translate('Для заказа обратного звонка заполните, пожалуйста, форму')}}</p>
                    <div class="input-wrapper"><input data-targetinput="" class="call-input1" type="text" name="fio"
                            placeholder="{{$header->translate('Ваше имя')}}"></div>
                    <div class="input-wrapper"><input data-targetinput="" class="call-input1" type="text" name="phone"
                            placeholder="{{$header->translate('Телефон')}}"></div>
                    <div class="input-wrapper">
                        <textarea data-targetinput="" class="call-textarea1" name="info"
                            placeholder="{{$header->translate('Дополнительная информация')}}"></textarea>
                    </div>
                    <button>{{$header->translate('Отправить')}}</button>
                </form>
            </div>

            <!-- mobile navigation (MOVED UP) -->
        </div>

        <!-- Salon Modal -->
        <style>
            #officesModal .modal-content {
                border-radius: 20px;
                border: none;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
                overflow: hidden;
            }

            #officesModal .om-header {
                background: linear-gradient(135deg, #3180D1 0%, #1a5faa 100%);
                color: #fff;
                padding: 22px 28px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            #officesModal .om-header h5 {
                font-size: 1.25rem;
                font-weight: 700;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            #officesModal .om-header .btn-close {
                filter: invert(1);
                opacity: 0.85;
            }

            #officesModal .om-body {
                padding: 24px 28px 0;
            }

            /* Info row */
            .om-info-row {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 20px;
            }

            .om-info-card {
                flex: 1 1 220px;
                background: #f7f9fc;
                border-radius: 14px;
                padding: 18px 20px;
            }

            .om-info-card .om-label {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                color: #3180D1;
                margin-bottom: 6px;
            }

            .om-info-card .om-val {
                font-size: 15px;
                font-weight: 600;
                color: #1a1a2e;
                line-height: 1.5;
            }

            .om-info-card .om-val a {
                color: #1a1a2e;
                text-decoration: none;
            }

            .om-info-card .om-val a:hover {
                color: #3180D1;
            }

            /* CTA Buttons */
            .om-cta-row {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 20px;
            }

            .om-btn {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 10px 18px;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                transition: transform 0.15s, box-shadow 0.15s;
                cursor: pointer;
                border: none;
            }

            .om-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.13);
            }

            .om-btn-call {
                background: #3180D1;
                color: #fff;
            }

            .om-btn-viber {
                background: #7360f2;
                color: #fff;
            }

            .om-btn-map {
                background: #fff;
                color: #3180D1;
                border: 2px solid #3180D1;
            }

            /* Map */
            .om-map-wrap {
                border-radius: 14px;
                overflow: hidden;
                margin-bottom: 22px;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            }

            .om-map-wrap iframe {
                display: block;
                width: 100%;
                height: 260px;
                border: 0;
            }

            /* Reviews */
            .om-reviews-section {
                margin-bottom: 24px;
            }

            .om-reviews-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 14px;
            }

            .om-reviews-rating-big {
                font-size: 2rem;
                font-weight: 800;
                color: #1a1a2e;
                line-height: 1;
            }

            .om-reviews-stars {
                color: #f5a623;
                font-size: 18px;
                letter-spacing: 1px;
            }

            .om-reviews-count {
                font-size: 13px;
                color: #888;
                margin-top: 2px;
            }

            .om-reviews-link {
                margin-left: auto;
                font-size: 13px;
                color: #3180D1;
                text-decoration: none;
                font-weight: 600;
                white-space: nowrap;
            }

            .om-reviews-link:hover {
                text-decoration: underline;
            }

            .om-reviews-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 12px;
            }

            .om-review-card {
                background: #f7f9fc;
                border-radius: 12px;
                padding: 14px 16px;
                border: 1px solid #edf0f7;
            }

            .om-review-top {
                display: flex;
                align-items: center;
                gap: 9px;
                margin-bottom: 8px;
            }

            .om-review-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: linear-gradient(135deg, #3180D1, #8E24AA);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 15px;
                font-weight: 700;
                flex-shrink: 0;
            }

            .om-review-name {
                font-size: 14px;
                font-weight: 700;
                color: #1a1a2e;
            }

            .om-review-stars {
                color: #f5a623;
                font-size: 13px;
            }

            .om-review-text {
                font-size: 13px;
                color: #555;
                line-height: 1.5;
            }

            .om-google-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 11px;
                color: #888;
                margin-top: 8px;
            }

            @media (max-width: 600px) {
                #officesModal .om-body {
                    padding: 16px 14px 0;
                }

                .om-map-wrap iframe {
                    height: 200px;
                }
            }
        </style>

        <div class="modal fade" id="officesModal" tabindex="-1" aria-labelledby="officesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="om-header">
                        <h5 id="officesModalLabel">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg>
                            Наш салон проката
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>

                    <div class="modal-body p-0">
                        <div class="om-body">

                            <!-- Info cards -->
                            <div class="om-info-row">
                                <div class="om-info-card">
                                    <div class="om-label">📍 Адрес</div>
                                    <div class="om-val">ул. Литературная, 22<br><span
                                            style="font-size:13px; color:#888; font-weight:400;">г. Минск</span></div>
                                </div>
                                <div class="om-info-card">
                                    <div class="om-label">📞 Телефоны</div>
                                    <div class="om-val">
                                        <a href="tel:+375296303532">+375 (29) 630-35-32</a><br>
                                        <a href="tel:+375447454040">+375 (44) 745-40-40</a>
                                    </div>
                                </div>
                                <div class="om-info-card">
                                    <div class="om-label">🕐 Часы работы</div>
                                    <div class="om-val">
                                        пн–пт: 10:00–19:00<br>
                                        сб, вс: 10:00–15:00
                                    </div>
                                </div>
                            </div>

                            <!-- CTA Buttons -->
                            <div class="om-cta-row">
                                <a href="tel:+375447454040" class="om-btn om-btn-call">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                                    </svg>
                                    Позвонить
                                </a>
                                <a href="viber://add?number=375447454040" class="om-btn om-btn-viber">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                                    </svg>
                                    Написать в Viber
                                </a>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=ул.+Литературная,+22,+Минск"
                                    target="_blank" class="om-btn om-btn-map">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="3 11 22 2 13 21 11 13 3 11" />
                                    </svg>
                                    Маршрут
                                </a>
                            </div>

                            <!-- Map -->
                            <div class="om-map-wrap">
                                <iframe
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d37576.53071565148!2d27.49688255176978!3d53.940037097398736!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbcf987119c2ed%3A0x38a520fb62e6031d!2sTIKTAK%20SALON%20PROKATA%20DETSKIH%20TOVAROV%20UP%20TODDLER%20FAN!5e0!3m2!1sen!2spl!4v1648465519790!5m2!1sen!2spl"
                                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            </div>

                            <!-- Reviews -->
                            <div class="om-reviews-section">
                                <div class="om-reviews-header">
                                    <div>
                                        <div class="om-reviews-rating-big">5.0</div>
                                        <div class="om-reviews-stars">★★★★★</div>
                                        <div class="om-reviews-count">рейтинг на Google</div>
                                    </div>
                                    <a href="https://www.google.com/maps/place/TIKTAK+SALON+PROKATA+DETSKIH+TOVAROV+UP+TODDLER+FAN/@53.9401009,27.5680041,15z"
                                        target="_blank" class="om-reviews-link">
                                        Все отзывы на Google →
                                    </a>
                                </div>

                                <div class="om-reviews-grid">
                                    <!-- Review 1 -->
                                    <div class="om-review-card">
                                        <div class="om-review-top">
                                            <div class="om-review-avatar">А</div>
                                            <div>
                                                <div class="om-review-name">Анастасия К.</div>
                                                <div class="om-review-stars">★★★★★</div>
                                            </div>
                                        </div>
                                        <div class="om-review-text">Отличный прокат! Брали коляску и автокресло — всё в
                                            идеальном состоянии. Персонал очень вежливый и помогает с выбором.
                                            Рекомендую!</div>
                                        <div class="om-google-badge">
                                            <svg width="12" height="12" viewBox="0 0 24 24">
                                                <path fill="#4285F4"
                                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                                <path fill="#34A853"
                                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                                <path fill="#FBBC05"
                                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                                                <path fill="#EA4335"
                                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                                            </svg>
                                            Google Maps
                                        </div>
                                    </div>
                                    <!-- Review 2 -->
                                    <div class="om-review-card">
                                        <div class="om-review-top">
                                            <div class="om-review-avatar"
                                                style="background: linear-gradient(135deg, #1a5faa, #3180D1);">Д</div>
                                            <div>
                                                <div class="om-review-name">Дмитрий С.</div>
                                                <div class="om-review-stars">★★★★★</div>
                                            </div>
                                        </div>
                                        <div class="om-review-text">Пользуемся уже второй раз. Удобно, что можно
                                            продлить аренду. Цены честные, товар качественный. Спасибо!</div>
                                        <div class="om-google-badge">
                                            <svg width="12" height="12" viewBox="0 0 24 24">
                                                <path fill="#4285F4"
                                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                                <path fill="#34A853"
                                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                                <path fill="#FBBC05"
                                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                                                <path fill="#EA4335"
                                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                                            </svg>
                                            Google Maps
                                        </div>
                                    </div>
                                    <!-- Review 3 -->
                                    <div class="om-review-card">
                                        <div class="om-review-top">
                                            <div class="om-review-avatar"
                                                style="background: linear-gradient(135deg, #8E24AA, #c158dc);">М</div>
                                            <div>
                                                <div class="om-review-name">Мария П.</div>
                                                <div class="om-review-stars">★★★★★</div>
                                            </div>
                                        </div>
                                        <div class="om-review-text">Очень удобный сервис для молодых родителей. Взяли
                                            кроватку и качели — ребёнку очень понравилось. Буду рекомендовать знакомым!
                                        </div>
                                        <div class="om-google-badge">
                                            <svg width="12" height="12" viewBox="0 0 24 24">
                                                <path fill="#4285F4"
                                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                                <path fill="#34A853"
                                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                                <path fill="#FBBC05"
                                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                                                <path fill="#EA4335"
                                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                                            </svg>
                                            Google Maps
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /.om-body -->
                    </div><!-- /.modal-body -->

                    <div class="modal-footer" style="border-top: 1px solid #eef0f6; padding: 14px 28px;">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                            style="background: #3180D1; border: none; padding: 9px 30px; border-radius: 10px; font-weight: 600;">Закрыть</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- desktop navigation -->
        <nav class="container-fluid top-nav-container d-none d-md-block">
            <div class="backgound"></div>
            <ul class="top-nav-row">
                @foreach($topMenu->getRazdels() as $r)
                    @if(is_object($r))
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
                                            @if(is_object($sr))
                                                <ul class="top-cat-list">
                                                    <li><a class="list-header-img-li"
                                                            href="{{$sr->getUrlForPage(request()->lang, $r->getUrlRazdelName())}}"><img
                                                                src="{{$sr->getUrlSubRazdelIcon()}}"></a></li>
                                                    <li class="list-header"><a
                                                            href="{{$sr->getUrlForPage(request()->lang, $r->getUrlRazdelName())}}">{{$sr->getNameSubRazdelText()}}</a>
                                                    </li>
                                                    @foreach($sr->getCategories() as $cat)
                                                        @if(is_object($cat))
                                                            <li><a
                                                                    href="{{$cat->getUrlForPage(request()->lang, $r->getUrlRazdelName(), $sr->getUrlSubRazdelName())}}">{{$cat->getName()}}</a>
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
        <!-- Profile Coming Soon Modal -->
        <div class="modal fade" id="profileComingSoonModal" tabindex="-1" aria-labelledby="profileComingSoonModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content"
                    style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <div class="modal-header" style="border-bottom: none; padding: 20px 20px 0;">
                        <h5 class="modal-title" id="profileComingSoonModalLabel" style="font-weight: 700; color: #333;">
                            Личный кабинет</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center" style="padding: 20px 30px 30px;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🚀</div>
                        <h4 style="color: #3180D1; font-weight: 700; margin-bottom: 15px;">Скоро открытие!</h4>
                        <p style="color: #666; font-size: 16px; margin-bottom: 25px;">Мы готовим для вас удобный личный
                            кабинет, где вы сможете:</p>

                        <div
                            style="text-align: left; background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 25px;">
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <span style="margin-right: 10px; font-size: 18px;">📄</span>
                                <span style="color: #333; font-weight: 500;">Видеть историю заказов</span>
                            </div>
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <span style="margin-right: 10px; font-size: 18px;">⏱️</span>
                                <span style="color: #333; font-weight: 500;">Продлевать аренду в один клик</span>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <span style="margin-right: 10px; font-size: 18px;">💳</span>
                                <span style="color: #333; font-weight: 500;">Быстро оплачивать заказы</span>
                            </div>
                        </div>

                        <p style="color: #888; font-size: 14px; margin-bottom: 0;">Следите за обновлениями!</p>
                    </div>
                    <div class="modal-footer" style="border-top: none; justify-content: center; padding-bottom: 30px;">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                            style="background: #3180D1; border: none; padding: 10px 40px; border-radius: 25px; font-weight: 600;">Понятно</button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    @php
        $an = \bb\classes\Announcement::getMessageByType('main');
    @endphp
    @if($an && $an->toShow())
        <div class="container-app">
            <div
                style="background-color: #E3F2FD; border-radius: 8px; padding: 15px; color: #1565C0; display: flex; align-items: center; justify-content: center; gap: 15px; margin-top: 10px; margin-bottom: 10px;">
                <div style="flex-shrink: 0;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                            stroke="#1565C0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M12 16V12" stroke="#1565C0" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path d="M12 8H12.01" stroke="#1565C0" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </div>
                <div style="font-size: 16px; line-height: 1.5; text-align: left;">
                    {!! $an->getMessage() !!}
                </div>
            </div>
        </div>
    @endif