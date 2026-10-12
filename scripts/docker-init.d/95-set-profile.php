#!/usr/bin/env php
<?php
/*
 * Dolibarr Reference Environment - Profile Configuration Script
 * This script runs during container initialization (via docker-init.d mechanism)
 * to configure the baseline profile: language, timezone, and currency.
 */

require_once '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

echo "[set-profile] Configuring baseline profile...\n";

// 1. Set language to es_ES (Spanish)
$langCode = 'es_ES';
echo "[set-profile] Setting MAIN_LANG_DEFAULT to $langCode\n";

$res = dolibarr_set_const($db, 'MAIN_LANG_DEFAULT', $langCode, 'chaine', 0, '', $conf->entity);
if ($res < 0) {
    echo "[set-profile] ERROR: Failed to update MAIN_LANG_DEFAULT: " . $db->lasterror() . "\n";
    exit(1);
}
echo "[set-profile] MAIN_LANG_DEFAULT set to $langCode\n";

// 2. Set base currency to EUR using MAIN_MONNAIE (standard Dolibarr mechanism, independent of Multicurrency module)
$currencyCode = 'EUR';
echo "[set-profile] Setting MAIN_MONNAIE to $currencyCode\n";

$res = dolibarr_set_const($db, 'MAIN_MONNAIE', $currencyCode, 'chaine', 0, '', $conf->entity);
if ($res < 0) {
    echo "[set-profile] ERROR: Failed to update MAIN_MONNAIE: " . $db->lasterror() . "\n";
    exit(1);
}
echo "[set-profile] MAIN_MONNAIE set to $currencyCode\n";

echo "[set-profile] Profile configuration complete\n";
exit(0);