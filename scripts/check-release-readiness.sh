#!/usr/bin/env bash
#
# check-release-readiness.sh
#
# Controleert of composer.json in de repo-root klaar is om als getagde
# release naar Packagist te gaan. Faalt (exit 1) zodra:
#
#   A) "require" een dev-* vereiste bevat (met name "dev-development"):
#      dat wijst naar een branch in plaats van een getagde versie, en
#      breekt zodra iemand het pakket via Packagist installeert.
#
#   B) "repositories" een entry met "type": "path" bevat: dat verwijst
#      naar een lokale map op deze machine en bestaat niet bij een
#      installatie via Packagist.
#
# "require-dev" telt niet mee: dat zijn dev-tools voor dit pakket zelf,
# geen dependencies die met de release meegaan.
#
# Gebruik:
#   scripts/check-release-readiness.sh [pad-naar-composer.json]
#
# Zonder argument wordt composer.json in de repo-root gebruikt (bepaald
# t.o.v. de locatie van dit script).

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
composer_json="${1:-${script_dir}/composer.json}"

if [[ ! -f "${composer_json}" ]]; then
    echo "FOUT: bestand niet gevonden: ${composer_json}" >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "FOUT: php is nodig om composer.json te controleren, maar is niet gevonden." >&2
    exit 1
fi

failed=0

# Check A: dev-* vereisten in "require" (bv. dev-development).
dev_requires="$(
    php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data)) { exit(0); }
        $require = $data["require"] ?? [];
        foreach ($require as $package => $constraint) {
            if (is_string($constraint) && str_starts_with($constraint, "dev-")) {
                echo "{$package}: {$constraint}\n";
            }
        }
    ' "${composer_json}"
)"

if [[ -n "${dev_requires}" ]]; then
    echo "FOUT: composer.json bevat dev-* vereisten in \"require\":" >&2
    echo "${dev_requires}" | sed 's/^/  - /' >&2
    echo "  Vervang deze door getagde versie-constraints voordat je release tagt." >&2
    failed=1
fi

# Check B: path-type entries in "repositories".
path_repositories="$(
    php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data)) { exit(0); }
        $repositories = $data["repositories"] ?? [];
        foreach ($repositories as $key => $repo) {
            if (is_array($repo) && ($repo["type"] ?? null) === "path") {
                $url = $repo["url"] ?? "?";
                echo "{$key}: {$url}\n";
            }
        }
    ' "${composer_json}"
)"

if [[ -n "${path_repositories}" ]]; then
    echo "FOUT: composer.json bevat path-type entries in \"repositories\":" >&2
    echo "${path_repositories}" | sed 's/^/  - /' >&2
    echo "  Verwijder deze lokale path-repositories voordat je release tagt." >&2
    failed=1
fi

if [[ "${failed}" -ne 0 ]]; then
    echo "" >&2
    echo "Release-readiness check FAILED voor ${composer_json}." >&2
    exit 1
fi

echo "OK: ${composer_json} bevat geen dev-development vereisten of path-repositories."
exit 0
