<?php

/*
 * FCM HTTP v1 helper (push notifications).
 *
 * Uses the same service-account.json as firebase.php, but with the
 * firebase.messaging scope. Every function throws an Exception on failure,
 * so callers should wrap it in try/catch (a push must never break the
 * main action).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;

const FCM_PROJECT_ID = 'citifix-67aa2';
const FCM_TOPIC      = 'announcements';
const FCM_SCOPE      = 'https://www.googleapis.com/auth/firebase.messaging';

/**
 * Thrown when FCM answers with an error. $errorCode is FCM's own error code
 * (e.g. "UNREGISTERED") when it sent one, otherwise an empty string.
 */
class FcmException extends Exception
{
    public string $errorCode;

    public function __construct(string $message, int $httpStatus = 0, string $errorCode = '')
    {
        parent::__construct($message, $httpStatus);
        $this->errorCode = $errorCode;
    }
}

/**
 * True when FCM says the device token is dead (app uninstalled, data
 * cleared, token replaced), so it should be removed from Firestore.
 */
function fcm_is_unregistered(Throwable $e): bool
{
    return $e instanceof FcmException && $e->errorCode === 'UNREGISTERED';
}

/**
 * OAuth access token for the FCM API (cached for the current request).
 */
function fcm_access_token(): string
{
    static $cache = null;

    if ($cache !== null && $cache['expires'] > time() + 60) {
        return $cache['token'];
    }

$path = file_exists('/var/citifix/service-account.json')
    ? '/var/citifix/service-account.json'
    : __DIR__ . '/../service-account/service-account.json';
    if (!file_exists($path)) {
        throw new Exception('service-account.json was not found.');
    }

    $keyFile = json_decode((string) file_get_contents($path), true);
    if (!is_array($keyFile)) {
        throw new Exception('service-account.json is not valid JSON.');
    }

    $credentials = new ServiceAccountCredentials(FCM_SCOPE, $keyFile);
    $result      = $credentials->fetchAuthToken();

    if (!is_array($result) || empty($result['access_token'])) {
        throw new Exception('Could not get an FCM access token.');
    }

    $cache = [
        'token'   => $result['access_token'],
        'expires' => time() + (int) ($result['expires_in'] ?? 3600),
    ];

    return $cache['token'];
}

/**
 * POSTs one message to the FCM HTTP v1 API.
 * Uses cURL when available, otherwise a plain HTTPS stream.
 */
function fcm_send(array $message): void
{
    $url     = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';
    $payload = json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    $headers = [
        'Authorization: Bearer ' . fcm_access_token(),
        'Content-Type: application/json; charset=utf-8',
    ];

    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception($curlErr !== '' ? $curlErr : 'Could not reach FCM.');
        }
    } else {
        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $payload,
            'timeout'       => 20,
            'ignore_errors' => true,
        ]]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception('Could not reach FCM.');
        }

        if (isset($http_response_header[0])
            && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    if ($status < 200 || $status >= 300) {
        $json = json_decode((string) $response, true);
        $msg  = is_array($json) ? ($json['error']['message'] ?? '') : '';

        // FCM puts its own error code (e.g. UNREGISTERED) in error.details[].errorCode
        $errorCode = '';
        $details   = is_array($json) ? ($json['error']['details'] ?? []) : [];
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (is_array($detail) && !empty($detail['errorCode'])) {
                    $errorCode = (string) $detail['errorCode'];
                    break;
                }
            }
        }

        throw new FcmException(
            'FCM error (HTTP ' . $status . '): ' . ($msg !== '' ? $msg : 'unknown error'),
            $status,
            $errorCode
        );
    }
}

/**
 * Builds a notification + data message for a target
 * (['topic' => ...] or ['token' => ...]).
 * Data values must be strings; the app reads data.type and data.id.
 * Android priority HIGH so the popup arrives promptly even when the phone
 * is idle; the popup itself comes from the app's high-importance channel.
 */
function fcm_message(array $target, string $title, string $body, array $data): array
{
    return $target + [
        'notification' => [
            'title' => $title,
            'body'  => $body,
        ],
        'data'    => array_map('strval', $data),
        'android' => [
            'priority' => 'HIGH',
        ],
    ];
}

/** One push to every device subscribed to the "announcements" topic. */
function fcm_send_to_topic(string $title, string $body, array $data): void
{
    fcm_send(fcm_message(['topic' => FCM_TOPIC], $title, $body, $data));
}

/** One push to a single device token. */
function fcm_send_to_token(string $token, string $title, string $body, array $data): void
{
    fcm_send(fcm_message(['token' => $token], $title, $body, $data));
}

/** Shortens text for a notification body. */
function fcm_excerpt(string $text, int $max = 100): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

/**
 * Same reference the app shows (reportReferenceOf in my_reports.dart):
 * the stored referenceNumber, or the first 8 characters of the document ID.
 */
function fcm_report_reference(string $id, array $data): string
{
    return (string) ($data['referenceNumber'] ?? strtoupper(substr($id, 0, 8)));
}

/** Same wording as the in-app notification (_reportTitle in notifications_screen.dart). */
function fcm_report_title(string $ref, string $status): string
{
    switch ($status) {
        case 'in progress':
            return "Report #$ref is now In Progress";
        case 'resolved':
            return "Report #$ref has been resolved";
        case 'rejected':
            return "Report #$ref was rejected";
        default:
            return "Report #$ref was updated";
    }
}