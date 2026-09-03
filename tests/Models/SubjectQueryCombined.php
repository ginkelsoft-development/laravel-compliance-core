<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Tests\Models;

use Ginkelsoft\ComplianceCore\Contracts\ResolvesSubjectColumn;
use Ginkelsoft\ComplianceCore\Tests\Concerns\FakeExportablePolicy;
use Ginkelsoft\ComplianceCore\Tests\Concerns\FakeForgettablePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Combined-policy fixture: stands in for a model that uses both
 * `Forgettable` and `Exportable` once those traits compose
 * `HasSubjectQuery` instead of each declaring their own
 * `forSubjectQuery`. No `insteadof` is declared here — and none is
 * needed, because both fake policy traits pull `forSubjectQuery` from
 * the exact same `HasSubjectQuery` source, so PHP does not see a
 * collision.
 */
class SubjectQueryCombined extends Model implements ResolvesSubjectColumn
{
    use FakeExportablePolicy, FakeForgettablePolicy;

    protected $table = 'subject_query_combineds';

    protected $fillable = ['subject', 'note'];

    public static function subjectColumn(): string
    {
        return 'subject';
    }
}
