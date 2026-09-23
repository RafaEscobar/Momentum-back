<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

it('rejects absent empty malformed and altered bearer tokens', function (string $token) {
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertUnauthorized();
})->with([
    'absent' => '',
    'malformed' => 'not-a-sanctum-token',
    'altered hash' => '1|'.str_repeat('a', 40),
    'unexpected scheme value' => 'null',
]);

it('rejects a revoked token immediately while another token remains valid', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('Current browser');
    $otherToken = $user->createToken('Other browser');

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/logout')
        ->assertNoContent();

    Auth::forgetGuards();
    $this->withToken($currentToken->plainTextToken)
        ->getJson('/api/user')
        ->assertUnauthorized();

    Auth::forgetGuards();
    $this->withToken($otherToken->plainTextToken)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

it('rejects tokens whose user was deleted', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Deleted account')->plainTextToken;

    $user->delete();

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects expired tokens according to the configured lifetime', function () {
    config(['sanctum.expiration' => 1]);
    $user = User::factory()->create();
    $token = $user->createToken('Expiring browser')->plainTextToken;

    $this->withToken($token)->getJson('/api/user')->assertOk();

    $this->travel(2)->minutes();

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
});

it('stores only a token hash and never returns the token after issuance', function () {
    Log::spy();
    $user = User::factory()->create();
    $newToken = $user->createToken('Secure browser');
    [$tokenId, $secret] = explode('|', $newToken->plainTextToken, 2);

    expect($newToken->accessToken->token)->not->toBe($secret)
        ->and($newToken->accessToken->token)->toBe(hash('sha256', $secret));

    $this->withToken($newToken->plainTextToken)
        ->getJson('/api/user')
        ->assertOk()
        ->assertDontSee($newToken->plainTextToken, false)
        ->assertDontSee($secret, false)
        ->assertJsonMissingPath('token');

    $this->withToken($newToken->plainTextToken)
        ->getJson('/api/testing/missing-route')
        ->assertNotFound()
        ->assertDontSee($newToken->plainTextToken, false)
        ->assertDontSee($secret, false);

    expect((int) $tokenId)->toBe($newToken->accessToken->id);
    Log::shouldNotHaveReceived('emergency');
    Log::shouldNotHaveReceived('alert');
    Log::shouldNotHaveReceived('critical');
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('notice');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
});

it('stores a validated device name when issuing a token', function () {
    $user = User::factory()->create([
        'email' => 'security@example.com',
        'password' => 'secure-password',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secure-password',
        'device_name' => 'Firefox on workstation',
    ])->assertOk();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'Firefox on workstation',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secure-password',
        'device_name' => str_repeat('a', 101),
    ])->assertUnprocessable()->assertInvalid(['device_name']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secure-password',
        'device_name' => "Browser\nInjected log line",
    ])->assertUnprocessable()->assertInvalid(['device_name']);
});

it('requires the current password before revoking every token', function () {
    $user = User::factory()->create(['password' => 'secure-password']);
    $currentToken = $user->createToken('Current browser');
    $otherToken = $user->createToken('Other browser');

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/logout-all', ['password' => 'incorrect-password'])
        ->assertUnprocessable()
        ->assertInvalid(['password']);
    $this->assertDatabaseCount('personal_access_tokens', 2);

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/logout-all', ['password' => 'secure-password'])
        ->assertNoContent();
    $this->assertDatabaseCount('personal_access_tokens', 0);

    Auth::forgetGuards();
    $this->withToken($currentToken->plainTextToken)->getJson('/api/user')->assertUnauthorized();
    Auth::forgetGuards();
    $this->withToken($otherToken->plainTextToken)->getJson('/api/user')->assertUnauthorized();
});

it('configures a finite default token lifetime and daily pruning', function () {
    expect(config('sanctum.expiration'))->toBeInt()->toBeGreaterThan(0)
        ->and(collect(Schedule::events())->contains(
            fn ($event): bool => str_contains($event->command ?? '', 'sanctum:prune-expired --hours=24')
        ))->toBeTrue();
});
