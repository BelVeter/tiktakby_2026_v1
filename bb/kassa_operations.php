<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 29.05.2019
 * Time: 22:17
 */

use bb\models;

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Schedule.php');

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Kassa.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/KassaOperation.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/KassaChannel.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/OperationForm.php');

/*
 * transformation notes:
 * shift operations, type2 change of1k2 to office-1-k2 and other
 */





//ajax requests processing
$isAjax = false ;
if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $isAjax = true ;

    // сюда попадаем в случае AJAX-запроса
    $form = new models\OperationForm($_SERVER['REQUEST_URI']);
    $form->postLoad();

    $res['status']='ok';
    $res['result']=$form->getNewOperationForm();
    $res['param']='';

    $result=json_encode($res);
    echo $result;
    exit();
}


echo bb\Base::PageStartAdvansed('Операции кассы.');
//echo \bb\Base::PostCheck();

models\OperationForm::RequiredEcho();

$form = new models\OperationForm($_SERVER['REQUEST_URI']);
$form->postLoad();

if (isset($_POST['action'])) {
    $action = \bb\Base::GetPost('action');

    switch ($action) {
        case 'Сохранить':
            $op=new models\KassaOperation();
            $op->formLoad($form);
            if ($op->type!='shift') {
                if (!$op->save()) {
                    echo 'Операция не сохранена. Ошибка! Обратитесь к администратору.';
                } else {
                    echo 'Операция сохранена успешно!!!';
                }
            }
            else {//shift
                $ok=true;
                $form2=clone $form;
                $form->operation_type='shift_plus';
                $form2->operation_type='shift_minus';

                $form2->channel=$form2->shift_to_channel;
                $form2->kassa_type=$form2->kassa_type_shift;

                $op->formLoad($form);

                $op_to=new models\KassaOperation();
                $op_to->formLoad($form2);

                $op->type2=\bb\models\KassaOperation::getDbShiftToString($op_to);
                $op_to->type2=\bb\models\KassaOperation::getDbShiftToString($op);


                //!!! start transaction
                \bb\Db::startTransaction();

                if (!$op->save()) $ok=false;
                if (!$op_to->save()) $ok=false;

                $op->link_to=$op_to->id_operation;
                $op_to->link_to=$op->id_operation;

                if (!$op->update()) $ok=false;;
                if (!$op_to->update()) $ok=false;

                //!!! end transaction
                if ($ok) \bb\Db::commitTransaction();
                else \bb\Db::rollBackTransaction();

            }

            break;

        case 'Удалить':
            if ($form->dr_id>0) {
                if (!models\KassaOperation::deleteId($form->dr_id)) {
                    echo 'Ошибка! Операция не удалена.';
                }
                else {
                    echo 'Операция удалена.';
                }

            }
            break;
    }




}















//echo '<div class="container">';
echo '
    <div class="row">
    <div class="col-12 text-right">
        <button class="btn btn-dark btn-lg" data-toggle="modal" data-target="#exampleModalLabel">Внести расход</button>
    </div>
    </div>';

echo '
<div class="modal fade" id="exampleModalLabel" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body" id="new_operation_div">
                '.$form->getNewOperationForm().'
            </div>
        </div>
    </div>
</div>
';



//echo '</div>';

echo $form->startTable();
$start=new DateTime("2019-06-01");
$end=new DateTime("2019-10-03");

echo $form->tableRows($start, $end);
echo $form->endTable();

echo bb\Base::PageEndHTML();

?>


