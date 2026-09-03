<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Tests\Concerns;

use Ginkelsoft\ComplianceCore\Concerns\HasSubjectQuery;

/**
 * Stand-in for `Ginkelsoft\DataSubjectAccess\Concerns\Exportable` after
 * it drops its own `forSubjectQuery` in favour of {@see HasSubjectQuery}
 * (tracked as a follow-up in that repo — it lives in a separate package
 * and cannot be depended on from here).
 *
 * Composes `HasSubjectQuery` itself, the way the real trait will, so the
 * combined-trait test below proves the "no insteadof" claim against the
 * actual PHP trait resolution rules, not just against a hand-written
 * substitute.
 */
trait FakeExportablePolicy
{
    use HasSubjectQuery;

    public function exportablePolicyName(): string
    {
        return 'export';
    }
}
