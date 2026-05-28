<?php

declare(strict_types=1);

use Ginkelsoft\ComplianceCore\Strategies\HashStrategy;
use Ginkelsoft\ComplianceCore\Strategies\NullStrategy;
use Ginkelsoft\ComplianceCore\Strategies\PlaceholderStrategy;
use Ginkelsoft\ComplianceCore\Strategies\StrategyResolver;
use Ginkelsoft\ComplianceCore\Tests\Models\BareModel;

it('NullStrategy always returns null', function (): void {
    $strategy = new NullStrategy;
    $model = new BareModel;

    expect($strategy->apply('anything', 'first_name', $model))->toBeNull()
        ->and($strategy->apply(null, 'first_name', $model))->toBeNull()
        ->and($strategy->apply(42, 'first_name', $model))->toBeNull();
});

it('HashStrategy returns a SHA-256 hex digest', function (): void {
    config()->set('compliance.log_secret', 'unit-secret');

    $strategy = new HashStrategy;
    $model = new BareModel;

    $hash = $strategy->apply('123456789', 'bsn', $model);

    expect($hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('HashStrategy returns null for a null input', function (): void {
    $strategy = new HashStrategy;
    $model = new BareModel;

    expect($strategy->apply(null, 'bsn', $model))->toBeNull();
});

it('HashStrategy is contextual: same value across fields hashes differently', function (): void {
    config()->set('compliance.log_secret', 'unit-secret');

    $strategy = new HashStrategy;
    $model = new BareModel;

    $a = $strategy->apply('shared-value', 'bsn', $model);
    $b = $strategy->apply('shared-value', 'phone', $model);

    expect($a)->not->toBe($b);
});

it('HashStrategy is deterministic for the same input', function (): void {
    config()->set('compliance.log_secret', 'unit-secret');

    $strategy = new HashStrategy;
    $model = new BareModel;

    expect($strategy->apply('abc', 'bsn', $model))
        ->toBe($strategy->apply('abc', 'bsn', $model));
});

it('HashStrategy falls back to the legacy data-retention config key', function (): void {
    config()->set('compliance.log_secret', '');
    config()->set('data-retention.log_secret', 'legacy-secret');

    $strategy = new HashStrategy;
    $model = new BareModel;

    $hash = $strategy->apply('abc', 'bsn', $model);

    expect($hash)->toMatch('/^[a-f0-9]{64}$/');

    config()->set('data-retention.log_secret', null);
});

it('PlaceholderStrategy uses the configured default', function (): void {
    config()->set('compliance.placeholders.string', '[GONE]');

    $strategy = new PlaceholderStrategy;
    $model = new BareModel;

    expect($strategy->apply('Wietse', 'first_name', $model))->toBe('[GONE]');
});

it('PlaceholderStrategy uses an injected value when provided', function (): void {
    $strategy = new PlaceholderStrategy('custom-value');
    $model = new BareModel;

    expect($strategy->apply('Wietse', 'first_name', $model))->toBe('custom-value');
});

it('PlaceholderStrategy falls back to the legacy data-retention config key', function (): void {
    config()->set('compliance.placeholders.string', null);
    config()->set('data-retention.placeholders.string', '[LEGACY]');

    $strategy = new PlaceholderStrategy;
    $model = new BareModel;

    expect($strategy->apply('Wietse', 'first_name', $model))->toBe('[LEGACY]');

    config()->set('data-retention.placeholders.string', null);
});

it('StrategyResolver returns the right built-in strategy by id', function (): void {
    expect(StrategyResolver::resolve('null'))->toBeInstanceOf(NullStrategy::class)
        ->and(StrategyResolver::resolve('hash'))->toBeInstanceOf(HashStrategy::class)
        ->and(StrategyResolver::resolve('placeholder'))->toBeInstanceOf(PlaceholderStrategy::class);
});

it('StrategyResolver rejects unknown strategy ids', function (): void {
    StrategyResolver::resolve('mystery');
})->throws(InvalidArgumentException::class);

it('StrategyResolver wraps a Closure into a strategy', function (): void {
    $strategy = StrategyResolver::resolve(fn ($value, $field, $model) => 'replaced-'.$field);
    $model = new BareModel;

    expect($strategy->apply('original', 'email', $model))->toBe('replaced-email');
});
