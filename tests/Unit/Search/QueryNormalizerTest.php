<?php

namespace Tests\Unit\Search;

use App\MyClasses\Search\QueryNormalizer;
use PHPUnit\Framework\TestCase;

class QueryNormalizerTest extends TestCase
{
    /** @dataProvider stems */
    public function test_stem_strips_russian_endings(string $word, string $expected): void
    {
        $this->assertSame($expected, QueryNormalizer::stem($word));
    }

    public function stems(): array
    {
        return [
            'мн.число'                   => ['коляски', 'коляск'],
            'тв.падеж мн.'               => ['колясками', 'коляск'],
            'кроватки'                   => ['кроватки', 'кроватк'],
            'эргорюкзаки'                => ['эргорюкзаки', 'эргорюкзак'],
            'прилагательное ж.р.'        => ['прогулочная', 'прогулочн'],
            'прилагательное м.р.'        => ['цыганский', 'цыганск'],
            'видеоняня'                  => ['видеоняня', 'видеонян'],
            'короткая основа не режется' => ['весы', 'весы'],
            'нет окончания — как есть'   => ['матрас', 'матрас'],
            'шезлонг'                    => ['шезлонг', 'шезлонг'],
            'латиница не режется'        => ['doona', 'doona'],
            'латиница на -a'             => ['anexa', 'anexa'],
        ];
    }

    /**
     * «для» — три буквы, то есть ниже ft_min_word_len, поэтому оно уходит
     * в короткие токены, а не в основы для FULLTEXT.
     */
    public function test_tokenize_splits_short_and_long_tokens(): void
    {
        $result = QueryNormalizer::tokenize('Коляска  для МЯЧ');

        $this->assertSame(['коляск'], $result['stems']);
        $this->assertSame(['для', 'мяч'], $result['short']);
    }

    public function test_tokenize_drops_boolean_operators(): void
    {
        $result = QueryNormalizer::tokenize('+коляска* -автокресло ~(тест)');

        $this->assertSame(['коляск', 'автокресл', 'тест'], $result['stems']);
    }

    public function test_tokenize_caps_token_count(): void
    {
        $result = QueryNormalizer::tokenize(implode(' ', range(1000, 1040)) . ' коляска');

        $this->assertLessThanOrEqual(10, count($result['stems']) + count($result['short']));
    }

    public function test_tokenize_deduplicates_repeated_words(): void
    {
        $result = QueryNormalizer::tokenize(str_repeat('коляска ', 40));

        $this->assertSame(['коляск'], $result['stems']);
    }

    public function test_tokenize_of_empty_string_is_empty(): void
    {
        $result = QueryNormalizer::tokenize('   ');

        $this->assertSame([], $result['stems']);
        $this->assertSame([], $result['short']);
    }

    public function test_tokenize_survives_sql_injection_attempt(): void
    {
        $result = QueryNormalizer::tokenize("коляска' OR '1'='1");

        $this->assertSame(['коляск'], $result['stems']);
        $this->assertNotContains("'", $result['raw']);
    }
}
