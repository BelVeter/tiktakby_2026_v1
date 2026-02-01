@extends('layouts.empty')
@php /** @var $message */ @endphp

@section('content')
  <div class="kb_final_container">
    <div class="btn-close3" data-bs-dismiss="modal" aria-label="Close"><img src="/public/svg/l3_modal_cross.svg" alt="close modal"></div>
    <div class="topline">
      <img src="/public/svg/kb_ok.svg" alt="ok">
      <span>готово</span>
    </div>
    <p class="message">{{$message}}</p>
    <div class="bottom_line">Благодарим за заказ!</div>
  </div>
@endsection
