<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo 'Token keamanan tidak valid.';
    exit;
}

$code = trim((string) ($_POST['code'] ?? ''));
if ($code === '') {
    header('Location: admin.php?status=delete_failed');
    exit;
}

try {
    $deleted = $database->deleteSubmissionByCode($code);
    $path = pdf_file_path($code);
    if (is_file($path)) {
        @unlink($path);
    }
    header('Location: admin.php?status=' . ($deleted ? 'deleted' : 'delete_failed'));
    exit;
} catch (Throwable $exception) {
    header('Location: admin.php?status=delete_failed');
    exit;
}
