<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$code = (string) ($_GET['code'] ?? '');
$submission = $database->findSubmissionByCode($code);

if ($submission === null) {
    http_response_code(404);
    echo 'PDF tidak ditemukan.';
    exit;
}

$path = pdf_file_path($submission['submission_code']);
save_submission_pdf($submission);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $submission['submission_code'] . '.pdf"');
readfile($path);
exit;
