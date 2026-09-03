# Changelog

All notable changes to `ginkelsoft/laravel-compliance-core` are documented
in this file. This project follows [Semantic Versioning](https://semver.org/).

## [Unreleased] — targeting 1.1.0 (minor)

### Added

- `Ginkelsoft\ComplianceCore\Concerns\HasSubjectQuery` — a shared base
  trait that provides one default `forSubjectQuery` implementation
  (`WHERE column = subject`) for every subject-driven control in the
  family.
- `Ginkelsoft\ComplianceCore\Contracts\ResolvesSubjectColumn` — the
  `subjectColumn(): string` contract `HasSubjectQuery` relies on to know
  which column to filter on.

This is purely additive (no existing public API changed), which is why
it ships as a minor release.

It is the foundation for a follow-up in each child repo: once
`laravel-data-right-to-be-forgotten`'s `Forgettable` and
`laravel-data-subject-access`'s `Exportable` are updated to compose
`HasSubjectQuery` instead of each declaring their own `forSubjectQuery`,
a model combining both no longer needs an `insteadof` to resolve the
conflict. Those two updates (plus the accompanying README notes) are
tracked as separate PRs in the child repos and are **not** part of this
release.

## [1.0.0] — 2026-05-28

Initial extraction of the shared compliance primitives (hash chain,
subject hash, anonymize strategies, shared config, BC fallbacks) from
the monolithic `ginkelsoft/laravel-data-retention` v1.x package.
