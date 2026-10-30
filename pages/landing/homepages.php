<?php

require_once __DIR__ . '/../utils/announcements_util.php';
require_once __DIR__ . '/../utils/icons_util.php';

$pageTitle   = 'CitiFix Manila | Report problems in Sampaloc and track them to resolution';
$currentPage = 'home';

$announcements = cf_fetch_announcements(8);

// Data for the detail modal (rendered with textContent on the client, so it is safe from injection).
$annModalData = array_map(static function ($p) {
    return [
        'text'   => $p['text'],
        'image'  => $p['image'],
        'author' => $p['author'],
        'full'   => $p['full'],
    ];
}, $announcements);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="CitiFix lets residents of District 4 (Sampaloc), Manila report urban problems with a photo and exact location, then track every status update.">
<style>
    .ann-track { scrollbar-width: none; -ms-overflow-style: none; }
    .ann-track::-webkit-scrollbar { display: none; }
</style>
</head>

<body class="bg-white text-slate-700 antialiased">

<?php require __DIR__ . '/navbar.php'; ?>

<main>

    <!-- HERO -->
    <section class="relative flex min-h-[calc(100svh-4.5rem)] items-center overflow-hidden border-b border-brand-100 bg-brand-50">
        <!-- Manila City Hall background (decorative) -->
        <img src="../../images/manilacityhallbackground.jpg" alt="" aria-hidden="true"
             class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center opacity-20">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-brand-50/80 via-brand-50/30 to-transparent" aria-hidden="true"></div>

        <div class="su-container relative grid w-full items-center gap-12 py-14 sm:py-20 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16 lg:py-24">

            <div>
                <h1 class="su-hero-title text-slate-900">
                    Report problems in Sampaloc and follow them until they&rsquo;re fixed.
                </h1>
                <p class="su-lead mt-6 max-w-xl text-slate-600">
                    Send a report with a photo and your exact location. CitiFix routes it to the right department and shows you every status update.
                </p>

                <div class="mt-8 flex flex-col gap-3 xs:flex-row">
                    <a href="download.php" class="inline-flex items-center justify-center rounded-xl bg-brand-700 px-6 py-3.5 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-800">
                        Download the app
                    </a>
                    <a href="about.php" class="inline-flex items-center justify-center rounded-xl border border-brand-200 bg-white px-6 py-3.5 text-base font-semibold text-brand-800 transition-colors hover:bg-brand-100">
                        About CitiFix
                    </a>
                </div>

                <p class="mt-5 text-sm text-slate-500">
                    Available for Android. For non-emergency concerns only.
                </p>
            </div>

            <!-- Phone mockup: a report with its status timeline -->
            <div class="relative mx-auto w-[17rem] sm:w-[19rem]" aria-hidden="true">
                <div class="overflow-hidden rounded-[2.5rem] border-[10px] border-slate-900 bg-white shadow-2xl">
                    <div class="bg-brand-700 px-5 pb-4 pt-7 text-white">
                        <p class="text-xs text-brand-100">My Reports</p>
                        <p class="mt-0.5 text-lg font-bold leading-snug">Pothole on Espa&ntilde;a Blvd</p>
                    </div>

                    <div class="space-y-4 p-4">
                        <div class="flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2">
                            <span class="text-xs text-slate-500">Reference</span>
                            <span class="font-mono text-xs font-semibold text-slate-800">7F3A9C2B</span>
                        </div>

                        <div class="flex items-start gap-2 text-sm text-slate-600">
                            <span>📍</span>
                            <span>Sampaloc, Manila<br><span class="text-xs text-slate-400">Inside coverage area</span></span>
                        </div>

                        <div>
                            <p class="mb-3 text-xs font-semibold text-slate-500">Status history</p>
                            <div class="space-y-3.5">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded-full bg-amber-500"></span>
                                    <div><p class="text-sm font-semibold text-slate-800">Pending</p><p class="text-xs text-slate-400">Oct 24, 9:12 AM</p></div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded-full bg-brand-500"></span>
                                    <div><p class="text-sm font-semibold text-slate-800">In Progress</p><p class="text-xs text-slate-400">Oct 25, 1:40 PM</p></div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded-full bg-green-500"></span>
                                    <div><p class="text-sm font-semibold text-slate-800">Resolved</p><p class="text-xs text-slate-400">Oct 27, 10:05 AM</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="absolute -left-4 bottom-12 hidden w-52 rounded-xl border border-slate-200 bg-white p-3 shadow-lg sm:block lg:-left-10">
                    <p class="text-xs font-semibold text-slate-800">🔔 Status update</p>
                    <p class="mt-0.5 text-xs text-slate-500">Your report is now in progress.</p>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($announcements)): ?>
    <!-- ANNOUNCEMENTS -->
    <section class="su-section" aria-labelledby="ann-heading">
        <div class="su-container">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <h2 id="ann-heading" class="su-h2 text-slate-900">Announcements</h2>
                    <p class="su-lead mt-4 text-slate-600">Updates posted by CitiFix administrators.</p>
                </div>

                <div id="ann-nav" class="flex shrink-0 items-center gap-2">
                    <button type="button" id="ann-prev" aria-label="Previous announcements"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" id="ann-next" aria-label="Next announcements"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <div id="ann-track" class="ann-track mt-10 flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth pb-2" tabindex="0" aria-label="Announcements carousel">
                <?php foreach ($announcements as $i => $post):
                    $excerpt = mb_strimwidth($post['text'], 0, 150, '…', 'UTF-8');
                ?>
                    <button type="button" data-ann="<?= (int) $i ?>"
                            class="group flex w-[17rem] shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition-shadow hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 sm:w-80">
                        <?php if ($post['image'] !== ''): ?>
                            <span class="block h-44 w-full overflow-hidden bg-slate-100">
                                <img src="<?= htmlspecialchars($post['image']) ?>" alt="" loading="lazy"
                                     class="h-full w-full object-cover"
                                     onerror="this.parentNode.className='flex h-44 w-full items-center justify-center bg-brand-50 text-4xl';this.parentNode.textContent='📣'">
                            </span>
                        <?php else: ?>
                            <span class="flex h-44 w-full items-center justify-center bg-brand-50 text-4xl" aria-hidden="true">📣</span>
                        <?php endif; ?>

                        <span class="flex flex-1 flex-col p-5">
                            <span class="text-xs text-slate-500">
                                <?= htmlspecialchars($post['author']) ?><?= $post['ago'] !== '' ? ' &middot; ' . htmlspecialchars($post['ago']) : '' ?>
                            </span>
                            <span class="mt-2 block break-words text-sm leading-relaxed text-slate-700 sm:text-base">
                                <?= $excerpt !== '' ? htmlspecialchars($excerpt) : 'Photo announcement' ?>
                            </span>
                            <span class="mt-4 text-sm font-semibold text-brand-700 group-hover:text-brand-800">Read more</span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ANNOUNCEMENT DETAIL MODAL -->
    <div id="ann-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-4" role="dialog" aria-modal="true" aria-labelledby="ann-modal-author">
        <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <button type="button" id="ann-close" aria-label="Close announcement"
                    class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-slate-900/70 text-xl leading-none text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300">&times;</button>

            <a id="ann-modal-imglink" href="#" target="_blank" rel="noopener" class="hidden border-b border-slate-100 bg-slate-100">
                <img id="ann-modal-img" src="" alt="Announcement photo" class="max-h-[26rem] w-full object-cover">
            </a>

            <div class="p-6 sm:p-8">
                <p id="ann-modal-author" class="text-base font-bold text-slate-900"></p>
                <p id="ann-modal-date" class="mt-0.5 text-sm text-slate-500"></p>
                <p id="ann-modal-text" class="mt-5 whitespace-pre-wrap break-words text-base leading-relaxed text-slate-700"></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- WHAT YOU CAN REPORT -->
    <section class="su-section border-t border-slate-200">
        <div class="su-container grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
            <div>
                <h2 class="su-h2 text-slate-900">Five everyday problems, one place to report them.</h2>
                <p class="su-lead mt-5 text-slate-600">
                    Pick a category, describe what you see, and CitiFix sends it to the department that handles it.
                </p>
            </div>

            <ul class="divide-y divide-slate-200 border-y border-slate-200">
                <?php
                $categories = [
                    ['cone-striped', 'Potholes',            'Damaged roads and sidewalks that slow traffic or put people at risk.'],
                    ['droplet',      'Flooding',             'Clogged drains and streets that stay under water.'],
                    ['trash3',       'Garbage',              'Uncollected or illegally dumped waste.'],
                    ['car-front',    'Illegal parking',      'Vehicles blocking roads, driveways, or walkways.'],
                    ['lightbulb',    'Broken streetlights',  'Dark streets and corners that need repair.'],
                ];
                foreach ($categories as [$icon, $name, $desc]): ?>
                    <li class="flex items-start gap-4 py-5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><?= cf_icon($icon, 'h-5 w-5') ?></span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900"><?= $name ?></h3>
                            <p class="mt-0.5 text-sm text-slate-600 sm:text-base"><?= $desc ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- FEATURES -->
    <section class="bg-slate-50 su-section">
        <div class="su-container">
            <div class="max-w-2xl">
                <h2 class="su-h2 text-slate-900">Made for reporting from your phone.</h2>
                <p class="su-lead mt-5 text-slate-600">Everything you need to send a clear report and know what happens next.</p>
            </div>

            <div class="mt-12 grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                <?php
                $features = [
                    ['camera',  'bg-brand-100 text-brand-700',   'Photo and exact location', 'Attach a photo and pin the spot with GPS so staff know exactly where to go.'],
                    ['geo-alt', 'bg-green-100 text-green-700',   'Sampaloc coverage check',  'Reports are checked against the Sampaloc boundary, so they reach the right district.'],
                    ['bell',    'bg-purple-100 text-purple-700', 'Status notifications',     'Get notified when your report moves from Pending to In Progress to Resolved.'],
                ];
                foreach ($features as [$icon, $tint, $title, $text]): ?>
                    <div>
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl <?= $tint ?>"><?= cf_icon($icon, 'h-6 w-6') ?></span>
                        <h3 class="mt-4 text-lg font-bold text-slate-900"><?= $title ?></h3>
                        <p class="mt-1.5 text-slate-600"><?= $text ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="border-t border-slate-200 bg-brand-50">
        <div class="su-container flex flex-col items-start justify-between gap-6 py-14 md:flex-row md:items-center md:py-16">
            <div>
                <h2 class="su-h2 text-slate-900">See something that needs fixing?</h2>
                <p class="su-lead mt-3 text-slate-600">Download CitiFix and send your first report in a few taps.</p>
            </div>
            <a href="download.php" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-brand-700 px-7 py-4 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-800">
                Download the app
            </a>
        </div>
    </section>

</main>

<?php require __DIR__ . '/footer.php'; ?>

<?php if (!empty($announcements)): ?>
<script>
(function () {
    var posts = <?= json_encode($annModalData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

    var track = document.getElementById('ann-track');
    var nav   = document.getElementById('ann-nav');
    var prev  = document.getElementById('ann-prev');
    var next  = document.getElementById('ann-next');

    var modal    = document.getElementById('ann-modal');
    var closeBtn = document.getElementById('ann-close');
    var imgLink  = document.getElementById('ann-modal-imglink');
    var img      = document.getElementById('ann-modal-img');
    var author   = document.getElementById('ann-modal-author');
    var date     = document.getElementById('ann-modal-date');
    var text     = document.getElementById('ann-modal-text');
    var lastFocus = null;

    /* ---- Carousel ---- */
    function step() {
        var card = track.querySelector('[data-ann]');
        return card ? card.getBoundingClientRect().width + 20 : 300;
    }

    function updateNav() {
        var max = track.scrollWidth - track.clientWidth - 2;
        nav.style.display = max <= 0 ? 'none' : 'flex';
        prev.disabled = track.scrollLeft <= 2;
        next.disabled = track.scrollLeft >= max;
    }

    prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
    next.addEventListener('click', function () { track.scrollBy({ left:  step(), behavior: 'smooth' }); });
    track.addEventListener('scroll', updateNav, { passive: true });
    window.addEventListener('resize', updateNav);
    updateNav();

    /* ---- Detail modal ---- */
    function openModal(index) {
        var p = posts[index];
        if (!p) return;

        lastFocus = document.activeElement;

        author.textContent = p.author;
        date.textContent   = p.full;
        text.textContent   = p.text;
        text.style.display = p.text === '' ? 'none' : '';

        if (p.image) {
            img.src = p.image;
            imgLink.href = p.image;
            imgLink.classList.remove('hidden');
        } else {
            img.removeAttribute('src');
            imgLink.classList.add('hidden');
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        img.removeAttribute('src');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    track.addEventListener('click', function (e) {
        var card = e.target.closest('[data-ann]');
        if (card) openModal(parseInt(card.getAttribute('data-ann'), 10));
    });

    closeBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'Tab') { e.preventDefault(); closeBtn.focus(); }   // keep focus inside the dialog
    });
})();
</script>
<?php endif; ?>

</body>
</html>