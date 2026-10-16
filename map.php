<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/firebase.php';

$reports = [];
$error = '';

$boundaryPath = __DIR__ . '/sampaloc_boundary.json';
$boundary = null;

if (file_exists($boundaryPath)) {
    $boundary = json_decode(file_get_contents($boundaryPath), true);
}

try {

    $documents = $db->collection('reports')->documents();

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
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude
            ];
        }
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

<title>CitiFix Map View</title>

<!-- Tailwind via CDN (with preflight disabled to keep style.css working) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: {
            preflight: false
        }
    }
</script>

<!-- Your existing CSS -->
<link rel="stylesheet" href="css/style.css">

<!-- Leaflet CSS -->
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>

<style>

/* 
 * The map page uses flexbox to fit on one screen (no page scroll).
 * Tailwind handles most styling; these few rules handle the layout
 * that Tailwind can't cleanly express (100vh flex chain).
 */

body.map-page {
    height: 100vh;
    overflow: hidden;
}

body.map-page .admin-layout {
    height: 100vh;
    display: flex;
    overflow: hidden;
}

body.map-page .main-content {
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 16px 24px;
    box-sizing: border-box;
}

body.map-page .dashboard-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin: 0;
    min-height: 0;
}

body.map-page .dashboard-card .card-header {
    flex-shrink: 0;
}

body.map-page #map {
    flex: 1;
    width: 100%;
    min-height: 0;
    border-radius: 8px;
    overflow: hidden;
}

/* Leaflet legend — Tailwind classes don't reach into Leaflet's own DOM */
.map-legend {
    background: white;
    padding: 8px 12px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    font-size: 12px;
    line-height: 1.5;
}

.map-legend h4 {
    margin: 0 0 4px 0;
    font-size: 12px;
    font-weight: 600;
}

.legend-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
    border: 2px solid white;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.3);
}

</style>

</head>

<body class="map-page">

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
            <a href="map.php" class="menu-item active">🗺️ <span>Map View</span></a>
            <a href="personnel.php" class="menu-item">👥 <span>Personnel</span></a>
            <a href="users.php" class="menu-item">👤 <span>Users</span></a>
            <a href="settings.php" class="menu-item">⚙️ <span>Settings</span></a>
            <a href="logout.php" class="menu-item logout-menu">🚪 <span>Logout</span></a>
        </nav>

    </aside>

    <main class="main-content">

        <!-- Header — now using Tailwind classes -->
        <header class="flex-shrink-0 mb-3 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 m-0">
                    Map View
                </h1>
                <p class="text-sm text-slate-500 m-0 mt-1">
                    View reported issues by location in Sampaloc.
                </p>
            </div>

            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-100 text-blue-700 text-sm">
                <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                <strong><?= count($reports) ?></strong>
                <span>report<?= count($reports) === 1 ? '' : 's' ?></span>
            </div>
        </header>

        <!-- Card -->
        <section class="dashboard-card bg-white rounded-xl shadow-sm border border-slate-200 p-4">

            <div class="card-header flex-shrink-0 mb-3">
                <h2 class="text-lg font-semibold text-slate-800 m-0">
                    Report Locations
                </h2>
                <p class="text-sm text-slate-500 m-0 mt-0.5">
                    CitiFix reports within Sampaloc, Manila.
                </p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="mb-3 px-3 py-2 rounded-md bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div id="map"></div>

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
    "in progress": "#3b82f6",
    "in_progress": "#3b82f6",
    "resolved":    "#22c55e"
};

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
            color: "#2563eb",
            weight: 2,
            opacity: 0.8,
            fillColor: "#2563eb",
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

reports.forEach(function(report) {

    const statusKey = report.status.toLowerCase();
    const color = STATUS_COLORS[statusKey] || "#ef4444";

    const marker = L.circleMarker(
        [report.latitude, report.longitude],
        {
            radius: 8,
            fillColor: color,
            color: "#ffffff",
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        }
    );

    marker.bindPopup(
        "<div style='min-width:200px'>" +
        "<strong>" + escapeHtml(report.category) + "</strong><br>" +
        "<small>" + escapeHtml(report.description) + "</small><br><br>" +
        "<span style='text-transform:capitalize;color:" + color + ";font-weight:600'>" +
            escapeHtml(report.status) +
        "</span>" +
        "</div>"
    );

    marker.addTo(markerLayer);

});

const legend = L.control({ position: "bottomright" });

legend.onAdd = function() {

    const div = L.DomUtil.create("div", "map-legend");

    div.innerHTML =
        "<h4>Legend</h4>" +
        "<div><span class='legend-dot' style='background:#f59e0b'></span>Pending</div>" +
        "<div><span class='legend-dot' style='background:#3b82f6'></span>In Progress</div>" +
        "<div><span class='legend-dot' style='background:#22c55e'></span>Resolved</div>";

    return div;
};

legend.addTo(map);

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

setTimeout(function() { map.invalidateSize(); }, 100);
window.addEventListener("resize", function() { map.invalidateSize(); });

</script>

</body>
</html>