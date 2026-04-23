<?php
// login first
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/bb/one_login.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "of_select=1&login=123&pass=123");
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);

// delete zayavka
curl_setopt($ch, CURLOPT_URL, "http://localhost/bb/rent_zayavk.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "action=удалить&order_id=211051&user_id=1");
$res = curl_exec($ch);
curl_close($ch);
file_put_contents('output_test.html', $res);
echo "Length of response: " . strlen($res) . "\n";
