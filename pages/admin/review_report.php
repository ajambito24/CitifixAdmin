<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/fcm.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function review_json(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    review_json(405, ['ok' => false, 'msg' => 'Invalid request.']);
}

// 🔒 Only the super admin reviews (accepts / declines) new reports
if (!$IS_SUPER_ADMIN) {
    review_json(403, ['ok' => false, 'msg' => 'Only the super admin can review reports.']);
}

$reportId = trim($_POST['report_id'] ?? '');
$action   = strtolower(trim($_POST['action'] ?? ''));

if ($reportId === '' || !in_array($action, ['accept', 'decline'], true)) {
    review_json(400, ['ok' => false, 'msg' => 'Invalid report or action.']);
}

try {
    $ref      = $db->collection('reports')->document($reportId);
    $snapshot = $ref->snapshot();

    if (!$snapshot->exists()) {
        review_json(404, ['ok' => false, 'msg' => 'Report not found.']);
    }

    $current = $snapshot->data();
    $status  = strtolower(trim((string) ($current['status'] ?? 'pending')));

    // Only reports still waiting for review can be accepted / declined
    if ($status !== 'pending' || !empty($current['reviewStatus'])) {
        review_json(409, ['ok' => false, 'msg' => 'This report was already reviewed.']);
    }

    $now = new \Google\Cloud\Core\Timestamp(new \DateTime());

    // ---------------------------------------------------------------
    // ACCEPT → stays "pending", just marked as reviewed
    // ---------------------------------------------------------------
    if ($action === 'accept') {
        $ref->set([
            'reviewStatus' => 'accepted',
            'reviewedAt'   => $now,
            'reviewedBy'   => $ADMIN_EMAIL,
        ], ['merge' => true]);

        review_json(200, ['ok' => true, 'action' => 'accept']);
    }

    // ---------------------------------------------------------------
    // DECLINE → status becomes "rejected"
    // ---------------------------------------------------------------
    $history = $current['statusHistory'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }
    $history[] = [
        'status' => 'rejected',
        'at'     => $now,
        'by'     => $ADMIN_EMAIL,
    ];

    $ref->set([
        'status'        => 'rejected',
        'reviewStatus'  => 'declined',
        'reviewedAt'    => $now,
        'reviewedBy'    => $ADMIN_EMAIL,
        'updatedAt'     => $now,
        'statusHistory' => $history,
        'resolvedAt'    => null,
        'isResolved'    => false,
    ], ['merge' => true]);

    // 🔔 Push to the report owner (same behavior as update_status.php).
    // Best effort: the report is already saved.
    $pushFailed = false;
    $ownerId    = (string) ($current['userId'] ?? '');

    try {
        if ($ownerId !== '') {
            $ownerSnap = $db->collection('users')->document($ownerId)->snapshot();
            $fcmToken  = $ownerSnap->exists()
                ? (string) ($ownerSnap->data()['fcmToken'] ?? '')
                : '';

            if ($fcmToken !== '') {
                $refNo = fcm_report_reference($reportId, $current);

                fcm_send_to_token(
                    $fcmToken,
                    fcm_report_title($refNo, 'rejected'),
                    (string) ($current['category'] ?? 'Issue'),
                    ['type' => 'report', 'id' => $reportId]
                );
            }
        }
    } catch (Throwable $e) {
        if (fcm_is_unregistered($e) && $ownerId !== '') {
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

    review_json(200, ['ok' => true, 'action' => 'decline', 'pushFailed' => $pushFailed]);

} catch (Throwable $e) {
    review_json(500, ['ok' => false, 'msg' => 'Unable to review report: ' . $e->getMessage()]);
}