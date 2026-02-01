@php
    {{
      $menu=\App\MyClasses\CatMenuItem::getAllMenu();
    }}
@endphp
    <nav class="navbar navbar-expand-md navbar-light w-100 py-0" style="background-color: white; z-index: 3">
        <!--
        <a class="navbar-brand" href="#">Категории:</a>
        <button class="navbar-toggler navbar-dark" style="background-color: #025d9f; height: 45px; color:white" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <img src="/public/img/menu-dropdown-white.svg" class="cat-menu-lines"><span class="pl-2">Категории</span>
        </button>
        -->
        <div class="collapse navbar-collapse left-menu" id="navbar" style="border-top: double">
            <!-- Пункты вертикального меню -->
            <ul class="navbar-nav">
                @foreach($menu as $m)
                    <li class="nav-item level1">
                        <span class="nav-link" style="font-weight: bold; font-size: 1.1rem;" >{{$m->getCatNameText()}} <img class="lm_cross" src="/public/plus.png"></span>
                    </li>
                    <li class="nav-item level2" {!! ($m->hasChildByCatUrlName(\Illuminate\Support\Facades\Request::route('cat')) ? '' : 'style="display: none"')!!}>
                        <ul>
                            @foreach($m->getChildItems() as $ch)
                                <li class="nav-item">
                                    <a class="nav-link" href="{{$ch->getUrl()}}" {!! ($ch->isCurrent(\Illuminate\Support\Facades\Request::route('cat')) ? 'style="text-decoration: underline"' : '')!!}>{{$ch->getCatNameText()}}</a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>
