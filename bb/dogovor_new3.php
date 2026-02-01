<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Client.php'); //

echo \bb\Base::pageStartB5('Новый договор 3');
?>

<div class="container-fluid">
  <?php
  \bb\Base::loginCheck();
//  \bb\Base::PostCheckVarDumpEcho();
  $fio = '';
  $phone = '';
  $str ='';

  if (isset($_POST['fio'])) {
    $fio = $_POST['fio'];
    $cls = \bb\classes\Client::getClientsByFioString($fio);
  }

  if (isset($_POST['phone'])) {
    $phone = \bb\Base::getNumbersOnly($_POST['phone']);
    $clsPh = \bb\classes\Client::getClientsByPhoneNumber($phone);
  }


  ?>

  <link rel="stylesheet" href="/public/css/bbnew.css">
  <script type="module" src="/bb/assets/js/dogovor.js"></script>

  <form class="" id="clientSrchForm" method="post">
    <h2>Найти клиента</h2>
    <div class="row">
      <div class="col-12 col-sm-3">
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="srch-fio" name="fio" placeholder="" value="<?= $fio ?>">
          <label class="corrected-label" for="srch-fio">ФИО</label>
        </div>
      </div>
      <div class="col-3 d-flex align-items-center d-none" id="client-srch-fio-result-div">
        <span class="client-srch-result">Найдено <span id="clientSrchFioNumResult">...</span> совпадений</span>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-sm-3">
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="srch-phone" name="phone" placeholder="" value="<?= $phone ?>">
          <label class="corrected-label" for="srch-phone">Телефон</label>
        </div>
      </div>
      <div class="col-3 d-flex align-items-center d-none" id="client-srch-phone-result-div">
        <span class="client-srch-result">Найдено <span id="clientSrchPhoneNumResult">...</span> совпадений</span>
      </div>
    </div>
    <div class="row">
      <div class="col-9 col-sm-3">
        <div class="row">
          <div class="col-9">
            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="srch-str" name="str" placeholder="" value="<?= $str ?>">
              <label class="corrected-label" for="srch-str">Улица</label>
            </div>
          </div>
          <div class="col-3">
            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="srch-dom" name="dom" placeholder="" value="<?= $str ?>">
              <label class="corrected-label" for="srch-dom">дом</label>
            </div>
          </div>
        </div>
      </div>
      <div class="col-3 d-flex align-items-center d-none" id="client-srch-addr-result-div">
        <span class="client-srch-result">Найдено <span id="clientSrchAddrNumResult">...</span> совпадений</span>
      </div>
    </div>
    <div class="row">
      <div class="col-12 d-flex align-items-center d-none" id="client-srch-all-result-div">
        <span class="client-srch-result">Все условия: найдено <span id="clientSrchAllNumResult">...</span> совпадений</span>
      </div>
    </div>
<div class="row">
  <button type="button" class="btn btn-info col-6 col-sm-2 test-btn">Показать</button>
</div>
  </form>
<div class="row">
  <div class="col">
    <table class="table table-hover client_srch-table">
      <thead>
      <tr>
        <th scope="col">#</th>
        <th scope="col">ФИО</th>
        <th scope="col">Адрес (проживания\прописка)</th>
        <th scope="col">Телефон(-ы)</th>
        <th scope="col"></th>
      </tr>
      </thead>
      <tbody class="clients-tbody">
      </tbody>
      <tbody style="display: none;">
      <tr class="client-row-template" data-cl_id="0">
        <th scope="row">1</th>
        <td><span data-clrow="fio">Найденов Дмитрий Михайлович</span></td>
        <td>
          <div class="d-flex flex-column">
            <span data-clrow="addr">Минск, пр-т Независимости, 142-34</span><span data-clrow="reg_addr">Минск, пр-т Независимости, 142-34</span></td>
          </div>
        <td>
          <div class="d-flex flex-column">
            <span data-clrow="phone1">+375 44 768-07-43</span><span data-clrow="phone2">+375 44 768-05-71</span>
          </div>
        </td>
        <td><input class="btn btn-info" type="button" value="выбрать"></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

</div><!-- end of container-fluid div -->



<?php
echo \bb\Base::pageEndHtmlB5();
?>
