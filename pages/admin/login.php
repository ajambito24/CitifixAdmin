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

        // Look in super_admins first, then admins.
        $found     = null;
        $foundRole = null;

        $collections = [
            'super_admins' => 'super_admin',
            'admins'       => 'admin',
        ];

        foreach ($collections as $collection => $role) {
            $docs = $db->collection($collection)
                ->where('email', '=', $email)
                ->limit(1)
                ->documents();

            foreach ($docs as $doc) {
                if ($doc->exists()) {
                    $found        = $doc->data();
                    $found['_id'] = $doc->id();
                    $foundRole    = $role;
                    break 2;
                }
            }
        }

        $stored = $found['password'] ?? '';

        if ($found && $stored !== '' && password_verify($password, $stored)) {

            if (empty($found['isActive'])) {
                $error = 'Your account is inactive. Contact the super administrator.';
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $found['_id'];
                $_SESSION['admin_email']     = $email;
                $_SESSION['admin_role']      = $foundRole;

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
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Manila - Admin Login</title>
</head>

<body class="bg-slate-50 text-slate-700 antialiased">

<main class="flex min-h-screen flex-col lg:flex-row" style="min-height: 100dvh;">

    <!-- BRAND PANEL -->
    <section class="relative flex flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 px-[var(--su-gutter)] py-8 text-center text-white lg:w-1/2 lg:py-16"
             style="padding-top: max(2rem, env(safe-area-inset-top, 0px));">

        <!-- decorative blobs -->
        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-white/5" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-white/5" aria-hidden="true"></div>

        <div class="relative flex flex-col items-center">
            <img src="../../images/logo.png" alt="CitiFix Manila"
                 class="h-16 w-16 rounded-2xl bg-white/10 object-contain p-2 ring-1 ring-white/20 sm:h-20 sm:w-20 lg:h-28 lg:w-28 lg:p-3">

            <h1 class="mt-4 text-2xl font-extrabold tracking-[0.2em] sm:text-3xl lg:mt-6 lg:text-4xl">CITIFIX</h1>
            <p class="text-xs font-semibold tracking-[0.5em] text-brand-200 sm:text-sm lg:text-base">MANILA</p>

        </div>
    </section>

    <!-- FORM PANEL -->
    <section class="flex flex-1 items-center justify-center px-[var(--su-gutter)] py-10 sm:py-14"
             style="padding-bottom: max(2.5rem, env(safe-area-inset-bottom, 0px));">

        <div class="w-full max-w-md">

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">

                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Admin Login</h2>
                <p class="mt-1.5 text-sm text-slate-500">Sign in to manage CitiFix reports.</p>

                <?php if ($error !== ''): ?>
                    <div class="mt-5 flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="login-form" class="mt-6 space-y-5">

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="admin@citifix.com"
                                autocomplete="username"
                                required
                                class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-800 placeholder-slate-400 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4"/></svg>
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter password"
                                autocomplete="current-password"
                                required
                                class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-11 pr-12 text-sm text-slate-800 placeholder-slate-400 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                            >
                            <button type="button" id="toggle-password" aria-label="Show password"
                                    class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3.5 text-slate-400 transition-colors hover:text-slate-600">
                                <svg id="eye-open" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg id="eye-closed" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>

                    <button
                        type="submit"
                        id="login-button"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-brand-700 px-4 py-3 text-sm font-bold tracking-wide text-white shadow-sm transition-colors hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        LOGIN
                    </button>

                </form>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">&copy; <?= date('Y') ?> CitiFix Manila</p>

        </div>
    </section>

</main>

<script>
(function () {
    const pw      = document.getElementById('password');
    const toggle  = document.getElementById('toggle-password');
    const eyeOpen = document.getElementById('eye-open');
    const eyeShut = document.getElementById('eye-closed');
    const form    = document.getElementById('login-form');
    const button  = document.getElementById('login-button');

    toggle.addEventListener('click', function () {
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        eyeOpen.classList.toggle('hidden', show);
        eyeShut.classList.toggle('hidden', !show);
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    form.addEventListener('submit', function () {
        button.disabled = true;
        button.textContent = 'SIGNING IN...';
    });
})();
</script>

</body>
</html>