@extends('layouts.empty')
@php /** @var \App\MyClasses\KBForm $f */ @endphp

@section('content')
<div class="kbform_reject">
  <div class="header_line">
    <div class="controll-div">
      <div class="btn-back"><img src="/public/svg/back_arrow_l3karn.svg" alt="close modal"></div>
      <div class="btn-close3" data-bs-dismiss="modal" aria-label="Close"><img src="/public/svg/l3_modal_cross.svg" alt="close modal"></div>
    </div>
{{--    <table class="header-table">--}}
{{--      <tr>--}}
{{--        <td class="name" colspan="2">{!! $f->getTovarName() !!}</td>--}}
{{--      </tr>--}}
{{--      <tr>--}}
{{--        <td class="name">Размер</td>--}}
{{--        <td class="value">{{$f->getRostFrom()}}-{{$f->getRostFrom()}}, {{$f->getSize()}}</td>--}}
{{--      </tr>--}}
{{--      <tr>--}}
{{--        <td class="name">Дата праздника</td>--}}
{{--        <td class="value">{{$f->getEventDate()->format('d')}} {{\bb\Base::getMonthNameForDay($f->getEventDate()->format('m'))}}</td>--}}
{{--      </tr>--}}
{{--    </table>--}}
  </div>
  <p>К сожалению на {{ $f->getEventDate()->format('d.m.Y') }} все костюмы модели “{!! $f->getTovarName() !!}” в размере {{$f->getRostFrom()}}-{{$f->getRostTo()}} см.  уже забронированы.</p>
  <p>Попробуйте выбрать другую модель костюма.</p>
  <p>Также вы можете оставить заявку на этот костюм и в случае отмены брони другим клинтом, мы обязательно свяжемся с вами!</p>

  <input type="text" name="phone" class="kb_phone kb_phone_zayavka" id="phone" placeholder="Телефон">

  <div class="kb_container_1">
    <input class="kb_reject_btn kb-btn-zayavka" type="button" value="Забронировать">
    <img class="kb_message" src="/public/png/message.png">
  </div>


</div>

@endsection
