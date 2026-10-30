<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';
require_once __DIR__ . '/../utils/icons_util.php';

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

/**
 * Firestore Timestamp / DateTime / string -> "Oct 30, 2026 · 3:45 PM" (Manila time).
 */
function users_format_date($value): string
{
    if ($value === null) return '';

    if (is_object($value) && method_exists($value, 'get')) {
        $value = $value->get();
    }

    if ($value instanceof DateTimeInterface) {
        $dt = DateTime::createFromFormat('U', (string) $value->getTimestamp());
    } elseif (is_string($value) && ($ts = strtotime($value)) !== false) {
        $dt = DateTime::createFromFormat('U', (string) $ts);
    } else {
        return '';
    }

    if (!$dt) return '';

    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format('M j, Y') . ' · ' . $dt->format('g:i A');
}

/**
 * Only the fields the modal shows (no role, no department, no termsAccepted).
 * Every value is a plain string; the modal renders "—" for empty ones.
 */
$modalUsers = [];
foreach ($users as $user) {
    $first = trim((string) ($user['firstName'] ?? ''));
    $last  = trim((string) ($user['lastName'] ?? ''));
    $full  = trim((string) ($user['fullName'] ?? ''));
    if ($full === '') $full = trim($first . ' ' . $last);

    $modalUsers[] = [
        'name'      => $full !== '' ? $full : 'No Name',
        'active'    => (bool) ($user['isActive'] ?? true),
        'firstName' => $first,
        'lastName'  => $last,
        'email'     => (string) ($user['email'] ?? ''),
        'phone'     => (string) ($user['phone'] ?? ''),
        'street'    => (string) ($user['streetAddress'] ?? ''),
        'barangay'  => (string) ($user['barangay'] ?? ''),
        'city'      => (string) ($user['city'] ?? ''),
        'joined'    => users_format_date($user['createdAt'] ?? null),
        'id'        => (string) ($user['_id'] ?? ''),
    ];
}

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
                    <div class="flex justify-center text-slate-300"><?= cf_icon('person', 'h-12 w-12') ?></div>
                    <p class="mt-2 text-sm text-slate-500">No users found.</p>
                </div>

            <?php else: ?>

                <!-- TABLE (sm and up) -->
                <div class="hidden overflow-x-auto border-t border-slate-100 sm:block">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <th class="w-[26%] px-4 py-3 sm:px-6">Name</th>
                                <th class="w-[30%] px-4 py-3">Email</th>
                                <th class="w-[14%] px-4 py-3">Role</th>
                                <th class="w-[11%] px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users as $i => $user):

                            $role   = strtolower($user['role'] ?? 'resident');
                            $active = $user['isActive'] ?? true;
                            $badge  = $roleBadgeMap[$role] ?? $roleBadgeMap['staff'];

                        ?>
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-2.5 font-semibold text-slate-800 sm:px-6"><?= htmlspecialchars($modalUsers[$i]['name']) ?></td>
                                <td class="px-4 py-2.5 text-slate-600"><?= htmlspecialchars($user['email'] ?? 'No Email') ?></td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $badge ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <?php if ($active): ?>
                                        <span class="inline-block rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                    <?php else: ?>
                                        <span class="inline-block rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-1.5 align-middle">
                                    <button type="button" data-user-index="<?= $i ?>"
                                            aria-label="View details for <?= htmlspecialchars($modalUsers[$i]['name'], ENT_QUOTES) ?>"
                                            title="View details"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-brand-50 hover:text-brand-700">
                                        <?= cf_icon('eye', 'h-5 w-5') ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- CARDS (below sm) -->
                <div class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
                    <?php foreach ($users as $i => $user):

                        $role   = strtolower($user['role'] ?? 'resident');
                        $active = $user['isActive'] ?? true;
                        $badge  = $roleBadgeMap[$role] ?? $roleBadgeMap['staff'];

                    ?>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <strong class="block truncate text-slate-900"><?= htmlspecialchars($modalUsers[$i]['name']) ?></strong>
                                    <span class="block truncate text-xs text-slate-500"><?= htmlspecialchars($user['email'] ?? 'No Email') ?></span>
                                </div>
                                <?php if ($active): ?>
                                    <span class="inline-block shrink-0 rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                <?php else: ?>
                                    <span class="inline-block shrink-0 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $badge ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?></span>
                                <button type="button" data-user-index="<?= $i ?>"
                                        aria-label="View details for <?= htmlspecialchars($modalUsers[$i]['name'], ENT_QUOTES) ?>"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-brand-50 hover:text-brand-700">
                                    <?= cf_icon('eye', 'h-5 w-5') ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </section>

    </main>
</div>

<!-- USER DETAIL MODAL (view only) -->
<div id="user-modal" class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true" aria-labelledby="um-name">

    <div id="um-backdrop" class="absolute inset-0 bg-slate-900/55"></div>

    <div class="absolute inset-x-0 bottom-0 flex max-h-[90dvh] flex-col rounded-t-2xl bg-white shadow-xl sm:inset-0 sm:m-auto sm:h-fit sm:max-h-[85dvh] sm:w-full sm:max-w-lg sm:rounded-2xl"
         style="padding-bottom: env(safe-area-inset-bottom, 0px);">

        <!-- Header -->
        <div class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
            <div id="um-avatar" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-700 text-lg font-extrabold text-white"></div>
            <div class="min-w-0 flex-1">
                <h2 id="um-name" class="truncate text-lg font-bold text-slate-900"></h2>
                <span id="um-status" class="mt-1 inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold"></span>
            </div>
            <button type="button" id="um-close" aria-label="Close"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                <?= cf_icon('x-lg', 'h-5 w-5') ?>
            </button>
        </div>

        <!-- Body -->
        <dl id="um-body" class="grid grid-cols-1 gap-x-6 gap-y-4 overflow-y-auto p-4 text-sm sm:grid-cols-2 sm:p-6"></dl>

    </div>
</div>

<script>
(function () {
    var USERS = <?= json_encode($modalUsers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

    // label, key, spans both columns on sm+
    var FIELDS = [
        ['First name',     'firstName', false],
        ['Last name',      'lastName',  false],
        ['Email',          'email',     true],
        ['Phone',          'phone',     false],
        ['Barangay',       'barangay',  false],
        ['Street address', 'street',    true],
        ['City',           'city',      false],
        ['Member since',   'joined',    false],
        ['User ID',        'id',        true]
    ];

    var ACTIVE_CLASS   = 'border-green-200 bg-green-100 text-green-800';
    var INACTIVE_CLASS = 'border-slate-200 bg-slate-100 text-slate-600';

    var modal  = document.getElementById('user-modal');
    var body   = document.getElementById('um-body');
    var nameEl = document.getElementById('um-name');
    var avatar = document.getElementById('um-avatar');
    var status = document.getElementById('um-status');
    var closeB = document.getElementById('um-close');
    var lastTrigger = null;

    function fill(u) {
        nameEl.textContent = u.name;
        avatar.textContent = (u.name.charAt(0) || '?').toUpperCase();

        status.textContent = u.active ? 'Active' : 'Inactive';
        status.className = 'mt-1 inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold ' +
            (u.active ? ACTIVE_CLASS : INACTIVE_CLASS);

        body.textContent = '';
        FIELDS.forEach(function (f) {
            var wrap = document.createElement('div');
            wrap.className = 'min-w-0' + (f[2] ? ' sm:col-span-2' : '');

            var dt = document.createElement('dt');
            dt.className = 'text-xs font-semibold text-slate-500';
            dt.textContent = f[0];

            var dd = document.createElement('dd');
            var val = u[f[1]];
            var empty = !val;
            dd.className = 'mt-0.5 break-words font-medium ' + (empty ? 'text-slate-400' : 'text-slate-800') +
                (f[1] === 'id' ? ' font-mono text-xs' : '');
            dd.textContent = empty ? '—' : val;

            wrap.appendChild(dt);
            wrap.appendChild(dd);
            body.appendChild(wrap);
        });
    }

    function openModal(index, trigger) {
        var u = USERS[index];
        if (!u) return;
        lastTrigger = trigger || null;
        fill(u);
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        closeB.focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        if (lastTrigger) lastTrigger.focus();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-user-index]');
        if (btn) openModal(parseInt(btn.getAttribute('data-user-index'), 10), btn);
    });

    closeB.addEventListener('click', closeModal);
    document.getElementById('um-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
})();
</script>

</body>
</html>