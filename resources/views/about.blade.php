@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', 'О прокате TikTak: аренда детских товаров в Минске | Надежность и Команда')

@section('content')

    <div class="container-app">
        <div class="row mt-4">
            @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>

        <div class="row">
            <div class="col-12">
                <h1 class="about__h1">Сервис проката TikTak в Минске — история и стандарты работы</h1>
                <p class="about__first-p strong">
                    TikTak — это не просто склад вещей. Это сервис, который помогает жителям Минска экономить семейный
                    бюджет и пространство в квартире. Мы понимаем, как быстро растут дети и как нерационально покупать
                    дорогие вещи на 1-2 месяца.
                    <br><br>
                    Наш прокат специализируется на предоставлении современных, безопасных и необходимых товаров: от
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
            <div class="col-12 text-center mb-5">
                <h2 class="about__h2">Люди, которые работают для вас</h2>
                <p class="about__first-p" style="max-width: 800px; margin: 0 auto;">
                    За каждым принятым звонком и доставленным заказом стоят реальные люди. Мы всегда готовы
                    проконсультировать, помочь с настройкой техники или выбором игрушки.
                </p>
            </div>
        </div>

        <div class="row justify-content-center about__photos-container" style="display: flex; flex-wrap: wrap;">

            <div class="col-12 col-md-4 mb-5 d-flex justify-content-center">
                <div class="about__person-container" style="position: relative; width: auto; height: auto;">
                    <div class="text-center mb-3">
                        <img class="staff-img" src="/path/to/photo_director.jpg" alt="Директор"
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
                        <img class="staff-img" src="/path/to/photo_consultant1.jpg" alt="Консультант"
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
                        <img class="staff-img" src="/path/to/photo_consultant2.jpg" alt="Консультант"
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
                        <img class="staff-img" src="/path/to/photo_consultant3.jpg" alt="Консультант"
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
                        <img class="staff-img" src="/path/to/photo_driver.jpg" alt="Водитель"
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
                <h2 class="about__h1">Частые вопросы</h2>
                <div class="accordion" id="faqAccordion">
                    <div style="margin-bottom: 20px;">
                        <h4 style="font-weight: bold;">— Насколько безопасно брать медицинские товары?</h4>
                        <p>Абсолютно безопасно. Все части, контактирующие с телом, либо являются одноразовыми/сменными, либо
                            проходят стерилизацию медицинского уровня в три этапа.</p>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <h4 style="font-weight: bold;">— Как быстро вы доставляете по Минску?</h4>
                        <p>При оформлении заказа до 14:00 доставка часто возможна в тот же день. Мы согласуем удобное время,
                            чтобы не разбудить малыша.</p>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <h4 style="font-weight: bold;">— Что делать, если вещь сломается?</h4>
                        <p>Мелкие поломки из-за естественного износа мы берем на себя. Мы всегда стараемся найти
                            компромиссное решение..</p>
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
                                "name": "Насколько безопасно брать медицинские товары напрокат?",
                                "acceptedAnswer": {
                                  "@type": "Answer",
                                  "text": "Абсолютно безопасно. Все части, контактирующие с телом, либо являются одноразовыми/сменными, либо проходят стерилизацию медицинского уровня."
                                }
                              }, {
                                "@type": "Question",
                                "name": "Как быстро вы доставляете по Минску?",
                                "acceptedAnswer": {
                                  "@type": "Answer",
                                  "text": "При оформлении заказа до 14:00 доставка часто возможна в тот же день."
                                }
                              }]
                            }
                            </script>

@endsection