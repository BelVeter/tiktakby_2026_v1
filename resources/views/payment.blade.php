@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', 'Оплата | Прокат TikTak')
@section('meta-description', 'Способы оплаты в прокате детских товаров TikTak: наличный расчет, банковские карты, безналичный расчет и ЕРИП.')

@section('content')
    <style>
        .payment-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: none;
            height: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .payment-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .payment-icon.cash {
            color: #4CAF50;
        }

        .payment-icon.card-icon {
            color: #2196F3;
        }

        .payment-icon.bank {
            color: #FF9800;
        }

        .payment-icon.erip {
            color: #9C27B0;
        }

        .erip-steps {
            padding-left: 0;
            list-style-type: none;
            counter-reset: erip-counter;
        }

        .erip-steps li {
            position: relative;
            padding-left: 2.5rem;
            margin-bottom: 1rem;
        }

        .erip-steps li::before {
            counter-increment: erip-counter;
            content: counter(erip-counter);
            position: absolute;
            left: 0;
            top: 0;
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 50%;
            background-color: #f3f4f6;
            color: #9C27B0;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .bank-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            border-left: 4px solid #FF9800;
        }

        .text-highlight {
            font-weight: bold;
            color: #333;
        }
    </style>

    <div class="container-app mb-5">
        <div class="row mt-4">
            @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>

        <div class="row mb-3">
            <div class="col-12">
                <h1 class="about__h1 font-weight-bold" style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                    Способы оплаты</h1>
                <p class="text-muted mb-0" style="font-size: 1.1rem;">
                    Мы заботимся о вашем удобстве и предлагаем несколько интерактивных вариантов оплаты аренды детских
                    товаров. Выберите тот, который подходит именно вам.
                </p>
            </div>
        </div>

        <div class="row g-4 mb-5" style="margin-left: -15px; margin-right: -15px;">
            <div class="col-12 col-md-6 mb-4">
                <div class="card payment-card p-4">
                    <div class="card-body">
                        <div class="payment-icon cash">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-dollar-sign">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <h3 class="h4 font-weight-bold mb-3">Наличный расчет</h3>
                        <p class="text-muted">
                            Оплата наличными курьеру при получении товара или менеджеру в пункте самовывоза после проверки
                            комплектации и подписания договора проката.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 mb-4">
                <div class="card payment-card p-4">
                    <div class="card-body">
                        <div class="payment-icon card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-credit-card">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                <line x1="1" y1="10" x2="23" y2="10"></line>
                            </svg>
                        </div>
                        <h3 class="h4 font-weight-bold mb-3">Оплата банковской картой</h3>
                        <p class="text-muted">
                            Оплата картой через терминал при получении товара в наших пунктах самовывоза. К оплате
                            принимаются все основные типы банковских карт.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5" style="margin-left: -15px; margin-right: -15px;">
            <div class="col-12 col-lg-6 mb-4">
                <div class="payment-card card p-4 h-100">
                    <div class="card-body">
                        <div class="payment-icon erip mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-smartphone">
                                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                                <line x1="12" y1="18" x2="12.01" y2="18"></line>
                            </svg>
                        </div>
                        <h3 class="h4 font-weight-bold mb-4">Оплата через ЕРИП (E-POS)</h3>
                        <p class="text-muted mb-4">Удобный способ для продления аренды без визита в офис:</p>
                        <ol class="erip-steps">
                            <li>В перечне услуг ЕРИП выберите: <strong>Сервис E-POS → E-POS - оплата товаров и
                                    услуг.</strong></li>
                            <li>В поле «Лицевой счет» введите <strong
                                    style="color: #9C27B0; font-size: 1.1em;">33973-1-01</strong>.</li>
                            <li>Укажите <strong>ФИО арендатора</strong>.</li>
                            <li>Введите сумму платежа и нажмите «Оплатить».</li>
                        </ol>
                        <div class="alert alert-info mt-4"
                            style="background-color: #f3e5f5; color: #6a1b9a; border-color: #e1bee7;" role="alert">
                            <strong>Важно:</strong> Сохраняйте электронную квитанцию об оплате до конца срока действия
                            договора проката!
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6 mb-4">
                <div class="payment-card card p-4 h-100">
                    <div class="card-body">
                        <div class="payment-icon bank mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-file-text">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <h3 class="h4 font-weight-bold mb-4">Безналичный расчет</h3>
                        <p class="text-muted mb-4">Оплата произвольным платежом через любой банк или интернет-банкинг:</p>

                        <div class="bank-details">
                            <div class="mb-2"><span class="text-highlight">Получатель:</span> ЧУП Куюмджи</div>
                            <div class="mb-2"><span class="text-highlight">УНП:</span> 193137666</div>
                            <div class="mb-2"><span class="text-highlight">р/с (IBAN):</span> BY80BPSB30123070280179330000
                                <br>в ОАО "СБЕРБАНК" г.Минск, BIC: BPSBBY2X
                            </div>
                            <div class="mb-2"><span class="text-highlight">Назначение:</span> За услуги проката от (ФИО
                                арендатора)</div>
                            <div class="mb-0"><span class="text-highlight">Код платежа:</span> 23501 <span
                                    class="text-muted" style="font-size: 0.9em;">(если не проходит - удалите код)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection