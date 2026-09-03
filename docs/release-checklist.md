# Release-checklist: laravel-compliance-core en de familiepakketten

Deze checklist is voor iedereen die voor het eerst een release van
`ginkelsoft/laravel-compliance-core` of een van de familiepakketten
(`laravel-data-retention`, `laravel-data-right-to-be-forgotten`,
`laravel-data-subject-access`, `laravel-data-consent`,
`laravel-data-breach-registry`, `laravel-compliance-hub`) naar Packagist
publiceert. Volg de stappen in volgorde — sla er geen over.

## Waarom dit nodig is

Tijdens ontwikkeling wijzen de familiepakketten met een Composer
**path-repository** naar een lokale checkout van `laravel-compliance-core`,
en staat de dependency op `"dev-development"` (de branch, geen release).
Packagist kent geen lokale paden en `dev-development` is geen getagde
versie — als je zo publiceert, faalt `composer install` bij iedere
consument die het pakket van Packagist installeert. Voor publicatie moet
dat omgezet worden naar een getagde semver-constraint, zonder
path-repository. Na de release zet je het lokaal weer terug naar
`dev-development`, zodat je verder kunt ontwikkelen tegen de main-branch.

## Vóór publicatie

- [ ] **1. Alles staat op de `development`-branch en is gemerged.**
      Geen openstaande PR's die nog in de release moeten.
- [ ] **2. Tests, statische analyse en codestijl zijn groen.**
  ```bash
  composer install
  vendor/bin/pest
  vendor/bin/phpstan analyse
  vendor/bin/pint --test
  ```
  Als één van deze faalt: **stop, fix eerst, ga niet verder.**
- [ ] **3. Versiebump bepalen.**
      Volg semver: patch (bugfix, geen API-wijziging), minor (nieuwe,
      backwards-compatible functionaliteit), major (breaking change — zie
      ook de hash-algoritme-waarschuwing in de README: wijzigingen aan de
      hash-chain-berekening zijn altijd major, met een gedocumenteerde
      migratie/rotatieprocedure).
- [ ] **4. Changelog / release-notes bijwerken.**
      Voeg een sectie toe met de nieuwe versie, datum, en een korte
      opsomming van wijzigingen (Added/Changed/Fixed/Deprecated/Removed).
      Als dit repo (nog) geen `CHANGELOG.md` heeft: gebruik de
      GitHub-release-notes bij het aanmaken van de tag (stap 8) en noem dat
      hier expliciet in de PR-beschrijving.
- [ ] **5. Dependency-swap: dev-development → getagde versie.**
      Alleen relevant voor familiepakketten die
      `ginkelsoft/laravel-compliance-core` als dependency hebben (niet voor
      compliance-core zelf, dat heeft geen path-repository naar zichzelf).
      Draai vanuit `laravel-compliance-core`:
  ```bash
  # eerst droog proberen — schrijft niets weg
  scripts/release-prep.sh --dry-run ../laravel-data-retention/composer.json 1.4.0

  # daadwerkelijk toepassen (maakt automatisch een .bak-bestand)
  scripts/release-prep.sh ../laravel-data-retention/composer.json 1.4.0
  ```
      Herhaal dit voor elk familiepakket dat je meereleaset. Controleer
      daarna handmatig de diff van elk gewijzigd `composer.json`:
      - de `dev-development`-constraint moet vervangen zijn door iets als
        `^1.4`;
      - de `"repositories"`-entry van het type `path` naar
        `laravel-compliance-core` moet weg zijn (of het hele
        `"repositories"`-blok, als dat de enige entry was).
- [ ] **6. Dependencies opnieuw installeren en tests draaien tegen de
      getagde versie (niet tegen de lokale checkout).**
      Dit is de enige manier om te bewijzen dat de getagde versie op
      Packagist ook echt werkt voor consumenten — met de path-repository
      erin heb je dat nooit getest.
  ```bash
  rm -f composer.lock
  composer install --no-interaction
  vendor/bin/pest
  ```
      Faalt dit? Herstel eerst met `scripts/release-prep.sh --restore
      composer.json`, fix het probleem, en begin opnieuw bij stap 5.
      **Publiceer nooit met een falende testrun.**
- [ ] **7. Versienummer vastleggen waar dat hoort** (bv. in een
      `CHANGELOG.md`-kop of, als het project dat gebruikt, een
      `VERSION`-bestand). `composer.json` zelf bevat geen versieveld voor
      libraries — de git-tag ís de versie.
- [ ] **8. Commit en tag.**
  ```bash
  git add -A
  git commit -m "chore(release): v1.4.0"
  git tag -a v1.4.0 -m "v1.4.0"
  git push origin development --tags
  ```
- [ ] **9. Publiceren.**
      Als het pakket al op Packagist staat met een webhook: de tag-push in
      stap 8 triggert de update automatisch. Controleer op
      https://packagist.org/packages/ginkelsoft/<pakket> dat de nieuwe
      versie verschijnt. Staat er geen webhook: log in op Packagist en
      klik "Update" op de pakketpagina.
- [ ] **10. Lokale ontwikkelstand herstellen.**
      Zet elk gewijzigd `composer.json` terug naar `dev-development` +
      path-repository, zodat lokaal verder ontwikkelen tegen de
      main-branch blijft werken:
  ```bash
  scripts/release-prep.sh --restore ../laravel-data-retention/composer.json
  ```
      Controleer met `git diff` dat het bestand weer exact de
      ontwikkelstand heeft, en commit dat niet per ongeluk mee met een
      volgende, ongerelateerde wijziging.
- [ ] **11. Afhankelijke pakketten nabellen.**
      Als `laravel-compliance-core` zelf gereleased is: elk familiepakket
      dat een lossere constraint heeft (bv. `^1.0`) pikt de nieuwe versie
      vanzelf op bij de volgende `composer update` bij de consument. Een
      pakket met een strakke bovengrens moet zelf ook een release krijgen
      om de nieuwe core-versie te kunnen gebruiken.

## Wat er misgaat als je een stap overslaat

| Overgeslagen stap | Gevolg |
| --- | --- |
| 2 (tests/analyse/stijl) | Een kapotte of niet-conforme release komt op Packagist terecht; consumenten ontdekken het pas na installatie. |
| 4 (changelog) | Niemand — inclusief toekomstige jij — weet meer wat er in een versie zit; upgrade-beslissingen worden giswerk. |
| 5 (dependency-swap) | `composer install` faalt bij elke consument: Packagist heeft geen branch `dev-development` en geen lokaal pad `../laravel-compliance-core`. |
| 6 (opnieuw testen na de swap) | Je hebt nooit getest tegen wat je daadwerkelijk publiceert; de path-repository verborg mogelijk een echte breuk. |
| 8 (tag) | Packagist heeft niets om op te pikken — er verschijnt geen nieuwe versie, ondanks een gepubliceerde commit. |
| 10 (herstellen naar dev-development) | De volgende ontwikkelaar (of jijzelf) werkt per ongeluk tegen een bevroren getagde versie in plaats van de development-branch, en wijzigingen aan compliance-core worden niet meer opgepikt totdat iemand het toevallig opmerkt. |

## Script: `scripts/release-prep.sh`

Zie `scripts/release-prep.sh --help` voor de volledige uitleg. Kort:

```bash
# droog proberen (wijzigt niets)
scripts/release-prep.sh --dry-run <pad-naar-composer.json> <versie>

# echt toepassen (maakt automatisch composer.json.release-prep.bak)
scripts/release-prep.sh <pad-naar-composer.json> <versie>

# terugzetten naar de staat vóór de laatste run
scripts/release-prep.sh --restore <pad-naar-composer.json>
```

Het script bevat geen hardcoded paden of geheimen: het doelbestand, de
versie en (optioneel) de packagenaam komen als argument binnen. Het werkt
op willekeurige composer.json-bestanden, dus ook op die van andere
familiepakketten, mits je het pad ernaartoe meegeeft.
