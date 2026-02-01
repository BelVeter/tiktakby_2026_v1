@php /** @var[] $b */ @endphp

<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-transparent">
        @php
            $prelast=count($b)-1;
            $i=1;
        @endphp
        @foreach($b as $key => $value)
            @if($value!='')
                <li class="breadcrumb-item {{$i==$prelast ? 'prelast-item' : 'd-none d-sm-inline-block'}}"><a href="{{$value}}">{!! $i==$prelast ? '<img class="breadcrumb_arrow d-sm-none" src="/public/svg/arrow_breadcrumbs.svg">' : ''!!}{{$key}}</a></li>
            @else
                <li class="breadcrumb-item active d-none d-sm-inline-block" aria-current="page">{!!$key!!}</li>
            @endif
            @php
                $i++;
            @endphp
        @endforeach
    </ol>
</nav>
