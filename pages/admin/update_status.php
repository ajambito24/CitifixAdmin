<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';
require_once __DIR__ . '/../../config/fcm.php';

// 🔒 Only admins / super admins can update
if (!can_edit_reports()) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'You are not allowed to edit reports.'
    ];
    header('Location: reports.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reports.php');
    exit;
}

$reportId   = trim($_POST['report_id'] ?? '');
$status     = strtolower(trim($_POST['status'] ?? ''));
$adminNotes = trim($_POST['admin_notes'] ?? '');

$allowedStatuses = [
    'pending',
    'in progress',
    'resolved',
    'rejected'
];

if (
    $reportId === '' ||
    !in_array($status, $allowedStatuses, true)
) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Invalid report or status.'
    ];
    header('Location: reports.php');
    exit;
}

try {

    $snapshot = $db
        ->collection('reports')
        ->document($reportId)
        ->snapshot();

    if (!$snapshot->exists()) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'msg'  => 'Report not found.'
        ];
        header('Location: reports.php');
        exit;
    }

    $current = $snapshot->data();

    // 🔒 Assignment guard — super admin bypasses this
    if (!$IS_SUPER_ADMIN) {
        if (($current['assignedAdminId'] ?? null) !== $ADMIN_ID) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'msg'  => 'Access denied: this report is not assigned to you.'
            ];
            header('Location: reports.php');
            exit;
        }
    }

    $history = $current['statusHistory'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }

    $lastStatus = null;
    if (!empty($history)) {
        $lastEntry = end($history);
        $lastStatus = strtolower($lastEntry['status'] ?? '');
    }

    $statusChanged = ($lastStatus !== $status);

    // Status the report had before this save (used to decide on the push).
    $previousStatus = strtolower(trim((string) ($current['status'] ?? 'pending')));

    if ($statusChanged) {
        $history[] = [
            'status' => $status,
            'at'     => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            'by'     => $ADMIN_EMAIL,
        ];
    }

    $updateData = [
        'status'        => $status,
        'updatedAt'     => new \Google\Cloud\Core\Timestamp(new \DateTime()),
        'statusHistory' => $history,
    ];

    if ($adminNotes !== '') {
        $updateData['adminNotes'] = $adminNotes;
    }

    if ($status === 'resolved') {
        $updateData['resolvedAt'] = new \Google\Cloud\Core\Timestamp(new \DateTime());
        $updateData['isResolved'] = true;
    } else {
        $updateData['resolvedAt'] = null;
        $updateData['isResolved'] = false;
    }

    // 🔒 Assignment — super admin only
    if ($IS_SUPER_ADMIN && isset($_POST['assigned_admin_id'])) {
        $assignedId      = trim($_POST['assigned_admin_id']);
        $currentAssigned = (string) ($current['assignedAdminId'] ?? '');

        if ($assignedId === $currentAssigned) {
            // Unchanged — keep the assignment, but sync the report's department
            // with the assigned admin's department (fixes older assignments).
            if ($assignedId !== '') {
                $syncDoc = $db->collection('admins')->document($assignedId)->snapshot();
                if ($syncDoc->exists() && !empty($syncDoc->data()['departmentId'])) {
                    $updateData['departmentId'] = $syncDoc->data()['departmentId'];
                }
            }
        } elseif ($assignedId === '') {
            $updateData['assignedAdminId']   = null;
            $updateData['assignedAdminName'] = null;
        } else {
            $adminDoc = $db->collection('admins')->document($assignedId)->snapshot();

            if (!$adminDoc->exists() || empty($adminDoc->data()['isActive'])) {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'msg'  => 'Selected admin does not exist or is inactive.'
                ];
                header('Location: reports.php');
                exit;
            }

            $adminData = $adminDoc->data();
            $updateData['assignedAdminId']   = $adminDoc->id();
            $updateData['assignedAdminName'] = $adminData['fullName'] ?? ($adminData['email'] ?? $adminDoc->id());

            // The report takes the assigned admin's department.
            if (!empty($adminData['departmentId'])) {
                $updateData['departmentId'] = $adminData['departmentId'];
            }
        }
    }

    $db
        ->collection('reports')
        ->document($reportId)
        ->set(
            $updateData,
            [ 'merge' => true ]
        );

    // 🔔 Push to the report owner — only when the status really changed
    // (never for "pending", same as the in-app notification list).
    // Best effort: the report is already saved, so a push failure must not
    // turn the update into an error.
    $pushFailed = false;

    if ($statusChanged && $previousStatus !== $status && $status !== 'pending') {
        $ownerId = (string) ($current['userId'] ?? '');

        try {
            if ($ownerId !== '') {
                $ownerSnap = $db->collection('users')->document($ownerId)->snapshot();
                $fcmToken  = $ownerSnap->exists()
                    ? (string) ($ownerSnap->data()['fcmToken'] ?? '')
                    : '';

                // No token = the resident hasn't enabled push (or logged out): nothing to send.
                if ($fcmToken !== '') {
                    $ref = fcm_report_reference($reportId, $current);

                    fcm_send_to_token(
                        $fcmToken,
                        fcm_report_title($ref, $status),
                        (string) ($current['category'] ?? 'Issue'),
                        ['type' => 'report', 'id' => $reportId]
                    );
                }
            }
        } catch (Throwable $e) {
            if (fcm_is_unregistered($e) && $ownerId !== '') {
                // The token is dead (app uninstalled, data cleared, ...).
                // Remove it so we stop sending to it; the app saves a fresh
                // one the next time the resident opens it. Not an error for
                // the admin, so no warning flash.
                try {
                    $db->collection('users')->document($ownerId)->update([
                        ['path' => 'fcmToken', 'value' => \Google\Cloud\Firestore\FieldValue::deleteField()],
                    ]);
                } catch (Throwable $cleanupError) {
                    error_log('FCM stale token cleanup failed: ' . $cleanupError->getMessage());
                }
                error_log('FCM token removed (UNREGISTERED) for user ' . $ownerId);
            } else {
                $pushFailed = true;
                error_log('FCM report push failed: ' . $e->getMessage());
            }
        }
    }

    $_SESSION['flash'] = [
        'type' => 'success',
        'msg'  => $pushFailed
            ? 'Report updated, but the push notification could not be sent.'
            : 'Report updated successfully.'
    ];

} catch (Exception $e) {

    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Unable to update report: ' . $e->getMessage()
    ];
}

header('Location: reports.php');
exit;