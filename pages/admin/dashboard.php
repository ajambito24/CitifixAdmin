<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';

$reports = [];

try {

    // 🔒 Scope: super admin sees all, admins see only reports assigned to them
    $query = $db->collection('reports');
    if (!$IS_SUPER_ADMIN) {
        $query = $query->where('assignedAdminId', '=', $ADMIN_ID);
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
$rejected = 0;

foreach ($reports as $report) {
    $status = strtolower(trim($report['status'] ?? 'pending'));

    if ($status === 'pending') {
        $pending++;
    } elseif ($status === 'in progress' || $status === 'in_progress') {
        $inProgress++;
    } elseif ($status === 'resolved') {
        $resolved++;
    } elseif ($status === 'rejected') {
        $rejected++;
    }
}

usort($reports, function ($a, $b) {
    $ta = $a['createdAt'] ?? null;
    $tb = $b['createdAt'] ?? null;
    if (is_object($ta) && method_exists($ta, 'get')) $ta = $ta->get();
    if (is_object($tb) && method_exists($tb, 'get')) $tb = $tb->get();
    return ($tb <=> $ta);
});

// Group by status: pending is the main list, the rest go in collapsible groups.
$byStatus = [
    'pending'     => [],
    'in progress' => [],
    'resolved'    => [],
    'rejected'    => [],
];

foreach ($reports as $report) {
    $s = strtolower(trim($report['status'] ?? 'pending'));
    if ($s === 'in_progress') $s = 'in progress';
    if (!isset($byStatus[$s])) $s = 'pending';
    $byStatus[$s][] = $report;
}

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

// ---- Design-only helpers (no data logic) ----
$activePage = 'dashboard';

$statCards = [
    ['Total Reports', $totalReports, '📋', 'bg-blue-100'],
    ['Pending',       $pending,      '⏳', 'bg-amber-100'],
    ['In Progress',   $inProgress,   '🔧', 'bg-purple-100'],
    ['Resolved',      $resolved,     '✓',  'bg-green-100 text-green-700 font-bold'],
    ['Rejected',      $rejected,     '✕',  'bg-red-100 text-red-700 font-bold'],
];

$pillMap = [
    'pending'     => 'bg-amber-100 text-amber-800 border-amber-200',
    'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
    'in_progress' => 'bg-blue-100 text-blue-800 border-blue-200',
    'resolved'    => 'bg-green-100 text-green-800 border-green-200',
    'rejected'    => 'bg-red-100 text-red-800 border-red-200',
];

/**
 * Report table used by the dashboard list. Rows carry their status so the
 * dropdown can filter them client-side; only pending shows initially.
 */
function dash_report_table(array $items, array $pillMap): void
{
    global $db;
    $known = ['pending', 'in progress', 'resolved', 'rejected'];
    ?>
    <div class="max-h-[28rem] overflow-auto border-t border-slate-100">
        <table class="w-full min-w-[34rem] text-left text-sm">
            <thead>
                <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                    <th class="sticky top-0 bg-slate-50 px-4 py-3 sm:px-6">ID</th>
                    <th class="sticky top-0 bg-slate-50 px-4 py-3">Issue</th>
                    <th class="sticky top-0 bg-slate-50 px-4 py-3">Department</th>
                    <th class="sticky top-0 bg-slate-50 px-4 py-3 sm:px-6">Status</th>
                </tr>
            </thead>
            <tbody id="dash-tbody" class="divide-y divide-slate-100">
            <?php foreach ($items as $report):
                $status = strtolower(trim($report['status'] ?? 'pending'));
                $pill   = $pillMap[$status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                $filter = $status === 'in_progress' ? 'in progress' : $status;
                if (!in_array($filter, $known, true)) $filter = 'pending';
            ?>
                <tr data-status="<?= htmlspecialchars($filter) ?>" class="transition-colors hover:bg-slate-50"<?= $filter !== 'pending' ? ' style="display:none"' : '' ?>>
                    <td class="px-4 py-3.5 font-mono text-xs text-slate-500 sm:px-6">
                        <?= htmlspecialchars(substr($report['_id'], 0, 8)) ?>
                    </td>
                    <td class="px-4 py-3.5">
                        <strong class="text-slate-800"><?= htmlspecialchars($report['category'] ?? 'Unknown') ?></strong>
                    </td>
                    <td class="px-4 py-3.5 text-slate-600">
                        <?= htmlspecialchars(department_name($db, $report['departmentId'] ?? null)) ?>
                    </td>
                    <td class="px-4 py-3.5 sm:px-6">
                        <span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $pill ?>">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Admin Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>

<body class="bg-slate-50 text-slate-700 antialiased">

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="cf-content">

    <!-- TOP BAR -->
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="flex h-16 items-center justify-between gap-3 px-[var(--su-gutter)]">

            <div class="flex min-w-0 items-center gap-3">
                <button type="button" data-cf-open aria-label="Open menu"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">Admin Dashboard</h1>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">
                        <?php if ($IS_SUPER_ADMIN): ?>
                            All departments
                        <?php else: ?>
                            <?= htmlspecialchars(department_name($db, $ADMIN_DEPT)) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <?php require __DIR__ . '/bell.php'; ?>
                <div class="hidden text-right leading-tight sm:block">
                    <strong class="block text-sm text-slate-800"><?= htmlspecialchars($ADMIN_NAME) ?></strong>
                    <small class="hidden text-xs text-slate-500 md:block">
                        <?= htmlspecialchars($ADMIN_EMAIL) ?>
                        &middot; <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ADMIN_ROLE))) ?>
                        <?php if (!$IS_SUPER_ADMIN): ?>
                            &middot; <?= htmlspecialchars(department_name($db, $ADMIN_DEPT)) ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-700 font-extrabold text-white">
                    <?= htmlspecialchars(strtoupper(substr($ADMIN_NAME, 0, 1))) ?>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[96rem] px-[var(--su-gutter)] py-6 sm:py-8">

        <!-- STAT CARDS -->
        <section class="grid grid-cols-1 gap-4 xs:grid-cols-2 xl:grid-cols-5 sm:gap-5">
            <?php foreach ($statCards as [$label, $value, $icon, $tint]): ?>
                <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl <?= $tint ?>"><?= $icon ?></span>
                    <div>
                        <p class="text-sm font-medium text-slate-500"><?= $label ?></p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-slate-900"><?= $value ?></h2>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- REPORTS OVERVIEW -->
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:mt-8 sm:p-6">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Reports Overview</h2>
                    <p class="text-xs text-slate-500 sm:text-sm">
                        Reports submitted &middot; <?= htmlspecialchars($allowedRanges[$selectedRange]) ?>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <?php foreach ($allowedRanges as $rangeKey => $rangeLabel): ?>
                        <a href="?range=<?= urlencode($rangeKey) ?>"
                           class="<?= $selectedRange === $rangeKey
                               ? 'bg-brand-700 text-white'
                               : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> rounded-full px-3 py-1.5 text-xs font-semibold transition-colors sm:text-sm">
                            <?= htmlspecialchars($rangeLabel) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="relative h-56 w-full sm:h-72 md:h-80 3xl:h-96">
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

        <!-- EXPORT REPORT -->
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:mt-8 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2zM13 3v5a1 1 0 001 1h5M9 13h6M9 17h4"/></svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Export Report</h2>
                        <p class="text-xs text-slate-500 sm:text-sm">
                            Download a Word or PDF document of
                            <?= $IS_SUPER_ADMIN ? 'all reports' : 'the reports assigned to you' ?>
                            for the selected period.
                        </p>
                    </div>
                </div>

                <form method="GET" action="export_reports.php" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select name="range" aria-label="Export period" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="day">Today</option>
                        <option value="week" selected>This Week</option>
                        <option value="6weeks">Last 6 Weeks</option>
                        <option value="month">This Month</option>
                        <option value="all">All Time</option>
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" name="format" value="docx" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                            Word
                        </button>
                        <button type="submit" name="format" value="pdf" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-brand-700 bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                            PDF
                        </button>
                    </div>
                </form>

            </div>
        </section>

        <!-- REPORTS LIST (status filter, default: pending) -->
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm sm:mt-8">
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-6">
                <div>
                    <h2 id="dash-title" class="text-lg font-bold text-slate-900 sm:text-xl">Pending Reports</h2>
                    <p class="text-xs text-slate-500 sm:text-sm">
                        <?= $IS_SUPER_ADMIN ? 'Latest submitted community issues' : 'Reports assigned to you' ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="dash-status" aria-label="Report status" onchange="dashFilter(this.value)" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="pending" selected>Pending (<?= $pending ?>)</option>
                        <option value="in progress">In Progress (<?= $inProgress ?>)</option>
                        <option value="resolved">Resolved (<?= $resolved ?>)</option>
                        <option value="rejected">Rejected (<?= $rejected ?>)</option>
                        <option value="all">All (<?= $totalReports ?>)</option>
                    </select>
                    <a id="dash-viewall" href="reports.php?status=pending" class="shrink-0 rounded-lg bg-brand-50 px-3.5 py-2 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-100">
                        View all
                    </a>
                </div>
            </div>

            <?php if (empty($reports)): ?>

                <div class="border-t border-slate-100 py-14 text-center">
                    <div class="text-4xl">📋</div>
                    <p class="mt-2 text-sm text-slate-500">No reports found.</p>
                </div>

            <?php else: ?>

                <?php dash_report_table($reports, $pillMap); ?>

                <div id="dash-none" class="<?= empty($byStatus['pending']) ? '' : 'hidden ' ?>border-t border-slate-100 py-14 text-center">
                    <div class="text-4xl">🔍</div>
                    <p class="mt-2 text-sm text-slate-500">No reports with this status.</p>
                </div>

            <?php endif; ?>
        </section>

        <script>
        const DASH_TITLES = {
            "pending": "Pending Reports",
            "in progress": "In Progress Reports",
            "resolved": "Resolved Reports",
            "rejected": "Rejected Reports",
            "all": "All Reports"
        };

        function dashFilter(value) {
            const rows = document.querySelectorAll("#dash-tbody tr");
            let visible = 0;

            rows.forEach(function (row) {
                const show = value === "all" || row.dataset.status === value;
                row.style.display = show ? "" : "none";
                if (show) visible++;
            });

            const none = document.getElementById("dash-none");
            if (none) none.classList.toggle("hidden", visible !== 0);

            const title = document.getElementById("dash-title");
            if (title) title.textContent = DASH_TITLES[value] || "Reports";

            const link = document.getElementById("dash-viewall");
            if (link) link.href = "reports.php?status=" + encodeURIComponent(value);
        }
        </script>

    </main>
</div>

</body>
</html>