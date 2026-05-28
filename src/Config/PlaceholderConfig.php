<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Config;

/**
 * Resolver for the default anonymize placeholders.
 *
 * Reads `compliance.placeholders.{key}` first, then falls back to the
 * legacy `data-retention.placeholders.{key}` for backwards
 * compatibility with installations upgrading from the monolithic
 * v1.x `ginkelsoft/laravel-data-retention` package.
 */
final class PlaceholderConfig
{
    /**
     * Return the configured placeholder for a given key.
     *
     * @param  string  $key  The placeholder key ('string', 'email', ...).
     * @param  string  $default  Fallback used when neither config source is set.
     */
    public static function value(string $key, string $default): string
    {
        $primary = config("compliance.placeholders.{$key}");

        if (is_string($primary) && $primary !== '') {
            return $primary;
        }

        $legacy = config("data-retention.placeholders.{$key}");

        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }

        return $default;
    }
}
