<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Concerns;

use Ginkelsoft\ComplianceCore\Contracts\ResolvesSubjectColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasSubjectQuery
 *
 * Shared foundation for every subject-driven trait in the compliance
 * family (right-to-be-forgotten's `Forgettable`, subject access's
 * `Exportable`, and any future one). Each of those traits used to carry
 * its own, byte-identical `forSubjectQuery` implementation
 * (`WHERE column = subject`) — fine on its own, but a model that combined
 * two of them had to pick one with an `insteadof`, even though both
 * implementations did exactly the same thing.
 *
 * `HasSubjectQuery` provides that one implementation. The host model must
 * satisfy {@see ResolvesSubjectColumn} — either directly, or via another
 * trait it uses — so this trait knows which column to filter on.
 *
 * Models whose subject mapping is not a simple `column = subject`
 * predicate (polymorphic, OR-across-two-columns, joined, ...) keep
 * overriding `forSubjectQuery` directly on the model, same as before —
 * a model method always wins over a trait method, so the override still
 * takes effect without any extra wiring.
 *
 * @mixin Model
 * @mixin ResolvesSubjectColumn
 */
trait HasSubjectQuery
{
    /**
     * Build the query that selects every record of this model belonging
     * to the given subject identifier.
     *
     * @return Builder<static>
     */
    public static function forSubjectQuery(string $subject): Builder
    {
        /** @var Builder<static> $query */
        $query = static::query();

        return $query->where(static::subjectColumn(), '=', $subject);
    }
}
