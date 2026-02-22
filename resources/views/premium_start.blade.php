@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', 'Программа «Premium Start» | Прокат детских товаров TikTak')
@section('meta-description', 'Первый год малыша без лишних хлопот и трат! Подписка на премиум-товары (Cybex, 4moms). Экономия до 80%.')

@section('content')

    <link href="/public/css/premium_start.css?v={{ time() }}" rel="stylesheet">

    <div class="container-app">
        <div class="row mt-4">
            @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>
    </div>

    <!-- ВЕСЬ КОНТЕНТ ЛЕНДИНГА БУДЕТ ЗДЕСЬ -->
    <div class="ps-landing">
        <!-- Блок 1: Главный экран (Hero Section) -->
        <section class="ps-hero">
            <div class="container-app h-100 position-relative">
                <div class="ps-hero__content">
                    <h1 class="ps-hero__title">
                        Программа «Premium Start»:
                        <span class="ps-hero__title-sub">Первый год малыша без лишних хлопот и трат!</span>
                    </h1>
                    <p class="ps-hero__subtitle">Пользуйтесь лучшими мировыми брендами (Cybex, 4moms), меняйте товары по
                        мере роста ребенка и экономьте до 80%.</p>
                    <a href="#ps-calculator" class="ps-btn ps-btn--caramel">Рассчитать выгоду</a>
                </div>
            </div>
        </section>

        <!-- Блок 2: Проблематика (Боли) -->
        <section class="ps-pains">
            <div class="container-app">
                <h2>Зачем покупать на века то, что нужно на пару месяцев?</h2>
                <div class="ps-pains__grid">
                    <div class="ps-pains__item">
                        <div class="ps-pains__icon">
                            <!-- Иконка коробки -->
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path
                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                                </path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </div>
                        <div class="ps-pains__text">Захламление квартиры</div>
                    </div>
                    <div class="ps-pains__item">
                        <div class="ps-pains__icon">
                            <!-- Иконка кошелька/денег -->
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="2" y1="10" x2="22" y2="10"></line>
                            </svg>
                        </div>
                        <div class="ps-pains__text">Огромные траты на старт</div>
                    </div>
                    <div class="ps-pains__item">
                        <div class="ps-pains__icon">
                            <!-- Иконка часов -->
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="ps-pains__text">Потеря времени на продажу б/у</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Блок 3: Решение (Как это работает) -->
        <section class="ps-solution">
            <div class="container-app">
                <h2>Как работает подписка</h2>
                <div class="ps-solution__steps">
                    <div class="ps-solution__step">
                        <div class="ps-solution__step-num">1</div>
                        <div class="ps-solution__step-text">Выбираете тариф</div>
                    </div>
                    <div class="ps-solution__arrow">➔</div>
                    <div class="ps-solution__step">
                        <div class="ps-solution__step-num">2</div>
                        <div class="ps-solution__step-text">Пользуетесь<br> премиум-товарами</div>
                    </div>
                    <div class="ps-solution__arrow">➔</div>
                    <div class="ps-solution__step">
                        <div class="ps-solution__step-num">3</div>
                        <div class="ps-solution__step-text">Бесплатно меняете на<br> следующие по возрасту</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Блок 4: Калькулятор выгоды -->
        <section id="ps-calculator" class="ps-calculator">
            <div class="container-app">
                <h2>Считаем ваши деньги: Покупка vs Подписка</h2>
                <div class="ps-calc">
                    <div class="ps-calc__table-container">
                        <table class="ps-calc__table">
                            <thead>
                                <tr>
                                    <th>Товары на первый год</th>
                                    <th>Покупка нового</th>
                                    <th class="ps-calc__highlight">По подписке (12 мес.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Коляска 2в1 (напр. Cybex Priam)</td>
                                    <td>~3500 BYN</td>
                                    <td rowspan="6" class="ps-calc__highlight ps-calc__sub-total">
                                        Ежемесячный платеж<br>
                                        <span class="ps-calc__big-price">100 BYN/мес</span><br>
                                        <small>(Итого: 1200 BYN)</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Автокресло 0+ (рекомендуемое)</td>
                                    <td>~800 BYN</td>
                                </tr>
                                <tr>
                                    <td>Колыбель/Приставная кроватка</td>
                                    <td>~500 BYN</td>
                                </tr>
                                <tr>
                                    <td>Шезлонг/Укачивающий центр (напр. 4moms)</td>
                                    <td>~1000 BYN</td>
                                </tr>
                                <tr>
                                    <td>Развивающий коврик, мобили</td>
                                    <td>~400 BYN</td>
                                </tr>
                                <tr>
                                    <td>Весы медицинские, манеж, шезлонги</td>
                                    <td>~700 BYN</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Итого:</td>
                                    <td>~6900 BYN</td>
                                    <td class="ps-calc__highlight"><b>~1200 BYN</b></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="ps-calc__result">
                        <div class="ps-calc__profit">Ваша выгода более 5500 BYN!</div>
                        <a href="#ps-tariffs" class="ps-btn ps-btn--caramel">Выбрать тариф</a>
                    </div>
                </div>
            </div>
            <!-- Блок 5: Ассортимент (Таймлайн / Слайдер) -->
            <section class="ps-timeline">
                <div class="container-app">
                    <h2>Эволюция комфорта 0–12 месяцев</h2>
                    <p class="ps-timeline__subtitle"
                        style="text-align: center; margin-bottom: 30px; color: var(--ps-text-muted); font-size: 1.1rem;">
                        Набор товаров согласовывается с родителями и зависит от тарифного плана.
                    </p>
                    <div class="ps-tabs">
                        <button class="ps-tab active" data-target="tab-1">0-4 мес</button>
                        <button class="ps-tab" data-target="tab-2">4-8 мес</button>
                        <button class="ps-tab" data-target="tab-3">8-12 мес</button>
                    </div>
                    <div class="ps-tab-content active" id="tab-1">
                        <div class="ps-timeline__images ps-timeline__images--transparent">
                            <div class="ps-assortment-fan">
                                <div class="ps-assortment-item">
                                    <img src="/public/rent/images/koliaski-prokat-minsk/CybexBaliosSLux_2025_prokat/FabConvert_1.webp"
                                        alt="Коляска" class="ps-assortment-item__img">
                                    <div class="ps-assortment-item__label">Коляска</div>
                                </div>
                                <div class="ps-assortment-item">
                                    <img src="/public/rent/images/kolybeli/4momsmamaroosleep_bassinet/4momsmamaroosleep_bassinet.jpg"
                                        alt="Колыбель" class="ps-assortment-item__img">
                                    <div class="ps-assortment-item__label">Колыбель</div>
                                </div>
                                <div class="ps-assortment-item">
                                    <img src="/public/rent/images/kolybel-kacheli/electric_kacheli_4moms_mamaroo_4.0_color_multiplush/1.jpg"
                                        alt="Кокон" class="ps-assortment-item__img">
                                    <div class="ps-assortment-item__label">Кокон</div>
                                </div>
                                <div class="ps-assortment-item">
                                    <img src="/public/rent/images/vannochka/StokkeFlexi_Bath/6441440515.jpg"
                                        alt="Ванночка для купания" class="ps-assortment-item__img">
                                    <div class="ps-assortment-item__label">Ванночка для купания</div>
                                </div>
                                <div class="ps-assortment-item">
                                    <img src="/public/rent/images/scales/vesy_detskie_laica_MD6141/http-www.jpg" alt="Весы"
                                        class="ps-assortment-item__img">
                                    <div class="ps-assortment-item__label">Весы</div>
                                </div>
                                <div class="ps-assortment-item">
                                    <img src="/public/rent/images/radio-nyana/philips_avent_philips_avent_scd_501/philips_avent_philips_avent_scd5011.jpg"
                                        alt="Радионяня" class="ps-assortment-item__img">
                                    <div class="ps-assortment-item__label">Радионяня</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ps-tab-content" id="tab-2">
                        <div class="ps-timeline__images">
                            <img src="https://placehold.co/800x400/F8F5F2/D4A373?text=Товары+4-8+месяцев"
                                alt="Товары 4-8 месяцев">
                        </div>
                    </div>
                    <div class="ps-tab-content" id="tab-3">
                        <div class="ps-timeline__images">
                            <img src="https://placehold.co/800x400/F8F5F2/D4A373?text=Товары+8-12+месяцев"
                                alt="Товары 8-12 месяцев">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Блок 6: Блок доверия (Гигиена) -->
            <section class="ps-hygiene">
                <div class="container-app">
                    <h2>Безопасно, как из магазина (и даже лучше)</h2>
                    <div class="ps-hygiene__grid">
                        <div class="ps-hygiene__item">
                            <div class="ps-hygiene__icon">🌱</div>
                            <div class="ps-hygiene__text">Эко-химия</div>
                        </div>
                        <div class="ps-hygiene__arrow">➔</div>
                        <div class="ps-hygiene__item">
                            <div class="ps-hygiene__icon">💨</div>
                            <div class="ps-hygiene__text">Обработка паром 130°C</div>
                        </div>
                        <div class="ps-hygiene__arrow">➔</div>
                        <div class="ps-hygiene__item">
                            <div class="ps-hygiene__icon">☀️</div>
                            <div class="ps-hygiene__text">Кварцевание</div>
                        </div>
                        <div class="ps-hygiene__arrow">➔</div>
                        <div class="ps-hygiene__item">
                            <div class="ps-hygiene__icon">📦</div>
                            <div class="ps-hygiene__text">Герметичная упаковка</div>
                        </div>
                    </div>
            </section>

            <!-- Блок 7: Тарифы -->
            <section id="ps-tariffs" class="ps-tariffs">
                <div class="container-app">
                    <h2>Выберите свой пакет</h2>
                    <p class="ps-tariffs__subtitle"
                        style="text-align: center; margin-bottom: 40px; color: var(--ps-text-muted); max-width: 600px; margin-left: auto; margin-right: auto;">
                        Доступна оплата в рассрочку поквартально, а также оплата подарочными сертификатами.
                    </p>
                    <div class="ps-tariffs__cards">
                        <!-- Тариф 1 -->
                        <div class="ps-tariff-card">
                            <div class="ps-tariff-card__header">
                                <h3>Базовый</h3>
                                <div class="ps-tariff-card__price">100 BYN <span>/ мес</span></div>
                            </div>
                            <ul class="ps-tariff-card__features">
                                <li>Бесплатная замена товаров 1 раз в месяц</li>
                                <li>Выбор премиум товаров из предложенных в тарифе, либо любые товары из ассортимента
                                    проката на сумму ежемесячного платежа</li>
                            </ul>
                            <button class="ps-btn ps-btn--outline" data-target-modal="ps-modal-order">Оформить
                                подписку</button>
                        </div>
                        <!-- Тариф 2 -->
                        <div class="ps-tariff-card ps-tariff-card--premium">
                            <div class="ps-tariff-card__badge">ХИТ ВЫБОР МАМ</div>
                            <div class="ps-tariff-card__header">
                                <h3>Оптимальный</h3>
                                <div class="ps-tariff-card__price">200 BYN <span>/ мес</span></div>
                            </div>
                            <ul class="ps-tariff-card__features">
                                <li>Бесплатная замена товаров 1 раз в месяц</li>
                                <li>Выбор премиум товаров из предложенных в тарифе, либо любые товары из ассортимента
                                    проката на сумму ежемесячного платежа</li>
                            </ul>
                            <button class="ps-btn ps-btn--caramel" data-target-modal="ps-modal-order">Оформить
                                подписку</button>
                        </div>
                        <!-- Тариф 3 -->
                        <div class="ps-tariff-card">
                            <div class="ps-tariff-card__header">
                                <h3>Премиум</h3>
                                <div class="ps-tariff-card__price">300 BYN <span>/ мес</span></div>
                            </div>
                            <ul class="ps-tariff-card__features">
                                <li>Бесплатная замена товаров неограниченное количество раз (не чаще 1 раза в неделю)</li>
                                <li>Выбор премиум товаров из предложенных в тарифе, либо любые товары из ассортимента
                                    проката на сумму ежемесячного платежа</li>
                            </ul>
                            <button class="ps-btn ps-btn--outline" data-target-modal="ps-modal-order">Оформить
                                подписку</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Блок 8: Подарочный сертификат -->
            <section class="ps-gift">
                <div class="container-app">
                    <div class="ps-gift__wrap">
                        <div class="ps-gift__image">
                            <!-- Плоская заглушка (или сертификат TikTak) -->
                            <div class="ps-gift__mockup">
                                <h4>Подарочный Сертификат</h4>
                                <p>«Premium Start»</p>
                                <div class="ps-gift__logo">TikTak.by</div>
                            </div>
                        </div>
                        <div class="ps-gift__content">
                            <h2>Ищете идеальный подарок на смотрины?</h2>
                            <p>Подарите молодым родителям самое ценное — спокойствие и комфорт. Сертификат на программу
                                Premium Start — лучше, чем очередная мягкая игрушка.</p>
                            <button class="ps-btn ps-btn--outline" data-target-modal="ps-modal-gift">Купить в
                                подарок</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Блок 9: FAQ -->
            <section class="ps-faq">
                <div class="container-app">
                    <h2>Остались вопросы?</h2>
                    <div class="ps-faq__list">
                        <details class="ps-faq__item">
                            <summary>Разве не выгоднее все купить на Авито/Куфаре?</summary>
                            <div class="ps-faq__content">Вещи б/у часто имеют скрытые дефекты. У нас вы получаете
                                новые или идеально чистые товары после химчистки, с гарантией и возможностью
                                обратной замены. Никаких встреч с продавцами и торга!</div>
                        </details>
                        <details class="ps-faq__item">
                            <summary>Что покрывает страховка? А если ребенок испачкает коляску?</summary>
                            <div class="ps-faq__content">В Premium Year включена полная страховка — мы берем на себя
                                химчистку пятен от еды и естественный износ. Если поломка механическая по вине
                                пользователя (например, порвали ткань ножом), мы решаем это индивидуально, но всегда
                                лояльны к клиентам.</div>
                        </details>
                        <details class="ps-faq__item">
                            <summary>Как часто я могу менять товары?</summary>
                            <div class="ps-faq__content">В базовом тарифе доступна 1 замена в месяц. В Premium Year
                                — безлимитно. Малыш вырос из колыбельки? Привезем манеж. Не понравился укачивающий
                                центр? Поменяем на шезлонг.</div>
                        </details>
                        <details class="ps-faq__item">
                            <summary>Какая гигиена товаров?</summary>
                            <div class="ps-faq__content">У нас строгий 3-этапный стандарт: эко-стирка, обработка
                                паром 130°C и кварцевание бактерицидными лампами. Товар доставляется в герметичной
                                упаковке.</div>
                        </details>
                    </div>
                </div>
            </section>

            <!-- Блок 10: Footer / Форма -->
            <section class="ps-lead">
                <div class="container-app">
                    <div class="ps-lead__wrap">
                        <h2>Поможем подобрать идеальный пакет для вашего малыша</h2>
                        <p>Оставьте контакты, наш менеджер (которая тоже мама) вам перезвонит и ответит на все
                            вопросы.</p>
                        <form action="{{ route('zvonokSave') }}" method="POST" class="ps-lead__form">
                            @csrf
                            <input type="hidden" name="type" value="premium-start">
                            <input type="text" name="name" placeholder="Ваше имя" required class="ps-input">
                            <input type="tel" name="phone" placeholder="Номер телефона" required class="ps-input">
                            <button type="submit" class="ps-btn ps-btn--caramel">Перезвоните мне</button>
                        </form>
                        <div class="ps-lead__messengers">
                            <span>Или напишите нам напрямую:</span>
                            <a href="https://t.me/tiktakby" target="_blank"
                                class="ps-messenger ps-messenger--tg">Telegram</a>
                            <a href="viber://chat?number=%2B37529XXXXXXX" target="_blank"
                                class="ps-messenger ps-messenger--viber">Viber</a>
                        </div>
                    </div>
                </div>
            </section>

    </div>



    <script src="/public/js/premium_start.js"></script>

    @if(isset($_COOKIE['tt_is_logged_in']))
        <div data-bb-edit-url="/bb/page_management.php" data-bb-edit-method="POST"
            data-bb-edit-params='@json(["level_code" => "main", "url_key" => "premium-start"])'></div>
    @endif
@endsection