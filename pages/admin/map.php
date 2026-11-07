<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';

$reports = [];
$error = '';

$boundaryPath = __DIR__ . '/../../sampaloc_boundary.json';
$boundary = null;

if (file_exists($boundaryPath)) {
    $boundary = json_decode(file_get_contents($boundaryPath), true);
}

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

        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        if (is_numeric($latitude) && is_numeric($longitude)) {

            $reports[] = [
                'id' => $document->id(),
                'category' => $data['category'] ?? 'Issue',
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 'pending',
                'imageUrl' => $data['imageUrl'] ?? '',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude
            ];
        }
    }

} catch (Exception $e) {

    $error = $e->getMessage();
}

// ---- Design-only helpers (no data logic) ----
$activePage = 'map';

?>
<!DOCTYPE html>
<html lang="en">

<head>

<?php require __DIR__ . '/../utils/screen_util.php'; ?>

<title>CitiFix Map View</title>

<!-- Leaflet CSS -->
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>

<style>

/* Leaflet's own DOM is outside Tailwind's reach, so these are plain CSS. */

.leaflet-container { font-family: inherit; }

.leaflet-top    { margin-top: 0; }
.leaflet-bottom { margin-bottom: env(safe-area-inset-bottom, 0px); }

.leaflet-bar { border: 1px solid #e2e8f0 !important; border-radius: 0.75rem !important; overflow: hidden; box-shadow: 0 1px 3px rgba(15, 23, 42, .12) !important; }
.leaflet-bar a { color: #1d4ed8; }

.leaflet-popup-content-wrapper { border-radius: 0.875rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .18); padding: 0; overflow: hidden; }
.leaflet-popup-content { margin: 0; }
.leaflet-container a.leaflet-popup-close-button { top: 6px; right: 6px; z-index: 2; }

.cf-popup { width: 16rem; max-width: 100%; }
.cf-popup-body { padding: 0.85rem 1rem 0.9rem; }
.cf-popup-imglink { display: block; }
.cf-popup-img { display: block; width: 100%; height: 140px; object-fit: cover; background: #f1f5f9; }
.cf-popup-title { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0 1.25rem 0.25rem 0; }
.cf-popup-desc { font-size: 0.8rem; line-height: 1.45; color: #475569; margin: 0 0 0.6rem; }
.cf-popup-foot { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.cf-popup-pill { display: inline-block; border: 1px solid; border-radius: 9999px; padding: 0.1rem 0.6rem; font-size: 0.72rem; font-weight: 700; text-transform: capitalize; }
.cf-popup-link { font-size: 0.78rem; font-weight: 700; color: #1d4ed8; text-decoration: none; }
.cf-popup-link:hover { text-decoration: underline; }

.map-legend { background: #fff; padding: 0.7rem 0.9rem; border: 1px solid #e2e8f0; border-radius: 0.875rem; box-shadow: 0 4px 14px rgba(15, 23, 42, .15); font-size: 0.78rem; line-height: 1.6; color: #334155; min-width: 9.5rem; }
.map-legend h4 { margin: 0 0 0.25rem; font-size: 0.78rem; font-weight: 800; color: #0f172a; }
.map-legend .legend-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.map-legend .legend-name { display: inline-flex; align-items: center; }
.map-legend .legend-count { font-weight: 700; color: #0f172a; }

.legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 7px; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,0.25); }

</style>

</head>

<body class="overflow-hidden bg-slate-50 text-slate-700 antialiased">

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="cf-content flex h-[100dvh] flex-col">

    <!-- TOP BAR -->
    <header class="z-30 shrink-0 border-b border-slate-200 bg-white/90 backdrop-blur" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="flex h-16 items-center justify-between gap-3 px-[var(--su-gutter)]">

            <div class="flex min-w-0 items-center gap-3">
                <button type="button" data-cf-open aria-label="Open menu"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">Map View</h1>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">Sampaloc, Manila</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">          
                <div class="hidden text-right leading-tight sm:block">
                    <strong class="block text-sm text-slate-800"><?= htmlspecialchars($ADMIN_NAME) ?></strong>
                    <small class="hidden text-xs text-slate-500 md:block">
                        <?= htmlspecialchars($ADMIN_EMAIL) ?>
                        &middot; <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ADMIN_ROLE))) ?>
                    </small>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-700 font-extrabold text-white">
                    <?= htmlspecialchars(strtoupper(substr($ADMIN_NAME, 0, 1))) ?>
                </div>
            </div>
        </div>
    </header>

    <main class="flex min-h-0 flex-1 flex-col px-[var(--su-gutter)] py-4 sm:py-6" style="padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px));">

        <section class="mx-auto flex min-h-0 w-full max-w-[96rem] flex-1 flex-col rounded-2xl border border-slate-200 bg-white shadow-sm">

            <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Report Locations</h2>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">
                        <?= $IS_SUPER_ADMIN ? 'CitiFix reports within Sampaloc, Manila' : 'Reports assigned to you' ?>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <select id="map-status-filter" aria-label="Filter reports by status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="pending" selected>Pending</option>
                        <option value="all">All</option>
                        <option value="resolved">Resolved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <div class="flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-sm text-brand-700">
                        <span class="inline-block h-2 w-2 rounded-full bg-brand-500"></span>
                        <strong id="visible-count">0</strong>
                        <span id="visible-label">reports</span>
                    </div>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="mx-4 mb-3 shrink-0 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:mx-5" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="min-h-0 flex-1 px-3 pb-3 sm:px-4 sm:pb-4">
                <div id="map" class="isolate h-full w-full overflow-hidden rounded-xl border border-slate-200"></div>
            </div>

        </section>

    </main>

</div>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>

<script>

const reports = <?= json_encode(
    $reports,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const boundary = <?= json_encode(
    $boundary,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const SAMPALOC_CENTER = [14.6031, 121.0022];

const STATUS_COLORS = {
    "pending":     "#f59e0b",
    "resolved":    "#22c55e",
    "rejected":    "#ef4444"
};

const STATUS_LABELS = {
    "pending":     "Pending",
    "resolved":    "Resolved",
    "rejected":    "Rejected"
};

const IS_TOUCH = window.matchMedia("(pointer: coarse)").matches;

// Normalize a raw status the same way reports.php does.
function normalizeStatus(raw) {
    const s = String(raw || "pending").toLowerCase().replace(/_/g, " ").trim();
    return STATUS_COLORS.hasOwnProperty(s) ? s : "pending";
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Only allow http(s) image URLs.
function safeImageUrl(url) {
    const u = String(url || "").trim();
    return /^https?:\/\//i.test(u) ? u : "";
}

const map = L.map("map", {
    center: SAMPALOC_CENTER,
    zoom: 14,
    minZoom: 12,
    maxZoom: 18,
    zoomControl: true
});

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

if (boundary) {

    const boundaryLayer = L.geoJSON(boundary, {
        style: {
            color: "#1d4ed8",
            weight: 2,
            opacity: 0.8,
            fillColor: "#1d4ed8",
            fillOpacity: 0.05
        }
    }).addTo(map);

    map.fitBounds(boundaryLayer.getBounds(), {
        padding: [40, 40],
        maxZoom: 15
    });

    const polygonBounds = boundaryLayer.getBounds();
    const expandedBounds = polygonBounds.pad(0.2);

    map.setMaxBounds(expandedBounds);
    map.options.maxBoundsViscosity = 1.0;
}

const markerLayer = L.layerGroup().addTo(map);

function buildPopup(report, statusKey, color) {
    const img = safeImageUrl(report.imageUrl);

    const imageHtml = img
        ? "<a class='cf-popup-imglink' href='" + escapeHtml(img) + "' target='_blank' rel='noopener noreferrer' title='Open full size'>" +
              "<img class='cf-popup-img' src='" + escapeHtml(img) + "' alt='Report photo' loading='lazy' onerror='this.parentNode.remove()'>" +
          "</a>"
        : "";

    return "<div class='cf-popup'>" +
        imageHtml +
        "<div class='cf-popup-body'>" +
            "<p class='cf-popup-title'>" + escapeHtml(report.category) + "</p>" +
            "<p class='cf-popup-desc'>" + escapeHtml(report.description) + "</p>" +
            "<div class='cf-popup-foot'>" +
                "<span class='cf-popup-pill' style='color:" + color + ";border-color:" + color + "'>" +
                    escapeHtml(STATUS_LABELS[statusKey]) +
                "</span>" +
                "<a class='cf-popup-link' href='reports.php?open=" + encodeURIComponent(report.id) + "'>View in Reports</a>" +
            "</div>" +
        "</div>" +
        "</div>";
}

// Build every marker once; the filter just adds/removes them from the layer.
const entries = reports.map(function(report) {

    const statusKey = normalizeStatus(report.status);
    const color = STATUS_COLORS[statusKey];

    const marker = L.circleMarker(
        [report.latitude, report.longitude],
        {
            radius: IS_TOUCH ? 11 : 8,
            fillColor: color,
            color: "#ffffff",
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        }
    );

    marker.bindPopup(buildPopup(report, statusKey, color), {
        autoPanPadding: [24, 24],
        maxWidth: 300
    });

    return { marker: marker, status: statusKey };
});

// ---- Legend (counts reflect what is currently shown on the map) ----
const legendEl = L.DomUtil.create("div", "map-legend");

function renderLegend(counts) {
    let html = "<h4>Legend</h4>";
    Object.keys(STATUS_LABELS).forEach(function(key) {
        html += "<div class='legend-row'><span class='legend-name'>" +
                "<span class='legend-dot' style='background:" + STATUS_COLORS[key] + "'></span>" +
                STATUS_LABELS[key] + "</span>" +
                "<span class='legend-count'>" + counts[key] + "</span></div>";
    });
    legendEl.innerHTML = html;
}

const legend = L.control({ position: "bottomright" });
legend.onAdd = function() { return legendEl; };
legend.addTo(map);

// ---- Status filter ----
function matchesFilter(status, filter) {
    if (filter === "all") return true;
    return status === filter;
}

function applyMapFilter(filter) {
    const counts = { "pending": 0, "resolved": 0, "rejected": 0 };
    let visible = 0;

    map.closePopup();

    entries.forEach(function(entry) {
        if (matchesFilter(entry.status, filter)) {
            markerLayer.addLayer(entry.marker);
            counts[entry.status]++;
            visible++;
        } else {
            markerLayer.removeLayer(entry.marker);
        }
    });

    document.getElementById("visible-count").textContent = visible;
    document.getElementById("visible-label").textContent = visible === 1 ? "report" : "reports";
    renderLegend(counts);
}

const filterSelect = document.getElementById("map-status-filter");
filterSelect.addEventListener("change", function() { applyMapFilter(this.value); });

// Default view: Active (pending + in progress)
applyMapFilter(filterSelect.value);

setTimeout(function() { map.invalidateSize(); }, 100);
window.addEventListener("resize", function() { map.invalidateSize(); });

// Resize the map once the sidebar collapse/expand transition finishes.
document.querySelector(".cf-content").addEventListener("transitionend", function(e) {
    if (e.propertyName === "margin-left") {
        map.invalidateSize();
    }
});

</script>

</body>
</html>