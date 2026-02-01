@php /** @var \App\MyClasses\L2ModelWeb $l2 */ @endphp

<div class="l2-card_container">
  <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_img-container">
    <span class="l2-card_header">{!! $l2->getName() !!}</span>
    <img src="{{ $l2->getPicUrl(request()->lang) }}" class="l2-card_img" alt="{{$l2->getPicAltText()}}">
  </a>
  <div class="l2-card_line-2">
    <div class="l2-col1">{{$l2->translate('Тариф за сутки')}}</div>
    <div class="l2-col2">{{$l2->translate('Суток')}}</div>
  </div>
  <div class="l2-card_line-2_mobile d-block d-md-none">
    от {{number_format($l2->getMinDayTarifValue(), 2, ',', ' ')}}<sup>Br</sup> {{ $l2->translate('в сутки') }}
  </div>
  <div class="l2-card_line-3">
    <div class="l2-col3">
      <span class="day"><span class="day-tarif-span">{{number_format($l2->getTarifModel()->getDaylyTarifForDaysPeriod(($l2->getBaseDaysForPlusMinus())), 2, ',', ' ')}}</span><sup>Br</sup></span>
      <span class="total"><span class="total-rent-span">{{number_format($l2->getTarifModel()->getAmmountForDaysPeriod(($l2->getBaseDaysForPlusMinus())), 2, ',', ' ')}}</span><sup>Br</sup> {{$l2->translate('ВСЕГО')}}</span>
    </div>
    <div class="l2-col4">
      <div class="l2-card_input-form">
        <button class="arrow-up" type="button"><img src="/public/svg/arrow-up-input.svg"></button>
        <button class="arrow-down" type="button"><img src="/public/svg/arrow-up-input.svg"></button>
        <input type="number" value="{{($l2->getBaseDaysForPlusMinus())}}" class="l2-card_number-input" id="{{$l2->getModelId()}}" onchange="" min="1" step="1">
        @if(is_array($l2->getTariffsAll()))
          @foreach($l2->getTariffsAll() as $t)
            <input type="hidden" class="tarif" data-days="{{$t->getDaysCalculatedNumber()}}" value="{{$t->getTotalAmount()}}">
          @endforeach
        @endif
      </div>
    </div>
  </div>

{{--button variants--}}
  @if($l2->isL2AvailabilityVisible())

    <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_line-4-a" {!! $l2->hasItemsAvailable() ? '' : 'style="background-color: #ff0013"' !!}}>
      <span class="l2-card_btn-desctop">{{$l2->translate(($l2->hasItemsAvailable() ? 'Доступен к прокату' : 'Оставить заявку'))}}</span>
      <span class="l2-card_btn-mobile">{{$l2->translate(($l2->hasItemsAvailable() ? 'Доступен к прокату' : 'Оставить заявку'))}}</span>
      <input type="hidden" name="dima-info" value="{{ $l2->getModelWeb()->getWebId() }}">
    </a>

    @if(!$l2->isKarnaval() && $l2->isAvailableAtOffice(1))
      <div class="office-available off1"></div>
    @endif
    @if(!$l2->isKarnaval() && $l2->isAvailableAtOffice(2))
      <div class="office-available off2"></div>
    @endif

  @else
    <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_line-4-a">
      <span class="l2-card_btn-desctop">Забронировать</span>
      <input type="hidden" name="dima-info" value="{{ $l2->getModelWeb()->getWebId() }}">
    </a>
  @endif

</div>
