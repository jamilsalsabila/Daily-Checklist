<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: admin.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: admin.php?status=csrf_error');
    exit;
}

$templateIdRaw = trim((string) ($_POST['template_id'] ?? ''));
if ($templateIdRaw === '' || !ctype_digit($templateIdRaw)) {
    header('Location: admin.php?status=template_delete_failed');
    exit;
}

try {
    $deleted = $database->deleteTemplate((int) $templateIdRaw);
    header('Location: admin.php?status=' . ($deleted ? 'template_deleted' : 'template_delete_failed'));
    exit;
} catch (Throwable $exception) {
    header('Location: admin.php?status=template_delete_failed');
    exit;
}
