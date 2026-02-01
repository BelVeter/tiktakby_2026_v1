@php
    {{
      $menu=\bb\classes\TopMenu::getTopMenu(request()->lang);
    }}
@endphp
    <nav class="nav-left d-none d-md-block">
        @if($r = $menu->getRazdel(request()->razdel))
            <ul class="nav-left_sub-razdel_ul">
            @foreach($r->getSubRazdels(request()->subrazdel) as $subR)
                    <li>
                        <div class="sub-razdel-row"><a href="{{$subR->getUrlForPage(request()->lang)}}">{{$subR->getNameSubRazdelText()}}</a><button type="button" class="nav-left_arrow-btn"><img src="/public/svg/arrow_w_nav-left.svg"></button></div>
                        @if($cats=$subR->getCategories())
                            <ul class="cat-row {{$subR->getUrlSubRazdelName() == request()->subrazdel ? 'show' : ''}}">
                                <li><a href="{{$subR->getUrlForPage(request()->lang)}}"><span class="nl-cat-radio {{($subR->getUrlSubRazdelName() == request()->subrazdel && request()->category=='') ? 'current' : ''}}"></span><span class="nl-cat-text">Смотреть все</span></a></li>
                            @foreach($cats as $cat)
                                <li><a href="{{$cat->getUrlForPage(request()->lang)}}"><span class="nl-cat-radio {{request()->category===$cat->getCatUrlKey() ? 'current' : ''}}"></span><span class="nl-cat-text">{{$cat->getName()}}</span></a></li>
                            @endforeach
                            </ul>
                        @endif
                    </li>
            @endforeach
            </ul>
        @endif
    </nav>
