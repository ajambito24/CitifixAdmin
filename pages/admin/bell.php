<?php
/**
 * bell.php: notification bell for the admin top bar.
 * Include inside the header's right-hand flex group:  <?php require __DIR__ . '/bell.php'; ?>
 * Needs auth.php and firebase.php to have run ($db, $IS_SUPER_ADMIN, $ADMIN_DEPT).
 * Needs bell_data.php and bell_feed.php in the same folder.
 *
 * - Badge = number of pending reports; dropdown = 5 newest pending.
 * - Polls bell_feed.php every 45s (only while the tab is visible) and updates without a refresh.
 * - When a pending report appears that the page hasn't seen, the bell shakes and plays a short sound.
 *   Sound only plays after the admin has clicked or pressed a key on the page (browser rule).
 * - Each item links to reports.php?open=<id>, which opens that report's modal.
 */

require_once __DIR__ . '/bell_data.php';

$bellData = cf_bell_fetch($db, !empty($IS_SUPER_ADMIN), $ADMIN_DEPT ?? null);
?>
<style>
@keyframes cf-bell-ring {
    0%, 100% { transform: rotate(0); }
    15% { transform: rotate(14deg); }
    30% { transform: rotate(-12deg); }
    45% { transform: rotate(9deg); }
    60% { transform: rotate(-6deg); }
    75% { transform: rotate(3deg); }
}
.cf-bell-ring { transform-origin: 50% 0; animation: cf-bell-ring 1s ease-in-out; }
</style>

<div class="relative" id="cf-bell">
    <button type="button" id="cf-bell-btn" aria-haspopup="true" aria-expanded="false"
            aria-label="Notifications"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-slate-100">
        <svg id="cf-bell-icon" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span id="cf-bell-badge" class="absolute -right-0.5 -top-0.5 flex hidden h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[0.65rem] font-bold leading-none text-white ring-2 ring-white"></span>
    </button>

    <div id="cf-bell-panel" role="menu"
         class="fixed inset-x-4 top-[calc(4.5rem+env(safe-area-inset-top,0px))] z-50 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-bold text-slate-900">Notifications</h2>
            <span id="cf-bell-count" class="text-xs text-slate-500"></span>
        </div>
        <div id="cf-bell-body"></div>
        <a href="reports.php" class="block border-t border-slate-100 bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50">View all reports</a>
    </div>
</div>

<script>
(function () {
    var INITIAL = <?= json_encode($bellData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var POLL_MS = 45000;

    var btn   = document.getElementById('cf-bell-btn');
    var panel = document.getElementById('cf-bell-panel');
    var badge = document.getElementById('cf-bell-badge');
    var count = document.getElementById('cf-bell-count');
    var body  = document.getElementById('cf-bell-body');
    var icon  = document.getElementById('cf-bell-icon');
    if (!btn || !panel) return;

    var known = {};
    var busy = false;
    var audioCtx = null;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function ago(ts) {
        if (ts === null || ts === undefined) return 'Unknown time';
        var diff = Math.max(0, Math.floor(Date.now() / 1000) - ts);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        if (diff < 604800) {
            var d = Math.floor(diff / 86400);
            return d + ' day' + (d === 1 ? '' : 's') + ' ago';
        }
        return new Date(ts * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function render(data) {
        var total = data.total || 0;
        var items = data.items || [];

        badge.textContent = total > 99 ? '99+' : String(total);
        badge.classList.toggle('hidden', total === 0);
        count.textContent = total + ' pending';
        btn.setAttribute('aria-label', 'Notifications, ' + total + ' pending');

        if (total === 0) {
            body.innerHTML =
                '<div class="px-4 py-10 text-center">' +
                '<div class="text-3xl">✅</div>' +
                '<p class="mt-2 text-sm text-slate-500">No pending reports. You\u2019re all caught up.</p>' +
                '</div>';
            return;
        }

        var html = '<ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">';
        items.forEach(function (item) {
            html +=
                '<li><a href="reports.php?open=' + encodeURIComponent(item.id) + '" class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-slate-50">' +
                '<span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>' +
                '<span class="min-w-0">' +
                '<span class="block truncate text-sm font-semibold text-slate-800">' + esc(item.category) + '</span>' +
                '<span class="block text-xs text-slate-500">New report &middot; ' + esc(ago(item.ts)) + '</span>' +
                '</span></a></li>';
        });
        html += '</ul>';
        if (total > items.length) {
            html += '<p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">and ' + (total - items.length) + ' more pending</p>';
        }
        body.innerHTML = html;
    }

    // ---- Alert: shake + short two-tone beep ----
    function unlockAudio() {
        try {
            if (!audioCtx) {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (AC) audioCtx = new AC();
            }
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
        } catch (e) {}
    }
    ['pointerdown', 'keydown'].forEach(function (evt) {
        document.addEventListener(evt, unlockAudio, { once: true });
    });

    function beep() {
        if (!audioCtx || audioCtx.state !== 'running') return;
        try {
            var now = audioCtx.currentTime;
            [[880, 0], [1175, 0.16]].forEach(function (tone) {
                var osc = audioCtx.createOscillator();
                var gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = tone[0];
                gain.gain.setValueAtTime(0.0001, now + tone[1]);
                gain.gain.exponentialRampToValueAtTime(0.2, now + tone[1] + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + tone[1] + 0.22);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now + tone[1]);
                osc.stop(now + tone[1] + 0.25);
            });
        } catch (e) {}
    }

    function ring() {
        icon.classList.remove('cf-bell-ring');
        void icon.getBoundingClientRect();
        icon.classList.add('cf-bell-ring');
        setTimeout(function () { icon.classList.remove('cf-bell-ring'); }, 1100);
        beep();
    }

    // ---- Live polling ----
    function poll() {
        if (busy) return;
        busy = true;
        fetch('bell_feed.php', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || data.ok !== true) return;
                var fresh = (data.items || []).filter(function (item) { return !known[item.id]; });
                (data.items || []).forEach(function (item) { known[item.id] = true; });
                render(data);
                if (fresh.length) ring();
            })
            .catch(function () { /* offline or session expired: keep the last known state */ })
            .then(function () { busy = false; });
    }

    (INITIAL.items || []).forEach(function (item) { known[item.id] = true; });
    render(INITIAL);

    setInterval(function () { if (!document.hidden) poll(); }, POLL_MS);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    // ---- Dropdown open/close ----
    function setOpen(open) {
        panel.classList.toggle('hidden', !open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(panel.classList.contains('hidden'));
    });
    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>