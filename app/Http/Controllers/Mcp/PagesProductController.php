<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class PagesProductController extends BaseController
{
    /**
     * GET /api/mcp/v1/pages/product
     * Returns all product pages with their SEO fields status.
     */
    public function index(Request $request)
    {
        // Build canonical URL for each model via a correlated subquery.
        // We need exactly one URL per model (subrazdel_category is many-to-many,
        // joining naively would produce duplicate rows).
        $models = DB::select("
            SELECT 
                rmw.page_addr as slug,
                rmw.item_name_main as name,
                rmw.model_id,
                rmw.title,
                rmw.meta_description,
                rmw.main_descr,
                rmw.m_pic_alt,
                rmw.l2_alt,
                rmw.breadcrumbs_name,
                rmw.faq,
                rmw.change_time,
                (
                    SELECT CONCAT('/ru/', r2.url_razdel_name, '/', sr2.url_sub_razdel_name, '/', tc2.cat_url_key, '/', rmw.page_addr)
                    FROM tovar_rent tr2
                    JOIN tovar_rent_cat tc2 ON tc2.tovar_rent_cat_id = tr2.tovar_rent_cat_id
                    JOIN subrazdel_category sc2 ON sc2.tovar_rent_cat_id = tc2.tovar_rent_cat_id
                    JOIN sub_razdel sr2 ON sr2.id_sub_razdel = sc2.id_sub_razdel
                    JOIN razdel_subrazdel rs2 ON rs2.id_sub_razdel = sr2.id_sub_razdel
                    JOIN razdel r2 ON r2.id_razdel = rs2.id_razdel
                    WHERE tr2.tovar_rent_id = rmw.model_id
                    ORDER BY r2.razdel_order_num, sr2.order_num_sub_razd
                    LIMIT 1
                ) as full_url
            FROM rent_model_web rmw
            WHERE rmw.lang = 'ru'
              AND rmw.status = 'show'
            ORDER BY rmw.sort_n, rmw.item_name_main
        ");

        $result = [];
        foreach ($models as $model) {
            $result[] = [
                'level' => 'product',
                'slug' => $model->slug,
                'name' => $model->name,
                'full_url' => $model->full_url,
                'seo_score' => [
                    'has_meta_title' => !empty($model->title),
                    'has_meta_description' => !empty($model->meta_description),
                    'has_main_pic_alt' => !empty($model->m_pic_alt),
                    'has_l2_pic_alt' => !empty($model->l2_alt),
                    'has_description' => !empty($model->main_descr),
                    'has_breadcrumb_name' => !empty($model->breadcrumbs_name),
                    'has_faq' => !empty($model->faq),
                ],
                'updated_at' => $model->change_time,
            ];
        }

        return $this->envelope($request->query(), $result);
    }

    /**
     * GET /api/mcp/v1/pages/product/{slug}
     */
    public function show(Request $request, string $slug)
    {
        $model = DB::selectOne("
            SELECT 
                rmw.*,
                r.url_razdel_name,
                sr.url_sub_razdel_name,
                tc.cat_url_key
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON tr.tovar_rent_id = rmw.model_id
            JOIN tovar_rent_cat tc ON tc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
            JOIN sub_razdel sr ON sr.id_sub_razdel = sc.id_sub_razdel
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel r ON r.id_razdel = rs.id_razdel
            WHERE rmw.lang = 'ru'
              AND rmw.page_addr = ?
            LIMIT 1
        ", [$slug]);
        
        if (!$model) {
            return response()->json([
                'error' => 'not_found',
                'message' => "Product page with slug '{$slug}' not found."
            ], 404);
        }

        $fullUrl = "/ru/{$model->url_razdel_name}/{$model->url_sub_razdel_name}/{$model->cat_url_key}/{$model->page_addr}";
        $displayName = $model->item_name_main;

        // Default values when empty
        $defaultValues = [
            'meta_title' => null, // Typically set manually, no generic default in code
            'meta_description' => null,
            'h1' => $displayName, // Read-only
            'main_pic_alt' => null,
            'l2_pic_alt' => null,
            'description' => null,
            'breadcrumb_name' => $displayName, // Defaults to h1 if empty
            'faq' => null,
        ];

        $currentValues = [
            'meta_title' => $model->title ?: null,
            'meta_description' => $model->meta_description ?: null,
            'h1' => $model->item_name_main ?: null, // Keep for context
            'main_pic_alt' => $model->m_pic_alt ?: null,
            'l2_pic_alt' => $model->l2_alt ?: null,
            'description' => $model->main_descr ?: null,
            'breadcrumb_name' => $model->breadcrumbs_name ?: null,
            'faq' => $model->faq ? json_decode($model->faq, true) : null,
        ];

        $data = [
            'level' => 'product',
            'slug' => $slug,
            'name' => $displayName,
            'full_url' => $fullUrl,
            'current_values' => $currentValues,
            'default_values' => $defaultValues,
            'seo_score' => [
                'has_meta_title' => !empty($model->title),
                'has_meta_description' => !empty($model->meta_description),
                'has_main_pic_alt' => !empty($model->m_pic_alt),
                'has_l2_pic_alt' => !empty($model->l2_alt),
                'has_description' => !empty($model->main_descr),
                'has_breadcrumb_name' => !empty($model->breadcrumbs_name),
                'has_faq' => !empty($model->faq),
            ],
            'updated_at' => $model->change_time,
        ];

        return $this->envelope($request->query(), $data);
    }

    /**
     * PATCH /api/mcp/v1/pages/product/{slug}
     */
    public function update(Request $request, string $slug)
    {
        $exists = DB::table('rent_model_web')
            ->where('page_addr', $slug)
            ->where('lang', 'ru')
            ->exists();
            
        if (!$exists) {
            return response()->json([
                'error' => 'not_found',
                'message' => "Product page with slug '{$slug}' not found."
            ], 404);
        }

        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'main_pic_alt' => 'nullable|string|max:125',
            'l2_pic_alt' => 'nullable|string|max:125',
            'description' => 'nullable|string',
            'breadcrumb_name' => 'nullable|string|max:100',
            'faq' => 'nullable|array',
            'faq.*.question' => 'required_with:faq|string|max:500',
            'faq.*.answer' => 'required_with:faq|string|max:5000',
        ]);
        
        // Map API fields to DB columns
        $updateData = [];
        if (array_key_exists('meta_title', $validated)) {
            $updateData['title'] = $validated['meta_title'] ?? '';
        }
        if (array_key_exists('meta_description', $validated)) {
            $updateData['meta_description'] = $validated['meta_description'] ?? '';
        }
        if (array_key_exists('main_pic_alt', $validated)) {
            $updateData['m_pic_alt'] = $validated['main_pic_alt'] ?? '';
        }
        if (array_key_exists('l2_pic_alt', $validated)) {
            $updateData['l2_alt'] = $validated['l2_pic_alt'] ?? '';
        }
        if (array_key_exists('description', $validated)) {
            $updateData['main_descr'] = $validated['description'] ?? '';
        }
        if (array_key_exists('breadcrumb_name', $validated)) {
            $updateData['breadcrumbs_name'] = $validated['breadcrumb_name'] ?? '';
        }
        if (array_key_exists('faq', $validated)) {
            $faqArr = $validated['faq'] ?? null;
            $updateData['faq'] = ($faqArr && count($faqArr) > 0)
                ? json_encode($faqArr, JSON_UNESCAPED_UNICODE)
                : null;
        }
        
        $updateData['change_time'] = date('Y-m-d H:i:s'); // rent_model_web.change_time is DATETIME

        if (empty($updateData)) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'No valid fields provided for update.'
            ], 400);
        }

        DB::table('rent_model_web')
            ->where('page_addr', $slug)
            ->where('lang', 'ru')
            ->update($updateData);

        // Fetch updated record
        return $this->show($request, $slug);
    }
}
