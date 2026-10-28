<?php

$pageTitle   = 'About | CitiFix Manila';
$currentPage = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="Why CitiFix exists, what it does, and what it does not cover for District 4 (Sampaloc), Manila.">
</head>

<body class="bg-white text-slate-700 antialiased">

<?php require __DIR__ . '/navbar.php'; ?>

<main>

    <!-- PAGE HEADER -->
    <section class="border-b border-brand-100 bg-brand-50">
        <div class="su-container py-14 sm:py-20 lg:py-24">
            <h1 class="su-hero-title max-w-4xl text-slate-900">
                A simpler way to bring community problems to the city.
            </h1>
            <p class="su-lead mt-6 max-w-2xl text-slate-600">
                CitiFix is a mobile and web platform that connects residents of District 4 (Sampaloc), Manila with the local government personnel who handle their concerns.
            </p>
        </div>
    </section>

    <!-- WHY -->
    <section class="su-section">
        <div class="su-container grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
            <h2 class="su-h2 text-slate-900">Reporting a problem shouldn&rsquo;t be this hard.</h2>
            <div class="space-y-5 text-base leading-relaxed text-slate-600 sm:text-lg">
                <p>
                    Flooding, illegal parking, garbage, potholes, and damaged public facilities affect neighborhoods across Sampaloc every day. Reporting them usually means phone calls, social media posts, or a trip to the barangay office, and these are slow, scattered, and hard to follow up on.
                </p>
                <p>
                    The result is delayed responses and little visibility. Residents don&rsquo;t know whether anyone saw their report, and staff have no single place to see what is waiting.
                </p>
                <p>
                    CitiFix puts both sides in one system. Residents report from their phones with a photo and location. Staff review, route, and update reports from a web dashboard.
                </p>
            </div>
        </div>
    </section>

    <!-- WHAT IT DOES -->
    <section class="bg-slate-50 su-section">
        <div class="su-container">
            <h2 class="su-h2 max-w-2xl text-slate-900">What CitiFix does</h2>

            <div class="mt-12 grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-7 sm:p-9">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-100 text-2xl">📱</span>
                    <h3 class="mt-5 text-xl font-bold text-slate-900">For residents</h3>
                    <ul class="mt-4 space-y-2.5 text-slate-600">
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>Submit reports with a category, description, photo, and GPS location</span></li>
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>Save reports offline and sync them later</span></li>
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>Follow each report&rsquo;s status and get notifications</span></li>
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>Ask the chatbot for help with filing a report</span></li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-7 sm:p-9">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 text-2xl">🖥️</span>
                    <h3 class="mt-5 text-xl font-bold text-slate-900">For city staff</h3>
                    <ul class="mt-4 space-y-2.5 text-slate-600">
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>Review incoming reports in one dashboard</span></li>
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>Route reports to the correct department by category</span></li>
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>See report locations on a map</span></li>
                        <li class="flex gap-3"><span class="text-brand-600">✓</span><span>Update statuses with role-based access and activity logs</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- LIMITS -->
    <section class="su-section">
        <div class="su-container grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
            <div>
                <h2 class="su-h2 text-slate-900">What CitiFix doesn&rsquo;t do</h2>
                <p class="su-lead mt-5 text-slate-600">Clear limits, so you know what to expect.</p>
            </div>

            <dl class="divide-y divide-slate-200 border-y border-slate-200">
                <?php
                $limits = [
                    ['Sampaloc only',               'Reports are accepted only from locations inside the District 4 (Sampaloc) boundary.'],
                    ['Not for emergencies',         'CitiFix is for non-emergency concerns. It does not contact emergency services, so call them directly when it is urgent.'],
                    ['No automatic photo analysis', 'Photos are attached for staff to review. CitiFix does not use image recognition to identify or classify problems.'],
                    ['No payments',                 'The app does not process fines, permits, or other municipal fees.'],
                    ['Works on its own',            'CitiFix does not connect to national government systems or other local government platforms.'],
                ];
                foreach ($limits as [$term, $desc]): ?>
                    <div class="grid gap-1 py-5 sm:grid-cols-[14rem_1fr] sm:gap-6">
                        <dt class="font-bold text-slate-900"><?= $term ?></dt>
                        <dd class="text-slate-600"><?= $desc ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <!-- CTA -->
    <section class="border-t border-slate-200 bg-brand-50">
        <div class="su-container flex flex-col items-start justify-between gap-6 py-14 md:flex-row md:items-center md:py-16">
            <div>
                <h2 class="su-h2 text-slate-900">Ready to send your first report?</h2>
                <p class="su-lead mt-3 text-slate-600">See how the app works, step by step.</p>
            </div>
            <a href="download.php" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-brand-700 px-7 py-4 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-800">
                Download and how to use
            </a>
        </div>
    </section>

</main>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>