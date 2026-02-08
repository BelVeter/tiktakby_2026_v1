@extends('layouts.app')

@section('content')
    <div class="container mt-4 mb-5">
        <h1>Избранные товары</h1>

        @if(count($models) > 0)
            <div class="row">
                @foreach($models as $l2)
                    <div class="col-12 col-md-6 col-lg-4 mb-4">
                        @include('includes.l2_model_block', ['l2' => $l2])
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-info">
                Ваш список избранного пуст.
            </div>
            <a href="/" class="btn btn-primary">Перейти в каталог</a>
        @endif
    </div>
@endsection