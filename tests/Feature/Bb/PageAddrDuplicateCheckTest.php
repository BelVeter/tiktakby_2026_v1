<?php

namespace Tests\Feature\Bb;

use bb\classes\ModelWeb;
use bb\Db;
use Tests\TestCase;

/**
 * `rent_model_web.page_addr` has no uniqueness constraint, so the admin form is
 * the only thing standing between the catalog and two pages answering to one
 * URL. Six such collisions exist in production (2026-07); five of them are the
 * same model duplicated inside one language, which the original check could not
 * see because it excluded the edited model by `model_id`.
 *
 * The slug is deliberately shared across languages — one product keeps the same
 * URL tail in ru/en/lt — so the check must scope by `lang` rather than treat a
 * translation as a collision.
 *
 * Talks to the real MySQL instance: bb\Db carries its own hardcoded mysqli
 * connection and ignores Laravel's test database config.
 */
class PageAddrDuplicateCheckTest extends TestCase
{
    private const SLUG = '__phpunit_dup_slug__';

    /** Far outside the real catalog's id range. */
    private const MODEL_A = 990001;
    private const MODEL_B = 990002;

    protected function tearDown(): void
    {
        $this->connection()->query(
            "DELETE FROM rent_model_web WHERE page_addr = '" . self::SLUG . "'"
        );

        parent::tearDown();
    }

    public function test_detects_collision_between_two_different_models(): void
    {
        $keep = $this->insertRow(self::MODEL_A, 'ru');
        $this->insertRow(self::MODEL_B, 'ru');

        $this->assertEquals(
            self::MODEL_B,
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', $keep),
            'A slug claimed by a different model must be reported as taken.'
        );
    }

    /**
     * The production case the original check was blind to: one model holding two
     * `ru` rows (e.g. podogrevatel_philips_avent, web_id 1227/1228).
     */
    public function test_detects_second_row_of_the_same_model_in_the_same_language(): void
    {
        $keep = $this->insertRow(self::MODEL_A, 'ru');
        $this->insertRow(self::MODEL_A, 'ru');

        $this->assertEquals(
            self::MODEL_A,
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', $keep),
            'A duplicate row of the same model is still two pages on one URL.'
        );
    }

    /** Translations legitimately share a slug — they are separate URLs by language. */
    public function test_allows_the_same_slug_in_another_language(): void
    {
        $ru = $this->insertRow(self::MODEL_A, 'ru');
        $this->insertRow(self::MODEL_A, 'en');

        $this->assertFalse(
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', $ru),
            'An en/lt translation of the same product is not a collision.'
        );
    }

    /** The row being edited must never report itself. */
    public function test_row_does_not_collide_with_itself(): void
    {
        $only = $this->insertRow(self::MODEL_A, 'ru');

        $this->assertFalse(
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', $only),
            'Re-saving a page without changing its slug must stay allowed.'
        );
    }

    /** A brand new page (no web_id yet) still has to see an occupied slug. */
    public function test_new_page_sees_an_occupied_slug(): void
    {
        $this->insertRow(self::MODEL_A, 'ru');

        $this->assertEquals(
            self::MODEL_A,
            ModelWeb::hasDublicatesPageUrlCode(self::SLUG, 'ru', 0),
            'Creating a page on a taken slug must be reported.'
        );
    }

    private function insertRow(int $modelId, string $lang): int
    {
        $mysqli = $this->connection();
        $mysqli->query(sprintf(
            "INSERT INTO rent_model_web SET model_id=%d, lang='%s', page_addr='%s'",
            $modelId,
            $mysqli->real_escape_string($lang),
            self::SLUG
        ));

        return (int) $mysqli->insert_id;
    }

    private function connection(): \mysqli
    {
        return Db::getInstance()->getConnection();
    }
}
