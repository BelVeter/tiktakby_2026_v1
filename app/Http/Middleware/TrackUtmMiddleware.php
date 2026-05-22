<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TrackUtmMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $parameters = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $hasUtm = false;

        foreach ($parameters as $param) {
            if ($request->has($param)) {
                // Queue the cookie for 30 days (43200 minutes)
                cookie()->queue($param, $request->input($param), 43200);
                $hasUtm = true;
            }
        }

        // If UTM tags are present, also store them in the session for immediate access
        // if needed by the frontend application
        if ($hasUtm) {
            foreach ($parameters as $param) {
                if ($request->has($param)) {
                    $request->session()->put($param, $request->input($param));
                }
            }
        }

        return $next($request);
    }
}
