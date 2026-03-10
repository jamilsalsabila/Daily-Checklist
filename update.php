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
    http_response_code(422);
    echo 'Data wajib belum lengkap untuk update.';
    exit;
}

$existing = $database->findSubmissionByCode($code);
if ($existing === null) {
    http_response_code(404);
    echo 'Submission tidak ditemukan.';
    exit;
}

$schema = TemplateSchema::fromSubmission($database, $existing);
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
    'opening_checks' => $existing['opening_checks'] ?? [],
    'team_control' => $existing['team_control'] ?? [],
    'service_control' => $existing['service_control'] ?? [],
    'floor_awareness' => $existing['floor_awareness'] ?? [],
    'customer_experience' => $existing['customer_experience'] ?? [],
    'closing_control' => $existing['closing_control'] ?? [],
    'operational_notes' => $existing['operational_notes'] ?? [],
    'responses' => $responses,
    'signature_strokes' => $existing['signature_strokes'] ?? [],
    'signature_preview' => (string) ($existing['signature_preview'] ?? ''),
];

try {
    $database->updateSubmissionByCode($code, $payload);
    $updated = $database->findSubmissionByCode($code);
    if ($updated !== null) {
        save_submission_pdf($updated);
    }
    header('Location: admin.php?code=' . urlencode($code) . '&status=updated');
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Terjadi error saat update checklist. ';
    echo 'Detail: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
