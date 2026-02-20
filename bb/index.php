<?php

use bb\Base;
use bb\classes\UserRole;
use bb\models\LegalEntity;
use bb\models\Office;
use bb\models\User;

session_start();

ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/Office.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/UserRole.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/KassaSet.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Permission.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/Kassa.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/LegalEntity.php'); //
//require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); //

//Base::PostCheckVarDumpEcho();

Base::GetAllPostGlobal();

if (isset($_POST['action'])) {
    $action = Base::GetPost('action');

    switch ($action) {
        case 'Войти':
            if ($user = User::LogIn($log, $pass)) {
                //var_dump($user);
                $user->sessionRegister();
                setcookie('tt_is_logged_in', '1', time() + 86400 * 30, '/');
            } else {
                echo '<div class="alert-warning text-center">Неверный пароль или имя пользователя.</div>';
            }
            break;
        case 'role_register':
            $role_name = Base::GetPost('user_role');
            $role = UserRole::getRoleByRoleName($role_name, User::getCurrentUser()->id_user);
            //var_dump($role);
            $role->sessionRegister();
            break;
        case 'office_register':
            $office_num = Base::GetPost('office');
            $office_type = Base::GetPost('office_type');
            $of = Office::getOfficeByNumber($office_num, $office_type);

            //Base::varDamp($of);

            $of->sessionRegister();

            break;
        case 'Выйти':
            Base::logOut();
            break;
    }
}

echo Base::PageStartAdvansed('Главная.');

echo '<link href="/bb/stile.css?v=3" rel="stylesheet" type="text/css" />
      <link rel="stylesheet" href="/bb/assets/styles/cur_style.css?v=1">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">

';

echo Base::getBarCodeReaderScript('', array('target' => '/bb/scanner_tovar.php'));

if (!Base::isAllLoggedIn()) {
    $prev_step_passed = true;
    //login
    if (!User::isLoggedIn()) {
        $prev_step_passed = false;
        //потом убрать !!! и сделать правильный вылогин
        unset($_SESSION['office_s']);
        unset($_SESSION['role']);
        unset($_SESSION['user']);
        //echo 'Login run.<br>';
        echo Base::getLoginForm();
    }

    //role chose
    if ($prev_step_passed && !UserRole::isRoleChosen()) {//is role chosen
        $prev_step_passed = false;
        //echo 'Role run.<br>';
        $roles = UserRole::getRolesForUser(User::getCurrentUser()->id_user);
        //Base::varDamp($roles);
        if (count($roles) == 1) {
            $roles[0]->sessionRegister();
            $prev_step_passed = true;
        } else {
            //        echo 'roles num: '.count($roles).'<br>';
//        Base::varDamp($roles);
            echo '
            <form action="index.php" method="post">
                <div class="alert-info text-center h1">Выберите роль:</div>
                <input type="hidden" name="action" value="role_register">

                <div class="custom-control custom-radio text-center">';
            foreach ($roles as $role) {
                echo '
                        <input type="radio" name="user_role" style="display:none;" value="' . $role->role . '" id="role_' . $role->id . '" onchange="this.form.submit();">
                            <label for="role_' . $role->id . '" style="margin: 0"><div class="btn btn-lg  btn-primary" style="margin: 5px 0;">' . $role->getRoleTextName() . '</div></label>
                        ';
            }
            echo '
                </div>
            </form>

        ';

        }
    }//end of role chose
    //echo 'iscur:'.User::getCurrentUser()->isCourier();
//Base::varDamp($_SESSION);
//office chose
    if ($prev_step_passed && !Office::isOfficeChosen() && !UserRole::getCurrentRole()->isCurier()) {//is office chosen
        $prev_step_passed = false;
        //echo 'Office run.<br>';
        if (!UserRole::getCurrentRole()->isCurier()) {//non curier
            if (User::getCurrentUser()->isIpRestricted()) {
                $offices = Office::getAllOffices('office', $_SERVER['REMOTE_ADDR']);//chose possible offices for respective IP
            } else {
                $offices = Office::getAllOffices('office');
            }
        } else {//curier
            //echo '1 if run.<br>';
            $offices = Office::getAllOffices('courier');
            //Base::varDamp($offices);
        }

        if (count($offices) < 1) {
            if (User::getCurrentUser()->isIpRestricted()) {
                echo '
                <div class="row">
                    <div class="col alert-danger text-center h3">Ошибка: С учетом ограничений по IP нет возможности выбрать офис. <br> Убедитесь, что вы подключились через рабочий Wi-Fi, либо свяжитесь с администратором</div>
                </div>
                </div>
                <div class="row">
                    <div class="col text-center h3"><form method="post" action="index.php" style="margin: 0;"><input type="submit" class="btn btn-danger btn-lg" name="action" value="Выйти" /></form></div>
                </div>
                    ';
            } else {
                echo 'Ошибка: нет офиса для выбора.';
            }

        } elseif (count($offices) == 1) {
            //echo 'off session register started';
            //Base::varDamp($offices);
            $offices[0]->sessionRegister();
            $prev_step_passed = true;
        } else {//more than 1 office
            echo '
        <div class="alert-info text-center h1">Выберите офис:</div>
        <form action="index.php" method="post">
            <input type="hidden" name="action" value="office_register">
            <input type="hidden" name="office_type" value="' . $offices[0]->type . '">

            <div class="custom-control custom-radio text-center">';
            foreach ($offices as $office) {
                echo '
                    <input type="radio" name="office" style="display:none;" value="' . $office->number . '" id="office_' . $office->id_office . '" onchange="this.form.submit();">
                        <label for="office_' . $office->id_office . '" style="margin: 0"><div class="btn btn-lg  btn-warning" style="margin: 5px 0;">' . $office->getShortName() . '</div></label>
                    ';
            }
            echo '
            </div>
        </form>

        ';
        }
    }//end of office check

    //LE chose
//    if ($prev_step_passed && !LegalEntity::isLeChosen()){//chosing LE
//
//    }
//KassaSet chose

    if ($prev_step_passed) {
        $_SESSION['svoi'] = 8941;
    }

    if (!Base::isAllLoggedIn()) {//only if the whole steps are not passed. In other case after office successful login - we stop at white page until refresh.
        //echo '</div>';
        echo '<div class="row">
                <div class="col"><p class="small">' . $_SERVER['REMOTE_ADDR'] . '</p></div>
              </div>';

        echo Base::PageEndHTML();
        die();
    }

}//end of login

//change of office for owners
if (isset($_POST['office_change_to']) && User::getCurrentUser()->isOwner()) {

    $office_num = Base::GetPost('office_change_to');
    $office_type = 'office';
    $of = Office::getOfficeByNumber($office_num, $office_type);

    //Base::varDamp($of);

    $of->sessionRegister();

    // $of_ch_n=Base::GetPost('office_change_to');
    // Office::changeOfficeLoggeIn($of_ch_n);
}

$role = UserRole::getCurrentRole();
?>

<?php include_once($_SERVER['DOCUMENT_ROOT'] . '/bb/top_menu.php'); ?>

<a href="/bb/cur_viezdy.php" style="background-color: #00c400; font-size: 20px; padding: 15px;">Ссылка для курьеров.
    Тестовая версия.</a>
<div class="container-menu">
    <a class="menu-link" href="/bb/dogovor_new.php">
        <img src="/bb/assets/images/png/menu-clients.png">
        <span>Клиенты</span>
    </a>
    <a class="menu-link" href="/bb/rda.php">
        <img src="/bb/assets/images/png/menu-deals.png">
        <span>Сделки</span>
    </a>
    <a class="menu-link" href="/bb/deals_arch.php">
        <img src="/bb/assets/images/png/menu-archive.png">
        <span>Архив</span>
    </a>
    <a class="menu-link" href="/bb/cur_page2.php">
        <img src="/bb/assets/images/png/menu-cur.png">
        <span>Курьер</span>
    </a>
    <a class="menu-link" href="/bb/kr_baza_new.php">
        <img src="/bb/assets/images/png/menu-tovar.png">
        <span>Товары</span>
    </a>
    <a class="menu-link" href="/bb/rent_orders.php">
        <img src="/bb/assets/images/png/menu-broni.png">
        <span>Брони</span>
    </a>
    <a class="menu-link" href="/bb/rent_zayavk.php">
        <img src="/bb/assets/images/png/tasks.png">
        <span>Заявки</span>
    </a>
    <a class="menu-link" href="/bb/kb.php">
        <img src="/bb/assets/images/png/menu-karnaval.png">
        <span>Карнавал</span>
    </a>
    <a class="menu-link" href="/bb/zv_ch.php">
        <img src="/bb/assets/images/png/menu-calls.png">
        <span>Заказы звонка</span>
    </a>
    <a class="menu-link" href="/bb/working_hours.php">
        <img src="/bb/assets/images/png/grafiki.png">
        <span>График</span>
    </a>
    <a class="menu-link" href="/bb/task_management.php">
        <img src="/bb/assets/images/png/tasks.png">
        <span>Задачи</span>
    </a>
    <a class="menu-link" href="/bb/announcements.php">
        <img src="/bb/assets/images/png/announcement.png" style="max-width: 50px">
        <span>Объявление</span>
    </a>
    <a class="menu-link" href="/bb/subscriptions.php">
        <img src="/bb/assets/images/jpg/subscr.jpg" style="width: 50px;">
        <span>Подписки</span>
    </a>

</div>
<!-- смена для курьеров
<div class="cur-container">
    <h1>Доброго дня, Кирилл!</h1>
    <ul class="cur-start-menu">
        <li><button>Расчет зарплаты</button></li>
        <li><button>График работы</button></li>
        <li><button>Расчет зарплаты</button></li>
        <li><button>График отпусков</button></li>
    </ul>
    <div class="cur-main-shift">
        <span>Открыть смену</span><button class="cur-toggle-btn"></button>
    </div>
    <div class="cur-footer">
        <ul>
            <li>
                <img src="/bb/assets/images/png/cur_profile.png" alt="Cur profile" height="28" width="28">
                <span>Профиль</span>
            </li>
            <li>
                <img src="/bb/assets/images/png/car.png" alt="Car" height="28" width="45">
                <span>Авто</span>
            </li>
            <li>
                <img src="/bb/assets/images/png/list.png" alt="List" width="25" height="32">
                <span>Выезд</span>
            </li>
            <li>
                <img src="/bb/assets/images/png/kassa.png" alt="Kaccа" width="28" height="28">
                <span>Касса</span>
            </li>
        </ul>
    </div>
</div>
-->
<?php

if (($_SESSION['level'] > 4 || $_SESSION['level'] == 3) && !$role->isCurier()) {

    echo '
 <div class="container-menu">
        <a class="menu-link" href="/bb/tovar_new.php">
            <img src="/bb/assets/images/png/menu-newtovar.png">
            <span>Внести товар</span>
        </a>
        <a class="menu-link" href="/bb/b_users.php">
            <img src="/bb/assets/images/png/menu-users.png">
            <span>Пользователи</span>
        </a>
        <a class="menu-link" href="/bb/rent_tarifs.php">
            <img src="/bb/assets/images/png/menu-tarifs.png">
            <span>Тарифы</span>
        </a>
        <a class="menu-link" href="/bb/reports.php">
            <img src="/bb/assets/images/png/menu-reports.png">
            <span>Отчеты</span>
        </a>
        <a class="menu-link" href="/bb/qrs.php">
            <img src="/bb/assets/images/png/menu-qr.png">
            <span>QR-коды</span>
        </a>
    </div>
';
}

if (User::getCurrentUser()->getId() == 26) {

    echo '
 <div class="container-menu">
        <a class="menu-link" href="/bb/tovar_new.php">
            <img src="/bb/assets/images/png/menu-newtovar.png">
            <span>Внести товар</span>
        </a>
        <a class="menu-link" href="/bb/rent_tarifs.php">
            <img src="/bb/assets/images/png/menu-tarifs.png">
            <span>Тарифы</span>
        </a>
        <a class="menu-link" href="/bb/qrs.php">
            <img src="/bb/assets/images/png/menu-qr.png">
            <span>QR-коды</span>
        </a>
    </div>
';
}

echo '
<div class="top_menu">
    ' . (User::getCurrentUser()->hasPermission(1) ? '
    <a class="div_item" href="/bb/razdel_manage.php" style="background-color: mediumpurple; color: black;">Работа с меню</a>
    <a class="div_item" href="/bb/page_management.php" style="background-color: mediumpurple; color: black;">Страницы</a>
    <a class="div_item" href="/bb/favorite_tovars_management.php" style="background-color: mediumpurple; color: black;">Популярные товары</a>
    ' : '') . '
    ' . (User::getCurrentUser()->hasPermission(2) ? '<a class="div_item" href="/bb/bulk_actions.php" style="background-color: orange; color: black;">Массовые операции</a>' : '') . '
</div>
';

if (User::getCurrentUser()->isManagement()) {

    echo '
    <div class="top_menu">
        <a class="div_item" href="/bb/tov_analytics.php">Анализ категорий.</a>
    </div>
    ';

}

if (User::getCurrentUser()->hasPermission(3)) {
    echo '
    <div class="top_menu">
	' . (($_SESSION['level'] != 3) ? '<a class="div_item" href="/bb/tovar_new.php">Новый товар</a><a class="div_item" href="/bb/tovar_new_mod.php">Новая модель</a>' : '') . '
	<a class="div_item" href="/bb/tovar_rent_all.php">Просмотр всех товаров</a>
</div>
<br /><br />
<div class="top_menu">
	<a class="div_item" href="/bb/b_user.php" style="background-color: #00c1fd">Завести нового пользователя</a><a class="div_item" href="/bb/b_users.php" style="background-color: #00c1fd">Просмотр всех пользователей</a>
</div>
<br><br>

<div class="top_menu">
	<a class="div_item" href="/bb/rent_tarifs.php">Работа с тарифами</a><a class="div_item" href="/bb/cl_check.php">Чистка клиентов</a><a class="div_item" href="/bb/staf_track.php">Входы-выходы</a>
</div>
<br /><br />
' . (User::getCurrentUser()->hasPermission(3) ? '
<div class="top_menu">
	<a class="div_item" href="/bb/reports.php">Сводный отчет.</a>
	<a class="div_item" href="/bb/gr.php" style="background-color: #acf398">Сводный график.</a>

	<a class="div_item" href="/bb/sales_breakdown.php" style="background-color: #00FF00">Динамика выручки.</a>
	<a class="div_item" href="/bb/dohrash2.php" style="background-color: #00FF00">Свод доходов и расходов.</a>
	<a class="div_item" href="/bb/cat_analysis.php" style="background-color: #00FF00">Анализ выдачи по категориям.</a>
	<a class="div_item" href="/bb/tovar_report.php" style="background-color: #00FF00">Динамика товаров.</a>
	<a class="div_item" href="/bb/tovar_dinamics.php" style="background-color: #00FF00">Покупка/продажа товаров детали.</a>

	<a class="div_item" href="/bb/tov_analytics.php">Анализ категорий.</a>
	<a class="div_item" href="/bb/qrs.php" style="background-color: #00c1fd">QR коды.</a>
	<a class="div_item" href="/bb/report_insta.php" style="background-color: #00c1fd">Инста-отчет.</a>

	<a class="div_item" href="/bb/kassas.php">Касса. Попытка №1.</a>
	<a class="div_item" href="/bb/doh_rash_book.php">Книга доходов и расходов.</a>
	<a class="div_item" href="/bb/doh_rash_book_kr.php">Книга доходов и расходов2.</a>
	<a class="div_item" href="/bb/zpl.php">Расчет з\пл.</a>
	<a class="div_item" href="/bb/kch.php">Сохраненные остатки.</a>
</div> <br />' : '') . '';
}


if (User::getCurrentUser()->isOwner()) {
    echo '
    <div class="top_menu">
        <a class="div_item" href="/bb/orphan_models.php" style="background-color: darkred; color: white;">Очистка висячих моделей</a>
        <a class="div_item" href="/bb/validate_images.php" style="background-color: #8b4513; color: white;">Валидация картинок</a>
        <a class="div_item" href="/bb/redirects.php" style="background-color: #0d6efd; color: white;">Перенаправления</a>
    </div>';
}

echo Base::PageEndHTML();
?>