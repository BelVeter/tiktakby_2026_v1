<?php

namespace App\MyClasses\Search;

/**
 * Разбор пользовательского запроса в токены, пригодные для MySQL FULLTEXT.
 *
 * Полноценной морфологии (Porter/mystem) здесь нет намеренно: каталог — меньше
 * тысячи бытовых названий, и отсечения окончания с wildcard-суффиксом хватает,
 * чтобы «коляски» находили «коляска». Правила держим в одном месте, чтобы их
 * можно было расширять по логу search_log.
 */
class QueryNormalizer
{
    /**
     * ft_min_word_len на проде и локально = 4. Токены короче FULLTEXT не
     * индексирует ДАЖЕ с '*' (проверено: «+мяч*» в BOOLEAN MODE даёт 0),
     * поэтому они уходят в отдельную ветку поиска.
     */
    public const MIN_FT_LEN = 4;

    /** Защита от запроса-простыни: больше десяти слов в поиске по прокату не бывает. */
    private const MAX_TOKENS = 10;

    /**
     * Русские окончания, от длинных к коротким. Только кириллица: латинские
     * бренды (doona, anexa) резать нельзя, там 'a' — часть имени, а не окончание.
     */
    private const ENDINGS = [
        'иями', 'ами', 'ями', 'ого', 'его', 'ому', 'ему', 'ыми', 'ими',
        'ая', 'яя', 'ое', 'ее', 'ые', 'ие', 'ой', 'ей', 'ый', 'ий',
        'ом', 'ем', 'ах', 'ях', 'ам', 'ям', 'ов', 'ев', 'ью', 'ия', 'ья',
        'а', 'я', 'ы', 'и', 'у', 'ю', 'е', 'о', 'ь',
    ];

    /**
     * @return array{stems: string[], short: string[], raw: string[]}
     */
    public static function tokenize(string $raw): array
    {
        $raw = mb_strtolower(trim($raw), 'UTF-8');

        // Всё, что не буква и не цифра — разделитель. Заодно снимает булевы
        // операторы FULLTEXT (+ - > < ( ) ~ * " @) и кавычки из SQL-инъекций,
        // которые иначе ломают синтаксис запроса.
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return ['stems' => [], 'short' => [], 'raw' => []];
        }

        $parts = array_slice(array_values(array_unique($parts)), 0, self::MAX_TOKENS);

        $stems = [];
        $short = [];
        foreach ($parts as $token) {
            if (mb_strlen($token, 'UTF-8') < self::MIN_FT_LEN) {
                $short[] = $token;
                continue;
            }
            $stems[] = self::stem($token);
        }

        return ['stems' => $stems, 'short' => $short, 'raw' => $parts];
    }

    /**
     * Отсекает самое длинное известное окончание при условии, что основа
     * остаётся не короче MIN_FT_LEN — иначе FULLTEXT её не увидит:
     * «весы» → «вес» было бы поиском в пустоту.
     */
    public static function stem(string $word): string
    {
        $len = mb_strlen($word, 'UTF-8');
        if ($len <= self::MIN_FT_LEN) {
            return $word;
        }

        foreach (self::ENDINGS as $ending) {
            $endLen = mb_strlen($ending, 'UTF-8');
            if ($len - $endLen < self::MIN_FT_LEN) {
                continue;
            }
            if (mb_substr($word, -$endLen, null, 'UTF-8') === $ending) {
                return mb_substr($word, 0, $len - $endLen, 'UTF-8');
            }
        }

        return $word;
    }
}
