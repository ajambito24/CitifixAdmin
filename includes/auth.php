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
 * Load the current account from Firestore using the id + role stored at login.
 * super_admin → `super_admins` collection, admin → `admins` collection.
 */
function citifix_current_admin($db): ?array
{
    $id   = $_SESSION['admin_id']   ?? null;
    $role = $_SESSION['admin_role'] ?? null;

    if (!$id || !in_array($role, ['super_admin', 'admin'], true)) {
        return null;
    }

    $collection = $role === 'super_admin' ? 'super_admins' : 'admins';

    $doc = $db->collection($collection)->document($id)->snapshot();
    if (!$doc->exists()) {
        return null;
    }

    $data = $doc->data();
    $data['_id']  = $doc->id();
    $data['role'] = $role;
    return $data;
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
$ADMIN_ROLE     = $currentUser['role'];                    // 'super_admin' | 'admin'
$ADMIN_DEPT     = $currentUser['departmentId'] ?? null;    // informational only
$IS_SUPER_ADMIN = ($ADMIN_ROLE === 'super_admin');

/**
 * Can this user change report status / notes?
 * Admins are further limited to their own assigned reports (see assert_report_access).
 */
function can_edit_reports(): bool
{
    global $ADMIN_ROLE;
    return in_array($ADMIN_ROLE, ['admin', 'super_admin'], true);
}

/**
 * Hard guard: 403 if a report is not assigned to the current admin.
 */
function assert_report_access(array $report): void
{
    global $IS_SUPER_ADMIN, $ADMIN_ID;

    if ($IS_SUPER_ADMIN) return;

    if (($report['assignedAdminId'] ?? null) !== $ADMIN_ID) {
        http_response_code(403);
        die('Access denied: this report is not assigned to you.');
    }
}

/**
 * Page guard: super admin only.
 */
function require_super_admin(): void
{
    global $IS_SUPER_ADMIN;

    if (!$IS_SUPER_ADMIN) {
        http_response_code(403);
        die('Only super administrators can access this page.');
    }
}