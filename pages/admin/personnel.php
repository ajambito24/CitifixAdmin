<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';

// 🔒 Super admin only
require_super_admin();

$personnel = [];
$error = '';

$postAction = $_SERVER['REQUEST_METHOD'] === 'POST' ? trim($_POST['action'] ?? '') : '';

$id     = $_GET['id'] ?? $_POST['id'] ?? '';
$isEdit = $id !== '';
$formError = '';

$person = [
    'fullName'     => '',
    'email'        => '',
    'departmentId' => '',
    'isActive'     => true,
];

// ---- Department form state ----
$deptForm       = ['code' => '', 'name' => '', 'description' => ''];
$isDeptEdit     = false;
$deptFormError  = '';
$deptModalOpen  = false;

const CF_MIN_PASSWORD = 8;

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

// ---- Load existing personnel (edit) ----
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

// ---- Load existing department (edit) via ?dept=CODE ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['dept']) && $_GET['dept'] !== '') {
    $reqDept = (string) $_GET['dept'];
    try {
        if (strpos($reqDept, '/') !== false) {
            throw new Exception('Invalid department code.');
        }
        $dsnap = $db->collection('departments')->document($reqDept)->snapshot();
        if ($dsnap->exists()) {
            $dd = $dsnap->data();
            $deptForm = [
                'code'        => $dsnap->id(),
                'name'        => $dd['name'] ?? '',
                'description' => $dd['description'] ?? '',
            ];
            $isDeptEdit    = true;
            $deptModalOpen = true;
        } else {
            $error = 'Department not found.';
        }
    } catch (Exception $e) {
        $error = 'Department load failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['add_dept'])) {
    $deptModalOpen = true;
}

// ---- Submit: personnel ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'save_personnel') {

    $firstName  = trim($_POST['firstName'] ?? '');
    $surname    = trim($_POST['surname'] ?? '');
    $fullName   = trim($firstName . ' ' . $surname);
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirmPassword'] ?? '';
    $department = trim($_POST['departmentId'] ?? '');
    $isActive   = !empty($_POST['isActive']);

    // Validation
    if ($firstName === '' || $surname === '' || $email === '') {
        $formError = 'First name, surname and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Please enter a valid email address.';
    } elseif ($department === '') {
        $formError = 'Please choose a department for this admin.';
    } elseif (!$isEdit && $password === '') {
        $formError = 'Password is required for new personnel.';
    } elseif ($password !== '' && strlen($password) < CF_MIN_PASSWORD) {
        $formError = 'Password must be at least ' . CF_MIN_PASSWORD . ' characters.';
    } elseif ($password !== $confirm) {
        $formError = 'Passwords do not match.';
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
    $person['isActive'] = $isActive;
}

// ---- Submit: add / edit department ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'save_department') {

    $isDeptEdit = ($_POST['dept_mode'] ?? '') === 'edit';
    $deptCode   = trim($_POST['deptCode'] ?? '');
    $deptName   = trim($_POST['deptName'] ?? '');
    $deptDesc   = trim($_POST['deptDescription'] ?? '');

    if (!$isDeptEdit) {
        $deptCode = strtoupper($deptCode);
    }

    $deptForm = ['code' => $deptCode, 'name' => $deptName, 'description' => $deptDesc];

    if ($deptCode === '' || $deptName === '') {
        $deptFormError = 'Department code and name are required.';
    } elseif (strpos($deptCode, '/') !== false) {
        $deptFormError = 'Invalid department code.';
    } elseif (!$isDeptEdit && !preg_match('/^[A-Z0-9]{2,10}$/', $deptCode)) {
        $deptFormError = 'Department code must be 2–10 letters or numbers (no spaces or symbols).';
    } elseif (mb_strlen($deptName) > 120) {
        $deptFormError = 'Department name is too long (max 120 characters).';
    } elseif (mb_strlen($deptDesc) > 200) {
        $deptFormError = 'Description is too long (max 200 characters).';
    }

    if ($deptFormError === '') {
        try {
            $ref  = $db->collection('departments')->document($deptCode);
            $snap = $ref->snapshot();

            if ($isDeptEdit) {
                if (!$snap->exists()) {
                    $deptFormError = 'Department not found.';
                } else {
                    $ref->set([
                        'name'        => $deptName,
                        'description' => $deptDesc,
                        'updatedAt'   => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                    ], ['merge' => true]);
                }
            } else {
                if ($snap->exists()) {
                    $deptFormError = 'A department with code "' . $deptCode . '" already exists.';
                } else {
                    $ref->create([
                        'name'        => $deptName,
                        'description' => $deptDesc,
                        'createdAt'   => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                        'createdBy'   => $ADMIN_EMAIL,
                    ]);
                }
            }

            if ($deptFormError === '') {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'msg'  => $isDeptEdit ? 'Department updated.' : 'Department created.',
                ];
                header('Location: personnel.php');
                exit;
            }

        } catch (Exception $e) {
            $deptFormError = 'Save failed: ' . $e->getMessage();
        }
    }

    $deptModalOpen = true;
}

// ---- Submit: delete department (blocked while it is still in use) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'delete_department') {

    $delCode = trim($_POST['dept_code'] ?? '');
    $flashMsg = '';
    $flashType = 'success';

    try {
        if ($delCode === '' || strpos($delCode, '/') !== false) {
            throw new Exception('Invalid department code.');
        }

        $ref  = $db->collection('departments')->document($delCode);
        $snap = $ref->snapshot();

        if (!$snap->exists()) {
            $flashType = 'error';
            $flashMsg  = 'Department not found.';
        } else {
            $delName = $snap->data()['name'] ?? $delCode;
            $usedBy  = '';

            foreach ([
                'admins'     => 'admin accounts',
                'categories' => 'categories',
                'reports'    => 'reports',
            ] as $col => $label) {
                foreach ($db->collection($col)->where('departmentId', '=', $delCode)->limit(1)->documents() as $u) {
                    if ($u->exists()) {
                        $usedBy = $label;
                        break 2;
                    }
                }
            }

            if ($usedBy !== '') {
                $flashType = 'error';
                $flashMsg  = 'Cannot delete "' . $delName . '": it is still used by ' . $usedBy . '. Reassign them first.';
            } else {
                $ref->delete();
                $flashMsg = 'Department deleted.';
            }
        }
    } catch (Exception $e) {
        $flashType = 'error';
        $flashMsg  = 'Delete failed: ' . $e->getMessage();
    }

    $_SESSION['flash'] = ['type' => $flashType, 'msg' => $flashMsg];
    header('Location: personnel.php');
    exit;
}

// ---- Departments list (full data for the Departments section) ----
$departments = [];
try {
    foreach ($db->collection('departments')->documents() as $d) {
        if (!$d->exists()) continue;
        $dd = $d->data();
        $departments[] = [
            'id'          => $d->id(),
            'name'        => $dd['name'] ?? $d->id(),
            'description' => $dd['description'] ?? '',
        ];
    }
    usort($departments, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
} catch (Exception $e) {
    if ($error === '') $error = 'Departments load failed: ' . $e->getMessage();
}

// ---- Session flash (one-time) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Design-only helpers (no data logic) ----
$activePage = 'personnel';

// The add/edit dialogs open server-side: ?add=1, ?id=..., ?add_dept=1, ?dept=..., or a failed POST.
$modalOpen = ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'save_personnel')
          || $id !== ''
          || isset($_GET['add']);

$anyModalOpen = $modalOpen || $deptModalOpen;

$inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';

// Split for the add/edit dialog inputs (display only; saved value is still fullName)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'save_personnel') {
    $personFirst   = trim($_POST['firstName'] ?? '');
    $personSurname = trim($_POST['surname'] ?? '');
} else {
    $fullForSplit  = trim($person['fullName'] ?? '');
    $splitPos      = strrpos($fullForSplit, ' ');
    $personFirst   = $splitPos === false ? $fullForSplit : substr($fullForSplit, 0, $splitPos);
    $personSurname = $splitPos === false ? '' : substr($fullForSplit, $splitPos + 1);
}

$eyeOn  = '<svg class="eye-on h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>';
$eyeOff = '<svg class="eye-off hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Personnel</title>
<style>
/* Flash popup (success / error after saving or deleting) */
@keyframes flash-pop-in {
    from { opacity: 0; transform: scale(0.92) translateY(8px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
#flash-popup { transition: opacity 0.3s ease; }
#flash-popup.flash-hide { opacity: 0; pointer-events: none; }
#flash-popup .flash-card { animation: flash-pop-in 0.25s ease-out; }
</style>
</head>

<body class="bg-slate-50 text-slate-700 antialiased<?= $anyModalOpen ? ' overflow-hidden' : '' ?>">

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
                    <p class="truncate text-xs text-slate-500 sm:text-sm">Admin accounts &amp; departments</p>
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

        <!-- ================= PERSONNEL LIST ================= -->
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

        <!-- ================= DEPARTMENTS LIST ================= -->
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between gap-3 p-4 sm:p-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Departments</h2>
                    <p class="text-xs text-slate-500 sm:text-sm"><?= count($departments) ?> department(s)</p>
                </div>
                <a href="personnel.php?add_dept=1" class="shrink-0 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-800">+ Add Department</a>
            </div>

            <?php if (empty($departments)): ?>
                <div class="border-t border-slate-100 py-14 text-center">
                    <div class="text-4xl">🏢</div>
                    <p class="mt-2 text-sm text-slate-500">No departments yet.</p>
                    <p class="text-xs text-slate-400">Click "+ Add Department" to create one.</p>
                </div>
            <?php else: ?>

                <!-- TABLE (sm and up) -->
                <div class="hidden overflow-x-auto border-t border-slate-100 sm:block">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                                <th class="px-4 py-3 sm:px-6">Code</th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-right sm:px-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($departments as $dept): ?>
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3.5 font-mono text-xs font-semibold text-slate-600 sm:px-6"><?= htmlspecialchars($dept['id']) ?></td>
                                <td class="px-4 py-3.5 font-semibold text-slate-800"><?= htmlspecialchars($dept['name']) ?></td>
                                <td class="px-4 py-3.5 text-slate-600">
                                    <?php if ($dept['description'] !== ''): ?>
                                        <?= htmlspecialchars($dept['description']) ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right sm:px-6">
                                    <a href="personnel.php?dept=<?= urlencode($dept['id']) ?>" class="inline-flex items-center rounded-lg bg-brand-700 px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-800">Edit</a>
                                    <button type="button"
                                            data-code="<?= htmlspecialchars($dept['id']) ?>"
                                            data-name="<?= htmlspecialchars($dept['name']) ?>"
                                            onclick="openDeleteDept(this)"
                                            class="ml-1 inline-flex items-center rounded-lg bg-red-50 px-3.5 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- CARDS (below sm) -->
                <div class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
                    <?php foreach ($departments as $dept): ?>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <strong class="min-w-0 text-slate-900"><?= htmlspecialchars($dept['name']) ?></strong>
                                <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-600"><?= htmlspecialchars($dept['id']) ?></span>
                            </div>
                            <?php if ($dept['description'] !== ''): ?>
                                <p class="mt-1 text-xs text-slate-600"><?= htmlspecialchars($dept['description']) ?></p>
                            <?php endif; ?>
                            <div class="mt-3 flex gap-2">
                                <a href="personnel.php?dept=<?= urlencode($dept['id']) ?>" class="flex-1 rounded-lg bg-brand-700 py-2.5 text-center text-sm font-semibold text-white transition-colors hover:bg-brand-800">Edit</a>
                                <button type="button"
                                        data-code="<?= htmlspecialchars($dept['id']) ?>"
                                        data-name="<?= htmlspecialchars($dept['name']) ?>"
                                        onclick="openDeleteDept(this)"
                                        class="flex-1 rounded-lg bg-red-50 py-2.5 text-center text-sm font-semibold text-red-700 transition-colors hover:bg-red-100">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </section>
    </main>
</div>

<!-- ADD / EDIT PERSONNEL DIALOG: bottom sheet on mobile, centered dialog on sm+ -->
<div id="person-modal" <?= $modalOpen ? 'data-server-modal' : '' ?> class="fixed inset-0 z-[70] <?= $modalOpen ? 'flex' : 'hidden' ?> items-end justify-center bg-slate-900/60 sm:items-center sm:p-4" onclick="if (event.target === this) window.location.href = 'personnel.php'">
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

        <form method="POST" action="personnel.php" class="flex min-h-0 flex-1 flex-col" id="person-form" autocomplete="off">
            <input type="hidden" name="action" value="save_personnel">

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">

                <?php if ($formError): ?>
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"><?= htmlspecialchars($formError) ?></div>
                <?php endif; ?>

                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                <?php endif; ?>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500" for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" required class="<?= $inputClass ?>"
                                   value="<?= htmlspecialchars($personFirst) ?>">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500" for="surname">Surname</label>
                            <input type="text" id="surname" name="surname" required class="<?= $inputClass ?>"
                                   value="<?= htmlspecialchars($personSurname) ?>">
                        </div>
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
                        <div class="relative">
                            <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?= CF_MIN_PASSWORD ?>" class="<?= $inputClass ?> pr-11" <?= $isEdit ? '' : 'required' ?>>
                            <button type="button" data-toggle-password="password" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-400 transition-colors hover:text-slate-700">
                                <?= $eyeOn ?><?= $eyeOff ?>
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">At least <?= CF_MIN_PASSWORD ?> characters.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="confirmPassword">Confirm Password</label>
                        <div class="relative">
                            <input type="password" id="confirmPassword" name="confirmPassword" autocomplete="new-password" class="<?= $inputClass ?> pr-11" <?= $isEdit ? '' : 'required' ?>>
                            <button type="button" data-toggle-password="confirmPassword" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-400 transition-colors hover:text-slate-700">
                                <?= $eyeOn ?><?= $eyeOff ?>
                            </button>
                        </div>
                        <p id="pw-hint" class="mt-1 hidden text-xs font-medium text-red-600" role="alert"></p>
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
                <button type="submit" id="person-save" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-brand-700">Save</button>
            </div>

        </form>
    </div>
</div>

<!-- ADD / EDIT DEPARTMENT DIALOG -->
<div id="dept-modal" <?= $deptModalOpen ? 'data-server-modal' : '' ?> class="fixed inset-0 z-[70] <?= $deptModalOpen ? 'flex' : 'hidden' ?> items-end justify-center bg-slate-900/60 sm:items-center sm:p-4" onclick="if (event.target === this) window.location.href = 'personnel.php'">
    <div class="flex max-h-[90dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900"><?= $isDeptEdit ? 'Edit Department' : 'Add Department' ?></h3>
                <p class="text-xs text-slate-500"><?= $isDeptEdit
                    ? 'Update the name or description. The code cannot be changed.'
                    : 'Create a new department that admins and categories can use.' ?></p>
            </div>
            <a href="personnel.php" class="flex h-9 w-9 items-center justify-center rounded-lg text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">×</a>
        </div>

        <form method="POST" action="personnel.php" class="flex min-h-0 flex-1 flex-col" autocomplete="off">
            <input type="hidden" name="action" value="save_department">
            <input type="hidden" name="dept_mode" value="<?= $isDeptEdit ? 'edit' : 'add' ?>">

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">

                <?php if ($deptFormError): ?>
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"><?= htmlspecialchars($deptFormError) ?></div>
                <?php endif; ?>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="deptCode">Department Code</label>
                        <?php if ($isDeptEdit): ?>
                            <input type="text" id="deptCode" name="deptCode" readonly class="<?= $inputClass ?> cursor-not-allowed bg-slate-100 font-mono text-slate-500"
                                   value="<?= htmlspecialchars($deptForm['code']) ?>">
                        <?php else: ?>
                            <input type="text" id="deptCode" name="deptCode" required minlength="2" maxlength="10" pattern="[A-Za-z0-9]{2,10}"
                                   placeholder="e.g. DEPS" style="text-transform: uppercase;"
                                   class="<?= $inputClass ?> font-mono"
                                   value="<?= htmlspecialchars($deptForm['code']) ?>">
                            <p class="mt-1 text-[11px] text-slate-400">2–10 letters or numbers. Used as the department ID and can't be changed later.</p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="deptName">Department Name</label>
                        <input type="text" id="deptName" name="deptName" required maxlength="120" class="<?= $inputClass ?>"
                               placeholder="e.g. Department of Environment and Public Service"
                               value="<?= htmlspecialchars($deptForm['name']) ?>">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="deptDescription">Description <span class="font-normal text-slate-400">(optional)</span></label>
                        <input type="text" id="deptDescription" name="deptDescription" maxlength="200" class="<?= $inputClass ?>"
                               placeholder="e.g. Flooding and Garbage"
                               value="<?= htmlspecialchars($deptForm['description']) ?>">
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-end gap-2 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-5 pt-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px));">
                <a href="personnel.php" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">Cancel</a>
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800"><?= $isDeptEdit ? 'Save Changes' : 'Create Department' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE DEPARTMENT CONFIRMATION -->
<div id="dept-delete-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/60 p-4" onclick="if (event.target === this) closeDeleteDept()">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="dept-delete-title">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <h3 id="dept-delete-title" class="mt-4 text-lg font-bold text-slate-900">Delete department?</h3>
        <p class="mt-1 text-sm text-slate-600">
            <strong id="dept-delete-name" class="text-slate-900"></strong>
            <span class="font-mono text-xs text-slate-500">(<span id="dept-delete-code"></span>)</span>
            will be permanently deleted. This cannot be undone.
        </p>
        <p class="mt-2 text-xs text-slate-400">A department that is still used by admins, categories, or reports can't be deleted.</p>
        <form method="POST" action="personnel.php" class="mt-5 flex gap-2">
            <input type="hidden" name="action" value="delete_department">
            <input type="hidden" name="dept_code" id="dept-delete-input" value="">
            <button type="button" onclick="closeDeleteDept()" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-700">Delete</button>
        </form>
    </div>
</div>

<script>
const MIN_PASSWORD = <?= (int) CF_MIN_PASSWORD ?>;

// ---- Esc: close delete confirm first, otherwise close an open server-side modal ----
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    const del = document.getElementById('dept-delete-modal');
    if (del && !del.classList.contains('hidden')) {
        closeDeleteDept();
        return;
    }
    if (document.querySelector('[data-server-modal]')) {
        window.location.href = 'personnel.php';
    }
});

// ---- Delete department confirmation ----
function openDeleteDept(btn) {
    document.getElementById('dept-delete-name').textContent = btn.dataset.name || btn.dataset.code;
    document.getElementById('dept-delete-code').textContent = btn.dataset.code;
    document.getElementById('dept-delete-input').value = btn.dataset.code;

    const modal = document.getElementById('dept-delete-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeDeleteDept() {
    const modal = document.getElementById('dept-delete-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

// ---- Password eye toggles ----
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const input = document.getElementById(btn.dataset.togglePassword);
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('.eye-on').classList.toggle('hidden', show);
        btn.querySelector('.eye-off').classList.toggle('hidden', !show);
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});

// ---- Live password / confirm check (server re-checks on submit) ----
(function () {
    const pw   = document.getElementById('password');
    const cf   = document.getElementById('confirmPassword');
    const hint = document.getElementById('pw-hint');
    const save = document.getElementById('person-save');
    if (!pw || !cf || !hint || !save) return;

    function check() {
        let msg = '';

        if (pw.value !== '' && pw.value.length < MIN_PASSWORD) {
            msg = 'Password must be at least ' + MIN_PASSWORD + ' characters.';
        } else if ((pw.value !== '' || cf.value !== '') && pw.value !== cf.value) {
            // Don't nag while the confirm field is still empty and being filled in
            if (cf.value !== '' || pw.value === '') {
                msg = 'Passwords do not match.';
            }
        }

        hint.textContent = msg;
        hint.classList.toggle('hidden', msg === '');
        cf.classList.toggle('border-red-400', msg === 'Passwords do not match.');

        const mismatch = (pw.value !== cf.value);
        const tooShort = pw.value !== '' && pw.value.length < MIN_PASSWORD;
        save.disabled = mismatch || tooShort;
    }

    pw.addEventListener('input', check);
    cf.addEventListener('input', check);
    check();
})();

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