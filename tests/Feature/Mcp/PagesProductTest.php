<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * Tests for the L3 product SEO endpoints:
 *   GET   /pages/product
 *   GET   /pages/product/{slug}
 *   PATCH /pages/product/{slug}
 *   PATCH /pages/product/bulk
 *   GET   /pages/history
 *
 * Runs against the real catalog — legacy tables have no factories.
 *
 * DatabaseTransactions cannot undo any of it: `rent_model_web` is MyISAM, so
 * writes land immediately and survive the rollback. Until this was handled the
 * suite left a trail behind every run — cloned rows from duplicateRow() and
 * real catalog rows still carrying test copy. Each row this class touches is
 * therefore snapshotted and put back by hand in tearDown.
 */
class PagesProductTest extends McpTestCase
{
    use DatabaseTransactions;

    private const SLUG_INDEX = 'uniq_page_addr_lang';

    /** web_id => full row as it looked before the test, restored in tearDown. */
    private array $rowSnapshots = [];

    /** Rows created by duplicateRow(), deleted in tearDown. */
    private array $clonedWebIds = [];

    private bool $slugIndexDropped = false;

    protected function tearDown(): void
    {
        foreach ($this->clonedWebIds as $webId) {
            DB::table('rent_model_web')->where('web_id', $webId)->delete();
        }

        foreach ($this->rowSnapshots as $webId => $row) {
            DB::table('rent_model_web')->where('web_id', $webId)->update($row);
        }

        // Only after the clones are gone, or the unique index cannot be rebuilt.
        $this->restoreSlugIndex();

        $this->clonedWebIds  = [];
        $this->rowSnapshots  = [];

        parent::tearDown();
    }

    /** Remember a row's current contents so tearDown can undo whatever the test does to it. */
    private function snapshotRow(int $webId): void
    {
        if (isset($this->rowSnapshots[$webId])) {
            return;
        }

        $row = (array) DB::table('rent_model_web')->where('web_id', $webId)->first();
        unset($row['web_id']);

        $this->rowSnapshots[$webId] = $row;
    }

    /** A model that has a canonical URL, used as the happy-path fixture. */
    private function anyLinkedRow(): object
    {
        $row = DB::selectOne("
            SELECT rmw.web_id, rmw.model_id, rmw.page_addr, rmw.title, rmw.meta_description
            FROM rent_model_web rmw
            JOIN tovar_rent tr    ON tr.tovar_rent_id    = rmw.model_id
            JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id AND c.cat_url_key <> ''
            JOIN sub_razdel sr    ON sr.id_sub_razdel    = c.main_sub_razdel_id AND sr.url_sub_razdel_name <> ''
            JOIN razdel r         ON r.id_razdel         = sr.main_razdel_id    AND r.url_razdel_name <> ''
            WHERE rmw.lang = 'ru' AND rmw.status = 'show'
              AND (SELECT COUNT(*) FROM rent_model_web d WHERE d.lang='ru' AND d.page_addr = rmw.page_addr) = 1
            LIMIT 1
        ");

        if (!$row) {
            $this->markTestSkipped('no linked L3 model in this dataset');
        }

        // Most callers go on to PATCH this row, and MyISAM will keep the change.
        $this->snapshotRow((int) $row->web_id);

        return $row;
    }

    // ─── GET /pages/product ───────────────────────────────────────────────────

    public function test_index_returns_envelope_with_pagination_and_summary(): void
    {
        $r = $this->mcp('pages/product', ['per_page' => 5]);
        $r->assertStatus(200);

        $r->assertJsonStructure([
            'query',
            'data' => ['*' => [
                'level', 'slug', 'model_id', 'name', 'full_url',
                'razdel_slug', 'subrazdel_slug', 'category_slug',
                'status', 'active_units', 'is_indexable', 'seo_score', 'updated_at',
            ]],
            'meta' => ['total_rows', 'returned_rows', 'page', 'per_page', 'summary' => ['total_matching', 'missing']],
        ]);

        $this->assertLessThanOrEqual(5, count($r->json('data')));
        $this->assertSame($r->json('meta.total_rows'), $r->json('meta.summary.total_matching'));
    }

    public function test_index_summary_counts_match_the_filtered_set(): void
    {
        $all     = $this->mcp('pages/product', ['per_page' => 1]);
        $missing = $this->mcp('pages/product', ['per_page' => 1, 'missing' => 'meta_description']);

        $this->assertSame(
            $all->json('meta.summary.missing.meta_description'),
            $missing->json('meta.total_rows'),
            'missing=meta_description must return exactly the pages counted as missing it'
        );
    }

    public function test_index_missing_filter_rejects_unknown_field(): void
    {
        $this->mcp('pages/product', ['missing' => 'nope'])->assertStatus(422);
    }

    public function test_index_fields_full_includes_values_and_summary_does_not(): void
    {
        $lean = $this->mcp('pages/product', ['per_page' => 1]);
        $full = $this->mcp('pages/product', ['per_page' => 1, 'fields' => 'full']);

        $this->assertArrayNotHasKey('current_values', $lean->json('data.0'));
        $this->assertArrayHasKey('current_values', $full->json('data.0'));
        $this->assertArrayHasKey('description', $full->json('data.0.current_values'));
    }

    public function test_index_category_filter_narrows_the_set(): void
    {
        $slug = DB::table('rent_model_web AS rmw')
            ->join('tovar_rent AS tr', 'tr.tovar_rent_id', '=', 'rmw.model_id')
            ->join('tovar_rent_cat AS c', 'c.tovar_rent_cat_id', '=', 'tr.tovar_rent_cat_id')
            ->where('rmw.lang', 'ru')->where('rmw.status', 'show')
            ->where('c.cat_url_key', '<>', '')
            ->value('c.cat_url_key');

        if (!$slug) {
            $this->markTestSkipped('no category slugs in this dataset');
        }

        $r = $this->mcp('pages/product', ['category' => $slug, 'per_page' => 500]);
        $r->assertStatus(200);
        $this->assertNotEmpty($r->json('data'));

        foreach ($r->json('data') as $row) {
            $this->assertSame($slug, $row['category_slug']);
        }
    }

    public function test_index_summary_flag_adds_category_breakdown(): void
    {
        $r = $this->mcp('pages/product', ['per_page' => 1, 'summary' => 1]);
        $r->assertStatus(200);
        $this->assertIsArray($r->json('meta.summary.by_category'));
        $this->assertArrayHasKey('total', $r->json('meta.summary.by_category.0'));
    }

    // ─── canonical URL ────────────────────────────────────────────────────────

    /**
     * The URL must come from the single-parent chain the site uses in
     * ModelWeb::getUrlPageAddress (cat.main_sub_razdel_id →
     * sub_razdel.main_razdel_id), not from the many-to-many subrazdel_category
     * chain, which yields a non-canonical URL for models in multi-linked
     * categories.
     */
    public function test_full_url_matches_the_canonical_chain(): void
    {
        $r = $this->mcp('pages/product', ['per_page' => 50]);

        foreach ($r->json('data') as $row) {
            if ($row['full_url'] === null) {
                continue;
            }

            $expected = DB::selectOne("
                SELECT CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', c.cat_url_key, '/', ?) AS url
                FROM tovar_rent tr
                JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
                JOIN sub_razdel sr    ON sr.id_sub_razdel    = c.main_sub_razdel_id
                JOIN razdel r         ON r.id_razdel         = sr.main_razdel_id
                WHERE tr.tovar_rent_id = ?
            ", [$row['slug'], $row['model_id']]);

            $this->assertSame($expected->url, $row['full_url'], "canonical URL mismatch for {$row['slug']}");
        }
    }

    // ─── GET /pages/product/{slug} ────────────────────────────────────────────

    public function test_show_returns_current_and_default_values(): void
    {
        $row = $this->anyLinkedRow();

        $r = $this->mcp('pages/product/' . $row->page_addr);
        $r->assertStatus(200);
        $r->assertJsonStructure([
            'data' => [
                'slug', 'model_id', 'full_url', 'is_indexable',
                'current_values' => ['meta_title', 'meta_description', 'h1', 'l2_name', 'main_pic_alt', 'main_pic_title', 'description', 'breadcrumb_name', 'faq'],
                'default_values',
                'seo_score',
            ],
        ]);
        $this->assertSame($row->page_addr, $r->json('data.slug'));
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->mcp('pages/product/definitely-not-a-slug-xyz')->assertStatus(404);
    }

    /**
     * A model whose category is not wired into the catalog tree has no URL but
     * is still an editable page — it must be readable, not 404. Previously the
     * INNER JOIN hid ~15% of rows, including pages present in sitemap.xml.
     */
    public function test_show_serves_models_without_canonical_url(): void
    {
        $orphan = DB::selectOne("
            SELECT rmw.page_addr
            FROM rent_model_web rmw
            LEFT JOIN tovar_rent tr      ON tr.tovar_rent_id    = rmw.model_id
            LEFT JOIN tovar_rent_cat c   ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id AND c.cat_url_key <> ''
            LEFT JOIN sub_razdel sr      ON sr.id_sub_razdel    = c.main_sub_razdel_id AND sr.url_sub_razdel_name <> ''
            LEFT JOIN razdel r           ON r.id_razdel         = sr.main_razdel_id    AND r.url_razdel_name <> ''
            WHERE rmw.lang = 'ru' AND r.url_razdel_name IS NULL
              AND (SELECT COUNT(*) FROM rent_model_web d WHERE d.lang='ru' AND d.page_addr = rmw.page_addr) = 1
            LIMIT 1
        ");

        if (!$orphan) {
            $this->markTestSkipped('every model resolves to a URL in this dataset');
        }

        $r = $this->mcp('pages/product/' . $orphan->page_addr);
        $r->assertStatus(200);
        $this->assertNull($r->json('data.full_url'));
        $this->assertFalse($r->json('data.is_indexable'));
        $this->assertContains('no_canonical_url', array_column($r->json('meta.warnings'), 'code'));
    }

    public function test_show_refuses_ambiguous_slug_with_409(): void
    {
        $row = $this->anyLinkedRow();
        $this->duplicateRow($row->web_id);

        $r = $this->mcp('pages/product/' . $row->page_addr);
        $r->assertStatus(409);
        $r->assertJsonPath('error', 'slug_conflict');
        $this->assertCount(2, $r->json('models'));
    }

    // ─── PATCH /pages/product/{slug} ──────────────────────────────────────────

    public function test_patch_updates_fields_and_returns_fresh_values(): void
    {
        $row = $this->anyLinkedRow();

        $r = $this->patchProduct($row->page_addr, [
            'meta_description' => 'Тестовое описание для проката.',
            'breadcrumb_name'  => 'Тест крошка',
        ]);

        $r->assertStatus(200);
        $this->assertSame('Тестовое описание для проката.', $r->json('data.current_values.meta_description'));
        $this->assertSame('Тест крошка', $r->json('data.current_values.breadcrumb_name'));
        $this->assertTrue($r->json('data.seo_score.has_meta_description'));
    }

    /** h1 (item_name_main) and l2_name were readable but not writable before. */
    public function test_patch_can_write_h1_and_l2_name(): void
    {
        $row = $this->anyLinkedRow();

        $r = $this->patchProduct($row->page_addr, ['h1' => 'Новый H1', 'l2_name' => 'Новое имя карточки']);
        $r->assertStatus(200);

        $this->assertSame('Новый H1', $r->json('data.current_values.h1'));
        $this->assertSame('Новое имя карточки', $r->json('data.current_values.l2_name'));
        $this->assertSame('Новый H1', $r->json('data.name'));
    }

    /**
     * meta description / alt / title are interpolated into HTML attributes by
     * an unescaped @yield, so a quote or a tag would break the tag.
     */
    public function test_patch_strips_markup_from_attribute_fields(): void
    {
        $row = $this->anyLinkedRow();

        $r = $this->patchProduct($row->page_addr, [
            'meta_description' => 'Прокат <b>колясок</b> "без залога"   в Минске',
            'main_pic_alt'     => 'Коляска "Chicco"',
        ]);

        $this->assertSame('Прокат колясок без залога в Минске', $r->json('data.current_values.meta_description'));
        $this->assertSame('Коляска Chicco', $r->json('data.current_values.main_pic_alt'));
    }

    /**
     * strip_tags() would treat the lone "<" as an opening tag and swallow the
     * rest of the string; only well-formed tags may be removed.
     */
    public function test_patch_keeps_text_after_a_lone_angle_bracket(): void
    {
        $row = $this->anyLinkedRow();

        $r = $this->patchProduct($row->page_addr, ['meta_description' => 'Коляска весом <5 кг для прогулок']);
        $this->assertSame('Коляска весом 5 кг для прогулок', $r->json('data.current_values.meta_description'));
    }

    /**
     * sql_mode is empty on production, so MySQL truncates oversized values
     * instead of failing — length has to be rejected in the app, in bytes.
     */
    public function test_patch_rejects_description_larger_than_the_text_column(): void
    {
        $row = $this->anyLinkedRow();
        $before = DB::table('rent_model_web')->where('web_id', $row->web_id)->value('main_descr');

        $this->patchProduct($row->page_addr, ['description' => str_repeat('д', 40000)]) // 80 000 bytes
            ->assertStatus(422);

        $this->assertSame($before, DB::table('rent_model_web')->where('web_id', $row->web_id)->value('main_descr'));
    }

    public function test_bulk_marks_oversized_description_invalid(): void
    {
        $row = $this->anyLinkedRow();

        $r = $this->patchMcp('pages/product/bulk', ['pages' => [
            ['slug' => $row->page_addr, 'description' => str_repeat('д', 40000)],
        ]]);

        $this->assertSame('invalid', $r->json('data.0.status'));
        $this->assertArrayHasKey('description', $r->json('data.0.errors'));
    }

    /** item_name_main is the product name everywhere — editable, but not erasable. */
    public function test_patch_refuses_to_blank_h1_or_l2_name(): void
    {
        $row = $this->anyLinkedRow();

        $this->patchProduct($row->page_addr, ['h1' => ''])->assertStatus(422);
        $this->patchProduct($row->page_addr, ['l2_name' => null])->assertStatus(422);

        $this->assertNotSame('', DB::table('rent_model_web')->where('web_id', $row->web_id)->value('item_name_main'));
    }

    /** HTML body copy must survive untouched — only attribute fields are stripped. */
    public function test_patch_keeps_html_in_description(): void
    {
        $row  = $this->anyLinkedRow();
        $html = '<p>Описание с <b>разметкой</b></p>';

        $r = $this->patchProduct($row->page_addr, ['description' => $html]);
        $this->assertSame($html, $r->json('data.current_values.description'));
    }

    public function test_patch_enforces_meta_description_limit(): void
    {
        $row = $this->anyLinkedRow();

        $this->patchProduct($row->page_addr, ['meta_description' => str_repeat('д', 161)])
            ->assertStatus(422);
    }

    public function test_patch_returns_404_for_unknown_slug(): void
    {
        $this->patchProduct('definitely-not-a-slug-xyz', ['meta_title' => 'x'])->assertStatus(404);
    }

    /**
     * The old implementation matched on page_addr without a limit, so a shared
     * slug silently rewrote several models at once.
     */
    public function test_patch_refuses_ambiguous_slug_and_writes_nothing(): void
    {
        $row = $this->anyLinkedRow();
        $this->duplicateRow($row->web_id);

        $this->patchProduct($row->page_addr, ['meta_title' => 'Должно быть отклонено'])
            ->assertStatus(409);

        $titles = DB::table('rent_model_web')->where('page_addr', $row->page_addr)->pluck('title');
        foreach ($titles as $title) {
            $this->assertNotSame('Должно быть отклонено', $title);
        }
    }

    public function test_patch_writes_change_history(): void
    {
        $row = $this->anyLinkedRow();
        DB::table('mcp_content_versions')->where('page_slug', $row->page_addr)->delete();

        $this->patchProduct($row->page_addr, ['meta_description' => 'Версия один.']);

        $history = DB::table('mcp_content_versions')
            ->where('page_type', 'product')
            ->where('page_slug', $row->page_addr)
            ->where('field', 'meta_description')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('Версия один.', $history->new_value);
        $this->assertSame($row->meta_description ?: null, $history->old_value);
    }

    // ─── PATCH /pages/product/bulk ────────────────────────────────────────────

    public function test_bulk_reports_per_item_status(): void
    {
        $row = $this->anyLinkedRow();

        $r = $this->patchMcp('pages/product/bulk', ['pages' => [
            ['slug' => $row->page_addr, 'meta_description' => 'Пакетное описание.'],
            ['slug' => 'definitely-not-a-slug-xyz', 'meta_description' => 'нет такой'],
            ['slug' => $row->page_addr, 'meta_description' => str_repeat('д', 200)],
        ]]);

        $r->assertStatus(200);
        $statuses = array_column($r->json('data'), 'status');
        $this->assertSame(['updated', 'not_found', 'invalid'], $statuses);
        $this->assertSame(['meta_description'], $r->json('data.0.changed_fields'));
        $this->assertSame(1, $r->json('meta.summary.updated'));
        $this->assertSame(1, $r->json('meta.summary.not_found'));
        $this->assertSame(1, $r->json('meta.summary.invalid'));

        $this->assertSame(
            'Пакетное описание.',
            DB::table('rent_model_web')->where('web_id', $row->web_id)->value('meta_description'),
            'a failing sibling item must not roll back a good write'
        );
    }

    public function test_bulk_reports_unchanged_when_value_is_identical(): void
    {
        $row = $this->anyLinkedRow();
        $this->patchProduct($row->page_addr, ['meta_description' => 'Одно и то же.']);

        $r = $this->patchMcp('pages/product/bulk', ['pages' => [
            ['slug' => $row->page_addr, 'meta_description' => 'Одно и то же.'],
        ]]);

        $this->assertSame('unchanged', $r->json('data.0.status'));
        $this->assertSame([], $r->json('data.0.changed_fields'));
    }

    public function test_bulk_rejects_oversized_batch(): void
    {
        $pages = array_fill(0, 101, ['slug' => 'x', 'meta_title' => 'y']);
        $this->patchMcp('pages/product/bulk', ['pages' => $pages])->assertStatus(422);
    }

    // ─── GET /pages/history ───────────────────────────────────────────────────

    public function test_history_endpoint_lists_recorded_changes(): void
    {
        $row = $this->anyLinkedRow();

        // A version row is only written when the value actually changes, so the
        // title has to differ from whatever this fixture currently holds —
        // otherwise the PATCH is a no-op and there is no history to list.
        $current = DB::table('rent_model_web')->where('web_id', $row->web_id)->value('title');
        $title   = $current === 'История заголовка A' ? 'История заголовка B' : 'История заголовка A';

        $this->patchProduct($row->page_addr, ['meta_title' => $title]);

        $r = $this->mcp('pages/history', ['page_type' => 'product', 'slug' => $row->page_addr]);
        $r->assertStatus(200);
        $r->assertJsonStructure(['data' => ['*' => ['id', 'page_type', 'slug', 'field', 'old_value', 'new_value', 'source', 'created_at']]]);

        $this->assertContains('meta_title', array_column($r->json('data'), 'field'));
    }

    // ─── auth ─────────────────────────────────────────────────────────────────

    public function test_endpoints_require_token(): void
    {
        $this->assertRequiresToken('pages/product');
        $this->assertRequiresToken('pages/history');
        $this->json('PATCH', '/api/mcp/v1/pages/product/bulk', ['pages' => []])->assertStatus(401);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function patchProduct(string $slug, array $body)
    {
        return $this->patchMcp('pages/product/' . $slug, $body);
    }

    private function patchMcp(string $path, array $body)
    {
        return $this->json('PATCH', '/api/mcp/v1/' . ltrim($path, '/'), $body, [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
    }

    /**
     * Clone a rent_model_web row (new web_id, same page_addr) to force a slug
     * collision.
     *
     * `uniq_page_addr_lang` exists precisely to make this state unreachable, so
     * the index comes off for the duration of the test. The endpoints still owe
     * a 409 if a collision ever turns up — from a restore, a legacy script, or
     * a copy of the database predating the constraint — and that is what these
     * tests pin down.
     */
    private function duplicateRow(int $webId): void
    {
        $this->dropSlugIndex();

        $copy = (array) DB::table('rent_model_web')->where('web_id', $webId)->first();
        unset($copy['web_id']);

        DB::table('rent_model_web')->insert($copy);

        $this->clonedWebIds[] = (int) DB::getPdo()->lastInsertId();
    }

    private function dropSlugIndex(): void
    {
        if ($this->slugIndexDropped || !$this->slugIndexExists()) {
            return;
        }

        DB::statement('ALTER TABLE rent_model_web DROP INDEX ' . self::SLUG_INDEX);
        $this->slugIndexDropped = true;
    }

    private function restoreSlugIndex(): void
    {
        if (!$this->slugIndexDropped) {
            return;
        }

        DB::statement(
            'ALTER TABLE rent_model_web ADD UNIQUE INDEX ' . self::SLUG_INDEX . ' (page_addr(191), lang)'
        );
        $this->slugIndexDropped = false;
    }

    private function slugIndexExists(): bool
    {
        return !empty(DB::select(
            'SHOW INDEX FROM rent_model_web WHERE Key_name = ?',
            [self::SLUG_INDEX]
        ));
    }
}
