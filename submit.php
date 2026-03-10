<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php');
    exit;
}

$signatureStrokesRaw = (string) ($_POST['signature_strokes'] ?? '');
$signaturePreview = (string) ($_POST['signature_preview'] ?? '');
$templateIdRaw = trim((string) ($_POST['template_id'] ?? ''));
$templateVersionIdRaw = trim((string) ($_POST['template_version_id'] ?? ''));
$templateSlug = trim((string) ($_POST['template_slug'] ?? ''));

$signatureStrokes = json_decode($signatureStrokesRaw, true);
$templateId = ctype_digit($templateIdRaw) ? (int) $templateIdRaw : null;
$templateVersionId = ctype_digit($templateVersionIdRaw) ? (int) $templateVersionIdRaw : null;

if (!is_array($signatureStrokes) || $signatureStrokes === [] || $signaturePreview === '') {
    http_response_code(422);
    echo 'Data wajib belum lengkap. Silakan kembali dan lengkapi form.';
    exit;
}

$activeTemplate = null;
if ($templateSlug !== '') {
    $activeTemplate = $database->findTemplateBySlug($templateSlug, true);
}
if ($activeTemplate === null) {
    $activeTemplate = $database->getActiveTemplate();
}
$schema = TemplateSchema::fromTemplate($activeTemplate);
$templateId = (int) ($activeTemplate['id'] ?? $templateId ?? 0) ?: $templateId;
$templateVersionId = (int) ($activeTemplate['current_version_id'] ?? $templateVersionId ?? 0) ?: $templateVersionId;
$responses = TemplateSchema::collectResponsesFromPost($schema, (array) ($_POST['responses'] ?? []));
$missingRequired = TemplateSchema::missingRequiredFields($schema, $responses);
if ($missingRequired !== []) {
    http_response_code(422);
    echo 'Masih ada field wajib yang belum diisi.';
    exit;
}
$meta = TemplateSchema::deriveMeta($schema, $responses);

$payload = [
    'tanggal' => $meta['tanggal'],
    'floor_captain' => $meta['floor_captain'],
    'opening_checks' => [],
    'team_control' => [],
    'service_control' => [],
    'floor_awareness' => [],
    'customer_experience' => [],
    'closing_control' => [],
    'operational_notes' => [],
    'responses' => $responses,
    'signature_strokes' => $signatureStrokes,
    'signature_preview' => $signaturePreview,
    'template_id' => $templateId,
    'template_version_id' => $templateVersionId,
];

try {
    $code = $database->insertSubmission($payload);
    $submission = $database->findSubmissionByCode($code);

    if ($submission !== null) {
        save_submission_pdf($submission);
    }

    $redirect = 'thank-you.php?code=' . urlencode($code);
    if ($templateSlug !== '') {
        $redirect .= '&template=' . urlencode($templateSlug);
    }
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Terjadi error saat menyimpan checklist. ';
    echo 'Periksa izin folder storage atau konfigurasi server. ';
    echo 'Detail: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
