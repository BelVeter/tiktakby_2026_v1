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
@once
@if(isset($b) && is_array($b) && count($b) > 0)
@php
    $__ldItems = [];
    $__idx = 1;
    foreach($b as $key => $value) {
        $__ldItems[] = [
            '@type' => 'ListItem',
            'position' => $__idx,
            'name' => strip_tags($key),
            'item' => $value !== '' ? $value : url()->current()
        ];
        $__idx++;
    }
    $__ldData = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $__ldItems
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($__ldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@endif
@endonce
