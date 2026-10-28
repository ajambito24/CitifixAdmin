<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../config/department_map.php';

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
    'resolved'
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

    // 🔒 Department guard — super admin bypasses this
    if (!$IS_SUPER_ADMIN) {
        if (($current['departmentId'] ?? null) !== $ADMIN_DEPT) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'msg'  => 'Access denied: this report belongs to another department.'
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

    $db
        ->collection('reports')
        ->document($reportId)
        ->set(
            $updateData,
            [ 'merge' => true ]
        );

    $_SESSION['flash'] = [
        'type' => 'success',
        'msg'  => 'Report updated successfully.'
    ];

} catch (Exception $e) {

    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Unable to update report: ' . $e->getMessage()
    ];
}

header('Location: reports.php');
exit;