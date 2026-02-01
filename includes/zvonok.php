<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php');

$q_type='';


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}



if ($q_type=='zakaz_zv') {


echo '

     <form action="/includes/zvonok.php" name="zakaz" method="post" class="zvonok_form" id="zvonok_form" style="padding: 15px; width: 430px">
	    Ваше имя*: <input type="text" name="name" id="z_name" value="" /><br />
		Телефон*:
	    		<select name="operator" id="operator">
							<option value="Velcom">Velcom</option>
		               		<option value="МТС">МТС</option>
		                    <option value="Life">Life:)</option>
		                    <option value="город">городской</option>
				</select>
	    		<input type="text" name="phone" id="phone" value="" /><br />
		Тема сообщения:
				<select name="tema" id="tema">
							<option value="заказ товара">заказ товара</option>
		               		<option value="консультация">консультация</option>
		                    <option value="вызов курьера для возврата товара">вызов курьера для возврата товара</option>
							<option value="сообщение об оплате">сообщение об оплате</option>
							<option value="консультация">иное</option>
		        </select>

		<br />
		Дополнительная информация: <br />
		<textarea name="info" id="info" cols="45" rows="5"></textarea><br />


        <input type="button" name="action" value="отправить заявку" onclick="zv_send()" />
     	<input type="button" class="zv_otm" id="zv_otm" value="отмена" onclick="cans_zv()" />


	</form>';
}
elseif ($q_type=='zv_send') {


	//Проверка входящей информации
	//		echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
	//		foreach ($_POST as $key => $value) {
	//			echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
	//		}
	//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";



	//вставка данных в базу
        $mysqli = \bb\Db::getInstance()->getConnection();
		$query_zv = "INSERT INTO zvonki VALUES('', '$z_name', '', '$operator', '$phone', '$tema', '$info', '".time()."', 'new', '', '')";
        $result = $mysqli->query($query_zv);
		if (!$result) {
			echo '<form class="zvonok_form" style="text-align:center;">По техническим причинам Ваша заявка не была отправлена. <br /> Приносим свои извинения.<br /> Свяжитесь с нашими операторами по телефону.<br />
				<input type="button" value="OK" onclick="cans_zv()" />
				</form>';
		}
		else {
			echo '<form class="zvonok_form" style="text-align:center;">Заявка принята!<br /> Оператор свяжется с Вами в ближайшее время. <br />
				<input type="button" value="OK" onclick="cans_zv()" />
			</form>';
		}

}//end of elseif
elseif ($q_type=='office'){
    //echo $q_type.'---'.$officeid;

    switch ($officeid) {
        case 1:
            echo '
            <div class="office-top-container">
                <div class="ofcol1">
                    <a class="office-address" href="#">
                        <img src="/assets/pics/png/zamok.png" alt="Офис">
                        <span>ул. Литературная 22</span>
                    </a>
                    <span class="sub-header">Телефоны:</span>
                    <a href="tel:+375296303532" class="textline phone">+375 (29) 630-35-32</a>
                    <a href="tel:+375297454040" class="textline phone">+375 (29) 745-40-40</a>
                    <span class="sub-header">Часы работы:</span>
                    <span class="textline">пн-пт: 10.00-19.00</span>
                    <span class="textline">сб, вс: 10.00-16.00</span>
                </div>
                <div class="ofcol2">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d37576.53071565148!2d27.49688255176978!3d53.940037097398736!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbcf987119c2ed%3A0x38a520fb62e6031d!2sTIKTAK%20SALON%20PROKATA%20DETSKIH%20TOVAROV%20UP%20TODDLER%20FAN!5e0!3m2!1sen!2spl!4v1648465519790!5m2!1sen!2spl" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            ';
            break;
        case 2:
            echo '
            <div class="office-top-container">
                <div class="ofcol1">
                    <a class="office-address" href="#">
                        <img src="/assets/pics/png/zamok.png" alt="Офис">
                        <span>ул. Ложинская, 5</span>
                    </a>
                    <span class="sub-header">Телефоны:</span>
                    <a href="tel:+375296303558" class="textline phone">+375 (29) 630-35-58</a>
                    <a href="tel:+375297454040" class="textline phone">+375 (29) 745-40-40</a>
                    <span class="sub-header">Часы работы:</span>
                    <span class="textline">пн-пт: 10.00-19.00</span>
                    <span class="textline">сб, вс: 11.00-15.00</span>
                </div>
                <div class="ofcol2">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2348.1549247760463!2d27.685609051599968!3d53.94675598001161!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbc9357a6cbe07%3A0xc6249534647e615d!2z0J_RgNC-0LrQsNGCINC00LXRgtGB0LrQuNGFINGC0L7QstCw0YDQvtCyIFRpa1Rhay4g0KHQsNC70L7QvSDihJYyLg!5e0!3m2!1sen!2spl!4v1648465657389!5m2!1sen!2spl" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            ';
            break;
        case 3:
            echo '
            <div class="office-top-container">
                <div class="ofcol1">
                    <a class="office-address" href="#">
                        <img src="/assets/pics/png/zamok.png" alt="Офис">
                        <span>пр-т Победителей, 125</span>
                    </a>
                    <span class="sub-header">Телефоны:</span>
                    <a href="tel:+37529296944040" class="textline phone">+375 (29) 694-40-40</a>
                    <a href="tel:+375336944040" class="textline phone">+375 (33) 694-40-40</a>
                    <span class="sub-header">Часы работы:</span>
                    <span class="textline">пн-пт: 10.00-19.00</span>
                    <span class="textline">сб, вс: 11.00-15.00</span>
                </div>
                <div class="ofcol2">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2348.571860997931!2d27.466276251599684!3d53.93934978000999!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbc4fcb2357655%3A0xffd68328ee180dac!2z0J_RgNC-0LrQsNGCINC00LXRgtGB0LrQuNGFINGC0L7QstCw0YDQvtCyIFRpa3Rhay4g0KHQsNC70L7QvSDihJYzLg!5e0!3m2!1sen!2spl!4v1648465785555!5m2!1sen!2spl" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            ';
            break;
    }

}




function get_post($var)
{

    $mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}


function phone_to_n ($ph) {
	$ph=preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
}



?>
