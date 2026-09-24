<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SecurityProductionCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:check-production
                            {--database : Inspect the current database account grants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fail when production security configuration is incomplete';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [
            'APP_ENV is production' => app()->isProduction(),
            'APP_DEBUG is disabled' => config('app.debug') === false,
            'APP_KEY is a valid encryption key' => $this->hasValidApplicationKey(),
            'APP_URL uses HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'CORS has explicit HTTPS origins' => $this->hasSafeCorsOrigins(),
            'CORS credentials are disabled for Bearer authentication' => config('cors.supports_credentials') === false,
            'CORS methods and headers do not contain wildcards' => ! in_array('*', config('cors.allowed_methods', []), true)
                && ! in_array('*', config('cors.allowed_headers', []), true),
            'Trusted proxies do not use a wildcard' => ! in_array('*', config('trustedproxy.proxies', []), true),
            'Sanctum tokens have a finite lifetime' => is_int(config('sanctum.expiration'))
                && config('sanctum.expiration') > 0,
            'Application request body limit is enabled' => is_int(config('security.max_request_body_kb'))
                && config('security.max_request_body_kb') > 0,
            'Pagination has a finite upper bound' => is_int(config('security.max_page'))
                && config('security.max_page') > 0,
            'Activity retention is finite' => is_int(config('security.activity_retention_days'))
                && config('security.activity_retention_days') > 0,
            'Database user is not a default administrator' => ! in_array(
                strtolower((string) config('database.connections.'.config('database.default').'.username')),
                ['', 'root', 'admin', 'administrator'],
                true,
            ),
            'Database password is configured' => filled(
                config('database.connections.'.config('database.default').'.password')
            ),
            'Production log level excludes debug messages' => $this->hasSafeLogLevel(),
            'Public directory contains no sensitive artifacts' => $this->publicDirectoryIsSafe(),
        ];

        if ($this->option('database')) {
            $checks['Database account has no administrative grants'] = $this->hasSafeDatabaseGrants();
        }

        foreach ($checks as $description => $passed) {
            $passed ? $this->components->info($description) : $this->components->error($description);
        }

        if (in_array(false, $checks, true)) {
            $this->newLine();
            $this->error('Production security preflight failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Production security preflight passed.');

        return self::SUCCESS;
    }

    private function hasValidApplicationKey(): bool
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }

        return in_array(strlen($key), [16, 32], true);
    }

    private function hasSafeCorsOrigins(): bool
    {
        $origins = config('cors.allowed_origins', []);

        return is_array($origins)
            && $origins !== []
            && collect($origins)->every(fn (mixed $origin): bool => is_string($origin)
                && str_starts_with($origin, 'https://')
                && ! str_contains($origin, '*'));
    }

    private function hasSafeLogLevel(): bool
    {
        $defaultChannel = (string) config('logging.default');
        $channel = config("logging.channels.{$defaultChannel}", []);
        $levels = [];

        if (($channel['driver'] ?? null) === 'stack') {
            foreach ($channel['channels'] ?? [] as $stackedChannel) {
                $levels[] = config("logging.channels.{$stackedChannel}.level");
            }
        } else {
            $levels[] = $channel['level'] ?? null;
        }

        return collect($levels)
            ->filter()
            ->every(fn (mixed $level): bool => strtolower((string) $level) !== 'debug');
    }

    private function hasSafeDatabaseGrants(): bool
    {
        try {
            $grants = collect(DB::select('SHOW GRANTS FOR CURRENT_USER'))
                ->flatMap(fn (object $row): array => array_values((array) $row))
                ->implode(' ');
        } catch (Throwable) {
            return false;
        }

        return ! preg_match(
            '/\b(GRANT OPTION|SUPER|FILE|SHUTDOWN|CREATE USER|SYSTEM_USER|ROLE_ADMIN)\b|\bON \*\.\*/i',
            $grants,
        );
    }

    private function publicDirectoryIsSafe(): bool
    {
        $forbiddenNames = ['.env', '.git', 'auth.json', 'composer.json', 'composer.lock'];
        $forbiddenExtensions = ['bak', 'backup', 'dump', 'key', 'pem', 'p12', 'pfx', 'sql'];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $name = strtolower($file->getFilename());
            $extension = strtolower($file->getExtension());

            if (in_array($name, $forbiddenNames, true)
                || in_array($extension, $forbiddenExtensions, true)
                || str_ends_with($name, '.sql.gz')) {
                return false;
            }
        }

        return true;
    }
}
