@extends('layouts.empty')
@php
  /** @var \bb\\bb\KBron $kb */
  /** @var $message */
@endphp

@section('content')
  <div class="kb_final_container">
    <div class="btn-close3" data-bs-dismiss="modal" aria-label="Close"><img src="/public/svg/l3_modal_cross.svg" alt="close modal"></div>
    <div class="topline">
      <img src="/public/svg/kb_ok.svg" alt="ok">
      <span>готово</span>
    </div>
    <div class="qr_line">
      <img class="kb_qr_img" src="/bb/qr_png.php?text=brnum{{ $kb->br_num }}&size=10" alt="order QR">
      <div class="k_bron">
        <span class="text1">Номер вашей брони</span>
        <span class="brnum">{{$kb->getBrNumFormated()}}</span>
      </div>
    </div>
    <div class="recommendation">Рекомендуем сделать скрин этого экрана</div>
    <div class="item">
      <div class="text1">
        @php
        try{
          $item = \bb\classes\tovar::getTovarByInvN($kb->inv_n);
          $model = \bb\classes\Model::getById($item->model_id);
          $modelWeb = \bb\classes\ModelWeb::getByModelId($item->model_id);
        }
        catch (Exception $e){
          $item = new \bb\classes\tovar();
          $model = new \bb\classes\Model();
          $model->model='Ошибка';
        }
        @endphp
        <div class="name">{{ $model->getShortNameModelOnly(false) }}</div>
        <div class="size"> {{ $item->getRostFrom() }}-{{$item->getRostTo()}} ({{$item->getItemSize()}})</div>
        <div class="from1">
          <span class="name1">Выдача:</span>
          <span class="value1">{{$kb->from_kb->format("d")}} {{\bb\Base::getShorMonthText($kb->from_kb->format('m'))}} {{$kb->from_kb->format("H.i")}}</span>
        </div>
        <div class="to1">
          <span class="name1">Возврат:</span>
          <span class="value1">{{$kb->to_kb->format("d")}} {{\bb\Base::getShorMonthText($kb->to_kb->format('m'))}} {{$kb->to_kb->format("H.i")}}</span>
        </div>
      </div>
      <div class="img_container"><img class="modal_final_item_img" src="{{$modelWeb->getL2PicUrlAddress()}}" alt="{{ $model->getShortNameModelOnly(false) }}"></div>

    </div>
    <div class="recommendation">Рекомендуем сделать скрин этого экрана</div>
    <p class="message">{!! $message !!}</p>
    <a href="/ru/conditions#karnaval" target="_blank" class="karnaval_terms">
      <img src="/public/svg/karn_terms.png" alt="terms for karnaval">
      <span>Правила проката карнавальных костюмов</span>
    </a>

    <div class="bottom_line">Благодарим за заказ!</div>
  </div>
@endsection
