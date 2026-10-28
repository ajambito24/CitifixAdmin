<?php

$currentPage = $currentPage ?? '';

$navLinks = [
    'home'     => ['Home',     'homepages.php'],
    'about'    => ['About',    'about.php'],
    'download' => ['Download', 'download.php'],
];
?>
<header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur" style="padding-top: env(safe-area-inset-top, 0px);">
    <nav class="su-container flex h-16 items-center justify-between" aria-label="Main">

        <a href="homepages.php" class="flex items-center gap-2.5" aria-label="CitiFix Manila home">
            <img src="../../images/logo.png" alt="" class="h-9 w-9 object-contain">
            <span class="leading-none">
                <span class="block text-lg font-extrabold tracking-tight text-slate-900">CitiFix</span>
                <span class="block text-xs font-medium text-slate-500">Manila</span>
            </span>
        </a>

        <ul class="hidden items-center gap-1 md:flex">
            <?php foreach ($navLinks as $key => [$label, $href]): ?>
                <li>
                    <a href="<?= $href ?>"
                       class="rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors <?= $currentPage === $key ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>"
                       <?= $currentPage === $key ? 'aria-current="page"' : '' ?>>
                        <?= $label ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="flex items-center gap-2">
            <a href="download.php" class="hidden rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-800 sm:inline-flex">
                Get the app
            </a>
            <button type="button" id="nav-toggle"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 md:hidden"
                    aria-expanded="false" aria-controls="nav-menu" aria-label="Open menu">
                <svg id="nav-icon-open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg id="nav-icon-close" class="hidden h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </nav>

    <div id="nav-menu" class="hidden border-t border-slate-200 bg-white md:hidden">
        <ul class="su-container flex flex-col gap-1 py-3">
            <?php foreach ($navLinks as $key => [$label, $href]): ?>
                <li>
                    <a href="<?= $href ?>"
                       class="block rounded-lg px-3 py-3 text-base font-semibold <?= $currentPage === $key ? 'bg-brand-50 text-brand-700' : 'text-slate-700 hover:bg-slate-100' ?>">
                        <?= $label ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="pt-2">
                <a href="download.php" class="block rounded-lg bg-brand-700 px-3 py-3 text-center text-base font-semibold text-white hover:bg-brand-800">Get the app</a>
            </li>
        </ul>
    </div>
</header>

<script>
(function () {
    var btn = document.getElementById('nav-toggle');
    var menu = document.getElementById('nav-menu');
    var iconOpen = document.getElementById('nav-icon-open');
    var iconClose = document.getElementById('nav-icon-close');
    if (!btn || !menu) return;

    function setOpen(open) {
        menu.classList.toggle('hidden', !open);
        iconOpen.classList.toggle('hidden', open);
        iconClose.classList.toggle('hidden', !open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }

    btn.addEventListener('click', function () {
        setOpen(btn.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) setOpen(false);
    });
})();
</script>