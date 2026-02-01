@extends('layouts.app')

@php /** @var \App\MyClasses\L3Page $p */ @endphp


@section('page-title', $p->getPageTitle())
@section('meta-description', $p->getMetaDescription())

@if($url = $p->getCanonicalUrlBy())
  @section('canonical')
    <link rel="canonical" href="{{ $url }}">
  @endsection
@endif

@section('content')
  <script type="module" src="/public/js/l3.js?v=15"></script>

  <section class="main-gradient">
    <div class="container-app">
      <div class="row">
        <div class="col">
          <div class="row breadcrumb-line">
            <div class="col">
              @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
            </div>
          </div>
          @if($p->hasMessages())
            <div class="row">
              <div class="col-12">
                <div class="alert alert-warning">
                  <ul>
                    @foreach($p->getMessages() as $m)
                      <li>{!! $m !!}</li>
                    @endforeach
                  </ul>
                </div>
              </div>
            </div>
          @endif
          <div class="row">
            <div class="col d-block d-md-none">
              <h1 class="l3_h1-mobile">
                @if(!$p->isKarnaval())
                  {!! $p->getL3MainName() !!}
                @endif
              </h1>
            </div>
          </div>
          <div class="row">
            <div class="col l3_line1">
              {{-- Slider moved to l3_tovar_info_block --}}
              @if($p->isKarnaval())
                @include('includes.l3_tovar_info_block_karnaval', ['p' => $p])
              @else
                @include('includes.l3_tovar_info_block', ['p' => $p])
              @endif
            </div>
          </div>
          {{-- <div class="row">--}}
            {{-- <div class="col">--}}
              {{-- @include('includes.kbLine', ['l' => \App\MyClasses\KBronLine::getLine('70287', new DateTime())])--}}
              {{-- </div>--}}
            {{-- </div>--}}
          <div class="row">
            <div class="col">
              <div class="l3_description_container">
                {!! $p->getDescription() !!}
                <div class="shadow-gradient"></div>
                <button class="show-more-btn"><img src="/public/svg/arrow-l3-main-slider.svg"></button>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col">
              <div class="l3-fav-slider-container">
                <h3>{{$p->translate('Вам может понравится')}}</h3>
                <button class="btn-controll btn-left hide"><img src="/public/svg/arrow-l3-fav-slider.svg"
                    alt="arrow"></button>
                <button class="btn-controll btn-right"><img src="/public/svg/arrow-l3-fav-slider.svg"
                    alt="arrow"></button>
                <div class="l3_favorite_tovar_container">
                  @foreach($p->getFavoriteTovarsModels() as $mw)
                    <a class="small-card-container" href="{{$mw->getL3Url()}}">
                      <img class="heart-img" src="/public/png/heart-small-card.png" alt="like">
                      <img class="main-img" src="{{$mw->getPicUrl()}}" alt="{{$mw->getPicAltText()}}">
                      <h4>{!! $mw->getName() !!}</h4>
                      <button>{{$p->translate('Подробнее')}}</button>
                    </a>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row">@if(isset($_GET['v']) && $_GET['v'] == 'dima')
        <div class="row">
          <div class="col">
            работает {{$p->getModelId()}}
          </div>
        </div>

      @endif
      </div>
    </div>
  </section>

@endsection