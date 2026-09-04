#!/usr/bin/env bash
#
# run-check-release-readiness-tests.sh — geautomatiseerde tests voor
# scripts/check-release-readiness.sh.
#
# Roept het script aan tegen een reeks fixture-composer.json-bestanden
# (scripts/tests/fixtures/*.composer.json) en controleert dat de exit-code
# klopt: 1 (faalt) voor elke niet-conforme variant, 0 (slaagt) voor een
# schone composer.json. Geen bats of andere externe testrunner nodig — dit
# is een klein, afhankelijkheidsvrij bash-scriptje dat overal draait waar
# check-release-readiness.sh zelf ook draait (bash + php).
#
# Gebruik:
#   scripts/tests/run-check-release-readiness-tests.sh
#
# Exit-codes:
#   0  alle scenario's gedragen zich zoals verwacht.
#   1  minstens één scenario week af van de verwachting (details op stdout).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
TARGET_SCRIPT="${REPO_ROOT}/scripts/check-release-readiness.sh"
FIXTURES_DIR="${SCRIPT_DIR}/fixtures"

PASS_COUNT=0
FAIL_COUNT=0

# assert_exit_code <omschrijving> <verwachte-exit-code> <commando...>
assert_exit_code() {
    local description="$1"
    local expected="$2"
    shift 2

    local output
    local actual
    set +e
    output="$("$@" 2>&1)"
    actual=$?
    set -e

    if [ "${actual}" -eq "${expected}" ]; then
        echo "ok   - ${description} (exit=${actual})"
        PASS_COUNT=$((PASS_COUNT + 1))
    else
        echo "FAIL - ${description}"
        echo "       verwachte exit-code ${expected}, kreeg ${actual}"
        echo "       output:"
        echo "${output}" | sed 's/^/         /'
        FAIL_COUNT=$((FAIL_COUNT + 1))
    fi
}

# assert_contains <omschrijving> <naald> <commando...>
assert_contains() {
    local description="$1"
    local needle="$2"
    shift 2

    local output
    set +e
    output="$("$@" 2>&1)"
    set -e

    if echo "${output}" | grep -qF -- "${needle}"; then
        echo "ok   - ${description}"
        PASS_COUNT=$((PASS_COUNT + 1))
    else
        echo "FAIL - ${description}"
        echo "       verwachtte \"${needle}\" in de output, maar die stond er niet in:"
        echo "${output}" | sed 's/^/         /'
        FAIL_COUNT=$((FAIL_COUNT + 1))
    fi
}

echo "== check-release-readiness.sh — niet-conforme varianten (moeten falen) =="
assert_exit_code \
    "dev-development in \"require\" faalt" \
    1 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/dev-in-require.composer.json"

assert_contains \
    "dev-development in \"require\" meldt de veroorzaker" \
    "require.ginkelsoft/laravel-compliance-core" \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/dev-in-require.composer.json"

assert_exit_code \
    "path-repository faalt" \
    1 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/path-repository.composer.json"

assert_contains \
    "path-repository meldt de veroorzaker" \
    "repositories[0]" \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/path-repository.composer.json"

assert_exit_code \
    "dev-development in require + path-repository samen faalt" \
    1 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/both-problems.composer.json"

assert_exit_code \
    "ongeldige JSON faalt" \
    1 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/invalid-json.composer.json"

assert_exit_code \
    "ontbrekend bestand faalt" \
    1 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/does-not-exist.composer.json"

echo
echo "== check-release-readiness.sh — conforme varianten (moeten slagen) =="
assert_exit_code \
    "schone composer.json slaagt" \
    0 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/clean.composer.json"

assert_exit_code \
    "dev-development alleen in require-dev slaagt (Composer installeert require-dev van een dependency nooit bij een consument)" \
    0 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/dev-in-require-dev-only.composer.json"

assert_exit_code \
    "niet-path repository (vcs) slaagt" \
    0 \
    "${TARGET_SCRIPT}" "${FIXTURES_DIR}/non-path-repository.composer.json"

echo
echo "== check-release-readiness.sh — overig gedrag =="
assert_exit_code \
    "--help toont hulptekst en slaagt" \
    0 \
    "${TARGET_SCRIPT}" "--help"

# Zonder argument controleert het script ./composer.json in de huidige map.
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT
cp "${FIXTURES_DIR}/clean.composer.json" "${TMP_DIR}/composer.json"
assert_exit_code \
    "zonder argument valt terug op ./composer.json (schoon) en slaagt" \
    0 \
    bash -c "cd '${TMP_DIR}' && '${TARGET_SCRIPT}'"

cp "${FIXTURES_DIR}/dev-in-require.composer.json" "${TMP_DIR}/composer.json"
assert_exit_code \
    "zonder argument valt terug op ./composer.json (niet-conform) en faalt" \
    1 \
    bash -c "cd '${TMP_DIR}' && '${TARGET_SCRIPT}'"

echo
echo "----------------------------------------"
echo "Geslaagd: ${PASS_COUNT}, gefaald: ${FAIL_COUNT}"

if [ "${FAIL_COUNT}" -ne 0 ]; then
    exit 1
fi

exit 0
