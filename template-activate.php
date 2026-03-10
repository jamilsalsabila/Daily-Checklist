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
    header('Location: admin.php?status=template_activate_failed');
    exit;
}

$action = trim((string) ($_POST['action'] ?? 'publish'));

try {
    if ($action === 'unpublish') {
        $changed = $database->deactivateTemplate((int) $templateIdRaw);
        header('Location: admin.php?status=' . ($changed ? 'template_unpublished' : 'template_activate_failed'));
        exit;
    }

    $changed = $database->activateTemplate((int) $templateIdRaw);
    header('Location: admin.php?status=' . ($changed ? 'template_published' : 'template_activate_failed'));
    exit;
} catch (Throwable $exception) {
    header('Location: admin.php?status=template_activate_failed');
    exit;
}
