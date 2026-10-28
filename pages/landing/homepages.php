<?php

$pageTitle   = 'CitiFix Manila | Report problems in Sampaloc and track them to resolution';
$currentPage = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="CitiFix lets residents of District 4 (Sampaloc), Manila report urban problems with a photo and exact location, then track every status update.">
</head>

<body class="bg-white text-slate-700 antialiased">

<?php require __DIR__ . '/navbar.php'; ?>

<main>

    <!-- HERO -->
    <section class="overflow-hidden border-b border-brand-100 bg-brand-50">
        <div class="su-container grid items-center gap-12 py-14 sm:py-20 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16 lg:py-24">

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

    <!-- WHAT YOU CAN REPORT -->
    <section class="su-section">
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
                    ['🕳️', 'Potholes',            'Damaged roads and sidewalks that slow traffic or put people at risk.'],
                    ['🌊', 'Flooding',             'Clogged drains and streets that stay under water.'],
                    ['🗑️', 'Garbage',              'Uncollected or illegally dumped waste.'],
                    ['🚗', 'Illegal parking',      'Vehicles blocking roads, driveways, or walkways.'],
                    ['💡', 'Broken streetlights',  'Dark streets and corners that need repair.'],
                ];
                foreach ($categories as [$icon, $name, $desc]): ?>
                    <li class="flex items-start gap-4 py-5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-xl"><?= $icon ?></span>
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

            <div class="mt-12 grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                <?php
                $features = [
                    ['📷', 'bg-brand-100',  'Photo and exact location', 'Attach a photo and pin the spot with GPS so staff know exactly where to go.'],
                    ['🗺️', 'bg-green-100',  'Sampaloc coverage check',  'Reports are checked against the Sampaloc boundary, so they reach the right district.'],
                    ['📶', 'bg-orange-100', 'Works with a weak signal', 'Save a report offline. It syncs when your connection comes back.'],
                    ['🔔', 'bg-purple-100', 'Status notifications',     'Get notified when your report moves from Pending to In Progress to Resolved.'],
                    ['🏢', 'bg-brand-100',  'Sent to the right department', 'Each report is routed by category to the department that handles it.'],
                    ['💬', 'bg-orange-100', 'Chatbot help',             'Ask the in-app chatbot how to file a report or what a status means.'],
                ];
                foreach ($features as [$icon, $tint, $title, $text]): ?>
                    <div>
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl text-2xl <?= $tint ?>"><?= $icon ?></span>
                        <h3 class="mt-4 text-lg font-bold text-slate-900"><?= $title ?></h3>
                        <p class="mt-1.5 text-slate-600"><?= $text ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CITY STAFF -->
    <section class="su-section">
        <div class="su-container">
            <div class="grid items-center gap-8 rounded-3xl bg-brand-900 p-8 text-white sm:p-12 lg:grid-cols-[1.2fr_0.8fr] lg:gap-14 lg:p-16">
                <div>
                    <h2 class="su-h2">Built for city staff, too.</h2>
                    <p class="su-lead mt-5 max-w-xl text-brand-100">
                        A web dashboard lets authorized personnel review incoming reports, see them on a map, and update their status, so residents always see progress.
                    </p>
                </div>
                <ul class="space-y-3 text-sm sm:text-base">
                    <li class="flex gap-3"><span class="text-green-400">✓</span><span>Reports separated by department</span></li>
                    <li class="flex gap-3"><span class="text-green-400">✓</span><span>Map view of report locations</span></li>
                    <li class="flex gap-3"><span class="text-green-400">✓</span><span>Role-based access and activity logs</span></li>
                </ul>
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

</body>
</html>