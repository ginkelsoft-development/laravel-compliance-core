#!/usr/bin/env bash
#
# Proefdraai-script voor laravel-compliance-core (Composer-pakket, geen
# eigen Laravel-app).
#
# Dit repo heeft geen artisan/.env/routes: het is een package dat je in een
# consumerende Laravel-app installeert (rechtstreeks, of via een van de
# familiepakketten). Voor lokaal proefdraaien gebruiken we daarom Orchestra
# Testbench, dat een wegwerp-Laravel-skeleton opzet met dit pakket erin
# geladen (via "extra.laravel.providers" in composer.json).
#
# Dit script:
#   1. draait composer install,
#   2. publiceert de eigen config (config/compliance.php) naar de
#      testbench-skeleton, zodat je 'm kunt inzien/aanpassen,
#   3. start de testbench-skeleton met `artisan serve` op 0.0.0.0:$PORT.
#
# Er is geen database-migratie nodig: dit pakket levert zelf geen
# migraties, commands of routes (zie ComplianceCoreServiceProvider) — de
# hash-chain- en anonymize-primitieven zijn pure PHP-classes zonder
# eigen tabellen. `vendor/bin/pest` (zie hieronder) is de manier om het
# gedrag te zien; de draaiende server bewijst vooral dat het pakket
# zonder fouten in een echte Laravel-boot terechtkomt en zijn config
# publiceert.
#
# Gebruik:
#   PORT=8080 .zyra/proef.sh
#
# Standaard poort is 8000 als $PORT niet gezet is.
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PORT="${PORT:-8000}"

echo "== laravel-compliance-core: proefdraai =="

echo "-- composer install"
composer install --no-interaction

echo "-- eigen config publiceren naar de testbench-skeleton (config/compliance.php)"
vendor/bin/testbench vendor:publish --tag=compliance-config --no-interaction

echo "-- eigen tests draaien (bewijst dat de hash-chain/anonymize-primitieven werken)"
vendor/bin/pest --colors=always || {
    echo "Let op: de testrun faalde. De server hieronder start alsnog," \
         "zodat je kunt rondkijken, maar los eerst de falende test(s) op" \
         "voordat je dit als 'werkt' beschouwt." >&2
}

echo "-- server starten op 0.0.0.0:${PORT}"
echo "   Dit pakket levert zelf geen routes; de skeleton-welkomstpagina"
echo "   bewijst dat de service provider zonder fouten boot. Interessanter"
echo "   is om in een tweede shell te kijken naar:"
echo "     vendor/orchestra/testbench-core/laravel/config/compliance.php"
echo "   (de zojuist gepubliceerde config)."
exec vendor/bin/testbench serve --host=0.0.0.0 --port="${PORT}" --no-interaction
