<?php

namespace App\MyClasses\Search;

/**
 * Результат поиска вместе с тем, КАК он получен.
 *
 * Тир нужен странице результатов, чтобы честно сказать «точных совпадений нет,
 * показываем похожее», а не выдавать частичное совпадение за точный ответ.
 */
class SearchResult
{
    /** Все слова запроса найдены в самом товаре. */
    public const TIER_EXACT = 'exact';

    /** Найдена только часть слов запроса. */
    public const TIER_PARTIAL = 'partial';

    /** Совпало название категории, а не товара. */
    public const TIER_CATEGORY = 'category';

    /** Не найдено ничего. */
    public const TIER_NONE = 'none';

    /** @var int[] */
    private $modelIds;

    /** @var string */
    private $tier;

    /** @param int[] $modelIds */
    public function __construct(array $modelIds, string $tier)
    {
        $this->modelIds = array_values($modelIds);
        $this->tier = $this->modelIds === [] ? self::TIER_NONE : $tier;
    }

    public static function empty(): self
    {
        return new self([], self::TIER_NONE);
    }

    /** @return int[] */
    public function getModelIds(): array
    {
        return $this->modelIds;
    }

    public function getTier(): string
    {
        return $this->tier;
    }

    public function getTotal(): int
    {
        return count($this->modelIds);
    }
}
