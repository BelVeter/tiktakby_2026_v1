@extends('layouts.app')

@section('page-title', 'Страница не найдена')
@section('meta-description', 'Страница не найдена')
@section('style')
  <link rel="stylesheet" href="/public/css/pages/l2.css?v=10">
@endsection

@section('content')
<div class="main-gradient">
  <div class="main-real-gradient"></div>
  <div class="container-app">
    <div class="row">
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
      <div class="col-12 cat-main-container">
        <div class="cat-content-container" style="width:100%">
          <div style="margin: 60px auto; text-align: center; max-width: 500px;">
            <h1>404 — Страница не найдена :(</h1>
            <p style="margin-top: 30px;"><a href="/ru">На главную</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
