<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/firebase.php';
require_once __DIR__ . '/config/department_map.php';

// 🔒 Super admin only
if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can view all users.');
}

$users = [];
$error = '';

try {

    $documents = $db->collection('users')->documents();

    foreach ($documents as $document) {

        if (!$document->exists()) {
            continue;
        }

        $data = $document->data();
        $data['_id'] = $document->id();
        $users[] = $data;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CitiFix Users</title>

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
            <a href="personnel.php" class="menu-item">👥 <span>Personnel</span></a>
            <a href="users.php" class="menu-item active">👤 <span>Users</span></a>
            <a href="settings.php" class="menu-item">⚙️ <span>Settings</span></a>
            <a href="logout.php" class="menu-item logout-menu">🚪 <span>Logout</span></a>
        </nav>

    </aside>

    <main class="main-content">

        <header class="top-header">

            <div>
                <h1>Users</h1>
                <p>All accounts in the system — residents and personnel.</p>
            </div>

        </header>

        <section class="dashboard-card">

            <div class="card-header">

                <div>
                    <h2>User Information</h2>
                    <p>Residents and administrators registered in CitiFix.</p>
                </div>

            </div>

            <?php if ($error): ?>

                <div class="error-message"><?= htmlspecialchars($error) ?></div>

            <?php elseif (empty($users)): ?>

                <div class="empty-state">
                    <div>👤</div>
                    <p>No users found.</p>
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
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($users as $user):

                            $role   = strtolower($user['role'] ?? 'resident');
                            $active = $user['isActive'] ?? true;
                            $deptId = $user['departmentId'] ?? null;

                        ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($user['fullName'] ?? 'No Name') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($user['email'] ?? 'No Email') ?>
                                </td>

                                <td>
                                    <span class="role-badge">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($role === 'super_admin'): ?>
                                        <em>All departments</em>
                                    <?php elseif ($role === 'resident'): ?>
                                        <em>—</em>
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