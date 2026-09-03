# Ginkelsoft Laravel Compliance Core

[![Tests](https://github.com/ginkelsoft-development/laravel-compliance-core/actions/workflows/tests.yml/badge.svg?branch=development)](https://github.com/ginkelsoft-development/laravel-compliance-core/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)
[![Laravel](https://img.shields.io/badge/Laravel-10--13-brightgreen?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%20--%208.5-blue?style=flat-square&logo=php)](https://php.net)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen?style=flat-square)](phpstan.neon.dist)

## Overview

Shared foundation for the GinkelSoft GDPR / AVG compliance family. This package
contains zero AVG controls of its own — it only provides the primitives that
every member of the family relies on:

- A deterministic, tamper-evident SHA-256 **hash chain** (`HashChain`).
- A one-way **subject hash** so audit logs can prove "this subject" without
  storing the identifier (`SubjectHash`).
- The **anonymize strategies** (`null`, `hash`, `placeholder`) and their
  resolver, shared across every package that anonymizes (`AnonymizeStrategy`,
  `StrategyResolver`, `NullStrategy`, `HashStrategy`, `PlaceholderStrategy`).
- A single **shared config** with one `log_secret` that signs every chain in
  the family (`config/compliance.php`).
- `HasSubjectQuery` — a base trait that gives every subject-driven trait in
  the family (right-to-be-forgotten's `Forgettable`, subject access's
  `Exportable`) one shared `forSubjectQuery` implementation, resolved via
  the `ResolvesSubjectColumn` contract, so a model combining several of
  them no longer needs an `insteadof`.
- BC fallbacks (`LogSecret`, `PlaceholderConfig`) so installations upgrading
  from the monolithic v1.x `ginkelsoft/laravel-data-retention` package keep
  verifying their existing chains without renaming env vars.

## The family

| Package | GDPR Article(s) | Role |
| ------- | --------------- | ---- |
| [`laravel-compliance-core`](https://github.com/ginkelsoft-development/laravel-compliance-core) | art. 5(2) (accountability) | Shared primitives — this package |
| [`laravel-data-retention`](https://github.com/ginkelsoft-development/laravel-data-retention) | art. 5(1)(e) | Storage limitation / time-based sweeps |
| [`laravel-data-right-to-be-forgotten`](https://github.com/ginkelsoft-development/laravel-data-right-to-be-forgotten) | art. 17 | Subject-driven erasure |
| [`laravel-data-subject-access`](https://github.com/ginkelsoft-development/laravel-data-subject-access) | art. 15 + 20 | Read-only subject export (inzageverzoek) |
| [`laravel-data-consent`](https://github.com/ginkelsoft-development/laravel-data-consent) | art. 6(1)(a) + 7 | Consent registry |
| [`laravel-data-breach-registry`](https://github.com/ginkelsoft-development/laravel-data-breach-registry) | art. 33 + 34 | Personal-data breach register, 72-hour deadlines |
| [`laravel-compliance-hub`](https://github.com/ginkelsoft-development/laravel-compliance-hub) | art. 5(2) | Umbrella: installs the whole family, verifies every chain |

Install just the hub to get everything.

## Installation

You do not normally install this package directly — install one of the family
members, or the hub, and Composer pulls it in.

For development / direct use:

```bash
composer require ginkelsoft/laravel-compliance-core
php artisan vendor:publish --tag=compliance-config
```

Then add a secret to `.env`:

```env
COMPLIANCE_LOG_SECRET="$(openssl rand -base64 32)"
```

Existing installations of `ginkelsoft/laravel-data-retention` v1.x can keep
their `DATA_RETENTION_LOG_SECRET` — the `LogSecret` helper reads it as a
fallback so existing hash chains keep verifying after the upgrade.

## Hash algorithm contract

The hash algorithms in this package are **frozen**. Changing them would break
every already-written audit log on every production database that uses the
family. They are version-locked, not implementation details.

**Chained log entry hash:**

```
normalized = ksort + ATOM-format any DateTimeInterface values
serialized = json_encode(normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
hash       = sha256(previousHash + '|' + serialized + '|' + secret)
```

**Subject hash:**

```
sha256('subject|' + subjectId + '|' + secret)
```

**Hash-strategy output (per field, anonymize-time):**

```
sha256(model::class + '|' + field + '|' + stringValue + '|' + secret)
```

A regression test (`tests/Unit/HashChainRegressionTest.php`) pins three
end-to-end fixture hashes plus two subject hashes from the v1.x monolithic
`laravel-data-retention` package. If any of those fail, an upgrade would break
existing production chains and the change must be treated as a major version
bump with a documented chain rotation procedure.

## Security model

| Threat | Mitigation |
| ------ | ---------- |
| Retroactive edit of a log row | Every row's hash depends on the previous row's hash + the shared secret. Editing a row invalidates every later row, in every chain. |
| Forged row inserted by an attacker without `log_secret` | The forged row cannot produce a hash that chains with both its neighbors. |
| Leaking subject identifier from a log | `SubjectHash` is one-way and secret-mixed; the original identifier never appears in the forget / access logs (consent_log is the documented exception). |
| Secret stored next to the data it protects | `log_secret` lives in `.env`, not the database — read access to the DB does not let the attacker forge a chain. |

## Compliance notes

* **GDPR art. 5(2)** — Accountability. The hash chain is the evidence; this
  package is what produces it.

This package is **not legal advice**. Retention periods, consent texts,
severity assessments and DPIA judgements are the responsibility of your DPO.

## Testing

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

## See also

- [`UPGRADE.md`](UPGRADE.md) — migrating from `laravel-data-retention` v1.x to the family.
- [`CHANGELOG.md`](CHANGELOG.md) — notable changes per release.
- The family packages listed at the top of this README.

## Reporting bugs

Found a bug or unexpected behaviour? We want to hear about it.

**Preferred — open a GitHub issue:**
[https://github.com/ginkelsoft-development/laravel-compliance-core/issues/new](https://github.com/ginkelsoft-development/laravel-compliance-core/issues/new)

When opening an issue, please include:

1. **Versions** — PHP, Laravel, and the package version
   (`composer show ginkelsoft/laravel-compliance-core`).
2. **What you did** — the artisan command, code snippet, or steps that
   triggered the bug.
3. **What you expected** vs **what actually happened** — include full
   error output or a stack trace if there is one.
4. **A minimal reproduction** if you can — a failing test or a small
   code sample beats a long description.

**Security-sensitive findings** (anything that could expose personal
data, break a hash-chain, or bypass an audit log) — please **do not**
open a public issue. E-mail **info@ginkelsoft.com** directly with
"SECURITY" in the subject line and we will respond privately.

**Not on GitHub?** You can also e-mail **info@ginkelsoft.com** with the
same information.

## Contact

For commercial support, integration questions, or anything that doesn't
fit a GitHub issue: **info@ginkelsoft.com** —
[https://ginkelsoft.com](https://ginkelsoft.com).

## License

MIT License — see [LICENSE](LICENSE).
(c) 2026 Ginkelsoft
