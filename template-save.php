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
$templateId = ctype_digit($templateIdRaw) ? (int) $templateIdRaw : null;
$name = trim((string) ($_POST['name'] ?? ''));
$slugRaw = trim((string) ($_POST['slug'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$schemaJson = trim((string) ($_POST['schema_json'] ?? ''));
$activateNow = isset($_POST['activate_now']) || isset($_POST['publish_now']);

$slug = strtolower(preg_replace('/[^a-z0-9\-]+/', '-', $slugRaw) ?? '');
$slug = trim($slug, '-');

$formRedirect = 'template-form.php' . ($templateId !== null ? '?id=' . $templateId : '');

if ($name === '' || $slug === '' || $schemaJson === '') {
    header('Location: ' . $formRedirect . ($templateId !== null ? '&' : '?') . 'status=invalid_input');
    exit;
}

$decoded = json_decode($schemaJson, true);
if (!is_array($decoded)) {
    header('Location: ' . $formRedirect . ($templateId !== null ? '&' : '?') . 'status=invalid_json');
    exit;
}

$schema = TemplateSchema::normalize($decoded);

if (!$database->isTemplateSlugAvailable($slug, $templateId)) {
    header('Location: ' . $formRedirect . ($templateId !== null ? '&' : '?') . 'status=slug_exists');
    exit;
}

try {
    if ($templateId !== null) {
        $saved = $database->updateTemplate($templateId, $slug, $name, $description, $schema);
        if (!$saved) {
            header('Location: ' . $formRedirect . '&status=save_failed');
            exit;
        }
        if ($activateNow) {
            $database->activateTemplate($templateId);
        }
    } else {
        $database->createTemplate($slug, $name, $description, $schema, $activateNow);
    }

    header('Location: admin.php?status=template_saved');
    exit;
} catch (Throwable $exception) {
    header('Location: ' . $formRedirect . ($templateId !== null ? '&' : '?') . 'status=save_failed');
    exit;
}
