<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidatePagination
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawQueryString = $request->server->getString('QUERY_STRING');
        $pageParameters = collect($request->query())
            ->keys()
            ->filter(fn (string $key): bool => $key === 'page' || str_ends_with($key, '_page'))
            ->all();

        $pageParameters = array_values(array_unique([
            ...$pageParameters,
            ...$this->repeatedPageParameters($rawQueryString),
        ]));

        if ($pageParameters !== []) {
            $rules = array_fill_keys(
                $pageParameters,
                ['bail', 'integer', 'min:1', 'max:'.config('security.max_page', 10000)],
            );
            $validator = Validator::make($request->query(), $rules);

            foreach ($this->repeatedPageParameters($rawQueryString) as $parameter) {
                $validator->after(fn ($validator) => $validator->errors()->add(
                    $parameter,
                    'The pagination parameter must only be provided once.',
                ));
            }

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }

        return $next($request);
    }

    /** @return array<int, string> */
    private function repeatedPageParameters(?string $queryString): array
    {
        $counts = [];

        foreach (explode('&', (string) $queryString) as $part) {
            $key = urldecode(explode('=', $part, 2)[0]);
            $baseKey = strstr($key, '[', true) ?: $key;

            if ($baseKey === 'page' || str_ends_with($baseKey, '_page')) {
                $counts[$baseKey] = ($counts[$baseKey] ?? 0) + 1;
            }
        }

        return array_keys(array_filter($counts, fn (int $count): bool => $count > 1));
    }
}
