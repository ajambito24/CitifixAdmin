<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/firebase.php';
require_once __DIR__ . '/config/department_map.php';

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

        if ($createdAtRaw !== null) {

            $dt = null;

            if ($createdAtRaw instanceof \Google\Cloud\Core\Timestamp) {
                $dt = $createdAtRaw->get();
            } elseif ($createdAtRaw instanceof \DateTimeInterface) {
                $dt = $createdAtRaw;
            } elseif (is_numeric($createdAtRaw)) {
                $ts = (int) $createdAtRaw;
                if ($ts > 9999999999) {
                    $ts = (int) ($ts / 1000);
                }
                $dt = (new \DateTime())->setTimestamp($ts);
            }

            if ($dt !== null) {

                if ($dt instanceof \DateTimeImmutable) {
                    $dt = \DateTime::createFromImmutable($dt);
                }

                $dt->setTimezone(new \DateTimeZone('Asia/Manila'));

                $createdAtFormatted = $dt->format('M d, Y · g:i A');
            }
        }

        // ---- Format statusHistory ----
        $historyRaw = $data['statusHistory'] ?? [];
        $statusHistory = [];

        if (is_array($historyRaw)) {
            foreach ($historyRaw as $entry) {

                $entryStatus = strtolower($entry['status'] ?? '');
                $entryAt = $entry['at'] ?? null;
                $entryAtFormatted = '';

                if ($entryAt instanceof \Google\Cloud\Core\Timestamp) {
                    $entryAtFormatted = $entryAt
                        ->get()
                        ->setTimezone(new DateTimeZone('Asia/Manila'))
                        ->format('M d, Y · g:i A');
                } elseif ($entryAt instanceof \DateTimeInterface) {
                    $entryAtFormatted = $entryAt
                        ->setTimezone(new DateTimeZone('Asia/Manila'))
                        ->format('M d, Y · g:i A');
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

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CitiFix Reports</title>

<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: { preflight: false }
    }
</script>

<link rel="stylesheet" href="css/style.css">

<style>

body.reports-page {
    min-height: 100vh;
}

#report-modal .modal-body {
    max-height: calc(90vh - 140px);
    overflow-y: auto;
}

/* Timeline styles */
.timeline-entry {
    position: relative;
    padding-left: 28px;
    padding-bottom: 14px;
}
.timeline-entry:last-child {
    padding-bottom: 0;
}
.timeline-entry::before {
    content: "";
    position: absolute;
    left: 8px;
    top: 16px;
    bottom: -2px;
    width: 2px;
    background: #e2e8f0;
}
.timeline-entry:last-child::before {
    display: none;
}
.timeline-dot {
    position: absolute;
    left: 0;
    top: 4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 1px #cbd5e1;
}

</style>

</head>

<body class="reports-page">

<div class="admin-layout">

    <aside class="sidebar">

        <div class="sidebar-logo">
            <img src="images/logo.png" alt="CitiFix Manila" class="sidebar-logo-img">
            <div class="sidebar-title">CITIFIX</div>
            <div class="sidebar-subtitle">MANILA</div>
        </div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">🏠 <span>Home</span></a>
            <a href="reports.php" class="menu-item active">📋 <span>Reports</span></a>
            <a href="map.php" class="menu-item">🗺️ <span>Map View</span></a>
            <?php if ($IS_SUPER_ADMIN): ?>
                <a href="personnel.php" class="menu-item">👥 <span>Personnel</span></a>
                <a href="users.php" class="menu-item">👤 <span>Users</span></a>
                <a href="settings.php" class="menu-item">⚙️ <span>Settings</span></a>
            <?php endif; ?>
            <a href="logout.php" class="menu-item logout-menu">🚪 <span>Logout</span></a>
        </nav>

    </aside>

    <main class="main-content">

        <header class="flex-shrink-0 mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 m-0">
                    Reports
                </h1>
                <p class="text-sm text-slate-500 m-0 mt-1">
                    <?php if ($IS_SUPER_ADMIN): ?>
                        All departments
                    <?php else: ?>
                        <?= htmlspecialchars(department_name($db, $ADMIN_DEPT)) ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-100 text-blue-700 text-sm">
                <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                <strong><?= $countAll ?></strong>
                <span>report<?= $countAll === 1 ? '' : 's' ?></span>
            </div>
        </header>

        <?php if ($flash !== null): ?>
            <?php
                $flashType = $flash['type'] ?? 'success';
                $flashMsg  = $flash['msg']  ?? '';
                $flashClasses = $flashType === 'success'
                    ? 'bg-green-50 border-green-200 text-green-800'
                    : 'bg-red-50 border-red-200 text-red-700';
                $flashIcon = $flashType === 'success' ? '✅' : '⚠️';
            ?>
            <div class="mb-4 px-4 py-3 rounded-md border text-sm flex items-center gap-2 <?= $flashClasses ?>">
                <span><?= $flashIcon ?></span>
                <span><?= htmlspecialchars($flashMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mb-4 px-4 py-3 rounded-md bg-red-50 border border-red-200 text-red-700 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="dashboard-card bg-white rounded-xl shadow-sm border border-slate-200 p-5">

            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-800 m-0">
                    All Reports
                </h2>
                <p class="text-sm text-slate-500 m-0 mt-0.5">
                    Reports submitted by CitiFix residents.
                </p>
            </div>

            <?php if (empty($reports)): ?>

                <div class="py-12 text-center">
                    <div class="text-4xl mb-2">📋</div>
                    <p class="text-slate-500 text-sm m-0">
                        No reports available.
                    </p>
                </div>

            <?php else: ?>

                <!-- Filter tabs -->
                <div class="flex gap-2 mb-4 flex-wrap" id="filter-tabs">
                    <button
                        type="button"
                        data-filter="all"
                        class="filter-tab px-3 py-1.5 rounded-full border text-xs font-medium transition-colors bg-blue-500 text-white border-blue-500"
                    >
                        All (<?= $countAll ?>)
                    </button>
                    <button
                        type="button"
                        data-filter="pending"
                        class="filter-tab px-3 py-1.5 rounded-full border text-xs font-medium transition-colors bg-white text-slate-700 border-slate-300 hover:bg-slate-50"
                    >
                        🟠 Pending (<?= $countPending ?>)
                    </button>
                    <button
                        type="button"
                        data-filter="in progress"
                        class="filter-tab px-3 py-1.5 rounded-full border text-xs font-medium transition-colors bg-white text-slate-700 border-slate-300 hover:bg-slate-50"
                    >
                        🔵 In Progress (<?= $countInProgress ?>)
                    </button>
                    <button
                        type="button"
                        data-filter="resolved"
                        class="filter-tab px-3 py-1.5 rounded-full border text-xs font-medium transition-colors bg-white text-slate-700 border-slate-300 hover:bg-slate-50"
                    >
                        🟢 Resolved (<?= $countResolved ?>)
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">

                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                <th class="px-3 py-2">ID</th>
                                <th class="px-3 py-2">Issue</th>
                                <th class="px-3 py-2">Department</th>
                                <th class="px-3 py-2">Reporter</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Action</th>
                            </tr>
                        </thead>

                        <tbody id="reports-tbody">

                        <?php foreach ($reports as $report): ?>

                            <?php
                                $status = strtolower($report['status']);

                                $statusClasses = [
                                    'pending'     => 'bg-amber-100 text-amber-800 border-amber-200',
                                    'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'in_progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'resolved'    => 'bg-green-100 text-green-800 border-green-200',
                                ];

                                $pillClass = $statusClasses[$status]
                                    ?? 'bg-slate-100 text-slate-700 border-slate-200';

                                $filterKey = ($status === 'in_progress') ? 'in progress' : $status;
                            ?>

                            <tr
                                data-status="<?= htmlspecialchars($filterKey) ?>"
                                class="report-row border-b border-slate-100 hover:bg-slate-50 transition-colors"
                            >

                                <td class="px-3 py-3 font-mono text-xs text-slate-500">
                                    <?= htmlspecialchars(substr($report['id'], 0, 8)) ?>
                                </td>

                                <td class="px-3 py-3">
                                    <strong class="text-slate-800">
                                        <?= htmlspecialchars($report['category']) ?>
                                    </strong>
                                </td>

                                <td class="px-3 py-3 text-slate-600 text-xs">
                                    <?= htmlspecialchars(department_name($db, $report['departmentId'] ?? null)) ?>
                                </td>

                                <td class="px-3 py-3 text-slate-700">
                                    <?= htmlspecialchars($report['userFullName']) ?>
                                </td>

                                <td class="px-3 py-3 text-slate-500 text-xs whitespace-nowrap">
                                    <?= htmlspecialchars($report['createdAtFormatted']) ?>
                                </td>

                                <td class="px-3 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded-full border text-xs font-medium capitalize <?= $pillClass ?>">
                                        <?= htmlspecialchars($report['status']) ?>
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <?php if (can_edit_reports()): ?>
                                        <button
                                            type="button"
                                            onclick='openReportModal(<?= htmlspecialchars(
                                                json_encode($report),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>)'
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium transition-colors"
                                        >
                                            ✏️ Edit
                                        </button>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            onclick='openReportModal(<?= htmlspecialchars(
                                                json_encode($report),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>)'
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-slate-400 hover:bg-slate-500 text-white text-xs font-medium transition-colors"
                                        >
                                            👁️ View
                                        </button>
                                    <?php endif; ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>
                </div>

                <div id="no-results" class="hidden py-12 text-center">
                    <div class="text-4xl mb-2">🔍</div>
                    <p class="text-slate-500 text-sm m-0">
                        No reports match this filter.
                    </p>
                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<!-- =========================== -->
<!-- REPORT MODAL                -->
<!-- =========================== -->

<div
    id="report-modal"
    class="hidden fixed inset-0 z-50 items-center justify-center bg-black/60 p-4"
    onclick="if (event.target === this) closeReportModal()"
>

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh]">

        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">

            <div>
                <h3 class="text-lg font-semibold text-slate-800 m-0">
                    Report Details
                </h3>
                <p class="text-xs text-slate-500 m-0 mt-0.5">
                    Update status and add admin notes.
                </p>
            </div>

            <button
                type="button"
                onclick="closeReportModal()"
                class="text-slate-400 hover:text-slate-700 text-2xl leading-none px-2"
                aria-label="Close"
            >
                ×
            </button>

        </div>

        <form
            method="POST"
            action="update_status.php"
            class="flex flex-col flex-1 min-h-0"
            id="report-form"
        >

            <input type="hidden" name="report_id" id="modal-report-id">
            <input type="hidden" name="status" id="modal-status-hidden">

            <div class="modal-body px-5 py-4 flex-1 overflow-y-auto">

                <div id="modal-image-wrap" class="mb-4" style="display:none;">
                    <img
                        id="modal-image"
                        src=""
                        alt="Report photo"
                        class="w-full max-h-64 object-cover rounded-lg border border-slate-200 cursor-pointer"
                        onclick="window.open(this.src, '_blank')"
                    >
                    <p class="text-xs text-slate-400 mt-1 m-0">
                        Click image to open full size
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Category
                    </label>
                    <div id="modal-category" class="text-slate-800 font-medium"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Department
                    </label>
                    <div id="modal-department" class="text-slate-800"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Reported By
                    </label>
                    <div id="modal-reporter" class="text-slate-800"></div>
                    <div id="modal-email" class="text-xs text-slate-500"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Date &amp; Time
                    </label>
                    <div id="modal-date" class="text-slate-800"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Description
                    </label>
                    <div id="modal-description" class="text-slate-700 text-sm whitespace-pre-wrap"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Location
                    </label>
                    <div id="modal-coords" class="text-slate-700 font-mono text-xs"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Status
                    </label>
                    <select
                        id="modal-status"
                        onchange="document.getElementById('modal-status-hidden').value = this.value"
                        class="w-full px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-800 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none"
                    >
                        <option value="pending">Pending</option>
                        <option value="in progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                        Admin Notes <span class="text-slate-400 font-normal normal-case">(optional)</span>
                    </label>
                    <textarea
                        id="modal-notes"
                        name="admin_notes"
                        rows="3"
                        placeholder="Add internal notes about this report…"
                        class="w-full px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-800 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none resize-y"
                    ></textarea>
                </div>

                <!-- Status History -->
                <div id="modal-history-wrap" class="mt-4 pt-4 border-t border-slate-200">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                        Status History
                    </label>
                    <div id="modal-history"></div>
                </div>

            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50 rounded-b-xl">

                <button
                    type="button"
                    onclick="closeReportModal()"
                    class="px-4 py-2 rounded-md bg-white border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-100 transition-colors"
                >
                    Close
                </button>

                <?php if (can_edit_reports()): ?>
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-md bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium transition-colors"
                    >
                        Save Changes
                    </button>
                <?php endif; ?>

            </div>

        </form>

    </div>

</div>


<script>

// ---- Status metadata ----
const STATUS_META = {
    "pending":     { color: "#f59e0b", label: "Pending" },
    "in progress": { color: "#3b82f6", label: "In Progress" },
    "resolved":    { color: "#22c55e", label: "Resolved" }
};

function getStatusMeta(status) {
    const s = String(status || "pending").toLowerCase();
    return STATUS_META[s] || { color: "#94a3b8", label: s };
}

// ---- Modal open ----
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

    // Image
    const imgWrap = document.getElementById("modal-image-wrap");
    const img = document.getElementById("modal-image");

    if (report.imageUrl && report.imageUrl.trim() !== "") {
        img.src = report.imageUrl;
        imgWrap.style.display = "block";
    } else {
        img.src = "";
        imgWrap.style.display = "none";
    }

    // ---- Status History ----
    const historyContainer = document.getElementById("modal-history");
    historyContainer.innerHTML = "";

    const history = Array.isArray(report.statusHistory) ? report.statusHistory : [];

    if (history.length === 0) {
        const meta = getStatusMeta(report.status);
        const fallback = document.createElement("div");
        fallback.className = "timeline-entry";
        fallback.innerHTML =
            "<span class='timeline-dot' style='background:" + meta.color + "'></span>" +
            "<div class='text-sm text-slate-800 font-medium'>" + meta.label + "</div>" +
            "<div class='text-xs text-slate-500'>" +
                (report.createdAtFormatted || "Unknown") +
            "</div>";
        historyContainer.appendChild(fallback);
    } else {
        history.forEach(function(entry) {
            const meta = getStatusMeta(entry.status);

            const row = document.createElement("div");
            row.className = "timeline-entry";
            row.innerHTML =
                "<span class='timeline-dot' style='background:" + meta.color + "'></span>" +
                "<div class='text-sm text-slate-800 font-medium'>" + meta.label + "</div>" +
                "<div class='text-xs text-slate-500'>" +
                    (entry.at || "Unknown") +
                "</div>";

            historyContainer.appendChild(row);
        });
    }

    // Show modal
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
    if (event.key === "Escape") {
        closeReportModal();
    }
});

// ---- Filter tabs ----
const filterTabs = document.querySelectorAll(".filter-tab");
const reportRows = document.querySelectorAll(".report-row");
const noResults = document.getElementById("no-results");

filterTabs.forEach(function(tab) {

    tab.addEventListener("click", function() {

        const filter = tab.getAttribute("data-filter");

        filterTabs.forEach(function(t) {
            t.classList.remove("bg-blue-500", "text-white", "border-blue-500");
            t.classList.add("bg-white", "text-slate-700", "border-slate-300", "hover:bg-slate-50");
        });

        tab.classList.remove("bg-white", "text-slate-700", "border-slate-300", "hover:bg-slate-50");
        tab.classList.add("bg-blue-500", "text-white", "border-blue-500");

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

</script>

</body>
</html>