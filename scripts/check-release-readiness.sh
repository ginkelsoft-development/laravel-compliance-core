#!/usr/bin/env bash
#
# check-release-readiness.sh
#
# Faalt (exit 1) zodra de composer.json van dit pakket:
#   - een "dev-development" vereiste bevat in "require" of "require-dev", of
#   - een "path"-type entry bevat in "repositories".
#
# Beide zijn tekenen dat dit pakket nog naar een lokale sibling-checkout
# wijst in plaats van naar een getagde Packagist-versie. Zie RELEASE.md
# voor de volledige release-checklist waar dit script onderdeel van is.
#
# Controleert alleen de composer.json van de repo waarin het draait, niet
# die van andere familiepakketten (die heeft een checkout meestal niet
# ernaast staan).
#
# Gebruik:
#   ./scripts/check-release-readiness.sh [pad-naar-composer.json]

set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

COMPOSER_JSON="${1:-composer.json}"

if [ ! -f "$COMPOSER_JSON" ]; then
    echo "FOUT: $COMPOSER_JSON niet gevonden." >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "FOUT: php is nodig om $COMPOSER_JSON te controleren." >&2
    exit 1
fi

RESULT="$(php -r '
$file = $argv[1];
$json = json_decode(file_get_contents($file), true);
if (!is_array($json)) {
    fwrite(STDERR, "FOUT: kon $file niet parsen als JSON.\n");
    exit(1);
}

$problems = [];

foreach (["require", "require-dev"] as $section) {
    foreach (($json[$section] ?? []) as $package => $constraint) {
        if (str_contains((string) $constraint, "dev-development")) {
            $problems[] = "  - {$section}.{$package}: \"{$constraint}\" (dev-development)";
        }
    }
}

foreach (($json["repositories"] ?? []) as $key => $repo) {
    if (is_array($repo) && ($repo["type"] ?? null) === "path") {
        $url = $repo["url"] ?? "?";
        $problems[] = "  - repositories[{$key}]: type \"path\" naar \"{$url}\"";
    }
}

if ($problems !== []) {
    echo implode("\n", $problems) . "\n";
    exit(1);
}

exit(0);
' "$COMPOSER_JSON")" && STATUS=0 || STATUS=$?

if [ "$STATUS" -ne 0 ]; then
    echo "Niet klaar voor release: $COMPOSER_JSON bevat nog dev-development-vereisten of path-repositories:" >&2
    echo "$RESULT" >&2
    echo "" >&2
    echo "Zie RELEASE.md, stap 2 en 3, om dit op te lossen." >&2
    exit 1
fi

echo "OK: $COMPOSER_JSON bevat geen dev-development-vereisten of path-repositories."
