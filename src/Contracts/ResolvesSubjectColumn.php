<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Contracts;

use Ginkelsoft\ComplianceCore\Concerns\HasSubjectQuery;

/**
 * Implemented by every subject-driven policy in the compliance family —
 * right-to-be-forgotten's `Forgettable` and subject access's `Exportable`
 * today, any future subject-driven control tomorrow.
 *
 * A model (or the trait it uses) that can answer "which column holds the
 * subject identifier for this row?" satisfies this contract, which is all
 * {@see HasSubjectQuery} needs to build its default
 * `WHERE column = subject` query. Two traits that both resolve to the
 * same column no longer need an `insteadof` to pick one `forSubjectQuery`
 * implementation — they can share the one from `HasSubjectQuery`.
 */
interface ResolvesSubjectColumn
{
    /**
     * The column that holds the subject identifier for this model.
     */
    public static function subjectColumn(): string;
}
