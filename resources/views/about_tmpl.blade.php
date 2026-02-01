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


@endsection
