<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Фолбэк RedirectController::notFound: на «мусорный» адрес вида /kovriki/<...>/<slug>
 * ищем живую страницу по последнему сегменту и отвечаем 301 на её каноническую версию.
 *
 * Родителей нужно искать по цепочке canonical/sitemap
 * (категория → main_sub_razdel_id → sub_razdel.main_razdel_id), а не через M:N
 * subrazdel_category — у части категорий там нет записи, и живые товары/категории
 * давали 404 вместо 301.
 *
 * Тесты берут реальные данные dev-БД и пропускаются, если подходящих строк нет.
 */
class RedirectFallbackTest extends TestCase
{
    public function test_garbage_prefix_redirects_live_model_of_category_missing_in_subrazdel_category(): void
    {
        $row = DB::selectOne("
            SELECT rmw.page_addr AS slug,
                   CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', tc.cat_url_key, '/', rmw.page_addr) AS url
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON tr.tovar_rent_id = rmw.model_id
            JOIN tovar_rent_cat tc ON tc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN sub_razdel sr ON sr.id_sub_razdel = tc.main_sub_razdel_id
            JOIN razdel r ON r.id_razdel = sr.main_razdel_id
            WHERE rmw.lang = 'ru' AND rmw.status = 'show' AND rmw.page_addr <> ''
              AND NOT EXISTS (SELECT 1 FROM subrazdel_category sc WHERE sc.tovar_rent_cat_id = tc.tovar_rent_cat_id)
              AND (SELECT COUNT(*) FROM rent_model_web x WHERE x.page_addr = rmw.page_addr) = 1
            ORDER BY rmw.model_id
            LIMIT 1
        ");

        if (!$row) {
            $this->markTestSkipped('В dev-БД нет опубликованной модели из категории вне subrazdel_category.');
        }

        $this->get('/kovriki/prokat-detskih-tovarov/no-such-cat/' . $row->slug)
            ->assertStatus(301)
            ->assertRedirect($row->url);
    }

    public function test_garbage_prefix_redirects_category_missing_in_subrazdel_category(): void
    {
        $row = DB::selectOne("
            SELECT tc.cat_url_key AS slug,
                   CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', tc.cat_url_key) AS url
            FROM tovar_rent_cat tc
            JOIN sub_razdel sr ON sr.id_sub_razdel = tc.main_sub_razdel_id
            JOIN razdel r ON r.id_razdel = sr.main_razdel_id
            WHERE tc.cat_url_key <> '' AND sr.url_sub_razdel_name <> '' AND r.url_razdel_name <> ''
              AND NOT EXISTS (SELECT 1 FROM subrazdel_category sc WHERE sc.tovar_rent_cat_id = tc.tovar_rent_cat_id)
              AND NOT EXISTS (SELECT 1 FROM rent_model_web m WHERE m.page_addr = tc.cat_url_key)
              AND (SELECT COUNT(*) FROM tovar_rent_cat d WHERE d.cat_url_key = tc.cat_url_key) = 1
            ORDER BY tc.tovar_rent_cat_id
            LIMIT 1
        ");

        if (!$row) {
            $this->markTestSkipped('В dev-БД нет категории вне subrazdel_category с уникальным slug.');
        }

        $this->get('/kovriki/' . $row->slug)
            ->assertStatus(301)
            ->assertRedirect($row->url);
    }

    public function test_garbage_prefix_with_unknown_slug_stays_404(): void
    {
        $this->get('/kovriki/prokat-detskih-tovarov/no-such-cat/zzz-no-such-slug-12345')
            ->assertStatus(404);
    }
}
