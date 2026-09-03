#!/usr/bin/env bash
#
# release-prep.sh — zet dev-development composer-dependencies om naar een
# getagde versie, ter voorbereiding van een Packagist-publicatie.
#
# Context: de GinkelSoft-compliance-familiepakketten (laravel-data-retention,
# laravel-data-right-to-be-forgotten, laravel-data-subject-access,
# laravel-data-consent, laravel-data-breach-registry, laravel-compliance-hub)
# ontwikkelen tegen elkaar via een Composer path-repository + de
# "dev-development" branch-constraint op ginkelsoft/laravel-compliance-core.
# Packagist kent geen path-repositories en "dev-development" verwijst naar
# een branch, geen release — dat moet vóór publicatie omgezet worden naar
# een getagde semver-constraint (bv. "^1.2") en de path-repository moet
# tijdelijk verwijderd worden, anders faalt `composer validate` /
# `composer install` bij consumenten die het pakket van Packagist trekken.
#
# Werkt alleen op composer.json — er is geen enkel hardcoded pad of geheim
# in dit script: het doelbestand en de versie worden als argument meegegeven.
#
# Gebruik:
#   scripts/release-prep.sh <pad-naar-composer.json> <versie> [package]
#   scripts/release-prep.sh --restore <pad-naar-composer.json>
#   scripts/release-prep.sh --dry-run <pad-naar-composer.json> <versie> [package]
#
# Argumenten:
#   <pad-naar-composer.json>  Pad naar het composer.json van het te
#                             releasen familiepakket (bv.
#                             ../laravel-data-retention/composer.json).
#   <versie>                  Getagde versie zonder "dev-" prefix, bv. 1.4.0
#                             of v1.4.0. Wordt geschreven als "^1.4" tenzij
#                             je zelf al een constraint-operator meegeeft
#                             (^, ~, >=, etc.) — dan wordt die letterlijk
#                             overgenomen.
#   [package]                 Optioneel, composer-pakketnaam waarvan de
#                             dev-development constraint vervangen wordt.
#                             Standaard: ginkelsoft/laravel-compliance-core.
#
# Opties:
#   --restore   Zet <pad-naar-composer.json> terug naar de staat van vóór
#               de laatste run van dit script (uit het .bak-bestand ernaast).
#   --dry-run   Toont wat er zou veranderen, schrijft niets weg.
#   -h, --help  Toont deze hulptekst.
#
# Wat dit script doet:
#   1. Maakt een back-up: <composer.json>.release-prep.bak (alleen als er
#      nog geen back-up van een eerdere, niet-herstelde run bestaat).
#   2. Vervangt in "require" en "require-dev" de constraint van [package]
#      van "dev-development" (of elke andere "dev-*" branch-constraint)
#      naar de opgegeven getagde versie.
#   3. Verwijdert uit "repositories" elke entry met "type": "path" waarvan
#      de "url" naar het [package]-repo lijkt te verwijzen (bevat de laatste
#      padcomponent van de package-naam, bv. "laravel-compliance-core").
#   4. Laat verder niets aan het bestand veranderen (sleutelvolgorde en
#      overige inhoud blijven intact) en valideert het resultaat met
#      `composer validate --no-check-publish` als composer beschikbaar is.
#
# Wat er misgaat als een stap ontbreekt:
#   - Geen back-up (stap 1) -> --restore kan niet herstellen en een
#     mislukte release-run laat het werkbestand in een tussenstaat.
#   - Geen dependency-swap (stap 2) -> `composer install` bij de consument
#     faalt: Packagist heeft geen branch "dev-development".
#   - Geen path-repository-verwijdering (stap 3) -> composer.json verwijst
#     naar een lokaal pad dat op de machine van de consument niet bestaat;
#     `composer validate` faalt of composer probeert een niet-bestaand pad
#     te lezen.
#   - Geen validatie (stap 4) -> een kapot composer.json wordt pas
#     opgemerkt nadat de tag al gepubliceerd is.
set -euo pipefail

PROGRAM_NAME="$(basename "${BASH_SOURCE[0]}")"

usage() {
    cat <<EOF
Gebruik:
  ${PROGRAM_NAME} <pad-naar-composer.json> <versie> [package]
  ${PROGRAM_NAME} --restore <pad-naar-composer.json>
  ${PROGRAM_NAME} --dry-run <pad-naar-composer.json> <versie> [package]

Zie de commentaarblok bovenin dit script voor de volledige uitleg.
EOF
}

DEFAULT_PACKAGE="ginkelsoft/laravel-compliance-core"
DRY_RUN=0
RESTORE=0

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

if [ "${1:-}" = "--restore" ]; then
    RESTORE=1
    shift
elif [ "${1:-}" = "--dry-run" ]; then
    DRY_RUN=1
    shift
fi

TARGET="${1:-}"
if [ -z "${TARGET}" ]; then
    echo "Fout: geen pad naar composer.json opgegeven." >&2
    usage >&2
    exit 1
fi
if [ ! -f "${TARGET}" ]; then
    echo "Fout: bestand niet gevonden: ${TARGET}" >&2
    exit 1
fi

BACKUP="${TARGET}.release-prep.bak"

if [ "${RESTORE}" -eq 1 ]; then
    if [ ! -f "${BACKUP}" ]; then
        echo "Fout: geen back-up gevonden (${BACKUP})." >&2
        echo "Er is niets om te herstellen — is release-prep.sh hier al gedraaid?" >&2
        exit 1
    fi
    cp "${BACKUP}" "${TARGET}"
    rm -f "${BACKUP}"
    echo "Hersteld: ${TARGET} (uit ${BACKUP})."
    exit 0
fi

VERSION="${2:-}"
if [ -z "${VERSION}" ]; then
    echo "Fout: geen versie opgegeven." >&2
    usage >&2
    exit 1
fi
PACKAGE="${3:-${DEFAULT_PACKAGE}}"

if ! command -v php >/dev/null 2>&1; then
    echo "Fout: php is nodig om composer.json veilig te bewerken (JSON-parsing)." >&2
    exit 1
fi

if [ "${DRY_RUN}" -eq 0 ] && [ -f "${BACKUP}" ]; then
    echo "Fout: er bestaat al een back-up (${BACKUP})." >&2
    echo "Draai eerst '${PROGRAM_NAME} --restore ${TARGET}' of verwijder de back-up handmatig" \
         "als je zeker weet dat je een nieuwe swap wilt starten." >&2
    exit 1
fi

if [ "${DRY_RUN}" -eq 0 ]; then
    cp "${TARGET}" "${BACKUP}"
fi

export RELEASE_PREP_TARGET="${TARGET}"
export RELEASE_PREP_VERSION="${VERSION}"
export RELEASE_PREP_PACKAGE="${PACKAGE}"
export RELEASE_PREP_DRY_RUN="${DRY_RUN}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
php "${SCRIPT_DIR}/release-prep.php"

if [ "${DRY_RUN}" -eq 1 ]; then
    exit 0
fi

echo "Geschreven: ${TARGET}"
echo "Back-up:    ${BACKUP} (herstel met: ${PROGRAM_NAME} --restore ${TARGET})"

if command -v composer >/dev/null 2>&1; then
    echo "-- composer validate --no-check-publish"
    composer validate --no-check-publish --working-dir="$(dirname "${TARGET}")" || {
        echo "Waarschuwing: composer validate faalde. Controleer ${TARGET} handmatig," \
             "of herstel met: ${PROGRAM_NAME} --restore ${TARGET}" >&2
    }
else
    echo "Let op: composer niet gevonden, validatie overgeslagen." >&2
fi
