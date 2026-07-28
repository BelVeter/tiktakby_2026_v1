<?php

namespace bb\classes;

/**
 * Сравнение названий на «похожесть» — для ловли опечаток и почти-дублей.
 *
 * Почему не `levenshtein()` и не `similar_text()`: обе функции PHP работают с
 * БАЙТАМИ, а кириллица в UTF-8 занимает по два байта на букву. На русских
 * названиях они дают бессмысленный результат, поэтому здесь всё через
 * `preg_split('//u')` и посимвольные операции.
 *
 * Метод — коэффициент Жаккара на триграммах. Он устойчив к перестановкам,
 * вставкам и лишним словам, в отличие от расстояния редактирования.
 *
 * Перед сравнением строка нормализуется: нижний регистр, схлопывание
 * пробелов/дефисов/кавычек и замена кириллических гомоглифов на латиницу.
 * Последнее ловит настоящую ловушку каталога: «Сybex» с русской «С» и «Cybex»
 * с латинской выглядят одинаково, но это разные строки для базы.
 *
 * Порог 0.55 подобран замером на реальных названиях категорий и производителей
 * (июль 2026): ниже начинают попадаться разные товары, выше — теряются опечатки
 * вида «прогулочние»/«прогулочные».
 */
class Similarity
{
    /** Кириллические буквы, неотличимые на вид от латинских. */
    private const HOMOGLYPHS = [
        'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h',
        'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x',
    ];

    /** Порог, ниже которого названия считаем разными. */
    const DEFAULT_THRESHOLD = 0.55;

    /**
     * Приводит название к сравнимому виду.
     * «Maxi-Cosi», «Maxi Cosi» и «maxicosi» дают одну и ту же строку.
     */
    public static function normalize($value)
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = strtr($value, self::HOMOGLYPHS);

        return preg_replace('/[\s\-_«»"\'`.,()]+/u', '', $value);
    }

    /**
     * @return string[] уникальные триграммы строки
     */
    private static function trigrams($value)
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        $length = count($chars);

        if ($length < 3) {
            return $length ? [implode('', $chars)] : [];
        }

        $out = [];
        for ($i = 0; $i <= $length - 3; $i++) {
            $out[] = $chars[$i] . $chars[$i + 1] . $chars[$i + 2];
        }

        return array_values(array_unique($out));
    }

    /**
     * Похожесть двух названий: 0.0 (ничего общего) .. 1.0 (совпадают после нормализации).
     *
     * @return float
     */
    public static function score($a, $b)
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }

        $ta = self::trigrams($na);
        $tb = self::trigrams($nb);

        $union = count(array_unique(array_merge($ta, $tb)));

        return $union ? count(array_intersect($ta, $tb)) / $union : 0.0;
    }

    /**
     * Находит похожие, но не идентичные названия.
     *
     * @param  string             $needle
     * @param  array<int|string, string>  $haystack  ключ => название
     * @param  float              $min
     * @param  int                $limit
     * @return array<int, array{key: int|string, label: string, score: float}>
     */
    public static function findSimilar($needle, array $haystack, $min = self::DEFAULT_THRESHOLD, $limit = 5)
    {
        $out = [];

        foreach ($haystack as $key => $label) {
            $score = self::score($needle, (string) $label);

            // score = 1.0 это точный дубль, его показывают отдельным сообщением.
            if ($score >= $min && $score < 1.0) {
                $out[] = ['key' => $key, 'label' => (string) $label, 'score' => round($score, 3)];
            }
        }

        usort($out, function ($x, $y) {
            return $y['score'] < $x['score'] ? -1 : ($y['score'] > $x['score'] ? 1 : 0);
        });

        return array_slice($out, 0, $limit);
    }

    /**
     * Точное совпадение после нормализации: «Автокресла» и «авто кресла».
     *
     * @param  array<int|string, string>  $haystack
     * @return int|string|false  ключ найденного либо false
     */
    public static function findExact($needle, array $haystack)
    {
        $normalized = self::normalize($needle);

        if ($normalized === '') {
            return false;
        }

        foreach ($haystack as $key => $label) {
            if (self::normalize($label) === $normalized) {
                return $key;
            }
        }

        return false;
    }
}
