# Мультитоварный договор — Спецификация

**Дата:** 2026-05-19  
**Ветка:** `ats-calls` → новая feature-ветка  
**Статус:** утверждена

## Цель

Позволить сотруднику оформить одну сделку на несколько товаров для одного клиента на одной странице, с автоматическим расчётом суммы и распечаткой единого RTF-договора. БД не меняется — каждый товар хранится отдельной записью в `rent_deals_act`.

---

## Файлы

| Файл | Тип | Объём |
|------|-----|-------|
| `bb/dogovor_multi_new.php` | новый | ~380 строк |
| `bb/get_item_tarifs.php` | новый AJAX endpoint | ~70 строк |
| `bb/ndk_multi.rtf` | новый RTF-шаблон | правки вручную |
| `bb/dogovor_new.php` | правки | +5 строк в меню |

---

## Авторизация

Та же проверка что везде в `bb/`:

```php
if ($_SESSION['svoi'] != 8941) { die(...); }
```

Используемые данные сессии: `$_SESSION['office']`, `$_SESSION['user_id']`, `$_SESSION['user_fio']`.

---

## `dogovor_multi_new.php` — структура страницы

### Блок 1: Выбор клиента

Переиспользует AJAX из `dog3_ajax.php` (уже есть: `client-fio-srch-num`, `client-all-srch-clients`).  
После выбора клиента — его данные отображаются в readonly-полях: ФИО, паспорт, адрес, телефоны.  
Скрытый `<input name="client_id">`.

### Блок 2: Таблица товаров

Строки добавляются кнопкой «+ Добавить товар». Минимум 1 строка (при загрузке страницы пустая).  
Максимум — не ограничен, но UI рассчитан на 2–5 товаров.

Каждая строка содержит элементы с уникальным суффиксом `_{rowIndex}` (0, 1, 2…):

| Элемент | ID | Описание |
|---------|-----|---------|
| Инв. № | `inv_n_{i}` | text input, onblur → `loadItemTarifs(i)` |
| Название товара | `item_name_{i}` | readonly, заполняется AJAX |
| Дата выдачи | `start_date_{i}` | date input, по умолчанию сегодня |
| Дата возврата | `return_date_{i}` | date input, onchange → `calculateRow(i)` |
| Кол-во дней | `days_{i}` | readonly, вычисляется JS |
| Тариф/день | `tarif_day_{i}` | readonly, вычисляется JS |
| К оплате | `r_to_pay_{i}` | readonly, вычисляется JS |
| Тарифная сетка | `tarifs_data_{i}` | скрытый контейнер с JSON тарифов |
| Кнопка удаления | — | удаляет строку, пересчитывает итог |
| `tarif_id_{i}` | hidden | ID тарифа для сохранения |
| `tarif_step_{i}` | hidden | шаг тарифа (day/week/month) |

Под таблицей: **Итого к оплате: [сумма]** (обновляется при каждом изменении).

### Блок 3: Оплата

```
Тип оплаты: [select: nal_cheque / nal_no_cheque / card / bank]
Общая сумма: [input]   [Разнести →]

Строка на каждый товар:
  {Название товара}  К оплате: 17.50  Оплата: [17.50]  Остаток: 0.00

Итого: оплачено / к оплате / разница (подсветка если ≠ 0)
```

**Логика разнесения (JS):**

```
доля_i = r_to_pay_i / сумма_всех_r_to_pay
оплата_i = round(общая_сумма × доля_i, 2)
```

Последней строке добавляется остаток от округления (чтобы сумма была точной).  
Все поля оплаты редактируемые. При ручном изменении — пересчитывается итог и показывается разница.

### Блок 4: Кнопки

- **«Сохранить и распечатать»** — сохраняет все сделки + оплату → генерирует RTF
- **«Отмена»** — возврат на `dogovor_new.php`

---

## `get_item_tarifs.php` — AJAX endpoint

**Запрос:** `GET /bb/get_item_tarifs.php?inv_n=701001`

**Ответ (JSON):**

```json
{
  "status": "ok",
  "item_name": "Коляска Inglesina Trilogy",
  "model_id": 42,
  "cat_id": 7,
  "item_size": "стандарт",
  "agr_price": 450.00,
  "agr_price_cur": "USD",
  "tarifs": [
    {"tarif_id": 5, "days": 1, "total": 5.00, "step": "day"},
    {"tarif_id": 5, "days": 7, "total": 25.00, "step": "day"},
    {"tarif_id": 5, "days": 30, "total": 70.00, "step": "month"}
  ]
}
```

Ошибки: `{"status": "not_found"}` если товар не найден, `{"status": "not_available"}` если занят.

Запрос к БД: `tovar_rent_items JOIN tovar_list JOIN rent_tarif_act` по `item_inv_n`.  
Статус товара проверяется: только `to_rent` или `bron`/`t_bron` с истёкшим `br_time` — доступны.

---

## JS-функции (в `dogovor_multi_new.php`)

```js
loadItemTarifs(rowIndex)     // AJAX → get_item_tarifs.php → заполняет item_name, tarifs_data
calculateRow(rowIndex)       // дни × тариф → r_to_pay_i
calculateTotal()             // сумма r_to_pay по всем строкам → итог
distributePayment()          // пропорциональное разнесение → payment_i
addRow()                     // добавляет строку, инициализирует обработчики
removeRow(rowIndex)          // удаляет строку, пересчитывает итог
```

Алгоритм `calculateRow` — повторяет логику `getRentToPay` из `dogovor_new.php`:
- Находит подходящий тариф по числу дней (максимальный из подходящих)
- Применяет потолок следующего тарифа если сумма превышает

---

## PHP при сохранении (POST)

Принимает: `client_id`, массивы `inv_n[]`, `start_date[]`, `return_date[]`, `tarif_id[]`, `tarif_step[]`, `r_to_pay[]`, `payment_amount[]`, `payment_type` (один тип для всех).

Для каждого товара `i`:

```sql
-- 1. Создать сделку (все даты — unix timestamp)
INSERT INTO rent_deals_act VALUES(
  '', '{client_id}', '{inv_n[i]}', '{start_ts[i]}', '{return_ts[i]}',
  '0', '0', '', '{r_to_pay[i]}', '', '0', 'BYN', 'active', '',
  '{SESSION.user_id}', '{SESSION.user_id}', '{time()}', '{time()}', '{start_ts[i]}',
  '', '{SESSION.office}'
)

-- 2. Создать sub_deal first_rent
-- tarif_value = ставка за шаг (rent_tarif из JS), rent_tenor = кол-во шагов (дней)
INSERT INTO rent_sub_deals_act VALUES(
  '', '{deal_id}', 'first_rent', '10', '{start_ts[i]}', '{return_ts[i]}',
  '{tarif_id[i]}', '{tarif_step[i]}', '{tarif_value[i]}', '{days[i]}',
  '{r_to_pay[i]}', '0', '0', '0', '', '', '', '', 'active', '',
  '{time()}', '{SESSION.user_id}', '', '', '', '{start_ts[i]}', '{SESSION.office}',
  '', '', '', ''
)

-- 3. Создать payment если payment_amount[i] > 0
-- sub_deal_id ссылается на first_rent sub_deal этой же сделки
INSERT INTO rent_sub_deals_act VALUES(
  '', '{deal_id}', 'payment', '30', '{start_ts[i]}', '', '', '', '', '', '',
  '', '', '', '{payment_amount[i]}', '0', '{payment_type}', '', 'pure_payment',
  '', '{time()}', '{SESSION.user_id}', '', '', '{first_rent_sub_id}', '{start_ts[i]}',
  '{SESSION.office}', '', '', '', ''
)

-- 4. Обновить статус товара
UPDATE tovar_rent_items SET status='rented', active_deal_id='{deal_id}'
WHERE item_inv_n='{inv_n[i]}'
```

После всех вставок: генерация RTF и отправка в браузер.

**Валидации на сервере:**
- Все `deal_id` для товаров с одинаковым `client_id` (защита от подмены)
- Все `inv_n` — целочисленные, товар существует и доступен
- `payment_amount` для каждого товара — числа ≥ 0
- `start_date` ≤ `return_date` для каждой строки
- Минимум 2 строки (для 1 товара используется `dogovor_new.php`)

---

## `ndk_multi.rtf` — шаблон

Копия `ndk_1.rtf`. Изменения:

**Шапка** (те же плейсхолдеры, что в одиночном договоре):
- `fioone`, `fiotwo` — ФИО клиента
- `nomer_dogovora` → строка со всеми deal_id: `"5, 6, 7"`
- `pas_n`, `pas_date`, `pas_who`, `phone_1`, `phone_2`, `pasln`
- `actaddress`, `reg_address`
- `acc_date` — дата оформления (сегодня)
- `start_date` — дата выдачи первого товара

**Таблица товаров** — вместо одиночных `itemname`/`itset`/`agr_price` и т.д.:
- Плейсхолдер `items_rows` заменяется на сгенерированные PHP RTF-строки таблицы

Каждая RTF-строка содержит: номер, название товара, инв. №, срок аренды, тариф, сумму.

**Итоговая строка:**
- `rto_pay_total` — общая сумма по всем товарам
- `rto_pay_total_words` — сумма прописью

**Подписи:** `signaturestart`, `signatureend` — те же что в одиночном договоре.

---

## Ссылка в `dogovor_new.php`

В блоке `top_menu` (строка ~1445) добавить:

```html
<a class="div_item" href="/bb/dogovor_multi_new.php">Мультидоговор</a>
```

---

## Ограничения (сознательные)

- **Без доставки:** курьерская доставка не поддерживается в мультиформе (сложность × редкость)
- **Без продления:** продление каждой сделки — через обычный `dogovor_new.php`
- **Один тип оплаты** на все товары (нет `multi`-режима с разбивкой нал+карта)
- **Минимум 2 товара:** для одного — `dogovor_new.php`
- **Только активные товары:** нет поддержки отложенных (takeaway_plan) через мультиформу
