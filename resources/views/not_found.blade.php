@extends('layouts.app')

@php
  use Illuminate\Support\Facades\Log;

  // Get the current request path
  $currentPath = request()->fullUrl();

  // Log the path
  Log::info("View loaded: " . $currentPath);
@endphp

@section('page-title', 'Страница не найдена')
@section('meta-description', 'Страница не найдена')
@section('style')
  <link rel="stylesheet" href="/public/css/pages/l2.css?v=10">
@endsection

@section('content')


<div class="main-gradient">
  <div class="main-real-gradient"></div>
    <div class="container-app">
        <div class="row col d-flex d-md-none mobile-next-level_menu-container">
            <div class="row-n">
                @foreach(\bb\classes\TopMenu::getTopMenu(request()->lang)->getNexLevelMenuArrayLine(request()->razdel, request()->subrazdel, 1) as $ar)
                    <a href="{{$ar[0]}}" class="mobil-2d-menu-a"><span>{{$ar[1]}}</span></a>
                @endforeach
            </div>
            @if(count(\bb\classes\TopMenu::getTopMenu(request()->lang)->getNexLevelMenuArrayLine(request()->razdel, request()->subrazdel, 2))>0)
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
                  <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent">
                      <li class="breadcrumb-item d-none d-sm-inline-block"><a href="/ru/">Главная</a></li>
                      <li class="breadcrumb-item active d-none d-sm-inline-block" aria-current="page">Страница не найдена :(</li>
                    </ol>
                  </nav>
                </div>
            </div>
        </div>



        <!-- Cat content -->
        <div class="col-12 cat-main-container">
            <!-- Боковое меню -->
            <div class="left-menu">
                @include('includes.leftmenu')
            </div>
            <!-- основной контент -->
            <div class="cat-content-container">
              <div style="margin: 20px; width: 100%;">
                <h1 style="text-align: center">404 - Страница не найдена :(</h1>
                <p style="text-align: center; margin-top: 30px;"><a href="/ru">На главную</a></p>
              </div>
            </div>
        </div>
    </div>
</div> <!-- end of container app -->
</div><!-- end of gradient -->
    <script type="module" src="/public/js/l2.js?v=10 "></script>
@endsection
