@extends('layouts.app')

@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', $p->getTitle())
@section('meta-description', $p->getMetaDescription())
@section('style')
  <link rel="stylesheet" href="/public/css/pages/l2.css?v=3">
@endsection
@if($url = $p->getCanonicalUrlBy())
  @section('canonical')
    <link rel="canonical" href="{{ $url }}">
  @endsection
@endif


@section('content')
  <!-- Cat Header  -->
  @if($p->hasH1LongText())
    <div class="cat-header-container">
      @if($p->getH1PicUrl())
        <div class="cat-header_picture"><img src="{{$p->getH1PicUrl()}}"></div>
      @endif
      <div class="cat-header_text" @if(!$p->getH1PicUrl()) style="flex-basis: 100%; max-width: 100%;" @endif>
        <!-- Хлебные крошки -->
        <div class="breadcrumb-line breadcrumb-long-text-mobile">
          <div class="row">
            <div class="col">
              @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
            </div>
          </div>
        </div>
        <h1>{{$p->getH1()}}</h1>
        {!! $p->getH1LongText() !!}
      </div>
    </div>
  @else
    <!-- Хлебные крошки -->
    <div class="breadcrumb-line breadcrumb-long-text-mobile container-app">
      <div class="row">
        <div class="col">
          @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
        </div>
      </div>
    </div>
    <div class="col-12 container-app">
      <h1 class="h1-listing text-left">{{$p->getH1()}}</h1>
    </div>
  @endif

  <div class="main-gradient">
    <div class="main-real-gradient"></div>
    <div class="container-app">
      <div class="row col d-flex d-md-none mobile-next-level_menu-container">
        <div class="row-n">
          @foreach(\bb\classes\TopMenu::getTopMenu(request()->lang)->getNexLevelMenuArrayLine(request()->razdel, request()->subrazdel, 1) as $ar)
            <a href="{{$ar[0]}}" class="mobil-2d-menu-a"><span>{{$ar[1]}}</span></a>
          @endforeach
        </div>
        @if(count(\bb\classes\TopMenu::getTopMenu(request()->lang)->getNexLevelMenuArrayLine(request()->razdel, request()->subrazdel, 2)) > 0)
          <div class="row-n">
            @foreach(\bb\classes\TopMenu::getTopMenu(request()->lang)->getNexLevelMenuArrayLine(request()->razdel, request()->subrazdel, 2) as $ar)
              <a href="{{$ar[0]}}" class="mobil-2d-menu-a"><span>{{$ar[1]}}</span></a>
            @endforeach
          </div>
        @endif


      </div>
      <div class="row">
        <!-- Хлебные крошки -->
        <div class="col-12 breadcrumb-line breadcrumb-std-desctop">
          <div class="row">
            <div class="col">
              @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
            </div>
            <div class="col-3 ml-auto l2-results-count">РЕЗУЛЬТАТЫ:
              {{ $p->getStartListingNumber() }}-{{ $p->getEndListingNumber()   }} ИЗ {{$p->getTotalModelsNum()}}
            </div>
          </div>
        </div>

        @if($p->isKarnaval())
          <!-- Filter form -->
          <div class="l3k-filter-container">
            <div class="col1"></div>
            <form class="col2" method="get">
              <div class="form-floating">
                <select name="gender" class="form-select" id="floatingSelect" aria-label="Floating label select example">
                  <option value="all" {{ \bb\Base::sel_d(request()->gender, 'all')  }}>не указан</option>
                  <option value="m" {{ \bb\Base::sel_d(request()->gender, 'm')  }}>для мальчика</option>
                  <option value="f" {{ \bb\Base::sel_d(request()->gender, 'f')  }}>для девочки</option>
                </select>
                <label for="floatingSelect">Пол:</label>
              </div>
              <div class="form-floating">
                <input name="rost" type="number" class="form-control" value="{{ request()->rost }}">
                <label for="floatingInputValue">Рост (см.) </label>
              </div>
              <div class="form-floating date d-none">
                <input type="date" name="date" class="form-control" min="{{ (new DateTime())->format("Y-m-d") }}">
                <label for="floatingInputValue">Дата мероприятия</label>
              </div>
              <input type="hidden" value="filter" value="1">
              <button class="btn btn-outline-info" type="button" id="filter-btn-l2">фильтр</button>
            </form>
          </div>
        @endif
        <!-- Cat content -->
        <div class="col-12 cat-main-container">
          <!-- Боковое меню -->
          <div class="left-menu">
            @include('includes.leftmenu')
          </div>
          <!-- основной контент -->
          <div class="cat-content-container">
            @if($p->getShowPageNumber() > 1)
              <div class="more-btn-div"><a class="l2-more-btn"
                  href="{{\Illuminate\Support\Facades\URL::current() . ($p->getShowPageNumber() > 2 ? '?page=' . ($p->getShowPageNumber() - 1) . (request()->gender == '' ? '' : '&gender=' . request()->gender) . (request()->rost == '' ? '' : '&rost=' . request()->rost) . (request()->date == '' ? '' : '&date=' . request()->date) : '')}}">Показать
                  предыдущие</a></div>
            @endif
            <div class="l2-cards-container">
              @if($p->getModelsNum() > 0)
                @foreach($p->getModels() as $m)
                  @if($m->isKarnaval())
                    @include('includes.l2karn_model_block', ['l2' => $m])
                  @else
                    @include('includes.l2_model_block', ['l2' => $m])
                  @endif
                @endforeach

              @else
                <div class="col">
                  @if($p->isInRazdel('prokat-strojinstrumenty'))
                    <img class="img img-fluid" src="/public/jpg/stroi-no.jpg" alt="Товары в данной категории не найдены">
                  @else
                    @if(request()->rost > 0 || request()->gender != '' || request()->date != '')
                      <div class="alert-warning">
                        <h2>Товары в данной категории с заданными параметрами фильтрации не найдены. <br> Попробуйте изменить
                          параметры фильтров.</h2>
                      </div>
                    @else
                      <div class="alert-warning">
                        <h2>Товары в данной категории не найдены.</h2>
                      </div>
                    @endif
                  @endif
                </div>
              @endif
            </div> <!-- end of models div -->
            <div class="listing-end-results">Показано {{ $p->getStartListingNumber() }}-{{ $p->getEndListingNumber()   }}
              из {{$p->getTotalModelsNum()}}</div>
            @if($p->hasNextModelsButton())
              <div class="more-btn-div"><a class="l2-more-btn"
                  href="{{\Illuminate\Support\Facades\URL::current() . '?page=' . ($p->getShowPageNumber() + 1) . (request()->gender == '' ? '' : '&gender=' . request()->gender) . (request()->rost == '' ? '' : '&rost=' . request()->rost) . (request()->date == '' ? '' : '&date=' . request()->date)}}">Показать
                  еще</a></div>
            @endif

            @if($p->getShowAgeFilter() == 1)
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
            @if($p->getCodeBlock1() && $p->getShowPageNumber() < 2)
              <div class="row">
                <div class="col-12">
                  {!! $p->getCodeBlock1() !!}
                </div>
              </div>
            @endif
            @if(isset($_GET['v']) && $_GET['v'] == 'dima')
              <div class="row">
                @foreach($p->getDevInfo() as $d)
                  <div class="col-12">{!! $d !!}</div>
                @endforeach
              </div>
            @endif
          </div>
        </div>
      </div>
    </div> <!-- end of container app -->
  </div><!-- end of gradient -->
  <script type="module" src="/public/js/l2.js?v=2 "></script>

  @if(isset($_COOKIE['tt_is_logged_in']))
    @if($p->getLevelCode() == 'razdel')
      <div data-bb-edit-url="/bb/page_management.php" data-bb-edit-method="POST"
        data-bb-edit-params='@json(["level_code" => "razdel", "url_key" => $p->_razdel->getUrlRazdelName()])'></div>
    @elseif($p->getLevelCode() == 'subrazdel' && $p->_subRazdel)
      <div data-bb-edit-url="/bb/page_management.php" data-bb-edit-method="POST"
        data-bb-edit-params='@json(["level_code" => "subrazdel", "url_key" => $p->_subRazdel->getUrlSubRazdelName()])'></div>
    @elseif($p->getLevelCode() == 'category' && $p->_category)
      <div data-bb-edit-url="/bb/page_management.php" data-bb-edit-method="POST"
        data-bb-edit-params='@json(["level_code" => "category", "url_key" => $p->_category->getCatUrlKey()])'></div>
    @endif
  @endif

@endsection