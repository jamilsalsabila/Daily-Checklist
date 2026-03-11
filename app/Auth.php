<?php

declare(strict_types=1);

function auth_bootstrap_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_is_authenticated(): bool
{
    auth_bootstrap_session();
    return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_email']);
}

function admin_is_configured(): bool
{
    $email = trim((string) (Env::get('ADMIN_EMAIL', '') ?? ''));
    $passwordHash = trim((string) (Env::get('ADMIN_PASSWORD_HASH', '') ?? ''));
    $passwordPlain = trim((string) (Env::get('ADMIN_PASSWORD', '') ?? ''));

    return $email !== '' && ($passwordHash !== '' || $passwordPlain !== '');
}

function admin_login(string $email, string $password, ?string &$error = null): bool
{
    auth_bootstrap_session();

    $configuredEmail = strtolower(trim((string) (Env::get('ADMIN_EMAIL', '') ?? '')));
    $configuredHash = trim((string) (Env::get('ADMIN_PASSWORD_HASH', '') ?? ''));
    $configuredPlain = (string) (Env::get('ADMIN_PASSWORD', '') ?? '');

    if ($configuredEmail === '') {
        $error = 'ADMIN_EMAIL belum diatur.';
        return false;
    }

    if ($configuredHash === '' && $configuredPlain === '') {
        $error = 'ADMIN_PASSWORD_HASH belum diatur.';
        return false;
    }

    $emailOk = hash_equals($configuredEmail, strtolower(trim($email)));
    $passwordOk = false;

    if ($configuredHash !== '') {
        $passwordOk = password_verify($password, $configuredHash);
    } else {
        $passwordOk = hash_equals($configuredPlain, $password);
    }

    if (!$emailOk || !$passwordOk) {
        $error = 'Email atau password salah.';
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = $configuredEmail;
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

    return true;
}

function admin_logout(): void
{
    auth_bootstrap_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function require_admin_auth(): void
{
    if (admin_is_authenticated()) {
        return;
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin.php');
    header('Location: login.php?next=' . urlencode($requestUri));
    exit;
}

function csrf_token(): string
{
    auth_bootstrap_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $provided): bool
{
    auth_bootstrap_session();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && is_string($provided) && hash_equals($sessionToken, $provided);
}

function issue_form_submit_token(string $scope = 'default'): string
{
    auth_bootstrap_session();
    if (!isset($_SESSION['form_submit_tokens']) || !is_array($_SESSION['form_submit_tokens'])) {
        $_SESSION['form_submit_tokens'] = [];
    }
    if (!isset($_SESSION['form_submit_tokens'][$scope]) || !is_array($_SESSION['form_submit_tokens'][$scope])) {
        $_SESSION['form_submit_tokens'][$scope] = [];
    }

    $now = time();
    foreach ($_SESSION['form_submit_tokens'][$scope] as $token => $issuedAt) {
        if (!is_int($issuedAt) || ($now - $issuedAt) > 7200) {
            unset($_SESSION['form_submit_tokens'][$scope][$token]);
        }
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['form_submit_tokens'][$scope][$token] = $now;

    if (count($_SESSION['form_submit_tokens'][$scope]) > 50) {
        asort($_SESSION['form_submit_tokens'][$scope]);
        while (count($_SESSION['form_submit_tokens'][$scope]) > 50) {
            $firstKey = array_key_first($_SESSION['form_submit_tokens'][$scope]);
            if ($firstKey === null) {
                break;
            }
            unset($_SESSION['form_submit_tokens'][$scope][$firstKey]);
        }
    }

    return $token;
}

function consume_form_submit_token(?string $provided, string $scope = 'default'): bool
{
    auth_bootstrap_session();
    if (!is_string($provided) || $provided === '') {
        return false;
    }
    $tokens = $_SESSION['form_submit_tokens'][$scope] ?? null;
    if (!is_array($tokens) || !isset($tokens[$provided])) {
        return false;
    }

    unset($_SESSION['form_submit_tokens'][$scope][$provided]);
    return true;
}

function enforce_login_rate_limit(): void
{
    auth_bootstrap_session();
    $now = time();
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
    if ($lockedUntil > $now) {
        http_response_code(429);
        echo 'Terlalu banyak percobaan login. Coba lagi beberapa saat.';
        exit;
    }
}

function register_login_failure(): void
{
    auth_bootstrap_session();
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_attempts'] = $attempts;

    if ($attempts >= 5) {
        $_SESSION['login_locked_until'] = time() + 300;
        $_SESSION['login_attempts'] = 0;
    }
}
