<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

it('returns json for unauthenticated API requests', function () {
    $this->get('/api/dashboard')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json');
});

it('returns 403 json for forbidden actions', function () {
    Route::get('/api/testing/forbidden', function (): never {
        throw new AuthorizationException;
    });

    $this->get('/api/testing/forbidden')
        ->assertForbidden()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('returns 404 json for missing API routes', function () {
    $this->get('/api/testing/missing-route')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('returns 422 json for validation errors', function () {
    Route::get('/api/testing/validation', function (): never {
        throw ValidationException::withMessages(['field' => ['The field is invalid.']]);
    });

    $this->get('/api/testing/validation')
        ->assertUnprocessable()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonValidationErrors(['field']);
});

it('does not expose internal exception details when debug mode is disabled', function () {
    $previousDebug = config('app.debug');
    config(['app.debug' => false]);

    Route::get('/api/testing/server-error', function (): never {
        throw new RuntimeException(
            'DATABASE_PASSWORD=super-secret SQLSTATE[HY000] SELECT * FROM users '.
            'at /var/www/momentum/app/Secrets.php Authorization: Bearer leaked-token'
        );
    });

    try {
        $this->get('/api/testing/server-error')
            ->assertInternalServerError()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('message', 'Server Error')
            ->assertDontSee('DATABASE_PASSWORD', false)
            ->assertDontSee('super-secret', false)
            ->assertDontSee('SQLSTATE', false)
            ->assertDontSee('SELECT * FROM users', false)
            ->assertDontSee('/var/www/momentum', false)
            ->assertDontSee('leaked-token', false)
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');
    } finally {
        config(['app.debug' => $previousDebug]);
    }
});
