<?php

require_once __DIR__ . '/includes/auth.php';

// 🔒 Super admin only
if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can access settings.');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>CitiFix Settings</title>

<link
    rel="stylesheet"
    href="css/style.css"
>

</head>

<body>

<div class="admin-layout">

    <aside class="sidebar">

        <div class="sidebar-logo">

            <img
                src="images/logo.png"
                alt="CitiFix Manila"
                class="sidebar-logo-img"
            >

            <div class="sidebar-title">
                CITIFIX
            </div>

            <div class="sidebar-subtitle">
                MANILA
            </div>

        </div>

        <nav class="sidebar-menu">

            <a href="dashboard.php" class="menu-item">
                🏠 <span>Home</span>
            </a>

            <a href="reports.php" class="menu-item">
                📋 <span>Reports</span>
            </a>

            <a href="map.php" class="menu-item">
                🗺️ <span>Map View</span>
            </a>

            <?php if ($IS_SUPER_ADMIN): ?>
                <a href="personnel.php" class="menu-item">
                    👥 <span>Personnel</span>
                </a>

                <a href="users.php" class="menu-item">
                    👤 <span>Users</span>
                </a>

                <a href="settings.php" class="menu-item active">
                    ⚙️ <span>Settings</span>
                </a>
            <?php endif; ?>

            <a href="logout.php" class="menu-item logout-menu">
                🚪 <span>Logout</span>
            </a>

        </nav>

    </aside>

    <main class="main-content">

        <header class="top-header">

            <div>

                <h1>
                    Settings
                </h1>

                <p>
                    CitiFix administrator settings.
                </p>

            </div>

        </header>

        <section class="dashboard-card">

            <h2>
                System Information
            </h2>

            <div class="settings-list">

                <div class="setting-row">

                    <span>
                        System Name
                    </span>

                    <strong>
                        CitiFix Manila
                    </strong>

                </div>

                <div class="setting-row">

                    <span>
                        Target Area
                    </span>

                    <strong>
                        District 4 - Sampaloc, Manila
                    </strong>

                </div>

                <div class="setting-row">

                    <span>
                        Database
                    </span>

                    <strong>
                        Firebase Firestore
                    </strong>

                </div>

                <div class="setting-row">

                    <span>
                        Administrator
                    </span>

                    <strong>
                        <?= htmlspecialchars($ADMIN_EMAIL) ?>
                    </strong>

                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>