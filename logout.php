<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo 'Token keamanan tidak valid.';
    exit;
}

admin_logout();
header('Location: login.php');
exit;
