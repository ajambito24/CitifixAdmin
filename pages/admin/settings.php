<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';   // needed by the notification bell ($db)

// 🔒 Super admin only
if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can access settings.');
}

// ---- Design-only helpers (no data logic) ----
$activePage = 'settings';

$settingsRows = [
    ['System Name',   'CitiFix Manila'],
    ['Target Area',   'District 4 - Sampaloc, Manila'],
    ['Database',      'Firebase Firestore'],
    ['Administrator', $ADMIN_EMAIL],
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Settings</title>
</head>

<body class="bg-slate-50 text-slate-700 antialiased">

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="cf-content">

    <!-- TOP BAR -->
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="flex h-16 items-center justify-between gap-3 px-[var(--su-gutter)]">

            <div class="flex min-w-0 items-center gap-3">
                <button type="button" data-cf-open aria-label="Open menu"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">Settings</h1>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">CitiFix administrator settings</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <?php require __DIR__ . '/bell.php'; ?>
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

    <main class="mx-auto w-full max-w-[96rem] px-[var(--su-gutter)] py-6 sm:py-8">

        <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-sm">

            <div class="p-4 sm:p-6">
                <h2 class="text-lg font-bold text-slate-900 sm:text-xl">System Information</h2>
                <p class="text-xs text-slate-500 sm:text-sm">Current CitiFix deployment details</p>
            </div>

            <dl class="divide-y divide-slate-100 border-t border-slate-100">
                <?php foreach ($settingsRows as [$label, $value]): ?>
                    <div class="flex flex-col gap-1 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500"><?= htmlspecialchars($label) ?></dt>
                        <dd class="min-w-0 break-words text-sm font-bold text-slate-900 sm:text-right"><?= htmlspecialchars($value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

        </section>

    </main>
</div>

</body>
</html>