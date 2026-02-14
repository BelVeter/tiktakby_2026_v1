<?php

namespace App\Http\Controllers;

use App\MyClasses\L2ModelWeb;
use Illuminate\Http\Request;

class FavoritesController extends Controller
{
    /**
     * Show the favorites page shell (cards loaded via AJAX)
     */
    public function index()
    {
        return view('favorites.index');
    }

    /**
     * AJAX endpoint: returns rendered product card HTML for given model IDs
     */
    public function getCards(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['html' => '', 'count' => 0]);
        }

        // Limit to 50 items max for safety
        $ids = array_slice($ids, 0, 50);

        $html = '';
        $count = 0;

        foreach ($ids as $id) {
            $id = intval($id);
            if ($id <= 0)
                continue;

            $l2 = L2ModelWeb::getL2ModelWebById($id, 'ru');
            if ($l2) {
                $html .= view('includes.l2_model_block', ['l2' => $l2])->render();
                $count++;
            }
        }

        return response()->json([
            'html' => $html,
            'count' => $count,
        ]);
    }
}
