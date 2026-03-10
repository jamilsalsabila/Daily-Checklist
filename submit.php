<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php');
    exit;
}

$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$floorCaptain = trim((string) ($_POST['floor_captain'] ?? ''));
$signatureStrokesRaw = (string) ($_POST['signature_strokes'] ?? '');
$signaturePreview = (string) ($_POST['signature_preview'] ?? '');

$signatureStrokes = json_decode($signatureStrokesRaw, true);

if ($tanggal === '' || $floorCaptain === '' || !is_array($signatureStrokes) || $signatureStrokes === [] || $signaturePreview === '') {
    http_response_code(422);
    echo 'Data wajib belum lengkap. Silakan kembali dan lengkapi form.';
    exit;
}

$openingChecks = [];
foreach (FormDefinition::openingSections() as $section) {
    $posted = $_POST['opening_checks'][$section['key']] ?? [];
    $values = [];
    foreach ($section['items'] as $index => $_) {
        $values[$index] = isset($posted[$index]) ? 1 : 0;
    }
    $openingChecks[$section['key']] = $values;
}

$teamControl = [];
foreach (FormDefinition::teamControlItems() as $key => $_) {
    $teamControl[$key] = (string) ($_POST['team_control'][$key] ?? '');
}

$serviceControl = [];
foreach (FormDefinition::serviceControlItems() as $key => $_) {
    $serviceControl[$key] = (string) ($_POST['service_control'][$key] ?? '');
}

$floorAwareness = [];
foreach (FormDefinition::floorAwarenessItems() as $key => $_) {
    $floorAwareness[$key] = (string) ($_POST['floor_awareness'][$key] ?? '');
}

$closingControl = [];
foreach (FormDefinition::closingControlItems() as $key => $_) {
    $closingControl[$key] = isset($_POST['closing_control'][$key]) ? 1 : 0;
}

$payload = [
    'tanggal' => $tanggal,
    'floor_captain' => $floorCaptain,
    'opening_checks' => $openingChecks,
    'team_control' => $teamControl,
    'service_control' => $serviceControl,
    'floor_awareness' => $floorAwareness,
    'customer_experience' => [
        'ada_komplain' => (string) ($_POST['customer_experience']['ada_komplain'] ?? ''),
        'jenis_komplain' => trim((string) ($_POST['customer_experience']['jenis_komplain'] ?? '')),
        'ditangani_oleh' => trim((string) ($_POST['customer_experience']['ditangani_oleh'] ?? '')),
    ],
    'closing_control' => $closingControl,
    'operational_notes' => [
        'masalah_hari_ini' => trim((string) ($_POST['operational_notes']['masalah_hari_ini'] ?? '')),
        'perbaikan' => trim((string) ($_POST['operational_notes']['perbaikan'] ?? '')),
    ],
    'signature_strokes' => $signatureStrokes,
    'signature_preview' => $signaturePreview,
];

try {
    $code = $database->insertSubmission($payload);
    $submission = $database->findSubmissionByCode($code);

    if ($submission !== null) {
        save_submission_pdf($submission);
    }

    header('Location: thank-you.php?code=' . urlencode($code));
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Terjadi error saat menyimpan checklist. ';
    echo 'Periksa izin folder storage atau konfigurasi server. ';
    echo 'Detail: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
