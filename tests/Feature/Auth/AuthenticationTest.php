<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Rafa',
        'email' => 'rafa@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'device_name' => 'Registration browser',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.name', 'Rafa')
        ->assertJsonPath('user.email', 'rafa@example.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
        ->assertJsonMissingPath('user.password');

    $user = User::where('email', 'rafa@example.com')->firstOrFail();

    expect(Hash::check('secure-password', $user->password))->toBeTrue();
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'Registration browser',
    ]);
});

it('returns 422 when registration data is invalid', function () {
    $response = $this->postJson('/api/register', []);

    $response
        ->assertUnprocessable()
        ->assertInvalid(['name', 'email', 'password']);
    $this->assertDatabaseCount('users', 0);
});

it('returns 429 when registration exceeds its rate limit', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/register', [])->assertUnprocessable();
    }

    $this->postJson('/api/register', [])->assertTooManyRequests();
});

it('logs in with valid credentials and returns a token', function () {
    $user = User::factory()->create([
        'email' => 'rafa@example.com',
        'password' => 'secure-password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'rafa@example.com',
        'password' => 'secure-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'rafa@example.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('returns 422 when credentials are incorrect', function () {
    User::factory()->create([
        'email' => 'rafa@example.com',
        'password' => 'secure-password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'rafa@example.com',
        'password' => 'incorrect-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertInvalid(['email' => 'The provided credentials are incorrect.']);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('returns 429 when login exceeds its rate limit', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', [
            'email' => 'rafa@example.com',
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/login', [
        'email' => 'rafa@example.com',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/user');

    $response
        ->assertOk()
        ->assertExactJson([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
});

it('returns 401 when no token is provided', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('revokes only the current token on logout', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('current-token');
    $otherToken = $user->createToken('other-token');

    $response = $this->withToken($currentToken->plainTextToken)->postJson('/api/logout');

    $response->assertNoContent();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
});
