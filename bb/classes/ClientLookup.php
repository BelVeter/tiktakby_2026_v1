<?php
namespace bb\classes;

use bb\Db;

// bb/ не использует composer autoload — легаси-страницы открываются Apache напрямую,
// без vendor/autoload.php, поэтому зависимости объявляем сами (см. CLAUDE.md).
require_once __DIR__ . '/../Db.php';

/**
 * Поиск повторного клиента по телефону для заявок и броней.
 *
 * Совпадением считается равенство ПОСЛЕДНИХ 9 ЦИФР номера. Это не косметика:
 * в clients.phone_1 один и тот же абонент лежит в четырёх видах — «291234567»,
 * «0291234567», «80291234567», «375291234567» (и российские «79236076387»),
 * и только хвост из 9 цифр сводит их вместе.
 *
 * Замер на боевом объёме (51 239 клиентов, 27.08.2026): один батч-запрос на 100 номеров —
 * 36 мс. Поэтому страницы обязаны звать forPhones() ОДИН раз на весь список,
 * а не по разу на строку: построчно это 78 сканов таблицы вместо одного.
 */
class ClientLookup
{
    /** Длина хвоста номера, по которому сличаем. Задана владельцем, не менять без спроса. */
    const PHONE_KEY_LEN = 9;

    /**
     * INT-overflow, а не настоящий номер: rent_orders_arch.phone исторически был int(11),
     * и полные номера (375291234567) молча схлопывались в максимум int (см. docs/db_notes.md, п.4).
     * Таких строк в архиве ~11,8 тыс. — без этой отсечки они все «узнали» бы друг друга.
     */
    const PHONE_OVERFLOW_SENTINEL = '2147483647';

    /** Сколько карточек показываем, если на номере висит пачка дублей. */
    const MAX_MATCHES_PER_PHONE = 10;

    /**
     * С этого числа совпавших карточек номер считается «общим» и доверия ему нет.
     *
     * Это не теория: в базе есть номера, вписанные как phone_1 нескольким РАЗНЫМ людям —
     * рекордсмен 447122445 стоит у шести человек (Демидко, Леончик, Авдонин, Петрович,
     * Курдеко), причём у каждого свой личный phone_2. Похоже, им затыкали пустое поле.
     * Хвост небольшой (~150 номеров из 45 493 имеют 3+ карточки), но именно на них
     * иконка «клиент уже был» показала бы неправду, поэтому такие номера помечаем отдельно.
     */
    const SHARED_PHONE_THRESHOLD = 4;

    /**
     * Приводит любой формат номера к ключу сравнения — последним 9 цифрам.
     * Возвращает null, если номера фактически нет или он короче 9 цифр
     * (в базе таких ~1%: 7-значные городские и мусор вроде «0»).
     */
    public static function phoneKey($raw): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string)$raw);

        if ($digits === '' || $digits === self::PHONE_OVERFLOW_SENTINEL) {
            return null;
        }
        // «0» и «1» — принятые в базе обозначения пустого телефона
        if (strlen($digits) < self::PHONE_KEY_LEN) {
            return null;
        }

        return substr($digits, -self::PHONE_KEY_LEN);
    }

    /**
     * Батч-поиск: массив сырых номеров → [ключ => массив совпавших клиентов].
     *
     * Клиент попадает в выдачу, только если он у нас РЕАЛЬНО был, то есть имеет договоры.
     * Пустая карточка (завели и бросили) повторным клиентом не считается — так решил владелец.
     *
     * @param string[]|int[] $rawPhones
     * @return array<string, array<int, array>>
     */
    public static function forPhones(array $rawPhones): array
    {
        $keys = [];
        foreach ($rawPhones as $raw) {
            $k = self::phoneKey($raw);
            if ($k !== null) {
                $keys[$k] = true;
            }
        }
        if (!$keys) {
            return [];
        }

        $conn = Db::getInstance()->getConnection();

        $escaped = [];
        foreach (array_keys($keys) as $k) {
            $escaped[] = "'" . $conn->real_escape_string($k) . "'";
        }
        $in = implode(',', $escaped);

        // phone_1 в базе хранится ГОЛЫМИ цифрами — проверено 27.08.2026: строк с не-цифрами 0.
        // Поэтому берём RIGHT() напрямую, без REPLACE-лапши, которой обмазаны соседние файлы.
        // phone_2 у пустых заполнен строкой '0', а не NULL и не '' — отсюда явная отсечка.
        $sql = "SELECT client_id, family, name, otch, phone_1, phone_2,
                       arch_n, arch_amount, arch_l_date,
                       RIGHT(phone_1, " . self::PHONE_KEY_LEN . ") AS k1,
                       RIGHT(phone_2, " . self::PHONE_KEY_LEN . ") AS k2
                FROM clients
                WHERE RIGHT(phone_1, " . self::PHONE_KEY_LEN . ") IN ($in)
                   OR (phone_2 <> '0' AND phone_2 <> ''
                       AND RIGHT(phone_2, " . self::PHONE_KEY_LEN . ") IN ($in))";

        $res = $conn->query($sql);
        if (!$res) {
            // Список броней важнее подсказки: не роняем страницу из-за поиска.
            return [];
        }

        $rows = [];
        $needDealCheck = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
            if ((int)$row['arch_n'] <= 0) {
                $needDealCheck[(int)$row['client_id']] = true;
            }
        }
        if (!$rows) {
            return [];
        }

        // arch_n — счётчик договоров в карточке, и он надёжен: на 51 239 клиентов расходится
        // с реальными сделками всего в 18 случаях. Поэтому реальные таблицы сделок дёргаем
        // только для тех, у кого счётчик нулевой, — обычно это пустой список и запроса не будет.
        $dealsFallback = self::clientsHavingDeals($conn, array_keys($needDealCheck));

        $out = [];
        foreach ($rows as $row) {
            $clientId = (int)$row['client_id'];
            $dealsCount = (int)$row['arch_n'];
            $isReturning = $dealsCount > 0 || isset($dealsFallback[$clientId]);

            if (!$isReturning) {
                continue; // карточка есть, договоров не было — не «был у нас»
            }

            $match = [
                'client_id'    => $clientId,
                'family'       => (string)$row['family'],
                'name'         => (string)$row['name'],
                'otch'         => (string)$row['otch'],
                'phone_1'      => (string)$row['phone_1'],
                'phone_2'      => (string)$row['phone_2'],
                'deals_count'  => $dealsCount,
                'deals_amount' => (float)$row['arch_amount'],
                'last_deal_ts' => (int)$row['arch_l_date'],
            ];

            // Один клиент может совпасть и основным, и вторым телефоном — раскладываем по обоим ключам
            foreach ([$row['k1'], $row['k2']] as $k) {
                if ($k === null || $k === '' || !isset($keys[$k])) {
                    continue;
                }
                if (!isset($out[$k])) {
                    $out[$k] = [];
                }
                if (!isset($out[$k][$clientId])) {
                    $out[$k][$clientId] = $match;
                }
            }
        }

        foreach ($out as $k => $matches) {
            // Свежий договор — вперёд: при выборе из дублей оператору нужен последний активный.
            uasort($matches, function ($a, $b) {
                if ($a['last_deal_ts'] !== $b['last_deal_ts']) {
                    return $b['last_deal_ts'] - $a['last_deal_ts'];
                }
                return $b['deals_count'] - $a['deals_count'];
            });
            $out[$k] = array_slice(array_values($matches), 0, self::MAX_MATCHES_PER_PHONE);
        }

        return $out;
    }

    /**
     * Номер, за которым числится слишком много разных карточек, — «общий»:
     * скорее всего им затыкали пустое поле, и совпадение по нему ничего не значит.
     */
    public static function isSharedPhone(array $matches): bool
    {
        return count($matches) >= self::SHARED_PHONE_THRESHOLD;
    }

    /**
     * Поднимает наверх карточки, у которых фамилия совпала с указанной в самой брони.
     *
     * Телефон в заявке приходит вместе с именем (rent_orders.family), и на «общем» номере
     * это единственное, что отличает нужного человека от пяти посторонних. Порядок внутри
     * групп сохраняется, то есть свежесть договора остаётся вторичным критерием.
     */
    public static function rankByName(array $matches, ?string $family): array
    {
        $family = trim((string)$family);
        if ($family === '' || count($matches) < 2) {
            return $matches;
        }

        $needle = mb_strtolower($family);
        $exact = [];
        $rest  = [];
        foreach ($matches as $m) {
            if (mb_strtolower(trim($m['family'])) === $needle) {
                $exact[] = $m;
            } else {
                $rest[] = $m;
            }
        }

        return array_merge($exact, $rest);
    }

    /**
     * Значок «этот клиент у нас уже был» для строки списка заявок/броней.
     *
     * Рендер живёт здесь, а не в каждой странице, чтобы заявки и брони не разъехались:
     * иконка должна читаться одинаково в обоих списках.
     *
     * @param array       $matches совпадения по номеру из forPhones()
     * @param string|null $family  фамилия из самой брони — поднимает нужную карточку наверх
     * @param string      $phone   номер как есть, уйдёт в поиск клиента по клику
     */
    public static function badgeHtml(array $matches, ?string $family, $phone): string
    {
        if (!$matches) {
            return '';
        }

        $matches = self::rankByName($matches, $family);
        $shared  = self::isSharedPhone($matches);
        $top     = $matches[0];
        $n       = count($matches);

        // Человек с иконкой и галочкой — «узнали». На общем номере он серый и со знаком вопроса:
        // формально совпадение есть, но верить ему нельзя.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"'
             . ' fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'
             . '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>'
             . '<circle cx="9" cy="7" r="4"></circle>'
             . ($shared
                 ? '<path d="M19 8a2 2 0 1 1 2.5 2c-.6.3-.9.8-.9 1.5v.5"></path><line x1="20.6" y1="15" x2="20.6" y2="15"></line>'
                 : '<polyline points="16 11 18 13 22 9"></polyline>')
             . '</svg>';

        if ($shared) {
            $title = 'Номер числится за ' . $n . ' разными карточками — похоже, общий. Проверьте вручную.';
            $cls   = 'tt-rc tt-rc--shared';
        } else {
            $who   = trim($top['family'] . ' ' . $top['name']);
            $last  = $top['last_deal_ts'] > 0 ? date('d.m.Y', $top['last_deal_ts']) : 'дата неизвестна';
            $title = $n > 1
                ? ($n . ' карточки на этом номере, свежая — ' . $who . ' (договоров: ' . $top['deals_count'] . ', последний ' . $last . ')')
                : ($who . ' — договоров: ' . $top['deals_count'] . ', последний ' . $last);
            $cls   = 'tt-rc';
        }

        $label = $n > 1 ? '<span class="tt-rc__n">' . $n . '</span>' : '';

        return '<a href="#" class="' . $cls . '"'
             . ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"'
             . ' onclick="ttOpenClientCard(\'' . htmlspecialchars((string)$phone, ENT_QUOTES, 'UTF-8') . '\'); return false;">'
             . $svg . $label . '</a>';
    }

    /**
     * Стили и обработчик клика для badgeHtml(). Подключать один раз на страницу.
     *
     * Карточку открываем POST-формой, а не ссылкой: dogovor_new.php читает только $_POST
     * (см. его get_post()), а GET-параметры игнорирует. Форма строится в момент клика и
     * сразу убирается — вложить <form> в разметку нельзя, строки списка сами состоят из форм.
     */
    public static function badgeAssets(): string
    {
        return '
<style>
.tt-rc { display:inline-flex; align-items:center; gap:2px; vertical-align:middle; margin-left:6px;
         padding:1px 5px; border-radius:10px; text-decoration:none; line-height:1;
         background:#e6f4ea; color:#137333; border:1px solid #b7e1c4; }
.tt-rc:hover { background:#d2ecd9; }
.tt-rc--shared { background:#f1f3f4; color:#5f6368; border-color:#dadce0; }
.tt-rc--shared:hover { background:#e4e6e8; }
.tt-rc__n { font-size:11px; font-weight:bold; }
</style>
<script>
function ttOpenClientCard(phone) {
    var f = document.createElement("form");
    f.method = "post";
    f.action = "/bb/dogovor_new.php";
    f.target = "_blank";
    var a = document.createElement("input");
    a.type = "hidden"; a.name = "action"; a.value = "найти";
    var p = document.createElement("input");
    p.type = "hidden"; p.name = "s_ph"; p.value = phone;
    f.appendChild(a); f.appendChild(p);
    document.body.appendChild(f);
    f.submit();
    document.body.removeChild(f);
}
</script>';
    }

    /**
     * Подстраховка для клиентов с arch_n=0: реально ли у них нет договоров.
     * @return array<int, true>
     */
    private static function clientsHavingDeals(\mysqli $conn, array $clientIds): array
    {
        if (!$clientIds) {
            return [];
        }
        $ids = implode(',', array_map('intval', $clientIds));
        $found = [];

        foreach (['rent_deals_act', 'rent_deals_arch'] as $table) {
            $res = $conn->query("SELECT DISTINCT client_id FROM `$table` WHERE client_id IN ($ids)");
            if (!$res) {
                continue;
            }
            while ($row = $res->fetch_assoc()) {
                $found[(int)$row['client_id']] = true;
            }
        }

        return $found;
    }
}
