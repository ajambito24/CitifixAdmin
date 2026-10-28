<?php

$pageTitle   = 'Download and how to use | CitiFix Manila';
$currentPage = 'download';

// TODO: replace '#' with the real APK or store link.
$apkUrl = '#';

$steps = [
    ['Create your account',           'Sign up with your details, then log in.'],
    ['Choose a category',             'Pick the type of problem: pothole, flooding, garbage, illegal parking, or broken streetlight.'],
    ['Describe it and add a photo',   'Write what is wrong and attach a clear photo so staff can see the problem.'],
    ['Pin the location',              'Use your current location or move the pin. Reports are accepted only inside Sampaloc.'],
    ['Review and submit',             'Check the details, then send. You receive a reference number for your report.'],
    ['Track the status',              'Open My Reports to see whether it is Pending, In Progress, or Resolved. You also get a notification when it changes.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="Download the CitiFix app and learn how to report a problem in six steps.">
</head>

<body class="bg-white text-slate-700 antialiased">

<?php require __DIR__ . '/navbar.php'; ?>

<main>

    <!-- HEADER + DOWNLOAD -->
    <section class="border-b border-brand-100 bg-brand-50">
        <div class="su-container grid items-center gap-8 py-14 sm:py-20 lg:grid-cols-[1.2fr_0.8fr] lg:gap-16 lg:py-24">
            <div>
                <h1 class="su-hero-title text-slate-900">Get CitiFix on your phone.</h1>
                <p class="su-lead mt-6 max-w-xl text-slate-600">
                    Download the app, create an account, and send your first report in a few minutes.
                </p>
            </div>

            <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-semibold text-slate-500">Android app</p>
                <a href="<?= htmlspecialchars($apkUrl) ?>"
                   class="mt-4 flex items-center justify-center gap-3 rounded-xl bg-brand-700 px-6 py-4 text-base font-semibold text-white transition-colors hover:bg-brand-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14"/></svg>
                    Download CitiFix
                </a>
                <p class="mt-4 text-sm text-slate-500">
                    You need an Android phone and an internet connection to sign up.
                </p>
            </div>
        </div>
    </section>

    <!-- HOW TO USE -->
    <section class="su-section">
        <div class="su-container">
            <h2 class="su-h2 max-w-2xl text-slate-900">How to report a problem</h2>
            <p class="su-lead mt-4 max-w-2xl text-slate-600">Six steps from spotting the problem to seeing it resolved.</p>

            <div class="mt-12 grid gap-12 lg:grid-cols-[1fr_22rem] lg:gap-16">

                <ol class="relative">
                    <?php foreach ($steps as $i => [$title, $text]): ?>
                        <li class="relative flex gap-5 pb-10 last:pb-0">
                            <?php if ($i < count($steps) - 1): ?>
                                <span class="absolute left-[1.1875rem] top-11 bottom-0 w-px bg-brand-200" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-700 text-sm font-bold text-white"><?= $i + 1 ?></span>
                            <div class="pt-1.5">
                                <h3 class="text-lg font-bold text-slate-900"><?= $title ?></h3>
                                <p class="mt-1 max-w-xl text-slate-600"><?= $text ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <aside class="h-fit rounded-2xl bg-slate-50 p-6 sm:p-8 lg:sticky lg:top-24">
                    <h3 class="text-lg font-bold text-slate-900">Good to know</h3>
                    <ul class="mt-4 space-y-4 text-sm leading-relaxed text-slate-600">
                        <li><span class="font-semibold text-slate-800">Sampaloc only.</span> Reports outside District 4 can&rsquo;t be submitted.</li>
                        <li><span class="font-semibold text-slate-800">Not for emergencies.</span> Contact emergency services directly when it is urgent.</li>
                        <li><span class="font-semibold text-slate-800">Weak signal?</span> Save the report offline and it syncs when you are back online.</li>
                        <li><span class="font-semibold text-slate-800">Need help?</span> Ask the in-app chatbot how to file a report.</li>
                    </ul>
                </aside>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="border-t border-slate-200 bg-brand-50">
        <div class="su-container flex flex-col items-start justify-between gap-6 py-14 md:flex-row md:items-center md:py-16">
            <div>
                <h2 class="su-h2 text-slate-900">Help keep Sampaloc in good shape.</h2>
                <p class="su-lead mt-3 text-slate-600">Every report you send helps staff see what needs attention.</p>
            </div>
            <a href="<?= htmlspecialchars($apkUrl) ?>" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-brand-700 px-7 py-4 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-800">
                Download CitiFix
            </a>
        </div>
    </section>

</main>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>