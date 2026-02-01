@php /** @var \App\MyClasses\L3Page $p */ @endphp

<div class="l3__tovar_info_container">
  <div class="row__l3_tovar_name">
    <h1>{!! $p->getL3MainName() !!}</h1>
  </div>
  <div class="row__dog_price">
    <span>{{$p->translate("Оценочная стоимость")}}: ${{number_format($p->getItemDogPrice(),0)}} <br>({{$p->translate("без учета износа")}})</span>
    <img src="{{$p->getProducerLogoUrl()}}" alt="producer-rent-logo">
  </div>

  @php
    $rsArray=$p->getRostSizeArray();
    //dd(count($rsArray));
  @endphp

  <div class="row__l3_tarif_gradient">
    <div class="l3_gradient-header">{{$p->translate('Стоимость проката')}}:</div>
    @if($p->getTarifLinePeriodDaysNumber() == 1)
      <div class="l3_tarif_weeks"><span>1 {{$p->translate('сутки')}}</span><span>2 {{$p->translate('суток')}}</span><span>3 {{$p->translate('суток')}}</span><span>4 {{$p->translate('суток')}}</span></div>
    @elseif($p->getTarifLinePeriodDaysNumber() == 7)
      <div class="l3_tarif_weeks"><span>1 {{$p->translate('неделя')}}</span><span>2 {{$p->translate('недели')}}</span><span>3 {{$p->translate('недели')}}</span><span>4 {{$p->translate('недели')}}</span></div>
    @else
      <div class="l3_tarif_weeks"><span>1 {{$p->translate('месяц')}}</span><span>2 {{$p->translate('месяца')}}</span><span>3 {{$p->translate('месяца')}}</span><span>4 {{$p->translate('месяца')}}</span></div>
    @endif

    <div class="l3_tarif_gradient_container"><span></span><span></span><span></span><span></span><span></span></div>

    <div class="l3_tarif_weeks"><span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber()*1),2)}}<sup>Br</sup></span><span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber()*2),2)}}<sup>Br</sup></span><span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber()*3),2)}}<sup>Br</sup></span><span>{{number_format($p->getTarifModel()->getAmmountForDaysPeriod($p->getTarifLinePeriodDaysNumber()*4),2)}}<sup>Br</sup></span></div>

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
            <input type="hidden" class="tarif" data-days="{{$t->getDaysCalculatedNumber()}}" value="{{$t->getTotalAmount()}}">
          @endforeach
        @endif
      </div>
    </div>
  </div>


  <div class="row__action-buttons">
    @if($p->model->hasFreeItems())
      <button class="action-button bron-button" data-actionbtn="order" data-bs-toggle="modal" data-bs-target="#orderModal">{{$p->translate('Забронировать')}}</button>
    @else
      <button class="action-button bron-button" data-actionbtn="order" data-bs-toggle="modal" data-bs-target="#orderModal">{{$p->translate('Оставить заявку')}}</button>
    @endif
  </div>

  <!-- Modal -->
  <form method="post" class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          @if($p->model->hasFreeItems())
            <h5 class="modal-title" id="exampleModalLabel">{{$p->translate('Оформление заказа')}}:</h5>
          @else
            <h5 class="modal-title" id="exampleModalLabel">{{$p->translate('Вы получите уведомление, как только товар появится')}}</h5>
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
                  <input class="l3_date_from2" name="date_from" type="date" value="{{date("Y-m-d")}}" min="{{date("Y-m-d")}}">
                  <input class="l3_date_to2" name="date_to" type="date" min="{{date("Y-m-d")}}">
                  <span class="date-from-placeholder">{{$p->translate('выдача')}}</span>
                  <span class="date-to-placeholder">{{$p->translate('возврат')}}</span>
                </div>
                <div class="col2">
                  <button type="button" class="l3_button_minus2"><img src="/public/svg/l3_minus.svg" alt="tarif-minus"></button>
                  <div class="input-field-container">
                    <input class="l3_days_input2" name="days_num" type="number" min="0" value="{{($p->getBaseDaysForPlusMinus())}}">
                    <span>суток</span>
                  </div>
                  <button type="button" class="l3_button_plus2"><img src="/public/svg/l3_plus.svg" alt="tarif-plus"></button>
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
                <input type="text" class="form-control bg-white" name="fio" id="bron-name" placeholder="{{$p->translate('ФИО')}}" required>
                <label for="bron-name">{{$p->translate('ФИО')}}</label>
                <div class="invalid-feedback">
                  Укажите ФИО (не менее 3-х символов)
                </div>
              </div>
              <div class="form-floating">
                <input type="text" class="form-control bg-white" name="phone" id="bron-phone" placeholder="{{$p->translate('Телефон')}}" >
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
              <button type="button" id="zayavka-submit-btn" class="zayavka-submit-btn">{{$p->translate('Заказать')}}</button>
            </div>
          @endif
        </div>
      </div>
    </div>
  </form>
  <!-- EndOf Modal -->
</div>
