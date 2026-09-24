<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBody
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maximumBytes = (int) config('security.max_request_body_kb', 1024) * 1024;
        $contentLength = (int) $request->headers->get('Content-Length', 0);

        if ($maximumBytes > 0 && ($contentLength > $maximumBytes || strlen($request->getContent()) > $maximumBytes)) {
            return new JsonResponse([
                'message' => 'The request body is too large.',
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
