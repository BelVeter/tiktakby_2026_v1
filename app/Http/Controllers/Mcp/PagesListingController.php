<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\MyClasses\MainPage;

class PagesListingController extends BaseController
{
    /**
     * GET /api/mcp/v1/pages/listing
     * Returns all listing pages (razdel, subrazdel, category) with their SEO fields status.
     */
    public function index(Request $request)
    {
        // Get all items from catalog tables
        $razdels = DB::select("SELECT 'razdel' as level, url_razdel_name as slug, name_razdel_text as name, 
                               CONCAT('/ru/', url_razdel_name) as full_url 
                               FROM razdel ORDER BY razdel_order_num");
                               
        $subrazdels = DB::select("SELECT 'subrazdel' as level, sr.url_sub_razdel_name as slug, 
                                  CONCAT(r.name_razdel_text, ' / ', sr.name_sub_razdel_text) as name,
                                  CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name) as full_url
                                  FROM sub_razdel sr
                                  JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
                                  JOIN razdel r ON r.id_razdel = rs.id_razdel
                                  ORDER BY r.razdel_order_num, sr.order_num_sub_razd");
                                  
        $categories = DB::select("SELECT 'category' as level, tc.cat_url_key as slug, 
                                  tc.rent_cat_name as name,
                                  CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', tc.cat_url_key) as full_url
                                  FROM tovar_rent_cat tc
                                  JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
                                  JOIN sub_razdel sr ON sr.id_sub_razdel = sc.id_sub_razdel
                                  JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
                                  JOIN razdel r ON r.id_razdel = rs.id_razdel
                                  ORDER BY r.razdel_order_num, sr.order_num_sub_razd, tc.cat_sort");

        $catalogItems = array_merge($razdels, $subrazdels, $categories);
        
        // Load all existing SEO page records for lang='ru'
        $pagesRows = DB::table('pages')
            ->where('lang', 'ru')
            ->get()
            ->keyBy(function($item) {
                return $item->level_code . '_' . $item->url_key;
            });

        $result = [];
        foreach ($catalogItems as $item) {
            $key = $item->level . '_' . $item->slug;
            $page = $pagesRows->get($key);
            
            $result[] = [
                'level' => $item->level,
                'slug' => $item->slug,
                'name' => $item->name,
                'full_url' => $item->full_url,
                'seo_score' => [
                    'has_record' => $page !== null,
                    'has_meta_title' => !empty($page->title),
                    'has_meta_description' => !empty($page->meta_description),
                    'has_h1' => !empty($page->h1),
                    'has_intro_text' => !empty($page->h1_long_text),
                    'has_seo_text' => !empty($page->code_block_1),
                    'has_h1_pic_url' => !empty($page->h1_pic_url),
                ],
                'updated_at' => $page ? $page->change_time : null,
            ];
        }

        return $this->envelope($request->query(), $result);
    }

    /**
     * GET /api/mcp/v1/pages/listing/{slug}
     */
    public function show(Request $request, string $slug)
    {
        $resolved = $this->resolveListingSlug($slug);
        
        if (!$resolved) {
            return response()->json([
                'error' => 'not_found',
                'message' => "Listing page with slug '{$slug}' not found in catalog."
            ], 404);
        }

        $levelCode = $resolved['level_code'];
        $fullUrl = $resolved['full_url'];
        $displayName = $resolved['name'];

        $page = DB::table('pages')
            ->where('level_code', $levelCode)
            ->where('url_key', $slug)
            ->where('lang', 'ru')
            ->first();

        // Calculate default values that the site generates when DB fields are empty
        $defaultValues = [
            'meta_title' => "Прокат $displayName в Минске",
            'meta_description' => "$displayName напрокат в Минске без залога. Выгодные цены. Доставка.",
            'h1' => $displayName,
            'intro_text' => null,
            'seo_text' => null,
        ];

        $currentValues = null;
        if ($page) {
            $currentValues = [
                'meta_title' => $page->title ?: null,
                'meta_description' => $page->meta_description ?: null,
                'h1' => $page->h1 ?: null,
                'intro_text' => $page->h1_long_text ?: null,
                'seo_text' => $page->code_block_1 ?: null,
                'h1_pic_url' => $page->h1_pic_url ?: null,
            ];
        }

        $data = [
            'level' => $levelCode,
            'slug' => $slug,
            'name' => $displayName,
            'full_url' => $fullUrl,
            'current_values' => $currentValues,
            'default_values' => $defaultValues,
            'seo_score' => [
                'has_record' => $page !== null,
                'has_meta_title' => !empty($page->title),
                'has_meta_description' => !empty($page->meta_description),
                'has_h1' => !empty($page->h1),
                'has_intro_text' => !empty($page->h1_long_text),
                'has_seo_text' => !empty($page->code_block_1),
                'has_h1_pic_url' => !empty($page->h1_pic_url),
            ],
            'updated_at' => $page ? $page->change_time : null,
        ];

        return $this->envelope($request->query(), $data);
    }

    /**
     * PATCH /api/mcp/v1/pages/listing/{slug}
     */
    public function update(Request $request, string $slug)
    {
        $resolved = $this->resolveListingSlug($slug);
        
        if (!$resolved) {
            return response()->json([
                'error' => 'not_found',
                'message' => "Listing page with slug '{$slug}' not found in catalog."
            ], 404);
        }

        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'h1' => 'nullable|string|max:100',
            'intro_text' => 'nullable|string',
            'seo_text' => 'nullable|string',
            'h1_pic_url' => 'nullable|string|max:255',
        ]);

        $levelCode = $resolved['level_code'];
        
        // Map API fields to DB columns
        $updateData = [];
        if (array_key_exists('meta_title', $validated)) {
            $updateData['title'] = $validated['meta_title'] ?? '';
        }
        if (array_key_exists('meta_description', $validated)) {
            $updateData['meta_description'] = $validated['meta_description'] ?? '';
        }
        if (array_key_exists('h1', $validated)) {
            $updateData['h1'] = $validated['h1'] ?? '';
        }
        if (array_key_exists('intro_text', $validated)) {
            $updateData['h1_long_text'] = $validated['intro_text'] ?? '';
        }
        if (array_key_exists('seo_text', $validated)) {
            $updateData['code_block_1'] = $validated['seo_text'] ?? '';
        }
        if (array_key_exists('h1_pic_url', $validated)) {
            $updateData['h1_pic_url'] = $validated['h1_pic_url'] ?? '';
        }
        
        $updateData['change_time'] = now();

        if (empty($updateData)) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'No valid fields provided for update.'
            ], 400);
        }

        DB::table('pages')->updateOrInsert(
            [
                'level_code' => $levelCode,
                'url_key' => $slug,
                'lang' => 'ru'
            ],
            $updateData
        );

        // Fetch updated record
        return $this->show($request, $slug);
    }

    /**
     * Resolves a slug to its level code and computes its full URL and display name.
     */
    private function resolveListingSlug(string $slug): ?array
    {
        // Check category first as it's the most common
        $cat = DB::table('tovar_rent_cat AS tc')
            ->select('tc.rent_cat_name', 'r.url_razdel_name', 'sr.url_sub_razdel_name', 'tc.cat_url_key')
            ->join('subrazdel_category AS sc', 'sc.tovar_rent_cat_id', '=', 'tc.tovar_rent_cat_id')
            ->join('sub_razdel AS sr', 'sr.id_sub_razdel', '=', 'sc.id_sub_razdel')
            ->join('razdel_subrazdel AS rs', 'rs.id_sub_razdel', '=', 'sr.id_sub_razdel')
            ->join('razdel AS r', 'r.id_razdel', '=', 'rs.id_razdel')
            ->where('tc.cat_url_key', $slug)
            ->first();
            
        if ($cat) {
            return [
                'level_code' => 'category',
                'name' => $cat->rent_cat_name,
                'full_url' => "/ru/{$cat->url_razdel_name}/{$cat->url_sub_razdel_name}/{$cat->cat_url_key}"
            ];
        }

        // Check subrazdel
        $sub = DB::table('sub_razdel AS sr')
            ->select('sr.name_sub_razdel_text', 'r.url_razdel_name', 'sr.url_sub_razdel_name')
            ->join('razdel_subrazdel AS rs', 'rs.id_sub_razdel', '=', 'sr.id_sub_razdel')
            ->join('razdel AS r', 'r.id_razdel', '=', 'rs.id_razdel')
            ->where('sr.url_sub_razdel_name', $slug)
            ->first();
            
        if ($sub) {
            return [
                'level_code' => 'subrazdel',
                'name' => $sub->name_sub_razdel_text,
                'full_url' => "/ru/{$sub->url_razdel_name}/{$sub->url_sub_razdel_name}"
            ];
        }

        // Check razdel
        $razdel = DB::table('razdel')
            ->select('name_razdel_text', 'url_razdel_name')
            ->where('url_razdel_name', $slug)
            ->first();
            
        if ($razdel) {
            return [
                'level_code' => 'razdel',
                'name' => $razdel->name_razdel_text,
                'full_url' => "/ru/{$razdel->url_razdel_name}"
            ];
        }

        return null;
    }
}
