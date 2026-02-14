@php /** @var \App\MyClasses\L3Page $p */ @endphp

<div class="l3__tovar_info_container">
  <div class="row__l3_tovar_name">
    <h1>{!! $p->getL3MainName() !!}</h1>
  </div>
  <div class="row__dog_price">
    <span>{{$p->translate("Оценочная стоимость")}}: {{number_format($p->getItemDogPrice(), 0)}}&nbsp;BYN
      <br>({{$p->translate("с учетом износа")}})</span>
    <img src="{{$p->getProducerLogoUrl()}}" alt="producer-rent-logo">
  </div>

  @php
    $rsArray = $p->getRostSizeArray();
    //dd(count($rsArray));
  @endphp

  <div class="row__l3_tarif_gradient">
    <div class="l3_gradient-header">{{$p->translate('Стоимость проката')}}:</div>
    @if($p->getTarifLinePeriodDaysNumber() == 1)
      <div class="l3_tarif_weeks"><span>1 {{$p->translate('сутки')}}</span><span>2
          {{$p->translate('суток')}}</span><span>3 {{$p->translate('суток')}}</span><span>4
          {{$p->translate('суток')}}</span></div>
    @elseif($p->getTarifLinePeriodDaysNumber() == 7)
      <div class="l3_tarif_weeks"><span>1 {{$p->translate('неделя')}}</span><span>2
          {{$p->translate('недели')}}</span><span>3 {{$p->translate('недели')}}</span><span>4
          {{$p->translate('недели')}}</span></div>
    @else
      <div class="l3_tarif_weeks"><span>1 {{$p->translate('месяц')}}</span><span>2
          {{$p->translate('месяца')}}</span><span>3 {{$p->translate('месяца')}}</span><span>4
          {{$p->translate('месяца')}}</span></div>
    @endif

    <div class="l3_tarif_gradient_container"><span></span><span></span><span></span><span></span><span></span></div>

    <div class="l3_tarif_weeks">
      <span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber() * 1), 2)}}<sup>Br</sup></span><span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber() * 2), 2)}}<sup>Br</sup></span><span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber() * 3), 2)}}<sup>Br</sup></span><span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber() * 4), 2)}}<sup>Br</sup></span>
    </div>

  </div>

  <div class="row__l3_main_calc_container">
    <div class="l3_period_text">{{$p->translate('Выберите период или количество суток проката')}}:</div>
    <div class="l3_main_calc_container">
      <div class="col1">
        <input class="l3_date_from" type="date" value="{{date("Y-m-d")}}" min="{{date("Y-m-d")}}">
        <input class="l3_date_to" type="date" min="{{date("Y-m-d")}}">
        <span class="date-from-placeholder">{{$p->translate('выдача')}}</span>
        <span class="date-to-placeholder">{{$p->translate('возврат')}}</span>
      </div>
      <div class="col2">
        <button class="l3_button_minus"><img src="/public/svg/l3_minus.svg" alt="tarif-minus"></button>
        <div class="input-field-container">
          <input class="l3_days_input" type="number" min="0" value="{{($p->getBaseDaysForPlusMinus())}}">
          <span>суток</span>
        </div>
        <button class="l3_button_plus"><img src="/public/svg/l3_plus.svg" alt="tarif-plus"></button>
      </div>
      <div class="col3">
        <div class="row1">
          <span class="tarif-text">{{$p->translate('Тариф за сутки')}}</span>
          <span class="tarif-value"><span class="per_day_span">4,00</span><sup>Br</sup></span>
        </div>
        <div class="row2">
          <span class="tarif-text">{{$p->translate('Всего за период')}}</span>
          <span class="tarif-value"><span class="total_span">100,00</span><sup>Br</sup></span>
        </div>

        @if($p->getTarifs())
          @foreach($p->getTarifs() as $t)
            <input type="hidden" class="tarif" data-days="{{$t->getDaysCalculatedNumber()}}"
              value="{{$t->getTotalAmount()}}">
          @endforeach
        @endif
      </div>
    </div>
  </div>


  <div class="row__action-buttons">


    {{-- Add to Cart button --}}
    @php
      $l3CartTariffs = [];
      $l3TarifModel = $p->getTarifModel();
      if ($l3TarifModel) {
        foreach ($l3TarifModel->getTarifs() as $t) {
          $daysNum = $t->getDaysCalculatedNumber();
          if ($daysNum > 0) {
            $dailyRate = round($t->getTotalAmount() / $daysNum, 2);
            $l3CartTariffs[] = [$daysNum, $dailyRate];
          }
        }
        usort($l3CartTariffs, function ($a, $b) {
          return $a[0] - $b[0];
        });
      }
    @endphp
    <button type="button" class="action-button cart-button" id="l3-add-to-cart-btn"
      data-model-id="{{ $p->getModelId() }}" data-model-name="{{ strip_tags($p->getL3MainName()) }}"
      data-model-pic="{{ $p->getMainSmallPicUrl() }}" data-model-url="{{ url()->current() }}"
      data-tariffs='@json($l3CartTariffs)'>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        style="vertical-align:middle; margin-right:6px;">
        <circle cx="9" cy="21" r="1"></circle>
        <circle cx="20" cy="21" r="1"></circle>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
      </svg>
      {{$p->translate('В корзину')}}
    </button>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var l3CartBtn = document.getElementById('l3-add-to-cart-btn');
      if (l3CartBtn) {
        l3CartBtn.addEventListener('click', function () {
          var dateFrom = document.querySelector('.l3_date_from');
          var daysInput = document.querySelector('.l3_days_input');
          var btn = this;

          TiktakCart.addItem({
            modelId: parseInt(btn.getAttribute('data-model-id')),
            name: btn.getAttribute('data-model-name'),
            picUrl: btn.getAttribute('data-model-pic'),
            l3Url: btn.getAttribute('data-model-url'),
            dateFrom: dateFrom ? dateFrom.value : TiktakCart.todayStr(),
            days: daysInput ? parseInt(daysInput.value) || 14 : 14,
            tariffs: JSON.parse(btn.getAttribute('data-tariffs'))
          });
        });
      }
    });

    // Check if cart has items before showing direct booking modal
    var l3CartWarningShown = false;
    function l3CheckCartBeforeOrder(btn) {
      if (typeof TiktakCart !== 'undefined' && TiktakCart.hasItems() && !l3CartWarningShown) {
        // Prevent the modal from opening
        btn.removeAttribute('data-bs-toggle');
        btn.removeAttribute('data-bs-target');

        var count = TiktakCart.getCount();
        document.getElementById('cart-warning-count').textContent = count;

        var warningModal = new bootstrap.Modal(document.getElementById('cartWarningModal'));
        warningModal.show();

        // "Order only this" — let user proceed with direct booking
        document.getElementById('cart-warning-continue').onclick = function () {
          warningModal.hide();
          l3CartWarningShown = true;
          btn.setAttribute('data-bs-toggle', 'modal');
          btn.setAttribute('data-bs-target', '#orderModal');
          // Re-trigger click
          setTimeout(function () { btn.click(); }, 300);
        };

        // "Add to cart" — add item to cart and go to cart page
        document.getElementById('cart-warning-add').onclick = function () {
          warningModal.hide();
          var l3CartAddBtn = document.getElementById('l3-add-to-cart-btn');
          if (l3CartAddBtn) l3CartAddBtn.click();
          setTimeout(function () { window.location.href = '/cart'; }, 500);
        };

        return false;
      }
      return true;
    }
  </script>

  <!-- Modal -->
  <form method="post" class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          @if($p->model->hasFreeItems())
            <h5 class="modal-title" id="exampleModalLabel">{{$p->translate('Оформление заказа')}}:</h5>
          @else
            <h5 class="modal-title" id="exampleModalLabel">
              {{$p->translate('Вы получите уведомление, как только товар появится')}}
            </h5>
          @endif
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          @csrf
          <div class="l3_order-container">
            <input type="hidden" name="model_id" value="{{$p->getModelId()}}">

            <div class="line1">
              <img src="{{ $p->getMainBigPicUrl() }}" alt="Фото товара напрокат">
              <h4 class="{{($p->model->hasFreeItems() ? '' : 'zayavk')}}">{!! $p->getL3MainName()  !!}</h4>
            </div>
            <div class="row__l3_main_calc_container">
              <div class="l3_period_text">
                @if($p->model->hasFreeItems())
                  {{$p->translate('Выберите период или количество суток проката')}}:
                @else
                  {!! $p->translate('Укажите, пожалуйста, сколько Вы готовы ждать?<sup>*</sup>') !!}
                @endif
              </div>
              <div class="l3_main_calc_container {{ $p->model->hasFreeItems() ? '' : 'mt-0' }}">
                <div class="col1 {{ $p->model->hasFreeItems() ? '' : 'd-none' }}">
                  <input class="l3_date_from2" name="date_from" type="date" value="{{date("Y-m-d")}}"
                    min="{{date("Y-m-d")}}">
                  <input class="l3_date_to2" name="date_to" type="date" min="{{date("Y-m-d")}}">
                  <span class="date-from-placeholder">{{$p->translate('выдача')}}</span>
                  <span class="date-to-placeholder">{{$p->translate('возврат')}}</span>
                </div>
                <div class="col2">
                  <button type="button" class="l3_button_minus2"><img src="/public/svg/l3_minus.svg"
                      alt="tarif-minus"></button>
                  <div class="input-field-container">
                    <input class="l3_days_input2" name="days_num" type="number" min="0"
                      value="{{($p->getBaseDaysForPlusMinus())}}">
                    <span>суток</span>
                  </div>
                  <button type="button" class="l3_button_plus2"><img src="/public/svg/l3_plus.svg"
                      alt="tarif-plus"></button>
                </div>
                <div class="col3 {{ $p->model->hasFreeItems() ? '' : 'd-none' }}">
                  <div class="row1">
                    <span class="tarif-text">{{$p->translate('Тариф за сутки')}}</span>
                    <span class="tarif-value"><span class="per_day_span2">4,00</span><sup>Br</sup></span>
                  </div>
                  <div class="row2">
                    <span class="tarif-text">{{$p->translate('Всего за период')}}</span>
                    <span class="tarif-value"><span class="total_span2">100,00</span><sup>Br</sup></span>
                  </div>
                </div>
              </div>
              @if(!$p->model->hasFreeItems())
                <div class="l3_period_text" style="font-size: 13px;">
                  <sup>*</sup>по истечение указанного времени заявка автоматически аннулируется
                </div>
              @endif
            </div>
            <div class="form-data-div">
              <div class="form-floating {{ $p->model->hasFreeItems() ? '' : 'd-none' }}">
                <input type="text" class="form-control bg-white" name="fio" id="bron-name"
                  placeholder="{{$p->translate('ФИО')}}" required>
                <label for="bron-name">{{$p->translate('ФИО')}}</label>
                <div class="invalid-feedback">
                  Укажите ФИО (не менее 3-х символов)
                </div>
              </div>
              <div class="form-floating">
                <input type="text" class="form-control bg-white" name="phone" id="bron-phone"
                  placeholder="{{$p->translate('Телефон')}}">
                <label for="bron-phone">{{$p->translate('Телефон: +375 (00) 000-00-00')}}</label>
                <div class="invalid-feedback">
                  Должно быть не менее 7-ми цифр
                </div>
              </div>
              <span class="deliv-radio-text" data-show="whenfree">
                <span>Укажите способ доставки</span>
                <div class="invalid-feedback">
                  Сделайте выбор
                </div>
              </span>
              <div class="radio-row" data-show="whenfree">
                <label class="label1">
                  <input class="form-check-input" type="radio" name="delivery" value="1"><span>Доставка</span>
                </label>
                <label class="label2">
                  <input class="form-check-input" type="radio" name="delivery" value="0"><span>Самовывоз</span>
                </label>
              </div>
              <div class="radio-row-content" data-show="whenfree">
                <div class="content-deliv">
                  <label for="address">{{$p->translate('Адрес доставки')}}:</label>
                  <textarea name="address" class="form-control form-control-lg" id="address" placeholder=""></textarea>
                  <div class="invalid-feedback">
                    Заполните адрес доставки
                  </div>
                </div>
                <div class="content-sam">

                </div>
              </div>
              <div class="invalid-feedback" style="position: relative; top: -20px">
                Выберите конкретный офис
              </div>
              <div class="form-group">
                <label for="phone">{{$p->translate('Дополнительная информация')}}:</label>
                <textarea name="info" class="form-control form-control-lg" id="info" placeholder=""></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          @if($p->model->hasFreeItems())
            <button type="button" id="bron-submit-btn" class="btn btn-lg btn-info">{{$p->translate('Заказать')}}</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$p->translate('Отмена')}}</button>
          @else
            <div class="zayavka-btn-container">
              <button type="button" id="zayavka-submit-btn"
                class="zayavka-submit-btn">{{$p->translate('Заказать')}}</button>
            </div>
          @endif
        </div>
      </div>
    </div>
  </form>
  <!-- EndOf Modal -->

  <!-- Cart Warning Modal -->
  <div class="modal fade" id="cartWarningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header" style="border-bottom:1px solid #eee;">
          <h5 class="modal-title" style="font-family:'Nunito',sans-serif; font-weight:700;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF9800" stroke-width="2"
              style="margin-right:8px; vertical-align:middle;">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            Товары в корзине
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="font-family:'Nunito',sans-serif; font-size:15px; color:#333; padding:20px;">
          У вас уже есть <strong><span id="cart-warning-count">0</span> товар(ов)</strong> в корзине.
          <br><br>
          Вы хотите оформить заказ <strong>только на этот товар</strong> или <strong>добавить его в корзину</strong> и
          оформить всё вместе?
        </div>
        <div class="modal-footer" style="border-top:1px solid #eee; gap:10px;">
          <button type="button" id="cart-warning-continue" class="btn"
            style="background:#5EC282; color:#fff; font-weight:600; border-radius:6px; padding:10px 20px;">
            Оформить только этот
          </button>
          <button type="button" id="cart-warning-add" class="btn"
            style="background:#4A90E2; color:#fff; font-weight:600; border-radius:6px; padding:10px 20px;">
            Добавить в корзину
          </button>
        </div>
      </div>
    </div>
  </div>
</div>