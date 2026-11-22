<?php

namespace App\MyClasses\Carnival;

/**
 * Порядок карточек карнавального листинга — чистая логика, без БД (tests/Unit/CarnivalListingOrderTest.php).
 *
 * Вход — модели с рейтингом спроса из DemandRanking. Аксессуары (валенки, шляпы, маски, короны…)
 * всегда ниже полноценных костюмов. Дальше:
 *  - листинг БЕЗ фильтра пол/рост ($diversify = true): жадный отбор по рейтингу с поправками —
 *    каждая следующая карточка той же категории весит меньше (CATEGORY_DECAY), категория не повторяется
 *    среди последних CATEGORY_GAP карточек, перевес мальчиков/девочек не больше GENDER_TOLERANCE.
 *    Ограничения ослабляются, если выбрать больше не из чего (страница одной категории, один пол),
 *    и действуют только внутри яруса: модели без спроса (score 0) всегда ниже моделей со спросом;
 *  - листинг С фильтром: только рейтинг спроса (пол уже задан фильтром).
 * Порядок полностью детерминирован: равный спрос → спрос за год → более новая модель (id по убыванию).
 */
class ListingOrder
{
  /** Вес k-й карточки категории: score × 0.75^k — хиты не занимают весь первый экран. */
  public const CATEGORY_DECAY = 0.75;
  /** Категория не повторяется среди стольких последних карточек (пока есть из чего выбрать). */
  public const CATEGORY_GAP = 3;
  /** Допустимый перевес одного пола, после которого берётся лучшая карточка другого пола (или унисекс). */
  public const GENDER_TOLERANCE = 2;

  /**
   * @param array<int, array{id:int, score:float, annual:int, gender:string, cat:int, acc:bool}> $items
   * @param bool $diversify true — листинг без фильтра (баланс М/Д и разнообразие категорий)
   * @return int[] id моделей в порядке показа
   */
  public static function order(array $items, bool $diversify): array
  {
    usort($items, [self::class, 'compareBase']);

    $costumes = [];
    $accessories = [];
    foreach ($items as $it) {
      if ($it['acc']) {
        $accessories[] = $it;
      } else {
        $costumes[] = $it;
      }
    }

    if (!$diversify) {
      return array_merge(array_column($costumes, 'id'), array_column($accessories, 'id'));
    }

    // Баланс пола и разнообразие категорий переставляют карточки только внутри яруса «есть спрос» /
    // «нет спроса»: модель с 0 прокатов никогда не обгоняет модель со спросом. Иначе в подразделах
    // с 2–3 категориями или одним полом ограничения вытаскивали нули на 3–8-е места выше хитов.
    $out = [];
    foreach ([$costumes, $accessories] as $group) {
      foreach (self::splitByDemand($group) as $tier) {
        $out = array_merge($out, self::diversify($tier));
      }
    }

    return $out;
  }

  /** [модели со спросом (score > 0), модели без спроса] — порядок внутри каждой сохраняется. */
  private static function splitByDemand(array $items): array
  {
    $with = [];
    $without = [];
    foreach ($items as $it) {
      if ($it['score'] > 0) {
        $with[] = $it;
      } else {
        $without[] = $it;
      }
    }

    return [$with, $without];
  }

  /** Базовый порядок: спрос в окне ↓, спрос за год ↓, id ↓ (новее — выше). */
  public static function compareBase(array $a, array $b): int
  {
    return [$b['score'], $b['annual'], $b['id']] <=> [$a['score'], $a['annual'], $a['id']];
  }

  private static function diversify(array $items): array
  {
    $out = [];
    $recentCats = [];
    $catCount = [];
    $boys = 0;
    $girls = 0;

    while ($items) {
      $idx = self::pick($items, $recentCats, $catCount, $boys, $girls, true, true)
        ?? self::pick($items, $recentCats, $catCount, $boys, $girls, false, true)   // некого взять по балансу пола
        ?? self::pick($items, $recentCats, $catCount, $boys, $girls, false, false); // остались одни категории подряд

      $it = $items[$idx];
      array_splice($items, $idx, 1);

      $out[] = $it['id'];
      $catCount[$it['cat']] = ($catCount[$it['cat']] ?? 0) + 1;
      $recentCats[] = $it['cat'];
      if (count($recentCats) > self::CATEGORY_GAP) {
        array_shift($recentCats);
      }
      if ($it['gender'] === 'm') {
        $boys++;
      } elseif ($it['gender'] === 'f') {
        $girls++;
      }
    }

    return $out;
  }

  /** Индекс лучшей карточки с учётом ограничений или null. При равном весе побеждает более ранняя в базовом порядке. */
  private static function pick(array $items, array $recentCats, array $catCount, int $boys, int $girls, bool $checkGender, bool $checkGap): ?int
  {
    $best = null;
    $bestWeight = -1.0;

    foreach ($items as $idx => $it) {
      if ($checkGap && in_array($it['cat'], $recentCats, true)) {
        continue;
      }
      if ($checkGender && (
          ($it['gender'] === 'm' && $boys - $girls >= self::GENDER_TOLERANCE)
          || ($it['gender'] === 'f' && $girls - $boys >= self::GENDER_TOLERANCE))) {
        continue;
      }

      $weight = $it['score'] * self::CATEGORY_DECAY ** ($catCount[$it['cat']] ?? 0);
      if ($weight > $bestWeight) {
        $best = $idx;
        $bestWeight = $weight;
      }
    }

    return $best;
  }
}
