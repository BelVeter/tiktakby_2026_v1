<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmsController extends BaseController
{
    /**
     * Send an SMS message via RocketSMS.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'  => 'required|string|max:20',
            // No max: text length limit depends on encoding (160 Latin / 70 Cyrillic per part).
            // We leave enforcement to RocketSMS API itself.
            'text'   => 'required|string',
            // GSM Sender ID is max 11 chars; RocketSMS enforces this on their end as well.
            'sender' => 'nullable|string|max:11',
        ]);

        $rocketSms = new \bb\classes\RocketSMS();

        $phone  = $validated['phone'];
        $text   = $validated['text'];
        $sender = $validated['sender'] ?? null;

        $result = $rocketSms->send($phone, $text, $sender);

        if (isset($result['error'])) {
            return response()->json([
                'query' => $request->all(),
                'data'  => $result,
                'meta'  => [
                    'total_rows'     => null,
                    'currency'       => 'BYN',
                    'data_freshness' => $this->dataFreshness(),
                    'warnings'       => [
                        [
                            'code'    => 'sms_send_failed',
                            'message' => $result['error']
                        ]
                    ]
                ]
            ], 400);
        }

        // Pass null for total_rows — the result is a single SMS send response, not a collection.
        return $this->envelope($request->all(), $result, ['total_rows' => null]);
    }
}
