<?php

namespace App\MyClasses\Carnival;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Рейтинг спроса моделей для карнавальных листингов — вместо ручного rent_model_web.sort_n
 * (ему ~10 лет, наверх были закреплены 28 платьев/снежинок/снегурочек). sort_n в карнавале
 * больше не влияет на порядок, в БД ничего не меняется; в остальных разделах sort_n как раньше.
 *
 * score модели = оплаченные прокаты (rent_deals_arch r_paid > 0 + rent_deals_act) с выдачей в окне
 * [сегодня; +45 дней) год и два года назад, среднее за те годы, когда модель уже была
 * (по самой ранней buy_date её экземпляров, включая выбывшие — tovar_rent_items_arch).
 * Окно смотрит вперёд: в конце ноября поднимается декабрьский спрос, в октябре — Хэллоуин.
 * Новинки (куплены после окна прошлого года, прокатов в окнах нет) получают средний спрос своей
 * категории, а не 0. Старые модели без прокатов остаются с честным 0.
 *
 * Карта считается одним проходом раз в сутки и кэшируется до конца дня: тяжёлый запрос по архиву
 * сделок не идёт на каждое открытие страницы, а порядок (и пагинация) стабилен в течение дня.
 * Порядок на странице — ListingOrder. Тесты: tests/Unit/CarnivalDemandRankingTest.php.
 */
class DemandRanking
{
  public const WINDOW_DAYS = 45;
  public const YEARS_BACK = 2;
  private const CACHE_KEY = 'carnival_demand_ranking_v2';

  /** Начало названия модели, по которому она — аксессуар, а не полноценный костюм. */
  private const ACCESSORY_NAME = '/^(?:(?:карнавальный\s+)?(?:аксессуар|акссесуар|аксесуар|асессуар)'
    . '|набор\s+(?:карнавальных\s+)?(?:аксессуаров|акссесуаров|аксесуаров)'
    . '|(?:маска|шляпа|шляпка|корона|кокошник|колпак|валенки|парик|скипетр|меч|шпага|крылья|шарф|ободок'
    . '|диадема|перчатки|борода|посох|жезл|ковбойская\s+шляпа|рука|пилотка|платок|рюкзак)(?=$|[\s\-,.:;"«»\/()]))/u';

  /** «"Малефисента" головной убор»: описание сразу после названия в кавычках (ведущая кавычка уже обрезана). */
  private const ACCESSORY_DESCRIPTOR = '/^[^"«»]+["»]\s*(?:головной\s+убор|украшение\s+на\s+голову'
    . '|аксессуар|акссесуар|аксесуар)/u';

  /**
   * Переупорядочить строки [model_id, free_num, sort_n] карнавального листинга.
   * При любой ошибке расчёта — прежний порядок по sort_n (листинг не должен падать в сезон).
   *
   * @param array $rows строки из Model::getModelIdsArrayBy*()
   * @param mixed $filter ['gender' => …, 'rost' => …] из CatController или false
   * @param array|null $map для тестов; по умолчанию — map() из кэша
   */
  public static function sortListing(array $rows, $filter, ?array $map = null): array
  {
    try {
      $map = $map ?? self::map();
    } catch (\Throwable $e) {
      Log::warning('Carnival DemandRanking: расчёт не удался, порядок по sort_n', ['error' => $e->getMessage()]);
      usort($rows, function ($a, $b) {
        return $a[2] - $b[2];
      });
      return $rows;
    }

    $rowsById = [];
    $items = [];
    foreach ($rows as $row) {
      $id = (int) $row[0];
      $rowsById[$id][] = $row;
      if (count($rowsById[$id]) > 1) {
        continue;
      }
      $info = $map[$id] ?? ['score' => 0.0, 'annual' => 0, 'gender' => 'u', 'cat' => 0, 'acc' => false];
      $items[] = ['id' => $id, 'score' => (float) $info['score'], 'annual' => (int) $info['annual'],
        'gender' => $info['gender'], 'cat' => (int) $info['cat'], 'acc' => (bool) $info['acc']];
    }

    $out = [];
    foreach (ListingOrder::order($items, !self::isFilterActive($filter)) as $id) {
      foreach ($rowsById[$id] as $row) {
        $out[] = $row;
      }
    }

    return $out;
  }

  /** Фильтр пол/рост на листинге задан (gender=all и пустой рост — не фильтр). */
  public static function isFilterActive($filter): bool
  {
    if (!is_array($filter)) {
      return false;
    }

    return in_array($filter['gender'] ?? null, ['m', 'f', 'u'], true) || (int) ($filter['rost'] ?? 0) > 0;
  }

  /**
   * @return array<int, array{score: float, annual: int, gender: string, cat: int, acc: bool, new: bool}>
   */
  public static function map(?DateTimeImmutable $today = null): array
  {
    $today = $today ?? new DateTimeImmutable('today');
    $day = $today->format('Y-m-d');

    // Один ключ с датой внутри, а не ключ на каждый день: файловый кэш удаляет просроченное только
    // при чтении, и ключи прошлых дней (~185 КБ) копились бы до очередного optimize:clear.
    $cached = Cache::get(self::CACHE_KEY);
    if (is_array($cached) && ($cached['day'] ?? null) === $day && is_array($cached['map'] ?? null)) {
      return $cached['map'];
    }

    $map = self::build($today);
    Cache::put(self::CACHE_KEY, ['day' => $day, 'map' => $map], $today->modify('+1 day +1 hour'));

    return $map;
  }

  /**
   * Окна выдач [начало; конец) в unix-времени: [сегодня; +WINDOW_DAYS) год, два года… назад.
   * @return array<int, array{0:int, 1:int}>
   */
  public static function windows(DateTimeImmutable $today): array
  {
    $out = [];
    for ($k = 1; $k <= self::YEARS_BACK; $k++) {
      $start = $today->setTime(0, 0)->modify("-$k year");
      $out[] = [$start->getTimestamp(), $start->modify('+' . self::WINDOW_DAYS . ' days')->getTimestamp()];
    }

    return $out;
  }

  /**
   * Средний спрос в окне за те годы, когда модель уже была. null — новинка (не было ни в одном окне).
   *
   * @param int[] $counts прокаты в окнах (по порядку windows())
   * @param int|null $firstBuy самая ранняя buy_date экземпляров (0 — неизвестна, считаем давней)
   * @param int[] $windowStarts начала окон
   */
  public static function historyScore(array $counts, ?int $firstBuy, array $windowStarts): ?float
  {
    $sum = 0;
    $years = 0;
    foreach ($windowStarts as $k => $start) {
      $n = (int) ($counts[$k] ?? 0);
      if ($n > 0 || ($firstBuy !== null && $firstBuy <= $start)) {
        $sum += $n;
        $years++;
      }
    }

    return $years > 0 ? $sum / $years : null;
  }

  /** Пол модели: m_sex модели, иначе большинство экземпляров, иначе (и при равенстве) унисекс. */
  public static function gender(string $modelSex, array $itemSexCounts): string
  {
    if (in_array($modelSex, ['m', 'f', 'u'], true)) {
      return $modelSex;
    }

    $counts = array_intersect_key($itemSexCounts, ['m' => 0, 'f' => 0, 'u' => 0]);
    if (!$counts) {
      return 'u';
    }
    arsort($counts);
    $top = array_keys($counts);

    return (count($counts) > 1 && $counts[$top[0]] === $counts[$top[1]]) ? 'u' : $top[0];
  }

  public static function isAccessory(string $l2Name, string $dogName): bool
  {
    if (mb_strtolower(trim($dogName)) === 'аксессуар') {
      return true;
    }
    // Обрезка кавычек/пробелов — посимвольно (/u): trim() режет байты, а «» и NBSP в UTF-8 двухбайтовые.
    $name = preg_replace('/^[\s\x{00A0}"\'«»]+|[\s\x{00A0}"\'«»]+$/u', '', html_entity_decode($l2Name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $name = mb_strtolower((string) $name);

    return (bool) preg_match(self::ACCESSORY_NAME, $name) || (bool) preg_match(self::ACCESSORY_DESCRIPTOR, $name);
  }

  /**
   * Полный расчёт карты по всем моделям (кросс-листинг multi_web тоже попадает в карнавальные страницы).
   * @return array<int, array{score: float, annual: int, gender: string, cat: int, acc: bool, new: bool}>
   */
  public static function build(DateTimeImmutable $today): array
  {
    $windows = self::windows($today);
    $annualFrom = $today->setTime(0, 0)->modify('-1 year')->getTimestamp();
    $todayTs = $today->setTime(0, 0)->getTimestamp();
    $from = min(array_merge(array_column($windows, 0), [$annualFrom]));

    $models = DB::select("SELECT tr.tovar_rent_id AS id, tr.tovar_rent_cat_id AS cat, tr.m_sex,
        c.dog_name, c.cat_type, w.l2_name
      FROM tovar_rent tr
      LEFT JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
      LEFT JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id AND w.lang = 'ru'");

    $itemSex = [];
    $firstBuy = [];
    foreach (DB::select('SELECT model_id, sex, COUNT(*) AS n, MIN(buy_date) AS first_buy FROM tovar_rent_items GROUP BY model_id, sex') as $r) {
      $itemSex[(int) $r->model_id][(string) $r->sex] = (int) $r->n;
      $firstBuy[(int) $r->model_id] = min($firstBuy[(int) $r->model_id] ?? PHP_INT_MAX, (int) $r->first_buy);
    }
    foreach (DB::select('SELECT model_id, MIN(buy_date) AS first_buy FROM tovar_rent_items_arch GROUP BY model_id') as $r) {
      $firstBuy[(int) $r->model_id] = min($firstBuy[(int) $r->model_id] ?? PHP_INT_MAX, (int) $r->first_buy);
    }

    // Оплаченные прокаты (архив) + текущие, экземпляр → модель через живые и выбывшие экземпляры.
    $sums = [];
    $bindings = [];
    foreach ($windows as $k => $w) {
      $sums[] = "SUM(d.start_date >= ? AND d.start_date < ?) AS w$k";
      $bindings[] = $w[0];
      $bindings[] = $w[1];
    }
    $bindings = array_merge($bindings, [$annualFrom, $todayTs, $from, $from]);
    $demand = [];
    foreach (DB::select('SELECT m.model_id, ' . implode(', ', $sums) . ', SUM(d.start_date >= ? AND d.start_date < ?) AS annual
        FROM (SELECT item_inv_n, start_date FROM rent_deals_arch WHERE start_date >= ? AND r_paid > 0
              UNION ALL
              SELECT item_inv_n, start_date FROM rent_deals_act WHERE start_date >= ?) d
        JOIN (SELECT item_inv_n, model_id FROM tovar_rent_items
              UNION
              SELECT item_inv_n, model_id FROM tovar_rent_items_arch) m ON m.item_inv_n = d.item_inv_n
        GROUP BY m.model_id', $bindings) as $r) {
      $demand[(int) $r->model_id] = $r;
    }

    $starts = array_column($windows, 0);
    $map = [];
    $catType = [];
    $byCat = [];
    $byType = [];
    foreach ($models as $m) {
      $id = (int) $m->id;
      $counts = [];
      foreach (array_keys($windows) as $k) {
        $counts[] = isset($demand[$id]) ? (int) $demand[$id]->{"w$k"} : 0;
      }
      $score = self::historyScore($counts, $firstBuy[$id] ?? null, $starts);

      $map[$id] = [
        'score' => $score,
        'annual' => isset($demand[$id]) ? (int) $demand[$id]->annual : 0,
        'gender' => self::gender((string) $m->m_sex, $itemSex[$id] ?? []),
        'cat' => (int) $m->cat,
        'acc' => self::isAccessory((string) $m->l2_name, (string) $m->dog_name),
        'new' => $score === null,
      ];
      $catType[$id] = (int) $m->cat_type;
      if ($score !== null && isset($itemSex[$id])) {   // среднее — только по моделям в наличии
        $byCat[(int) $m->cat][] = $score;
        $byType[(int) $m->cat_type][] = $score;
      }
    }

    // Новинки — средний спрос категории (если в ней нет моделей с историей — всех категорий того же типа).
    $avg = function (array $xs) {
      return $xs ? array_sum($xs) / count($xs) : 0.0;
    };
    foreach ($map as $id => $row) {
      if ($row['score'] === null) {
        $map[$id]['score'] = isset($byCat[$row['cat']]) ? $avg($byCat[$row['cat']]) : $avg($byType[$catType[$id]] ?? []);
      }
    }

    return $map;
  }
}
