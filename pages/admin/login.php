<?php

session_start();

if (
    isset($_SESSION['admin_logged_in']) &&
    $_SESSION['admin_logged_in'] === true
) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../../config/firebase.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {

        $docs = $db->collection('users')
            ->where('email', '=', $email)
            ->limit(1)
            ->documents();

        $found = null;
        foreach ($docs as $doc) {
            if ($doc->exists()) {
                $found = $doc->data();
                $found['_id'] = $doc->id();
            }
        }

        
        // Upgrade to password_verify() later.
        $stored = $found['password'] ?? '';

        if ($found && $stored !== '' && hash_equals($stored, $password)) {

            if (empty($found['isActive'])) {
                $error = 'Your account is inactive. Contact the super administrator.';
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_email']     = $email;

                header('Location: dashboard.php');
                exit;
            }

        } else {
            $error = 'Invalid email or password.';
        }

    } catch (Exception $e) {
        $error = 'Login error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CitiFix Manila - Admin Login</title>

<link rel="stylesheet" href="../../css/style.css">

</head>

<body class="login-page">

<div class="login-wrapper">

    <div class="login-card">

        <div class="sidebar-logo">

            <img
                src="../../images/logo.png"
                alt="CitiFix Manila"
                class="sidebar-logo-img"
            >

            <div class="sidebar-title">
                CITIFIX
            </div>

            <div class="sidebar-subtitle">
                MANILA
            </div>

        </div>

        <h2>Admin Login</h2>

        <p class="login-text">
            Sign in to manage CitiFix reports.
        </p>

        <?php if ($error !== ''): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="input-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    placeholder="admin@citifix.com"
                    required
                >

            </div>

            <div class="input-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >

            </div>

            <button
                type="submit"
                class="login-button"
            >
                LOGIN
            </button>

        </form>

    </div>

</div>

</body>
</html>