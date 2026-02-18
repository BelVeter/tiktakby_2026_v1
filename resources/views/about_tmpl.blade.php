@extends('layouts.app')
@php /** @var \App\MyClasses\MainPage $p */ @endphp

@section('page-title', $p->getTitle())

@section('content')

  <div class="container-app">
    <div class="row mt-4">
      @include('includes.breadcrumbs', ['b' => $p->getBreadCrumbsArray()])
    </div>
    <div class="row">
      <h1 style="text-align: left; margin: 10px 0;">{{ $p->getH1() }}</h1>
      {!! $p->getCodeBlock1() !!}
    </div>
  </div>


  @if(isset($_COOKIE['tt_is_logged_in']))
    <div data-bb-edit-url="/bb/page_management.php" data-bb-edit-method="POST"
      data-bb-edit-params='@json(["level_code" => "main", "url_key" => $p->getUrlKey()])'></div>
  @endif
@endsection