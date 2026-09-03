#!/usr/bin/env bash
#
# Proefdraai-script voor laravel-compliance-core.
#
# Dit is een Composer-package (geen losstaande Laravel-applicatie): er is
# geen artisan, geen .env.example en geen eigen database. Proefdraaien
# betekent hier: dependencies installeren, de testsuite (inclusief de
# nieuwe LogSecret-waarschuwing) draaien, en een klein statusoverzicht op
# $PORT serveren zodat je zonder editor kunt zien dat alles werkt.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PORT="${PORT:-8000}"

echo "==> composer install"
composer install --no-interaction --prefer-dist

echo "==> testsuite draaien (vendor/bin/pest)"
TEST_OUTPUT_FILE="$(mktemp)"
if vendor/bin/pest --colors=never | tee "$TEST_OUTPUT_FILE"; then
    TEST_STATUS="geslaagd"
else
    TEST_STATUS="MISLUKT"
fi

PUBLIC_DIR="$ROOT_DIR/.zyra/public"
rm -rf "$PUBLIC_DIR"
mkdir -p "$PUBLIC_DIR"

TEST_SUMMARY="$(tail -n 5 "$TEST_OUTPUT_FILE" | sed 's/</\&lt;/g; s/>/\&gt;/g')"

cat > "$PUBLIC_DIR/index.php" <<PHP
<?php
\$status = '${TEST_STATUS}';
\$summary = <<<'TXT'
${TEST_SUMMARY}
TXT;
?>
<!DOCTYPE html>
<html lang="nl">
<head><meta charset="utf-8"><title>laravel-compliance-core — proefdraaien</title></head>
<body style="font-family: sans-serif; max-width: 40rem; margin: 2rem auto;">
<h1>laravel-compliance-core</h1>
<p>Dit is een Composer-package voor Laravel, geen losstaande applicatie.
Er draait hier geen artisan-server met eigen routes; deze pagina toont
alleen dat installatie en testsuite zijn gelukt.</p>
<p><strong>Testsuite:</strong> <?= htmlspecialchars(\$status) ?></p>
<pre><?= htmlspecialchars(\$summary) ?></pre>
<h2>Issue #1: LogSecret::value()</h2>
<p>Wanneer <code>compliance.log_secret</code> leeg is, logt
<code>LogSecret::value()</code> nu eenmalig een waarschuwing via het
Laravel <code>Log</code>-kanaal (te zien in <code>storage/logs/laravel.log</code>
van de applicatie die dit package gebruikt). Zie
<code>tests/Unit/LogSecretTest.php</code> voor het bewijs.</p>
</body>
</html>
PHP

echo "==> serveer statusoverzicht op 0.0.0.0:${PORT}"
exec php -S "0.0.0.0:${PORT}" -t "$PUBLIC_DIR"
