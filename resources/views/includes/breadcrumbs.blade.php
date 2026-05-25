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
@if(isset($b) && count($b) > 0)
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    @php
      $__bKeys = array_keys($b);
      $__bVals = array_values($b);
      $__bCount = count($b);
    @endphp
    @for($__idx = 0; $__idx < $__bCount; $__idx++)
    {
      "@type": "ListItem",
      "position": {{ $__idx + 1 }},
      "name": "{{ addslashes(strip_tags($__bKeys[$__idx])) }}",
      "item": "{{ $__bVals[$__idx] !== '' ? $__bVals[$__idx] : url()->current() }}"
    }{{ $__idx < $__bCount - 1 ? ',' : '' }}
    @endfor
  ]
}
</script>
@endif
@endonce
