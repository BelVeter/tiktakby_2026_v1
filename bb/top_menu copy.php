<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <!--<a class="navbar-brand" href="#">second try</a> -->
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link btn btn-secondary mr-2" style="background-color: #656d78" href="/bb/dogovor_new.php">Клиенты</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-danger mr-2" style="background-color: #af97eb; border-color: #c9bcf0" href="/bb/dogovor_new.php">Товары</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-info mr-2" style="background-color: #73a9ec" href="/bb/rda.php">Сделки дня</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-outline-dark mr-2"  href="/bb/rent_orders.php">Брони</a>
            </li>

            <li class="nav-item">
                <a class="nav-link btn mr-2" style="color: #89b5e5" href="/bb/cur_page2.php">Курьер</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-info mr-2" style="background-color: #89b5e5" href="/bb/zv_ch.php">Звонки</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-success mr-2" style="background-color: #a0d469" href="/bb/kb.php">Карнавал</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link btn btn-warning mr-2 dropdown-toggle" style="background-color: #fccd53" href="#" id="navbarDropdown1" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Архив</a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdown1">
                    <a class="dropdown-item" href="/bb/deals_arch.php">Завершенные сделки</a>
                    <a class="dropdown-item" href="/bb/rent_orders_arch.php">Удаленные брони</a>
                </div>
            </li>
        </ul>
        <?php use bb\Base;
            echo Base::officeLoggedInfo2();
            echo Base::getLoggedInAndExit();
        ?>
<!--        <form class="form-inline my-2 my-lg-0">-->
<!--            <input class="form-control mr-sm-2" type="search" placeholder="Search" aria-label="Search">-->
<!--            <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Search</button>-->
<!--        </form>-->
    </div>

</nav>