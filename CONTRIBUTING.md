# Contributing

## CI / testmatrix

De PHP/Laravel-testmatrix en de CI-jobs (`test`, `static-analysis`,
`code-style`) worden onderhouden in
[`.github/workflows/family-tests.yml`](.github/workflows/family-tests.yml).
Dit is een herbruikbare workflow (`on: workflow_call`).

`.github/workflows/tests.yml` in dit pakket is een dunne wrapper die
`family-tests.yml` aanroept via `uses:`. Wijzig de matrix (nieuwe PHP- of
Laravel-versie, andere jobs) alleen in `family-tests.yml`, niet in `tests.yml`.

De overige familiepakketten (`laravel-data-retention`,
`laravel-data-right-to-be-forgotten`, `laravel-data-subject-access`,
`laravel-data-consent`, `laravel-data-breach-registry`,
`laravel-compliance-hub`) roepen dezelfde `family-tests.yml` aan vanuit
`laravel-compliance-core`, zodat de matrix op één plek staat voor de hele
familie:

```yaml
jobs:
  tests:
    uses: ginkelsoft-development/laravel-compliance-core/.github/workflows/family-tests.yml@development
```

## Lokaal testen

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

Of gebruik `.zyra/proef.sh` om de package via Testbench te proefdraaien.
