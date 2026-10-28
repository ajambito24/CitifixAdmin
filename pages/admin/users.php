<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';

// 🔒 Super admin only
if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can view all users.');
}

$users = [];
$error = '';

try {

    $documents = $db->collection('users')->documents();

    foreach ($documents as $document) {

        if (!$document->exists()) {
            continue;
        }

        $data = $document->data();
        $data['_id'] = $document->id();
        $users[] = $data;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// ---- Design-only helpers (no data logic) ----
$activePage = 'users';

$roleBadgeMap = [
    'super_admin' => 'bg-purple-100 text-purple-800 border-purple-200',
    'admin'       => 'bg-blue-100 text-blue-800 border-blue-200',
    'staff'       => 'bg-slate-100 text-slate-700 border-slate-200',
    'resident'    => 'bg-teal-100 text-teal-800 border-teal-200',
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Users</title>
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
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">Users</h1>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">Residents and personnel</p>
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

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between gap-3 p-4 sm:p-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 sm:text-xl">User Information</h2>
                    <p class="text-xs text-slate-500 sm:text-sm">Residents and administrators registered in CitiFix</p>
                </div>
                <div class="flex shrink-0 items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-sm text-brand-700">
                    <span class="inline-block h-2 w-2 rounded-full bg-brand-500"></span>
                    <strong><?= count($users) ?></strong>
                    <span>user<?= count($users) === 1 ? '' : 's' ?></span>
                </div>
            </div>

            <?php if ($error): ?>

                <div class="mx-4 mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:mx-6" role="alert"><?= htmlspecialchars($error) ?></div>

            <?php elseif (empty($users)): ?>

                <div class="border-t border-slate-100 py-14 text-center">
                    <div class="text-4xl">👤</div>
                    <p class="mt-2 text-sm text-slate-500">No users found.</p>
                </div>

            <?php else: ?>

                <!-- TABLE (sm and up) -->
                <div class="hidden overflow-x-auto border-t border-slate-100 sm:block">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <th class="px-4 py-3 sm:px-6">Name</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">Department</th>
                                <th class="px-4 py-3 sm:px-6">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users as $user):

                            $role   = strtolower($user['role'] ?? 'resident');
                            $active = $user['isActive'] ?? true;
                            $deptId = $user['departmentId'] ?? null;
                            $badge  = $roleBadgeMap[$role] ?? $roleBadgeMap['staff'];

                        ?>
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3.5 font-semibold text-slate-800 sm:px-6"><?= htmlspecialchars($user['fullName'] ?? 'No Name') ?></td>
                                <td class="px-4 py-3.5 text-slate-600"><?= htmlspecialchars($user['email'] ?? 'No Email') ?></td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $badge ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-600">
                                    <?php if ($role === 'super_admin'): ?>
                                        <em>All departments</em>
                                    <?php elseif ($role === 'resident'): ?>
                                        <em>—</em>
                                    <?php else: ?>
                                        <?= htmlspecialchars(department_name($db, $deptId)) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3.5 sm:px-6">
                                    <?php if ($active): ?>
                                        <span class="inline-block rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                    <?php else: ?>
                                        <span class="inline-block rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- CARDS (below sm) -->
                <div class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
                    <?php foreach ($users as $user):

                        $role   = strtolower($user['role'] ?? 'resident');
                        $active = $user['isActive'] ?? true;
                        $deptId = $user['departmentId'] ?? null;
                        $badge  = $roleBadgeMap[$role] ?? $roleBadgeMap['staff'];

                    ?>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <strong class="block truncate text-slate-900"><?= htmlspecialchars($user['fullName'] ?? 'No Name') ?></strong>
                                    <span class="block truncate text-xs text-slate-500"><?= htmlspecialchars($user['email'] ?? 'No Email') ?></span>
                                </div>
                                <?php if ($active): ?>
                                    <span class="inline-block shrink-0 rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                <?php else: ?>
                                    <span class="inline-block shrink-0 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                <span class="inline-block rounded-full border px-2.5 py-0.5 font-semibold <?= $badge ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?></span>
                                <?php if ($role === 'super_admin'): ?>
                                    <em>All departments</em>
                                <?php elseif ($role === 'resident'): ?>
                                    <em>—</em>
                                <?php else: ?>
                                    <?= htmlspecialchars(department_name($db, $deptId)) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </section>

    </main>
</div>

</body>
</html>