<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/firebase.php';

// Not logged in → bounce to login
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

/**
 * Load the current admin from Firestore by email.
 */
function citifix_current_admin($db): ?array
{
    $email = $_SESSION['admin_email'] ?? null;
    if (!$email) return null;

    $docs = $db->collection('users')
        ->where('email', '=', $email)
        ->limit(1)
        ->documents();

    foreach ($docs as $doc) {
        if ($doc->exists()) {
            $data = $doc->data();
            $data['_id'] = $doc->id();
            return $data;
        }
    }
    return null;
}

$currentUser = citifix_current_admin($db);

if (!$currentUser || empty($currentUser['isActive'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ---- Globals available to every protected page ----
$ADMIN_ID       = $currentUser['_id'];
$ADMIN_EMAIL    = $currentUser['email'] ?? '';
$ADMIN_NAME     = $currentUser['fullName'] ?? 'Administrator';
$ADMIN_ROLE     = strtolower($currentUser['role'] ?? 'staff');
$ADMIN_DEPT     = $currentUser['departmentId'] ?? null;
$IS_SUPER_ADMIN = ($ADMIN_ROLE === 'super_admin');

if (!$IS_SUPER_ADMIN && !$ADMIN_DEPT) {
    http_response_code(403);
    die('Your account has no department assigned. Contact the super administrator.');
}

/**
 * Can this user change report status / notes?
 */
function can_edit_reports(): bool
{
    global $ADMIN_ROLE;
    return in_array($ADMIN_ROLE, ['admin', 'super_admin'], true);
}

/**
 * Hard guard: throw 403 if a report belongs to another dept.
 */
function assert_report_access(array $report): void
{
    global $IS_SUPER_ADMIN, $ADMIN_DEPT;

    if ($IS_SUPER_ADMIN) return;

    if (($report['departmentId'] ?? null) !== $ADMIN_DEPT) {
        http_response_code(403);
        die('Access denied: this report belongs to another department.');
    }
}