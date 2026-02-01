<?php
session_start();

use bb\Base;
use bb\models\User;

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/UserRole.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); //


echo Base::PageStartAdvansed('Список пользователей');

//access check
Base::loginCheck();

if (!User::getCurrentUser()->isAdmin()) {//if not admin - no access
    echo '<div style="color: red; font-size: 16px;">Нет прав на доступ к данной странице.</div>';
    echo Base::PageEndHTML();
    die();
}
//end of access check


?>
    <!-- Top nav menu -->
    <nav class="navbar navbar-expand-md navbar-light bg-light">
        <a class="navbar-brand" href="/bb/index.php">Главная</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mr-auto">
                <!--<li class="nav-item active">
                    <a class="nav-link" href="#">Home <span class="sr-only">(current)</span></a>
                </li>-->
                <li class="nav-item">
                    <a class="nav-link" href="/bb/b_user.php">Новый пользователь</a>
                </li>
            </ul>
        </div>
    </nav>
    <!-- END of Top nav menu -->
<?php

$user_status=1;

Base::GetAllPostGlobal();

$users=User::getUsers(array('active'=>$user_status));

//Base::varDamp($users);
echo '<div class="row">';
echo '
    <form method="post" action="b_users.php">
        <select name="user_status" onchange="this.form.submit()">
            <option value="1" '.Base::sel_d($user_status, 1).'>Активные пользователи</option>
            <option value="0" '.Base::sel_d($user_status, 0).'>Архивные пользователи</option>
        </select>
    </form>
';
echo '</div>';


echo '<div class="row">';
echo '<table class="table">
    <tr>
        <th scope="col">id</th>
        <th scope="col">логин</th>
        <th scope="col">Имя</th>
        <th scope="col">Роль</th>
        <th scope="col">Контроль IP?</th>
        <th scope="col">Делает доставки?</th>
        <th scope="col">Зарплата?</th>
        <th scope="col">Статус</th>
        <th scope="col">Действия</th>
    </tr>';

foreach ($users as $u) {
    echo '
    <tr>
        <th scope="row" style="background-color: '.$u->getColor().'">'.$u->id_user.'</th>
        <td>'.$u->login.'</td>
        <td>'.$u->user_name.'</td>
        <td>'.$u->getRoleName().'</td>
        <td>'.($u->ip_yn==1 ? 'Да' : 'Нет').'</td>
        <td>'.($u->delivery==1 ? 'Да' : 'Нет').'</td>
        <td>'.($u->zp_yn==1 ? 'Да' : 'Нет').'</td>
        <td>'.($u->active==1 ? 'Активный' : 'Архивный').'</td>
        <td>
            <form method="post" action="b_user.php" class="form-inline">
                <input type="hidden" name="user_id" value="'.$u->id_user.'">
                <input type="submit" name="action" value="Редактировать" class="btn btn-info">
            </form>
        </td>
    </tr>';
}
    echo '
</table>';
echo '</div>';

echo Base::PageEndHTML();




?>
