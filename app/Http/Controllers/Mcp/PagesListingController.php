<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\MyClasses\MainPage;
use Illuminate\Support\Facades\File;

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
                    'has_faq' => !empty($page->faq),
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
            'faq' => null,
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
                'faq' => $page->faq ? json_decode($page->faq, true) : null,
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
                'has_faq' => !empty($page->faq),
            ],
            'updated_at' => $page ? $page->change_time : null,
        ];

        return $this->envelope($request->query(), $data);
    }

    /**
     * POST /api/mcp/v1/pages/listing/{slug}/image
     */
    public function uploadImage(Request $request, string $slug)
    {
        $resolved = $this->resolveListingSlug($slug);
        
        if (!$resolved) {
            return response()->json([
                'error' => 'not_found',
                'message' => "Listing page with slug '{$slug}' not found in catalog."
            ], 404);
        }

        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $file = $request->file('image');
        $imgInfo = getimagesize($file->getPathname());
        
        if (!$imgInfo) {
            return response()->json([
                'error' => 'invalid_image',
                'message' => 'Uploaded file is not a valid image.'
            ], 422);
        }

        $sourceWidth = $imgInfo[0];
        $sourceHeight = $imgInfo[1];
        $mimeType = $imgInfo['mime'];

        $sourceImage = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($file->getPathname());
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($file->getPathname());
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($file->getPathname());
                break;
        }

        if (!$sourceImage) {
            return response()->json([
                'error' => 'unsupported_image',
                'message' => 'Failed to process image format.'
            ], 422);
        }

        $targetWidth = 1440;
        $targetHeight = 635;

        // Calculate aspect ratio preserving dimensions
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            // Image is wider than target: fit by width
            $newWidth = $targetWidth;
            $newHeight = (int) round($targetWidth / $sourceRatio);
        } else {
            // Image is taller than target: fit by height
            $newHeight = $targetHeight;
            $newWidth = (int) round($targetHeight * $sourceRatio);
        }

        $dstX = (int) round(($targetWidth - $newWidth) / 2);
        $dstY = (int) round(($targetHeight - $newHeight) / 2);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        
        // Fill background with #F3F9FF (RGB: 243, 249, 255)
        $bgColor = imagecolorallocate($canvas, 243, 249, 255);
        imagefill($canvas, 0, 0, $bgColor);

        imagecopyresampled(
            $canvas, $sourceImage,
            $dstX, $dstY, 0, 0,
            $newWidth, $newHeight, $sourceWidth, $sourceHeight
        );

        // Ensure directory exists
        $dirPath = public_path('img/topmenu');
        if (!File::exists($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        $filename = "{$slug}-prokat.jpg";
        $savePath = $dirPath . '/' . $filename;

        imagejpeg($canvas, $savePath, 88);

        imagedestroy($sourceImage);
        imagedestroy($canvas);

        $dbUrl = "/public/img/topmenu/{$filename}";

        $matchColumns = [
            'level_code' => $resolved['level_code'],
            'url_key' => $slug,
            'lang' => 'ru'
        ];

        $updateData = [
            'h1_pic_url' => $dbUrl,
            'change_time' => now()
        ];

        if (!DB::table('pages')->where($matchColumns)->exists()) {
            $updateData += [
                'title'         => '',
                'h1'            => '',
                'h1_long_text'  => '',
                'block_2_title' => '',
                'code_block_2'  => '',
            ];
        }

        DB::table('pages')->updateOrInsert($matchColumns, $updateData);

        return $this->show($request, $slug);
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
            'faq' => 'nullable|array',
            'faq.*.question' => 'required_with:faq|string|max:500',
            'faq.*.answer' => 'required_with:faq|string|max:5000',
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
        if (array_key_exists('faq', $validated)) {
            $faqArr = $validated['faq'] ?? null;
            $updateData['faq'] = ($faqArr && count($faqArr) > 0)
                ? json_encode($faqArr, JSON_UNESCAPED_UNICODE)
                : null;
        }
        
        if (empty($updateData)) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'No valid fields provided for update.'
            ], 400);
        }

        $updateData['change_time'] = now();

        $matchColumns = [
            'level_code' => $levelCode,
            'url_key' => $slug,
            'lang' => 'ru'
        ];

        if (!DB::table('pages')->where($matchColumns)->exists()) {
            $updateData += [
                'title'         => '',
                'h1'            => '',
                'h1_pic_url'    => '',
                'h1_long_text'  => '',
                'block_2_title' => '',
                'code_block_2'  => '',
            ];
        }

        DB::table('pages')->updateOrInsert($matchColumns, $updateData);

        // Fetch updated record
        return $this->show($request, $slug);
    }

    /**
     * GET /api/mcp/v1/pages/listing/{slug}/products
     *
     * Returns all rental models shown on the listing page with this slug.
     * Supports all catalog levels (razdel, subrazdel, category).
     * Inventory counts use the same status logic as /inventory/free-tree.
     * Pre-aggregated subqueries prevent M:N inflation for multi-category models.
     */
    public function products(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveListingSlug($slug);

        if (!$resolved) {
            return response()->json([
                'error'   => 'not_found',
                'message' => "Listing page with slug '{$slug}' not found in catalog.",
            ], 404);
        }

        $levelCode = $resolved['level_code'];
        $key = $this->cacheKey('pages.listing.products', ['slug' => $slug]);

        $data = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($slug, $levelCode) {
            [$catalogJoin, $params] = $this->buildCatalogJoin($levelCode, $slug);

            $rows = DB::select("
                SELECT DISTINCT
                    rmw.model_id,
                    rmw.l2_name                              AS model_name,
                    rmw.page_addr                            AS model_url,
                    NULLIF(tr.producer, '')                  AS brand,
                    COALESCE(total_sub.total_units, 0)       AS total_units,
                    COALESCE(free_sub.free_units, 0)         AS free_units,
                    prices.price_per_week_byn,
                    prices.price_per_day_byn
                FROM rent_model_web rmw
                JOIN tovar_rent tr ON tr.tovar_rent_id = rmw.model_id
                {$catalogJoin}
                LEFT JOIN (
                    SELECT model_id, COUNT(*) AS total_units
                    FROM tovar_rent_items
                    GROUP BY model_id
                ) total_sub ON total_sub.model_id = rmw.model_id
                LEFT JOIN (
                    SELECT model_id, COUNT(*) AS free_units
                    FROM tovar_rent_items
                    WHERE active_deal_id = 0
                      AND (status IS NULL OR status NOT IN ('в аренде','бронь','ремонт','списан'))
                    GROUP BY model_id
                ) free_sub ON free_sub.model_id = rmw.model_id
                LEFT JOIN (
                    SELECT model_id,
                        MIN(CASE WHEN step = 'week' AND kol_vo = 1 THEN rent_amount END) AS price_per_week_byn,
                        MIN(CASE WHEN step = 'day'  AND kol_vo = 1 THEN rent_amount END) AS price_per_day_byn
                    FROM rent_tarif_act
                    GROUP BY model_id
                ) prices ON prices.model_id = rmw.model_id
                WHERE rmw.lang = 'ru' AND rmw.status = 'show'
                ORDER BY free_units DESC, price_per_week_byn DESC
            ", $params);

            return array_map(fn ($r) => [
                'model_id'           => (int) $r->model_id,
                'model_name'         => $r->model_name,
                'model_url'          => $r->model_url,
                'brand'              => $r->brand,
                'total_units'        => (int) $r->total_units,
                'free_units'         => (int) $r->free_units,
                'is_available'       => (int) $r->free_units > 0,
                'price_per_week_byn' => $r->price_per_week_byn !== null ? (float) $r->price_per_week_byn : null,
                'price_per_day_byn'  => $r->price_per_day_byn !== null ? (float) $r->price_per_day_byn : null,
            ], $rows);
        });

        return $this->envelope(
            array_merge($request->query(), ['slug' => $slug, 'level' => $levelCode]),
            $data
        );
    }

    /**
     * Builds the catalog JOIN fragment and bound parameters for the given level/slug.
     * Called by products() to filter models to the specific listing page.
     * Note: the main query already JOINs tovar_rent tr for brand; the category-level
     * join reuses that alias by adding only the tovar_rent_cat join condition.
     *
     * @return array{0: string, 1: array}  [sql_fragment, bound_params]
     */
    private function buildCatalogJoin(string $levelCode, string $slug): array
    {
        if ($levelCode === 'category') {
            // tr is already joined in the outer query for brand; add category filter
            return [
                'JOIN tovar_rent_cat tc    ON tc.tovar_rent_cat_id = tr.tovar_rent_cat_id
                                         AND tc.cat_url_key = ?',
                [$slug],
            ];
        }

        if ($levelCode === 'subrazdel') {
            return [
                'JOIN tovar_rent_cat tc    ON tc.tovar_rent_cat_id = tr.tovar_rent_cat_id
                 JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
                 JOIN sub_razdel sr        ON sr.id_sub_razdel = sc.id_sub_razdel
                                         AND sr.url_sub_razdel_name = ?',
                [$slug],
            ];
        }

        // razdel
        return [
            'JOIN tovar_rent_cat tc    ON tc.tovar_rent_cat_id = tr.tovar_rent_cat_id
             JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
             JOIN sub_razdel sr        ON sr.id_sub_razdel = sc.id_sub_razdel
             JOIN razdel_subrazdel rs  ON rs.id_sub_razdel = sr.id_sub_razdel
             JOIN razdel r             ON r.id_razdel = rs.id_razdel
                                     AND r.url_razdel_name = ?',
            [$slug],
        ];
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
