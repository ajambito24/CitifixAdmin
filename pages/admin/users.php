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

// ---- Delete handler (called by the delete confirmation modal) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    header('Content-Type: application/json');

    $uid = trim((string) ($_POST['userId'] ?? ''));
    if ($uid === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing user ID.']);
        exit;
    }

    try {
        $db->collection('users')->document($uid)->delete();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
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
                    <strong id="user-count"><?= count($users) ?></strong>
                    <span id="user-count-label">user<?= count($users) === 1 ? '' : 's' ?></span>
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

                <!-- Shown by JS once every user has been deleted -->
                <div id="users-empty" class="hidden border-t border-slate-100 py-14 text-center">
                    <div class="flex justify-center text-slate-300"><?= cf_icon('person', 'h-12 w-12') ?></div>
                    <p class="mt-2 text-sm text-slate-500">No users found.</p>
                </div>

                <div id="users-lists">

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
                            <tr data-user-row="<?= $i ?>" class="transition-colors hover:bg-slate-50">
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
                        <div data-user-row="<?= $i ?>" class="p-4">
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

                </div><!-- /#users-lists -->

            <?php endif; ?>

        </section>

    </main>
</div>

<!-- USER DETAIL MODAL -->
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

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 border-t border-slate-100 p-4 sm:px-6">
            <button type="button" id="um-delete"
                    class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10zM10 11v6M14 11v6"/></svg>
                Delete user
            </button>
        </div>

    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div id="delete-modal" class="fixed inset-0 z-[80] hidden" role="alertdialog" aria-modal="true" aria-labelledby="dm-title" aria-describedby="dm-text">

    <div id="dm-backdrop" class="absolute inset-0 bg-slate-900/60"></div>

    <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-4">
    <div class="pointer-events-auto w-full max-w-md rounded-2xl bg-white p-5 shadow-xl sm:p-6">

        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L2.4 18a2 2 0 001.7 3h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <h3 id="dm-title" class="text-base font-bold text-slate-900">Delete this user?</h3>
                <p id="dm-text" class="mt-1 text-sm text-slate-600">
                    You are about to permanently delete
                    <strong id="dm-name" class="break-words text-slate-900"></strong>
                    <span id="dm-email" class="block break-all text-xs text-slate-500"></span>
                    This action cannot be undone.
                </p>
            </div>
        </div>

        <div id="dm-error" class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></div>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" id="dm-cancel"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                Cancel
            </button>
            <button type="button" id="dm-confirm"
                    class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-60">
                Delete
            </button>
        </div>
    </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="pointer-events-none fixed left-1/2 top-4 z-[90] hidden -translate-x-1/2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg" role="status" aria-live="polite"></div>

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
    var delB   = document.getElementById('um-delete');

    var dModal   = document.getElementById('delete-modal');
    var dName    = document.getElementById('dm-name');
    var dEmail   = document.getElementById('dm-email');
    var dError   = document.getElementById('dm-error');
    var dCancel  = document.getElementById('dm-cancel');
    var dConfirm = document.getElementById('dm-confirm');
    var toastEl  = document.getElementById('toast');

    var lastTrigger  = null;
    var currentIndex = null;
    var deleting     = false;
    var toastTimer   = null;

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
        currentIndex = index;
        lastTrigger = trigger || null;
        fill(u);
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        closeB.focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        if (lastTrigger && document.body.contains(lastTrigger)) lastTrigger.focus();
        currentIndex = null;
    }

    // ---- Delete flow ----
    function setDeleting(on) {
        deleting = on;
        dConfirm.disabled = on;
        dCancel.disabled = on;
        dConfirm.textContent = on ? 'Deleting...' : 'Delete';
    }

    function openConfirm() {
        var u = USERS[currentIndex];
        if (!u) return;
        dName.textContent = u.name;
        dEmail.textContent = u.email || '';
        dError.classList.add('hidden');
        dError.textContent = '';
        setDeleting(false);
        dModal.classList.remove('hidden');
        dCancel.focus();
    }

    function closeConfirm() {
        if (deleting) return;
        dModal.classList.add('hidden');
        delB.focus();
    }

    function showToast(msg) {
        toastEl.textContent = msg;
        toastEl.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.add('hidden'); }, 3000);
    }

    function updateCount() {
        // Table rows are the source of truth (cards mirror them)
        var remaining = document.querySelectorAll('tr[data-user-row]').length;
        document.getElementById('user-count').textContent = remaining;
        document.getElementById('user-count-label').textContent = remaining === 1 ? 'user' : 'users';

        if (remaining === 0) {
            document.getElementById('users-lists').classList.add('hidden');
            document.getElementById('users-empty').classList.remove('hidden');
        }
    }

    function showDeleteError(msg) {
        dError.textContent = msg || 'Something went wrong. Please try again.';
        dError.classList.remove('hidden');
    }

    function confirmDelete() {
        if (deleting) return;
        var index = currentIndex;
        var u = USERS[index];
        if (!u || !u.id) { showDeleteError('This user has no ID.'); return; }

        setDeleting(true);
        dError.classList.add('hidden');

        var form = new FormData();
        form.append('action', 'delete_user');
        form.append('userId', u.id);

        fetch(window.location.href, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (res) {
            return res.json().catch(function () { return { success: false, message: 'Unexpected server response.' }; })
                .then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (r) {
            if (!r.ok || !r.data.success) {
                setDeleting(false);
                showDeleteError(r.data && r.data.message);
                return;
            }

            // Success: remove row + card live
            document.querySelectorAll('[data-user-row="' + index + '"]').forEach(function (el) { el.remove(); });
            updateCount();

            setDeleting(false);
            dModal.classList.add('hidden');
            lastTrigger = null;
            closeModal();
            showToast(u.name + ' was deleted.');
        })
        .catch(function () {
            setDeleting(false);
            showDeleteError('Network error. Please check your connection and try again.');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-user-index]');
        if (btn) openModal(parseInt(btn.getAttribute('data-user-index'), 10), btn);
    });

    closeB.addEventListener('click', closeModal);
    document.getElementById('um-backdrop').addEventListener('click', closeModal);

    delB.addEventListener('click', openConfirm);
    dCancel.addEventListener('click', closeConfirm);
    document.getElementById('dm-backdrop').addEventListener('click', closeConfirm);
    dConfirm.addEventListener('click', confirmDelete);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!dModal.classList.contains('hidden')) { closeConfirm(); return; }
        if (!modal.classList.contains('hidden')) closeModal();
    });
})();
</script>

</body>
</html>