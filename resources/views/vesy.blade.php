@extends('layouts.app')

@section('page-title', 'Прокат детских весов')


@section('content')
    <div class="col-md-4 col-lg-3 navbar-container bg-light">
        <!-- Вертикальное меню -->
        <nav class="navbar navbar-expand-md navbar-light">
            <a class="navbar-brand" href="#">Категории:</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbar">
                <!-- Пункты вертикального меню -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link">На прогулке и в авто</span>
                    </li>
                    <li class="nav-item">
                        <ul>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Автокресла</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Коляски</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Слинги</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Переноски</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Аксессуары к коляскам</a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link" href="#link-3">Детская комната</span>
                    </li>
                    <li class="nav-item">
                        <ul>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Колыбели</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Кроватки</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Качели-колыбели</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Шезлонги</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Манежи-кровати</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Манежи игровые</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Стульчики для кормления</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#link-1">Ходунки и ходилки</a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#link-5">Ссылка 5</a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>

    <div class="col-md-8 col-lg-9 content-container">
        <div class="row">
            <div class="col">
                <div id="demo" class="carousel slide" data-ride="carousel">
                    <ul class="carousel-indicators">
                        <li data-target="#demo" data-slide-to="0" class="active"></li>
                        <li data-target="#demo" data-slide-to="1"></li>
                        <li data-target="#demo" data-slide-to="2"></li>
                    </ul>
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="/public/111.jpeg" alt="Los Angeles" width="1100" height="500">
                            <div class="carousel-caption">
                                <h3>Los Angeles</h3>
                                <p>We had such a great time in LA!</p>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="/public/222.jpeg" alt="Chicago" width="1100" height="500">
                            <div class="carousel-caption">
                                <h3>Chicago</h3>
                                <p>Thank you, Chicago!</p>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="/public/333.jpeg" alt="New York" width="1100" height="500">
                            <div class="carousel-caption">
                                <h3>New York</h3>
                                <p>We love the Big Apple!</p>
                            </div>
                        </div>
                    </div>
                    <a class="carousel-control-prev" href="#demo" data-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </a>
                    <a class="carousel-control-next" href="#demo" data-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </a>
                </div>
            </div>

        </div>
        <div class="row">
            <div class="col-12 col-md-6 col-lg-4 mt-5">
                <div class="card p-3 border rounded">
                    <div class="card-header rounded text-center l2_mod_header">Автокресло-люлька<br>Fisher-price Baby Carrier 0-13 кг</div>
                    <img src="/public/img/autokreslo_fisher_price_baby_carrier_0_13_top.jpeg" class="card-img-top py-3" alt="...">
                    <div class="card-body p-0">
                        <div class="row font-italic">
                            <div class="col-8 text-left pr-0 l2_mod_text1">Тариф за неделю</div>
                            <div class="col-4 text-right pl-0 l2_mod_text1">Недель</div>
                        </div>
                        <div class="row">
                            <div class="col-8 text-left">
                                <span class="l2_mod_text2">5.00 EUR</span><br>
                                <span class="font-italic" style="color: #00c400">(20.00 EUR всего)</span>
                            </div>
                            <div class="col-4 text-right my-auto">
                                <form class="middle">
                                    <input type="number" value="4" class="text-center l2_mod_num" style="font-size: 1.5rem" id="tov2_num_671" onchange="" min="1" step="1">
                                </form>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-lg btn-block my-2">К бронированию</button>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 mt-5">
                <div class="card p-3 border rounded" >
                    <div class="card-header rounded text-center l2_mod_header">Автокресло-люлька<br>Fisher-price Baby Carrier 0-13 кг</div>
                    <img src="/public/img/autokreslo_fisher_price_baby_carrier_0_13_top.jpeg" class="card-img-top py-3" alt="...">
                    <div class="card-body p-0">
                        <div class="row font-italic">
                            <div class="col-8 text-left pr-0 l2_mod_text1">Тариф за неделю</div>
                            <div class="col-4 text-right pl-0 l2_mod_text1">Недель</div>
                        </div>
                        <div class="row">
                            <div class="col-8 text-left">
                                <span class="l2_mod_text2">5.00 EUR</span><br>
                                <span class="font-italic" style="color: #00c400">(20.00 EUR всего)</span>
                            </div>
                            <div class="col-4 text-right my-auto">
                                <form class="middle">
                                    <input type="number" value="4" class="text-center l2_mod_num" style="font-size: 1.5rem" id="tov2_num_671" onchange="" min="1" step="1">
                                </form>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-lg btn-block my-2">К бронированию</button>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 mt-5">
                <div class="card p-3 border rounded" >
                    <div class="card-header rounded text-center l2_mod_header">Автокресло-люлька<br>Fisher-price Baby Carrier 0-13 кг</div>
                    <img src="/public/img/autokreslo_fisher_price_baby_carrier_0_13_top.jpeg" class="card-img-top py-3" alt="...">
                    <div class="card-body p-0">
                        <div class="row font-italic">
                            <div class="col-8 text-left pr-0 l2_mod_text1">Тариф за неделю</div>
                            <div class="col-4 text-right pl-0 l2_mod_text1">Недель</div>
                        </div>
                        <div class="row">
                            <div class="col-8 text-left">
                                <span class="l2_mod_text2">5.00 EUR</span><br>
                                <span class="font-italic" style="color: #00c400">(20.00 EUR всего)</span>
                            </div>
                            <div class="col-4 text-right my-auto">
                                <form class="middle">
                                    <input type="number" value="4" class="text-center l2_mod_num" style="font-size: 1.5rem" id="tov2_num_671" onchange="" min="1" step="1">
                                </form>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-lg btn-block my-2">К бронированию</button>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 mt-5">
            <div class="card p-3 border rounded" >
                <div class="card-header rounded text-center l2_mod_header">Автокресло-люлька<br>Fisher-price Baby Carrier 0-13 кг</div>
                <img src="/public/img/autokreslo_fisher_price_baby_carrier_0_13_top.jpeg" class="card-img-top py-3" alt="...">
                <div class="card-body p-0">
                    <div class="row font-italic">
                        <div class="col-8 text-left pr-0 l2_mod_text1">Тариф за неделю</div>
                        <div class="col-4 text-right pl-0 l2_mod_text1">Недель</div>
                    </div>
                    <div class="row">
                        <div class="col-8 text-left">
                            <span class="l2_mod_text2">5.00 EUR</span><br>
                            <span class="font-italic" style="color: #00c400">(20.00 EUR всего)</span>
                        </div>
                        <div class="col-4 text-right my-auto">
                            <form class="middle">
                                <input type="number" value="4" class="text-center l2_mod_num" style="font-size: 1.5rem" id="tov2_num_671" onchange="" min="1" step="1">
                            </form>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-lg btn-block my-2">К бронированию</button>
                </div>
            </div>
        </div>
        </div>
    </div>




@endsection
