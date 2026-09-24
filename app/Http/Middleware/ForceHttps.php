<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction() || $request->isSecure()) {
            return $next($request);
        }

        if ($request->headers->has('Authorization')
            || $request->cookies->count() > 0
            || ! $request->isMethodSafe()) {
            return response()->json(['message' => 'HTTPS is required.'], 400);
        }

        $target = rtrim((string) config('app.url'), '/').'/'.ltrim($request->getRequestUri(), '/');

        return redirect()->away($target, 308);

    }
}
