<?php

use bb\Base;
use bb\classes\UserRole;
use bb\models\LegalEntity;
use bb\models\Office;
use bb\models\User;

session_start();

ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/UserRole.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/KassaSet.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Kassa.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/LegalEntity.php'); //
//require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); //

echo Base::PageStartAdvansed('Главная.');
echo '<link href="/bb/stile.css" rel="stylesheet" type="text/css" />';
echo '<div class="row">'.$_SERVER['REMOTE_ADDR'].'</div>';
//Base::PostCheckVarDumpEcho();

Base::GetAllPostGlobal();

if (isset($_POST['action'])) {
    $action=Base::GetPost('action');

    switch ($action) {
        case 'Войти':
            if ($user=User::LogIn($log, $pass)) {
                //var_dump($user);
                $user->sessionRegister();
            }
            else {
                echo '<div class="alert-warning text-center">Неверный пароль или имя пользователя.</div>';
            }
            break;
        case 'role_register':
            $role_name=Base::GetPost('user_role');
            $role=UserRole::getRoleByRoleName($role_name, User::getCurrentUser()->id_user);
            //var_dump($role);
            $role->sessionRegister();
            break;
        case 'office_register':
                $office_num=Base::GetPost('office');
                $office_type=Base::GetPost('office_type');
                $of = Office::getOfficeByNumber($office_num, $office_type);

                //Base::varDamp($of);

                $of->sessionRegister();

            break;
        case 'Выйти':
            Base::logOut();
            break;
    }
}

if (!Base::isAllLoggedIn()) {
$prev_step_passed=true;
//login
    if (!User::isLoggedIn()) {
        $prev_step_passed=false;
        //потом убрать !!! и сделать правильный вылогин
        unset($_SESSION['office_s']);
        unset($_SESSION['role']);
        unset($_SESSION['user']);
        //echo 'Login run.<br>';
        echo Base::getLoginForm();
    }

//role chose
    if ($prev_step_passed && !UserRole::isRoleChosen()) {//is role chosen
        $prev_step_passed=false;
        //echo 'Role run.<br>';
        $roles = UserRole::getRolesForUser(User::getCurrentUser()->id_user);
        //Base::varDamp($roles);
        if (count($roles) == 1) {
            $roles[0]->sessionRegister();
            $prev_step_passed=true;
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
//Base::varDamp($_SESSION);
//office chose
    if ($prev_step_passed && !Office::isOfficeChosen()) {//is office chosen
        $prev_step_passed=false;
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
            }
            else{
                echo 'Ошибка: нет офиса для выбора.';
            }

        }
        elseif (count($offices) == 1) {
            //echo 'off session register started';
            //Base::varDamp($offices);
            $offices[0]->sessionRegister();
            $prev_step_passed=true;
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
        $_SESSION['svoi']=8941;
    }

    if (!Base::isAllLoggedIn()) {//only if the whole steps are not passed. In other case after office successful login - we stop at white page until refresh.
        echo Base::PageEndHTML();
        die();
    }

}//end of login

//change of office for owners
if (isset($_POST['office_change_to']) && User::getCurrentUser()->isOwner()) {

    $office_num=Base::GetPost('office_change_to');
    $office_type='office';
    $of = Office::getOfficeByNumber($office_num, $office_type);

    //Base::varDamp($of);

    $of->sessionRegister();

   // $of_ch_n=Base::GetPost('office_change_to');
   // Office::changeOfficeLoggeIn($of_ch_n);
}


?>

    <nav class="navbar navbar-expand-md navbar-light bg-light">
        <a class="navbar-brand" href="/bb/index.php">Главная</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mr-auto">
                <!--<li class="nav-item active">
                    <a class="nav-link" href="#">Home <span class="sr-only">(current)</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/bb/b_user.php">Новый пользователь</a>
                </li>
                -->
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item text-center">
                    <?php echo Base::officeLoggedInfo2(); ?>
                </li>
                <li class="nav-item text-center">
                    <?php echo Base::getLoggedInAndExit(); ?>
                </li>
            </ul>
        </div>

    </nav>
    <div class="row">
        <div class="col alert-danger h2 text-center" id="zv_div"></div>
    </div>

<?php

if ($_SESSION['level']!=3) {

    //echo \bb\Base::sum2words('22325.55');

    echo'


		
<div class="top_menu">
	<a class="div_item" href="/bb/dogovor_new.php">Очень новый договор/продление</a>
	<a class="div_item" href="/bb/cur_page2.php">Страница курьера</a>
	<!--<a class="div_item" style="background-color: blue; color: white;" href="/bb/cur_page2.php">Быстрый курьер</a>-->
	<a class="div_item" href="/bb/rda.php" style="background-color:#03F; color:#FFF">Все сделки</a>
	<!--<a class="div_item" href="/bb/rent_deals_all.php">Все сделки (старые)</a>-->
	<a class="div_item" href="/bb/rent_orders.php">Брони</a>
	<a class="div_item" href="/bb/obrabotka.php">Обработка</a>
	<a class="div_item" href="/bb/doh-rash.php">Расходы</a>
<br />
		<form method="post" action="/bb/kr_baza_new.php" style="display:inline-block;">
			<input type="hidden" name="cat_id" value="2" /><input type="submit" value="КАРНАВАЛЫ" style="width:100px; height:35px; background-color:green; color:white" />
		</form>
<br />
		
		
		
	<a class="div_item" href="/bb/deals_arch.php">Завершенные сделки/архив</a><br /><br />
	<a class="div_item" href="/bb/kr_baza.php"><strong>Товары к возврату</strong></a>
	<a class="div_item" href="/bb/kr_baza_new.php"><strong>Все товары</strong></a>
	<a class="div_item" href="/bb/scanner_tovar.php" style="background-color:#060; color:#FFF"><strong>Карточка товара</strong></a>
	<a class="div_item" href="/bb/kb.php"><strong>Карнавальные брони</strong></a>
	<a class="div_item" href="/bb/kb_lines.php"><strong>Карнавальная таблица</strong></a>
<!--	<a class="div_item" href="/bb/karn_free.php"><strong>Свободные по дате</strong></a> -->	
	<a href="/bb/zv_ch.php">перейти к звонкам</a>
	
</div>

<br />
<!--
<div class="top_menu">
	<a class="div_item" href="/bb/karn_zakazs.php">Карнавальные паспортные данные</a> 
</div> -->
<br /><br />
		
';
}

if ($_SESSION['level']>4 || $_SESSION['level']==3) {

    echo'	
<div class="top_menu">
	'.($_SESSION['level']!=3 ? '<a class="div_item" href="/bb/tovar_new.php">Новый товар</a><a class="div_item" href="/bb/tovar_new_mod.php">Новая модель</a>' : '').'
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
'.($_SESSION['level']!=3 ? '
<div class="top_menu">
	<a class="div_item" href="/bb/reports.php">Сводный отчет.</a>
	<a class="div_item" href="/bb/gr.php" style="background-color: #acf398">Сводный график.</a>
	<a class="div_item" href="/bb/tov_analytics.php">Анализ категорий.</a>
	<a class="div_item" href="/bb/qrs.php" style="background-color: #00c1fd">QR коды.</a>
	<a class="div_item" href="/bb/report_insta.php" style="background-color: #00c1fd">Инста-отчет.</a>
	
	<a class="div_item" href="/bb/kassas.php">Касса. Попытка №1.</a>
	<a class="div_item" href="/bb/doh_rash_book.php">Книга доходов и расходов.</a>
	<a class="div_item" href="/bb/doh_rash_book_kr.php">Книга доходов и расходов2.</a>
	<a class="div_item" href="/bb/zpl.php">Расчет з\пл.</a>
	<a class="div_item" href="/bb/kch.php">Сохраненные остатки.</a>
</div> <br />' : '').'

';
}

require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show2.php'); // включаем подключение к базе данных
echo Base::PageEndHTML();
?>