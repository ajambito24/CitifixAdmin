<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';
require_once __DIR__ . '/../utils/icons_util.php';
$reports = [];
$reviewQueue = []; // new reports waiting for the super admin to accept / decline

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

        // Reports the super admin hasn't accepted / declined yet stay out of every
        // count and list; they only appear in the review modal.
        $rawStatus = strtolower(trim((string) ($data['status'] ?? 'pending')));
        if ($rawStatus === 'pending' && empty($data['reviewStatus'])) {
            $reviewQueue[] = $data;
            continue;
        }

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

// ---- Review queue (super admin only): newest first, formatted for the modal ----
$reviewItems = [];
if ($IS_SUPER_ADMIN) {
    foreach ($reviewQueue as $r) {
        $dt   = citifix_report_datetime($r['createdAt'] ?? null);
        $ts   = 0;
        $when = 'Unknown';
        if ($dt !== null) {
            $dt   = DateTime::createFromInterface($dt)->setTimezone(new DateTimeZone('Asia/Manila'));
            $ts   = $dt->getTimestamp();
            $when = $dt->format('M d, Y · g:i A');
        }

        $addr = array_filter([
            trim((string) ($r['streetAddress'] ?? '')),
            trim((string) ($r['barangay'] ?? '')),
        ], function ($part) {
            return $part !== '';
        });

        $desc = preg_replace('/\s+/', ' ', trim((string) ($r['description'] ?? '')));
        $desc = mb_strimwidth($desc, 0, 260, '…');

        $reviewItems[] = [
            'id'         => $r['_id'],
            'category'   => $r['category'] ?? 'Unknown',
            'reporter'   => $r['userFullName'] ?? 'Unknown User',
            'department' => department_name($db, $r['departmentId'] ?? null),
            'when'       => $when,
            'ts'         => $ts,
            'desc'       => $desc !== '' ? $desc : 'No description',
            'address'    => implode(', ', $addr),
            'image'      => trim((string) ($r['imageUrl'] ?? '')),
        ];
    }
    usort($reviewItems, function ($a, $b) {
        return $b['ts'] <=> $a['ts'];
    });
}
$needsReview = count($reviewItems);

// ---- Design-only helpers (no data logic) ----
$activePage = 'dashboard';

// [label, value, icon, tint, link to reports.php, red badge count, opens the review modal?]
// Total Reports (super admin): badge = reports waiting for review, click opens the review modal.
$statCards = [
    ['Total Reports', $totalReports, 'clipboard-data',  'bg-blue-100 text-blue-700',     'reports.php?status=' . urlencode('all'),         $needsReview, $IS_SUPER_ADMIN],
    ['Pending',       $pending,      'hourglass-split', 'bg-amber-100 text-amber-700',   'reports.php?status=' . urlencode('pending'),     0, false],
    ['In Progress',   $inProgress,   'tools',           'bg-purple-100 text-purple-700', 'reports.php?status=' . urlencode('in progress'), 0, false],
    ['Resolved',      $resolved,     'check-circle',    'bg-green-100 text-green-700',   'reports.php?status=' . urlencode('resolved'),    0, false],
    ['Rejected',      $rejected,     'x-circle',        'bg-red-100 text-red-700',       'reports.php?status=' . urlencode('rejected'),    0, false],
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

        <!-- STAT CARDS (each one links to the Reports page with its filter) -->
        <section class="grid grid-cols-1 gap-4 xs:grid-cols-2 xl:grid-cols-5 sm:gap-5">
            <?php foreach ($statCards as [$label, $value, $icon, $tint, $href, $badge, $opensReview]): ?>
                <a href="<?= htmlspecialchars($href) ?>"
                   <?php if ($opensReview): ?>data-open-review="1"<?php endif; ?>
                   <?php if ($badge > 0): ?>aria-label="<?= htmlspecialchars($label) ?>, <?= (int) $badge ?> waiting for review"<?php endif; ?>
                   class="relative flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <?php if ($badge > 0): ?>
                        <span id="review-badge" class="absolute -right-2 -top-2 flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-bold leading-none text-white ring-2 ring-white">
                            <?= $badge > 99 ? '99+' : (int) $badge ?>
                        </span>
                    <?php endif; ?>
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl <?= $tint ?>"><?= cf_icon($icon, 'h-6 w-6') ?></span>
                    <div>
                        <p class="text-sm font-medium text-slate-500"><?= $label ?></p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-slate-900"><?= $value ?></h2>
                    </div>
                </a>
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
                            <button type="submit" name="format" value="xlsx" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-brand-700 bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50">
                              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                            Excel
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
                    <div class="flex justify-center text-slate-300"><?= cf_icon('clipboard-data', 'h-12 w-12') ?></div>
                    <p class="mt-2 text-sm text-slate-500">No reports found.</p>
                </div>

            <?php else: ?>

                <?php dash_report_table($reports, $pillMap); ?>

                <div id="dash-none" class="<?= empty($byStatus['pending']) ? '' : 'hidden ' ?>border-t border-slate-100 py-14 text-center">
                    <div class="flex justify-center text-slate-300"><?= cf_icon('search', 'h-12 w-12') ?></div>
                    <p class="mt-2 text-sm text-slate-500">No reports with this status.</p>
                </div>

            <?php endif; ?>
        </section>

<?php if ($IS_SUPER_ADMIN): ?>
        <!-- REVIEW MODAL: accept (-> Pending) or decline (-> Rejected) new reports -->
        <div id="review-modal" class="fixed inset-0 z-[70] hidden items-end justify-center bg-slate-900/60 sm:items-center sm:p-4" onclick="if (event.target === this) closeReviewModal()">
            <div class="flex max-h-[90dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-2xl">
                <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Review New Reports</h3>
                        <p class="text-xs text-slate-500">Accept to move a report to Pending, or decline to mark it Rejected. <span id="review-count" class="font-semibold text-slate-700"><?= $needsReview ?> waiting</span></p>
                    </div>
                    <button type="button" onclick="closeReviewModal()" class="flex h-9 w-9 items-center justify-center rounded-lg text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">×</button>
                </div>

                <div id="review-list" class="min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto">
                    <?php foreach ($reviewItems as $item): ?>
                        <div class="review-item px-5 py-4" data-id="<?= htmlspecialchars($item['id']) ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <strong class="block text-slate-900"><?= htmlspecialchars($item['category']) ?></strong>
                                    <p class="text-xs text-slate-500"><?= htmlspecialchars($item['reporter']) ?> &middot; <?= htmlspecialchars($item['department']) ?></p>
                                </div>
                                <span class="shrink-0 text-xs text-slate-500"><?= htmlspecialchars($item['when']) ?></span>
                            </div>
                            <?php if ($item['image'] !== ''): ?>
                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="Report photo" loading="lazy"
                                     class="mt-3 max-h-56 w-full cursor-pointer rounded-xl border border-slate-200 bg-slate-50 object-cover"
                                     onclick="window.open(this.src, '_blank')">
                            <?php endif; ?>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700"><?= htmlspecialchars($item['desc']) ?></p>
                            <?php if ($item['address'] !== ''): ?>
                                <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($item['address']) ?></p>
                            <?php endif; ?>
                            <div class="mt-3 flex gap-2">
                                <button type="button" data-review="decline" class="flex-1 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 disabled:opacity-50">Decline</button>
                                <button type="button" data-review="accept" class="flex-1 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700 disabled:opacity-50">Accept</button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div id="review-empty" class="<?= $needsReview > 0 ? 'hidden ' : '' ?>py-14 text-center">
                        <div class="flex justify-center text-slate-300"><?= cf_icon('check-circle', 'h-12 w-12') ?></div>
                        <p class="mt-2 text-sm text-slate-500">No reports waiting for review.</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-end rounded-b-2xl border-t border-slate-200 bg-slate-50 px-5 pt-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px));">
                    <button type="button" onclick="closeReviewModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">Close</button>
                </div>
            </div>
        </div>

        <!-- CONFIRM POPUP (accept / decline) -->
        <div id="review-confirm" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </div>
                <h4 id="review-confirm-title" class="mt-4 text-base font-bold text-slate-900"></h4>
                <p id="review-confirm-text" class="mt-1 text-sm text-slate-500"></p>
                <div class="mt-5 flex gap-2">
                    <button type="button" id="review-confirm-no" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">Cancel</button>
                    <button type="button" id="review-confirm-yes" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"></button>
                </div>
            </div>
        </div>

        <!-- RESULT POPUP (success auto-hides, errors stay until closed) -->
        <div id="review-result" role="alert" aria-live="assertive" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                <div id="review-result-icon" class="mx-auto flex h-14 w-14 items-center justify-center rounded-full"></div>
                <p id="review-result-msg" class="mt-4 text-base font-semibold text-slate-900"></p>
                <button type="button" id="review-result-close" class="mt-5 hidden w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-slate-800">Close</button>
            </div>
        </div>

        <script>
        (function () {
            const modal   = document.getElementById("review-modal");
            const card    = document.querySelector("[data-open-review]");
            const list    = document.getElementById("review-list");
            const countEl = document.getElementById("review-count");
            const empty   = document.getElementById("review-empty");

            const confirmBox   = document.getElementById("review-confirm");
            const confirmTitle = document.getElementById("review-confirm-title");
            const confirmText  = document.getElementById("review-confirm-text");
            const confirmYes   = document.getElementById("review-confirm-yes");
            const confirmNo    = document.getElementById("review-confirm-no");

            const resultBox   = document.getElementById("review-result");
            const resultIcon  = document.getElementById("review-result-icon");
            const resultMsg   = document.getElementById("review-result-msg");
            const resultClose = document.getElementById("review-result-close");

            const CHECK = '<svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            const CROSS = '<svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>';

            const CONFIRM = {
                accept: {
                    title: "Accept this report?",
                    text: "Are you sure you want to accept it? It will be moved to Pending.",
                    yes: "Yes, accept",
                    cls: "bg-green-600 hover:bg-green-700"
                },
                decline: {
                    title: "Decline this report?",
                    text: "Are you sure you want to decline it? It will be marked as Rejected.",
                    yes: "Yes, decline",
                    cls: "bg-red-600 hover:bg-red-700"
                }
            };

            let changed   = false;
            let target    = null;   // { item, action } waiting for confirmation
            let busy      = false;
            let hideTimer = null;

            function show(el) { el.classList.remove("hidden"); el.classList.add("flex"); }
            function hide(el) { el.classList.add("hidden"); el.classList.remove("flex"); }

            function openReviewModal() {
                show(modal);
                document.body.style.overflow = "hidden";
            }

            window.closeReviewModal = function () {
                if (modal.classList.contains("hidden")) return;
                hide(modal);
                document.body.style.overflow = "";
                // Reload so the stat cards and lists reflect what was accepted / declined.
                if (changed) window.location.reload();
            };

            function refreshCount() {
                const n = list.querySelectorAll(".review-item").length;
                countEl.textContent = n + " waiting";
                empty.classList.toggle("hidden", n !== 0);

                const badge = document.getElementById("review-badge");
                if (badge) {
                    if (n === 0) badge.classList.add("hidden");
                    else badge.textContent = n > 99 ? "99+" : String(n);
                }
            }

            // ---- confirm popup ----
            function openConfirm(item, action) {
                const c = CONFIRM[action];
                target = { item: item, action: action };
                confirmTitle.textContent = c.title;
                confirmText.textContent  = c.text;
                confirmYes.textContent   = c.yes;
                confirmYes.className     = "flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50 " + c.cls;
                confirmYes.disabled = false;
                confirmNo.disabled  = false;
                show(confirmBox);
            }

            function closeConfirm() {
                if (busy) return;
                hide(confirmBox);
                target = null;
            }

            // ---- result popup ----
            function closeResult() {
                if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
                hide(resultBox);
            }

            function showResult(type, message) {
                if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }

                const ok = type !== "error";
                resultIcon.className = "mx-auto flex h-14 w-14 items-center justify-center rounded-full " +
                    (type === "accept" ? "bg-green-100 text-green-600" : "bg-red-100 text-red-600");
                resultIcon.innerHTML = type === "accept" ? CHECK : CROSS;
                resultMsg.textContent = message;
                resultClose.classList.toggle("hidden", ok);
                show(resultBox);

                if (ok) hideTimer = setTimeout(closeResult, 2000);
            }

            async function submitReview() {
                if (!target || busy) return;
                busy = true;
                confirmYes.disabled = true;
                confirmNo.disabled  = true;

                const item   = target.item;
                const action = target.action;

                try {
                    const fd = new FormData();
                    fd.append("report_id", item.dataset.id);
                    fd.append("action", action);

                    const res = await fetch("review_report.php", {
                        method: "POST",
                        body: fd,
                        credentials: "same-origin",
                        headers: { "Accept": "application/json" }
                    });
                    const data = await res.json();

                    if (!data || data.ok !== true) {
                        throw new Error((data && data.msg) || "Something went wrong.");
                    }

                    item.remove();
                    changed = true;
                    refreshCount();

                    busy = false;
                    closeConfirm();
                    showResult(action, action === "accept"
                        ? "Report accepted successfully."
                        : "Report has been rejected.");
                } catch (err) {
                    busy = false;
                    closeConfirm();
                    showResult("error", err.message || "Something went wrong. Please try again.");
                }
            }

            if (card) {
                card.addEventListener("click", function (e) {
                    e.preventDefault();
                    openReviewModal();
                });
            }

            list.addEventListener("click", function (e) {
                const btn = e.target.closest("[data-review]");
                if (!btn) return;
                openConfirm(btn.closest(".review-item"), btn.dataset.review);
            });

            confirmYes.addEventListener("click", submitReview);
            confirmNo.addEventListener("click", closeConfirm);
            resultClose.addEventListener("click", closeResult);

            confirmBox.addEventListener("click", function (e) { if (e.target === confirmBox) closeConfirm(); });
            resultBox.addEventListener("click", function (e) { if (e.target === resultBox) closeResult(); });

            // Escape closes the top-most popup first
            document.addEventListener("keydown", function (e) {
                if (e.key !== "Escape") return;
                if (!resultBox.classList.contains("hidden")) { closeResult(); return; }
                if (!confirmBox.classList.contains("hidden")) { closeConfirm(); return; }
                window.closeReviewModal();
            });
        })();
        </script>
        <?php endif; ?>

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