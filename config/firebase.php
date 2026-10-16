<?php

/*
 * Force REST transport instead of gRPC.
 *
 * The gRPC PHP extension crashes Apache (0xC0000005) on Windows
 * with PHP 8.2. Using REST avoids loading gRPC entirely.
 */
putenv('GOOGLE_CLOUD_PHP_TRANSPORT=rest');          
$_SERVER['GOOGLE_CLOUD_PHP_TRANSPORT'] = 'rest';    

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;
use Google\Auth\Credentials\ServiceAccountCredentials;

$serviceAccountPath =
    __DIR__ . '/../service-account/service-account.json';

if (!file_exists($serviceAccountPath)) {
    die('ERROR: service-account.json was not found.');
}

$json = file_get_contents($serviceAccountPath);

$keyFile = json_decode($json, true);

if (!is_array($keyFile)) {
    die('ERROR: service-account.json is not valid JSON.');
}

if (($keyFile['type'] ?? '') !== 'service_account') {
    die('ERROR: This is not a valid Google service-account JSON file.');
}

if (
    empty($keyFile['client_email']) ||
    empty($keyFile['private_key'])
) {
    die('ERROR: Service-account JSON is missing required credentials.');
}

$credentials = new ServiceAccountCredentials(
    'https://www.googleapis.com/auth/datastore',
    $keyFile
);

try {

    $db = new FirestoreClient([
        'projectId'   => 'citifix-67aa2',
        'credentials' => $credentials,
        'transport'   => 'rest',                    
    ]);

} catch (Exception $e) {

    die(
        'Firebase connection error:<br><br>' .
        htmlspecialchars($e->getMessage())
    );
}