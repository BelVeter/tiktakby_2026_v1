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
        $clientIp = $request->ip();
        $serverIp = $request->server('SERVER_ADDR');
        $isLocal = in_array($clientIp, ['127.0.0.1', '::1']) || ($serverIp && $clientIp === $serverIp);

        // Allow Docker bridge networks (172.16.x.x - 172.31.x.x) and local networks for local development
        if (!$isLocal) {
            $ipParts = explode('.', $clientIp);
            if (count($ipParts) === 4) {
                if ($ipParts[0] === '10' || $ipParts[0] === '192' && $ipParts[1] === '168') {
                    $isLocal = true;
                } elseif ($ipParts[0] === '172' && (int)$ipParts[1] >= 16 && (int)$ipParts[1] <= 31) {
                    $isLocal = true;
                }
            }
        }

        if (!$isLocal) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'SMS endpoint is restricted to local system access only.'
            ], 403);
        }

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
