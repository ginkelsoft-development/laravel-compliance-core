<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Support;

/**
 * One-way hash of a subject identifier for use in audit logs.
 *
 * The same subject identifier always hashes to the same value,
 * allowing the auditor to confirm that "this subject was forgotten"
 * (or "this subject's data was exported") while keeping the
 * identifier itself out of the audit-log table.
 *
 * The hash incorporates the shared `compliance.log_secret`, so the
 * mapping from identifier to hash cannot be reproduced by an attacker
 * who has read access to the audit table but not to the secret.
 *
 * Hash algorithm contract (frozen — do NOT change without a major
 * version bump):
 *
 *   sha256('subject|' + subjectId + '|' + secret)
 */
final class SubjectHash
{
    /**
     * Compute the subject hash.
     *
     * @param  string  $subjectId  Caller-provided identifier (string).
     *                             Caller decides what an identifier is:
     *                             primary key, UUID, ULID, email, etc.
     * @param  string  $secret  The compliance log secret.
     * @return string 64-character hex SHA-256 digest.
     */
    public static function compute(string $subjectId, string $secret): string
    {
        return hash('sha256', 'subject|'.$subjectId.'|'.$secret);
    }
}
