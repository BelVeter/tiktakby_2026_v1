<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use bb\classes\Model;
use App\MyClasses\L2ModelWeb;
use bb\classes\Tariff;
use App\MyClasses\MainPage;

class FavoritesController extends Controller
{
    public function toggle(Request $request)
    {
        $productId = $request->input('product_id');

        if (!$productId) {
            return response()->json(['status' => 'error', 'message' => 'Product ID required'], 400);
        }

        $favorites = session()->get('favorites', []);

        if (in_array($productId, $favorites)) {
            $favorites = array_diff($favorites, [$productId]);
            $action = 'removed';
        } else {
            $favorites[] = $productId;
            $action = 'added';
        }

        session()->put('favorites', $favorites);
        session()->save();

        return response()->json([
            'status' => 'success',
            'action' => $action,
            'count' => count($favorites)
        ]);
    }

    public function index(Request $request)
    {
        $lang = $request->lang ?? 'ru';
        $favorites = session()->get('favorites', []);
        $models = [];

        if (!empty($favorites)) {
            // Retrieve models. Ideally, you'd have a method to get multiple by IDs, 
            // but strictly following existing patterns, we might iterate or use a whereIn if available.
            // Looking at MainPage.php, it uses L2ModelWeb::getL2ModelWebById($mid, $lang)
            foreach ($favorites as $id) {
                if ($l2m = L2ModelWeb::getL2ModelWebById($id, $lang)) {
                    $models[] = $l2m;
                }
            }
        }

        // Prepare a basic page structure similar to MainPage logic if needed, 
        // or just pass data to a simple view.
        // For now, I'll pass the models to a view.

        // Simulating a MainPage object to reuse header/footer logic if your layout expects it
        // This part might need adjustment based on how 'layouts.app' consumes data.
        // Assuming a simple view for now.

        return view('favorites.index', [
            'models' => $models,
            'lang' => $lang,
            'header' => new \App\MyClasses\Header($lang), // Assuming this class exists based on header usage
        ]);
    }
}
