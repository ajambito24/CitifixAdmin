<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(120);

putenv('GOOGLE_CLOUD_PHP_TRANSPORT=rest');
$_SERVER['GOOGLE_CLOUD_PHP_TRANSPORT'] = 'rest';

require __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;
use Google\Auth\Credentials\ServiceAccountCredentials;

$keyFile = json_decode(
    file_get_contents(__DIR__ . '/service-account/service-account.json'),
    true
);

$credentials = new ServiceAccountCredentials(
    'https://www.googleapis.com/auth/datastore',
    $keyFile
);

echo "Creating client...\n";
$db = new FirestoreClient([
    'projectId'   => 'citifix-67aa2',
    'credentials' => $credentials,
    'transport'   => 'rest',
]);
echo "Client created.\n";

echo "Fetching 1 user doc...\n";
$docs = $db->collection('users')->limit(1)->documents();
foreach ($docs as $doc) {
    echo "OK: " . $doc->id() . "\n";
}
echo "Done.\n";