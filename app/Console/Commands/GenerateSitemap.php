<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
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

        $xml = $this->buildXml($urls);
        $paths = [base_path('sitemap.xml'), public_path('sitemap.xml')];
        foreach ($paths as $path) {
            file_put_contents($path, $xml);
        }

        $this->info('Sitemap generated: ' . count($urls) . ' URLs → ' . implode(', ', $paths));
        return 0;
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
