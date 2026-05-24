<?php

namespace App\Http\Controllers;

use App\Helpers\UtmTracker;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * POST /track-event
     * Tracks a frontend event (e.g. phone_click, add_to_cart_click) and its UTMs.
     */
    public function trackEvent(Request $request)
    {
        $entityType = $request->input('entity_type');
        
        if (!$entityType) {
            return response()->json(['success' => false, 'error' => 'Missing entity_type'], 400);
        }

        // Only allow specific event types to prevent pollution
        $allowedTypes = ['phone_click', 'add_to_cart_click'];
        if (!in_array($entityType, $allowedTypes)) {
            return response()->json(['success' => false, 'error' => 'Invalid entity_type'], 400);
        }

        $success = UtmTracker::track($entityType, 0);

        return response()->json([
            'success' => $success,
        ]);
    }
}
