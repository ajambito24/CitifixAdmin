<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';
require_once __DIR__ . '/../../config/fcm.php';

// Cloudinary (same cloud + unsigned preset used for report and announcement photos)
const UPD_CLOUD_NAME   = 'e7d4ahld';
const UPD_CLOUD_PRESET = 'Citifix';
const UPD_CLOUD_FOLDER = 'resolutions';
const UPD_MAX_IMAGE    = 5 * 1024 * 1024; // 5 MB

function upd_fail(string $msg): void
{
    $_SESSION['flash'] = ['type' => 'error', 'msg' => $msg];
    header('Location: reports.php');
    exit;
}

/**
 * Uploads an image to Cloudinary (unsigned preset) and returns its secure https URL.
 * Same approach as announcement.php. Throws Exception on failure.
 */
function upd_cloudinary_upload(string $tmpPath, string $mime, string $ext): string
{
    $url    = 'https://api.cloudinary.com/v1_1/' . UPD_CLOUD_NAME . '/image/upload';
    $fields = ['upload_preset' => UPD_CLOUD_PRESET, 'folder' => UPD_CLOUD_FOLDER];
    $name   = 'resolution.' . $ext;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields + ['file' => new CURLFile($tmpPath, $mime, $name)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception($curlErr !== '' ? $curlErr : 'Could not reach Cloudinary.');
        }
    } else {
        $boundary = '----cf' . bin2hex(random_bytes(8));
        $body = '';
        foreach ($fields as $key => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$key}\"\r\n\r\n{$value}\r\n";
        }
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$name}\"\r\n"
               . "Content-Type: {$mime}\r\n\r\n" . file_get_contents($tmpPath) . "\r\n--{$boundary}--\r\n";

        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: multipart/form-data; boundary={$boundary}\r\n",
            'content'       => $body,
            'timeout'       => 60,
            'ignore_errors' => true,
        ]]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception('Could not reach Cloudinary.');
        }
    }

    $json = json_decode($response, true);
    if (!is_array($json) || empty($json['secure_url'])) {
        throw new Exception($json['error']['message'] ?? 'Unexpected response from Cloudinary.');
    }

    return $json['secure_url'];
}

// 🔒 Only admins / super admins can update
if (!can_edit_reports()) {
    upd_fail('You are not allowed to edit reports.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reports.php');
    exit;
}

$reportId   = trim($_POST['report_id'] ?? '');
$status     = strtolower(trim($_POST['status'] ?? ''));
$adminNotes = trim($_POST['admin_notes'] ?? '');

// "in progress" no longer exists
$allowedStatuses = ['pending', 'resolved', 'rejected'];

if ($reportId === '' || !in_array($status, $allowedStatuses, true)) {
    upd_fail('Invalid report or status.');
}

try {

    $snapshot = $db
        ->collection('reports')
        ->document($reportId)
        ->snapshot();

    if (!$snapshot->exists()) {
        upd_fail('Report not found.');
    }

    $current = $snapshot->data();

    // 🔒 Assignment guard — super admin bypasses this
    if (!$IS_SUPER_ADMIN) {
        if (($current['assignedAdminId'] ?? null) !== $ADMIN_ID) {
            upd_fail('Access denied: this report is not assigned to you.');
        }
    }

    // Status the report had before this save. Old "in progress" counts as pending.
    $previousStatus = strtolower(str_replace('_', ' ', trim((string) ($current['status'] ?? 'pending'))));
    if (!in_array($previousStatus, ['pending', 'resolved', 'rejected'], true)) {
        $previousStatus = 'pending';
    }

    // 🔒 Closed (resolved / rejected) reports: only the super admin can still edit or reopen them
    $isClosed = ($previousStatus !== 'pending');
    if ($isClosed && !$IS_SUPER_ADMIN) {
        upd_fail('This report is already closed and can no longer be edited.');
    }

    // Does this save change the status? (e.g. pending -> rejected, or reopening to pending)
    $statusChanged = ($previousStatus !== $status);

    // 🔒 Role rules
    if ($IS_SUPER_ADMIN) {
        $currentAssigned   = (string) ($current['assignedAdminId'] ?? '');
        $resultingAssigned = isset($_POST['assigned_admin_id'])
            ? trim((string) $_POST['assigned_admin_id'])
            : $currentAssigned;

        if ($statusChanged) {
            // Super admin never resolves. Rejecting is only possible while unassigned.
            // Reopening (closed -> pending) is always allowed.
            if ($status === 'resolved') {
                upd_fail('Only the assigned admin can resolve a report.');
            }
            if ($status === 'rejected' && $resultingAssigned !== '') {
                upd_fail('Assigned reports stay Pending. Only the assigned admin can resolve or reject them.');
            }
        } elseif ($isClosed && $resultingAssigned !== '' && $resultingAssigned !== $currentAssigned) {
            upd_fail('Assigning a closed report reopens it. Set the status to Pending first.');
        }
    } else {
        // Assigned admin: Resolved or Rejected only
        if (!in_array($status, ['resolved', 'rejected'], true)) {
            upd_fail('Please choose Resolved or Rejected.');
        }
    }

    // 📸 Resolved by the assigned admin requires a photo (uploaded to Cloudinary)
    $resolutionUrl = '';
    if (!$IS_SUPER_ADMIN && $status === 'resolved') {
        $file = $_FILES['resolution_image'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            upd_fail('Please attach a resolution photo before marking this report as resolved.');
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            upd_fail('Image is too large (max 5 MB).');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            upd_fail('Image upload failed. Please try again.');
        }
        if ($file['size'] > UPD_MAX_IMAGE) {
            upd_fail('Image is too large (max 5 MB).');
        }

        $info   = @getimagesize($file['tmp_name']);
        $mime   = is_array($info) ? ($info['mime'] ?? '') : '';
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($extMap[$mime])) {
            upd_fail('Only JPG, PNG or WebP images are allowed.');
        }

        try {
            $resolutionUrl = upd_cloudinary_upload($file['tmp_name'], $mime, $extMap[$mime]);
        } catch (Exception $e) {
            upd_fail('Photo upload failed: ' . $e->getMessage());
        }
    }

    $history = $current['statusHistory'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }

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
        // Only when the assigned admin really resolves it. A super admin editing
        // notes on an already resolved report must not touch the resolution fields.
        if ($statusChanged) {
            $updateData['resolvedAt']         = new \Google\Cloud\Core\Timestamp(new \DateTime());
            $updateData['isResolved']         = true;
            $updateData['resolutionImageUrl'] = $resolutionUrl;
        }
    } else {
        $updateData['resolvedAt'] = null;
        $updateData['isResolved'] = false;

        // Reopening a resolved report clears its resolution photo (the file stays in Cloudinary).
        if ($previousStatus === 'resolved') {
            $updateData['resolutionImageUrl'] = '';
        }
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
                upd_fail('Selected admin does not exist or is inactive.');
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

    if ($statusChanged && $status !== 'pending') {
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