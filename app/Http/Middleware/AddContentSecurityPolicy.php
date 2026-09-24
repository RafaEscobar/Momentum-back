<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self'",
            "style-src 'self' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net",
            "img-src 'self' data:",
            "connect-src 'self'",
            "manifest-src 'self'",
        ];

        if (app()->isProduction()) {
            $directives[] = 'upgrade-insecure-requests';
        } else {
            $directives[5] .= " 'unsafe-eval'";
            $directives[6] .= " 'unsafe-inline'";
            $directives[9] .= ' ws: http: https:';
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $directives));

        return $response;
    }
}
