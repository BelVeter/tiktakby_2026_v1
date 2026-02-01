<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/VideoYt.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //


echo \bb\Base::pageStartB5('Илья и Маша');
$vv=\bb\classes\VideoYt::getAll();

?>

<style>
  .video_container{
    margin: 0 auto;
    width: 100%;
    max-width: 800px;
  }
  .video{
    margin-top: 30px;
    position: relative;
    overflow: hidden;
    width: 100%;
    padding-top: 56.25%;
  }

  .video iframe{
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    width: 100%;
    height: 100%;
  }
</style>

<?php foreach ($vv as $v): ?>
<div class="video_container">
  <div class="video">
    <iframe src="<?= $v->getLink() ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
  </div>
</div>
<?php endforeach; ?>




<?php

echo \bb\Base::pageEndHtmlB5();

