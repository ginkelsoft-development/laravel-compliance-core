<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the shared compliance core.
 *
 * Only merges and publishes the shared config. Contains no commands,
 * migrations, or runtime services — those belong in the per-control
 * family packages that depend on this one.
 */
class ComplianceCoreServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings and configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/compliance.php',
            'compliance'
        );
    }

    /**
     * Bootstrap the package: publish the shared config file.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/compliance.php' => config_path('compliance.php'),
        ], 'compliance-config');
    }
}
