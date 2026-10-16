<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/firebase.php';
require_once __DIR__ . '/config/department_map.php';

// 🔒 Super admin only
if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can manage personnel.');
}

$personnel = [];
$error = '';

try {
    // Pull all users whose role is staff/admin/super_admin
    foreach ($db->collection('users')->documents() as $doc) {
        if (!$doc->exists()) continue;

        $data = $doc->data();
        $role = strtolower($data['role'] ?? 'resident');

        // Skip residents
        if ($role === 'resident') continue;

        $data['_id'] = $doc->id();
        $personnel[] = $data;
    }

    usort($personnel, function ($a, $b) {
        return strcmp($a['fullName'] ?? '', $b['fullName'] ?? '');
    });

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CitiFix Personnel</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="admin-layout">

    <aside class="sidebar">
       <div class="sidebar-logo">
    <img src="images/logo.png" alt="CitiFix Manila" class="sidebar-logo-img">
    <div class="sidebar-title">CITIFIX</div>
    <div class="sidebar-subtitle">MANILA</div>
</div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">🏠 <span>Home</span></a>
            <a href="reports.php" class="menu-item">📋 <span>Reports</span></a>
            <a href="map.php" class="menu-item">🗺️ <span>Map View</span></a>
            <a href="personnel.php" class="menu-item active">👥 <span>Personnel</span></a>
            <a href="users.php" class="menu-item">👤 <span>Users</span></a>
            <a href="settings.php" class="menu-item">⚙️ <span>Settings</span></a>
            <a href="logout.php" class="menu-item logout-menu">🚪 <span>Logout</span></a>
        </nav>
    </aside>

    <main class="main-content">

        <header class="top-header">
            <div>
                <h1>Personnel</h1>
                <p>Create admin/staff accounts and assign them to departments.</p>
            </div>
            <a href="personnel_edit.php" class="login-button" style="text-decoration:none;">
                + Add Personnel
            </a>
        </header>

        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="<?= $_SESSION['flash']['type'] === 'success' ? 'success-message' : 'error-message' ?>">
                <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section class="dashboard-card">

            <div class="card-header">
                <div>
                    <h2>Personnel List</h2>
                    <p><?= count($personnel) ?> account(s).</p>
                </div>
            </div>

            <?php if (empty($personnel)): ?>
                <div class="empty-state">
                    <div>👥</div>
                    <p>No personnel records yet.</p>
                    <small>Click "+ Add Personnel" to create the first admin or staff account.</small>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($personnel as $p):
                            $role   = strtolower($p['role'] ?? 'staff');
                            $active = $p['isActive'] ?? true;
                            $deptId = $p['departmentId'] ?? null;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($p['fullName'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($p['email'] ?? '') ?></td>
                                <td>
                                    <span class="role-badge">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($role === 'super_admin'): ?>
                                        <em>All departments</em>
                                    <?php else: ?>
                                        <?= htmlspecialchars(department_name($db, $deptId)) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($active): ?>
                                        <span class="status resolved">Active</span>
                                    <?php else: ?>
                                        <span class="status pending">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="personnel_edit.php?id=<?= urlencode($p['_id']) ?>">Edit</a>

                                    <?php if ($p['_id'] !== $ADMIN_ID): ?>
                                        &nbsp;|&nbsp;
                                        <a href="personnel_delete.php?id=<?= urlencode($p['_id']) ?>"
                                           onclick="return confirm('Delete this personnel? This cannot be undone.');"
                                           style="color:#dc2626;">
                                            Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </section>
    </main>
</div>
</body>
</html>