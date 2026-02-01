<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 15:58
 */

use bb\Base;
use bb\classes\LastRent;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\Db;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Model.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/LastRent.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/ModelWeb.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Delivery.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubRazdel.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Picture.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SpeedTrack.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/TopMenu.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Client.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubDeal.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Deal.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Subscription.php');

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');

Base::loginCheck();

echo Base::PageStartAdvansed('Подписки');
$subs = \bb\classes\Subscription::getAll();
?>
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
<!--    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">-->
<!--      <span class="navbar-toggler-icon"></span>-->
<!--    </button>-->
<!--    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">-->
<!--      <div class="navbar-nav">-->
<!--        <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>-->
<!--        <a class="nav-item nav-link" href="/bb/favorite_tovars_management.php">Популярные товары</a>-->
<!--        <a class="nav-item nav-link" href="/bb/l3_karn_dop.php">Доп.поля карнавала</a>-->
<!--      </div>-->
<!--    </div>-->
  </nav>
<?php if (!$subs): ?>
  <div class="alert-warning text-center">Пока нед подписок</div>
<?php else: ?>

  <div class="container">
    <table class="table">
      <thead>
      <tr>
        <th scope="col">№</th>
        <th scope="col">e-mail</th>
        <th scope="col">дата подписки</th>
      </tr>
      </thead>
      <tbody>
  <?php foreach ($subs as $sub): ?>
    <tr>
      <th scope="row"><?= $sub->getId() ?></th>
      <td><?= $sub->getEmail() ?></td>
      <td><?= $sub->getCrDate()->format("d.m.Y (H:i)") ?></td>
    </tr>
  <?php endforeach; ?>
      </tbody>
    </table>
  </div>


<?php endif; ?>

<?php
echo Base::PageEndHTML();

