<?php

namespace Tests\Unit;

use bb\classes\Similarity;
use PHPUnit\Framework\TestCase;

class SimilarityLevenshteinTest extends TestCase
{
    /**
     * Пары, которые триграммный Jaccard пропускает (см. докблок Similarity):
     * аксессуар/акссесуар — 0.40, Chicco/Chico — 0.40, Батик/Ботик — 0.20.
     * Все три — одиночная опечатка в коротком слове, editSimilarity обязана
     * их ловить через расстояние редактирования, а не через триграммы.
     */
    public function test_edit_similarity_catches_typos_jaccard_misses(): void
    {
        $this->assertGreaterThanOrEqual(0.55, Similarity::editSimilarity('аксессуар', 'акссесуар'));
        $this->assertGreaterThanOrEqual(0.55, Similarity::editSimilarity('Chicco', 'Chico'));
        $this->assertGreaterThanOrEqual(0.55, Similarity::editSimilarity('Батик', 'Ботик'));
    }

    /**
     * Medel/Medela — РАЗНЫЕ реальные бренды (см. спеку 2026-08-14). По
     * расстоянию Левенштейна они дают 0.83 — это ПРЕДУПРЕЖДЕНИЕ (< 1.0),
     * не запрет. Запрет — только точное совпадение нормализованной строки.
     */
    public function test_medel_medela_is_warning_not_exact_duplicate(): void
    {
        $score = Similarity::combinedScore('Medel', 'Medela');

        $this->assertGreaterThanOrEqual(0.55, $score);
        $this->assertLessThan(1.0, $score);
        $this->assertFalse(Similarity::normalize('Medel') === Similarity::normalize('Medela'));
    }

    public function test_combined_score_is_max_of_both_metrics(): void
    {
        $a = 'Maxi Cosi';
        $b = 'подгузники'; // заведомо непохоже ни по триграммам, ни по Левенштейну

        $this->assertSame(
            max(Similarity::score($a, $b), Similarity::editSimilarity($a, $b)),
            Similarity::combinedScore($a, $b)
        );
    }

    public function test_edit_similarity_is_utf8_safe(): void
    {
        // Кириллица — 2 байта/символ в UTF-8. Штатный levenshtein() читает
        // байты, поэтому на разнице в одну БУКВУ дал бы дистанцию 2 (два
        // байта), а не 1. editSimilarity должна считать посимвольно.
        $this->assertEqualsWithDelta(1 - 1 / 5, Similarity::editSimilarity('Батик', 'Ботик'), 0.001);
    }

    public function test_find_similar_by_edit_excludes_exact_matches(): void
    {
        $haystack = ['a' => 'Chicco', 'b' => 'Chico', 'c' => 'CHICCO'];

        $found = Similarity::findSimilarByEdit('Chicco', $haystack);

        $keys = array_column($found, 'key');
        $this->assertContains('b', $keys, 'Chico похож на Chicco — должен найтись');
        $this->assertNotContains('c', $keys, 'CHICCO — точный дубль после normalize(), не "похожий"');
    }

    public function test_find_similar_by_edit_respects_threshold_and_limit(): void
    {
        $haystack = ['a' => 'Chico', 'b' => 'Совершенно другое слово'];

        $found = Similarity::findSimilarByEdit('Chicco', $haystack, 0.55, 1);

        $this->assertCount(1, $found);
        $this->assertSame('a', $found[0]['key']);
    }
}
