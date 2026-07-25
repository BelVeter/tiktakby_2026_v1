<?php

namespace Tests\Feature\Bb;

use bb\classes\ModelWeb;
use bb\Db;
use Tests\TestCase;

/**
 * Two defences keep one URL pointing at one page.
 *
 * `uniq_page_addr_lang` is the backstop: the database refuses a second row on
 * a slug that is already taken in that language. ModelWeb::hasDublicatesPageUrlCode
 * is the friendly one — the admin form calls it first so an operator gets a
 * message naming the conflicting model instead of a raw duplicate-key error.
 *
 * Both are scoped by `lang`, never by slug alone: one product keeps the same
 * URL tail across ru/en/lt, so a translation has to stay free to share it.
 * Six same-language collisions had accumulated in production by 2026-07; the
 * webp conversion updated only one row of each pair and deleted the image
 * files the other still pointed at.
 *
 * Talks to the real MySQL instance — bb\Db carries its own hardcoded mysqli
 * connection and ignores Laravel's test database config.
 */
class PageAddrDuplicateCheckTest extends TestCase
{
    private const SLUG       = '__phpunit_dup_slug__';
    private const OTHER_SLUG = '__phpunit_other_slug__';

    /** Far outside the real catalog's id range. */
    private const MODEL_A = 990001;
    private const MODEL_B = 990002;

    protected function tearDown(): void
    {
        $this->connection()->query(sprintf(
            "DELETE FROM rent_model_web WHERE page_addr IN ('%s', '%s')",
            self::SLUG,
            self::OTHER_SLUG
        ));

        parent::tearDown();
    }

    // ─── the check the admin form calls ───────────────────────────────────────

    /**
     * The everyday mistake: editing one model and giving it a slug another
     * model already owns.
     */
    public function test_reports_the_model_that_owns_the_slug(): void
    {
        $this->insertRow(self::MODEL_A, 'ru', self::SLUG);
        $editing = $this->insertRow(self::MODEL_B, 'ru', self::OTHER_SLUG);

        $this->assertEquals(
            self::MODEL_A,
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', $editing),
            'A slug held by a different model must be reported as taken.'
        );
    }

    /** Translations legitimately share a slug — they are separate pages by language. */
    public function test_allows_the_same_slug_in_another_language(): void
    {
        $this->insertRow(self::MODEL_A, 'ru', self::SLUG);
        $en = $this->insertRow(self::MODEL_A, 'en', self::SLUG);

        $this->assertFalse(
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'en', $en),
            'An en/lt translation of the same product is not a collision.'
        );
    }

    /** The row being edited must never report itself. */
    public function test_row_does_not_collide_with_itself(): void
    {
        $only = $this->insertRow(self::MODEL_A, 'ru', self::SLUG);

        $this->assertFalse(
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', $only),
            'Re-saving a page without changing its slug must stay allowed.'
        );
    }

    /** A page being created has no web_id yet and still has to see an occupied slug. */
    public function test_new_page_sees_an_occupied_slug(): void
    {
        $this->insertRow(self::MODEL_A, 'ru', self::SLUG);

        $this->assertEquals(
            self::MODEL_A,
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', 0),
            'Creating a page on a taken slug must be reported.'
        );
    }

    // ─── the constraint behind it ─────────────────────────────────────────────

    /**
     * The backstop for everything that never reaches the form: a direct POST,
     * two submits racing each other, or a legacy script inserting blindly.
     */
    public function test_database_rejects_a_second_row_on_the_same_slug_and_language(): void
    {
        $this->insertRow(self::MODEL_A, 'ru', self::SLUG);

        $this->assertFalse(
            $this->tryInsert(self::MODEL_B, 'ru', self::SLUG),
            'uniq_page_addr_lang must reject a second ru row on an occupied slug.'
        );
    }

    /** The constraint must not be what blocks multilingual pages. */
    public function test_database_accepts_the_same_slug_in_another_language(): void
    {
        $this->insertRow(self::MODEL_A, 'ru', self::SLUG);

        $this->assertTrue(
            $this->tryInsert(self::MODEL_A, 'en', self::SLUG),
            'The same slug in another language has to remain insertable.'
        );
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function insertRow(int $modelId, string $lang, string $slug): int
    {
        $this->assertTrue(
            $this->tryInsert($modelId, $lang, $slug),
            "Fixture row for model {$modelId} ({$lang}) could not be created."
        );

        return (int) $this->connection()->insert_id;
    }

    /** @return bool whether the row was accepted by the database */
    private function tryInsert(int $modelId, string $lang, string $slug): bool
    {
        $mysqli = $this->connection();

        try {
            // Depending on the mysqli error mode a rejected insert either returns
            // false or raises — a duplicate key is a normal outcome here.
            return (bool) $mysqli->query(sprintf(
                "INSERT INTO rent_model_web SET model_id=%d, lang='%s', page_addr='%s'",
                $modelId,
                $mysqli->real_escape_string($lang),
                $mysqli->real_escape_string($slug)
            ));
        } catch (\mysqli_sql_exception $e) {
            return false;
        }
    }

    private function connection(): \mysqli
    {
        return Db::getInstance()->getConnection();
    }
}
