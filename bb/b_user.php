<?php
session_start();

ini_set("display_errors",1);
error_reporting(E_ALL);

use bb\Base;
use bb\models\User;


require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //

echo Base::PageStartAdvansed('Пользователь');

Base::loginCheck();
?>




<?php

echo '

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
            </li>-->';
            if (User::getCurrentUser()->isAdmin()) {
                echo '
                <li class="nav-item">
                    <a class="nav-link" href="/bb/b_users.php">Все пользователи</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/bb/b_user.php">Ввод нового пользователя</a>
                </li>
                ';
            }

            echo '
        </ul>
    </div>
</nav>
<!-- END of Top nav menu -->

';



//echo Base::PostCheck();
//Base::varDamp($_SESSION);
$u=new User();

if (isset($_POST['action'])) {
    $action=Base::GetPost('action');

    switch ($action){
        case 'Редактировать':
            if (isset($_POST['user_id'])) {
                $u=User::getUserById($_POST['user_id']);
            }
            break;

        case 'Сохранить нового пользователя':
          echo 'start';
            $u = new User();

            $u->login=Base::GetPost('login');
            $u->password = Base::GetPost('password');
            $u->user_name = Base::GetPost('user_name');
            $u->ip_yn = Base::GetPost('ip_yn');
            $u->zp_yn = Base::GetPost('zp_yn');
            $u->delivery = Base::GetPost('delivery');
            $u->active = Base::GetPost('active');
            $u->main_role = Base::GetPost('main_role');
            $u->setColor(Base::GetPost('color'));

            $u->level=0;
            if (User::isLoginExists($u->login)) {
                echo '<div style="color: red;font-size: 16px;">Ошибка: пользователь с таким именем уже существует</div>';
            }
            else {
                $u->save();
            }

            break;
        case 'Обновить':
            $u = User::getUserById(Base::GetPost('user_id'));
            $pass_error=false;
            //var_dump(User::isPasswordCorrect(Base::GetPost('user_id'), Base::GetPost('act_password')));

            if (isset($_POST['act_password']) && $_POST['act_password']!='') {
                //echo 'акт сработал';
                if (User::isPasswordCorrect(Base::GetPost('user_id'), Base::GetPost('act_password')) && Base::GetPost('new_password')==Base::GetPost('new_password_2')) {
                    //echo 'новый пароль присвоен';
                    $u->password=Base::GetPost('new_password');
                }
                else{
                    $pass_error=true;
                }
            }

            $u->login=Base::GetPost('login');
            $u->user_name = Base::GetPost('user_name');
            $u->ip_yn = Base::GetPost('ip_yn');
            $u->zp_yn = Base::GetPost('zp_yn');
            $u->delivery = Base::GetPost('delivery');
            $u->active = Base::GetPost('active');
            $u->main_role = Base::GetPost('main_role');
            $u->setColor(Base::GetPost('color'));

            //$u->level=0;

            if ($pass_error) {
                echo '<div style="font-size: 16px; color: red;">Ошибка: текущий пароль не правильный..</div>';
            }
            else{
                $u->update();
            }


            break;
    }
}

if ($u->id_user<1 && !User::getCurrentUser()->isAdmin()) {
    echo '<div style="font-size: 16px; color: red;">Ошибка: нет прав доступа к странице.</div>';
    echo Base::PageEndHTML();
    die();
}


//$u = User::getUserById(3);
//$u->id_user=null;
//Base::varDamp($u);

echo '<form method="post" action="b_user.php">';
echo '<div class="form-group">';

echo '<div class="form-group">';

    if ($u->id_user>0) {
        echo '<label for="user_id">id:</label>';
        echo '<input class="form-control col-sm-3" type="text" id="user_id" name="user_id" value="'.$u->id_user.'" readonly>';
    }

    echo '<label for="login">Логин:</label>';
    echo '<input class="form-control col-sm-3" type="text" id="login" name="login" value="'.$u->login.'">';

    if ($u->id_user<1) {//=new user
        echo '<label for="password">Первоначальный пароль:</label>';
        echo '<input class="form-control col-sm-3" type="text" id="password" name="password" value="">';
    }
    elseif (User::getCurrentUser()->isAdmin() || User::getCurrentUser()->id_user==$u->id_user){

        echo '<input type="button" class="btn btn-info" onclick="pas_show();" id="pas_show_but" value="Сменить пароль"><br>';
        echo '<div id="pas_ch" style="display: none;">';
            echo '<label for="act_password">Текущий пароль:</label>';
            echo '<input class="form-control col-sm-3" type="password" id="act_password" name="act_password" value="">';

            echo '<label for="new_password">Новый пароль:</label>';
            echo '<input class="form-control col-sm-3" type="password" id="new_password" name="new_password" value="">';

            echo '<label for="new_password_2">Новый пароль еще раз (контроль):</label>';
            echo '<input class="form-control col-sm-3" type="password" id="new_password_2" name="new_password_2" value="">';
        echo '</div>';
    }

    echo '<label for="user_name">Имя (отражается в базе):</label>';
    echo '<input class="form-control col-sm-3" type="text" id="user_name" name="user_name" value="'.$u->user_name.'">';

    echo '<label for="ip_yn">Вход только с офиса (контроль по IP)?:</label>';
    echo '<select class="form-control col-sm-3" name="ip_yn" id="ip_yn">
        <option value="1" '.Base::sel_d($u->ip_yn, '1').'>Да</option>
        <option value="0" '.Base::sel_d($u->ip_yn, '0').'>Нет</option>
    </select>';

    echo '<label for="zp_yn">Начисление зарплаты?:</label>';
    echo '<select class="form-control col-sm-3" name="zp_yn" id="zp_yn">
        <option value="1" '.Base::sel_d($u->zp_yn, '1').'>Да</option>
        <option value="0" '.Base::sel_d($u->zp_yn, '0').'>Нет</option>
    </select>';

    echo '<label for="delivery">Осуществляет доставки?:</label>';
    echo '<select class="form-control col-sm-3" name="delivery" id="delivery">
        <option value="1" '.Base::sel_d($u->delivery, '1').'>Да</option>
        <option value="0" '.Base::sel_d($u->delivery, '0').'>Нет</option>
    </select>';

    echo '<label for="active">Статус пользователя:</label>';
    echo '<select class="form-control col-sm-3" name="active" id="active">
        <option value="1" '.Base::sel_d($u->active, '1').'>Активный</option>
        <option value="0" '.Base::sel_d($u->active, '0').'>Архивный (не работает)</option>
    </select>';

    echo '<label for="main_role">Роль пользователя:</label>';
    echo '<select class="form-control col-sm-3" name="main_role" id="main_role">';

        if ($u->main_role=='' || is_null($u->main_role)) echo '<option value="0" '.Base::sel_d($u->main_role, '0').'>выберите роль</option>';

              echo '
              <option value="consultant" '.Base::sel_d($u->main_role, "consultant").'>Консультант</option>
              <option value="courier" '.Base::sel_d($u->main_role, "courier").'>Курьер</option>
              <option value="accountant" '.Base::sel_d($u->main_role, "accountant").'>Бухгалтер</option>
              <option value="owner" '.Base::sel_d($u->main_role, "owner").'>Собственник</option>
              <option value="coder" '.Base::sel_d($u->main_role, "coder").'>Программист</option>
    </select>';
              echo '
          <label for="exampleColorInput" class="form-label">Color picker</label>
          <input type="color" class="form-control form-control-color" name="color" id="color" value="'.$u->getColor().'" style="width: 100px; height: 50px;" title="Выберите цвет">
              ';
echo '</div>';

echo '<div class="form-group">';

if ($u->id_user<1){
    echo '<input type="submit" id="save_new_btn" name="action" class="btn btn-success" value="Сохранить нового пользователя">';
}
else {
    echo '<input type="submit" name="action" class="btn btn-success" value="Обновить" onclick="return update_check();">';
}

echo '</div>';
echo '</form>';
?>

<script>
  function pas_show() {
    if ($('#pas_show_but').val()=='Сменить пароль'){
      $('#pas_ch').show();
      $('#pas_show_but').prop("value", 'Отменить изменение пароля');
    }
    else {
      $('#pas_ch').hide();
      $('#pas_show_but').prop("value", 'Сменить пароль');
    }
  }

  function update_check() {
    //alert('start');
    $alert='';
    $rez=true;

    if ($('#login').val().length<3) {
      $alert+=' login должен быть не короче 3-х символов';
      $rez=false;
    }

    if ($('#pas_show_but').val()=='Отменить изменение пароля') {
      if ($('#act_password').val().length<1) {
        $alert+=', введите текущий пароль';
        $rez=false;
      }
      if ($('#new_password').val().length<6) {
        $alert+=', введите новый пароль (не короче 6-и символов)';
        $rez=false;
      }
      if ($('#new_password_2').val().length<6) {
        $alert+=', введите подтверждение нового пароля (не короче 6-и символов)\'';
        $rez=false;
      }
      if ($('#new_password_2').val()!=$('#new_password').val()) {
        $alert+=', новый пароль и его подтверждение не совпадают';
        $rez=false;
      }

      if ($('#user_name').val().length<3) {
        $alert+=', имя пользователя должно быть не короче 3-х символов';
        $rez=false;
      }

    }

    if (!$rez) {
      alert( $alert);
    }

    return $rez;
  }

  document.querySelector('#save_new_btn').addEventListener('click', save_ch);

  function save_ch(e) {
    e.preventDefault();
    $alert = '';
    $rez = true;

    if (document.querySelector('#login').value.length < 3) {
      $rez = false;
      $alert += ' login должен быть не короче 3-х символов';
    }

    if (document.querySelector('#password').value.length < 6) {
      $rez = false;
      $alert += ', введите пароль (не короче 6-и символов)';
    }

    if (document.querySelector('#user_name').value.length < 3) {
      $rez = false;
      $alert += ', введите имя пользователя (не короче 3-х символов)';
    }

    if (document.querySelector('#main_role').value == 0) {
      $rez = false;
      $alert += ', выберите роль';
    }


    if (!$rez) {
      alert($alert);
    }
    else {
      let form = e.target.closest('form');
      let hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = "action";
        hiddenInput.value = "Сохранить нового пользователя";
      form.appendChild(hiddenInput);
      form.submit();
    }

  }

</script>

<?php
echo Base::PageEndHTML();

?>

