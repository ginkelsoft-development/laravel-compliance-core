<?php

/**
 * Hulpscript voor scripts/check-release-readiness.sh — leest composer.json op
 * JSON-niveau en rapporteert release-blokkerende problemen.
 *
 * Wordt niet los aangeroepen; scripts/check-release-readiness.sh zet de
 * benodigde omgevingsvariabele (CHECK_RELEASE_READINESS_TARGET) en roept dit
 * bestand aan met `php scripts/check-release-readiness.php`. Geen hardcoded
 * paden of geheimen: het doelbestand komt uit de omgeving die het
 * bash-script doorgeeft.
 *
 * Exit-code 0: composer.json is klaar voor publicatie.
 * Exit-code 1: er is minstens één blokkerend probleem gevonden (details op
 * stdout/stderr).
 */

declare(strict_types=1);

$target = getenv('CHECK_RELEASE_READINESS_TARGET');

if ($target === false || $target === '') {
    fwrite(STDERR, "Fout: CHECK_RELEASE_READINESS_TARGET ontbreekt in de omgeving.\n");
    exit(1);
}

$raw = file_get_contents($target);
if ($raw === false) {
    fwrite(STDERR, "Fout: kan {$target} niet lezen.\n");
    exit(1);
}

$data = json_decode($raw, true);
if (! is_array($data)) {
    fwrite(STDERR, "Fout: {$target} is geen geldig JSON-bestand.\n");
    exit(1);
}

$problems = [];

// Elke "dev-*" branch-constraint in "require" wijst naar een branch, niet
// naar een getagde release. Packagist-consumenten kunnen daar niet tegen
// installeren zoals de package-auteur dat lokaal wel kan. "require-dev"
// wordt bewust niet gecontroleerd: Composer installeert de require-dev van
// een *dependency* nooit bij een consument (alleen bij het root-package),
// dus een dev-*-constraint die uitsluitend in require-dev staat, breekt
// niets voor iemand die dit pakket van Packagist installeert.
if (isset($data['require']) && is_array($data['require'])) {
    foreach ($data['require'] as $package => $constraint) {
        if (is_string($constraint) && strncmp($constraint, 'dev-', 4) === 0) {
            $problems[] = "require.{$package}: constraint \"{$constraint}\" is een branch, geen getagde versie.";
        }
    }
}

// Een "path"-repository verwijst naar een lokale checkout op deze machine.
// Dat pad bestaat niet bij een consument die het pakket van Packagist trekt.
if (isset($data['repositories']) && is_array($data['repositories'])) {
    foreach ($data['repositories'] as $index => $repo) {
        if (is_array($repo) && ($repo['type'] ?? null) === 'path') {
            $url = $repo['url'] ?? '(onbekend pad)';
            $problems[] = "repositories[{$index}]: type \"path\" naar \"{$url}\" hoort niet in een gepubliceerd pakket.";
        }
    }
}

if ($problems === []) {
    echo "OK: {$target} bevat geen dev-* dependencies en geen path-repositories.\n";
    exit(0);
}

fwrite(STDERR, "Niet klaar voor release: {$target}\n");
foreach ($problems as $problem) {
    fwrite(STDERR, "  - {$problem}\n");
}
fwrite(STDERR, "\nLos dit op (zie scripts/release-prep.sh) voordat je publiceert.\n");
exit(1);
