<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BbAudioController extends Controller
{
    /**
     * GET /bb-internal/audio/{uuid}
     * Streams audio with bb/ cookie auth (tt_is_logged_in).
     */
    public function stream(string $uuid): BinaryFileResponse
    {
        if (!isset($_COOKIE['tt_is_logged_in'])) {
            abort(403, 'Unauthorized');
        }

        $recording = DB::table('a1_call_recordings')
            ->where('uuid', $uuid)
            ->first();

        if (!$recording) {
            abort(404, 'Recording not found');
        }

        $diskPath = storage_path('app/' . $recording->file_path);
        $allowed  = storage_path('app/a1_recordings') . DIRECTORY_SEPARATOR;
        $resolved = realpath($diskPath);

        if ($resolved === false || strpos($resolved, $allowed) !== 0) {
            abort(404, 'Recording not found');
        }

        if (!file_exists($diskPath)) {
            abort(404, 'Recording file not found on disk');
        }

        $parts    = explode('/', $recording->file_path);
        $filename = end($parts);

        if (request()->has('download')) {
            return response()->download($diskPath, $filename);
        }

        return response()->file($diskPath, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }
}
