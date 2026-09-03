<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Tests\Models;

use Ginkelsoft\ComplianceCore\Concerns\HasSubjectQuery;
use Ginkelsoft\ComplianceCore\Contracts\ResolvesSubjectColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Override fixture, mirroring `ForgetTicket` in
 * `laravel-data-right-to-be-forgotten`: the subject can appear in either
 * of two columns (an OR-across-two-columns mapping), so the model
 * overrides `forSubjectQuery` itself instead of relying on the
 * `HasSubjectQuery` default. Proves the model's own method still wins.
 */
class SubjectQueryOverride extends Model implements ResolvesSubjectColumn
{
    use HasSubjectQuery;

    protected $table = 'subject_query_overrides';

    protected $fillable = ['reporter_id', 'assignee_id'];

    public static function subjectColumn(): string
    {
        return 'reporter_id';
    }

    /**
     * @return Builder<static>
     */
    public static function forSubjectQuery(string $subject): Builder
    {
        /** @var Builder<static> $query */
        $query = static::query();

        return $query->where(function (Builder $q) use ($subject): void {
            $q->where('reporter_id', '=', $subject)
                ->orWhere('assignee_id', '=', $subject);
        });
    }
}
