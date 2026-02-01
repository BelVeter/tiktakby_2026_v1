<?php
// Подключаем файл с классом
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/ExpressPayApiClient.php'); //

// Указываем, что будем использовать класс из пространства имён bb
use bb\classes\ExpressPayApiClient;

// --- Настройки ---
$token = '3a0c82e3ac204dff9861900a0ef08342';             // Замените на свой токен
$serviceId = 31969;                   // Замените на ID вашей услуги
$secretWord = 'Golacheva*8941';  // Замените на ваше секретное слово
$isTestMode = false;                   // true для тестового режима

// Просмотр списка платежей по параметрам
function getListPayment()
{
  // Параметры запроса
  $params = array(
    "Token" => "3a0c82e3ac204dff9861900a0ef08342",
    "From" => "",
    "To" => "",
    "AccountNo" => "",
    "Status" => ""
  );

  // Использовать ли тестовый сервер
  $is_test = false;

  if ($is_test) {
    $base_url = "https://sandbox-api.express-pay.by/v1/payments?";
  } else {
    $base_url = "https://api.express-pay.by/v1/payments?";
  }

  // Использовать цифровую подпись
  $use_signature = true;

  // Секретное слово
  $secret_word = "Golacheva*8941";


  if ($use_signature) {
    $params['signature'] = computeSignature($params, $secret_word);
  }

  return file_get_contents($base_url . http_build_query($params));
}

/**
 * Формирование цифровой подписи
 *
 * @param array  $request_params Параметры запроса
 * @param string $secret_word Секретное слово
 *
 * @return string $hash Полученный хеш
 */
function computeSignature($request_params, $secret_word)
{
  $normalizedParams = array_change_key_case($request_params, CASE_LOWER);
  $mapping = array(
    "token",
    "from",
    "to",
    "accountno",
    "status"
  );
  $result = "";
  foreach ($mapping as $item) {
    $result .= $normalizedParams[$item] ?? null;
  }
  $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));
  return $hash;
}

// Пример использования
echo getListPayment();
