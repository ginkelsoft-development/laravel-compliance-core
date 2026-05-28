<?php

declare(strict_types=1);

namespace Ginkelsoft\ComplianceCore\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal model fixture used by the strategy tests. Has no schema,
 * no policies, no anonymize spec — strategies only need `$model::class`
 * for context, never the database.
 */
class BareModel extends Model
{
    protected $table = 'bare_models';
}
