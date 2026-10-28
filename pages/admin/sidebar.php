<?php
/**
 * sidebar.php: shared admin navigation.
 * Set $activePage ('dashboard' | 'reports' | 'map' | 'announcement' | 'personnel' | 'users' | 'settings')
 * before including. Needs auth.php to have run ($IS_SUPER_ADMIN, $ADMIN_NAME, $ADMIN_ROLE).
 * Self-contained: scoped CSS (cf-*), inline SVG, small JS. Works with or without Tailwind.
 * Wrap the page body in <div class="cf-content"> so it sits beside the sidebar.
 */
$activePage = $activePage ?? '';
$sbName     = $ADMIN_NAME ?? 'Administrator';
$sbRole     = ucfirst(str_replace('_', ' ', $ADMIN_ROLE ?? 'staff'));

$icons = [
    'home'         => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    'reports'      => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 12h6M9 16h4',
    'map'          => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
    'announcement' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
    'personnel'    => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    'users'        => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    'settings'     => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'logout'       => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
    'chevron'      => 'M15 19l-7-7 7-7',
];

$groups = [
    'Overview'   => [['dashboard', 'Home', 'dashboard.php', 'home']],
    'Operations' => [
        ['reports',      'Reports',      'reports.php',      'reports'],
        ['map',          'Map View',     'map.php',          'map'],
        ['announcement', 'Announcement', 'announcement.php', 'announcement'],
    ],
];
if (!empty($IS_SUPER_ADMIN)) {
    $groups['Administration'] = [
        ['personnel', 'Personnel', 'personnel.php', 'personnel'],
        ['users',     'Users',     'users.php',     'users'],
        ['settings',  'Settings',  'settings.php',  'settings'],
    ];
}

function cf_icon(string $d): string
{
    return '<svg class="cf-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}
?>
<script>try{if(localStorage.getItem('cf-collapsed')==='1')document.documentElement.classList.add('cf-collapsed');}catch(e){}</script>

<style>
:root { --cf-w: 16rem; --cf-w-min: 4.75rem; --cf-sw: var(--cf-w); }
html.cf-collapsed { --cf-sw: var(--cf-w-min); }

.cf-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 60; width: var(--cf-w); display: flex; flex-direction: column; background: #1e3a8a; color: #bfdbfe; padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px); transform: translateX(-100%); transition: transform .28s ease, width .28s ease; font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif; }
.cf-sidebar.cf-open { transform: translateX(0); box-shadow: 0 0 40px rgba(15, 23, 42, .35); }
.cf-content { min-width: 0; transition: margin-left .28s ease; }
.cf-overlay { position: fixed; inset: 0; z-index: 55; background: rgba(15, 23, 42, .55); opacity: 0; pointer-events: none; transition: opacity .25s ease; }
.cf-overlay.cf-show { opacity: 1; pointer-events: auto; }

.cf-brand { display: flex; align-items: center; gap: .75rem; padding: 1.1rem 1.1rem 1rem; min-height: 4.5rem; }
.cf-brand img { width: 2.25rem; height: 2.25rem; object-fit: contain; flex-shrink: 0; }
.cf-brand-text { line-height: 1.1; white-space: nowrap; }
.cf-brand-text b { display: block; font-size: 1.1rem; font-weight: 800; color: #fff; letter-spacing: -.01em; }
.cf-brand-text span { font-size: .72rem; color: #93c5fd; }

.cf-nav { flex: 1; overflow-y: auto; padding: .25rem .75rem 1rem; }
.cf-group-title { margin: 1.1rem .5rem .4rem; font-size: .72rem; font-weight: 600; color: #93c5fd; white-space: nowrap; }
.cf-link { position: relative; display: flex; align-items: center; gap: .8rem; padding: .7rem .8rem; margin-bottom: .15rem; border-radius: .75rem; color: #bfdbfe; font-size: .9rem; font-weight: 600; text-decoration: none; white-space: nowrap; transition: background .2s ease, color .2s ease; }
.cf-link:hover { background: rgba(255, 255, 255, .09); color: #fff; }
.cf-link:hover .cf-icon { transform: translateX(2px) scale(1.06); }
.cf-icon { width: 1.35rem; height: 1.35rem; flex-shrink: 0; transition: transform .2s ease; }
.cf-link.is-active { background: rgba(255, 255, 255, .15); color: #fff; font-weight: 700; }
.cf-link.is-active::before { content: ""; position: absolute; left: -.75rem; top: 22%; bottom: 22%; width: 4px; border-radius: 0 4px 4px 0; background: #60a5fa; transform-origin: center; animation: cf-bar .45s ease both; }
@keyframes cf-bar { from { transform: scaleY(0); } to { transform: scaleY(1); } }

.cf-foot { padding: .75rem; border-top: 1px solid rgba(255, 255, 255, .12); }
.cf-user { display: flex; align-items: center; gap: .75rem; padding: .5rem .5rem .75rem; }
.cf-avatar { width: 2.3rem; height: 2.3rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 9999px; background: #3b82f6; color: #fff; font-weight: 800; }
.cf-user-text { min-width: 0; line-height: 1.2; }
.cf-user-text b { display: block; font-size: .85rem; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cf-user-text span { font-size: .72rem; color: #93c5fd; }

.cf-collapse { display: none; position: absolute; top: 1.35rem; right: -.8rem; width: 1.6rem; height: 1.6rem; align-items: center; justify-content: center; border: 1px solid #dbeafe; border-radius: 9999px; background: #fff; color: #1d4ed8; cursor: pointer; box-shadow: 0 1px 4px rgba(15, 23, 42, .2); }
.cf-collapse .cf-icon { width: .95rem; height: .95rem; transition: transform .28s ease; }

@media (min-width: 1024px) {
    .cf-sidebar { transform: none; width: var(--cf-sw); box-shadow: none; }
    .cf-content { margin-left: var(--cf-sw); }
    .cf-collapse { display: flex; }
    .cf-overlay { display: none; }
    .cf-label, .cf-group-title, .cf-brand-text, .cf-user-text { transition: opacity .2s ease; }
    html.cf-collapsed .cf-label,
    html.cf-collapsed .cf-brand-text,
    html.cf-collapsed .cf-user-text { opacity: 0; width: 0; overflow: hidden; }
    html.cf-collapsed .cf-group-title { opacity: 0; height: 0; margin: .6rem 0; overflow: hidden; }
    html.cf-collapsed .cf-link { justify-content: center; gap: 0; }
    html.cf-collapsed .cf-link.is-active::before { left: -.75rem; }
    html.cf-collapsed .cf-collapse .cf-icon { transform: rotate(180deg); }
    html.cf-collapsed .cf-user { justify-content: center; }
    html.cf-collapsed .cf-brand { justify-content: center; padding-inline: 0; }
}

@media (prefers-reduced-motion: reduce) {
    .cf-sidebar, .cf-content, .cf-overlay, .cf-link, .cf-icon { transition: none !important; }
    .cf-link.is-active::before { animation: none; }
}
</style>

<div class="cf-overlay" id="cf-overlay"></div>

<aside class="cf-sidebar" id="cf-sidebar" aria-label="Admin navigation">

    <button type="button" class="cf-collapse" id="cf-collapse" aria-label="Collapse sidebar"><?= cf_icon($icons['chevron']) ?></button>

    <a href="dashboard.php" class="cf-brand" style="text-decoration:none;">
        <img src="../../images/logo.png" alt="CitiFix Manila">
        <span class="cf-brand-text"><b>CitiFix</b><span>Manila</span></span>
    </a>

    <nav class="cf-nav">
        <?php foreach ($groups as $title => $links): ?>
            <p class="cf-group-title"><?= htmlspecialchars($title) ?></p>
            <?php foreach ($links as [$key, $label, $href, $icon]): ?>
                <a href="<?= $href ?>" title="<?= $label ?>"
                   class="cf-link<?= $activePage === $key ? ' is-active' : '' ?>"
                   <?= $activePage === $key ? 'aria-current="page"' : '' ?>>
                    <?= cf_icon($icons[$icon]) ?>
                    <span class="cf-label"><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="cf-foot">
        <div class="cf-user">
            <div class="cf-avatar"><?= htmlspecialchars(strtoupper(substr($sbName, 0, 1))) ?></div>
            <div class="cf-user-text">
                <b><?= htmlspecialchars($sbName) ?></b>
                <span><?= htmlspecialchars($sbRole) ?></span>
            </div>
        </div>
        <a href="logout.php" title="Logout" class="cf-link">
            <?= cf_icon($icons['logout']) ?>
            <span class="cf-label">Logout</span>
        </a>
    </div>
</aside>

<script>
(function () {
    var root = document.documentElement;
    var sb = document.getElementById('cf-sidebar');
    var ov = document.getElementById('cf-overlay');
    var col = document.getElementById('cf-collapse');

    function setOpen(open) {
        sb.classList.toggle('cf-open', open);
        ov.classList.toggle('cf-show', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    document.querySelectorAll('[data-cf-open]').forEach(function (b) {
        b.addEventListener('click', function () { setOpen(true); });
    });
    ov.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth >= 1024) setOpen(false); });

    col.addEventListener('click', function () {
        var c = root.classList.toggle('cf-collapsed');
        col.setAttribute('aria-label', c ? 'Expand sidebar' : 'Collapse sidebar');
        try { localStorage.setItem('cf-collapsed', c ? '1' : '0'); } catch (e) {}
    });
})();
</script>