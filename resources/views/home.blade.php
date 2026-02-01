@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', 'Прокат детских товаров Минск')
@section('meta-description', $p->getMetaDescription())
@section('canonical')
  <link rel="canonical" href="/ru">
@endsection
@section('content')
  <!-- slider -->
  <div class="container-app">
    @include('includes.carousel', ['p' => $p])
  </div>
  <!-- end of slider -->

    <!-- main content 1 -->
<section class="main-h1-section">
    <div class="container-app">
        <div class="row">
            <div class="col-12 pt-3 pt-md-5 main-page-block1">
                <h1 class="title" style="" id="onas">
                    {!! $p->getH1() !!}
                </h1>
                <div class="main-h1-text-block">
                    {!! $p->getH1LongText() !!}
                </div> {{-- should be formed from <p>s --}}
            </div>
        </div>
    </div>
</section>
<section class="main-categories">
    <div class="container-app main-categories__container">
        <div class="row row1">
            <a href="/ru/prokat-detskih-tovarov" class="main-categories__cat-block pic-right" style="background-color: #80BDE7;" title="Прокат детских товаров">
                <div class="col1">
                    <h3>ДЕТСКИЕ ТОВАРЫ</h3>
                    <p>автокресла, коляски, кроватки, шезлонги, качельки, весы, видеоняни, развивающие коврики и др.</p>
                </div>
                <div class="circle"><img class="img" style="width: 79px; bottom: 8px" src="/public/png/detskie_tovary.png" alt="Прокат детских товаров"></div>
            </a>
          <a href="/ru/prokat-sports" class="main-categories__cat-block pic-left" style="background-color: #3277BD;" title="Прокат оборудования для спорта и отдыха">
            <div class="col1">
              <h3>СПОРТ И ОТДЫХ</h3>
              <p>беговые дорожки, вело-тренажеры, велосипеды, самокаты и др. </p>
            </div>
            <div class="circle"><img class="img" style="width: 79px; bottom: 5px; left: -3px;" src="/public/png/cat_sport.png" alt="Прокат спортивного оборудования"></div>
          </a>
        </div>
        <div class="row row2">
          <a href="/ru/karnavalnye-kostyumy" class="main-categories__cat-block pic-right" style="background-color: #FFDE01;" title="Карнавальные костюмы напрокат">
            <div class="col1">
              <h3 style="color: #5C5C5C;">КОСТЮМЕРНАЯ</h3>
              <p style="color: #5C5C5C;">карнавальные костюмы для детей и врослых, нарядные платья для девочек, фраки для мальчиков</p>
            </div>
            <div class="circle"><img class="img" style="width: 106px; bottom: 11px; left: -5px;" src="/public/png/prokat-karnavalnyh-kostumov.png" alt="Прокат карнавальных костюмов Минск"></div>
          </a>
            <a href="/ru/prokat-detskih-tovarov/begovely_velosipedy_samokaty" class="main-categories__cat-block pic-left" style="background-color: #BFD242;" title="Прокат детских велосипедов, беговелов,самокатов">
                <div class="col1">
                    <h3>ДЕТСКИЙ ТРАНСПОРТ</h3>
                    <p>беговелы, велосипеды, самокаты, машинки-каталки, самокаты, защитная экипировка</p>
                </div>
              <div class="circle"><img class="img" style="width: 130px; bottom: 0px; left: -15px;" src="/public/png/prokat-velosipedov-minsk.png" alt="Прокат беговелов, велосипедов, самокатов, роликов Минск"></div>
            </a>

        </div>
        <div class="row row3">
            <a href="/ru/prokat-uborka" class="main-categories__cat-block pic-right" style="background-color: #80BDE7;" title="Прокат оборудования для уборки">
                <div class="col1">
                    <h3>ТЕХНИКА ДЛЯ УБОРКИ</h3>
                    <p>роботы-мойщики окон, моющие пылесосы, пароочистители и др.</p>
                </div>
                <div class="circle"><img class="img" style="width: 93px; bottom: 5px; left: -8px;" src="/public/png/tehnika_dla_uborki.png" alt="Прокат оборудования для уборки"></div>
            </a>
            <a href="/ru/medical-prokat" class="main-categories__cat-block pic-left" style="background-color: #E83328;" title="Прокат медицинского оборудования">
                <div class="col1">
                    <h3>КРАСОТА И ЗДОРОВЬЕ</h3>
                    <p>ингаляторы, биоптроны, приборы по уходу за лицом и телом и др.</p>
                </div>
                <div class="circle"><img class="img" style="width: 75px;" src="/public/png/health.png"></div>
            </a>
        </div>

        <div class="circle-logo__container">
            <div class="main-circle">
                <div class="grey-circle"></div>
                <div class="white-circle">
                    <span class="prokat-text">прокат</span>
                    <img src="/public/png/logo-circle.png" alt="Logo">
                </div>
            </div>

            <div class="small-circle circle1"></div>
            <div class="small-circle circle2"></div>
            <div class="small-circle circle3"></div>
            <div class="small-circle circle4"></div>
            <div class="small-circle circle5"></div>
            <div class="small-circle circle6"></div>

            <div class="hvostik hv1"></div>
            <div class="hvostik hv2"></div>
            <div class="hvostik hv3"></div>
            <div class="hvostik hv4"></div>
            <div class="hvostik hv5"></div>
            <div class="hvostik hv6"></div>
        </div>
    </div>
</section>

<section class="main-block2-section">
        <div class="container-app">
            <div class="row">
                <div class="col-12 pt-4 pt-md-5 main-page-block1">
                    <h2 class="title" style="" id="rent_info_2">
                      Почему выгодно брать напрокат в TikTak?
                    </h2>
                    <div class="main-h1-text-block">
                        {!! $p->getCodeBlock1() !!}
                    </div>
                </div>
            </div>
        </div>
</section>

<section class="faforite-section">
   <div class="container-app">
       <div class="row">
           <div class="col-12">
               <div class="favorite-tovar-header" style="" id="about">
                   <h2>Популярные товары</h2>
               </div>
           </div>

           <div class="col-12 top-card-container-col12">
               <div class="top-card-container">
                   @foreach($p->getFavoriteTovars() as $ft)
                       <div class="top-card">
                           <img class="img-fluid rounded-circle" src="{{$ft->getPicUrl()}}" alt="{{$ft->getPicAlt()}}">
                           <h5 class="top-card-title">{!! $ft->getNameText() !!}</h5>
                           <p class="top-card-text">{!! $ft->getDescription() !!}</p>
                           <div class="top-card-btn-div"><a href="{{$ft->getUrlL3Page()}}" class="">Подробнее</a></div>
                       </div>
                   @endforeach
               </div>
               <button class="top-card-arrow left"><img src="/public/svg/arrow_left_slider.svg?v=1"></button>
               <button class="top-card-arrow right"><img src="/public/svg/arrow_right_slider.svg?v=1"></button>
           </div>
       </div>
   </div>
</section>

<section class="age-breackdown-section">
    <div class="container-app">
        <div class="row">
            <div class="col-12">
                <h2>Выбирайте товары для малыша по возрасту</h2>
            </div>
            <div class="col-12 age-filters-container">
                <a href="/ru/filter?age_from=0&age_to=6" class="age-filter">
                    <div class="age-filter_age-circle"><span>0+</span></div>
                    <span class="age-filter_agetext">0-6 месяцев</span>
                </a>
                <a href="/ru/filter?age_from=6&age_to=12" class="age-filter">
                    <div class="age-filter_age-circle"><span>6+</span></div>
                    <span class="age-filter_agetext">6-12 месяцев</span>
                </a>
                <a href="/ru/filter?age_from=12&age_to=18" class="age-filter">
                    <div class="age-filter_age-circle"><span>12+</span></div>
                    <span class="age-filter_agetext">12-18 месяцев</span>
                </a>
                <a href="/ru/filter?age_from=18&age_to=24" class="age-filter">
                    <div class="age-filter_age-circle"><span>18+</span></div>
                    <span class="age-filter_agetext">18-24 месяца</span>
                </a>
                <a href="/ru/filter?age_from=24&age_to=36" class="age-filter">
                    <div class="age-filter_age-circle"><span>2+</span></div>
                    <span class="age-filter_agetext">2+ года</span>
                </a>
                <a href="/ru/filter?age_from=36&age_to=3600" class="age-filter">
                    <div class="age-filter_age-circle"><span>3+</span></div>
                    <span class="age-filter_agetext">3+ года</span>
                </a>
            </div>
        </div>
    </div>
    <div class="age-filter_bottom-border"></div>
</section>

<section class="review-section">
    <div class="container-app">
        <div class="review-container">
            <h2>Отзывы</h2>
            <div class="description">Узнайте, что говорят о нашем сервисе другие родители</div>
            <div class="review-slider-container">
                <div class="review-item current">
                    <img src="/public/jpg/review1.jpg" alt="photo">
                    <div class="message-box">
                        <p class="message-text">«Пользуемся услугами проката ТикТак с самого рождения нашего сынишки. Он быстро растёт и я не вижу смысла покупать дорогие вещи, нужные всего на пару месяцев, если их можно взять в прокат по вполне умеренным ценам.»</p>
                        <div class="review-line">
                            <span class="name">-Алеся </span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                        </div>
                    </div>
                </div>
                <div class="review-item">
                    <img src="/public/jpg/review2.jpg" alt="photo">
                    <div class="message-box">
                        <p class="message-text">«Сервис проката Тик Так - просто находлка для нашей семьи. Брали всё: от весов для новорожденных до колясок и игрушек. Отличный выбор, грамотно подобранный ассортимент и лучшие мировые бренды. ТикТак экономит деньги и нервы родителям. Рекомендую!»</p>
                        <div class="review-line">
                            <span class="name">-Кирилл </span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                        </div>
                    </div>
                </div>
                <div class="review-item">
                    <img src="/public/jpg/review1.jpg" alt="photo">
                    <div class="message-box">
                        <p class="message-text">«Хочу выразить благодарность сотрудникам проката ТикТак. Оперативно ответили на запрос, подробно проконсультировали, помогли подобрать коляску для путешествия в самолете с малышом, быстро доставили. Очень довольны уровнем сервиса, будем еще обращаться.»</p>
                        <div class="review-line">
                            <span class="name">-Кристина </span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                            <span class="star"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pages">
                <div class="page active"></div>
                <div class="page"></div>
                <div class="page"></div>
            </div>
        </div>
    </div>
</section>

<section class="produser-section">
    <div class="container-app">
        <div class="producer-container">
            <h2>Популярные бренды</h2>
            <div class="description">Предлагаем к прокату только надеждных и проверенных производителей детских товаров</div>
        </div>
    </div>
    <div class="producer-card-container">
        @if($p->getProducers())
{{--            @php(dd($p->getProducers()))--}}
            @foreach($p->getProducers() as $producer)
                <a href="/ru/producer?producer={{$producer->getNameUrlEncoded()}}" class="producer-card">
                    <span>{{$producer->getName()}}</span>
                    <img src="{{$producer->getUrl()}}" alt="rent of {{$producer->getName()}} items">
                </a>
            @endforeach
        @endif
    </div>
</section>

<section class="main-block2-section">
    <div class="container-app">
        <div class="row">
            <div class="col-12 pt-4 pt-md-5 main-page-block1">
                <h2 class="title" style="" id="rent_info_2">
                    {{$p->getBlock2Title()}}
                </h2>
                <div class="main-h1-text-block">{!! $p->getCodeBlock2() !!}</div>
{{--                should be formed from <p>s--}}
            </div>
        </div>
    </div>
</section>

<section class="insta-section">
    <div class="container-app conainer-insta">
        <div class="insta_title">Есть в Инстаграм? Мы тоже!</div>
        <p class="insta_text">Подпишитесь на нас, и мы покажем вам новые товары, поделимся забавными историями и предоставим скидку 5 % на каждый заказ!</p>
        <a href="https://www.instagram.com/prokat_tiktak.by?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" class="insta_button">ПОДПИШИТЕСЬ</a>
        <a href="https://www.instagram.com/prokat_tiktak.by?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" class="insta_a_pic">
            <img class="insta-photo" src="/public/rent/images/kolybeli/chicco_stulchik_babyhug_4in1/chicco-baby-hug-air-2.jpg">
            <img class="insta-photo" src="/public/rent/images/prokat-platiev/prokat_pure_soul_classic/dress-pure-soul-classic.jpg">
            <img class="insta-photo" src="/public/rent/images/shezlongi/babybjorn_babysitter_balance/babybjorn-1.jpg">
            <img class="insta-photo" src="/public/rent/images/hobot-388-ultrasonic/hobot-388-ultrasonic/hobot-388-ultrasonic-1.jpg">
            <img class="insta-photo" src="https://tiktak.by/public/rent/images/autokresla-lulki/simple_parenting_doona/doona-1.jpg">
            <img class="insta-photo" src="/public/jpg/insta-photo.jpg">
        </a>

        <img class="insta-back" src="/public/png/insta-back.png">
        <img class="insta-back2" src="/public/png/insta-back2.png">
    </div>
</section>

@endsection
