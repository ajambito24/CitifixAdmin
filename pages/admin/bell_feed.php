<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/bell_data.php';

// Nothing below writes to the session, so release the lock to avoid blocking page loads.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(cf_bell_fetch($db, !empty($IS_SUPER_ADMIN), $ADMIN_DEPT ?? null));