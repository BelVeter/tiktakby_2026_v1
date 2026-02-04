@php /** @var \App\MyClasses\L3Page $p */ @endphp

<div class="l3__tovar_karn_info_container">
  <div class="l3_tovar_name">
    <h1>{!! $p->getL3MainName() !!}</h1>
  </div>
  <div class="karn_collateral">
    <span style="width: 100%;">{{$p->translate("Оценочная стоимость")}}: {{number_format($p->getItemDogPrice(), 0)}} BYN
      ({{$p->translate("с учетом износа")}})</span>
  </div>

  @php
    $rsArray = $p->getRostSizeArray();
    //dd($rsArray);
  @endphp
  <div class="tov_container2">
    <div class="k_size">
      <div class="circle first"></div>
      <div class="k_size_internal">
        <span>Размер:</span>
        <ul class="k_sizes">
          @if(is_array($rsArray) && count($rsArray) > 0)
            @foreach($rsArray as $rs)
              <li data-rost_from="{{$rs[0]}}" data-rost_to="{{$rs[1]}}" data-size="{{$rs[2]}}">{{$rs[1]}}</li>
            @endforeach
          @endif
        </ul>
      </div>
    </div>
    <div class="k_tarif">
      <div class="circle second"></div>
      <div class="k_tarif_internal l3_more_cont">
        <div class="line1">
          <span class="kt_name">Тариф проката:</span>
          <span class="kt_traif">{{ round($p->tariffs->getDaylyTarifForDaysPeriod(1), 0) }} руб / сутки</span>
          <div class="more_btn" data-action="l3_k_more_btn"><img src="/public/svg/k_arrow_l3.svg" alt="arrow"></div>
        </div>
        <div class="line2">
          {{ \bb\classes\L3KarnDop::get()->getTarif() }}
        </div>
      </div>
    </div>
    <div class="k_collateral">
      <div class="circle third"></div>
      <div class="k_collateral_internal l3_more_cont">
        <div class="line1">
          <span class="name">Залог</span>
          <span class="value">{{ round($p->getCollateralAmmount(), 0) }} руб</span>
          <div class="more_btn" data-action="l3_k_more_btn"><img src="/public/svg/k_arrow_l3.svg" alt="arrow"></div>
        </div>
        <div class="line2">
          {{ \bb\classes\L3KarnDop::get()->getCollateral() }}
        </div>
      </div>
    </div>
    <div class="k_address">
      <div class="circle forths"></div>
      <div class="k_adress_internal l3_more_cont">
        <div class="line1">
          <span class="name">Адрес:</span>
          <span class="value">Литературная 22</span>
          <div class="more_btn" data-action="l3_k_more_btn"><img src="/public/svg/k_arrow_l3.svg" alt="arrow"></div>
        </div>
        <div class="line2">
          {{ \bb\classes\L3KarnDop::get()->getAddress() }}
        </div>
      </div>
    </div>
    <div class="k_delivery">
      <div class="circle fifth"></div>
      <div class="k_delivery_internal l3_more_cont">
        <div class="line1">
          <span class="name">Доставка</span>
          <span class="value">не доставляется</span>
          <div class="more_btn" data-action="l3_k_more_btn"><img src="/public/svg/k_arrow_l3.svg" alt="arrow"></div>
        </div>
        <div class="line2">
          {{ \bb\classes\L3KarnDop::get()->getDelivery() }}
        </div>
      </div>
    </div>
    <a href="/{{$p->lang}}/conditions#karnaval" class="karnaval_terms">
      <img src="/public/svg/karn_terms.png" alt="terms for karnaval">
      <span>Правила проката карнавальных костюмов</span>
    </a>
  </div>


  <div class="row__action-buttons">
    <button class="action-button" data-action="kbronstart">{{$p->translate('Забронировать')}}</button>
  </div>

  <!-- Modal -->
  <form method="post" class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel"
    aria-hidden="true">
    @csrf
    <div class="modal-dialog">
      <div class="modal-content">
        {{-- <div class="modal-header">--}}
          {{-- <h5 class="modal-title" id="exampleModalLabel">{!! $p->getL3MainName() !!}</h5>--}}
          {{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
            id="modal_close_x"></button>--}}
          {{-- </div>--}}
        <div class="modal-body">
          <div class="k_first-step_container">
            <input type="hidden" name="model_id" value="{{$p->getModelId()}}">
            <input type="hidden" name="rost_from">
            <input type="hidden" name="rost_to">
            <input type="hidden" name="size">
            <input type="hidden" name="karnaval" value="1">

            <div class="name-div">
              <span class="name">{!! $p->getL3MainName() !!}</span>
              <div type="button" class="btn-close2" data-bs-dismiss="modal" aria-label="Close" id="modal_close_x"><img
                  src="/public/svg/l3_modal_cross.svg" alt="close modal"></div>
            </div>
            <div class="size-div">
              <span class="name">Размер:</span>
              <ul class="size-list">
                @if(is_array($rsArray) && count($rsArray) > 0)
                  @foreach($rsArray as $rs)
                    <li class="rostsize-li" data-rost_from="{{$rs[0]}}" data-rost_to="{{$rs[1]}}" data-size="{{$rs[2]}}">
                      {{$rs[1]}}
                    </li>
                  @endforeach
                @endif
              </ul>
            </div>
            <div class="event-div">
              <span class="name">Дата мероприятия:</span>
              <input class="date event_date_1" type="date" value="{{ (new DateTime())->format("Y-m-d") }}">
            </div>
            <button type="button" class="nex-btn" data-action="modal-next1">Далее</button>
          </div>
          <div class="k_second-step_container">

          </div>
        </div>
        {{-- <div class="modal-footer">--}}
          {{-- <button type="submit" class="btn btn-lg btn-info">{{$p->translate('Забронировать')}}</button>--}}
          {{-- <button type="button" class="btn btn-secondary"
            data-bs-dismiss="modal">{{$p->translate('Отмена')}}</button>--}}
          {{-- </div>--}}
      </div>
    </div>
  </form>
  <!-- EndOf Modal -->
</div>