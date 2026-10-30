<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';

// 🔒 Super admin only
require_super_admin();

$personnel = [];
$error = '';

$id     = $_GET['id'] ?? $_POST['id'] ?? '';
$isEdit = $id !== '';
$formError = '';

$person = [
    'fullName'     => '',
    'email'        => '',
    'departmentId' => '',
    'isActive'     => true,
];

// ---- List ----
try {
    foreach ($db->collection('admins')->documents() as $doc) {
        if (!$doc->exists()) continue;

        $data = $doc->data();
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
        $doc = $db->collection('admins')->document($id)->snapshot();
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
    $department = trim($_POST['departmentId'] ?? '');
    $isActive   = !empty($_POST['isActive']);

    // Validation
    if ($fullName === '' || $email === '') {
        $formError = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Please enter a valid email address.';
    } elseif ($department === '') {
        $formError = 'Please choose a department for this admin.';
    } elseif (!$isEdit && $password === '') {
        $formError = 'Password is required for new personnel.';
    }

    // Check duplicate email (admins + super_admins share the same login page)
    if ($formError === '') {
        try {
            foreach (['admins', 'super_admins'] as $col) {
                $dup = $db->collection($col)->where('email', '=', $email)->documents();
                foreach ($dup as $d) {
                    $isSelf = ($col === 'admins' && $d->id() === $id);
                    if ($d->exists() && !$isSelf) {
                        $formError = 'Email is already used by another account.';
                        break 2;
                    }
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
                'departmentId' => $department,
                'isActive'     => $isActive,
                'updatedAt'    => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ];

            if ($password !== '') {
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            if ($isEdit) {
                $db->collection('admins')->document($id)->set($data, ['merge' => true]);
            } else {
                $data['createdAt'] = new \Google\Cloud\Core\Timestamp(new \DateTime());
                $data['createdBy'] = $ADMIN_EMAIL;
                $db->collection('admins')->add($data);
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

$inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Personnel</title>
<style>
/* Flash popup (success / error after saving or deleting personnel) */
@keyframes flash-pop-in {
    from { opacity: 0; transform: scale(0.92) translateY(8px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
#flash-popup { transition: opacity 0.3s ease; }
#flash-popup.flash-hide { opacity: 0; pointer-events: none; }
#flash-popup .flash-card { animation: flash-pop-in 0.25s ease-out; }
</style>
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
                    <p class="truncate text-xs text-slate-500 sm:text-sm">Admin accounts</p>
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
                $flashType    = $flash['type'] ?? 'success';
                $flashMsg     = $flash['msg']  ?? '';
                $flashSuccess = $flashType === 'success';
            ?>
            <!-- FLASH POPUP: success auto-hides after ~2s, errors stay until closed -->
            <div id="flash-popup" role="alert" aria-live="assertive" data-type="<?= $flashSuccess ? 'success' : 'error' ?>"
                 class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/40 p-4"
                 onclick="if (event.target === this) closeFlashPopup()">
                <div class="flash-card w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full <?= $flashSuccess ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?>">
                        <?php if ($flashSuccess): ?>
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        <?php endif; ?>
                    </div>
                    <p class="mt-4 text-base font-semibold text-slate-900"><?= htmlspecialchars($flashMsg) ?></p>
                    <?php if (!$flashSuccess): ?>
                        <button type="button" onclick="closeFlashPopup()" class="mt-5 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-slate-800">Close</button>
                    <?php endif; ?>
                </div>
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
                    <p class="text-xs text-slate-400">Click "+ Add Personnel" to create the first admin account.</p>
                </div>
            <?php else: ?>

                <!-- TABLE (sm and up) -->
                <div class="hidden overflow-x-auto border-t border-slate-100 sm:block">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <th class="px-4 py-3 sm:px-6">Name</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Department</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right sm:px-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($personnel as $p):
                            $active = $p['isActive'] ?? true;
                            $deptId = $p['departmentId'] ?? null;
                        ?>
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3.5 font-semibold text-slate-800 sm:px-6"><?= htmlspecialchars($p['fullName'] ?? 'Unknown') ?></td>
                                <td class="px-4 py-3.5 text-slate-600"><?= htmlspecialchars($p['email'] ?? '') ?></td>
                                <td class="px-4 py-3.5 text-xs text-slate-600"><?= htmlspecialchars(department_name($db, $deptId)) ?></td>
                                <td class="px-4 py-3.5">
                                    <?php if ($active): ?>
                                        <span class="inline-block rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                    <?php else: ?>
                                        <span class="inline-block rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right sm:px-6">
                                    <a href="personnel.php?id=<?= urlencode($p['_id']) ?>" class="inline-flex items-center rounded-lg bg-brand-700 px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-800">Edit</a>
                                    <a href="personnel_delete.php?id=<?= urlencode($p['_id']) ?>"
                                       onclick="return confirm('Delete this personnel? This cannot be undone.');"
                                       class="ml-1 inline-flex items-center rounded-lg bg-red-50 px-3.5 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- CARDS (below sm) -->
                <div class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
                    <?php foreach ($personnel as $p):
                        $active = $p['isActive'] ?? true;
                        $deptId = $p['departmentId'] ?? null;
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
                            <p class="mt-2 text-xs text-slate-600"><?= htmlspecialchars(department_name($db, $deptId)) ?></p>
                            <div class="mt-3 flex gap-2">
                                <a href="personnel.php?id=<?= urlencode($p['_id']) ?>" class="flex-1 rounded-lg bg-brand-700 py-2.5 text-center text-sm font-semibold text-white transition-colors hover:bg-brand-800">Edit</a>
                                <a href="personnel_delete.php?id=<?= urlencode($p['_id']) ?>"
                                   onclick="return confirm('Delete this personnel? This cannot be undone.');"
                                   class="flex-1 rounded-lg bg-red-50 py-2.5 text-center text-sm font-semibold text-red-700 transition-colors hover:bg-red-100">Delete</a>
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
                    ? 'Update this admin\'s details or department.'
                    : 'Create a new admin account and assign a department.' ?></p>
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
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="deptSelect">Department</label>
                        <select name="departmentId" id="deptSelect" required class="<?= $inputClass ?>">
                            <option value="">— Select Department —</option>
                            <?php foreach (all_departments($db) as $deptId => $deptName): ?>
                                <option value="<?= htmlspecialchars($deptId) ?>"
                                    <?= ($person['departmentId'] ?? '') === $deptId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($deptName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !document.getElementById('person-modal').classList.contains('hidden')) {
        window.location.href = 'personnel.php';
    }
});

// ---- Flash popup: success fades out after ~2s, errors stay until closed ----
let flashHideTimer = null;

function closeFlashPopup() {
    const popup = document.getElementById('flash-popup');
    if (!popup) return;

    if (flashHideTimer) {
        clearTimeout(flashHideTimer);
        flashHideTimer = null;
    }

    popup.classList.add('flash-hide');
    setTimeout(function () {
        if (popup.parentNode) popup.parentNode.removeChild(popup);
    }, 300);
}

(function () {
    const popup = document.getElementById('flash-popup');
    if (!popup) return;

    if (popup.dataset.type === 'success') {
        flashHideTimer = setTimeout(closeFlashPopup, 2000);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeFlashPopup();
    });
})();
</script>

</body>
</html>