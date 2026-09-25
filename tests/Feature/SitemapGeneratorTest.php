<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateSitemap;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * В sitemap попадают только конечные страницы по каноническому адресу.
 *
 * Тесты берут реальные данные dev-БД; проверки, для которых в БД нет подходящих строк
 * (например, подраздела с двумя разделами), пропускаются.
 */
class SitemapGeneratorTest extends TestCase
{
    private const BASE = 'https://tiktak.by';

    private function locs(): array
    {
        $urls = app(GenerateSitemap::class)->collectUrls();
        $this->assertNotEmpty($urls);

        return array_column($urls, 'loc');
    }

    public function test_no_duplicate_urls(): void
    {
        $locs = $this->locs();

        $this->assertSame([], array_keys(array_filter(array_count_values($locs), fn ($n) => $n > 1)));
    }

    public function test_subrazdel_pages_use_only_the_canonical_razdel(): void
    {
        $canonical = [];
        foreach (DB::select("
            SELECT sr.url_sub_razdel_name AS sub, r.url_razdel_name AS razdel
            FROM sub_razdel sr JOIN razdel r ON r.id_razdel = sr.main_razdel_id
            WHERE sr.url_sub_razdel_name != ''") as $row) {
            $canonical[$row->sub][] = $row->razdel;
        }

        foreach ($this->locs() as $loc) {
            $parts = explode('/', substr($loc, strlen(self::BASE . '/ru/')));
            if (count($parts) < 2 || !isset($canonical[$parts[1]])) {
                continue;
            }
            $this->assertContains($parts[0], $canonical[$parts[1]], "Адрес не по канонической цепочке: $loc");
        }
    }

    public function test_subrazdel_linked_to_two_razdels_is_listed_once(): void
    {
        $multi = DB::selectOne("
            SELECT sr.url_sub_razdel_name AS sub, r.url_razdel_name AS extra
            FROM razdel_subrazdel rs
            JOIN sub_razdel sr ON sr.id_sub_razdel = rs.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE rs.id_razdel <> sr.main_razdel_id AND sr.url_sub_razdel_name != '' AND r.url_razdel_name != ''
            LIMIT 1");
        if (!$multi) {
            $this->markTestSkipped('В dev-БД нет подраздела, привязанного к двум разделам.');
        }

        $foreign = self::BASE . '/ru/' . $multi->extra . '/' . $multi->sub;
        foreach ($this->locs() as $loc) {
            $this->assertStringStartsNotWith($foreign, $loc, "Подраздел продублирован под чужим разделом: $loc");
        }
    }

    public function test_redirected_bioptron_category_is_excluded_but_alias_is_kept(): void
    {
        $category = '/ru/medical-prokat/bioptron-prokat-minsk/prokat-bioptron-minsk';
        $alias = '/ru/medical-prokat/bioptron';

        $this->get($category)->assertStatus(301)->assertRedirect($alias);

        $locs = $this->locs();
        $this->assertNotContains(self::BASE . $category, $locs);
        $this->assertContains(self::BASE . $alias, $locs);
    }

    public function test_every_url_is_valid_and_odd_slugs_are_encoded(): void
    {
        $locs = $this->locs();

        foreach ($locs as $loc) {
            $this->assertMatchesRegularExpression('#^https://tiktak\.by(/[A-Za-z0-9_.~%-]+)*$#', $loc, "Недопустимые символы в адресе: $loc");
        }

        $odd = DB::select("
            SELECT page_addr FROM rent_model_web
            WHERE lang = 'ru' AND status = 'show' AND page_addr != '' AND page_addr NOT REGEXP '^[A-Za-z0-9_.-]+$'");
        foreach ($odd as $row) {
            foreach ($locs as $loc) {
                $this->assertStringEndsNotWith('/' . $row->page_addr, $loc, "Slug с недопустимыми символами попал в sitemap как есть: $loc");
            }
        }
    }
}
