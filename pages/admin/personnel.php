<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';

// 🔒 Super admin only
if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can manage personnel.');
}

$personnel = [];
$error = '';

$id     = $_GET['id'] ?? $_POST['id'] ?? '';
$isEdit = $id !== '';
$formError = '';

$person = [
    'fullName'     => '',
    'email'        => '',
    'role'         => 'staff',
    'departmentId' => '',
    'isActive'     => true,
];

// ---- List ----
try {
    // Pull all users whose role is staff/admin/super_admin
    foreach ($db->collection('users')->documents() as $doc) {
        if (!$doc->exists()) continue;

        $data = $doc->data();
        $role = strtolower($data['role'] ?? 'resident');

        // Skip residents
        if ($role === 'resident') continue;

        $data['_id'] = $doc->id();
        $personnel[] = $data;
    }

    usort($personnel, function ($a, $b) {
        return strcmp($a['fullName'] ?? '', $b['fullName'] ?? '');
    });

} catch (Exception $e) {
    $error = $e->getMessage();
}

// ---- Load existing (edit) ----
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $doc = $db->collection('users')->document($id)->snapshot();
        if ($doc->exists()) {
            $person = array_merge($person, $doc->data());
        } else {
            $formError = 'Personnel not found.';
            $isEdit = false;
        }
    } catch (Exception $e) {
        $formError = 'Load failed: ' . $e->getMessage();
        $isEdit = false;
    }
}

// ---- Submit ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName   = trim($_POST['fullName'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = strtolower(trim($_POST['role'] ?? 'staff'));
    $department = trim($_POST['departmentId'] ?? '');
    $isActive   = !empty($_POST['isActive']);

    $allowedRoles = ['staff', 'admin', 'super_admin'];

    // Validation
    if ($fullName === '' || $email === '') {
        $formError = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $formError = 'Invalid role selected.';
    } elseif ($role !== 'super_admin' && $department === '') {
        $formError = 'Please choose a department for this account.';
    } elseif (!$isEdit && $password === '') {
        $formError = 'Password is required for new personnel.';
    } elseif ($isEdit && $id === $ADMIN_ID && $role !== 'super_admin') {
        $formError = 'You cannot demote your own account.';
    } elseif ($isEdit && $id === $ADMIN_ID && !$isActive) {
        $formError = 'You cannot deactivate your own account.';
    }

    // Check duplicate email
    if ($formError === '') {
        try {
            $dup = $db->collection('users')->where('email', '=', $email)->documents();
            foreach ($dup as $d) {
                if ($d->exists() && $d->id() !== $id) {
                    $formError = 'Email is already used by another account.';
                    break;
                }
            }
        } catch (Exception $e) {
            $formError = 'Email check failed: ' . $e->getMessage();
        }
    }

    // Save
    if ($formError === '') {
        try {
            $data = [
                'fullName'     => $fullName,
                'email'        => $email,
                'role'         => $role,
                'departmentId' => $role === 'super_admin' ? null : $department,
                'isActive'     => $isActive,
                'updatedAt'    => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ];

            if ($password !== '') {
                $data['password'] = $password;   // TODO: password_hash later
            }

            if ($isEdit) {
                $db->collection('users')->document($id)->set($data, ['merge' => true]);
            } else {
                $data['createdAt'] = new \Google\Cloud\Core\Timestamp(new \DateTime());
                $data['createdBy'] = $ADMIN_EMAIL;
                $db->collection('users')->add($data);
            }

            $_SESSION['flash'] = [
                'type' => 'success',
                'msg'  => $isEdit ? 'Personnel updated.' : 'Personnel created.',
            ];
            header('Location: personnel.php');
            exit;

        } catch (Exception $e) {
            $formError = 'Save failed: ' . $e->getMessage();
        }
    }

    $person = array_merge($person, $_POST);
}

// ---- Session flash (one-time) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Design-only helpers (no data logic) ----
$activePage = 'personnel';

// The add/edit dialog opens server-side: ?add=1, ?id=..., or a failed POST.
$modalOpen = $_SERVER['REQUEST_METHOD'] === 'POST' || $id !== '' || isset($_GET['add']);

$roleBadgeMap = [
    'super_admin' => 'bg-purple-100 text-purple-800 border-purple-200',
    'admin'       => 'bg-blue-100 text-blue-800 border-blue-200',
    'staff'       => 'bg-slate-100 text-slate-700 border-slate-200',
];

$inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Personnel</title>
</head>

<body class="bg-slate-50 text-slate-700 antialiased<?= $modalOpen ? ' overflow-hidden' : '' ?>">

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
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">Personnel</h1>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">Admin and staff accounts</p>
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

        <?php if ($flash !== null): ?>
            <?php
                $flashType = $flash['type'] ?? 'success';
                $flashMsg  = $flash['msg']  ?? '';
                $flashClasses = $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700';
                $flashIcon = $flashType === 'success' ? '✅' : '⚠️';
            ?>
            <div class="mb-4 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm <?= $flashClasses ?>" role="status">
                <span><?= $flashIcon ?></span>
                <span><?= htmlspecialchars($flashMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between gap-3 p-4 sm:p-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Personnel List</h2>
                    <p class="text-xs text-slate-500 sm:text-sm"><?= count($personnel) ?> account(s)</p>
                </div>
                <a href="personnel.php?add=1" class="shrink-0 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-800">+ Add Personnel</a>
            </div>

            <?php if (empty($personnel)): ?>
                <div class="border-t border-slate-100 py-14 text-center">
                    <div class="text-4xl">👥</div>
                    <p class="mt-2 text-sm text-slate-500">No personnel records yet.</p>
                    <p class="text-xs text-slate-400">Click "+ Add Personnel" to create the first admin or staff account.</p>
                </div>
            <?php else: ?>

                <!-- TABLE (sm and up) -->
                <div class="hidden overflow-x-auto border-t border-slate-100 sm:block">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <th class="px-4 py-3 sm:px-6">Name</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">Department</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right sm:px-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($personnel as $p):
                            $role   = strtolower($p['role'] ?? 'staff');
                            $active = $p['isActive'] ?? true;
                            $deptId = $p['departmentId'] ?? null;
                            $badge  = $roleBadgeMap[$role] ?? $roleBadgeMap['staff'];
                        ?>
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3.5 font-semibold text-slate-800 sm:px-6"><?= htmlspecialchars($p['fullName'] ?? 'Unknown') ?></td>
                                <td class="px-4 py-3.5 text-slate-600"><?= htmlspecialchars($p['email'] ?? '') ?></td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $badge ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-600">
                                    <?php if ($role === 'super_admin'): ?>
                                        <em>All departments</em>
                                    <?php else: ?>
                                        <?= htmlspecialchars(department_name($db, $deptId)) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3.5">
                                    <?php if ($active): ?>
                                        <span class="inline-block rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                    <?php else: ?>
                                        <span class="inline-block rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right sm:px-6">
                                    <a href="personnel.php?id=<?= urlencode($p['_id']) ?>" class="inline-flex items-center rounded-lg bg-brand-700 px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-800">Edit</a>
                                    <?php if ($p['_id'] !== $ADMIN_ID): ?>
                                        <a href="personnel_delete.php?id=<?= urlencode($p['_id']) ?>"
                                           onclick="return confirm('Delete this personnel? This cannot be undone.');"
                                           class="ml-1 inline-flex items-center rounded-lg bg-red-50 px-3.5 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- CARDS (below sm) -->
                <div class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
                    <?php foreach ($personnel as $p):
                        $role   = strtolower($p['role'] ?? 'staff');
                        $active = $p['isActive'] ?? true;
                        $deptId = $p['departmentId'] ?? null;
                        $badge  = $roleBadgeMap[$role] ?? $roleBadgeMap['staff'];
                    ?>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <strong class="block truncate text-slate-900"><?= htmlspecialchars($p['fullName'] ?? 'Unknown') ?></strong>
                                    <span class="block truncate text-xs text-slate-500"><?= htmlspecialchars($p['email'] ?? '') ?></span>
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
                                <?php else: ?>
                                    <?= htmlspecialchars(department_name($db, $deptId)) ?>
                                <?php endif; ?>
                            </p>
                            <div class="mt-3 flex gap-2">
                                <a href="personnel.php?id=<?= urlencode($p['_id']) ?>" class="flex-1 rounded-lg bg-brand-700 py-2.5 text-center text-sm font-semibold text-white transition-colors hover:bg-brand-800">Edit</a>
                                <?php if ($p['_id'] !== $ADMIN_ID): ?>
                                    <a href="personnel_delete.php?id=<?= urlencode($p['_id']) ?>"
                                       onclick="return confirm('Delete this personnel? This cannot be undone.');"
                                       class="flex-1 rounded-lg bg-red-50 py-2.5 text-center text-sm font-semibold text-red-700 transition-colors hover:bg-red-100">Delete</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </section>
    </main>
</div>

<!-- ADD / EDIT DIALOG: bottom sheet on mobile, centered dialog on sm+ -->
<div id="person-modal" class="fixed inset-0 z-[70] <?= $modalOpen ? 'flex' : 'hidden' ?> items-end justify-center bg-slate-900/60 sm:items-center sm:p-4" onclick="if (event.target === this) window.location.href = 'personnel.php'">
    <div class="flex max-h-[90dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900"><?= $isEdit ? 'Edit Personnel' : 'Add Personnel' ?></h3>
                <p class="text-xs text-slate-500"><?= $isEdit
                    ? 'Update this account\'s details or department.'
                    : 'Create a new admin or staff account and assign a department.' ?></p>
            </div>
            <a href="personnel.php" class="flex h-9 w-9 items-center justify-center rounded-lg text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">×</a>
        </div>

        <form method="POST" action="personnel.php" class="flex min-h-0 flex-1 flex-col">

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">

                <?php if ($formError): ?>
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"><?= htmlspecialchars($formError) ?></div>
                <?php endif; ?>

                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                <?php endif; ?>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="fullName">Full Name</label>
                        <input type="text" id="fullName" name="fullName" required class="<?= $inputClass ?>"
                               value="<?= htmlspecialchars($person['fullName'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="email">Email</label>
                        <input type="email" id="email" name="email" required class="<?= $inputClass ?>"
                               value="<?= htmlspecialchars($person['email'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="password">
                            Password
                            <?= $isEdit ? '<span class="font-normal text-slate-400">(leave blank to keep current)</span>' : '' ?>
                        </label>
                        <input type="password" id="password" name="password" class="<?= $inputClass ?>" <?= $isEdit ? '' : 'required' ?>>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="roleSelect">Role</label>
                        <select name="role" id="roleSelect" required onchange="toggleDept()" class="<?= $inputClass ?>">
                            <option value="staff"       <?= ($person['role'] ?? '') === 'staff'       ? 'selected' : '' ?>>Staff (view-only)</option>
                            <option value="admin"       <?= ($person['role'] ?? '') === 'admin'       ? 'selected' : '' ?>>Admin (can edit)</option>
                            <option value="super_admin" <?= ($person['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin (all departments)</option>
                        </select>
                    </div>

                    <div id="deptGroup">
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="deptSelect">Department</label>
                        <select name="departmentId" id="deptSelect" class="<?= $inputClass ?>">
                            <option value="">— Select Department —</option>
                            <?php foreach (all_departments($db) as $deptId => $deptName): ?>
                                <option value="<?= htmlspecialchars($deptId) ?>"
                                    <?= ($person['departmentId'] ?? '') === $deptId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($deptName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Required for Staff and Admin. Super Admin sees all departments.</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="isActive" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                            <?= !empty($person['isActive']) ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-end gap-2 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-5 pt-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px));">
                <a href="personnel.php" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">Cancel</a>
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800">Save</button>
            </div>

        </form>
    </div>
</div>

<script>
function toggleDept() {
    const role = document.getElementById('roleSelect').value;
    const group = document.getElementById('deptGroup');
    const select = document.getElementById('deptSelect');

    if (role === 'super_admin') {
        group.style.opacity = '0.4';
        select.removeAttribute('required');
    } else {
        group.style.opacity = '1';
        select.setAttribute('required', 'required');
    }
}
toggleDept();

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !document.getElementById('person-modal').classList.contains('hidden')) {
        window.location.href = 'personnel.php';
    }
});
</script>

</body>
</html>