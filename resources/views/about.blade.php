@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', 'О прокате TikTak: аренда детских товаров в Минске | Надежность и Команда')
@section('meta-description', '⭐️ Работаем 16 лет! Узнайте, как мы дезинфицируем детские товары (3 этапа очистки). Познакомьтесь с командой TikTak. 🚚 Доставка по Минску и району.')

@section('content')

    <div class="container-app">
        <div class="row mt-4">
            @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>

        <div class="row">
            <div class="col-12">
                <h1 class="about__h1">Сервис проката TikTak в Минске — история и стандарты работы</h1>
                <p class="about__first-p">
                    TikTak — это не просто склад вещей. Это сервис, который помогает жителям Минска экономить семейный
                    бюджет и пространство в квартире. Мы понимаем, как быстро растут дети и как нерационально покупать
                    дорогие вещи на 1-2 месяца.
                    <br><br>
                    Наш прокат специализируется на предоставлении
                    <a href="/ru/prokat-detskih-tovarov" title="Перейти в каталог детских товаров"
                        style="color: inherit; text-decoration: underline;">современных, безопасных и необходимых
                        товаров</a>: от
                    точнейших медицинских весов для новорожденных до тренажеров для реабилитации. Наша миссия — сделать
                    качественный быт и уход за здоровьем доступным каждому, без лишних трат на покупку.
                </p>

                <h2 class="about__h1" style="margin-top: 40px;">Стандарт чистоты TikTak: 3 этапа обработки</h2>
                <p class="about__first-p">
                    Мы знаем, что главный вопрос при аренде вещей для малышей и здоровья — это гигиена. В TikTak мы внедрили
                    жесткий протокол дезинфекции, который исключает любые риски. Каждый предмет, возвращаясь на склад,
                    проходит полный цикл обработки перед следующей выдачей:
                </p>
                <ul style="list-style-type: none; padding-left: 0; font-size: 1.1em; line-height: 1.6;">
                    <li style="margin-bottom: 15px;">✅ <strong>Глубокая очистка:</strong> Использование гипоаллергенных
                        моющих средств, безопасных для младенцев (ECO-сертификация).</li>
                    <li style="margin-bottom: 15px;">✅ <strong>Дезинфекция медкласса:</strong> Обработка поверхностей
                        средствами, применяемыми в медицинских учреждениях, уничтожающими 99.9% бактерий и вирусов.</li>
                    <li style="margin-bottom: 15px;">✅ <strong>Кварцевание и пар:</strong> Текстильные элементы и
                        труднодоступные места обрабатываются паром высокой температуры и кварцевыми лампами.</li>
                    <li style="margin-bottom: 15px;">✅ <strong>Упаковка:</strong> Чистый товар сразу упаковывается в
                        герметичную пленку. Вы — первый, кто вскроет её.</li>
                </ul>

                <div class="about__main-rent" style="margin-top: 30px;">
                    прокат tiktak
                    <span class="add-on">&ndash; это</span>
                </div>
            </div>

            <div class="about__cards-container">
                <div class="about__card-container">
                    <div class="count-up-span number" data-end="16" data-time="2000">16</div>
                    <div class="circle">лет на рынке услуг</div>
                </div>
                <div class="about__card-container second">
                    <div class="count-up-span number" data-end="3456" data-time="2000">3456</div>
                    <div class="circle">товаров в ассортименте</div>
                </div>
                <div class="about__card-container">
                    <div class="count-up-span number" data-end="54" data-time="2000">54</div>
                    <div class="circle">тысячи довольных клиентов</div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12 mb-5">
                <h2 class="about__h2">Люди, которые работают для вас</h2>
                <p class="about__first-p" style="max-width: 800px; margin-top: 30px;">
                    За каждым принятым звонком и доставленным заказом стоят реальные люди. Мы всегда готовы
                    проконсультировать, помочь с настройкой техники или выбором игрушки.
                </p>
            </div>
        </div>

        <div class="row justify-content-center about__photos-container" style="display: flex; flex-wrap: wrap;">

            <div class="col-12 col-md-4 mb-5 d-flex justify-content-center">
                <div class="about__person-container" style="position: relative; width: auto; height: auto;">
                    <div class="text-center mb-3">
                        <img class="staff-img" src="/public/images/team/ekaterina.jpg"
                            alt="Екатерина - директор сервиса проката TikTak Минск" loading="lazy" width="200" height="200"
                            style="position: relative; width: 200px; height: 200px; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div class="staff-description"
                        style="position: relative; left: auto; top: auto; width: 100%; text-align: center;">
                        <span class="staff-name" style="color: #E63625; font-size: 1.5em; display: block;">Екатерина</span>
                        <span class="staff-position" style="display: block; margin-bottom: 10px;">Директор</span>
                        <span class="staff-quote">“Слежу за тем, чтобы сервис работал как швейцарские часы. Ваше доверие —
                            наш капитал.”</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4 mb-5 d-flex justify-content-center">
                <div class="about__person-container" style="position: relative; width: auto; height: auto;">
                    <div class="text-center mb-3">
                        <img class="staff-img" src="/public/images/team/julia.jpg" alt="Юлия - старший консультант TikTak"
                            loading="lazy" width="200" height="200"
                            style="position: relative; width: 200px; height: 200px; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div class="staff-description"
                        style="position: relative; left: auto; top: auto; width: 100%; text-align: center;">
                        <span class="staff-name" style="color: #3180D1; font-size: 1.5em; display: block;">Юлия</span>
                        <span class="staff-position" style="display: block; margin-bottom: 10px;">Старший консультант</span>
                        <span class="staff-quote">“Помогу выбрать идеальные весы или игрушку именно под возраст вашего
                            малыша.”</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4 mb-5 d-flex justify-content-center">
                <div class="about__person-container" style="position: relative; width: auto; height: auto;">
                    <div class="text-center mb-3">
                        <img class="staff-img" src="/public/images/team/kristina.jpg"
                            alt="Кристина - менеджер по работе с клиентами TikTak" loading="lazy" width="200" height="200"
                            style="position: relative; width: 200px; height: 200px; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div class="staff-description"
                        style="position: relative; left: auto; top: auto; width: 100%; text-align: center;">
                        <span class="staff-name" style="color: #ACD701; font-size: 1.5em; display: block;">Кристина</span>
                        <span class="staff-position" style="display: block; margin-bottom: 10px;">Менеджер по работе с
                            клиентами</span>
                        <span class="staff-quote">“Всегда на связи. Объясню, подскажу и оформлю продление за пару
                            минут.”</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4 mb-5 d-flex justify-content-center">
                <div class="about__person-container" style="position: relative; width: auto; height: auto;">
                    <div class="text-center mb-3">
                        <img class="staff-img" src="/public/images/team/anastasia.jpg"
                            alt="Анастасия - поддержка клиентов TikTak" loading="lazy" width="200" height="200"
                            style="position: relative; width: 200px; height: 200px; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div class="staff-description"
                        style="position: relative; left: auto; top: auto; width: 100%; text-align: center;">
                        <span class="staff-name" style="color: #5CA8E0; font-size: 1.5em; display: block;">Анастасия</span>
                        <span class="staff-position" style="display: block; margin-bottom: 10px;">Специалист
                            поддержки</span>
                        <span class="staff-quote">“Забота о клиенте для меня на первом месте. Нет нерешаемых
                            вопросов!”</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4 mb-5 d-flex justify-content-center">
                <div class="about__person-container" style="position: relative; width: auto; height: auto;">
                    <div class="text-center mb-3">
                        <img class="staff-img" src="/public/images/team/driver-georgiy-delivery.jpg"
                            alt="Водитель-экспедитор Георгий доставляет детские товары TikTak по Минску" loading="lazy"
                            width="200" height="200"
                            style="position: relative; width: 200px; height: 200px; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div class="staff-description"
                        style="position: relative; left: auto; top: auto; width: 100%; text-align: center;">
                        <span class="staff-name" style="color: #FCD716; font-size: 1.5em; display: block;">Георгий</span>
                        <span class="staff-position" style="display: block; margin-bottom: 10px;">Водитель-экспедитор</span>
                        <span class="staff-quote">“Доставляю радость вовремя. Бережно привезу, подниму и покажу, как
                            работает.”</span>
                    </div>
                </div>
            </div>

        </div>
        <div class="row mt-5 mb-5">
            <div class="col-12">
                <h2 class="about__h1 mb-4">Частые вопросы</h2>
                <div class="accordion" id="faqAccordion">

                    <div class="faq-group mb-5">
                        <h3 class="d-flex align-items-center mb-4"
                            style="font-size: 1.5rem; font-weight: bold; color: #4CAF50;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-shield me-2" style="margin-right: 10px;">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                            Самое важное (Безопасность и Оформление)
                        </h3>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Как вы обрабатываете товары? Это безопасно для
                                ребенка?</h4>
                            <p>Безопасность — наш приоритет. Мы не просто протираем вещи, а проводим полноценную дезинфекцию
                                в 3 этапа после каждого клиента: стирка гипоаллергенными средствами, обработка паром и
                                кварцевание медицинскими лампами. Товар упаковывается в пленку. Вы получаете гарантированно
                                чистую вещь.</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Что нужно для оформления проката? Нужен ли
                                залог?</h4>
                            <p>Для заключения договора необходим только паспорт гражданина РБ (или вид на жительство). В
                                большинстве случаев залог не требуется. Исключение могут составлять дорогостоящие товары или
                                случаи, когда у клиента нет прописки в Минске/Минской области — этот момент менеджер уточнит
                                индивидуально.</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Как забронировать вещь и можно ли получить
                                реальное фото товара?</h4>
                            <p>Забронировать можно через сайт (корзину), по телефону или в мессенджерах. Если вы хотите
                                убедиться в состоянии конкретной модели, напишите нам в Viber/Telegram — мы пришлем
                                актуальное фото или видео товара, который поедет к вам.</p>
                        </div>
                    </div>

                    <div class="faq-group mb-5">
                        <h3 class="d-flex align-items-center mb-4"
                            style="font-size: 1.5rem; font-weight: bold; color: #2196F3;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-truck me-2" style="margin-right: 10px;">
                                <rect x="1" y="3" width="15" height="13"></rect>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                <circle cx="18.5" cy="18.5" r="2.5"></circle>
                            </svg>
                            Доставка и Возврат
                        </h3>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Есть ли доставка за пределы МКАД и как она
                                рассчитывается?</h4>
                            <p>Да, мы <a href="/delivery" title="Условия доставки"
                                    style="text-decoration: underline; color: inherit;">доставляем товары по Минскому
                                    району</a>. Стоимость рассчитывается просто: стандартный
                                тариф по городу + доплата за километраж от МКАД. Точную сумму оператор назовет при
                                подтверждении заказа.</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Курьер сам заберет товар, когда срок аренды
                                закончится?</h4>
                            <p>По умолчанию возврат осуществляется клиентом. Однако, если вам неудобно ехать к нам, вы
                                можете заказать услугу вывоза товара курьером. Пожалуйста, сообщите об этом заранее
                                (желательно за 1-2 дня до окончания срока), чтобы мы поставили это в график маршрутов.
                                Услуга платная.</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Можно ли заказать доставку/возврат к точному
                                времени?</h4>
                            <p>У наших курьеров плотный график с интервалами доставки (обычно 3-4 часа). Мы всегда стараемся
                                учитывать пожелания (например, "до обеденного сна ребенка"), но доставка "ровно в 13:00" не
                                всегда возможна из-за дорожной ситуации.</p>
                        </div>
                    </div>

                    <div class="faq-group mb-5">
                        <h3 class="d-flex align-items-center mb-4"
                            style="font-size: 1.5rem; font-weight: bold; color: #FF9800;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-settings me-2" style="margin-right: 10px;">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                                </path>
                            </svg>
                            Использование (Продление, Поломки, Стирка)
                        </h3>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Вы напоминаете об окончании срока аренды?</h4>
                            <p>Мы ценим ваше спокойствие. За сутки до окончания срока наша система автоматически отправляет
                                SMS-напоминание. Вы точно не забудете продлить вещь или подготовить её к возврату.</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Как продлить аренду, если вещь еще нужна?</h4>
                            <p>Очень просто! Не нужно приезжать в офис. Просто позвоните нам или напишите в мессенджер. Мы
                                продлим договор дистанционно, а оплату можно будет внести через ЕРИП.</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Что делать, если товар сломался у нас дома?</h4>
                            <p>Главное — не паниковать и не пытаться чинить самостоятельно. Сразу свяжитесь с нами.</p>
                            <p>Если это естественный износ (сели батарейки, отклеилась наклейка) — это наши заботы.</p>
                            <p>Если поломка механическая по неосторожности — мы найдем компромиссное решение (ремонт или
                                компенсация запчасти) согласно договору. Мы лояльны к клиентам и не наживаемся на
                                форс-мажорах.</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Нужно ли стирать текстиль (чехлы, костюмы) перед
                                возвратом?</h4>
                            <p>Нет, это делать не нужно. Мы в любом случае отправляем все текстильные элементы в
                                профессиональную чистку, чтобы гарантировать стерильность следующему малышу. Сдавайте как
                                есть!</p>
                        </div>

                        <div class="mb-4">
                            <h4 style="font-weight: bold; cursor: pointer;">Что делать, если товар не подошел или ребенок
                                отказался в нем сидеть?</h4>
                            <p>Мы рекомендуем внимательно выбирать модель (наши консультанты помогут!). Если вещь исправна,
                                но просто "не зашла" ребенку, возможен досрочный возврат. Перерасчет средств производится
                                согласно условиям договора (обычно пересчитывается по тарифу за фактический срок
                                использования, но не менее минимального срока аренды).</p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="row mt-2 mb-5 justify-content-center">
                <div class="col-12 text-center mb-4">
                    <h2 class="about__h1 mb-3">Полезная информация</h2>
                </div>

                <div class="col-12 col-md-4 mb-4">
                    <div class="card h-100 text-center"
                        style="border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s; cursor: pointer;"
                        onmouseover="this.style.transform='translateY(-5px)'"
                        onmouseout="this.style.transform='translateY(0)'" onclick="window.location.href='/ru/conditions'">
                        <div class="card-body p-4">
                            <div
                                style="width: 60px; height: 60px; background-color: #e3f2fd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
                                    fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-file-text">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                            </div>
                            <h4 class="font-weight-bold mb-2">Условия проката</h4>
                            <p class="text-muted mb-0">Правила оформления договора, залоги и возврат.</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4 mb-4">
                    <div class="card h-100 text-center"
                        style="border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s; cursor: pointer;"
                        onmouseover="this.style.transform='translateY(-5px)'"
                        onmouseout="this.style.transform='translateY(0)'" onclick="window.location.href='/ru/delivery'">
                        <div class="card-body p-4">
                            <div
                                style="width: 60px; height: 60px; background-color: #fff3e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
                                    fill="none" stroke="#FF9800" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-truck">
                                    <rect x="1" y="3" width="15" height="13"></rect>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                </svg>
                            </div>
                            <h4 class="font-weight-bold mb-2">Доставка</h4>
                            <p class="text-muted mb-0">Тарифы на доставку по Минску и району, самовывоз.</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4 mb-4">
                    <div class="card h-100 text-center"
                        style="border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s; cursor: pointer;"
                        onmouseover="this.style.transform='translateY(-5px)'"
                        onmouseout="this.style.transform='translateY(0)'" onclick="window.location.href='/ru/payment'">
                        <div class="card-body p-4">
                            <div
                                style="width: 60px; height: 60px; background-color: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
                                    fill="none" stroke="#4CAF50" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-credit-card">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                </svg>
                            </div>
                            <h4 class="font-weight-bold mb-2">Способы оплаты</h4>
                            <p class="text-muted mb-0">ЕРИП, наличный расчет, банковские карты и переводы.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <script src="/public/js/about.js"></script>

        <script type="application/ld+json">
                                            {
                                              "@context": "https://schema.org",
                                              "@type": "RentalBusiness",
                                              "name": "TikTak",
                                              "image": "https://tiktak.by/images/logo.png",
                                              "@id": "https://tiktak.by",
                                              "url": "https://tiktak.by",
                                              "telephone": "+37529XXXXXXX",
                                              "priceRange": "$$",
                                              "address": {
                                                "@type": "PostalAddress",
                                                "streetAddress": "Улица, дом",
                                                "addressLocality": "Минск",
                                                "addressCountry": "BY"
                                              },
                                              "geo": {
                                                "@type": "GeoCoordinates",
                                                "latitude": 53.9006,
                                                "longitude": 27.5590
                                              }
                                            }
                                            </script>

        <script type="application/ld+json">
                                            {
                                              "@context": "https://schema.org",
                                              "@type": "FAQPage",
                                              "mainEntity": [{
                                                "@type": "Question",
                                                "name": "Как вы обрабатываете товары? Это безопасно для ребенка?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Безопасность — наш приоритет. Мы не просто протираем вещи, а проводим полноценную дезинфекцию в 3 этапа после каждого клиента: стирка гипоаллергенными средствами, обработка паром и кварцевание медицинскими лампами. Товар упаковывается в пленку. Вы получаете гарантированно чистую вещь."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Что нужно для оформления проката? Нужен ли залог?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Для заключения договора необходим только паспорт гражданина РБ (или вид на жительство). В большинстве случаев залог не требуется. Исключение могут составлять дорогостоящие товары или случаи, когда у клиента нет прописки в Минске/Минской области — этот момент менеджер уточнит индивидуально."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Как забронировать вещь и можно ли получить реальное фото товара?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Забронировать можно через сайт (корзину), по телефону или в мессенджерах. Если вы хотите убедиться в состоянии конкретной модели, напишите нам в Viber/Telegram — мы пришлем актуальное фото или видео товара, который поедет к вам."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Есть ли доставка за пределы МКАД и как она рассчитывается?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Да, мы доставляем товары по Минскому району. Стоимость рассчитывается просто: стандартный тариф по городу + доплата за километраж от МКАД. Точную сумму оператор назовет при подтверждении заказа."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Курьер сам заберет товар, когда срок аренды закончится?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "По умолчанию возврат осуществляется клиентом. Однако, если вам неудобно ехать к нам, вы можете заказать услугу вывоза товара курьером. Пожалуйста, сообщите об этом заранее (желательно за 1-2 дня до окончания срока), чтобы мы поставили это в график маршрутов. Услуга платная."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Можно ли заказать доставку/возврат к точному времени?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "У наших курьеров плотный график с интервалами доставки (обычно 3-4 часа). Мы всегда стараемся учитывать пожелания (например, до обеденного сна ребенка), но доставка ровно в 13:00 не всегда возможна из-за дорожной ситуации."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Вы напоминаете об окончании срока аренды?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Мы ценим ваше спокойствие. За сутки до окончания срока наша система автоматически отправляет SMS-напоминание. Вы точно не забудете продлить вещь или подготовить её к возврату."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Как продлить аренду, если вещь еще нужна?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Очень просто! Не нужно приезжать в офис. Просто позвоните нам или напишите в мессенджер. Мы продлим договор дистанционно, а оплату можно будет внести через ЕРИП."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Что делать, если товар сломался у нас дома?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Главное — не паниковать и не пытаться чинить самостоятельно. Сразу свяжитесь с нами. Если это естественный износ (сели батарейки, отклеилась наклейка) — это наши заботы. Если поломка механическая по неосторожности — мы найдем компромиссное решение (ремонт или компенсация запчасти) согласно договору."
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Нужно ли стирать текстиль (чехлы, костюмы) перед возвратом?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Нет, это делать не нужно. Мы в любом случае отправляем все текстильные элементы в профессиональную чистку, чтобы гарантировать стерильность следующему малышу. Сдавайте как есть!"
                                                }
                                              }, {
                                                "@type": "Question",
                                                "name": "Что делать, если товар не подошел или ребенок отказался в нем сидеть?",
                                                "acceptedAnswer": {
                                                  "@type": "Answer",
                                                  "text": "Мы рекомендуем внимательно выбирать модель (наши консультанты помогут!). Если вещь исправна, но просто не зашла ребенку, возможен досрочный возврат. Перерасчет средств производится согласно условиям договора (обычно пересчитывается по тарифу за фактический срок использования, но не менее минимального срока аренды)."
                                                }
                                              }]
                                            }
                                            </script>
        @if(isset($_COOKIE['tt_is_logged_in']))
            <div data-bb-edit-url="/bb/page_management.php" data-bb-edit-method="POST"
                data-bb-edit-params='@json(["level_code" => "main", "url_key" => "about"])'></div>
        @endif
@endsection