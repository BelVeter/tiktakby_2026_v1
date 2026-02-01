<?php

namespace bb\classes;

class ExpressPayApiClient
{
  /**
   * URL API-сервера
   * @var string
   */
  private const BASE_URL = 'https://api.express-pay.by/v1/';

  /**
   * API-ключ (токен)
   * @var string
   */
  private string $token;

  /**
   * ID услуги
   * @var int
   */
  private int $serviceId;

  /**
   * Секретное слово для подписи
   * @var string
   */
  private string $secretWord;

  /**
   * Использовать тестовый режим
   * @var bool
   */
  private bool $isTestMode;

  /**
   * Конструктор клиента API
   *
   * @param string $token Токен для доступа к API
   * @param int $serviceId Номер услуги
   * @param string $secretWord Секретное слово для генерации подписи
   * @param bool $isTestMode Включить тестовый режим (по умолчанию false)
   */
  public function __construct(string $token, int $serviceId, string $secretWord, bool $isTestMode = false)
  {
    $this->token = $token;
    $this->serviceId = $serviceId;
    $this->secretWord = $secretWord;
    $this->isTestMode = $isTestMode;
  }

  /**
   * Выставление нового счета
   *
   * @param array $invoiceData Данные счета (AccountNo, Amount, Currency, Info и т.д.)
   * @return array Ответ от сервера в виде ассоциативного массива
   * @throws \Exception В случае ошибки запроса
   */
  public function addInvoice(array $invoiceData): array
  {
    $defaultParams = [
      'ServiceId' => $this->serviceId,
      'IsTest' => $this->isTestMode ? 1 : 0
    ];

    $params = array_merge($invoiceData, $defaultParams);

    $url = self::BASE_URL . 'invoices?token=' . $this->token;

    $signature = $this->computeSignature($params, 'addInvoice');

    $url .= '&signature=' . $signature;

    return $this->sendRequest($url, $params, 'POST');
  }

  /**
   * Получение статуса счета
   *
   * @param int $invoiceId Номер счета в системе Express Pay
   * @return array Ответ от сервера
   * @throws \Exception
   */
  public function getInvoiceStatus(int $invoiceId): array
  {
    $params = [
      'token' => $this->token,
    ];

    $url = self::BASE_URL . 'invoices/' . $invoiceId;

    $signature = $this->computeSignature($params, 'getInvoiceStatus');
    $params['signature'] = $signature;

    $queryString = http_build_query($params);

    return $this->sendRequest($url . '?' . $queryString, [], 'GET');
  }

  /**
   * Отмена счета
   *
   * @param int $invoiceId Номер счета в системе Express Pay
   * @return array Ответ от сервера
   * @throws \Exception
   */
  public function cancelInvoice(int $invoiceId): array
  {
    $params = [
      'token' => $this->token,
    ];

    $url = self::BASE_URL . 'invoices/' . $invoiceId;

    $signature = $this->computeSignature($params, 'cancelInvoice');
    $params['signature'] = $signature;

    $queryString = http_build_query($params);

    return $this->sendRequest($url . '?' . $queryString, [], 'DELETE');
  }

  /**
   * Вычисление цифровой подписи
   *
   * @param array $params Параметры запроса
   * @param string $method Метод API, для которого вычисляется подпись
   * @return string HMAC-SHA1 подпись
   */
  private function computeSignature(array $params, string $method): string
  {
    $normalizedParams = array_change_key_case($params, CASE_LOWER);

    $stringToSign = $this->token;

    if ($method === 'addInvoice') {
      // Для POST-запроса на добавление счета подпись вычисляется от JSON-тела
      $stringToSign .= json_encode($normalizedParams);
    } else {
      // Для GET/DELETE запросов подпись вычисляется от токена
    }

    return hash_hmac('sha1', $stringToSign, $this->secretWord);
  }

  /**
   * Отправка HTTP-запроса на сервер API
   *
   * @param string $url URL для запроса
   * @param array $data Данные для отправки
   * @param string $method HTTP-метод (POST, GET, DELETE)
   * @return array Декодированный JSON-ответ
   * @throws \Exception Если произошла ошибка cURL или API вернул ошибку
   */
  private function sendRequest(string $url, array $data, string $method): array
  {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method === 'POST') {
      $jsonData = json_encode($data);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
      ]);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
      $error = curl_error($ch);
      curl_close($ch);
      throw new \Exception("Ошибка cURL: " . $error);
    }

    curl_close($ch);

    $decodedResponse = json_decode($response, true);

    if ($httpCode >= 400) {
      $errorMessage = $decodedResponse['Error']['Message'] ?? 'Неизвестная ошибка API';
      $errorCode = $decodedResponse['Error']['Code'] ?? $httpCode;
      throw new \Exception("Ошибка API: {$errorMessage} (Код: {$errorCode})");
    }

    return $decodedResponse;
  }

  /**
   * Получение списка счетов за период
   *
   * @param array $filter Параметры фильтрации (например, ['From' => '2025-09-16', 'To' => '2025-09-23'])
   * @return array Ответ от сервера
   * @throws \Exception
   */
  public function getInvoicesList(array $filter = []): array
  {
    $params = array_merge(['token' => $this->token], $filter);

    $url = self::BASE_URL . 'invoices';

    // Сигнатура для GET-запросов вычисляется только от токена
    $signature = $this->computeSignature(['token' => $this->token], 'getInvoicesList');
    $params['signature'] = $signature;

    $queryString = http_build_query($params);

    return $this->sendRequest($url . '?' . $queryString, [], 'GET');
  }

  /**
   * Получение списка оплат за период
   *
   * @param array $filter Параметры фильтрации (например, ['From' => '2025-09-16', 'To' => '2025-09-23'])
   * @return array Ответ от сервера
   * @throws \Exception
   */
  public function getPaymentsList(array $filter = []): array
  {
    // 1. Формируем базовые параметры, включая токен
    $params = array_merge(['token' => $this->token]);

    // 2. Указываем правильный эндпоинт для оплат - /payments
    $url = self::BASE_URL . 'payments';

    // 3. Вычисляем подпись (логика для GET-запросов та же)
    $signature = $this->computeSignature(['token' => $this->token], 'getPaymentsList');
    $params['signature'] = $signature;

    // 4. Собираем и отправляем запрос
    $queryString = http_build_query($params);

    return $this->sendRequest($url . '?' . $queryString, [], 'GET');
  }
}
