<?php

/**
 * Hulpscript voor scripts/release-prep.sh — bewerkt composer.json op
 * JSON-niveau zodat sleutelvolgorde en overige inhoud intact blijven.
 *
 * Wordt niet los aangeroepen; scripts/release-prep.sh zet de benodigde
 * omgevingsvariabelen (RELEASE_PREP_*) en roept dit bestand aan met
 * `php scripts/release-prep.php`. Geen hardcoded paden of geheimen: alles
 * komt uit de omgeving die het bash-script doorgeeft.
 */

declare(strict_types=1);

$target = getenv('RELEASE_PREP_TARGET');
$version = getenv('RELEASE_PREP_VERSION');
$package = getenv('RELEASE_PREP_PACKAGE');
$dryRun = getenv('RELEASE_PREP_DRY_RUN') === '1';

if ($target === false || $version === false || $package === false) {
    fwrite(STDERR, "Fout: RELEASE_PREP_TARGET/VERSION/PACKAGE ontbreken in de omgeving.\n");
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

// Versie -> constraint. Als de aanroeper al een operator meegeeft
// (^, ~, >=, ==, of een kale semver met exacte pin), nemen we die
// letterlijk over; anders maken we er een caret-constraint van (bv.
// "1.4.0" -> "^1.4").
$bareVersion = ltrim($version, 'v');
if (preg_match('/^[\^~><=]/', $version) === 1) {
    $constraint = $version;
} else {
    $parts = explode('.', $bareVersion);
    $constraint = '^'.$parts[0].'.'.($parts[1] ?? '0');
}

$changed = [];
foreach (['require', 'require-dev'] as $section) {
    if (! isset($data[$section][$package])) {
        continue;
    }

    $current = $data[$section][$package];
    if (! is_string($current) || strncmp($current, 'dev-', 4) !== 0) {
        continue;
    }

    $changed[] = "{$section}.{$package}: {$current} -> {$constraint}";
    if (! $dryRun) {
        $data[$section][$package] = $constraint;
    }
}

// Path-repository die naar hetzelfde pakket wijst (herkend aan de laatste
// padcomponent van de package-naam, bv. "laravel-compliance-core") hoort
// niet meer thuis in een composer.json dat naar Packagist gaat: Packagist
// kent geen lokale paden en de consument heeft dat pad niet op schijf.
$needle = null;
if (($slash = strrpos($package, '/')) !== false) {
    $needle = substr($package, $slash + 1);
}

$removedRepos = [];
if (isset($data['repositories']) && is_array($data['repositories'])) {
    $kept = [];
    foreach ($data['repositories'] as $repo) {
        $isPathToPackage = is_array($repo)
            && ($repo['type'] ?? null) === 'path'
            && $needle !== null
            && isset($repo['url'])
            && str_contains((string) $repo['url'], $needle);

        if ($isPathToPackage) {
            $removedRepos[] = $repo['url'];

            continue;
        }
        $kept[] = $repo;
    }

    if (! $dryRun) {
        if (count($kept) > 0) {
            $data['repositories'] = $kept;
        } else {
            unset($data['repositories']);
        }
    }
}

if ($changed === [] && $removedRepos === []) {
    fwrite(STDERR, "Waarschuwing: niets om te wijzigen gevonden voor {$package} in {$target} ".
        "(geen dev-* constraint en geen bijbehorende path-repository).\n");
}

$prefix = $dryRun ? '[dry-run] ' : '';
foreach ($changed as $line) {
    echo "{$prefix}dependency: {$line}\n";
}
foreach ($removedRepos as $url) {
    echo "{$prefix}repository verwijderd: {$url}\n";
}

if ($dryRun) {
    exit(0);
}

$encoded = json_encode(
    $data,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if ($encoded === false) {
    fwrite(STDERR, "Fout: kon {$target} niet terugschrijven (json_encode faalde).\n");
    exit(1);
}

file_put_contents($target, $encoded."\n");
