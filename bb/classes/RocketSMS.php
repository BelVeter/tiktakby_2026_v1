<?php
namespace bb\classes;

/**
 * Класс для интеграции с API сервиса RocketSMS.by
 */
class RocketSMS {
    private $username;
    private $password;
    private $apiUrl = 'https://api.rocketsms.by/simple/';

    public function __construct() {
        $this->loadEnv();
    }

    /**
     * Парсинг .env файла вручную, т.к. dotenv не загружается в legacy-части
     */
    private function loadEnv() {
        $envFile = $_SERVER['DOCUMENT_ROOT'] . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim(trim($parts[1]), '"\''); // удаляем кавычки если есть
                    if ($key === 'ROCKETSMS_USERNAME') $this->username = $val;
                    if ($key === 'ROCKETSMS_PASSWORD') $this->password = $val;
                }
            }
        }
    }

    /**
     * Выполнение запроса к API
     */
    private function request($endpoint, $params = [], $isPost = false) {
        $params['username'] = $this->username;
        $params['password'] = md5($this->password);

        $url = $this->apiUrl . $endpoint;
        // Запрос должен передаваться как query string
        $query = http_build_query($params);

        $curl = curl_init();
        
        if ($isPost) {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        } else {
            curl_setopt($curl, CURLOPT_URL, $url . '?' . $query);
        }
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }

    /**
     * 1.1 SEND: отправка одного сообщения
     * @param string $phone номер в международном формате, например 375296890043
     * @param string $text сообщение в UTF-8
     * @param string|null $sender имя отправителя
     * @return array {"id":8767,"status":"SENT","cost":{"credits":1,"money":0.2}} или {"error" : "NO_MESSAGE"}
     */
    public function send($phone, $text, $sender = null) {
        $params = [
            'phone' => $phone,
            'text' => $text
        ];
        if ($sender) {
            $params['sender'] = $sender;
        }
        return $this->request('send', $params, true);
    }

    /**
     * 1.2 BULKSEND: отправка нескольких сообщений
     * @param array $phones массив номеров
     * @param string $text сообщение
     * @param string|null $sender имя отправителя
     * @return array
     */
    public function bulkSend(array $phones, $text, $sender = null) {
        $params = [
            'phones' => $phones,
            'text' => $text
        ];
        if ($sender) {
            $params['sender'] = $sender;
        }
        return $this->request('bulkSend', $params, true);
    }

    /**
     * 1.3 STATUS: проверка статуса сообщения
     * @param int $id ID сообщения
     * @return array {"id" : 3334, "status" : "QUEUED"}
     */
    public function status($id) {
        return $this->request('status', ['id' => $id]);
    }

    /**
     * 1.4 BALANCE: проверка баланса аккаунта
     * @return array {"credits" : 4400, "balance" : 22.00}
     */
    public function balance() {
        return $this->request('balance');
    }

    /**
     * 1.5 SENDERS: список доступных альфа-номеров
     * @return array [{"sender": "SHOP", "verified": false, "checked": true, "registered": false}]
     */
    public function senders() {
        return $this->request('senders');
    }

    /**
     * 1.7 TEMPLATES: список доступных шаблонов
     * @return array [{"tpl_id" : "TPL_ID_1", "text" : "Hello world"}]
     */
    public function templates() {
        return $this->request('templates');
    }
}
