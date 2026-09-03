<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Tests\Models;

use Ginkelsoft\ComplianceCore\Concerns\HasSubjectQuery;
use Ginkelsoft\ComplianceCore\Contracts\ResolvesSubjectColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-policy fixture: only `HasSubjectQuery`, subject column resolved
 * directly on the model. Proves the default `WHERE column = subject`
 * query for the simplest case.
 */
class SubjectQuerySingle extends Model implements ResolvesSubjectColumn
{
    use HasSubjectQuery;

    protected $table = 'subject_query_singles';

    protected $fillable = ['subject', 'note'];

    public static function subjectColumn(): string
    {
        return 'subject';
    }
}
