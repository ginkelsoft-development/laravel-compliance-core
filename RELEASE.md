# Release-checklist

Deze checklist voorkomt dat `ginkelsoft/laravel-compliance-core` (of een
van de andere familiepakketten) wordt getagd terwijl de `composer.json`
nog naar een lokale `dev-development`-branch of een `path`-repository
wijst. Dat zou installaties via Packagist breken: een consument die
`composer require ginkelsoft/laravel-compliance-core:^1.1` draait, kan
`dev-development` of een lokaal pad niet oplossen.

De familie bestaat uit `laravel-compliance-core`, de vijf functionele
pakketten (`laravel-data-retention`, `laravel-data-right-to-be-forgotten`,
`laravel-data-subject-access`, `laravel-data-consent`,
`laravel-data-breach-registry`) en `laravel-compliance-hub`, die op zijn
beurt van alle vijf afhangt. Tijdens development wijzen deze onderling
naar elkaar via `dev-development` + een lokale `path`-repository, zodat
je met wijzigingen in meerdere pakketten tegelijk kunt werken zonder
telkens te taggen. Vlak vóór een release moet dat tijdelijk teruggedraaid
worden naar getagde versies.

## Stappen

1. **Kies het volgende semver-tag per pakket.**
   Bepaal per pakket dat wijzigt of het een patch, minor of major is
   (zie `CHANGELOG.md` → sectie `[Unreleased]`) en spreek de nieuwe
   versienummers af vóór je begint, zodat de constraints in stap 2
   overal consistent zijn.

2. **Werk elke `dev-development`-vereiste bij naar de bijbehorende
   getagde constraint.**
   Doorzoek `composer.json` van elk pakket dat je gaat releasen op
   `dev-development` in `require` (of `require-dev`) en vervang dit door
   de versie uit stap 1, bijvoorbeeld:
   ```diff
   - "ginkelsoft/laravel-compliance-core": "dev-development",
   + "ginkelsoft/laravel-compliance-core": "^1.1",
   ```

3. **Verwijder of comment de lokale `path`-repositories-blokken.**
   Elk pakket dat via `path` naar een sibling-pakket verwijst
   (`"repositories": [{"type": "path", "url": "../laravel-..."}]`) moet
   dit blok kwijt vóór het taggen, anders resolvt Composer bij een
   consument niet naar Packagist.

4. **Draai tests + phpstan + pint opnieuw voor alle pakketten.**
   ```bash
   composer install --no-interaction
   vendor/bin/pest
   vendor/bin/phpstan analyse --memory-limit=1G
   vendor/bin/pint --test
   ```
   Doe dit per pakket, ná stap 2 en 3 — de dependency-resolutie
   verandert (van lokaal pad naar Packagist), dus een eerdere groene
   testrun garandeert niets meer.

5. **Tag de releasecommits in afhankelijkheidsvolgorde.**
   Eerst `laravel-compliance-core`, dan de vijf functionele pakketten
   (elk hangt af van `laravel-compliance-core`), pas daarna
   `laravel-compliance-hub` (hangt af van alle vijf). Zo bestaat elke
   dependency al als getagde versie op het moment dat een pakket dat
   erop leunt getagd wordt.

6. **Push de tags en laat Packagist-webhooks ze oppikken.**
   ```bash
   git push origin <tag>
   ```
   Controleer op packagist.org dat de nieuwe versie per pakket
   verschijnt. Dit is het enige moment waarop een release publiek wordt;
   git tag/push blijft altijd mensenwerk, geen script doet dit
   automatisch.

7. **Herstel de `dev-development`-vereisten en `path`-repositories op de
   `development`-branch.**
   Draai stap 2 en 3 terug (bijvoorbeeld met `git revert` van de
   release-commit, of door de wijziging opnieuw handmatig aan te
   brengen) zodat `development` weer met lokale paden werkt voor de
   volgende ontwikkelcyclus.

## Lokaal verifiëren vóór het taggen

`scripts/check-release-readiness.sh` controleert de `composer.json` van
dít pakket en faalt (non-zero exit) zodra er nog een
`dev-development`-vereiste in `require`/`require-dev` staat, of een
`path`-type entry in `repositories`. Draai dit na stap 2 en 3, vóór
stap 4:

```bash
./scripts/check-release-readiness.sh
```

Dezelfde check draait automatisch in CI bij elke tag-push (zie
`.github/workflows/release-check.yml`) en blokkeert de workflow als er
nog dev-development-vereisten of path-repositories in `composer.json`
staan.

**Let op:** dit script controleert alleen de `composer.json` van de
repo waarin het draait, niet die van de andere familiepakketten. Draai
het dus per pakket dat je release.
