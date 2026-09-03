<?php

declare(strict_types=1);

use Ginkelsoft\ComplianceCore\Tests\Models\SubjectQueryCombined;
use Ginkelsoft\ComplianceCore\Tests\Models\SubjectQueryOverride;
use Ginkelsoft\ComplianceCore\Tests\Models\SubjectQuerySingle;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('subject_query_singles', function ($table): void {
        $table->id();
        $table->string('subject', 64)->index();
        $table->string('note')->nullable();
        $table->timestamps();
    });

    Schema::create('subject_query_combineds', function ($table): void {
        $table->id();
        $table->string('subject', 64)->index();
        $table->string('note')->nullable();
        $table->timestamps();
    });

    Schema::create('subject_query_overrides', function ($table): void {
        $table->id();
        $table->string('reporter_id', 64)->index();
        $table->string('assignee_id', 64)->nullable()->index();
        $table->timestamps();
    });
});

it('builds a WHERE column = subject query for a single-policy model', function (): void {
    SubjectQuerySingle::create(['subject' => 'user-1', 'note' => 'mine']);
    SubjectQuerySingle::create(['subject' => 'user-2', 'note' => 'not mine']);

    $rows = SubjectQuerySingle::forSubjectQuery('user-1')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->note)->toBe('mine');
});

it('lets a model combine two subject-driven traits without an insteadof', function (): void {
    SubjectQueryCombined::create(['subject' => 'user-1', 'note' => 'mine']);
    SubjectQueryCombined::create(['subject' => 'user-2', 'note' => 'not mine']);

    $model = new SubjectQueryCombined;

    // Both fake policy traits are usable side by side — no collision.
    expect($model->forgettablePolicyName())->toBe('forget')
        ->and($model->exportablePolicyName())->toBe('export');

    $rows = SubjectQueryCombined::forSubjectQuery('user-1')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->note)->toBe('mine');
});

it('keeps a model-owned forSubjectQuery override instead of the default', function (): void {
    SubjectQueryOverride::create(['reporter_id' => 'user-1', 'assignee_id' => 'user-2']);
    SubjectQueryOverride::create(['reporter_id' => 'user-3', 'assignee_id' => 'user-4']);

    $asReporter = SubjectQueryOverride::forSubjectQuery('user-1')->get();
    $asAssignee = SubjectQueryOverride::forSubjectQuery('user-2')->get();
    $none = SubjectQueryOverride::forSubjectQuery('user-3-and-4-not-1-or-2')->get();

    expect($asReporter)->toHaveCount(1)
        ->and($asAssignee)->toHaveCount(1)
        ->and($asReporter->first()->is($asAssignee->first()))->toBeTrue()
        ->and($none)->toHaveCount(0);
});
