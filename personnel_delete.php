<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/firebase.php';

if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can delete personnel.');
}

$id = $_GET['id'] ?? '';

if ($id === '') {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Missing personnel ID.'];
    header('Location: personnel.php');
    exit;
}

if ($id === $ADMIN_ID) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'You cannot delete your own account.'];
    header('Location: personnel.php');
    exit;
}

try {
    $db->collection('users')->document($id)->delete();
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Personnel deleted.'];
} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Delete failed: ' . $e->getMessage()];
}

header('Location: personnel.php');
exit;