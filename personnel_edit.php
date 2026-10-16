<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/firebase.php';
require_once __DIR__ . '/config/department_map.php';

// 🔒 Super admin only
if (!$IS_SUPER_ADMIN) {
    http_response_code(403);
    die('Only super administrators can manage personnel.');
}

$id     = $_GET['id'] ?? $_POST['id'] ?? '';
$isEdit = $id !== '';
$error  = '';

$person = [
    'fullName'     => '',
    'email'        => '',
    'role'         => 'staff',
    'departmentId' => '',
    'isActive'     => true,
];

// Load existing
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $doc = $db->collection('users')->document($id)->snapshot();
        if ($doc->exists()) {
            $person = array_merge($person, $doc->data());
        } else {
            $error = 'Personnel not found.';
            $isEdit = false;
        }
    } catch (Exception $e) {
        $error = 'Load failed: ' . $e->getMessage();
        $isEdit = false;
    }
}

// Submit
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
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = 'Invalid role selected.';
    } elseif ($role !== 'super_admin' && $department === '') {
        $error = 'Please choose a department for this account.';
    } elseif (!$isEdit && $password === '') {
        $error = 'Password is required for new personnel.';
    } elseif ($isEdit && $id === $ADMIN_ID && $role !== 'super_admin') {
        $error = 'You cannot demote your own account.';
    } elseif ($isEdit && $id === $ADMIN_ID && !$isActive) {
        $error = 'You cannot deactivate your own account.';
    }

    // Check duplicate email
    if ($error === '') {
        try {
            $dup = $db->collection('users')->where('email', '=', $email)->documents();
            foreach ($dup as $d) {
                if ($d->exists() && $d->id() !== $id) {
                    $error = 'Email is already used by another account.';
                    break;
                }
            }
        } catch (Exception $e) {
            $error = 'Email check failed: ' . $e->getMessage();
        }
    }

    // Save
    if ($error === '') {
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
            $error = 'Save failed: ' . $e->getMessage();
        }
    }

    $person = array_merge($person, $_POST);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'Edit' : 'Add' ?> Personnel — CitiFix</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="admin-layout">

    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="sidebar-building">🏙️</div>
            <div class="sidebar-title">CITIFIX</div>
            <div class="sidebar-subtitle">MANILA</div>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">🏠 <span>Home</span></a>
            <a href="reports.php" class="menu-item">📋 <span>Reports</span></a>
            <a href="map.php" class="menu-item">🗺️ <span>Map View</span></a>
            <a href="personnel.php" class="menu-item active">👥 <span>Personnel</span></a>
            <a href="users.php" class="menu-item">👤 <span>Users</span></a>
            <a href="settings.php" class="menu-item">⚙️ <span>Settings</span></a>
            <a href="logout.php" class="menu-item logout-menu">🚪 <span>Logout</span></a>
        </nav>
    </aside>

    <main class="main-content">

        <header class="top-header">
            <div>
                <h1><?= $isEdit ? 'Edit Personnel' : 'Add Personnel' ?></h1>
                <p><?= $isEdit
                    ? 'Update this account\'s details or department.'
                    : 'Create a new admin or staff account and assign a department.' ?></p>
            </div>
        </header>

        <section class="dashboard-card">

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" style="max-width:640px;">

                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                <?php endif; ?>

                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="fullName" required
                           value="<?= htmlspecialchars($person['fullName'] ?? '') ?>">
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($person['email'] ?? '') ?>">
                </div>

                <div class="input-group">
                    <label>
                        Password
                        <?= $isEdit ? '(leave blank to keep current)' : '' ?>
                    </label>
                    <input type="password" name="password" <?= $isEdit ? '' : 'required' ?>>
                </div>

                <div class="input-group">
                    <label>Role</label>
                    <select name="role" id="roleSelect" required onchange="toggleDept()">
                        <option value="staff"       <?= ($person['role'] ?? '') === 'staff'       ? 'selected' : '' ?>>Staff (view-only)</option>
                        <option value="admin"       <?= ($person['role'] ?? '') === 'admin'       ? 'selected' : '' ?>>Admin (can edit)</option>
                        <option value="super_admin" <?= ($person['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin (all departments)</option>
                    </select>
                </div>

                <div class="input-group" id="deptGroup">
                    <label>Department</label>
                    <select name="departmentId" id="deptSelect">
                        <option value="">— Select Department —</option>
                        <?php foreach (all_departments($db) as $deptId => $deptName): ?>
                            <option value="<?= htmlspecialchars($deptId) ?>"
                                <?= ($person['departmentId'] ?? '') === $deptId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($deptName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Required for Staff and Admin. Super Admin sees all departments.</small>
                </div>

                <div class="input-group">
                    <label>
                        <input type="checkbox" name="isActive" value="1"
                            <?= !empty($person['isActive']) ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="login-button">SAVE</button>
                    <a href="personnel.php" style="margin-left:12px;">Cancel</a>
                </div>

            </form>

        </section>
    </main>
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
</script>

</body>
</html>