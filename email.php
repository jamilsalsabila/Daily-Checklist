<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/SmtpMailer.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php');
    exit;
}

$code = trim((string) ($_POST['code'] ?? ''));
$recipient = trim((string) ($_POST['recipient_email'] ?? ''));

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    header('Location: thank-you.php?code=' . urlencode($code) . '&email_status=invalid');
    exit;
}

$submission = $database->findSubmissionByCode($code);
if ($submission === null) {
    http_response_code(404);
    echo 'Data submission tidak ditemukan.';
    exit;
}

$path = pdf_file_path($submission['submission_code']);
if (!is_file($path)) {
    save_submission_pdf($submission);
}

$pdfData = file_get_contents($path);
if ($pdfData === false) {
    header('Location: thank-you.php?code=' . urlencode($code) . '&email_status=failed');
    exit;
}

$subject = 'Floor Captain Control Sheet - ' . $submission['tanggal'];
$bodyText = "Terlampir PDF Floor Captain Control Sheet.\n\n" .
    "Tanggal: {$submission['tanggal']}\n" .
    "Floor Captain: {$submission['floor_captain']}\n" .
    "Kode Submission: {$submission['submission_code']}\n";

$mailer = new SmtpMailer([
    'host' => Env::get('SMTP_HOST', '') ?? '',
    'port' => Env::get('SMTP_PORT', '587') ?? '587',
    'encryption' => Env::get('SMTP_ENCRYPTION', 'tls') ?? 'tls',
    'username' => Env::get('SMTP_USERNAME', '') ?? '',
    'password' => Env::get('SMTP_PASSWORD', '') ?? '',
    'from_email' => Env::get('SMTP_FROM_EMAIL', '') ?? '',
    'from_name' => Env::get('SMTP_FROM_NAME', 'Daily Checklist') ?? 'Daily Checklist',
]);

$error = null;
if (!$mailer->isConfigured()) {
    header('Location: thank-you.php?code=' . urlencode($code) . '&email_status=not_configured');
    exit;
}

$sent = $mailer->sendWithAttachment(
    $recipient,
    $subject,
    $bodyText,
    $submission['submission_code'] . '.pdf',
    $pdfData,
    $error
);

$status = $sent ? 'sent' : 'failed';
$query = 'code=' . urlencode($code) . '&email_status=' . $status;
if (!$sent && $error !== null && $error !== '') {
    $query .= '&email_error=' . urlencode($error);
}

header('Location: thank-you.php?' . $query);
exit;
