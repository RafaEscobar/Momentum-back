<?php

use App\Logging\RedactSensitiveData;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

it('redacts sensitive values from log messages and nested context', function () {
    $handler = new TestHandler;
    $monolog = new Logger('security', [$handler]);
    (new RedactSensitiveData)(new IlluminateLogger($monolog));
    $plainToken = '12|'.str_repeat('a', 40);

    $monolog->warning(
        "Authorization: Bearer {$plainToken} password=super-secret",
        [
            'authorization' => "Bearer {$plainToken}",
            'request' => [
                'password' => 'super-secret',
                'password_confirmation' => 'super-secret',
                'profile' => [
                    'api_token' => $plainToken,
                    'name' => 'Personal name',
                    'email' => 'private@example.test',
                    'content' => 'Private note body',
                ],
            ],
            'safe' => 'Visible value',
        ],
    );

    $record = $handler->getRecords()[0];

    expect($record->message)
        ->not->toContain($plainToken)
        ->not->toContain('super-secret')
        ->and($record->context['authorization'])->toBe('[REDACTED]')
        ->and($record->context['request']['password'])->toBe('[REDACTED]')
        ->and($record->context['request']['password_confirmation'])->toBe('[REDACTED]')
        ->and($record->context['request']['profile']['api_token'])->toBe('[REDACTED]')
        ->and($record->context['request']['profile']['name'])->toBe('[REDACTED]')
        ->and($record->context['request']['profile']['email'])->toBe('[REDACTED]')
        ->and($record->context['request']['profile']['content'])->toBe('[REDACTED]')
        ->and($record->context['safe'])->toBe('Visible value');
});
