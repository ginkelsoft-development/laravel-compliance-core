# Upgrade Guide

This document covers upgrading from the **monolithic v1.x
`ginkelsoft/laravel-data-retention`** package — which bundled five GDPR
controls in one — to the **v2.x compliance family**, where every control
lives in its own package and `ginkelsoft/laravel-compliance-core` holds
the shared primitives.

## The short version

1. `composer require ginkelsoft/laravel-compliance-hub` — this installs
   the entire family in one shot.
2. Run the new migrations: `php artisan migrate`. The only new table is
   `subject_access_log`; the four existing tables (`retention_log`,
   `forget_log`, `consent_log`, `breach_register` + `breach_event_log`)
   are unchanged and already exist in your database.
3. Move config keys from `data-retention.php` into the per-control config
   files (see below). The legacy `DATA_RETENTION_LOG_SECRET` env var
   keeps working — `Ginkelsoft\ComplianceCore\Config\LogSecret` reads it
   as a fallback so existing hash chains keep verifying.
4. Update imports in any application code that referenced
   `Ginkelsoft\DataRetention\Support\HashChain`,
   `Ginkelsoft\DataRetention\Support\SubjectHash`, the anonymize
   strategies, or the `AnonymizeStrategy` contract — they all moved to
   `Ginkelsoft\ComplianceCore\…`.
5. Run `php artisan compliance:verify`. Every existing chain must still
   verify; if one does not, **stop the upgrade and investigate** before
   you deploy.

## Hash chains stay byte-identical

This is the load-bearing guarantee of the split. The v1.x
`HashChain::compute` and `SubjectHash::compute` algorithms have moved
unchanged to `laravel-compliance-core`. A pinned regression test
(`tests/Unit/HashChainRegressionTest.php`) in core encodes the
byte-exact output of v1.x and fails CI if any future change drifts.

Concretely:

- The pre-upgrade hash of any row in `retention_log`, `forget_log`,
  `consent_log` or `breach_event_log` is identical to what
  `Ginkelsoft\ComplianceCore\Support\HashChain::compute()` produces
  post-upgrade. `HashChain::verify()` will continue to return `true` on
  every untampered chain.
- The `log_secret` value is also unchanged. Whether you read it from
  `compliance.log_secret` (new key) or `data-retention.log_secret`
  (legacy key, still supported via `LogSecret`), the helper returns the
  same string and the hashes match.

## Subject access — `retention_log` rows stay where they are

In v1.x, subject access (GDPR art. 15) logged its activity into
`retention_log` with `action = 'subject_access_exported'` and
`retention_field = 'subject_access'`. The schema was shared and the
chain was shared.

In v2.x, subject access has its own table `subject_access_log` with a
schema that better fits its semantics (`subject_hash`, `model_type`,
`record_count`, `format`, `performed_at`). The package writes to that
new table going forward.

**You do not need to migrate the old subject_access rows out of
`retention_log`.** They stay where they are, and the existing
`retention_log` chain (which may now contain a mix of
"deleted/anonymized" rows and the old "subject_access_exported" rows)
remains byte-identically verifiable in place — the chain logic only
cares about whether the hash of each row links to the previous one,
not what semantic the rows have.

The `subject_access_log` chain in v2.x simply starts from scratch with
its first new export.

## Config: split data-retention.php into per-control files

| v1.x `config/data-retention.php` | v2.x location |
| --- | --- |
| `models` (retention list) | `config/data-retention.php` — `models` (unchanged) |
| `include_soft_deleted` | `config/data-retention.php` — `include_soft_deleted` (unchanged) |
| `chunk_size` | `config/data-retention.php` — `chunk_size` (unchanged) |
| `forgettable.models` | `config/forget.php` — `models` |
| `exportable.models` | `config/subject-access.php` — `models` |
| `log_secret` | `config/compliance.php` — `log_secret` (legacy key still read as fallback) |
| `placeholders.string` / `placeholders.email` | `config/compliance.php` — `placeholders.*` (legacy keys still read as fallback) |
| `debug` | `config/compliance.php` — `debug` (legacy key still read as fallback) |

The legacy keys keep being read for backwards compatibility, so you can
do this gradually: install the hub, ship a release, then move config
entries package-by-package on a later sprint.

If you upgraded via the hub (`composer require
ginkelsoft/laravel-compliance-hub`), publish the new config files with:

```bash
php artisan vendor:publish --tag=compliance-config
php artisan vendor:publish --tag=forget-config
php artisan vendor:publish --tag=subject-access-config
php artisan vendor:publish --tag=consent-config
php artisan vendor:publish --tag=breach-config
```

## Env vars

The new canonical env var is `COMPLIANCE_LOG_SECRET`. The legacy
`DATA_RETENTION_LOG_SECRET` keeps working — `LogSecret::value()` reads
the new key first and falls back to the legacy key. **No action required
at upgrade time.** Switch the env var name on a separate, later commit if
you want a clean `.env`.

## Imports to update

Search-and-replace in your application code:

| v1.x | v2.x |
| --- | --- |
| `Ginkelsoft\DataRetention\Support\HashChain` | `Ginkelsoft\ComplianceCore\Support\HashChain` |
| `Ginkelsoft\DataRetention\Support\SubjectHash` | `Ginkelsoft\ComplianceCore\Support\SubjectHash` |
| `Ginkelsoft\DataRetention\Strategies\NullStrategy` | `Ginkelsoft\ComplianceCore\Strategies\NullStrategy` |
| `Ginkelsoft\DataRetention\Strategies\HashStrategy` | `Ginkelsoft\ComplianceCore\Strategies\HashStrategy` |
| `Ginkelsoft\DataRetention\Strategies\PlaceholderStrategy` | `Ginkelsoft\ComplianceCore\Strategies\PlaceholderStrategy` |
| `Ginkelsoft\DataRetention\Strategies\StrategyResolver` | `Ginkelsoft\ComplianceCore\Strategies\StrategyResolver` |
| `Ginkelsoft\DataRetention\Contracts\AnonymizeStrategy` | `Ginkelsoft\ComplianceCore\Contracts\AnonymizeStrategy` |
| `Ginkelsoft\DataRetention\Attributes\Forgettable` | `Ginkelsoft\DataRightToBeForgotten\Attributes\Forgettable` |
| `Ginkelsoft\DataRetention\Concerns\Forgettable` | `Ginkelsoft\DataRightToBeForgotten\Concerns\Forgettable` |
| `Ginkelsoft\DataRetention\Contracts\Forgettable` | `Ginkelsoft\DataRightToBeForgotten\Contracts\Forgettable` |
| `Ginkelsoft\DataRetention\Attributes\Exportable` | `Ginkelsoft\DataSubjectAccess\Attributes\Exportable` |
| `Ginkelsoft\DataRetention\Concerns\Exportable` | `Ginkelsoft\DataSubjectAccess\Concerns\Exportable` |
| `Ginkelsoft\DataRetention\Contracts\Exportable` | `Ginkelsoft\DataSubjectAccess\Contracts\Exportable` |
| `Ginkelsoft\DataRetention\Contracts\Exporter` | `Ginkelsoft\DataSubjectAccess\Contracts\Exporter` |
| `Ginkelsoft\DataRetention\Exporters\JsonExporter` | `Ginkelsoft\DataSubjectAccess\Exporters\JsonExporter` |
| `Ginkelsoft\DataRetention\Exporters\MarkdownExporter` | `Ginkelsoft\DataSubjectAccess\Exporters\MarkdownExporter` |
| `Ginkelsoft\DataRetention\Actions\RecordConsent` | `Ginkelsoft\DataConsent\Actions\RecordConsent` |
| `Ginkelsoft\DataRetention\Support\ConsentStatus` | `Ginkelsoft\DataConsent\Support\ConsentStatus` |
| `Ginkelsoft\DataRetention\Models\ConsentEntry` | `Ginkelsoft\DataConsent\Models\ConsentEntry` |
| `Ginkelsoft\DataRetention\Actions\BreachRegistry` | `Ginkelsoft\DataBreachRegistry\Actions\BreachRegistry` |
| `Ginkelsoft\DataRetention\Support\BreachDeadlines` | `Ginkelsoft\DataBreachRegistry\Support\BreachDeadlines` |
| `Ginkelsoft\DataRetention\Models\BreachRegisterEntry` | `Ginkelsoft\DataBreachRegistry\Models\BreachRegisterEntry` |
| `Ginkelsoft\DataRetention\Models\BreachEventLogEntry` | `Ginkelsoft\DataBreachRegistry\Models\BreachEventLogEntry` |

`Ginkelsoft\DataRetention\Models\RetentionLogEntry`, the
`#[Retention]` attribute, the `HasRetention` trait, the `ApplyRetention`
action, and the `retention:run` command stay on
`Ginkelsoft\DataRetention\…` — that package is now the storage-limitation
specialist.

## Commands

Every command keeps its v1.x name for BC. No script that scheduled
`retention:run`, `retention:forget`, `retention:export`,
`retention:consent:*`, or `retention:breach:*` needs to change.

Two **new** commands are added by the hub:

- `compliance:verify` — verifies every audit-log chain across the family
  in one shot. Exits non-zero on any tampered chain.
- `compliance:report` — bundles row counts + verify status into a single
  Markdown or JSON report. No PII.

Schedule `compliance:verify` daily and have it page someone on failure.

## Post-upgrade verification checklist

Run through these once, after the deploy and before signing off:

- [ ] `composer show ginkelsoft/laravel-compliance-core` resolves cleanly.
- [ ] `php artisan migrate` reports either no work to do, or only the
      new `create_subject_access_log_table` migration as pending.
- [ ] `php artisan compliance:verify` exits 0 and reports every chain
      that was used in v1.x as `OK` (row counts non-zero where they
      should be).
- [ ] A spot-check: re-run an old subject-access export and confirm the
      resulting rows land in `subject_access_log` (not `retention_log`).
- [ ] Application tests pass without any reference to the old
      `Ginkelsoft\DataRetention\Support\HashChain` namespace.

If any check fails, hold the deploy — the upgrade is a refactor, not a
behavior change. Everything that worked in v1.x must continue to work.

## When you absolutely must not upgrade

There is one situation where this upgrade is risky: if you have
**direct database writes** to any of the audit-log tables that bypass
the package models (raw INSERTs from a different system, for example),
and those writes recompute the hash via a vendored copy of v1.x's
`HashChain::compute()`. In that case:

1. Move that copy to read from
   `Ginkelsoft\ComplianceCore\Support\HashChain` instead.
2. Confirm the byte-identical guarantee still holds for your writes by
   running the pinned regression test in core
   (`vendor/bin/pest --filter=HashChainRegressionTest` in the core
   package).
3. Only then upgrade in production.

For every other installation — that only writes to the audit logs
through the package models — the upgrade is mechanical and safe.
