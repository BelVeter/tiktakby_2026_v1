<?php

use bb\Base;

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //

session_start();

Base::loginCheck();

echo Base::PageStartAdvansed();

?>

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart', 'line']});
        //google.charts.setOnLoadCallback(drawChart);

        function drawChart() {
            var data = google.visualization.arrayToDataTable([
                ["Date", "оф1", "оф2"],
                ['2004',  1000.05,      400],
                ['2005',  1170,      460],
                ['2006',  660,       1120],
                ['2007',  1030,      540]
            ]);

            var options = {
                title: 'Company Performance',
                curveType: 'function',
                legend: { position: 'bottom' }
            };

            var chart = new google.visualization.LineChart(document.getElementById('curve_chart'));

            chart.draw(data, options);
        }

        function of_show() {
            //alert('ok');

            var $form = $("#1t_form");
            //alert($form);
            $.ajax(
                {
                    type: $form.attr('method'),
                    url: "/bb/rep_aj.php",
                    data: $form.serialize(),
                }
            ).done(function (data) {
                var rez=JSON.parse(data);
                //alert (rez);
                //$("#"+$result_div_id).empty();
                //$("#"+$result_div_id).append(rez.result);
                //$("#active_inv_n").val(inv_n);

                var options = {
                    title: 'Выручка в бел.руб.',
                    curveType: 'line',
                    legend: { position: 'bottom' }
                };

                var chart = new google.visualization.LineChart(document.getElementById('curve_chart'));

                var data = google.visualization.arrayToDataTable(rez);

                chart.draw(data, options);


            });

        }
    </script>
<body>
<div class="top_menu">
    <a class="div_item" href="/bb/index.php">На главную</a>
</div>
<form id="1t_form" method="post">
    <select name="action">
        <option value="result_tovar">Выручка - расходы (без покупки товаров)</option>
        <option value="result">Выручка - расходы все</option>
        <option value="all_affices_by_days">Выручка Все офисы</option>
        <option value="all_affices_total">Все офисы - свод</option>
    </select>
    <select name="period">
        <option value="1">по дням</option>
        <option value="7">по неделям</option>
        <option value="30" selected>по месяцам</option>
    </select>
    <br>
    <input type="date" name="from" value="<?= date("Y-m-d") ?>">
    <input type="date" name="to" value="<?= date("Y-m-d") ?>">
    <button onclick="of_show();return false;">показать</button>
</form>

<div id="curve_chart" style="width: 900px; height: 500px"></div>
</body>
</html>
