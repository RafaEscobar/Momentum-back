<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

class RedactSensitiveData
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
        'db_password',
    ];

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: $this->redactString($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        ));
    }

    /** @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            $values[$key] = match (true) {
                is_array($value) => $this->redactArray($value),
                is_string($value) => $this->redactString($value),
                default => $value,
            };
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(str_replace(['-', ' '], '_', $key));

        return in_array($normalizedKey, self::SENSITIVE_KEYS, true)
            || str_ends_with($normalizedKey, '_token')
            || str_ends_with($normalizedKey, '_secret')
            || str_ends_with($normalizedKey, '_password');
    }

    private function redactString(string $value): string
    {
        return preg_replace(
            [
                '/\bBearer\s+[^\s,;]+/i',
                '/\b\d+\|[A-Za-z0-9]{20,}\b/',
                '/\b(password|password_confirmation|token|access_token|refresh_token|api_key|secret|cookie)\s*[:=]\s*[^\s,;]+/i',
            ],
            [
                'Bearer '.self::REDACTED,
                self::REDACTED,
                '$1='.self::REDACTED,
            ],
            $value,
        ) ?? $value;
    }
}
