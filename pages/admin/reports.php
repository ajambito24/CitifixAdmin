<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';
require_once __DIR__ . '/../../includes/report_range.php';

/**
 * Safely converts Firebase/Firestore date values into a Manila DateTime object.
 */
function citifix_parse_firebase_date($value): ?\DateTime
{
    if ($value === null) {
        return null;
    }

    $dt = null;

    if ($value instanceof \Google\Cloud\Core\Timestamp) {
       $dt = \DateTime::createFromInterface($value->get());
    } elseif ($value instanceof \DateTimeImmutable) {
        $dt = \DateTime::createFromImmutable($value);
    } elseif ($value instanceof \DateTime) {
        $dt = $value;
    } elseif (is_numeric($value)) {
        $ts = (int) $value;
        if ($ts > 9999999999) {
            $ts = (int) ($ts / 1000);
        }
        $dt = (new \DateTime())->setTimestamp($ts);
    } elseif (is_string($value)) {
        $ts = strtotime($value);
        if ($ts !== false) {
            $dt = (new \DateTime())->setTimestamp($ts);
        }
    }

    if ($dt !== null) {
        $dt->setTimezone(new \DateTimeZone('Asia/Manila'));
        return $dt;
    }

    return null;
}

$reports = [];
$error = '';

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

        // ---- Format createdAt safely ----
        $createdAtRaw = $data['createdAt'] ?? null;
        $createdAtFormatted = 'Unknown';

        $dt = citifix_parse_firebase_date($createdAtRaw);
        if ($dt !== null) {
            $createdAtFormatted = $dt->format('M d, Y · g:i A');
        }

        // ---- Format statusHistory ----
        $historyRaw = $data['statusHistory'] ?? [];
        $statusHistory = [];

        if (is_array($historyRaw)) {
            foreach ($historyRaw as $entry) {
                $entryStatus = strtolower($entry['status'] ?? '');
                $entryAt = $entry['at'] ?? null;
                $entryAtFormatted = '';

                $dtEntry = citifix_parse_firebase_date($entryAt);
                if ($dtEntry !== null) {
                    $entryAtFormatted = $dtEntry->format('M d, Y · g:i A');
                }

                $statusHistory[] = [
                    'status' => $entryStatus,
                    'at'     => $entryAtFormatted,
                ];
            }
        }

        $reports[] = [
            'id'                 => $document->id(),
            'category'           => $data['category']     ?? 'Unknown',
            'description'        => $data['description']  ?? 'No description',
            'status'             => $data['status']       ?? 'pending',
            'streetAddress'      => $data['streetAddress'] ?? '',
            'barangay'           => $data['barangay']      ?? '',
            'latitude'           => $data['latitude']     ?? null,
            'longitude'          => $data['longitude']    ?? null,
            'userFullName'       => $data['userFullName'] ?? 'Unknown User',
            'userEmail'          => $data['userEmail']    ?? '',
            'imageUrl'           => $data['imageUrl']     ?? '',
            'adminNotes'         => $data['adminNotes']   ?? '',
            'departmentId'       => $data['departmentId'] ?? null,
            'departmentName'     => department_name($db, $data['departmentId'] ?? null),
            'assignedAdminId'    => $data['assignedAdminId']   ?? '',
            'assignedAdminName'  => $data['assignedAdminName'] ?? '',
            'createdAtFormatted' => $createdAtFormatted,
            'createdAtTs'        => $dt !== null ? $dt->getTimestamp() : 0,
            'statusHistory'      => $statusHistory,
        ];
    }

    // Sort newest first
    usort($reports, function ($a, $b) {
        return $b['createdAtTs'] <=> $a['createdAtTs'];
    });

} catch (Exception $e) {
    $error = $e->getMessage();
}

// ---- Period filter (drives the lists and the export) ----
$allowedRanges = citifix_report_ranges();
$selectedRange = $_GET['range'] ?? 'all';
if (!array_key_exists($selectedRange, $allowedRanges)) {
    $selectedRange = 'all';
}

$bounds = citifix_range_bounds($selectedRange);
if ($bounds['startTs'] !== null) {
    $reports = array_values(array_filter($reports, function ($r) use ($bounds) {
        return $r['createdAtTs'] >= $bounds['startTs'] && $r['createdAtTs'] <= $bounds['endTs'];
    }));
}

// ---- Active admins for the "Assigned To" dropdown (super admin only) ----
$adminOptions = [];
if ($IS_SUPER_ADMIN) {
    try {
        foreach ($db->collection('admins')->documents() as $a) {
            if (!$a->exists()) continue;
            $ad = $a->data();
            if (empty($ad['isActive'])) continue;
            $adminOptions[$a->id()] = [
                'name' => $ad['fullName'] ?? ($ad['email'] ?? $a->id()),
                'dept' => department_name($db, $ad['departmentId'] ?? null),
            ];
        }
        uasort($adminOptions, function ($x, $y) {
            return strcasecmp($x['name'], $y['name']);
        });
    } catch (Exception $e) {
        // dropdown simply stays empty
    }
}

// ---- Session flash (one-time) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Design-only helpers (no data logic) ----
$activePage = 'reports';

$statusClasses = [
    'pending'     => 'bg-amber-100 text-amber-800 border-amber-200',
    'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
    'in_progress' => 'bg-blue-100 text-blue-800 border-blue-200',
    'resolved'    => 'bg-green-100 text-green-800 border-green-200',
    'rejected'    => 'bg-red-100 text-red-800 border-red-200',
];

// Status filter (default: pending). Filtering happens client-side; this only sets the initial state.
$statusOptions = [
    'pending'     => 'Pending',
    'in progress' => 'In Progress',
    'resolved'    => 'Resolved',
    'rejected'    => 'Rejected',
    'all'         => 'All',
];

$selectedStatus = strtolower(trim($_GET['status'] ?? 'pending'));
if ($selectedStatus === 'in_progress') $selectedStatus = 'in progress';
if (!array_key_exists($selectedStatus, $statusOptions)) {
    $selectedStatus = 'pending';
}

$rows = [];
$statusCounts = ['pending' => 0, 'in progress' => 0, 'resolved' => 0, 'rejected' => 0];

foreach ($reports as $report) {
    $status = strtolower($report['status']);
    $filter = ($status === 'in_progress') ? 'in progress' : $status;
    if (!array_key_exists($filter, $statusCounts)) {
        $filter = 'pending';
    }
    $statusCounts[$filter]++;

    $rows[] = [
        'report' => $report,
        'pill'   => $statusClasses[$status] ?? 'bg-slate-100 text-slate-700 border-slate-200',
        'filter' => $filter,
        'json'   => htmlspecialchars(json_encode($report), ENT_QUOTES, 'UTF-8'),
    ];
}

$countAll = count($rows);
$visibleCount = $selectedStatus === 'all' ? $countAll : $statusCounts[$selectedStatus];

$statusTitles = [
    'pending'     => 'Pending Reports',
    'in progress' => 'In Progress Reports',
    'resolved'    => 'Resolved Reports',
    'rejected'    => 'Rejected Reports',
    'all'         => 'All Reports',
];

/**
 * Renders one set of reports: a table (sm and up) and cards (below sm).
 */
function render_report_rows(array $items, string $emptyText = 'No reports.'): void
{
    global $db, $IS_SUPER_ADMIN, $selectedStatus;

    if (empty($items)) {
        echo '<div class="border-t border-slate-100 py-10 text-center text-sm text-slate-500">' . htmlspecialchars($emptyText) . '</div>';
        return;
    }
    ?>
    <!-- TABLE (sm and up) -->
    <div class="hidden overflow-x-auto border-t border-slate-100 sm:block">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead>
                <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3 sm:px-6">ID</th>
                    <th class="px-4 py-3">Issue</th>
                    <th class="px-4 py-3">Department</th>
                    <?php if ($IS_SUPER_ADMIN): ?>
                        <th class="px-4 py-3">Assigned To</th>
                    <?php endif; ?>
                    <th class="px-4 py-3">Reporter</th>
                    <th class="px-4 py-3">
                        <button type="button" onclick="toggleDateSort()" class="date-sort-btn inline-flex items-center gap-1 font-semibold text-slate-500 transition-colors hover:text-slate-800" title="Sort by date">
                            Date
                            <svg class="date-sort-icon h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0l-5-5m5 5l5-5"/></svg>
                            <span class="sr-only date-sort-label">Newest first</span>
                        </button>
                    </th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right sm:px-6">Action</th>
                </tr>
            </thead>
            <tbody data-sort-list class="divide-y divide-slate-100">
            <?php foreach ($items as $row): $report = $row['report']; ?>
                <tr data-id="<?= htmlspecialchars($report['id']) ?>" data-ts="<?= (int) $report['createdAtTs'] ?>" data-status="<?= htmlspecialchars($row['filter']) ?>" class="report-row transition-colors hover:bg-slate-50"<?= ($selectedStatus !== 'all' && $row['filter'] !== $selectedStatus) ? ' style="display:none"' : '' ?>>
                    <td class="px-4 py-3.5 font-mono text-xs text-slate-500 sm:px-6"><?= htmlspecialchars(substr($report['id'], 0, 8)) ?></td>
                    <td class="px-4 py-3.5"><strong class="text-slate-800"><?= htmlspecialchars($report['category']) ?></strong></td>
                    <td class="px-4 py-3.5 text-xs text-slate-600"><?= htmlspecialchars(department_name($db, $report['departmentId'] ?? null)) ?></td>
                    <?php if ($IS_SUPER_ADMIN): ?>
                        <td class="px-4 py-3.5 text-xs text-slate-600">
                            <?php if ($report['assignedAdminName'] !== ''): ?>
                                <?= htmlspecialchars($report['assignedAdminName']) ?>
                            <?php else: ?>
                                <span class="text-slate-400">Unassigned</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td class="px-4 py-3.5 text-slate-700"><?= htmlspecialchars($report['userFullName']) ?></td>
                    <td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-500"><?= htmlspecialchars($report['createdAtFormatted']) ?></td>
                    <td class="px-4 py-3.5">
                        <span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize <?= $row['pill'] ?>">
                            <?= htmlspecialchars($report['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-right sm:px-6">
                        <?php if (can_edit_reports()): ?>
                            <button type="button" onclick='openReportModal(<?= $row['json'] ?>)' class="inline-flex items-center rounded-lg bg-brand-700 px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-800">Edit</button>
                        <?php else: ?>
                            <button type="button" onclick='openReportModal(<?= $row['json'] ?>)' class="inline-flex items-center rounded-lg bg-slate-100 px-3.5 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200">View</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- CARDS (below sm) -->
    <div data-sort-list class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
        <?php foreach ($items as $row): $report = $row['report']; ?>
            <div data-id="<?= htmlspecialchars($report['id']) ?>" data-ts="<?= (int) $report['createdAtTs'] ?>" data-status="<?= htmlspecialchars($row['filter']) ?>" class="report-row p-4"<?= ($selectedStatus !== 'all' && $row['filter'] !== $selectedStatus) ? ' style="display:none"' : '' ?>>
                <div class="flex items-start justify-between gap-3">
                    <strong class="min-w-0 truncate text-slate-900"><?= htmlspecialchars($report['category']) ?></strong>
                    <span class="inline-block shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize <?= $row['pill'] ?>">
                        <?= htmlspecialchars($report['status']) ?>
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-600">
                    <?= htmlspecialchars(department_name($db, $report['departmentId'] ?? null)) ?>
                    &middot; <?= htmlspecialchars($report['userFullName']) ?>
                </p>
                <?php if ($IS_SUPER_ADMIN): ?>
                    <p class="mt-1 text-xs text-slate-600">
                        Assigned to:
                        <?php if ($report['assignedAdminName'] !== ''): ?>
                            <strong><?= htmlspecialchars($report['assignedAdminName']) ?></strong>
                        <?php else: ?>
                            <span class="text-slate-400">Unassigned</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <p class="mt-1 text-xs text-slate-500">
                    <?= htmlspecialchars($report['createdAtFormatted']) ?>
                    &middot; <span class="font-mono"><?= htmlspecialchars(substr($report['id'], 0, 8)) ?></span>
                </p>
                <?php if (can_edit_reports()): ?>
                    <button type="button" onclick='openReportModal(<?= $row['json'] ?>)' class="mt-3 w-full rounded-lg bg-brand-700 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800">Edit</button>
                <?php else: ?>
                    <button type="button" onclick='openReportModal(<?= $row['json'] ?>)' class="mt-3 w-full rounded-lg bg-slate-100 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-200">View</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Reports</title>
<style>
.timeline-entry { position: relative; padding-left: 28px; padding-bottom: 14px; }
.timeline-entry:last-child { padding-bottom: 0; }
.timeline-entry::before { content: ""; position: absolute; left: 8px; top: 16px; bottom: -2px; width: 2px; background: #e2e8f0; }
.timeline-entry:last-child::before { display: none; }
.timeline-dot { position: absolute; left: 0; top: 4px; width: 18px; height: 18px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; }

/* Flash popup (success / error after saving a report) */
@keyframes flash-pop-in {
    from { opacity: 0; transform: scale(0.92) translateY(8px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
#flash-popup { transition: opacity 0.3s ease; }
#flash-popup.flash-hide { opacity: 0; pointer-events: none; }
#flash-popup .flash-card { animation: flash-pop-in 0.25s ease-out; }
</style>
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
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">Reports</h1>
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

        <?php if ($flash !== null): ?>
            <?php
                $flashType    = $flash['type'] ?? 'success';
                $flashMsg     = $flash['msg']  ?? '';
                $flashSuccess = $flashType === 'success';
            ?>
            <!-- FLASH POPUP: success auto-hides after ~2s, errors stay until closed -->
            <div id="flash-popup" role="alert" aria-live="assertive" data-type="<?= $flashSuccess ? 'success' : 'error' ?>"
                 class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/40 p-4"
                 onclick="if (event.target === this) closeFlashPopup()">
                <div class="flash-card w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full <?= $flashSuccess ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?>">
                        <?php if ($flashSuccess): ?>
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        <?php endif; ?>
                    </div>
                    <p class="mt-4 text-base font-semibold text-slate-900"><?= htmlspecialchars($flashMsg) ?></p>
                    <?php if (!$flashSuccess): ?>
                        <button type="button" onclick="closeFlashPopup()" class="mt-5 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-slate-800">Close</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- PENDING REPORTS (main list) -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-6">
                <div>
                    <h2 id="status-title" class="text-lg font-bold text-slate-900 sm:text-xl"><?= htmlspecialchars($statusTitles[$selectedStatus]) ?></h2>
                    <p class="text-xs text-slate-500 sm:text-sm">
                        <?= $IS_SUPER_ADMIN ? 'Reports submitted by CitiFix residents' : 'Reports assigned to you' ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="status-select" aria-label="Report status" onchange="applyStatusFilter(this.value)" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <?php foreach ($statusOptions as $sk => $sl): ?>
                            <?php $n = $sk === 'all' ? $countAll : $statusCounts[$sk]; ?>
                            <option value="<?= htmlspecialchars($sk) ?>" <?= $selectedStatus === $sk ? 'selected' : '' ?>><?= htmlspecialchars($sl) ?> (<?= $n ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <select id="period-select" aria-label="Report period" onchange="window.location.href = '?range=' + encodeURIComponent(this.value) + '&status=' + encodeURIComponent(document.getElementById('status-select').value)" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <?php foreach ($allowedRanges as $rk => $rl): ?>
                            <option value="<?= htmlspecialchars($rk) ?>" <?= $selectedRange === $rk ? 'selected' : '' ?>><?= htmlspecialchars($rl) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="relative" id="export-dd">
                        <button type="button" id="export-btn" aria-haspopup="menu" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                            Export
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="export-menu" role="menu" class="absolute right-0 z-30 mt-1 hidden w-48 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                            <a id="export-docx" role="menuitem" href="export_reports.php?range=<?= urlencode($selectedRange) ?>&amp;format=docx&amp;status=all" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-blue-100 text-[10px] font-bold text-blue-700">W</span>
                                Word (.docx)
                            </a>
                            <a id="export-pdf" role="menuitem" href="export_reports.php?range=<?= urlencode($selectedRange) ?>&amp;format=pdf&amp;status=all" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-red-100 text-[10px] font-bold text-red-700">PDF</span>
                                PDF (.pdf)
                            </a>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-sm text-brand-700">
                        <span class="inline-block h-2 w-2 rounded-full bg-brand-500"></span>
                        <strong id="visible-count"><?= $visibleCount ?></strong>
                        <span id="visible-label"><?= $visibleCount === 1 ? 'report' : 'reports' ?></span>
                    </div>
                </div>
            </div>

            <!-- Mobile date sort -->
            <div class="flex justify-end border-t border-slate-100 px-4 py-2.5 sm:hidden">
                <button type="button" onclick="toggleDateSort()" class="date-sort-btn inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-200">
                    <svg class="date-sort-icon h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0l-5-5m5 5l5-5"/></svg>
                    <span class="date-sort-label">Newest first</span>
                </button>
            </div>

            <div id="reports-list">
                <?php render_report_rows($rows, $selectedRange === 'all' ? 'No reports available.' : 'No reports for this period.'); ?>
            </div>
            <div id="no-results" class="<?= ($visibleCount === 0 && !empty($rows)) ? '' : 'hidden ' ?>border-t border-slate-100 py-14 text-center">
                <div class="text-4xl">🔍</div>
                <p class="mt-2 text-sm text-slate-500">No reports with this status.</p>
            </div>
        </section>

    </main>
</div>

<!-- REPORT MODAL: bottom sheet on mobile, centered dialog on sm+ -->
<div id="report-modal" class="fixed inset-0 z-[70] hidden items-end justify-center bg-slate-900/60 sm:items-center sm:p-4" onclick="if (event.target === this) closeReportModal()">
    <div class="flex max-h-[90dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-2xl">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Report Details</h3>
                <p class="text-xs text-slate-500">Update status and add admin notes.</p>
            </div>
            <button type="button" onclick="closeReportModal()" class="flex h-9 w-9 items-center justify-center rounded-lg text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">×</button>
        </div>
        <form method="POST" action="update_status.php" class="flex min-h-0 flex-1 flex-col" id="report-form">
            <input type="hidden" name="report_id" id="modal-report-id">
            <input type="hidden" name="status" id="modal-status-hidden">
            <div class="modal-body min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <div id="modal-image-wrap" class="mb-4" style="display:none;">
                    <img id="modal-image" src="" alt="Report photo" class="max-h-64 w-full cursor-pointer rounded-xl border border-slate-200 object-cover" onclick="window.open(this.src, '_blank')">
                    <p class="mt-1 text-xs text-slate-400">Click image to open full size</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Category</label>
                        <div id="modal-category" class="font-medium text-slate-800"></div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Department</label>
                        <div id="modal-department" class="text-slate-800"></div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Reported By</label>
                        <div id="modal-reporter" class="text-slate-800"></div>
                        <div id="modal-email" class="break-all text-xs text-slate-500"></div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Date &amp; Time</label>
                        <div id="modal-date" class="text-slate-800"></div>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Description</label>
                    <div id="modal-description" class="whitespace-pre-wrap text-sm text-slate-700"></div>
                </div>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Address</label>
                    <div id="modal-address" class="text-sm text-slate-700"></div>
                </div>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Location</label>
                    <div id="modal-coords" class="font-mono text-xs text-slate-700"></div>
                </div>
                <?php if ($IS_SUPER_ADMIN): ?>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Assigned To</label>
                    <div class="relative" id="assign-dd">
                        <input type="hidden" name="assigned_admin_id" id="modal-assigned" value="">
                        <button type="button" id="assign-btn" aria-haspopup="listbox" class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                            <span id="assign-label" class="min-w-0 flex-1"></span>
                            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <ul id="assign-list" role="listbox" class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                            <li>
                                <button type="button" class="assign-opt block w-full px-3 py-2 text-left hover:bg-slate-50" data-value="" data-name="" data-dept="">
                                    <span class="block text-sm text-slate-500">— Unassigned —</span>
                                </button>
                            </li>
                            <?php foreach ($adminOptions as $aid => $opt): ?>
                                <li>
                                    <button type="button" class="assign-opt block w-full px-3 py-2 text-left hover:bg-slate-50"
                                            data-value="<?= htmlspecialchars($aid) ?>"
                                            data-name="<?= htmlspecialchars($opt['name']) ?>"
                                            data-dept="<?= htmlspecialchars($opt['dept']) ?>">
                                        <span class="block text-sm font-medium text-slate-800"><?= htmlspecialchars($opt['name']) ?></span>
                                        <span class="block text-[11px] text-slate-500"><?= htmlspecialchars($opt['dept']) ?></span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Status</label>
                    <select id="modal-status" onchange="document.getElementById('modal-status-hidden').value = this.value" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="pending">Pending</option>
                        <option value="in progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Admin Notes <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="modal-notes" name="admin_notes" rows="3" placeholder="Add internal notes about this report…" class="w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"></textarea>
                </div>
                <div id="modal-history-wrap" class="mt-5 border-t border-slate-200 pt-4">
                    <label class="mb-2 block text-xs font-semibold text-slate-500">Status History</label>
                    <div id="modal-history"></div>
                </div>
            </div>
            <div class="flex shrink-0 items-center justify-end gap-2 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-5 pt-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px));">
                <button type="button" onclick="closeReportModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">Close</button>
                <?php if (can_edit_reports()): ?>
                    <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800">Save Changes</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
const STATUS_META = {
    "pending":     { color: "#f59e0b", label: "Pending" },
    "in progress": { color: "#3b82f6", label: "In Progress" },
    "resolved":    { color: "#22c55e", label: "Resolved" },
    "rejected":    { color: "#ef4444", label: "Rejected" }
};

function getStatusMeta(status) {
    const s = String(status || "pending").toLowerCase();
    return STATUS_META[s] || { color: "#94a3b8", label: s };
}

function openReportModal(report) {
    document.getElementById("modal-report-id").value = report.id;
    document.getElementById("modal-category").textContent = report.category || "—";
    document.getElementById("modal-department").textContent = report.departmentName || "—";
    document.getElementById("modal-reporter").textContent = report.userFullName || "Unknown";
    document.getElementById("modal-email").textContent = report.userEmail || "";
    document.getElementById("modal-date").textContent = report.createdAtFormatted || "Unknown";
    document.getElementById("modal-description").textContent = report.description || "No description";

    // Typed address (street + barangay). Older reports don't have it.
    const addressParts = [report.streetAddress, report.barangay].filter(function (part) {
        return part && String(part).trim() !== "";
    });
    document.getElementById("modal-address").textContent = addressParts.length ? addressParts.join(", ") : "—";

    let coords = "N/A";
    if (report.latitude !== null && report.longitude !== null) {
        coords = report.latitude + ", " + report.longitude;
    }
    document.getElementById("modal-coords").textContent = coords;

    const statusSelect = document.getElementById("modal-status");
    statusSelect.value = (report.status || "pending").toLowerCase().replace("_", " ");
    document.getElementById("modal-status-hidden").value = statusSelect.value;
    document.getElementById("modal-notes").value = report.adminNotes || "";

    // Only rendered for the super admin
    setAssigned(report.assignedAdminId || "", report.assignedAdminName || "");

    const imgWrap = document.getElementById("modal-image-wrap");
    const img = document.getElementById("modal-image");
    if (report.imageUrl && report.imageUrl.trim() !== "") {
        img.src = report.imageUrl;
        imgWrap.style.display = "block";
    } else {
        img.src = "";
        imgWrap.style.display = "none";
    }

    const historyContainer = document.getElementById("modal-history");
    historyContainer.innerHTML = "";
    const history = Array.isArray(report.statusHistory) ? report.statusHistory : [];

    if (history.length === 0) {
        const meta = getStatusMeta(report.status);
        const fallback = document.createElement("div");
        fallback.className = "timeline-entry";
        fallback.innerHTML = "<span class='timeline-dot' style='background:" + meta.color + "'></span>" +
            "<div class='text-sm text-slate-800 font-medium'>" + meta.label + "</div>" +
            "<div class='text-xs text-slate-500'>" + (report.createdAtFormatted || "Unknown") + "</div>";
        historyContainer.appendChild(fallback);
    } else {
        history.forEach(function(entry) {
            const meta = getStatusMeta(entry.status);
            const row = document.createElement("div");
            row.className = "timeline-entry";
            row.innerHTML = "<span class='timeline-dot' style='background:" + meta.color + "'></span>" +
                "<div class='text-sm text-slate-800 font-medium'>" + meta.label + "</div>" +
                "<div class='text-xs text-slate-500'>" + (entry.at || "Unknown") + "</div>";
            historyContainer.appendChild(row);
        });
    }

    const modal = document.getElementById("report-modal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    document.body.style.overflow = "hidden";
}

// ---- "Assigned To" custom dropdown (super admin only) ----
function renderAssignLabel(name, dept) {
    const label = document.getElementById("assign-label");
    if (!label) return;
    label.innerHTML = "";

    const n = document.createElement("span");
    n.className = "block truncate text-sm " + (name ? "font-medium text-slate-800" : "text-slate-500");
    n.textContent = name || "— Unassigned —";
    label.appendChild(n);

    if (dept) {
        const d = document.createElement("span");
        d.className = "block truncate text-[11px] text-slate-500";
        d.textContent = dept;
        label.appendChild(d);
    }
}

function setAssigned(id, fallbackName) {
    const hidden = document.getElementById("modal-assigned");
    if (!hidden) return;

    hidden.value = id;
    closeAssignList();

    if (id === "") {
        renderAssignLabel("", "");
        return;
    }

    const opt = document.querySelector('.assign-opt[data-value="' + CSS.escape(id) + '"]');
    if (opt) {
        renderAssignLabel(opt.dataset.name, opt.dataset.dept);
    } else {
        // Admin was deactivated/removed: keep the current assignment untouched.
        renderAssignLabel((fallbackName || "Unknown admin") + " (inactive)", "");
    }
}

function closeAssignList() {
    const list = document.getElementById("assign-list");
    if (list) list.classList.add("hidden");
}

(function () {
    const btn = document.getElementById("assign-btn");
    const list = document.getElementById("assign-list");
    if (!btn || !list) return;

    btn.addEventListener("click", function () {
        list.classList.toggle("hidden");
    });

    list.querySelectorAll(".assign-opt").forEach(function (opt) {
        opt.addEventListener("click", function () {
            document.getElementById("modal-assigned").value = opt.dataset.value;
            renderAssignLabel(opt.dataset.name, opt.dataset.dept);
            closeAssignList();
        });
    });

    document.addEventListener("click", function (e) {
        if (!document.getElementById("assign-dd").contains(e.target)) closeAssignList();
    });
})();

function closeReportModal() {
    closeAssignList();
    const modal = document.getElementById("report-modal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    document.body.style.overflow = "";
}

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") closeReportModal();
});

// ---- Flash popup: success fades out after ~2s, errors stay until closed ----
let flashHideTimer = null;

function closeFlashPopup() {
    const popup = document.getElementById("flash-popup");
    if (!popup) return;

    if (flashHideTimer) {
        clearTimeout(flashHideTimer);
        flashHideTimer = null;
    }

    popup.classList.add("flash-hide");
    setTimeout(function () {
        if (popup.parentNode) popup.parentNode.removeChild(popup);
    }, 300);
}

(function () {
    const popup = document.getElementById("flash-popup");
    if (!popup) return;

    if (popup.dataset.type === "success") {
        flashHideTimer = setTimeout(closeFlashPopup, 2000);
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") closeFlashPopup();
    });
})();

// ---- Status filter (client-side) + export links ----
const EXPORT_RANGE = <?= json_encode($selectedRange) ?>;
const STATUS_TITLES = <?= json_encode($statusTitles) ?>;

function updateExportLinks() {
    const statusSel = document.getElementById("status-select");
    const status = statusSel ? statusSel.value : "all";

    ["docx", "pdf"].forEach(function (fmt) {
        const a = document.getElementById("export-" + fmt);
        if (!a) return;
        a.href = "export_reports.php?range=" + encodeURIComponent(EXPORT_RANGE) +
                 "&format=" + fmt +
                 "&status=" + encodeURIComponent(status);
    });
}

function applyStatusFilter(value) {
    const sel = document.getElementById("status-select");
    if (sel && sel.value !== value) sel.value = value;

    document.querySelectorAll(".report-row").forEach(function (row) {
        const show = value === "all" || row.dataset.status === value;
        row.style.display = show ? "" : "none";
    });

    // Count from the table rows only (cards duplicate them on mobile).
    const visible = document.querySelectorAll("tbody .report-row").length
        ? Array.from(document.querySelectorAll("tbody .report-row")).filter(function (r) { return r.style.display !== "none"; }).length
        : Array.from(document.querySelectorAll(".report-row")).filter(function (r) { return r.style.display !== "none"; }).length;

    const count = document.getElementById("visible-count");
    const label = document.getElementById("visible-label");
    const title = document.getElementById("status-title");
    const list = document.getElementById("reports-list");
    const none = document.getElementById("no-results");

    if (count) count.textContent = visible;
    if (label) label.textContent = visible === 1 ? "report" : "reports";
    if (title) title.textContent = STATUS_TITLES[value] || "Reports";
    if (list && none && document.querySelector(".report-row")) {
        none.classList.toggle("hidden", visible !== 0);
    }

    updateExportLinks();

    // Keep the status in the URL so a refresh / period change keeps it.
    const params = new URLSearchParams(window.location.search);
    params.set("status", value);
    window.history.replaceState(null, "", window.location.pathname + "?" + params.toString());
}

(function () {
    const btn = document.getElementById("export-btn");
    const menu = document.getElementById("export-menu");
    if (!btn || !menu) return;

    btn.addEventListener("click", function () { menu.classList.toggle("hidden"); });
    menu.addEventListener("click", function (e) {
        if (e.target.closest("a")) menu.classList.add("hidden");
    });
    document.addEventListener("click", function (e) {
        if (!document.getElementById("export-dd").contains(e.target)) menu.classList.add("hidden");
    });
})();

updateExportLinks();

// ---- Date sort (newest first / oldest first) — applies to every list ----
let dateSortDir = "desc";

function applyDateSort(dir) {
    dateSortDir = dir;

    document.querySelectorAll("[data-sort-list]").forEach(function (container) {
        const items = Array.from(container.querySelectorAll(":scope > .report-row"));
        items.sort(function (a, b) {
            const ta = Number(a.dataset.ts) || 0;
            const tb = Number(b.dataset.ts) || 0;
            return dir === "desc" ? tb - ta : ta - tb;
        });
        items.forEach(function (el) { container.appendChild(el); });
    });

    const text = dir === "desc" ? "Newest first" : "Oldest first";
    document.querySelectorAll(".date-sort-label").forEach(function (el) { el.textContent = text; });
    document.querySelectorAll(".date-sort-icon").forEach(function (el) {
        el.style.transform = dir === "desc" ? "" : "rotate(180deg)";
    });
    document.querySelectorAll(".date-sort-btn").forEach(function (b) {
        b.title = "Sorted: " + text + " (click to reverse)";
    });
}

function toggleDateSort() {
    applyDateSort(dateSortDir === "desc" ? "asc" : "desc");
}

// Opened from the notification bell: reports.php?open=<report id>
(function () {
    const openId = new URLSearchParams(window.location.search).get("open");
    if (!openId) return;

    const row = document.querySelector('.report-row[data-id="' + CSS.escape(openId) + '"]');
    const btn = row ? row.querySelector("button") : null;
    if (btn) {
        // Make sure the report's status is visible in the filter.
        const sel = document.getElementById("status-select");
        if (sel && sel.value !== "all" && sel.value !== row.dataset.status) {
            applyStatusFilter(row.dataset.status);
        }
        btn.click();
    }
    // Drop ?open= so a refresh doesn't reopen the modal (keep ?range=).
    const params = new URLSearchParams(window.location.search);
    params.delete("open");
    params.set("status", document.getElementById("status-select").value);
    const qs = params.toString();
    window.history.replaceState(null, "", window.location.pathname + (qs ? "?" + qs : ""));
})();
</script>
</body>
</html>