<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Config;

/**
 * Single point of truth for the audit-log signing secret.
 *
 * Reads `compliance.log_secret` first, then falls back to
 * `data-retention.log_secret` so that installations upgrading from the
 * monolithic v1.x `ginkelsoft/laravel-data-retention` package keep
 * verifying their existing hash chains without renaming their env var.
 *
 * Every package in the compliance family that signs or verifies a hash
 * chain MUST go through this helper so that the secret is read in
 * exactly one way across all chains.
 */
final class LogSecret
{
    /**
     * Return the configured log secret as a string.
     *
     * Always returns a string (possibly empty) so callers can pass the
     * result straight into HashChain::compute / verify without further
     * type coercion.
     */
    public static function value(): string
    {
        $primary = config('compliance.log_secret');

        if (is_string($primary) && $primary !== '') {
            return $primary;
        }

        $legacy = config('data-retention.log_secret');

        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }

        return '';
    }
}
