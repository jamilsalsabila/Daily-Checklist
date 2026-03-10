<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

auth_bootstrap_session();

if (admin_is_authenticated()) {
    $to = trim((string) ($_GET['next'] ?? 'admin.php'));
    header('Location: ' . ($to !== '' ? $to : 'admin.php'));
    exit;
}

$error = '';
$next = trim((string) ($_GET['next'] ?? ($_POST['next'] ?? 'admin.php')));
$next = str_replace(["\r", "\n"], '', $next);
if ($next === '' || str_starts_with($next, 'http://') || str_starts_with($next, 'https://') || str_starts_with($next, '//')) {
    $next = 'admin.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    enforce_login_rate_limit();

    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!admin_login($email, $password, $error)) {
            register_login_failure();
        } else {
            $target = $next !== '' ? $next : 'admin.php';
            if (!str_starts_with($target, '/')) {
                $target = ltrim($target, '/');
            }
            header('Location: ' . $target);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/css/base/global.css">
    <link rel="stylesheet" href="assets/css/base/tokens.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/pages/login.css">
    <script src="assets/js/modules/password-toggle.js" defer></script>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="auth-badge">Secure Access</div>
            <h1>Admin Login</h1>

            <?php if (!admin_is_configured()): ?>
                <div class="notice error">Admin belum dikonfigurasi. Isi `.env` dengan `ADMIN_EMAIL` dan `ADMIN_PASSWORD_HASH`.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="notice error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <label class="meta">
                    <span>Email Admin</span>
                    <input type="email" name="email" required autocomplete="username">
                </label>
                <label class="meta">
                    <span>Password</span>
                    <div class="password-field">
                        <input id="admin-password" type="password" name="password" required autocomplete="current-password">
                        <button
                            type="button"
                            class="password-toggle"
                            data-password-toggle
                            data-target="admin-password"
                            aria-label="Tampilkan password"
                            aria-pressed="false"
                        >
                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12C4.8 7.8 8.2 5.7 12 5.7C15.8 5.7 19.2 7.8 22 12C19.2 16.2 15.8 18.3 12 18.3C8.2 18.3 4.8 16.2 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="12" cy="12" r="3.1" fill="none" stroke="currentColor" stroke-width="1.8"/>
                            </svg>
                        </button>
                    </div>
                </label>
                <button class="btn btn-primary" type="submit">Login</button>
            </form>
        </section>
    </main>
</body>
</html>
