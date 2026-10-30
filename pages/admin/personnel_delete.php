<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';

// 🔒 Super admin only
require_super_admin();

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid personnel.'];
    header('Location: personnel.php');
    exit;
}

try {
    $ref = $db->collection('admins')->document($id);

    if (!$ref->snapshot()->exists()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Personnel not found.'];
    } else {
        $ref->delete();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Personnel deleted.'];
    }
} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Delete failed: ' . $e->getMessage()];
}

header('Location: personnel.php');
exit;