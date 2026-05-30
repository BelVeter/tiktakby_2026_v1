<?php
namespace bb\classes;

/**
 * Класс для интеграции с API сервиса RocketSMS.by
 * Документация: docs/rocketsms_api.md
 */
class RocketSMS {
    private $username;
    private $password;
    private $apiUrl = 'https://api.rocketsms.by/simple/';

    public function __construct() {
        $this->loadEnv();
    }

    /**
     * Парсинг .env файла вручную (dotenv недоступен в legacy bb/)
     */
    private function loadEnv() {
        $envFile = $_SERVER['DOCUMENT_ROOT'] . '/.env';
        if (!file_exists($envFile)) return;

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;
            $key = trim($parts[0]);
            $val = trim(trim($parts[1]), '"\'');
            if ($key === 'ROCKETSMS_USERNAME') $this->username = $val;
            if ($key === 'ROCKETSMS_PASSWORD') $this->password = $val;
        }
    }

    /**
     * Выполнение POST-запроса к API.
     * Все запросы идут через POST — учётные данные не попадают в URL и серверные логи.
     *
     * @param string $endpoint  суффикс пути (например, 'send', 'balance')
     * @param array  $extraBody дополнительные POST-параметры (без username/password)
     * @return array  декодированный JSON-ответ или ['error' => '...'] при сбое cURL
     */
    private function request($endpoint, $extraBody = []) {
        if (!$this->username || !$this->password) {
            return ['error' => 'ROCKETSMS_USERNAME или ROCKETSMS_PASSWORD не заданы в .env'];
        }

        $extraBody['username'] = $this->username;
        $extraBody['password'] = md5($this->password);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . $endpoint);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($extraBody));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($curl);

        if ($response === false) {
            $err = curl_error($curl);
            curl_close($curl);
            return ['error' => 'cURL error: ' . $err];
        }
        curl_close($curl);

        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : ['error' => 'Некорректный ответ API: ' . $response];
    }

    /**
     * BULKSEND использует отдельный метод сборки POST-тела,
     * т.к. http_build_query не умеет правильно отправлять phone[] как повторяющиеся поля.
     */
    private function postRaw($endpoint, $partsExtra, $phones) {
        if (!$this->username || !$this->password) {
            return ['error' => 'ROCKETSMS_USERNAME или ROCKETSMS_PASSWORD не заданы в .env'];
        }

        $parts = [
            'username=' . urlencode($this->username),
            'password=' . urlencode(md5($this->password)),
        ];
        foreach ($partsExtra as $k => $v) {
            $parts[] = urlencode($k) . '=' . urlencode($v);
        }
        foreach ($phones as $phone) {
            $parts[] = 'phone[]=' . urlencode($phone);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . $endpoint);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $parts));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($curl);

        if ($response === false) {
            $err = curl_error($curl);
            curl_close($curl);
            return ['error' => 'cURL error: ' . $err];
        }
        curl_close($curl);

        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : ['error' => 'Некорректный ответ API: ' . $response];
    }

    // ─── Публичные методы ─────────────────────────────────────────────────────

    /**
     * SEND: отправка одного SMS
     * @param string      $phone  номер в международном формате без «+», напр. 375296890043
     * @param string      $text   текст сообщения в UTF-8
     * @param string|null $sender Sender ID; null — дефолтное имя аккаунта
     * @return array  {"id":8767,"status":"SENT","cost":{"credits":1,"money":0.2}} | {"error":"..."}
     */
    public function send($phone, $text, $sender = null) {
        $params = ['phone' => $phone, 'text' => $text];
        if ($sender) $params['sender'] = $sender;
        return $this->request('send', $params);
    }

    /**
     * BULKSEND: один текст на несколько номеров
     * @param string[]    $phones массив номеров в международном формате
     * @param string      $text   текст сообщения
     * @param string|null $sender Sender ID
     * @return array
     */
    public function bulkSend(array $phones, $text, $sender = null) {
        $extra = ['text' => $text];
        if ($sender) $extra['sender'] = $sender;
        return $this->postRaw('bulkSend', $extra, $phones);
    }

    /**
     * STATUS: статус отправленного сообщения
     * @param  int   $id  ID из ответа send()
     * @return array {"id":3334,"status":"QUEUED"}
     */
    public function status($id) {
        return $this->request('status', ['id' => (int)$id]);
    }

    /**
     * BALANCE: текущий баланс аккаунта
     * @return array {"credits":4400,"balance":22.00}
     */
    public function balance() {
        return $this->request('balance');
    }

    /**
     * SENDERS: список доступных Sender ID (альфа-имён)
     * @return array [{"sender":"SHOP","verified":false,"checked":true,"registered":false}]
     */
    public function senders() {
        return $this->request('senders');
    }

    /**
     * TEMPLATES: список шаблонов из личного кабинета
     * @return array [{"tpl_id":"TPL_ID_1","text":"Hello world"}]
     */
    public function templates() {
        return $this->request('templates');
    }
}
