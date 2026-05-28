<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Ginkelsoft Laravel Compliance Core - Configuration
 * -----------------------------------------------------------------------------
 *
 * This file holds the package-wide settings that are shared by every
 * GinkelSoft compliance package: the HMAC-like secret that signs every
 * audit-log hash chain, and the default placeholder values used by the
 * anonymize strategies.
 *
 * Individual family packages (retention, right-to-be-forgotten, subject
 * access, consent, breach registry) read these values through the
 * `LogSecret` and `PlaceholderConfig` helpers in this package, so that
 * a single `.env` entry signs every chain consistently.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Log Signing Key
    |--------------------------------------------------------------------------
    |
    | A secret mixed into every audit-log hash. Without it the chain is
    | still tamper-evident against external attackers without DB write
    | access, but anyone with write access could forge a consistent chain.
    |
    | Generate one with:  openssl rand -base64 32
    |
    | For backwards compatibility with the original monolithic
    | `ginkelsoft/laravel-data-retention` v1.x package, the LogSecret
    | helper also reads `data-retention.log_secret` when this key is
    | unset, so existing installations do not need to rename their
    | environment variable on upgrade.
    |
    */
    'log_secret' => env('COMPLIANCE_LOG_SECRET', env('DATA_RETENTION_LOG_SECRET', '')),

    /*
    |--------------------------------------------------------------------------
    | Anonymization Placeholders
    |--------------------------------------------------------------------------
    |
    | Default replacement values used by PlaceholderStrategy when no
    | explicit value is configured on a field.
    |
    */
    'placeholders' => [
        'string' => '[REDACTED]',
        'email' => 'redacted@example.invalid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Logging
    |--------------------------------------------------------------------------
    |
    | Emit additional logger() output across the compliance family.
    | Recommended off in production.
    |
    */
    'debug' => env('COMPLIANCE_DEBUG', env('DATA_RETENTION_DEBUG', false)),
];
