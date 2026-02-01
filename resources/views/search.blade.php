@extends('layouts.app')

@php /** @var \App\MyClasses\CatMainPage $p */ @endphp

@section('page-title', $p->getPageTitle())

@section('content')
<div class="container-app">
    <div class="row">
        <!-- Хлебные крошки -->
        <div class="col-12">
            @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>
        <div class="col-12"><h1 class="h1-listing">{{$p->getH1Title()}}</h1></div>
{{--        <!-- Боковое меню -->--}}
{{--        <div class="col-md-4 col-lg-3">--}}
{{--            @include('includes.leftmenu')--}}
{{--        </div>--}}

        <!-- основной контент -->
        <div class="col-12 content-container">
            @if($p->hasBlock1())
                <div class="row">
                    <div class="col">
                        <div class="text-justify main-page-text main-page-text-hshow" id="mldiv_ml1">
                            {!! $p->getBlock1()!!}
                            <div class="bottom_shadow"></div>
                        </div>
                        <div class="text-right">
                            <a href="#" id="ml1" class="mlbtn" style="text-decoration: underline; color: var(--main-blue)">Развернуть »</a>
                        </div>
                    </div>
                </div>
            @endif
            <div class="row l2-cards-container">
                @if($p->getModelsNum()>0)
                    @foreach($p->getModels() as $m)
                        @include('includes.l2_model_block', ['l2' => $m])
                    @endforeach

                @else
                    <div class="col">
                        <div class="alert-warning"><h2>Товары в данной категории не найдены.</h2></div>

                    </div>
                @endif
            </div> <!-- end of row -->
            @if($p->getShowAgeFilter()==1)
                <div class="row">
                    <div class="col-12 age-filters-container">
                        <a href="/ru/filter?age_from=0&age_to=6" class="age-filter">
                            <div class="age-filter_age-circle"><span>0+</span></div>
                            <span class="age-filter_agetext">0-6 мес</span>
                        </a>
                        <a href="/ru/filter?age_from=6&age_to=12" class="age-filter">
                            <div class="age-filter_age-circle"><span>6+</span></div>
                            <span class="age-filter_agetext">6-12 мес</span>
                        </a>
                        <a href="/ru/filter?age_from=12&age_to=18" class="age-filter">
                            <div class="age-filter_age-circle"><span>12+</span></div>
                            <span class="age-filter_agetext">12-18 мес</span>
                        </a>
                        <a href="/ru/filter?age_from=18&age_to=24" class="age-filter">
                            <div class="age-filter_age-circle"><span>18+</span></div>
                            <span class="age-filter_agetext">18-24 мес</span>
                        </a>
                        <a href="/ru/filter?age_from=24&age_to=36" class="age-filter">
                            <div class="age-filter_age-circle"><span>2+</span></div>
                            <span class="age-filter_agetext">2+ года</span>
                        </a>
                        <a href="/ru/filter?age_from=36&age_to=3600" class="age-filter">
                            <div class="age-filter_age-circle"><span>3+</span></div>
                            <span class="age-filter_agetext">3+ года</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
  <div class="row">
    <div class="col">
      {!! $p->getBlock1() !!}
    </div>
  </div>
</div> <!-- end of container -->
@endsection
