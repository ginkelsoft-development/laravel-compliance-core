<?php

declare(strict_types=1);

use Ginkelsoft\ComplianceCore\Support\HashChain;
use Ginkelsoft\ComplianceCore\Support\SubjectHash;

/**
 * Regression test that pins the byte-exact hash output produced by
 * `ginkelsoft/laravel-data-retention` v1.x BEFORE the split into the
 * compliance family.
 *
 * If any of these hashes change, a production database that already
 * contains hash-chained log rows will fail `HashChain::verify()` after
 * upgrading. That is a breaking change that requires a chain rotation
 * with a documented procedure — never an unannounced patch release.
 *
 * Do NOT update these expected values. If a test in this file fails,
 * stop and treat it as evidence that the hash algorithm has shifted.
 */
it('produces byte-identical hashes to v1.x retention HashChain', function (): void {
    $secret = 'fixture-secret-do-not-change';

    $payload1 = [
        'model_type' => 'App\\Models\\Client',
        'model_id' => '01HXYZFIXTURE001',
        'action' => 'deleted',
        'retention_period' => '5 years',
        'retention_field' => 'ended_at',
        'expired_at' => '2026-01-01T00:00:00+00:00',
        'performed_at' => '2026-05-28T02:00:00+00:00',
    ];

    $hash1 = HashChain::compute($payload1, '', $secret);
    expect($hash1)->toBe('31b033b2bfe8a2f1e00622a80ab3dc0b6b031642e4171758f84e029b3fad3180');

    $payload2 = [
        'model_type' => 'App\\Models\\Client',
        'model_id' => '01HXYZFIXTURE002',
        'action' => 'anonymized',
        'retention_period' => '5 years',
        'retention_field' => 'ended_at',
        'expired_at' => '2026-01-15T00:00:00+00:00',
        'performed_at' => '2026-05-28T02:00:01+00:00',
    ];

    $hash2 = HashChain::compute($payload2, $hash1, $secret);
    expect($hash2)->toBe('fa33aead3d5633732f8dde7057e9b3a6a8c694a1d098bc871670dea5b39d3560');

    $payload3 = [
        'model_type' => 'App\\Models\\Profile',
        'model_id' => 'subject_access',
        'action' => 'subject_access_exported',
        'retention_period' => '3 records',
        'retention_field' => 'subject_access',
        'expired_at' => null,
        'performed_at' => '2026-05-28T03:00:00+00:00',
    ];

    $hash3 = HashChain::compute($payload3, $hash2, $secret);
    expect($hash3)->toBe('1a6caee392cbaf78e9ef4c535f4df3d13be244a9d5a58edcec4c278a024144f0');
});

it('produces byte-identical SubjectHash to v1.x retention SubjectHash', function (): void {
    $secret = 'fixture-secret-do-not-change';

    expect(SubjectHash::compute('alice-01', $secret))
        ->toBe('8c717687416d0dfaff98a6a81101ee89656f5d84283ad2a6563ab47cc9126222');

    expect(SubjectHash::compute('user@example.com', $secret))
        ->toBe('09ac084ba83acdc1bbc5438a2278da89114e7c7b08a94b72248d57f1ab83d557');
});

it('verifies a v1.x-produced chain end-to-end', function (): void {
    $secret = 'fixture-secret-do-not-change';

    $entries = [
        [
            'model_type' => 'App\\Models\\Client',
            'model_id' => '01HXYZFIXTURE001',
            'action' => 'deleted',
            'retention_period' => '5 years',
            'retention_field' => 'ended_at',
            'expired_at' => '2026-01-01T00:00:00+00:00',
            'performed_at' => '2026-05-28T02:00:00+00:00',
            'previous_hash' => '',
            'hash' => '31b033b2bfe8a2f1e00622a80ab3dc0b6b031642e4171758f84e029b3fad3180',
        ],
        [
            'model_type' => 'App\\Models\\Client',
            'model_id' => '01HXYZFIXTURE002',
            'action' => 'anonymized',
            'retention_period' => '5 years',
            'retention_field' => 'ended_at',
            'expired_at' => '2026-01-15T00:00:00+00:00',
            'performed_at' => '2026-05-28T02:00:01+00:00',
            'previous_hash' => '31b033b2bfe8a2f1e00622a80ab3dc0b6b031642e4171758f84e029b3fad3180',
            'hash' => 'fa33aead3d5633732f8dde7057e9b3a6a8c694a1d098bc871670dea5b39d3560',
        ],
    ];

    expect(HashChain::verify($entries, $secret))->toBeTrue();
});
