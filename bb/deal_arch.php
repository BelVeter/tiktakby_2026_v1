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
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php');

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');

Base::loginCheck();

echo Base::PageStartAdvansed('Manual deal archive');
//echo Base::PostCheck();

if (!\bb\models\User::getCurrentUser()->isDima()){

  echo 'You have no access to this page. Sorry.<br> Ask Dima for access.';

  Base::PageEndHTML();
  die();
}
//$str='http://go.obermat sa.com/0j35';
//echo $str;
//echo strpos($str,'go.obermatsa');
$dealId=0;
if(isset($_POST['dl_id'])) $dealId=Base::GetPost('dl_id');

?>
<form method="post">
  DlId to archive: <input type="number" name="dl_id" value="<?= $dealId ?>"><br>
  <button type="submit">archive</button>
</form>

<?php


if ($dealId>0){
  echo 'dealId='.$dealId;

  \bb\classes\Deal::archiveFullDeal($dealId);
  $dl = \bb\classes\Deal::getByDealId($dealId);
  if (!$dl) echo '<br>Looks like deal is archived (no active deals)';
}

echo Base::PageEndHTML();


?>
