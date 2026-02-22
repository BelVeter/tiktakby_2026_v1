@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', 'Условия доставки в Минске | Прокат TikTak')
@section('meta-description', 'Быстрая доставка детских товаров напрокат по Минску и району. Самовывоз из пунктов выдачи. Условия и тарифы.')

@section('content')
    <style>
        .info-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: none;
            height: 100%;
            transition: transform 0.2s;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .price-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            border-left: 4px solid #3180D1;
        }

        .check-list {
            list-style: none;
            padding-left: 0;
        }

        .check-list li {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 0.8rem;
        }

        .check-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            top: 0;
            color: #4CAF50;
            font-weight: bold;
            font-size: 1.2rem;
        }
    </style>

    <div class="container-app mb-5">
        <div class="row mt-4">
            @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>

        <div class="row mb-5">
            <div class="col-12 text-center">
                <h1 class="about__h1 font-weight-bold" style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                    Условия доставки</h1>
                <p class="text-muted" style="font-size: 1.1rem; max-width: 800px; margin: 0 auto;">
                    Мы с радостью доставим ваш заказ по Минску и Минскому району (до 35 кг). <br>Доставка — это отдельная
                    услуга, оформляется по желанию.
                </p>
            </div>
        </div>

        <div class="row g-4 mb-5" style="margin-left: -15px; margin-right: -15px;">
            <div class="col-12 col-lg-7 mb-4">
                <div class="card info-card p-4">
                    <div class="card-body">
                        <h3 class="h4 font-weight-bold mb-4 d-flex align-items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-truck me-3" style="color: #2196F3; margin-right: 10px;">
                                <rect x="1" y="3" width="15" height="13"></rect>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                <circle cx="18.5" cy="18.5" r="2.5"></circle>
                            </svg>
                            Как работает доставка
                        </h3>
                        <ul class="check-list text-muted">
                            <li>Товары доставляются с <strong>понедельника по субботу</strong> включительно.</li>
                            <li>Время доставки определяется согласно логистическому маршруту во временном интервале
                                (например: с 10:00 до 14:00).</li>
                            <li>Курьер <strong>предупреждает о визите за 20-30 минут</strong>. Если клиент не отвечает —
                                доставка отменяется.</li>
                            <li>Курьер вместе с Арендатором вскрывает упаковку и проверяет комплектность товара.</li>
                            <li>Вы можете запросить <strong>фото и видео обзор</strong> товара перед доставкой, чтобы
                                убедиться в его отличном состоянии и работоспособности. Мы с радостью пришлем их вам!</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5 mb-4">
                <div class="card info-card p-4" style="background-color: #f8fdff;">
                    <div class="card-body">
                        <h3 class="h4 font-weight-bold mb-4 d-flex align-items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-credit-card me-3" style="color: #4CAF50; margin-right: 10px;">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                <line x1="1" y1="10" x2="23" y2="10"></line>
                            </svg>
                            Стоимость доставки
                        </h3>

                        <div class="price-box mb-3">
                            <div class="mb-3">
                                <span class="d-block font-weight-bold" style="color: #2c3e50; font-size: 1.05rem;">Доставка
                                    по Минску</span>
                                <div class="d-flex justify-content-between align-items-center mt-2 mb-1">
                                    <span>При сумме от 30 руб</span>
                                    <strong style="color: #4CAF50;">Бесплатно</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center text-muted"
                                    style="font-size: 0.9rem;">
                                    <span>При сумме до 30 руб</span>
                                    <strong>10 руб</strong>
                                </div>
                            </div>

                            <hr class="my-3" style="border-top: 1px solid #dee2e6;">

                            <div class="mb-3">
                                <span class="d-block font-weight-bold" style="color: #2c3e50; font-size: 1.05rem;">Ближний
                                    пригород</span>
                                <p class="text-muted mb-2 mt-1" style="font-size: 0.85rem; line-height: 1.3;">
                                    Боровляны, Колодищи, Б. Тростенец, Гатово, Сеница, Богатырево, Ждановичи, Ратомка,
                                    Тарасово
                                </p>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>При сумме от 50 руб</span>
                                    <strong style="color: #4CAF50;">Бесплатно</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center text-muted"
                                    style="font-size: 0.9rem;">
                                    <span>При сумме до 50 руб</span>
                                    <strong>10 руб</strong>
                                </div>
                            </div>

                            <hr class="my-3" style="border-top: 1px solid #dee2e6;">

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center text-danger">
                                    <span class="font-weight-bold" style="font-size: 1.05rem;">Забор товара курьером</span>
                                    <strong style="font-size: 1.1rem;">10 руб</strong>
                                </div>
                                <div class="text-muted mt-1" style="font-size: 0.85rem;">* Услуга забора товара всегда
                                    платная, независимо от суммы заказа</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5" style="margin-left: -15px; margin-right: -15px;">
            <div class="col-12 col-md-6 mb-4">
                <div class="card info-card p-4 h-100">
                    <div class="card-body text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="feather feather-map-pin mb-3" style="color: #FF9800;">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <h4 class="font-weight-bold mb-3">Самовывоз и Яндекс.Доставка</h4>
                        <p class="text-muted">
                            Вы можете воспользоваться самовывозом из наших пунктов выдачи (Ложинская 5, Литературная 22)
                            абсолютно бесплатно. Также приветствуется использование <strong>Яндекс.Доставки</strong> для
                            максимальной скорости!
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 mb-4">
                <div class="card info-card p-4 h-100" style="border-left: 4px solid #E63625; border-radius: 8px;">
                    <div class="card-body">
                        <h4 class="font-weight-bold mb-3 d-flex align-items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-refresh-ccw me-2" style="color: #E63625; margin-right: 10px;">
                                <polyline points="1 4 1 10 7 10"></polyline>
                                <polyline points="23 20 23 14 17 14"></polyline>
                                <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                            </svg>
                            Возврат и отмена
                        </h4>
                        <p class="text-muted mb-2">
                            Обязаны вернуть товар <strong>самостоятельно</strong>, если услуга возврата курьером не была
                            заказана заранее за 2 дня.
                        </p>
                        <p class="text-muted mb-0">
                            При досрочном возврате курьер выезжает без возмещения денежной суммы за неиспользованный период
                            проката. Возврат арендной платы возможен только при самостоятельном возврате в офис.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection