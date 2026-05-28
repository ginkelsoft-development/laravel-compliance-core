<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Strategies;

use Ginkelsoft\ComplianceCore\Config\LogSecret;
use Ginkelsoft\ComplianceCore\Contracts\AnonymizeStrategy;
use Illuminate\Database\Eloquent\Model;

/**
 * Replaces the field's value with a one-way SHA-256 hash.
 *
 * Useful for identifiers that you still want to be able to dedupe
 * or join on but which must no longer reveal the original (BSN, IBAN,
 * email when used as a foreign key). The hash incorporates the
 * configured `compliance.log_secret` so the same plaintext does
 * not produce the same hash across unrelated installations.
 *
 * The output is fixed-length (64 hex chars) regardless of input,
 * so the column must accept at least 64 characters.
 *
 * Hash algorithm contract (frozen — do NOT change without a major
 * version bump):
 *
 *   sha256(model::class + '|' + field + '|' + stringValue + '|' + secret)
 */
final class HashStrategy implements AnonymizeStrategy
{
    public function apply(mixed $value, string $field, Model $model): mixed
    {
        if ($value === null) {
            return null;
        }

        $secret = LogSecret::value();

        $context = $model::class.'|'.$field;

        $stringValue = match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => serialize($value),
        };

        return hash('sha256', $context.'|'.$stringValue.'|'.$secret);
    }
}
