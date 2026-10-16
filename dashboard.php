<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/firebase.php';
require_once __DIR__ . '/config/department_map.php';

$reports = [];

try {

    // 🔒 Department scope: super admin sees all, others see own dept only
    $query = $db->collection('reports');
    if (!$IS_SUPER_ADMIN) {
        $query = $query->where('departmentId', '=', $ADMIN_DEPT);
    }

    $documents = $query->documents();

    foreach ($documents as $document) {

        if (!$document->exists()) {
            continue;
        }

        $data = $document->data();
        $data['_id'] = $document->id();
        $reports[] = $data;
    }

} catch (Exception $e) {
    $firebaseError = $e->getMessage();
}

$totalReports = count($reports);

$pending = 0;
$inProgress = 0;
$resolved = 0;

foreach ($reports as $report) {
    $status = strtolower(trim($report['status'] ?? 'pending'));

    if ($status === 'pending') {
        $pending++;
    } elseif ($status === 'in progress' || $status === 'in_progress') {
        $inProgress++;
    } elseif ($status === 'resolved') {
        $resolved++;
    }
}

usort($reports, function ($a, $b) {
    $ta = $a['createdAt'] ?? null;
    $tb = $b['createdAt'] ?? null;
    if (is_object($ta) && method_exists($ta, 'get')) $ta = $ta->get();
    if (is_object($tb) && method_exists($tb, 'get')) $tb = $tb->get();
    return ($tb <=> $ta);
});

/**
 * ---- Reports Overview (trend) data ----
 */
function citifix_report_datetime($createdAt)
{
    if ($createdAt === null) return null;
    if (is_object($createdAt) && method_exists($createdAt, 'get')) return $createdAt->get();
    if ($createdAt instanceof DateTime || $createdAt instanceof DateTimeImmutable) return $createdAt;
    if (is_string($createdAt)) {
        $ts = strtotime($createdAt);
        return $ts !== false ? (new DateTime())->setTimestamp($ts) : null;
    }
    return null;
}

$allowedRanges = [
    'week'     => 'This Week',
    'month'    => 'This Month',
    '3months'  => '3 Months',
    '6months'  => '6 Months',
    '12months' => '12 Months',
];

$selectedRange = $_GET['range'] ?? 'week';
if (!array_key_exists($selectedRange, $allowedRanges)) {
    $selectedRange = 'week';
}

$rangeConfig = [
    'week'     => ['unit' => 'day',   'count' => 7],
    'month'    => ['unit' => 'day',   'count' => 30],
    '3months'  => ['unit' => 'week',  'count' => 13],
    '6months'  => ['unit' => 'month', 'count' => 6],
    '12months' => ['unit' => 'month', 'count' => 12],
];

$unit  = $rangeConfig[$selectedRange]['unit'];
$count = $rangeConfig[$selectedRange]['count'];

$bucketCounts = [];
$chartLabels  = [];

for ($i = $count - 1; $i >= 0; $i--) {

    if ($unit === 'day') {
        $key = date('Y-m-d', strtotime("-{$i} days"));
        $bucketCounts[$key] = 0;
        $chartLabels[] = date('M j', strtotime($key));
    } elseif ($unit === 'week') {
        $weekStart = strtotime("monday this week -{$i} weeks");
        $key = date('o-W', $weekStart);
        $bucketCounts[$key] = 0;
        $chartLabels[] = date('M j', $weekStart);
    } elseif ($unit === 'month') {
        $monthStart = strtotime("first day of -{$i} months");
        $key = date('Y-m', $monthStart);
        $bucketCounts[$key] = 0;
        $chartLabels[] = date('M Y', $monthStart);
    }
}

foreach ($reports as $report) {
    $dt = citifix_report_datetime($report['createdAt'] ?? null);
    if ($dt === null) continue;

    if ($unit === 'day')       $key = $dt->format('Y-m-d');
    elseif ($unit === 'week')  $key = $dt->format('o-W');
    else                       $key = $dt->format('Y-m');

    if (array_key_exists($key, $bucketCounts)) {
        $bucketCounts[$key]++;
    }
}

$chartValues = array_values($bucketCounts);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>CitiFix Admin Dashboard</title>

<link
    rel="stylesheet"
    href="css/style.css"
>

<script src="https://cdn.tailwindcss.com"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

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

            <a
                href="dashboard.php"
                class="menu-item active"
            >
                🏠
                <span>Home</span>
            </a>

            <a
                href="reports.php"
                class="menu-item"
            >
                📋
                <span>Reports</span>
            </a>

            <a
                href="map.php"
                class="menu-item"
            >
                🗺️
                <span>Map View</span>
            </a>

            <a
                href="personnel.php"
                class="menu-item"
            >
                👥
                <span>Personnel</span>
            </a>

            <a
                href="users.php"
                class="menu-item"
            >
                👤
                <span>Users</span>
            </a>

            <a
                href="settings.php"
                class="menu-item"
            >
                ⚙️
                <span>Settings</span>
            </a>

            <a
                href="logout.php"
                class="menu-item logout-menu"
            >
                🚪
                <span>Logout</span>
            </a>

        </nav>

    </aside>

    <!-- MAIN -->

    <main class="main-content">

        <header class="top-header">

            <div>

                <h1>
                    Admin Dashboard
                </h1>

                <p>
                    <?php if ($IS_SUPER_ADMIN): ?>
                        All departments
                    <?php else: ?>
                        <?= htmlspecialchars(department_name($db, $ADMIN_DEPT)) ?>
                    <?php endif; ?>
                </p>

            </div>

            <div class="admin-user">

                <div class="admin-avatar">
                    <?= htmlspecialchars(strtoupper(substr($ADMIN_NAME, 0, 1))) ?>
                </div>

                <div>

                    <strong>
                        <?= htmlspecialchars($ADMIN_NAME) ?>
                    </strong>

                    <small>
                        <?= htmlspecialchars($ADMIN_EMAIL) ?>
                        &middot; <?= htmlspecialchars(ucfirst(str_replace('_',' ', $ADMIN_ROLE))) ?>
                        <?php if (!$IS_SUPER_ADMIN): ?>
                            &middot; <?= htmlspecialchars(department_name($db, $ADMIN_DEPT)) ?>
                        <?php endif; ?>
                    </small>

                </div>

            </div>

        </header>

        <!-- STAT CARDS -->

        <section class="stats-grid">

            <div class="stat-card">

                <div class="stat-icon blue">
                    📋
                </div>

                <div>

                    <p>Total Reports</p>

                    <h2>
                        <?= $totalReports ?>
                    </h2>

                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon orange">
                    ⏳
                </div>

                <div>

                    <p>Pending</p>

                    <h2>
                        <?= $pending ?>
                    </h2>

                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon purple">
                    🔧
                </div>

                <div>

                    <p>In Progress</p>

                    <h2>
                        <?= $inProgress ?>
                    </h2>

                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon green">
                    ✓
                </div>

                <div>

                    <p>Resolved</p>

                    <h2>
                        <?= $resolved ?>
                    </h2>

                </div>

            </div>

        </section>

        <!-- REPORTS OVERVIEW (ANALYTICS CHART) -->

        <section class="w-full bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6 mb-6">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">

                <div>

                    <h2 class="text-lg sm:text-xl font-bold text-gray-900">
                        Reports Overview
                    </h2>

                    <p class="text-xs sm:text-sm text-gray-500">
                        Reports submitted &middot; <?= htmlspecialchars($allowedRanges[$selectedRange]) ?>
                    </p>

                </div>

                <div class="flex flex-wrap gap-2">

                    <?php foreach ($allowedRanges as $rangeKey => $rangeLabel): ?>

                        <a
                            href="?range=<?= urlencode($rangeKey) ?>"
                            class="<?= $selectedRange === $rangeKey
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?> text-xs sm:text-sm font-medium px-3 py-1.5 rounded-full transition-colors"
                        >
                            <?= htmlspecialchars($rangeLabel) ?>
                        </a>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="relative w-full h-56 sm:h-72 md:h-80">

                <canvas id="reportsOverviewChart"></canvas>

            </div>

        </section>

        <script>
        (function () {
            const ctx = document.getElementById('reportsOverviewChart');

            if (!ctx || typeof Chart === 'undefined') {
                return;
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chartLabels) ?>,
                    datasets: [{
                        label: 'Reports',
                        data: <?= json_encode($chartValues) ?>,
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29, 78, 216, 0.08)',
                        pointBackgroundColor: '#1d4ed8',
                        pointBorderColor: '#1d4ed8',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 10,
                            titleFont: { weight: 'bold' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#6b7280'
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#6b7280',
                                font: { weight: 'bold' }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        })();
        </script>

        <!-- RECENT REPORTS -->

        <section class="dashboard-card">

            <div class="card-header">

                <div>

                    <h2>
                        Recent Reports
                    </h2>

                    <p>
                        Latest submitted community issues
                    </p>

                </div>

                <a
                    href="reports.php"
                    class="view-all"
                >
                    View All
                </a>

            </div>

            <?php if (empty($reports)): ?>

                <div class="empty-state">

                    <div>
                        📋
                    </div>

                    <p>
                        No reports found.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>ID</th>
                                <th>Issue</th>
                                <th>Department</th>
                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php

                        $displayReports =
                            array_slice($reports, 0, 5);

                        foreach ($displayReports as $report):

                            $status =
                                strtolower(
                                    $report['status'] ?? 'pending'
                                );

                            $statusClass =
                                str_replace(
                                    ' ',
                                    '-',
                                    $status
                                );

                        ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        substr(
                                            $report['_id'],
                                            0,
                                            8
                                        )
                                    ) ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $report['category'] ??
                                            'Unknown'
                                        ) ?>
                                    </strong>

                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        department_name(
                                            $db,
                                            $report['departmentId'] ?? null
                                        )
                                    ) ?>
                                </td>

                                <td>

                                    <span
                                        class="status <?= htmlspecialchars($statusClass) ?>"
                                    >
                                        <?= htmlspecialchars(
                                            ucfirst($status)
                                        ) ?>
                                    </span>

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