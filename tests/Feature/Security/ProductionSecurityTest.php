<?php

use Illuminate\Support\Facades\DB;

function configureSecureProductionEnvironment(): void
{
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
}

it('fails the production preflight for unsafe configuration', function () {
    config([
        'app.debug' => true,
        'app.key' => '',
        'app.url' => 'http://momentum.example',
        'cors.allowed_origins' => ['*'],
        'sanctum.expiration' => null,
        'database.default' => 'mysql',
        'database.connections.mysql.username' => 'root',
        'database.connections.mysql.password' => '',
        'logging.default' => 'single',
        'logging.channels.single.level' => 'debug',
    ]);

    $this->artisan('security:check-production')->assertFailed();
});

it('passes the production preflight for hardened configuration', function () {
    configureSecureProductionEnvironment();

    $this->artisan('security:check-production')->assertSuccessful();
});

it('rejects administrative database grants', function () {
    configureSecureProductionEnvironment();
    DB::shouldReceive('select')
        ->once()
        ->with('SHOW GRANTS FOR CURRENT_USER')
        ->andReturn([(object) ['grant' => 'GRANT ALL PRIVILEGES ON *.* WITH GRANT OPTION']]);

    $this->artisan('security:check-production --database')->assertFailed();
});

it('accepts least privilege runtime database grants', function () {
    configureSecureProductionEnvironment();
    DB::shouldReceive('select')
        ->once()
        ->with('SHOW GRANTS FOR CURRENT_USER')
        ->andReturn([(object) [
            'grant' => 'GRANT SELECT, INSERT, UPDATE, DELETE ON momentum.* TO momentum_runtime',
        ]]);

    $this->artisan('security:check-production --database')->assertSuccessful();
});
