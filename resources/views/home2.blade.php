@extends('layouts.app2')

@section('page-title', 'Прокат детских товаров Минск')


@section('content')
{{--    @include('includes.leftmenu_home')--}}
    <!--<div class="col-12">
        <div class="alert-warning text-center">Сайт находится в разработке.<br> The site is under construction.</div>
    </div>-->

    @include('includes.carousel')
{{--    @include('includes.carousel_mob')--}}

    <!-- main content -->
<div class="row">
   <div class="col pt-4 text-justify main-page-text">


       <div class="row">
           <div class="col-12">
               <div class="title" style="" id="about">
                   <span id="onas">О НАС</span>
               </div>



               <h3 class="text-center cat-header" ></h3>


               <p><strong>Прокат детских товаров TikTak</strong> работает на рынке услуг более 12 лет - ранее в Республике Беларусь, а теперь и в Литве. За это время мы смогли подобрать ассортимент,
                   максимально удовлетворяющий потребностям молодой семьи.</p>



               <p><strong>Философия нашего сервиса проста</strong> – облегчить родителям заботу о ребенке. Прокат детских товаров TikTak – надежный помощник, который делает
                   родительство осознанным, гармоничным, позволяет экономить денежные средства и места в ваших квартирах - мы предлагает товары, которые
                   выгоднее взять напрокат, чем купить.</p>

               <p> У нас Вы найдете для своих малышей детские товары отличного качества в прекрасном состоянии,
                   которые нет смысла покупать, но в которых существует острая необходимость на непродолжительный период времени.</p>



               <p>В нашем ассортименте только первокласснымие бренды, а это означает 100% продуманность, безопасность и функциональность в каждой
                   детали. В ассортименте как проверенные годами, так и инновационные продукты.</p>



               <p><strong>Прокат товаров для детей TikTak предлагает:</strong>
                   технологичные коляски – 2 в 1, 3 в 1 и легкие прогулочные варианты;
                   мебель для детской, среди которой незаменимые приставные кроватки, манежи и стульчики-трансформеры c рождения;
                   безопасные автокресла для разных возрастных категорий;
                   игрушки для развития речи, моторики, внимательности, усидчивости, цвето-, формо- и звуковосприятия;
                   детские весы, молокоотсосы, ингаляторы, радио и видеоняни;
                   слинги, нагрудные сумки, гамаки и манежи;
                   товары для спорта и активных игр на улице: беговелы, самокаты, батуты, горки и проч.;
                   карнавальные костюмы.</p>


               <p><strong>Выбирая прокат детских товаров TikTak вы получаете:</strong>

                   продукцию лучших мировых брендов в идеальном состоянии, возможность пробовать новинки мировой индустрии детских товаров,
                   экономию семейного бюджета, быструю доставку по Минску, официальный договор с гарантией возврата средств при досрочном возврате товара, подробную консультацию при
                   выборе из каталога, где заботливые родители найдут товары для детей от рождения и до 5-7 лет.
               </p>




               <p>Мы стремимся, чтобы наши товары приносили радость вашим детям и способствовали их умственному и физическому развитию - поэтому для нас чрезвычайно важны отзывы и пожелания родителей
                   - именно на них мы ориентируемся при формировании нашего ассортимента.
                   Заказать товары можно через наш сайт 24/7, связать с нами по телефону можно с 9 утра до 9 вечера ежедневно.</p><p>
                   <strong>Благодарим за доверие и обратную связь.</strong></p>

           </div>
       </div>

       <div class="row" style="background-color: #efece7">
           <div class="col-12">
               <div class="title" style="" id="about">
                   <span> НОВИНКИ НАШЕГО ПРОКАТА</span>
               </div>
           </div>

           <div class="col-12 col-sm-6 col-lg-3">
               <div class="card border-0 mx-auto h-100" style="max-width: 18rem; background-color: transparent;">
                   <img class="card-img-top img-fluid rounded-circle" src="/public/img/chicco_baby_hug_air_4_in_1.jpeg" alt="Card image cap">
                   <div class="card-body text-center">
                       <h5 class="card-title">Chicco <br />Baby Hug Air</h5>
                       <p class="card-text"> 4 в 1: колыбель, шезлонг, стульчик для кормления,
					   первый стул для еды за столом. Сопровождает малыша на каждом этапе его роста и развития.
					   </p>
                       <div class="home-cards"><a href="#" class="btn btn-success ">Подробнее</a></div>
                   </div>
               </div>
           </div>
           <div class="col-12 col-sm-6 col-lg-3">
               <div class="card border-0 mx-auto h-100" style="max-width: 18rem; background-color: transparent;">
                   <img class="card-img-top img-fluid rounded-circle" src="/public/img/omron_duobaby_ne_c301_e.jpeg" alt="Card image cap">
                   <div class="card-body text-center">
                       <h5 class="card-title">Ингалятор Omron DuoBaby <br />с назальным аспиратором</h5>
                       <p class="card-text">служит для выполнения аэрозольной терапии при заболеваниях респираторного тракта и удаления слизи из
					   носовой полости для облегчения дыхания</p>
                       <a href="#" class="btn btn-success ">Подробнее</a>
                   </div>
               </div>
           </div>
           <div class="col-12 col-sm-6 col-lg-3">
               <div class="card border-0 mx-auto h-100" style="max-width: 18rem; background-color: transparent;">
                   <img class="card-img-top img-fluid rounded-circle" src="/public/img/fisher_price_book.jpg.jpeg" alt="Card image cap">
                   <div class="card-body text-center">
                       <h5 class="card-title">Большая обучающая книга <br />Fisher Price</h5>
                       <p class="card-text">трансформируется из сборника рассказов в тактильно-цветовой модуль с различными активностями</p>
                       <div class="home-cards"><a href="#" class="btn btn-success ">Подробнее</a></div>
                   </div>
               </div>
           </div>
           <div class="col-12 col-sm-6 col-lg-3">
               <div class="card border-0 mx-auto h-100" style="max-width: 18rem; background-color: transparent;">
                   <img class="card-img-top img-fluid rounded-circle" src="/public/img/autokreslo.jpeg" alt="Card image cap">
                   <div class="card-body text-center">
                       <h5 class="card-title">Card title</h5>
                       <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
                       <div class="home-cards"><a href="#" class="btn btn-success ">Подробнее</a></div>
                   </div>
               </div>
           </div>
       </div>

       <div class="row">
           <div class="col-12 mt-5">
               <div class="title" style="" id="about">
                   <span id="delivery"> ДОСТАВКА</span>
               </div>



               <h3 class="text-center cat-header" ></h3>


               <p><strong>С радостью доставим выбранный вами товар в заранее согласованое время.</strong>

<p>Если Вы забирали товар из салона проката самостоятельно или оплатили только доставку товара, но в последствии решили, то Вам необходим выезд курьера,
чтобы его забрать - ПОЖАЛУЙСТА, ПРЕДУПРЕЖДАЙТЕ ОБ ЭТОМ ПРОКАТ ЗА 2 ДНЯ ДО СРОКА возврата товара,
в противном случае Вы обязаны вернуть товар в пункт проката самостоятельно.</p>


               <div class="row" style="background: var(--main-light-blue); border-radius: 10px; color: #fff; padding: 15px 20px;">
                   <div class="col col-md-4 col-md-offset-2" style="font-weight: 700; margin-top: 40px; font-size: 25px; margin-left: 30px; margin-right: 30px; text-align: center; opacity: 1; transform: scale(1);">
                       СТОИМОСТЬ ДОСТАВКИ:
                   </div>
                   <div class="col col-md-6" style="opacity: 1; transform: translateX(0px);">
                       <p style="color: #fff; font-size: 14px;"><img src="/public/img/galchka.png" alt="">3,00 € доставить / 3,00 €  забрать
</p>
                       <p style="color: #fff; font-size: 13px;"><img src="/public/img/galchka.png" alt="">Курьер выезжает на досрочные возвраты без возмещения клиенту денежной суммы за неиспользованный период проката.
					   Получить возврат арендной платы можно только при условии самостоятельно возврата товара в салон проката.</p>
                   </div>
               </div>

           </div>
       </div>

       <div class="row">
           <div class="col-12 mt-5">
               <div class="title" style="" id="about">
                   <span id="conditions"> УСЛОВИЯ ПРОКАТА</span>
               </div>

               <div class="row">

                   <div class="col-md-4" style="background: rgb(236, 236, 230); border-radius: 10px; padding: 25px; margin: 10px; height: 226px; opacity: 1; transform: scale(1);">
                       <div class="icon">
                           <img src="/public/img/icon_2.1.png" style="float: left; margin-right: 10px;" alt="">
                           <h3 style="font-size: 22px;">ЗАКЛЮЧЕНИЕ ДОГОВОРА:</h3>
                       </div>

                       <ul>
                           <li style="color: #00b5bd;"><span style="color: #686766;">Отношения аренды между Клиентом и Прокатом Tiktak.by оформляются договором
						   проката в письменной форме.
</span></li>

                           <li style="color: #00b5bd;"><span style="color: #686766;">При наличии регистрации в Минске залог не требуется.</span></li>
                       </ul>
                   </div>
                   <div class="col-md-4 col-md-offset-4" style="background: rgb(236, 236, 230); border-radius: 10px; padding: 25px; margin: 10px; height: 226px; opacity: 1; transform: scale(1);">
                       <div class="icon">
                           <img src="/public/img/icon_2.1.png" style="float: left; margin-right: 10px;" alt="">
                           <h3 style="font-size: 22px;">ПРОДЛЕНИЕ:</h3>
                       </div>

                       <ul>

                           <li style="color: #00b5bd;"><span style="color: #686766;">Клиент имеет преимущественное право продления срока аренды товара.
</span></li>
                                                     <li style="color: #00b5bd;"><span style="color: #686766;">Необходимо уведомить салон
проката о продлении за 2 дня</span></li>
                       </ul>
                   </div>

                   <div class="col-md-4" style="background: rgb(236, 236, 230); border-radius: 10px; padding: 25px; margin: 10px; opacity: 1; transform: scale(1);">
                       <div class="icon">
                           <img src="/public/img/icon_2.1.png" style="float: left; margin-right: 10px;" alt="">
                           <h3 style="font-size: 22px;">ПЕРЕДАЧА ТОВАРА:</h3>
                       </div>

                       <ul>
                           <li style="color: #00b5bd;"><span style="color: #686766;">Сотрудник проката в присутствии
Клиента проверяет исправность, комплектность и внешний вид
имущества, а также знакомит
Клиента с правилами эксплуатации имущества.</span></li>

                           <li style="color: #00b5bd;"><span style="color: #686766;">После подписания договора проката претензии к переданному по договору проката имуществу не принимаются.</span></li>
                       </ul>
                   </div>
                   <div class="col-md-4" style="background: rgb(236, 236, 230); border-radius: 10px; padding: 25px 25px 45px; margin: 10px; opacity: 1; transform: scale(1);">
                       <div class="icon">
                           <img src="/public/img/icon_2.1.png" style="float: left; margin-right: 10px;" alt="">
                           <h3 style="font-size: 22px; margin-bottom: 35px;">ВОЗВРАТ ТОВАРА:<br></h3>
                       </div>

                       <ul>
                           <li style="color: #00b5bd;"><span style="color: #686766;">Клиент обязан вернуть взятый напрокат товар в том же состоянии,
в котором он его получил в пункте проката в момент выдачи. Наличие всех комплектующих обязательно.
</span></li>
                                                     <li style="color: #00b5bd;"><span style="color: #686766;">При возврате товара, с Клиентом в обязательном порядке подписывается Акт приемки, подтверждающий
						   сдачу товара в пункт проката.</span></li>
                       </ul>
                   </div>


               </div>

           </div>
       </div>

   </div>
</div>

@endsection
