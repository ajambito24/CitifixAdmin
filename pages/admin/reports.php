<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';

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
            'latitude'           => $data['latitude']     ?? null,
            'longitude'          => $data['longitude']    ?? null,
            'userFullName'       => $data['userFullName'] ?? 'Unknown User',
            'userEmail'          => $data['userEmail']    ?? '',
            'imageUrl'           => $data['imageUrl']     ?? '',
            'adminNotes'         => $data['adminNotes']   ?? '',
            'departmentId'       => $data['departmentId'] ?? null,
            'departmentName'     => department_name($db, $data['departmentId'] ?? null),
            'createdAtFormatted' => $createdAtFormatted,
            'statusHistory'      => $statusHistory,
        ];
    }

    // Sort newest first
    usort($reports, function ($a, $b) {
        $ta = strtotime($a['createdAtFormatted']) ?: 0;
        $tb = strtotime($b['createdAtFormatted']) ?: 0;
        return $tb <=> $ta;
    });

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Counts for filter tabs
$countAll = count($reports);
$countPending = 0;
$countInProgress = 0;
$countResolved = 0;

foreach ($reports as $r) {
    $s = strtolower($r['status']);
    if ($s === 'pending') {
        $countPending++;
    } elseif ($s === 'in progress' || $s === 'in_progress') {
        $countInProgress++;
    } elseif ($s === 'resolved') {
        $countResolved++;
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
];

$rows = [];
foreach ($reports as $report) {
    $status = strtolower($report['status']);
    $rows[] = [
        'report' => $report,
        'pill'   => $statusClasses[$status] ?? 'bg-slate-100 text-slate-700 border-slate-200',
        'filter' => ($status === 'in_progress') ? 'in progress' : $status,
        'json'   => htmlspecialchars(json_encode($report), ENT_QUOTES, 'UTF-8'),
    ];
}

$tabBase     = 'filter-tab inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors sm:text-sm';
$tabActive   = 'bg-brand-700 text-white';
$tabInactive = 'bg-slate-100 text-slate-600 hover:bg-slate-200';

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
                $flashType = $flash['type'] ?? 'success';
                $flashMsg  = $flash['msg']  ?? '';
                $flashClasses = $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700';
                $flashIcon = $flashType === 'success' ? '✅' : '⚠️';
            ?>
            <div class="mb-4 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm <?= $flashClasses ?>" role="status">
                <span><?= $flashIcon ?></span>
                <span><?= htmlspecialchars($flashMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 p-4 sm:p-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 sm:text-xl">All Reports</h2>
                    <p class="text-xs text-slate-500 sm:text-sm">Reports submitted by CitiFix residents</p>
                </div>
                <div class="flex shrink-0 items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-sm text-brand-700">
                    <span class="inline-block h-2 w-2 rounded-full bg-brand-500"></span>
                    <strong><?= $countAll ?></strong>
                    <span>report<?= $countAll === 1 ? '' : 's' ?></span>
                </div>
            </div>

            <?php if (empty($reports)): ?>
                <div class="border-t border-slate-100 py-14 text-center">
                    <div class="text-4xl">📋</div>
                    <p class="mt-2 text-sm text-slate-500">No reports available.</p>
                </div>
            <?php else: ?>

                <div class="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-4 sm:px-6" id="filter-tabs">
                    <button type="button" data-filter="all" class="<?= $tabBase ?> <?= $tabActive ?>">All (<?= $countAll ?>)</button>
                    <button type="button" data-filter="pending" class="<?= $tabBase ?> <?= $tabInactive ?>"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Pending (<?= $countPending ?>)</button>
                    <button type="button" data-filter="in progress" class="<?= $tabBase ?> <?= $tabInactive ?>"><span class="h-2 w-2 rounded-full bg-blue-500"></span>In Progress (<?= $countInProgress ?>)</button>
                    <button type="button" data-filter="resolved" class="<?= $tabBase ?> <?= $tabInactive ?>"><span class="h-2 w-2 rounded-full bg-green-500"></span>Resolved (<?= $countResolved ?>)</button>
                </div>

                <!-- TABLE (sm and up) -->
                <div class="hidden overflow-x-auto border-t border-slate-100 sm:block">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <th class="px-4 py-3 sm:px-6">ID</th>
                                <th class="px-4 py-3">Issue</th>
                                <th class="px-4 py-3">Department</th>
                                <th class="px-4 py-3">Reporter</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right sm:px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody id="reports-tbody" class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): $report = $row['report']; ?>
                            <tr data-id="<?= htmlspecialchars($report['id']) ?>" data-status="<?= htmlspecialchars($row['filter']) ?>" class="report-row transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3.5 font-mono text-xs text-slate-500 sm:px-6"><?= htmlspecialchars(substr($report['id'], 0, 8)) ?></td>
                                <td class="px-4 py-3.5"><strong class="text-slate-800"><?= htmlspecialchars($report['category']) ?></strong></td>
                                <td class="px-4 py-3.5 text-xs text-slate-600"><?= htmlspecialchars(department_name($db, $report['departmentId'] ?? null)) ?></td>
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
                <div class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
                    <?php foreach ($rows as $row): $report = $row['report']; ?>
                        <div data-id="<?= htmlspecialchars($report['id']) ?>" data-status="<?= htmlspecialchars($row['filter']) ?>" class="report-row p-4">
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

                <div id="no-results" class="hidden border-t border-slate-100 py-14 text-center">
                    <div class="text-4xl">🔍</div>
                    <p class="mt-2 text-sm text-slate-500">No reports match this filter.</p>
                </div>
            <?php endif; ?>
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
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Location</label>
                    <div id="modal-coords" class="font-mono text-xs text-slate-700"></div>
                </div>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Status</label>
                    <select id="modal-status" onchange="document.getElementById('modal-status-hidden').value = this.value" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="pending">Pending</option>
                        <option value="in progress">In Progress</option>
                        <option value="resolved">Resolved</option>
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
    "resolved":    { color: "#22c55e", label: "Resolved" }
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

    let coords = "N/A";
    if (report.latitude !== null && report.longitude !== null) {
        coords = report.latitude + ", " + report.longitude;
    }
    document.getElementById("modal-coords").textContent = coords;

    const statusSelect = document.getElementById("modal-status");
    statusSelect.value = (report.status || "pending").toLowerCase();
    document.getElementById("modal-status-hidden").value = statusSelect.value;
    document.getElementById("modal-notes").value = report.adminNotes || "";

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

function closeReportModal() {
    const modal = document.getElementById("report-modal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    document.body.style.overflow = "";
}

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") closeReportModal();
});

const filterTabs = document.querySelectorAll(".filter-tab");
const reportRows = document.querySelectorAll(".report-row");
const noResults = document.getElementById("no-results");

const TAB_ACTIVE = ["bg-brand-700", "text-white"];
const TAB_INACTIVE = ["bg-slate-100", "text-slate-600", "hover:bg-slate-200"];

filterTabs.forEach(function(tab) {
    tab.addEventListener("click", function() {
        const filter = tab.getAttribute("data-filter");
        filterTabs.forEach(function(t) {
            t.classList.remove.apply(t.classList, TAB_ACTIVE);
            t.classList.add.apply(t.classList, TAB_INACTIVE);
        });
        tab.classList.remove.apply(tab.classList, TAB_INACTIVE);
        tab.classList.add.apply(tab.classList, TAB_ACTIVE);

        let visibleCount = 0;
        reportRows.forEach(function(row) {
            const status = row.getAttribute("data-status");
            if (filter === "all" || status === filter) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });
        if (visibleCount === 0) {
            noResults.classList.remove("hidden");
        } else {
            noResults.classList.add("hidden");
        }
    });
});

// Opened from the notification bell: reports.php?open=<report id>
(function () {
    const openId = new URLSearchParams(window.location.search).get("open");
    if (!openId) return;

    const btn = document.querySelector('.report-row[data-id="' + CSS.escape(openId) + '"] button');
    if (btn) {
        btn.click();
    }
    // Drop ?open= so a refresh doesn't reopen the modal.
    window.history.replaceState(null, "", window.location.pathname);
})();
</script>
</body>
</html>