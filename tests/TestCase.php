<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard against running the suite through a cached config.
     *
     * Running `php artisan config:cache` / `route:cache` bakes the local
     * `.env` (including the LIVE database name) into bootstrap/cache, and
     * that cached value takes precedence over the phpunit.xml test-DB override.
     * A `RefreshDatabase` migration then runs `migrate:fresh` against the LIVE
     * database and wipes every table. This check runs before any test setUp
     * (before any migrations fire) and aborts the whole suite with a clear
     * message so the developer clears the caches first.
     */
    private function guardAgainstCachedConfig(): void
    {
        $configCache = __DIR__.'/../bootstrap/cache/config.php';

        if (file_exists($configCache)) {
            throw new \RuntimeException(
                'Configuration is cached (bootstrap/cache/config.php exists). '.
                'Cached config reroutes RefreshDatabase to the LIVE database and '.
                'can wipe it. Run `php artisan config:clear` (and `route:clear`) '.
                'before running tests, then `php artisan view:cache` if you need it.'
            );
        }
    }

    /**
     * Backstop: never allow a test run to touch anything but a test database.
     */
    private function assertTestDatabase(): void
    {
        $connection = config('database.default');
        $name = config("database.connections.{$connection}.database");

        $looksLikeTest = $name === ':memory:'
            || preg_match('/_test(_|$)/', (string) $name) === 1;

        if (! $looksLikeTest) {
            throw new \RuntimeException(
                "Refusing to run tests against database '{$name}'. ".
                'The configured DB name must end in _test.'
            );
        }
    }

    protected function setUp(): void
    {
        $this->guardAgainstCachedConfig();

        parent::setUp();

        $this->assertTestDatabase();
    }
}
