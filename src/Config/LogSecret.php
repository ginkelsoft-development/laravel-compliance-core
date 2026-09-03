<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Config;

use Illuminate\Support\Facades\Log;

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
     * Whether the "no secret configured" warning has already been
     * logged during this request/process. Kept as a static so the
     * warning fires at most once, no matter how many chains call
     * ::value() in a single run.
     */
    private static bool $warned = false;

    /**
     * Return the configured log secret as a string.
     *
     * Always returns a string (possibly empty) so callers can pass the
     * result straight into HashChain::compute / verify without further
     * type coercion. Logs a one-time warning when no secret is
     * configured, since an empty secret means hash chains are not
     * tamper-evident against attackers with DB write access.
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

        self::warnOnce();

        return '';
    }

    /**
     * Log the missing-secret warning at most once per request/process.
     */
    private static function warnOnce(): void
    {
        if (self::$warned) {
            return;
        }

        self::$warned = true;

        Log::warning('compliance.log_secret is empty — audit-log hash chains are not tamper-evident against attackers with DB write access. Set COMPLIANCE_LOG_SECRET in .env.');
    }
}
