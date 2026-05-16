@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', 'Условия проката детских товаров TikTak')
@section('meta-description', 'Правила и условия проката: договор, залог, продление аренды, возврат товаров и отмена брони.')

@section('content')
    <style>
        .condition-block {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: transform 0.2s;
        }

        .condition-block:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .condition-title {
            color: #2c3e50;
            font-weight: bold;
            position: relative;
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .condition-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.3rem;
            width: 6px;
            height: 1.5rem;
            background-color: #3180D1;
            border-radius: 4px;
        }
    </style>

    <div class="container-app mb-5">
        <div class="row mt-4">
            @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>

        <div class="row mb-3 justify-content-center">
            <div class="col-12 col-lg-10">
                <h1 class="about__h1 font-weight-bold" style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                    Условия проката</h1>
                <p class="text-muted mb-0" style="font-size: 1.1rem;">
                    Ознакомьтесь с правилами оформления договора, продления и возврата. Использование сервиса TikTak
                    построено на взаимном доверии и бережном отношении к имуществу.
                </p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <!-- Заключение Договора -->
                <div class="condition-block">
                    <h3 class="condition-title h4">Заключение Договора и Передача</h3>
                    <p class="text-muted mb-3">
                        Договор проката заключается в письменной форме. Для оформления необходим только <strong>паспорт
                            гражданина Республики Беларусь или ВНЖ в РБ</strong>. С иностранными гражданами договор
                        заключается под денежный залог.
                    </p>
                    <p class="text-muted mb-0">
                        Сотрудник проката проверяет исправность и комплектность имущества в вашем присутствии. После
                        подписания договора претензии к переданному имуществу не принимаются.
                    </p>
                </div>

                <!-- Продление аренды -->
                <div class="condition-block" style="background-color: #f8fdff; border: 1px solid #e3f2fd;">
                    <h3 class="condition-title h4" style="color: #4CAF50;">Продление аренды</h3>
                    <p class="text-muted mb-4" style="font-size: 1.1rem;">
                        Вы имеете исключительное право на продление проката (для всех товаров, кроме карнавальных костюмов).
                        Звонить в салон заранее не нужно!
                    </p>
                    <div class="d-flex align-items-center flex-wrap">
                        <p class="text-muted mb-0 me-3 mr-3">
                            Достаточно перечислить оплату за новый срок не позднее <strong>3-х рабочих дней</strong>.
                        </p>
                        <a href="/ru/payment" class="btn text-white mt-2 mt-md-0"
                            style="background-color: #4CAF50; border-radius: 20px; padding: 10px 25px; font-weight: bold;">Смотреть
                            способы оплаты</a>
                    </div>
                </div>

                <!-- Возврат товара -->
                <div class="condition-block">
                    <style>
                        .condition-block.red .condition-title::before {
                            background-color: #E63625;
                        }
                    </style>
                    <div class="condition-block red p-0 m-0 shadow-none border-0 box-shadow-none"
                        style="background:transparent;">
                        <h3 class="condition-title h4">Возврат товара</h3>
                    </div>
                    <p class="text-muted mb-3">
                        Товары можно вернуть самостоятельно в наш офис — <strong>Литературная 22</strong>.
                    </p>
                    <ul class="text-muted mb-0">
                        <li class="mb-2">Товар должен быть в том же состоянии, в котором вы его получили (чистым!).</li>
                        <li class="mb-2"><strong>Комплектация:</strong> Наличие всех комплектующих обязательно. Возврат
                            некомплекта оценивается как полная порча имущества, пока вы не доукомплектуете его.</li>
                        <li>Обязательно подписывается <strong>Акт приемки</strong>. Без подписанного акта договор закрытым
                            не считается.</li>
                    </ul>
                </div>

                <!-- Карнавальные костюмы -->
                <div class="condition-block mt-5 text-center px-4"
                    style="background: linear-gradient(135deg, #f3e5f5, #fff); border: 1px solid #e1bee7;">
                    <h2 class="font-weight-bold mb-3" style="color: #9C27B0; font-size: 1.8rem;">🎭 Карнавальные костюмы
                    </h2>
                    <p class="text-muted mb-0">Правила бронирования, отмены и залога карнавальных костюмов отличаются от
                        базовых товаров. Из-за плотного графика новогодних праздников просим соблюдать следующие условия.
                    </p>
                </div>

                <div class="row g-4" style="margin-left: -15px; margin-right: -15px;">
                    <!-- 1. Бронирование и оплата -->
                    <div class="col-12 mb-4">
                        <div class="condition-block p-4 shadow-sm"
                            style="border-left: 4px solid #9C27B0; background-color: #fafafa;">
                            <h4 class="font-weight-bold mb-3" style="font-size: 1.1rem; color: #555;">1. Бронирование и
                                Оплата</h4>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0.8rem;">
                                Бронирование осуществляется онлайн 24/7 (за 10-15 дней до даты торжества). Все позиции с
                                фото и размерами на сайте абсолютно реальны и актуальны.
                            </p>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0.8rem;">
                                Забронировать костюм можно и по телефону, однако с учётом высочайшей загрузки сотрудников в
                                новогодний
                                период, убедительно просим вас в декабре пользоваться строго <strong>системой
                                    онлайн-бронирования
                                    24/7</strong>. Наш менеджер обязательно перезвонит вам для подтверждения брони.
                            </p>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0.8rem;">
                                Программа бронирования учитывает не только дату, но и <strong>время торжества</strong>
                                (например, на 9 утра костюм может быть еще занят, а к 18:00 уже доступен к прокату).
                                Пожалуйста, трезво
                                оценивайте свои возможности забрать и вернуть костюм вовремя! Все костюмы проходят
                                обязательную гигиеническую обработку, стирку и химчистку — это требует времени. <strong>Не
                                    подводите других клиентов</strong>, которые забронировали костюм после вас.
                            </p>
                            <div class="p-3 mb-2 rounded"
                                style="background-color: #fff3cd; color: #856404; font-size: 0.90rem; border: 1px solid #ffeeba;">
                                <strong>ВНИМАНИЕ!</strong> Если вы забронировали костюм более, чем за 7 дней до даты
                                праздника, вам необходимо <strong>в течение 3-х календарных дней внести арендную
                                    плату</strong> за прокат. При отсутствии оплаты бронь автоматически аннулируется без
                                уведомления
                                арендатора.
                            </div>
                        </div>
                    </div>

                    <!-- 2. Выдача и Залог -->
                    <div class="col-12 col-md-6 mb-4">
                        <div class="condition-block h-100 p-4 shadow-sm" style="background-color: #fafafa;">
                            <h4 class="font-weight-bold mb-3" style="font-size: 1.1rem; color: #555;">2. Выдача, Залог и
                                Продление</h4>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0.8rem;">Вносится
                                <strong>обеспечительный
                                    залог</strong> при выдаче (возвращается в 100% размере при своевременном возврате целого
                                костюма).
                            </p>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0;">Продление аренды костюма
                                возможно только с согласия менеджера, если он не забронирован
                                другим ребенком на это же время.</p>
                        </div>
                    </div>

                    <!-- 3. Риски и Форс-мажоры -->
                    <div class="col-12 col-md-6 mb-4">
                        <div class="condition-block h-100 p-4 shadow-sm"
                            style="border-left: 4px solid #E63625; background-color: #fafafa;">
                            <h4 class="font-weight-bold mb-3" style="font-size: 1.1rem; color: #555;">3. Гарантия брони и
                                форс-мажоры</h4>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0.8rem;">
                                Заранее забронированный костюм — это гарантия того, что мы не сдадим его на вашу дату.
                                Однако, он не изымается из проката до вашей аренды.
                            </p>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0;">
                                В случае, если предыдущий клиент утратил или безвозвратно испортил его, мы незамедлительно
                                сообщим вам,
                                <strong>полностью вернем оплату и предоставим любой другой свободный костюм напрокат
                                    абсолютно бесплатно.</strong>
                            </p>
                        </div>
                    </div>

                    <!-- 4. Примерка -->
                    <div class="col-12 col-md-6 mb-4">
                        <div class="condition-block h-100 p-4 shadow-sm" style="background-color: #fafafa;">
                            <h4 class="font-weight-bold mb-3" style="font-size: 1.1rem; color: #555;">4. Примерка 👑</h4>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0.8rem;">
                                Примерка осуществляется строго <strong>по предварительной записи</strong> (для записи просим
                                использовать мессенджер Viber <a href="viber://chat?number=%2B375297454040">+375 29 745 40
                                    40</a>).
                            </p>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0.8rem;">
                                В период новогодних праздников к примерке доступны только те костюмы, которые на момент
                                примерки не находятся в прокате у других клиентов (уточняйте наличие в Viber - отвечаем
                                быстро).
                            </p>
                            <p class="text-muted" style="font-size: 0.95rem; margin-bottom: 0;">
                                Просим бронировать <strong>не более 2-х костюмов</strong> к примерке, даже если вы
                                планируете посмотреть больше. Но вы можете сообщить менеджеру, какие еще костюмы вам
                                интересны.
                            </p>
                        </div>
                    </div>

                    <!-- 4. Отмена -->
                    <div class="col-12 col-md-6 mb-4">
                        <div class="condition-block h-100 p-4 shadow-sm" style="background-color: #fafafa;">
                            <h4 class="font-weight-bold mb-3" style="font-size: 1.1rem; color: #555;">4. Отмена брони</h4>
                            <p class="text-muted mb-2" style="font-size: 0.95rem;">При отказе от костюма возвращается часть
                                предоплаты:</p>
                            <ul class="text-muted mb-0" style="font-size: 0.95rem;">
                                <li>За <strong>10 и более суток</strong> — возврат 80%</li>
                                <li>В интервале от <strong>5 до 10 суток</strong> — возврат 50%</li>
                                <li>Менее <strong>5 суток</strong> — возврат 20% (менее 3 дней - оплата сгорает)</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection