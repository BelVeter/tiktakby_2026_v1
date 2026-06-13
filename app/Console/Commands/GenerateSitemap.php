<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--no-verify : Skip the HTTP 200-status verification pass}';
    protected $description = 'Generate sitemap.xml from database catalog structure';

    private const BASE_URL = 'https://tiktak.by';

    public function handle(): int
    {
        $urls = [];

        $urls[] = ['loc' => self::BASE_URL . '/ru', 'changefreq' => 'weekly', 'priority' => '1.0'];

        $staticPages = ['about', 'conditions', 'delivery', 'payment', 'contacts', 'policy', 'premium-start'];
        foreach ($staticPages as $page) {
            $urls[] = ['loc' => self::BASE_URL . '/ru/' . $page, 'changefreq' => 'monthly', 'priority' => '0.8'];
        }

        // Special alias page
        $urls[] = ['loc' => self::BASE_URL . '/ru/medical-prokat/bioptron', 'changefreq' => 'monthly', 'priority' => '0.7'];

        $razdels = DB::select("
            SELECT url_razdel_name, razdel_change_time
            FROM razdel r
            WHERE url_razdel_name != ''
              AND EXISTS (
                SELECT 1 FROM razdel_subrazdel rs2
                JOIN sub_razdel sr2 ON sr2.id_sub_razdel = rs2.id_sub_razdel
                JOIN tovar_rent_cat c2 ON c2.main_sub_razdel_id = sr2.id_sub_razdel
                JOIN tovar_rent tr2 ON tr2.tovar_rent_cat_id = c2.tovar_rent_cat_id
                JOIN rent_model_web rmw2 ON rmw2.model_id = tr2.tovar_rent_id
                JOIN tovar_rent_items ti ON ti.model_id = tr2.tovar_rent_id
                WHERE rs2.id_razdel = r.id_razdel
                  AND rmw2.status = 'show' AND rmw2.lang = 'ru'
              )
            ORDER BY razdel_order_num, id_razdel
        ");

        foreach ($razdels as $r) {
            $urls[] = [
                'loc'        => self::BASE_URL . '/ru/' . $r->url_razdel_name,
                'lastmod'    => $this->formatDate($r->razdel_change_time),
                'changefreq' => 'weekly',
                'priority'   => '0.9',
            ];
        }

        $subrazddels = DB::select("
            SELECT r.url_razdel_name, sr.url_sub_razdel_name, sr.sub_razdel_change_time
            FROM sub_razdel sr
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE sr.url_sub_razdel_name != '' AND r.url_razdel_name != ''
              AND EXISTS (
                SELECT 1 FROM tovar_rent_cat c2
                JOIN tovar_rent tr2 ON tr2.tovar_rent_cat_id = c2.tovar_rent_cat_id
                JOIN rent_model_web rmw2 ON rmw2.model_id = tr2.tovar_rent_id
                JOIN tovar_rent_items ti ON ti.model_id = tr2.tovar_rent_id
                WHERE c2.main_sub_razdel_id = sr.id_sub_razdel
                  AND rmw2.status = 'show' AND rmw2.lang = 'ru'
              )
            ORDER BY r.url_razdel_name, sr.url_sub_razdel_name
        ");

        foreach ($subrazddels as $sr) {
            $urls[] = [
                'loc'        => self::BASE_URL . '/ru/' . $sr->url_razdel_name . '/' . $sr->url_sub_razdel_name,
                'lastmod'    => $this->formatDate($sr->sub_razdel_change_time),
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ];
        }

        $categories = DB::select("
            SELECT r.url_razdel_name, sr.url_sub_razdel_name, c.cat_url_key
            FROM tovar_rent_cat c
            JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE c.cat_url_key != '' AND sr.url_sub_razdel_name != '' AND r.url_razdel_name != ''
              AND EXISTS (
                SELECT 1 FROM tovar_rent tr2
                JOIN rent_model_web rmw2 ON rmw2.model_id = tr2.tovar_rent_id
                JOIN tovar_rent_items ti ON ti.model_id = tr2.tovar_rent_id
                WHERE tr2.tovar_rent_cat_id = c.tovar_rent_cat_id
                  AND rmw2.status = 'show' AND rmw2.lang = 'ru'
              )
            ORDER BY r.url_razdel_name, sr.url_sub_razdel_name, c.cat_url_key
        ");

        foreach ($categories as $cat) {
            $urls[] = [
                'loc'        => self::BASE_URL . '/ru/' . $cat->url_razdel_name . '/' . $cat->url_sub_razdel_name . '/' . $cat->cat_url_key,
                'changefreq' => 'weekly',
                'priority'   => '0.7',
            ];
        }

        $models = DB::select("
            SELECT r.url_razdel_name, sr.url_sub_razdel_name, c.cat_url_key, rmw.page_addr
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON tr.tovar_rent_id = rmw.model_id
            JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE rmw.lang = 'ru' AND rmw.page_addr != '' AND rmw.status = 'show'
                AND sr.url_sub_razdel_name != '' AND r.url_razdel_name != '' AND c.cat_url_key != ''
                AND EXISTS (SELECT 1 FROM tovar_rent_items ti WHERE ti.model_id = rmw.model_id)
            ORDER BY r.url_razdel_name, sr.url_sub_razdel_name, c.cat_url_key, rmw.page_addr
        ");

        foreach ($models as $m) {
            $urls[] = [
                'loc'        => self::BASE_URL . '/ru/' . $m->url_razdel_name . '/' . $m->url_sub_razdel_name . '/' . $m->cat_url_key . '/' . $m->page_addr,
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ];
        }

        if (!$this->option('no-verify')) {
            $urls = $this->filterReachable($urls);
        }

        $xml = $this->buildXml($urls);
        $paths = [base_path('sitemap.xml'), public_path('sitemap.xml')];
        foreach ($paths as $path) {
            file_put_contents($path, $xml);
        }

        $this->info('Sitemap generated: ' . count($urls) . ' URLs → ' . implode(', ', $paths));
        return 0;
    }

    // HTTP-коды, по которым URL ТОЧНО лишний в sitemap:
    // редиректы (страница переехала) и «навсегда нет» (gone).
    // Намеренно НЕ трогаем 5xx, 403, 429 и сетевые сбои (null) — это транзиентные
    // состояния / троттлинг, по ним нельзя судить, что страница невалидна.
    private const SITEMAP_DROP_CODES = [301, 302, 303, 307, 308, 404, 410];

    /**
     * Отсеивает из sitemap URL, которые на самом деле редиректят (3xx) или удалены (404/410).
     *
     * Зачем: генератор строит адреса напрямую из структуры каталога в БД, но часть
     * категорий/моделей на лету редиректится приложением на родителя (источники
     * разнородные — Laravel-роуты, .htaccess, легаси; таблица `redirects` их НЕ
     * покрывает). По правилам SEO в sitemap должны быть только конечные 200-страницы.
     *
     * Проверка идёт HEAD-запросами небольшими пачками (Http::pool) с паузой между ними,
     * редиректы НЕ следуются — 301 так и остаётся 301 и выкидывается. Малый размер пачки
     * и пауза нужны, чтобы Cloudflare/origin не отдавал ложные 5xx/429 под залпом запросов
     * (иначе валидные 200-страницы отвалились бы ложно). Команда запускается раз в неделю
     * в 03:00, нагрузка незаметна.
     *
     * Консервативность: исключаем ТОЛЬКО по явным «плохим» кодам (см. SITEMAP_DROP_CODES).
     * Любой неопределённый ответ (200, 5xx, 429, таймаут) трактуем как «оставить» — лучше
     * сохранить лишний URL, чем выкинуть рабочую страницу.
     *
     * Защита от обнуления: если по итогу отсеялось > 15% списка (сайт лёг / массовый
     * троттлинг во время генерации) — считаем проверку ненадёжной и оставляем ВЕСЬ список.
     */
    private function filterReachable(array $urls): array
    {
        $total = \count($urls);
        if ($total === 0) {
            return $urls;
        }

        $statusByLoc = [];
        $locs = array_column($urls, 'loc');
        $chunks = array_chunk($locs, 10);

        foreach ($chunks as $i => $batch) {
            try {
                $responses = Http::pool(function ($pool) use ($batch) {
                    foreach ($batch as $loc) {
                        $pool->as($loc)
                            ->timeout(8)
                            ->withOptions(['allow_redirects' => false])
                            ->head($loc);
                    }
                });
            } catch (\Throwable $e) {
                $responses = [];
            }

            foreach ($batch as $loc) {
                $resp = $responses[$loc] ?? null;
                $statusByLoc[$loc] = ($resp && !$resp instanceof \Throwable) ? $resp->status() : null;
            }

            // Пауза между пачками — бережём origin/Cloudflare от залпа.
            if ($i < \count($chunks) - 1) {
                usleep(200000); // 0.2s
            }
        }

        $kept = array_values(array_filter($urls, function ($u) use ($statusByLoc) {
            $status = $statusByLoc[$u['loc']] ?? null;
            return !in_array($status, self::SITEMAP_DROP_CODES, true);
        }));

        $keptCount = \count($kept);
        $dropped = $total - $keptCount;

        // Защита: подозрительно много отсеяно — проверка ненадёжна, ничего не режем.
        if ($dropped > $total * 0.15) {
            $this->warn("Verification flagged {$dropped}/{$total} URLs (>15%) — treating check as unreliable, keeping ALL URLs.");
            return $urls;
        }

        if ($dropped > 0) {
            $this->warn("Excluded {$dropped} redirecting/gone URL(s) from sitemap:");
            foreach ($urls as $u) {
                $status = $statusByLoc[$u['loc']] ?? null;
                if (in_array($status, self::SITEMAP_DROP_CODES, true)) {
                    $this->line("  [{$status}] {$u['loc']}");
                }
            }
        }

        return $kept;
    }

    private function formatDate(?string $value): ?string
    {
        if (!$value) return null;

        $ts = is_numeric($value) ? (int)$value : strtotime($value);
        if (!$ts) return null;

        return date('Y-m-d', $ts);
    }

    private function buildXml(array $urls): string
    {
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $u) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
            if (!empty($u['lastmod'])) {
                $lines[] = '    <lastmod>' . $u['lastmod'] . '</lastmod>';
            }
            if (!empty($u['changefreq'])) {
                $lines[] = '    <changefreq>' . $u['changefreq'] . '</changefreq>';
            }
            if (!empty($u['priority'])) {
                $lines[] = '    <priority>' . $u['priority'] . '</priority>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        return implode("\n", $lines) . "\n";
    }
}
