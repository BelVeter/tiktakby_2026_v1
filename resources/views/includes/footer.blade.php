@php
    {{
    /** @var \App\MyClasses\Header */
    $header = new \App\MyClasses\Header(request()->lang);
    }}
@endphp
<footer class="footer">
    <div class="container-app footer-container">
        <img class="footer-back-img" src="/public/png/footer-back.png">
        <div class="row footer-row-1 justify-content-center">
            <div class="col-12 col-sm-6 col-md-4 col footer-logo-col text-center text-md-start">
                <a href="/{{$header->getLang()}}/"><img class="footer-logo-img" src="/public/svg/logo-footer.svg"
                        alt="Tiktak.lt logo"></a>
            </div>
            <div
                class="col-12 col-sm-6 col-md-4 footer-map-coll d-flex align-items-center justify-content-center justify-content-md-start mt-3 mt-md-0">
                <a href="#" class="footer-map"><img
                        src="/public/svg/footer-map.svg"></img>{{$header->translate('Минск')}}</a>
            </div>
            <form
                class="col-12 col-sm-6 col-md-4 subscribe-form justify-content-center justify-content-md-start mt-3 mt-md-0"
                method="post" action="/subscribe">
                @csrf
                <label class="text-center text-md-start"
                    for="subscribe-email">{{$header->translate('Подпишись на наши новости')}}:</label>
                <div class="footer-line justify-content-center justify-content-md-start">
                    <input class="footer-email" type="email" name="email" placeholder="Email"
                        id="subscribe-email"><button type="submit" class="footer-email-btn"
                        onclick="return subscriptionCheck();">SUBSCRIBE</button>
                    <script>
                        function subscriptionCheck() {
                            let rez = true;
                            let field = document.querySelector('.footer-email');
                            if (field.value == '') {
                                rez = false;
                                alert('{{$header->translate('Укажите свой')}} e-mail');
                            }


                            return rez;
                        }
                    </script>
                </div>
            </form>
        </div>
        <div class="row footer-row-2 justify-content-center">
            <div class="col-12 col-sm-6 col-md-4 d-flex justify-content-center justify-content-md-start">
                <ul class="footer-column ">
                    <li>On-line {{$header->translate('бронирование')}} 24/7</li>
                    <li><a href="tel:7454040">Тел. 745-40-40</a></li>
                    <li>{{$header->translate('Часы работы')}} 10.00 - 19.00<br>
                        Литературная 22: сб, вс: 10.00 - 15.00<br>
                        Ложинская 5: сб, вс - выходной
                    </li>
                </ul>
            </div>
            <div class="col-12 col-sm-6 col-md-4 footer-col-2 d-flex">
                <ul class="footer-column">
                    <li><a
                            href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/about">{{$header->translate('О компании')}}</a>
                    </li>
                    <li><a
                            href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/conditions">{{$header->translate('Условия проката')}}</a>
                    </li>
                    <li><a
                            href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/delivery">{{$header->translate('Доставка')}}</a>
                    </li>
                    <li><a
                            href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/payment">{{$header->translate('Оплата')}}</a>
                    </li>
                    <li><a href="/ru/contacts">{{$header->translate('Контакты')}}</a></li>
                </ul>
            </div>
            <div class="col-12 col-sm-6 col-md-4 d-flex">
                <ul class="footer-column last">
                    <li><a href="#">{{$header->translate('Публичная оферта')}}</a> </li>
                    <li><a
                            href="/{{app('request')->lang ? app('request')->lang : 'ru'}}/policy">{{$header->translate('Политика обработки персональных данных')}}</a>
                    </li>
                    <li><a href="#">{{$header->translate('Cоглашение на получение рассылки')}}</a> </li>
                </ul>
            </div>
        </div>
        <div class="row footer-row-3 justify-content-center">
            <div class="col-12 col-sm-6 col-md-4">TikTak &copy; {{date("Y")}}
                {{$header->translate('Все права защищены')}}</div>
            <div class="col-12 col-sm-6 col-md-4 footer-col-2"><span>{{$header->translate('Сервис проката')}}</span>
            </div>
            <div class="col-12 col-sm-6 col-md-4 d-flex flex-row justify-content-between">
                <span>{{$header->translate('Разработка сайта')}}</span>
                <div class="webit-loggo-container">
                    <img class="webit-loggo-img" src="/public/svg/webitconnection-logo.svg">
                    <div class="webit-loggo-text">
                        <span class="logo-text1">Web IT</span>
                        <span class="logo-text2">connection</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</footer>