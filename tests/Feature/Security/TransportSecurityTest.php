<?php

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

it('redirects safe HTTP requests to the configured HTTPS origin in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.url' => 'https://api.momentum.example']);

    $this->get('/api/user?source=test')
        ->assertStatus(308)
        ->assertRedirect('https://api.momentum.example/api/user?source=test');
});

it('rejects credentials and state changing requests received over HTTP', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.url' => 'https://api.momentum.example']);

    $this->withToken('must-not-be-forwarded')
        ->getJson('/api/user')
        ->assertBadRequest()
        ->assertExactJson(['message' => 'HTTPS is required.'])
        ->assertHeaderMissing('location');

    $this->postJson('/api/login', [
        'email' => 'user@example.com',
        'password' => 'secret',
    ])->assertBadRequest()
        ->assertExactJson(['message' => 'HTTPS is required.'])
        ->assertHeaderMissing('location');
});

it('accepts HTTPS requests in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.url' => 'https://api.momentum.example']);

    $this->getJson('https://api.momentum.example/api/user')
        ->assertUnauthorized();
});

it('ignores forwarded HTTPS headers from untrusted clients', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config([
        'app.url' => 'https://api.momentum.example',
        'trustedproxy.proxies' => [],
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get('/api/user')
        ->assertStatus(308);
});

it('recognizes forwarded HTTPS only from an explicitly trusted proxy', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config([
        'app.url' => 'https://api.momentum.example',
        'trustedproxy.proxies' => ['192.0.2.10'],
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('allows the configured frontend origin without enabling credentials', function () {
    $origin = config('cors.allowed_origins.0');

    $this->withHeader('Origin', $origin)
        ->getJson('/api/user')
        ->assertUnauthorized()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
});

it('does not grant cors access to malicious or null origins', function (string $origin) {
    $allowedOrigin = config('cors.allowed_origins.0');

    $this->withHeader('Origin', $origin)
        ->getJson('/api/user')
        ->assertUnauthorized()
        ->assertHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
})->with([
    'malicious origin' => 'https://evil.example',
    'null origin' => 'null',
    'lookalike origin' => 'http://localhost:5173.evil.example',
]);

it('handles an allowed cors preflight with only approved methods and headers', function () {
    $origin = config('cors.allowed_origins.0');

    $this->call('OPTIONS', '/api/login', server: [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, content-type',
    ])->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->assertHeader('Access-Control-Allow-Headers', 'accept, authorization, content-type, origin, x-requested-with')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
});

it('rejects cors preflight requests for unapproved methods and headers', function () {
    $origin = config('cors.allowed_origins.0');

    $response = $this->call('OPTIONS', '/api/login', server: [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'TRACE',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-dangerous-header',
    ]);

    $response
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->assertHeader('Access-Control-Allow-Headers', 'accept, authorization, content-type, origin, x-requested-with');
    expect($response->headers->get('Access-Control-Allow-Methods'))->not->toContain('TRACE')
        ->and($response->headers->get('Access-Control-Allow-Headers'))->not->toContain('x-dangerous-header');
});

it('does not expose diagnostic routes or sensitive project files', function (string $path) {
    expect($this->get($path)->status())->toBeIn([403, 404]);
})->with([
    '/.env',
    '/.git/config',
    '/auth.json',
    '/backup.sql',
    '/composer.json',
    '/app/Models/User.php',
    '/storage/logs/laravel.log',
    '/telescope',
    '/debugbar/open',
    '/horizon',
]);

it('has no registered diagnostic route prefixes', function () {
    $forbiddenPrefixes = ['telescope', '_debugbar', 'debugbar', 'horizon', 'ignition'];
    $diagnosticRoutes = collect(Route::getRoutes()->getRoutes())
        ->map(fn (IlluminateRoute $route): string => $route->uri())
        ->filter(fn (string $uri): bool => collect($forbiddenPrefixes)
            ->contains(fn (string $prefix): bool => str_starts_with($uri, $prefix)))
        ->values()
        ->all();

    expect($diagnosticRoutes)->toBe([])
        ->and(class_exists('Laravel\Telescope\TelescopeServiceProvider'))->toBeFalse()
        ->and(class_exists('Barryvdh\Debugbar\ServiceProvider'))->toBeFalse();
});

it('fails the production preflight when public contains a sensitive artifact', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'app.url' => 'https://api.momentum.example',
        'cors.allowed_origins' => ['https://momentum.example'],
        'cors.supports_credentials' => false,
        'sanctum.expiration' => 10080,
        'database.default' => 'mysql',
        'database.connections.mysql.username' => 'momentum_runtime',
        'database.connections.mysql.password' => 'configured-at-runtime',
        'logging.default' => 'stack',
        'logging.channels.stack.channels' => ['single'],
        'logging.channels.single.level' => 'warning',
    ]);
    $artifact = public_path('accidental-backup.sql');
    file_put_contents($artifact, 'not real data');

    try {
        $this->artisan('security:check-production')->assertFailed();
    } finally {
        @unlink($artifact);
    }
});
