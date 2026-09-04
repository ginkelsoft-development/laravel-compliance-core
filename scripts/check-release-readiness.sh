#!/usr/bin/env bash
#
# check-release-readiness.sh — controleert of een composer.json klaar is om
# naar Packagist gepubliceerd te worden, en FAALT (exit 1) als dat niet zo
# is. Dit is een controle, geen transformatie: het schrijft nooit iets weg.
# Voor de transformatie zelf, zie scripts/release-prep.sh.
#
# Context: de GinkelSoft-compliance-familiepakketten ontwikkelen tegen elkaar
# via een Composer path-repository + een "dev-development" branch-constraint
# op ginkelsoft/laravel-compliance-core. Packagist kent geen path-repositories
# en "dev-development" is geen getagde release. Dit script is de laatste
# controle vóór je tagt: het faalt zodra composer.json nog een "dev-*"
# dependency-constraint bevat, of nog een "path"-type repository heeft.
#
# Geen hardcoded paden of geheimen: het te controleren bestand komt als
# argument binnen (of is standaard ./composer.json in de huidige map).
#
# Gebruik:
#   scripts/check-release-readiness.sh [pad-naar-composer.json]
#
# Argumenten:
#   [pad-naar-composer.json]  Optioneel. Standaard: composer.json in de
#                             huidige werkmap (handig om als GitHub Action of
#                             pre-tag hook in elk familiepakket te draaien).
#
# Opties:
#   -h, --help  Toont deze hulptekst.
#
# Exit-codes:
#   0  composer.json is klaar voor publicatie (geen dev-* dependency, geen
#      path-repository).
#   1  Niet klaar: het bestand ontbreekt/is ongeldig JSON, óf er is minstens
#      één blokkerend probleem gevonden (details op stderr).
#
# Wat er misgaat als je dit overslaat:
#   - Je tagt en publiceert een versie waarvan composer.json nog naar een
#     lokaal pad of een ontwikkelbranch wijst. `composer install` faalt dan
#     bij iedere consument die het pakket van Packagist installeert, en dat
#     ontdek je pas na de publicatie in plaats van ervoor.
set -euo pipefail

PROGRAM_NAME="$(basename "${BASH_SOURCE[0]}")"

usage() {
    cat <<EOF
Gebruik:
  ${PROGRAM_NAME} [pad-naar-composer.json]

Zie de commentaarblok bovenin dit script voor de volledige uitleg.
EOF
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

TARGET="${1:-composer.json}"

if [ ! -f "${TARGET}" ]; then
    echo "Fout: bestand niet gevonden: ${TARGET}" >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "Fout: php is nodig om composer.json veilig te parsen (JSON)." >&2
    exit 1
fi

export CHECK_RELEASE_READINESS_TARGET="${TARGET}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
php "${SCRIPT_DIR}/check-release-readiness.php"
