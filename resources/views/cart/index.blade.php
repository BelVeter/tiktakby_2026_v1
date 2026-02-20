@extends('layouts.app')

@section('page-title', 'Корзина — TikTak')
@section('meta-description', 'Ваша корзина заказов на tiktak.by — прокат детских товаров в Минске')

@section('content')
    <div class="container-app cart-page" style="padding: 20px 15px; min-height: 60vh;">
        <h1 class="cart-page__title">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3180D1" stroke-width="2"
                style="vertical-align: middle; margin-right: 8px;">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            Корзина
        </h1>

        {{-- Loading state --}}
        <div id="cart-loading" class="cart-page__state">
            <div class="cart-page__spinner"></div>
            <p class="cart-page__state-text">Загрузка корзины...</p>
        </div>

        {{-- Empty state --}}
        <div id="cart-empty" class="cart-page__state" style="display: none;">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#C1D9F3" stroke-width="1.5"
                style="margin-bottom: 20px;">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <p class="cart-page__state-title">Корзина пуста</p>
            <p class="cart-page__state-subtitle">Добавляйте товары из каталога, чтобы оформить заказ</p>
            <a href="/ru/" class="cart-page__btn-catalog">Перейти в каталог</a>
        </div>

        {{-- Cart content --}}
        <div id="cart-content" style="display: none;">

            {{-- Desktop table (hidden on mobile) --}}
            <div class="cart-table-wrapper d-none d-md-block">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th class="cart-table__th-photo">Фото</th>
                            <th class="cart-table__th-name">Наименование</th>
                            <th class="cart-table__th-date">Начало</th>
                            <th class="cart-table__th-date">Окончание</th>
                            <th class="cart-table__th-days">Суток</th>
                            <th class="cart-table__th-price">Стоимость</th>
                            <th class="cart-table__th-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="cart-table-body">
                        {{-- Filled by JS --}}
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards (hidden on desktop) --}}
            <div id="cart-mobile-cards" class="d-block d-md-none">
                {{-- Filled by JS --}}
            </div>

            {{-- Total --}}
            <div class="cart-total-block">
                <div class="cart-total-block__row">
                    <span class="cart-total-block__label">Итого к оплате:</span>
                    <span class="cart-total-block__value" id="cart-total-value">0,00 BYN</span>
                </div>
            </div>

            {{-- Promo / Gift Certificate --}}
            <div class="cart-promo-block">
                <div class="cart-promo-block__row">
                    <div class="cart-promo-block__field">
                        <label for="cart-promo-code">Промокод</label>
                        <input type="text" id="cart-promo-code" placeholder="Введите промокод" class="cart-input">
                    </div>
                    <div class="cart-promo-block__field">
                        <label for="cart-gift-cert">Подарочный сертификат</label>
                        <input type="text" id="cart-gift-cert" placeholder="Номер сертификата" class="cart-input">
                    </div>
                </div>
            </div>

            {{-- Checkout form --}}
            <div class="cart-checkout-form">
                <h2 class="cart-checkout-form__title">Оформление заказа</h2>

                <div class="cart-checkout-form__group">
                    <div class="form-floating">
                        <input type="text" class="form-control bg-white" id="cart-fio" placeholder="ФИО" required>
                        <label for="cart-fio">ФИО</label>
                        <div class="invalid-feedback">Укажите ФИО (не менее 3-х символов)</div>
                    </div>
                </div>

                <div class="cart-checkout-form__group">
                    <div class="form-floating">
                        <input type="text" class="form-control bg-white" id="cart-phone" placeholder="Телефон">
                        <label for="cart-phone">Телефон: +375 (00) 000-00-00</label>
                        <div class="invalid-feedback">Должно быть не менее 7-ми цифр</div>
                    </div>
                </div>

                <div class="cart-checkout-form__group">
                    <span class="cart-checkout-form__radio-label">Укажите способ доставки</span>
                    <div class="cart-checkout-form__radio-row">
                        <label class="cart-radio-label">
                            <input class="form-check-input" type="radio" name="cart_delivery" value="1">
                            <span>Доставка</span>
                        </label>
                        <label class="cart-radio-label">
                            <input class="form-check-input" type="radio" name="cart_delivery" value="0">
                            <span>Самовывоз</span>
                        </label>
                    </div>
                    <div class="invalid-feedback" id="cart-delivery-error" style="display: none;">Выберите способ доставки
                    </div>
                </div>

                {{-- Delivery address (shown when delivery selected) --}}
                <div class="cart-checkout-form__group" id="cart-address-group" style="display: none;">
                    <label for="cart-address">Адрес доставки:</label>
                    <textarea class="form-control form-control-lg" id="cart-address"
                        placeholder="Укажите адрес доставки"></textarea>
                    <div class="invalid-feedback">Заполните адрес доставки</div>
                </div>

                {{-- Self-pickup office (shown when self-pickup selected) --}}
                <div class="cart-checkout-form__group" id="cart-office-group" style="display: none;">
                    <p class="cart-checkout-form__office-loading">Загрузка доступных пунктов выдачи...</p>
                </div>

                <div class="cart-checkout-form__group">
                    <label for="cart-info">Дополнительная информация:</label>
                    <textarea class="form-control form-control-lg" id="cart-info" placeholder=""></textarea>
                </div>

                <div class="cart-checkout-form__actions">
                    <button type="button" id="cart-checkout-btn" class="cart-checkout-form__submit-btn">
                        Оформить заказ
                    </button>
                </div>
            </div>
        </div>

        {{-- Success state --}}
        <div id="cart-success" class="cart-page__state" style="display: none;">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="1.5"
                style="margin-bottom: 20px;">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <p class="cart-page__state-title" style="color: #4CAF50;">Заказ оформлен!</p>
            <p class="cart-page__state-subtitle" id="cart-success-message">Оператор свяжется с вами в ближайшее время.</p>
            <div id="cart-success-details"></div>
            <a href="/ru/" class="cart-page__btn-catalog" style="margin-top: 20px;">Вернуться в каталог</a>
        </div>
    </div>

    {{-- Unavailable popup modal --}}
    <div class="modal fade" id="cartUnavailableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Товар сейчас в прокате</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="cart-unavailable-text">
                        К сожалению, данный товар сейчас находится в прокате.
                    </p>
                    <p id="cart-unavailable-return-date"></p>
                    <p>Оставьте свои контактные данные, и мы сообщим вам, как только товар станет доступен!</p>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="cart-unavail-phone" placeholder="Телефон">
                        <label for="cart-unavail-phone">Телефон: +375 (00) 000-00-00</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" id="cart-unavail-submit" class="btn btn-primary"
                        style="background: #3180D1; border-color: #3180D1;">
                        Уведомить меня
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Cart warning modal (shown when user tries direct booking with items in cart) --}}
    <div class="modal fade" id="cartWarningModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">У вас есть товары в корзине</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>У вас уже есть <strong id="cart-warning-count">0</strong> товар(ов) в корзине.
                        Вы можете добавить этот товар в корзину и оформить всё одним заказом — это быстрее и удобнее.</p>
                </div>
                <div class="modal-footer" style="flex-wrap: wrap; gap: 8px;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                        id="cart-warning-continue">
                        Заказать только этот товар
                    </button>
                    <button type="button" class="btn btn-primary" id="cart-warning-add"
                        style="background: #3180D1; border-color: #3180D1;">
                        Добавить в корзину
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* ===== Cart Page Styles (Mobile-First) ===== */

        main {
            background-color: #F4F7FB;
            /* Light blue-grey background */

        }

        .cart-page__title {
            font-family: 'Nunito', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }

        .cart-page__state {
            text-align: center;
            padding: 50px 20px;
        }

        .cart-page__spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #e0e0e0;
            border-top-color: #3180D1;
            border-radius: 50%;
            animation: cart-spin 0.8s linear infinite;
        }

        @keyframes cart-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .cart-page__state-text {
            font-family: 'Nunito', sans-serif;
            font-size: 16px;
            color: #999;
            margin-top: 16px;
        }

        .cart-page__state-title {
            font-family: 'Nunito', sans-serif;
            font-size: 20px;
            color: #999;
            margin-bottom: 12px;
        }

        .cart-page__state-subtitle {
            font-family: 'Nunito', sans-serif;
            font-size: 16px;
            color: #bbb;
            margin-bottom: 24px;
        }

        .cart-page__btn-catalog {
            display: inline-block;
            padding: 12px 32px;
            background: #3180D1;
            color: #fff;
            border-radius: 8px;
            font-family: 'Nunito', sans-serif;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .cart-page__btn-catalog:hover {
            background: #2567b0;
            color: #fff;
            text-decoration: none;
        }

        /* ===== Mobile Cart Cards ===== */
        .cart-mobile-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            position: relative;
        }

        .cart-mobile-card__top {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .cart-mobile-card__img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .cart-mobile-card__name {
            font-family: 'Nunito', sans-serif;
            font-size: 15px;
            font-weight: 600;
            color: #333;
            text-decoration: none;
            line-height: 1.3;
            padding-right: 36px;
            /* Space for the remove button */
            display: block;
            /* Ensure padding works correctly */
        }

        .cart-mobile-card__name:hover {
            color: #3180D1;
        }

        .cart-mobile-card__remove {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            width: 32px;
            /* Keep touch target size */
            height: 32px;
            /* Keep touch target size */
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #92bae3;
            /* Paler blue */
            padding: 0;
            transition: all 0.2s;
            z-index: 10;
        }

        .cart-mobile-card__remove:hover {
            color: #E53935;
        }

        .cart-mobile-card__dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }

        .cart-mobile-card__date-group {
            display: flex;
            flex-direction: column;
        }

        .cart-mobile-card__date-label {
            font-family: 'Nunito', sans-serif;
            font-size: 12px;
            color: #999;
            margin-bottom: 4px;
        }

        .cart-mobile-card__date-input {
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #333;
            background: #f8f9fa;
            width: 100%;
        }

        .cart-mobile-card__days-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .cart-mobile-card__days-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cart-mobile-card__days-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: #f8f9fa;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            transition: all 0.2s;
        }

        .cart-mobile-card__days-btn:hover {
            background: #3180D1;
            color: #fff;
            border-color: #3180D1;
        }

        .cart-mobile-card__days-value {
            font-family: 'Nunito', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #333;
            min-width: 40px;
            text-align: center;
        }

        .cart-mobile-card__days-label {
            font-family: 'Nunito', sans-serif;
            font-size: 13px;
            color: #999;
        }

        .cart-mobile-card__price-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }

        .cart-mobile-card__total {
            font-family: 'Nunito', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #3180D1;
        }

        .cart-mobile-card__rate {
            font-family: 'Nunito', sans-serif;
            font-size: 12px;
            color: #999;
            margin-left: 8px;
            /* Add space between total and rate */
        }

        /* ===== Desktop Table ===== */
        .cart-table-wrapper {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Nunito', sans-serif;
        }

        .cart-table thead th {
            background: #f8f9fa;
            padding: 12px 10px;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #eee;
            white-space: nowrap;
        }

        .cart-table tbody td {
            padding: 16px 10px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 14px;
            color: #333;
        }

        .cart-table__img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            border-radius: 6px;
        }

        .cart-table__name-link {
            font-weight: 600;
            color: #333;
            text-decoration: none;
            transition: color 0.2s;
        }

        .cart-table__name-link:hover {
            color: #3180D1;
        }

        .cart-table__date-input {
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 140px;
            color: #333;
            background: #f8f9fa;
        }

        .cart-table__days-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cart-table__days-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: #f8f9fa;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            transition: all 0.2s;
        }

        .cart-table__days-btn:hover {
            background: #3180D1;
            color: #fff;
            border-color: #3180D1;
        }

        .cart-table__days-value {
            font-weight: 700;
            font-size: 16px;
            min-width: 30px;
            text-align: center;
        }

        .cart-table__price-total {
            font-weight: 700;
            font-size: 16px;
            color: #3180D1;
            white-space: nowrap;
        }

        .cart-table__price-rate {
            font-size: 12px;
            color: #999;
            display: block;
            margin-top: 2px;
        }

        .cart-table__remove-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #ccc;
            padding: 6px;
            transition: color 0.2s;
        }

        .cart-table__remove-btn:hover {
            color: #E53935;
        }

        /* ===== Total Block ===== */
        .cart-total-block {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .cart-total-block__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-total-block__label {
            font-family: 'Nunito', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .cart-total-block__value {
            font-family: 'Nunito', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #3180D1;
        }

        /* ===== Promo Block ===== */
        .cart-promo-block {
            margin-bottom: 24px;
        }

        .cart-promo-block__row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        @media (min-width: 768px) {
            .cart-promo-block__row {
                grid-template-columns: 1fr 1fr;
            }
        }

        .cart-promo-block__field label {
            font-family: 'Nunito', sans-serif;
            font-size: 13px;
            color: #999;
            display: block;
            margin-bottom: 4px;
        }

        .cart-input {
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 100%;
            color: #333;
            background: #f8f9fa;
            transition: border-color 0.2s;
        }

        .cart-input:focus {
            border-color: #3180D1;
            outline: none;
            background: #fff;
        }

        /* ===== Checkout Form ===== */
        .cart-checkout-form {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 24px 20px;
        }

        .cart-checkout-form__title {
            font-family: 'Nunito', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }

        .cart-checkout-form__group {
            margin-bottom: 16px;
        }

        .cart-checkout-form__group label {
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            color: #666;
            margin-bottom: 6px;
            display: block;
        }

        .cart-checkout-form__radio-label {
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            color: #666;
            display: block;
            margin-bottom: 10px;
        }

        .cart-checkout-form__radio-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .cart-radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Nunito', sans-serif;
            font-size: 15px;
            color: #333;
            flex: 1;
            transition: all 0.2s;
        }

        .cart-radio-label:has(input:checked) {
            border-color: #3180D1;
            background: #EBF3FC;
        }

        .cart-checkout-form__submit-btn {
            width: 100%;
            padding: 14px 24px;
            background: #3180D1;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Nunito', sans-serif;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .cart-checkout-form__submit-btn:hover {
            background: #2567b0;
        }

        .cart-checkout-form__submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .cart-checkout-form__actions {
            margin-top: 20px;
        }

        /* ===== Cart Badge ===== */
        .cart-badge {
            display: none;
            position: absolute;
            top: -6px;
            right: -8px;
            background: #E53935;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            padding: 0 4px;
        }

        /* ===== Add to Cart Button on L2 ===== */
        .l2-card_btn-cart {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 16px;
            margin-top: 8px;
            background: #fff;
            color: #3180D1;
            border: 2px solid #3180D1;
            border-radius: 10px;
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s;
            letter-spacing: 0.3px;
        }

        .l2-card_btn-cart:hover {
            background: #3180D1;
            color: #fff;
        }

        .l2-card_btn-cart .btn-icon {
            width: 20px;
            height: 20px;
        }

        /* ===== Toast notification ===== */
        .cart-toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #333;
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            font-weight: 600;
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
        }

        .cart-toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .cart-toast.success {
            background: #4CAF50;
        }

        .cart-toast.error {
            background: #E53935;
        }

        .cart-toast.warning {
            background: #FF9800;
        }

        /* ===== L3 Add to Cart button ===== */
        .action-button.cart-button {
            background: #fff;
            color: #3180D1;
            border: 2px solid #3180D1;
            transition: all 0.25s;
        }

        .action-button.cart-button:hover {
            background: #3180D1;
            color: #fff;
        }

        @media (min-width: 768px) {
            .cart-page__title {
                font-size: 28px;
                margin-bottom: 24px;
            }

            .cart-checkout-form {
                padding: 30px;
            }

            .cart-checkout-form__submit-btn {
                width: auto;
                min-width: 250px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var loadingEl = document.getElementById('cart-loading');
            var emptyEl = document.getElementById('cart-empty');
            var contentEl = document.getElementById('cart-content');
            var successEl = document.getElementById('cart-success');

            // Get cart from TiktakCart (defined in app.blade.php)
            if (typeof window.TiktakCart === 'undefined') {
                loadingEl.style.display = 'none';
                emptyEl.style.display = 'block';
                return;
            }

            var items = TiktakCart.getItems();

            if (items.length === 0) {
                loadingEl.style.display = 'none';
                emptyEl.style.display = 'block';
                return;
            }

            // Render cart items
            loadingEl.style.display = 'none';
            contentEl.style.display = 'block';
            renderCart(items);

            // Delivery radio toggle
            var deliveryRadios = document.querySelectorAll('[name="cart_delivery"]');
            deliveryRadios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    var addressGroup = document.getElementById('cart-address-group');
                    var officeGroup = document.getElementById('cart-office-group');
                    if (this.value === '1') {
                        addressGroup.style.display = 'block';
                        officeGroup.style.display = 'none';
                    } else {
                        addressGroup.style.display = 'none';
                        officeGroup.style.display = 'block';
                        // TODO: load offices via AJAX
                        officeGroup.innerHTML = '<p style="font-family: Nunito, sans-serif; font-size: 14px; color: #666;">Адрес самовывоза уточнит оператор при подтверждении заказа.</p>';
                    }
                });
            });

            // Checkout button
            document.getElementById('cart-checkout-btn').addEventListener('click', doCheckout);

            function renderCart(items) {
                renderDesktopTable(items);
                renderMobileCards(items);
                updateTotal(items);
            }

            function renderDesktopTable(items) {
                var tbody = document.getElementById('cart-table-body');
                tbody.innerHTML = '';

                items.forEach(function (item, index) {
                    var price = TiktakCart.calculatePrice(item.tariffs, item.days);
                    var dailyRate = item.days > 0 ? (price / item.days) : 0;
                    var dateTo = calculateEndDate(item.dateFrom, item.days);

                    var tr = document.createElement('tr');
                    tr.setAttribute('data-model-id', item.modelId);
                    tr.innerHTML =
                        '<td><a href="' + item.l3Url + '"><img src="' + item.picUrl + '" class="cart-table__img" alt=""></a></td>' +
                        '<td><a href="' + item.l3Url + '" class="cart-table__name-link">' + item.name + '</a></td>' +
                        '<td><input type="date" class="cart-table__date-input cart-date-from" value="' + item.dateFrom + '" min="' + todayStr() + '" data-index="' + index + '"></td>' +
                        '<td><input type="date" class="cart-table__date-input cart-date-to" value="' + dateTo + '" min="' + todayStr() + '" data-index="' + index + '"></td>' +
                        '<td>' +
                        '<div class="cart-table__days-controls">' +
                        '<button class="cart-table__days-btn cart-days-minus" data-index="' + index + '">−</button>' +
                        '<span class="cart-table__days-value">' + item.days + '</span>' +
                        '<button class="cart-table__days-btn cart-days-plus" data-index="' + index + '">+</button>' +
                        '</div>' +
                        '</td>' +
                        '<td>' +
                        '<span class="cart-table__price-total">' + price.toFixed(2) + ' BYN</span>' +
                        '<span class="cart-table__price-rate">' + dailyRate.toFixed(2) + ' BYN/сутки</span>' +
                        '</td>' +
                        '<td><button class="cart-table__remove-btn cart-remove-item" data-index="' + index + '" title="Удалить">' +
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' +
                        '</button></td>';
                    tbody.appendChild(tr);
                });

                // Bind events
                bindCartEvents();
            }

            function renderMobileCards(items) {
                var container = document.getElementById('cart-mobile-cards');
                container.innerHTML = '';

                items.forEach(function (item, index) {
                    var price = TiktakCart.calculatePrice(item.tariffs, item.days);
                    var dailyRate = item.days > 0 ? (price / item.days) : 0;
                    var dateTo = calculateEndDate(item.dateFrom, item.days);

                    var card = document.createElement('div');
                    card.className = 'cart-mobile-card';
                    card.setAttribute('data-model-id', item.modelId);
                    card.innerHTML =
                        '<button class="cart-mobile-card__remove cart-remove-item" data-index="' + index + '">' +
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>' +
                        '</button>' +
                        '<div class="cart-mobile-card__top">' +
                        '<a href="' + item.l3Url + '"><img src="' + item.picUrl + '" class="cart-mobile-card__img" alt=""></a>' +
                        '<a href="' + item.l3Url + '" class="cart-mobile-card__name">' + item.name + '</a>' +
                        '</div>' +
                        '<div class="cart-mobile-card__dates">' +
                        '<div class="cart-mobile-card__date-group">' +
                        '<span class="cart-mobile-card__date-label">Начало проката</span>' +
                        '<input type="date" class="cart-mobile-card__date-input cart-date-from" value="' + item.dateFrom + '" min="' + todayStr() + '" data-index="' + index + '">' +
                        '</div>' +
                        '<div class="cart-mobile-card__date-group">' +
                        '<span class="cart-mobile-card__date-label">Окончание</span>' +
                        '<input type="date" class="cart-mobile-card__date-input cart-date-to" value="' + dateTo + '" min="' + todayStr() + '" data-index="' + index + '">' +
                        '</div>' +
                        '</div>' +
                        '<div class="cart-mobile-card__days-row">' +
                        '<span class="cart-mobile-card__days-label">Суток проката</span>' +
                        '<div class="cart-mobile-card__days-controls">' +
                        '<button class="cart-mobile-card__days-btn cart-days-minus" data-index="' + index + '">−</button>' +
                        '<span class="cart-mobile-card__days-value">' + item.days + '</span>' +
                        '<button class="cart-mobile-card__days-btn cart-days-plus" data-index="' + index + '">+</button>' +
                        '</div>' +
                        '</div>' +
                        '<div class="cart-mobile-card__price-row">' +
                        '<div>' +
                        '<span class="cart-mobile-card__total">' + price.toFixed(2) + ' BYN</span>' +
                        '<span class="cart-mobile-card__rate">' + dailyRate.toFixed(2) + ' BYN/сутки</span>' +
                        '</div>' +
                        '</div>';
                    container.appendChild(card);
                });

                // Bind events
                bindCartEvents();
            }

            function bindCartEvents() {
                // Remove buttons
                document.querySelectorAll('.cart-remove-item').forEach(function (btn) {
                    btn.onclick = function () {
                        var idx = parseInt(this.getAttribute('data-index'));
                        TiktakCart.removeByIndex(idx);
                        var items = TiktakCart.getItems();
                        if (items.length === 0) {
                            contentEl.style.display = 'none';
                            emptyEl.style.display = 'block';
                        } else {
                            renderCart(items);
                        }
                    };
                });

                // Plus/minus days
                document.querySelectorAll('.cart-days-plus').forEach(function (btn) {
                    btn.onclick = function () {
                        var idx = parseInt(this.getAttribute('data-index'));
                        var items = TiktakCart.getItems();
                        if (items[idx]) {
                            items[idx].days = items[idx].days + 1;
                            TiktakCart.saveItems(items);
                            renderCart(items);
                        }
                    };
                });

                document.querySelectorAll('.cart-days-minus').forEach(function (btn) {
                    btn.onclick = function () {
                        var idx = parseInt(this.getAttribute('data-index'));
                        var items = TiktakCart.getItems();
                        if (items[idx] && items[idx].days > 1) {
                            items[idx].days = items[idx].days - 1;
                            TiktakCart.saveItems(items);
                            renderCart(items);
                        }
                    };
                });

                // Date from change
                document.querySelectorAll('.cart-date-from').forEach(function (input) {
                    input.onchange = function () {
                        var idx = parseInt(this.getAttribute('data-index'));
                        var items = TiktakCart.getItems();
                        if (items[idx]) {
                            items[idx].dateFrom = this.value;
                            TiktakCart.saveItems(items);
                            renderCart(items);
                        }
                    };
                });

                // Date to change
                document.querySelectorAll('.cart-date-to').forEach(function (input) {
                    input.onchange = function () {
                        var idx = parseInt(this.getAttribute('data-index'));
                        var items = TiktakCart.getItems();
                        if (items[idx]) {
                            var dateFrom = new Date(items[idx].dateFrom);
                            var dateTo = new Date(this.value);
                            var diffDays = Math.round((dateTo - dateFrom) / (1000 * 60 * 60 * 24));
                            if (diffDays < 1) diffDays = 1;
                            items[idx].days = diffDays;
                            TiktakCart.saveItems(items);
                            renderCart(items);
                        }
                    };
                });
            }

            function updateTotal(items) {
                var total = 0;
                items.forEach(function (item) {
                    total += TiktakCart.calculatePrice(item.tariffs, item.days);
                });
                document.getElementById('cart-total-value').textContent = total.toFixed(2) + ' BYN';
            }

            function calculateEndDate(dateFromStr, days) {
                var d = new Date(dateFromStr);
                d.setDate(d.getDate() + days);
                return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
            }

            function todayStr() {
                var d = new Date();
                return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
            }

            function doCheckout() {
                var items = TiktakCart.getItems();
                if (items.length === 0) return;

                var fio = document.getElementById('cart-fio').value;
                var phone = document.getElementById('cart-phone').value;
                var deliveryRadio = document.querySelector('[name="cart_delivery"]:checked');
                var address = document.getElementById('cart-address').value;
                var info = document.getElementById('cart-info').value;
                var promoCode = document.getElementById('cart-promo-code').value;
                var giftCert = document.getElementById('cart-gift-cert').value;

                // Client-side validation
                var valid = true;

                if (fio.length < 3) {
                    document.getElementById('cart-fio').classList.add('is-invalid');
                    valid = false;
                } else {
                    document.getElementById('cart-fio').classList.remove('is-invalid');
                }

                var phoneDigits = phone.replace(/\D/g, '');
                if (phoneDigits.length < 7) {
                    document.getElementById('cart-phone').classList.add('is-invalid');
                    valid = false;
                } else {
                    document.getElementById('cart-phone').classList.remove('is-invalid');
                }

                if (!deliveryRadio) {
                    document.getElementById('cart-delivery-error').style.display = 'block';
                    valid = false;
                } else {
                    document.getElementById('cart-delivery-error').style.display = 'none';
                }

                if (deliveryRadio && deliveryRadio.value === '1' && address.length < 5) {
                    document.getElementById('cart-address').classList.add('is-invalid');
                    valid = false;
                } else {
                    var addrEl = document.getElementById('cart-address');
                    if (addrEl) addrEl.classList.remove('is-invalid');
                }

                if (!valid) return;

                // Disable button
                var btn = document.getElementById('cart-checkout-btn');
                btn.disabled = true;
                btn.textContent = 'Отправка...';

                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                var token = csrfToken ? csrfToken.getAttribute('content') : '';

                var requestBody = {
                    items: items.map(function (item) {
                        return {
                            modelId: item.modelId,
                            name: item.name,
                            dateFrom: item.dateFrom,
                            days: item.days,
                        };
                    }),
                    fio: fio,
                    phone: phone,
                    delivery: deliveryRadio.value,
                    address: address,
                    info: info,
                    promo_code: promoCode,
                    gift_certificate: giftCert,
                };

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/cart/checkout', true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', token);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.onload = function () {
                    btn.disabled = false;
                    btn.textContent = 'Оформить заказ';

                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            // Clear cart
                            TiktakCart.clear();

                            // Show success
                            contentEl.style.display = 'none';
                            successEl.style.display = 'block';
                            document.getElementById('cart-success-message').textContent = data.message;

                            // Show details for each item
                            if (data.results) {
                                var detailsHtml = '<div style="margin-top: 16px; text-align: left; max-width: 400px; margin-left: auto; margin-right: auto;">';
                                data.results.forEach(function (r) {
                                    var statusIcon = r.status === 'booked' ? '✅' : (r.status === 'waitlist' ? '⏳' : '❌');
                                    var statusText = r.status === 'booked' ? 'Забронировано' :
                                        (r.status === 'waitlist' ? 'Заявка принята' : 'Ошибка');
                                    detailsHtml += '<p style="font-size: 14px; margin: 8px 0;">' + statusIcon + ' ' + r.name + ' — <strong>' + statusText + '</strong></p>';
                                });
                                detailsHtml += '</div>';
                                document.getElementById('cart-success-details').innerHTML = detailsHtml;
                            }
                        } catch (e) {
                            TiktakCart.showToast('Произошла ошибка. Попробуйте ещё раз.', 'error');
                        }
                    } else if (xhr.status === 422) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            TiktakCart.showToast(data.errors.join('. '), 'error');
                        } catch (e) {
                            TiktakCart.showToast('Ошибка валидации', 'error');
                        }
                    } else {
                        TiktakCart.showToast('Ошибка сервера. Попробуйте позже.', 'error');
                    }
                };

                xhr.onerror = function () {
                    btn.disabled = false;
                    btn.textContent = 'Оформить заказ';
                    TiktakCart.showToast('Ошибка сети. Проверьте подключение.', 'error');
                };

                xhr.send(JSON.stringify(requestBody));
            }
        });
    </script>
@endsection