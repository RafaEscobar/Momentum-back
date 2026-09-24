<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('register', function (Request $request): array {
            $identity = $this->normalizedIdentity($request);

            return [
                Limit::perMinute(5)->by('register:ip:'.$request->ip()),
                Limit::perMinute(5)->by('register:identity:'.$identity.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('login', function (Request $request): array {
            $identity = $this->normalizedIdentity($request);

            return [
                Limit::perMinute(20)->by('login:ip:'.$request->ip()),
                Limit::perMinute(5)->by('login:identity:'.$identity.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('search', function (Request $request): Limit {
            return Limit::perMinute(30)->by($this->actorKey($request, 'search'));
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(120)->by($this->actorKey($request, 'api'));
        });

        RateLimiter::for('expensive-read', function (Request $request): Limit {
            return Limit::perMinute(30)->by($this->actorKey($request, 'expensive-read'));
        });

        RateLimiter::for('bulk-write', function (Request $request): Limit {
            return Limit::perMinute(20)->by($this->actorKey($request, 'bulk-write'));
        });
    }

    private function normalizedIdentity(Request $request): string
    {
        return Str::transliterate(Str::lower(trim($request->string('email')->toString())));
    }

    private function actorKey(Request $request, string $limiter): string
    {
        $actor = $request->user()?->getKey()
            ? 'user:'.$request->user()->getKey()
            : 'ip:'.$request->ip();

        return "{$limiter}:{$actor}";
    }
}
