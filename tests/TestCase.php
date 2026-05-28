<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Tests;

use Ginkelsoft\ComplianceCore\ComplianceCoreServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base TestCase for compliance-core: registers the core service
 * provider, points the database at in-memory SQLite, and seeds the
 * shared log secret.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ComplianceCoreServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('compliance.log_secret', 'test-log-secret');
    }
}
