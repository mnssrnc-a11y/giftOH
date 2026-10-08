<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Background fetches (the 15-second message refresh, AI recommendations) never show flash messages.
 * Without this, one that lands between a form post and its redirect consumes the message
 * (e.g. "We could not email your approval code"), so the person never sees it.
 */
class KeepFlashForBackgroundRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && ($request->ajax() || $request->wantsJson())) {
            $request->session()->reflash();
        }

        return $next($request);
    }
}
