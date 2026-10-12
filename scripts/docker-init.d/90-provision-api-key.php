#!/usr/bin/env php
<?php
/*
 * Dolibarr Reference Environment - API Key Provisioning Script
 * This script runs during container initialization (via docker-init.d mechanism)
 * to provision a REST API key for the admin user using Dolibarr's business layer.
 *
 * It uses the User class to set the api_key field in the Dolibarr user table
 * (database prefix is configurable, default is llx_).
 *
 * The API key MUST be provided via DOLIBARR_REFERENCE_API_KEY environment variable.
 * If not set, the script fails explicitly - no silent generation.
 */

require_once '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';

$apiKey = getenv('DOLIBARR_REFERENCE_API_KEY');

if (empty($apiKey)) {
    echo "[provision-api-key] ERROR: DOLIBARR_REFERENCE_API_KEY environment variable is not set\n";
    echo "[provision-api-key] API key must be provided by repository tooling, not generated internally\n";
    exit(1);
} else {
    echo "[provision-api-key] Using API key from environment\n";
}

// Load admin user
$adminLogin = getenv('DOLI_ADMIN_LOGIN') ?: 'admin';
$user = new User($db);
$result = $user->fetch(0, $adminLogin);

if ($result <= 0) {
    echo "[provision-api-key] ERROR: Admin user '$adminLogin' not found\n";
    exit(1);
}

echo "[provision-api-key] Found admin user (id={$user->id})\n";

// Set the API key using Dolibarr's business layer
$user->api_key = $apiKey;
$updateResult = $user->update($user); // notrigger=0, nosync=0

if ($updateResult < 0) {
    echo "[provision-api-key] ERROR: Failed to update API key: " . $user->error . "\n";
    exit(1);
}

echo "[provision-api-key] API key provisioned successfully for user '$adminLogin'\n";

// Verify the key works by fetching the user again
$verifyUser = new User($db);
$verifyUser->fetch($user->id);
if ($verifyUser->api_key === $apiKey) {
    echo "[provision-api-key] Verification: API key matches\n";
} else {
    echo "[provision-api-key] WARNING: API key verification failed (may be encryption difference)\n";
}

// Mask key for logging (show only first 8 and last 4 chars)
$maskedKey = strlen($apiKey) > 12
    ? substr($apiKey, 0, 8) . str_repeat('*', strlen($apiKey) - 12) . substr($apiKey, -4)
    : str_repeat('*', strlen($apiKey));
echo "[provision-api-key] API key (masked): $maskedKey\n";

exit(0);