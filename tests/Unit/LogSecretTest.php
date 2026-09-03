<?php

declare(strict_types=1);

use Ginkelsoft\ComplianceCore\Config\LogSecret;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    // Reset de statische "al gewaarschuwd"-guard tussen tests, anders is
    // het resultaat afhankelijk van de volgorde waarin tests draaien.
    $property = new ReflectionProperty(LogSecret::class, 'warned');
    $property->setAccessible(true);
    $property->setValue(null, false);
});

it('returns the configured secret without warning', function (): void {
    Log::spy();

    config()->set('compliance.log_secret', 'test-log-secret');

    expect(LogSecret::value())->toBe('test-log-secret');

    Log::shouldNotHaveReceived('warning');
});

it('logs a one-time warning when no secret is configured', function (): void {
    Log::spy();

    config()->set('compliance.log_secret', null);
    config()->set('data-retention.log_secret', null);

    expect(LogSecret::value())->toBe('');
    expect(LogSecret::value())->toBe('');

    Log::shouldHaveReceived('warning')
        ->with('compliance.log_secret is empty — audit-log hash chains are not tamper-evident against attackers with DB write access. Set COMPLIANCE_LOG_SECRET in .env.')
        ->once();
});
