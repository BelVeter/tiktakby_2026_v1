<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

use bb\Base;
use bb\classes\CatAnalytics;

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/CatAnalytics.php');
require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

//------- proverka paroley

$in_level= array(0, 5,7);

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>

	<div class="top_menu">
		<a class="div_item" href="/bb/index.php">Залогиниться</a>
	</div>

	</form></body></html>');
}

//-----------proverka paroley

$sort_f='';
if (isset($_POST['sort_f'])) {
    $sort_f = Base::GetPost('sort_f');
}
if (isset($_POST['date_from'])) {
    $date_from = Base::GetPost('date_from');
    $date_to = Base::GetPost('date_to');
}
else {
    $today= new DateTime();
    $date_from=$today->format("Y").'-01-01';
    $date_to=$today->format("Y-m-d");
}
echo bb\Base::PageStartAdvansed('Аналитика: категории.');

echo '
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>
';

//echo \bb\Base::PostCheck();

echo '<form method="post" action="tov_analytics.php" name="srch_form" id="srch_form">';
echo 'Период: с <input type="date" name="date_from" id="date_from" value="'.$date_from.'">';
echo 'по <input type="date" name="date_to" id="date_to" value="'.$date_to.'">';
echo '<input type="submit" value="показать">';
echo '</form>';


$start_date = new DateTime($date_from);
$to_date = new DateTime($date_to);


$rows= CatAnalytics::getData($start_date, $to_date);
CatAnalytics::sortArray($rows, $sort_f);

echo '<table class="table table-bordered table-sm">
<tr>
    <th style="width: 300px;"><label for="cat_radio">Категория </label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="cat" '.($sort_f=='cat' ? 'checked':'').' id="cat_radio"></th>
    <th style="width: 100px;"><label for="tov_num_radio">Кол-во товара (факт)</label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="tov_num" '.($sort_f=='tov_num' ? 'checked':'').' id="tov_num_radio"></th>
    <th style="width: 100px;"><label for="tov_cost_radio">Стоимость товара</label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="tov_cost" '.($sort_f=='tov_cost' ? 'checked':'').' id="tov_cost_radio"></th>
    <th style="width: 100px;"><label for="dls_num_radio">Кол-во выдач </label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="dl_num" '.($sort_f=='dl_num' ? 'checked':'').' id="dls_num_radio"></th>
    <th style="width: 100px;"><label for="d_rent_radio">% сдачи </label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="d_rent" '.($sort_f=='d_rent' ? 'checked':'').' id="d_rent_radio"></th>
    <th style="width: 100px;"><label for="sales_radio">Выручка </label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="sales" '.($sort_f=='sales' ? 'checked':'').' id="sales_radio"></th>
    <th style="width: 100px;"><label for="sales_tov_radio">Выручка на 1 товар </label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="sales_per_tov" '.($sort_f=='sales_per_tov' ? 'checked':'').' id="sales_tov_radio"></th>
    <th style="width: 100px;"><label for="sales_dl_radio">Выручка на 1 сделку </label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="sales_per_dl" '.($sort_f=='sales_per_dl' ? 'checked':'').' id="sales_dl_radio"></th>
    <th style="width: 100px;"><label for="sales_cost_radio">Выручка на вложенный рубль </label><input onchange="this.form.submit();" form="srch_form" type="radio" name="sort_f" value="sales_per_cost" '.($sort_f=='sales_per_cost' ? 'checked':'').' id="sales_cost_radio"></th>
</tr>
';
$total_sales=0;
$total_dl_number=0;
$total_tov_number=0;
$total_tov_cost=0;

foreach ($rows as $row) {
    $total_sales+=$row->sales;
    $total_dl_number+=$row->deals_n;
    $total_tov_number+=$row->items_n;
    $total_tov_cost+=$row->tov_buy_price;
    echo '
    <tr align="right">
        <td align="left">'.$row->cat_name.'(№'.$row->cat_id.')</td>
        <td>'.number_format($row->items_n, 0, ',', ' ').'</td>
        <td>'.number_format($row->tov_buy_price, 0, ',', ' ').'</td>
        <td>'.number_format($row->deals_n, 0, ',', ' ').'</td>
        <td>'.number_format($row->rentedOutPercent, 1, ',', ' ').'</td>
        <td>'.number_format($row->sales, 0, ',', ' ').'</td>
        <td style="font-style: italic;">'.number_format($row->salesPerTovar(), 0, ',', ' ').'</td>
        <td style="font-style: italic;">'.number_format($row->salesPerDeal(), 0, ',', ' ').'</td>
        <td style="font-style: italic;">'.number_format($row->salesPerCost(), 2, ',', ' ').'</td>

    </tr>
    ';
}

echo '
<tr align="right" style="font-weight: bold">
    <td align="left">Итого (среднее):</td>
    <td>'.number_format($total_tov_number, 0, ',', ' ').'</td>
    <td>'.number_format($total_tov_cost, 0, ',', ' ').'</td>
    <td>'.number_format($total_dl_number, 0, ',', ' ').'</td>
    <td></td>
    <td>'.number_format($total_sales, 0, ',', ' ').'</td>
    <td style="font-style: italic;">('.number_format($total_sales/$total_tov_number, 0, ',', ' ').')</td>
    <td style="font-style: italic;">('.number_format($total_sales/$total_dl_number, 0, ',', ' ').')</td>
    <td style="font-style: italic;">('.number_format($total_sales/$total_tov_cost, 2, ',', ' ').')</td>
</tr>
';

echo '</table>';


echo bb\Base::PageEndHTML();

//SELECT item_inv_n, COUNT(item_inv_n), SUM(r_paid), SUM(return_date-start_date) FROM `rent_deals_arch` GROUP BY item_inv_n



?>
