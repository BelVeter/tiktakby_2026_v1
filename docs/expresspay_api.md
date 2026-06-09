# ExpressPay API Integration

**Система:** [express-pay.by](https://express-pay.by) — белорусский агрегатор онлайн-оплаты  
**Клиент:** `bb/classes/ExpressPayApiClient.php`  
**API base URL:** `https://api.express-pay.by/v1/`  
**Sandbox:** `https://sandbox-api.express-pay.by/v1/`

## Конфигурация

Все реквизиты хранятся в `.env` (не в коде):

```
EXPRESSPAY_TOKEN=       # API-токен (личный кабинет → настройки)
EXPRESSPAY_SERVICE_ID=  # ID услуги в системе ExpressPay
EXPRESSPAY_SECRET_WORD= # Секретное слово (для HMAC-SHA1 подписи)
EXPRESSPAY_TEST_MODE=false
```

## Инициализация клиента

```php
use bb\classes\ExpressPayApiClient;

$client = new ExpressPayApiClient(
    token:      env('EXPRESSPAY_TOKEN'),
    serviceId:  (int) env('EXPRESSPAY_SERVICE_ID'),
    secretWord: env('EXPRESSPAY_SECRET_WORD'),
    isTestMode: env('EXPRESSPAY_TEST_MODE', false)
);
```

## Доступные методы

| Метод | HTTP | Эндпоинт | Описание |
|-------|------|----------|----------|
| `addInvoice(array $data)` | POST | `/invoices` | Выставить счёт |
| `getInvoiceStatus(int $id)` | GET | `/invoices/{id}` | Статус счёта |
| `cancelInvoice(int $id)` | DELETE | `/invoices/{id}` | Отменить счёт |
| `getInvoicesList(array $filter)` | GET | `/invoices` | Список счетов за период |
| `getPaymentsList(array $filter)` | GET | `/payments` | Список оплат за период |

### `addInvoice` — параметры

```php
$client->addInvoice([
    'AccountNo'   => 'ORDER-123',   // номер заказа (string)
    'Amount'      => 1500,          // сумма в копейках BYN (1500 = 15.00 BYN)
    'Currency'    => 933,           // 933 = BYN
    'Expiration'  => '2025-12-31',  // срок действия счёта
    'Info'        => 'Прокат велосипеда', // описание
    'ReturnUrl'   => 'https://tiktak.by/cart/payment-ok',
    'FailUrl'     => 'https://tiktak.by/cart/payment-fail',
    'Language'    => 'ru',
]);
```

### Фильтры для списков

```php
$client->getPaymentsList(['From' => '2025-01-01', 'To' => '2025-12-31']);
$client->getInvoicesList(['AccountNo' => 'ORDER-123', 'Status' => '']);
```

## Подпись запросов

Клиент автоматически вычисляет `HMAC-SHA1` подпись через `computeSignature()`.  
- POST (addInvoice): подпись от `token + JSON-тело`  
- GET/DELETE: подпись только от `token`

Итоговый хеш — `strtoupper(hash_hmac('sha1', $string, $secretWord))`.

## История

До 2026-06 реквизиты были захардкожены в `bb/dima_test.php` (удалён в коммите `bd856ae`).  
Файл не имел авторизационного гейта и был доступен публично — поэтому удалён в рамках аудита безопасности 2026-06-07.  
Класс `ExpressPayApiClient` сохранён: `bb/classes/ExpressPayApiClient.php`.
